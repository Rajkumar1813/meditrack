<?php

declare(strict_types=1);

namespace MediTrack\Services;

use MediTrack\Core\Database;
use MediTrack\Core\Cache;
use MediTrack\Core\Exceptions\ValidationException;
use MediTrack\Repositories\MedicineRepository;
use MediTrack\Repositories\BatchRepository;
use MediTrack\Repositories\CategoryRepository;
use MediTrack\Repositories\StockMovementRepository;
use MediTrack\Repositories\AuditLogRepository;

class CsvImportExportService
{
    private Database $db;
    private Cache $cache;
    private MedicineRepository $medRepo;
    private BatchRepository $batchRepo;
    private CategoryRepository $catRepo;
    private StockMovementRepository $movementRepo;
    private AuditLogRepository $auditRepo;

    public function __construct(
        Database $db,
        Cache $cache,
        MedicineRepository $medRepo,
        BatchRepository $batchRepo,
        CategoryRepository $catRepo,
        StockMovementRepository $movementRepo,
        AuditLogRepository $auditRepo
    ) {
        $this->db = $db;
        $this->cache = $cache;
        $this->medRepo = $medRepo;
        $this->batchRepo = $batchRepo;
        $this->catRepo = $catRepo;
        $this->movementRepo = $movementRepo;
        $this->auditRepo = $auditRepo;
    }

