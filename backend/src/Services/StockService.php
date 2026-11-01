<?php

declare(strict_types=1);

namespace MediTrack\Services;

use MediTrack\Core\Database;
use MediTrack\Core\Cache;
use MediTrack\Core\Exceptions\ValidationException;
use MediTrack\Repositories\StockMovementRepository;

class StockService
{
    private Database $db;
    private Cache $cache;
    private StockMovementRepository $movementRepo;

    public function __construct(
        Database $db,
        Cache $cache,
        StockMovementRepository $movementRepo
    ) {
        $this->db = $db;
        $this->cache = $cache;
        $this->movementRepo = $movementRepo;
    }

    /**
     * Allocate required quantity of a medicine using strict FEFO (Earliest Expiry First).
     * Locks rows with SELECT ... FOR UPDATE inside a transaction to prevent race conditions & overselling.
     * Expired batches (exp_date <= today) are NEVER allocated.
     *
     * @return array List of allocations: [['batch_id' => int, 'batch_number' => string, 'quantity' => int, 'selling_price' => float, 'exp_date' => string]]
     * @throws ValidationException if insufficient active unexpired stock
     */
    public function allocate(
        int $medicineId,
        int $requiredQty,
        ?int $userId = null,
        ?string $refType = 'order',
        ?int $refId = null
    ): array {
        if ($requiredQty <= 0) {
            throw new ValidationException(['quantity' => ['Quantity to allocate must be greater than zero.']]);
        }

        $today = date('Y-m-d');

        return $this->db->transaction(function () use ($medicineId, $requiredQty, $today, $userId, $refType, $refId) {
            // 1. Lock and fetch unexpired, active batches strictly in FEFO order
            $lock = $this->db->driver() !== 'sqlite' ? ' FOR UPDATE' : '';
            $sql = 'SELECT * FROM batches
                    WHERE medicine_id = ?
                      AND exp_date > ?
                      AND (quantity - reserved_quantity) > 0
                      AND disposal_status = \'none\'
                    ORDER BY exp_date ASC, id ASC' . $lock;

            $batches = $this->db->fetchAll($sql, [$medicineId, $today]);

            // 2. Calculate total available quantity
            $totalAvailable = 0;
            foreach ($batches as $batch) {
                $totalAvailable += ((int)$batch['quantity'] - (int)$batch['reserved_quantity']);
            }

            if ($totalAvailable < $requiredQty) {
                throw new ValidationException([
                    'stock' => ["Insufficient stock. Requested: {$requiredQty}, Available unexpired: {$totalAvailable}."],
                ]);
            }

            // 3. Allocate across batches in FEFO order
            $allocations = [];
            $remaining = $requiredQty;

            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $batchAvailable = (int)$batch['quantity'] - (int)$batch['reserved_quantity'];
                if ($batchAvailable <= 0) {
                    continue;
                }

                $take = min($remaining, $batchAvailable);

                // Update reserved quantity on the batch
                $this->db->execute(
                    'UPDATE batches SET reserved_quantity = reserved_quantity + ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                    [$take, $batch['id']]
                );

                // Log stock movement
                $this->movementRepo->log(
                    (int)$batch['id'],
                    'sale',
                    -$take,
                    $refType,
                    $refId,
                    $userId
                );

                $allocations[] = [
                    'batch_id' => (int)$batch['id'],
                    'batch_number' => $batch['batch_number'],
                    'quantity' => $take,
                    'selling_price' => (float)$batch['selling_price'],
                    'exp_date' => $batch['exp_date'],
                ];

                $remaining -= $take;
            }

            $this->cache->bumpVersion('catalog');

            return $allocations;
        });
    }

    /**
     * Release previously reserved quantity back to active batch stock.
     */
    public function release(
        int $batchId,
        int $qty,
        ?int $userId = null,
        ?string $refType = null,
        ?int $refId = null
    ): bool {
        if ($qty <= 0) {
            return false;
        }

        return $this->db->transaction(function () use ($batchId, $qty, $userId, $refType, $refId) {
            $func = $this->db->driver() === 'sqlite' ? 'MAX' : 'GREATEST';
            $updated = $this->db->execute(
                "UPDATE batches SET reserved_quantity = {$func}(0, reserved_quantity - ?), updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                [$qty, $batchId]
            ) > 0;

            if ($updated) {
                $this->movementRepo->log(
                    $batchId,
                    'cancel_restock',
                    $qty,
                    $refType,
                    $refId,
                    $userId
                );
                $this->cache->bumpVersion('catalog');
            }

            return $updated;
        });
    }

    /**
     * Add restocked quantity to a batch.
     */
    public function restock(
        int $batchId,
        int $qty,
        string $reason = 'Restock',
        ?int $userId = null
    ): bool {
        if ($qty <= 0) {
            return false;
        }

        return $this->db->transaction(function () use ($batchId, $qty, $reason, $userId) {
            $updated = $this->db->execute(
                'UPDATE batches SET quantity = quantity + ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                [$qty, $batchId]
            ) > 0;

            if ($updated) {
                $this->movementRepo->log(
                    $batchId,
                    'purchase',
                    $qty,
                    'restock',
                    null,
                    $userId
                );
                $this->cache->bumpVersion('catalog');
            }

            return $updated;
        });
    }

    /**
     * Mark an expired or damaged batch as returned or disposed.
     */
    public function dispose(
        int $batchId,
        string $status,
        ?string $notes = null,
        ?int $userId = null
    ): bool {
        $status = in_array($status, ['returned', 'disposed'], true) ? $status : 'disposed';

        return $this->db->transaction(function () use ($batchId, $status, $notes, $userId) {
            $lock = $this->db->driver() !== 'sqlite' ? ' FOR UPDATE' : '';
            $batch = $this->db->fetchOne('SELECT quantity FROM batches WHERE id = ?' . $lock, [$batchId]);
            if (!$batch) {
                return false;
            }

            $currentQty = (int)$batch['quantity'];

            $updated = $this->db->execute(
                'UPDATE batches SET disposal_status = ?, quantity = 0, reserved_quantity = 0, notes = COALESCE(?, notes), updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                [$status, $notes, $batchId]
            ) > 0;

            if ($updated && $currentQty > 0) {
                $this->movementRepo->log(
                    $batchId,
                    $status === 'returned' ? 'return' : 'dispose',
                    -$currentQty,
                    'disposal',
                    null,
                    $userId
                );
            }

            $this->cache->bumpVersion('catalog');

            return $updated;
        });
    }
}
