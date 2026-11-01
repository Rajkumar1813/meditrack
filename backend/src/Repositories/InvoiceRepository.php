<?php

declare(strict_types=1);

namespace MediTrack\Repositories;

use MediTrack\Core\Database;

class InvoiceRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM invoices WHERE id = ?', [$id]);
    }

    public function findByOrderId(int $orderId): ?array
    {
        return $this->db->fetchOne('SELECT * FROM invoices WHERE order_id = ?', [$orderId]);
    }

    public function findByInvoiceNo(string $invoiceNo): ?array
    {
        return $this->db->fetchOne('SELECT * FROM invoices WHERE invoice_no = ?', [$invoiceNo]);
    }

    public function create(int $orderId, string $invoiceNo, ?string $pdfPath = null): int
    {
        $sql = 'INSERT INTO invoices (order_id, invoice_no, pdf_path, issued_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)';
        $this->db->execute($sql, [$orderId, $invoiceNo, $pdfPath]);
        return (int)$this->db->lastInsertId();
    }

    public function updatePdfPath(int $id, string $pdfPath): bool
    {
        return $this->db->execute('UPDATE invoices SET pdf_path = ? WHERE id = ?', [$pdfPath, $id]) > 0;
    }

    /**
     * Generate gap-free sequential invoice number INV-YYYY-000001
     * Protected by database transaction and lock on invoice_counters table.
     */
    public function getNextInvoiceNumber(?int $year = null): string
    {
        $year = $year ?? (int)date('Y');
        $isSqlite = $this->db->driver() === 'sqlite';

        return $this->db->transaction(function () use ($year, $isSqlite) {
            try {
                if ($isSqlite) {
                    $this->db->execute(
                        'INSERT OR IGNORE INTO invoice_counters (year, last_number) VALUES (?, 0)',
                        [$year]
                    );
                    $row = $this->db->fetchOne(
                        'SELECT last_number FROM invoice_counters WHERE year = ?',
                        [$year]
                    );
                } else {
                    $this->db->execute(
                        'INSERT INTO invoice_counters (`year`, `last_number`) VALUES (?, 0)
                         ON DUPLICATE KEY UPDATE `year` = `year`',
                        [$year]
                    );
                    $row = $this->db->fetchOne(
                        'SELECT last_number FROM invoice_counters WHERE `year` = ? FOR UPDATE',
                        [$year]
                    );
                }

                $lastNumber = $row ? (int)$row['last_number'] : 0;
                $nextNumber = $lastNumber + 1;

                $this->db->execute(
                    'UPDATE invoice_counters SET last_number = ? WHERE year = ?',
                    [$nextNumber, $year]
                );

                return sprintf('INV-%04d-%06d', $year, $nextNumber);
            } catch (\Throwable) {
                // Fallback to table scan if counters table does not exist
                $prefix = sprintf('INV-%04d-', $year);
                $row = $this->db->fetchOne(
                    'SELECT invoice_no FROM invoices WHERE invoice_no LIKE ? ORDER BY id DESC LIMIT 1',
                    [$prefix . '%']
                );
                if (!$row) {
                    return $prefix . '000001';
                }
                $lastSeq = (int)substr($row['invoice_no'], strlen($prefix));
                $nextSeq = str_pad((string)($lastSeq + 1), 6, '0', STR_PAD_LEFT);
                return $prefix . $nextSeq;
            }
        });
    }

    /**
     * Paginate invoices for admin view with search and date range filters.
     */
    public function paginateAdmin(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['search']) || !empty($filters['q'])) {
            $search = '%' . trim((string)($filters['search'] ?? $filters['q'])) . '%';
            $where[] = '(i.invoice_no LIKE ? OR o.order_no LIKE ? OR u.name LIKE ? OR u.phone LIKE ? OR u.email LIKE ?)';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(i.issued_at) >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(i.issued_at) <= ?';
            $params[] = $filters['date_to'];
        }

        $whereClause = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) as cnt 
                     FROM invoices i
                     JOIN orders o ON i.order_id = o.id
                     LEFT JOIN users u ON o.user_id = u.id
                     WHERE {$whereClause}";
        $countRow = $this->db->fetchOne($countSql, $params);
        $total = (int)($countRow['cnt'] ?? 0);

        $sql = "SELECT i.*, 
                       o.order_no, o.total, o.payment_method, o.payment_status, o.status as order_status,
                       u.id as customer_id, u.name as customer_name, u.phone as customer_phone, u.email as customer_email
                FROM invoices i
                JOIN orders o ON i.order_id = o.id
                LEFT JOIN users u ON o.user_id = u.id
                WHERE {$whereClause}
                ORDER BY i.id DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $items = $this->db->fetchAll($sql, $params);

        return [
            'data' => $items,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int)ceil($total / $perPage),
            ],
        ];
    }
}