    /**
     * Generate streamed CSV contents.
     */
    public function export(): string
    {
        $sql = "
            SELECT
                m.name as medicine_name,
                m.slug,
                c.name as category,
                mf.name as manufacturer,
                m.schedule,
                m.requires_rx,
                m.pack_size,
                m.description,
                b.batch_number,
                b.quantity,
                b.purchase_price,
                b.mrp,
                b.selling_price,
                b.mfg_date,
                b.exp_date,
                r.name as rack,
                s.name as shelf,
                sp.name as supplier
            FROM medicines m
            JOIN categories c ON m.category_id = c.id
            LEFT JOIN manufacturers mf ON m.manufacturer_id = mf.id
            LEFT JOIN batches b ON m.id = b.medicine_id
            LEFT JOIN racks r ON b.rack_id = r.id
            LEFT JOIN shelves s ON b.shelf_id = s.id
            LEFT JOIN suppliers sp ON b.supplier_id = sp.id
            WHERE m.deleted_at IS NULL
            ORDER BY m.name ASC, b.exp_date ASC
        ";

        $rows = $this->db->fetchAll($sql);

        $fp = fopen('php://temp', 'r+');
        fputcsv($fp, [
            'medicine_name', 'slug', 'category', 'manufacturer', 'schedule', 'requires_rx',
            'pack_size', 'description', 'batch_number', 'quantity', 'purchase_price', 'mrp',
            'selling_price', 'mfg_date', 'exp_date', 'rack', 'shelf', 'supplier'
        ]);

        foreach ($rows as $row) {
            fputcsv($fp, [
                $row['medicine_name'] ?? '',
                $row['slug'] ?? '',
                $row['category'] ?? '',
                $row['manufacturer'] ?? '',
                $row['schedule'] ?? 'OTC',
                $row['requires_rx'] ? '1' : '0',
                $row['pack_size'] ?? '',
                $row['description'] ?? '',
                $row['batch_number'] ?? '',
                $row['quantity'] ?? '0',
                $row['purchase_price'] ?? '0.00',
                $row['mrp'] ?? '0.00',
                $row['selling_price'] ?? '0.00',
                $row['mfg_date'] ?? '',
                $row['exp_date'] ?? '',
                $row['rack'] ?? '',
                $row['shelf'] ?? '',
                $row['supplier'] ?? '',
            ]);
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return (string)$csv;
    }

    /**
     * Step 1: Preview and validate CSV rows without modifying the database.
     */
    public function preview(string $csvContent): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csvContent));
        if (empty($lines)) {
            throw new ValidationException(['file' => ['CSV file is empty.']]);
        }

        $header = str_getcsv(array_shift($lines));
        $normalizedHeader = array_map(fn($col) => strtolower(trim((string)$col)), $header);

        $parsedRows = [];
        $validCount = 0;
        $invalidCount = 0;
        $seenBatches = [];

        foreach ($lines as $idx => $line) {
            if (trim($line) === '') {
                continue;
            }

            $rawRow = str_getcsv($line);
            $row = [];
            foreach ($normalizedHeader as $colIdx => $colName) {
                $row[$colName] = isset($rawRow[$colIdx]) ? trim($rawRow[$colIdx]) : '';
            }

            $rowErrors = [];

            // Validation rules
            if (empty($row['medicine_name'])) {
                $rowErrors[] = 'Medicine name is required.';
            }
            if (empty($row['category'])) {
                $rowErrors[] = 'Category is required.';
            }
            if (empty($row['batch_number'])) {
                $rowErrors[] = 'Batch number is required.';
            }
            if (!isset($row['quantity']) || !is_numeric($row['quantity']) || (int)$row['quantity'] < 0) {
                $rowErrors[] = 'Quantity must be a non-negative integer.';
            }
            if (!isset($row['purchase_price']) || !is_numeric($row['purchase_price']) || (float)$row['purchase_price'] < 0) {
                $rowErrors[] = 'Purchase price must be a valid positive number.';
            }
            if (!isset($row['mrp']) || !is_numeric($row['mrp']) || (float)$row['mrp'] < (float)($row['purchase_price'] ?? 0)) {
                $rowErrors[] = 'MRP must be greater than or equal to purchase price.';
            }
            if (isset($row['selling_price']) && is_numeric($row['selling_price']) && (float)$row['selling_price'] > (float)$row['mrp']) {
                $rowErrors[] = 'Selling price must be less than or equal to MRP.';
            }
            if (empty($row['mfg_date']) || !strtotime($row['mfg_date'])) {
                $rowErrors[] = 'Valid manufacturing date is required (YYYY-MM-DD).';
            }
            if (empty($row['exp_date']) || !strtotime($row['exp_date'])) {
                $rowErrors[] = 'Valid expiry date is required (YYYY-MM-DD).';
            }
            if (!empty($row['mfg_date']) && !empty($row['exp_date']) && strtotime($row['exp_date']) <= strtotime($row['mfg_date'])) {
                $rowErrors[] = 'Expiry date must be after manufacturing date.';
            }

            // Intra-CSV duplicate check
            $batchKey = strtolower(($row['medicine_name'] ?? '') . '|' . ($row['batch_number'] ?? ''));
            if (isset($seenBatches[$batchKey])) {
                $rowErrors[] = 'Duplicate batch number for this medicine within the same CSV file.';
            }
            $seenBatches[$batchKey] = true;

            $isValid = empty($rowErrors);
            if ($isValid) {
                $validCount++;
            } else {
                $invalidCount++;
            }

            $parsedRows[] = [
                'row_index' => $idx + 1,
                'data' => $row,
                'valid' => $isValid,
                'errors' => $rowErrors,
            ];
        }

        $importToken = bin2hex(random_bytes(16));
        $cachePayload = [
            'total' => count($parsedRows),
            'valid' => $validCount,
            'invalid' => $invalidCount,
            'rows' => $parsedRows,
        ];

        $this->cache->set("import_csv:{$importToken}", json_encode($cachePayload), 3600);

        return [
            'import_token' => $importToken,
            'total_rows' => count($parsedRows),
            'valid_rows' => $validCount,
            'invalid_rows' => $invalidCount,
            'preview' => array_slice($parsedRows, 0, 50),
        ];
    }

    /**
     * Step 2: Commit validated rows into the database.
     */
    public function commit(string $importToken, string $onDuplicate = 'skip', ?int $userId = null): array
    {
        $cachedJson = $this->cache->get("import_csv:{$importToken}");
        if (!$cachedJson) {
            throw new ValidationException(['token' => ['Import session expired or invalid. Please upload CSV again.']]);
        }

        $payload = json_decode($cachedJson, true);
        $rows = $payload['rows'] ?? [];

        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;

        foreach ($rows as $item) {
            if (!$item['valid']) {
                $failedCount++;
                continue;
            }

            $data = $item['data'];

            try {
                // 1. Resolve Category
                $catName = trim($data['category']);
                $catSlug = strtolower(trim((string)preg_replace('/[^A-Za-z0-9-]+/', '-', $catName), '-'));
                $category = $this->db->fetchOne('SELECT id FROM categories WHERE slug = ? OR name = ?', [$catSlug, $catName]);
                if (!$category) {
                    $catId = $this->catRepo->create(['name' => $catName, 'slug' => $catSlug, 'is_active' => 1]);
                } else {
                    $catId = (int)$category['id'];
                }

                // 2. Resolve Manufacturer
                $manId = null;
                if (!empty($data['manufacturer'])) {
                    $manName = trim($data['manufacturer']);
                    $man = $this->db->fetchOne('SELECT id FROM manufacturers WHERE name = ?', [$manName]);
                    if (!$man) {
                        $this->db->execute('INSERT INTO manufacturers (name) VALUES (?)', [$manName]);
                        $manId = (int)$this->db->lastInsertId();
                    } else {
                        $manId = (int)$man['id'];
                    }
                }

                // 3. Resolve Rack & Shelf
                $rackId = null;
                $shelfId = null;
                if (!empty($data['rack'])) {
                    $rackName = trim($data['rack']);
                    $rack = $this->db->fetchOne('SELECT id FROM racks WHERE name = ?', [$rackName]);
                    if (!$rack) {
                        $this->db->execute('INSERT INTO racks (name) VALUES (?)', [$rackName]);
                        $rackId = (int)$this->db->lastInsertId();
                    } else {
                        $rackId = (int)$rack['id'];
                    }

                    if (!empty($data['shelf']) && $rackId) {
                        $shelfName = trim($data['shelf']);
                        $shelf = $this->db->fetchOne('SELECT id FROM shelves WHERE rack_id = ? AND name = ?', [$rackId, $shelfName]);
                        if (!$shelf) {
                            $this->db->execute('INSERT INTO shelves (rack_id, name) VALUES (?, ?)', [$rackId, $shelfName]);
                            $shelfId = (int)$this->db->lastInsertId();
                        } else {
                            $shelfId = (int)$shelf['id'];
                        }
                    }
                }

                // 4. Resolve Medicine
                $medName = trim($data['medicine_name']);
                $medSlug = !empty($data['slug']) ? trim($data['slug']) : strtolower(trim((string)preg_replace('/[^A-Za-z0-9-]+/', '-', $medName), '-'));
                $medicine = $this->db->fetchOne('SELECT id FROM medicines WHERE slug = ?', [$medSlug]);

                $schedule = !empty($data['schedule']) ? strtoupper(trim($data['schedule'])) : 'OTC';
                $requiresRx = $schedule !== 'OTC' ? 1 : (int)(!empty($data['requires_rx']));

                if (!$medicine) {
                    $medId = $this->medRepo->create([
                        'name' => $medName,
                        'slug' => $medSlug,
                        'category_id' => $catId,
                        'manufacturer_id' => $manId,
                        'schedule' => $schedule,
                        'requires_rx' => $requiresRx,
                        'pack_size' => $data['pack_size'] ?? null,
                        'description' => $data['description'] ?? null,
                        'created_by' => $userId,
                    ]);
                } else {
                    $medId = (int)$medicine['id'];
                }

                // 5. Resolve Batch
                $batchNum = strtoupper(trim($data['batch_number']));
                $existingBatch = $this->batchRepo->findByMedicineAndBatchNumber($medId, $batchNum);

                $qty = (int)$data['quantity'];
                $purchasePrice = (float)$data['purchase_price'];
                $mrp = (float)$data['mrp'];
                $sellingPrice = !empty($data['selling_price']) ? (float)$data['selling_price'] : $mrp;
                $mfgDate = date('Y-m-d', strtotime($data['mfg_date']));
                $expDate = date('Y-m-d', strtotime($data['exp_date']));

                if ($existingBatch) {
                    if ($onDuplicate === 'update') {
                        $this->batchRepo->update((int)$existingBatch['id'], [
                            'quantity' => (int)$existingBatch['quantity'] + $qty,
                            'purchase_price' => $purchasePrice,
                            'mrp' => $mrp,
                            'selling_price' => $sellingPrice,
                            'mfg_date' => $mfgDate,
                            'exp_date' => $expDate,
                            'rack_id' => $rackId ?? $existingBatch['rack_id'],
                            'shelf_id' => $shelfId ?? $existingBatch['shelf_id'],
                        ]);

                        $this->movementRepo->log((int)$existingBatch['id'], 'import', $qty, 'csv_import', null, $userId);
                        $updatedCount++;
                    } else {
                        $skippedCount++;
                    }
                } else {
                    $newBatchId = $this->batchRepo->create([
                        'medicine_id' => $medId,
                        'batch_number' => $batchNum,
                        'quantity' => $qty,
                        'purchase_price' => $purchasePrice,
                        'mrp' => $mrp,
                        'selling_price' => $sellingPrice,
                        'mfg_date' => $mfgDate,
                        'exp_date' => $expDate,
                        'rack_id' => $rackId,
                        'shelf_id' => $shelfId,
                    ]);

                    $this->movementRepo->log($newBatchId, 'import', $qty, 'csv_import', null, $userId);
                    $createdCount++;
                }
            } catch (\Throwable $e) {
                $failedCount++;
            }
        }

        $this->auditRepo->log('import_csv', 'inventory', null, null, [
            'created' => $createdCount,
            'updated' => $updatedCount,
            'skipped' => $skippedCount,
            'failed' => $failedCount,
        ], $userId);

        $this->cache->delete("import_csv:{$importToken}");
        $this->cache->bumpVersion('catalog');

        return [
            'created' => $createdCount,
            'updated' => $updatedCount,
            'skipped' => $skippedCount,
            'failed' => $failedCount,
        ];
    }
}

