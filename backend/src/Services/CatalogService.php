<?php

declare(strict_types=1);

namespace MediTrack\Services;

use MediTrack\Core\Database;
use MediTrack\Core\Cache;
use MediTrack\Core\Paginator;
use MediTrack\Repositories\CategoryRepository;
use MediTrack\Repositories\MedicineRepository;
use MediTrack\Repositories\SaltRepository;

class CatalogService
{
    private Database $db;
    private Cache $cache;
    private CategoryRepository $categoryRepo;
    private MedicineRepository $medicineRepo;
    private SaltRepository $saltRepo;

    public function __construct(
        Database $db,
        Cache $cache,
        CategoryRepository $categoryRepo,
        MedicineRepository $medicineRepo,
        SaltRepository $saltRepo
    ) {
        $this->db = $db;
        $this->cache = $cache;
        $this->categoryRepo = $categoryRepo;
        $this->medicineRepo = $medicineRepo;
        $this->saltRepo = $saltRepo;
    }

    /**
     * Get list of categories (cached).
     */
    public function getCategories(bool $onlyActive = true): array
    {
        $version = $this->cache->getVersion('catalog');
        $cacheKey = "categories:v{$version}:" . ($onlyActive ? 'active' : 'all');

        return $this->cache->remember($cacheKey, 3600, function () use ($onlyActive) {
            return $this->categoryRepo->all($onlyActive);
        });
    }

    /**
     * Get paginated medicines with single-query FEFO price and stock calculation.
     */
    public function getMedicines(array $filters = [], array $pagination = []): array
    {
        $version = $this->cache->getVersion('catalog');
        $cacheKey = "catalog:v{$version}:" . md5(json_encode([$filters, $pagination]));

        return $this->cache->remember($cacheKey, 300, function () use ($filters, $pagination) {
            return $this->fetchMedicinesFromDb($filters, $pagination);
        });
    }

    /**
     * Get medicine by slug with salts, FEFO price, stock, and same-salt alternatives.
     */
    public function getMedicineBySlug(string $slug): ?array
    {
        $version = $this->cache->getVersion('catalog');
        $cacheKey = "medicine:v{$version}:" . trim($slug);

        return $this->cache->remember($cacheKey, 600, function () use ($slug) {
            $today = date('Y-m-d');

            $sql = "
                SELECT
                    m.id,
                    m.name,
                    m.slug,
                    m.generic_name,
                    m.category_id,
                    c.name AS category_name,
                    c.slug AS category_slug,
                    m.manufacturer_id,
                    mf.name AS manufacturer_name,
                    m.schedule,
                    m.requires_rx,
                    m.pack_size,
                    m.gst_percent,
                    m.description,
                    m.usage_info,
                    m.side_effects,
                    m.storage_info,
                    m.image,
                    m.is_active,
                    m.is_featured,
                    m.notes,
                    (
                        SELECT b.selling_price
                        FROM batches b
                        WHERE b.medicine_id = m.id
                          AND b.exp_date > '{$today}'
                          AND (b.quantity - b.reserved_quantity) > 0
                          AND b.disposal_status = 'none'
                        ORDER BY b.exp_date ASC, b.id ASC
                        LIMIT 1
                    ) AS selling_price,
                    (
                        SELECT b.mrp
                        FROM batches b
                        WHERE b.medicine_id = m.id
                          AND b.exp_date > '{$today}'
                          AND (b.quantity - b.reserved_quantity) > 0
                          AND b.disposal_status = 'none'
                        ORDER BY b.exp_date ASC, b.id ASC
                        LIMIT 1
                    ) AS mrp,
                    COALESCE(
                        (
                            SELECT SUM(b.quantity - b.reserved_quantity)
                            FROM batches b
                            WHERE b.medicine_id = m.id
                              AND b.exp_date > '{$today}'
                              AND b.disposal_status = 'none'
                        ),
                        0
                    ) AS stock
                FROM medicines m
                LEFT JOIN categories c ON m.category_id = c.id
                LEFT JOIN manufacturers mf ON m.manufacturer_id = mf.id
                WHERE m.slug = ? AND m.deleted_at IS NULL AND m.is_active = 1
                LIMIT 1
            ";

            $medicine = $this->db->fetchOne($sql, [$slug]);
            if (!$medicine) {
                return null;
            }

            // Hydrate Types
            $medicine['id'] = (int)$medicine['id'];
            $medicine['category_id'] = (int)$medicine['category_id'];
            $medicine['manufacturer_id'] = $medicine['manufacturer_id'] !== null ? (int)$medicine['manufacturer_id'] : null;
            $medicine['requires_rx'] = (bool)$medicine['requires_rx'];
            $medicine['is_active'] = (bool)$medicine['is_active'];
            $medicine['is_featured'] = (bool)$medicine['is_featured'];
            $medicine['stock'] = (int)$medicine['stock'];
            $medicine['in_stock'] = $medicine['stock'] > 0;
            $medicine['selling_price'] = $medicine['selling_price'] !== null ? (float)$medicine['selling_price'] : null;
            $medicine['mrp'] = $medicine['mrp'] !== null ? (float)$medicine['mrp'] : null;

            // Fetch Salts Composition
            $medicine['salts'] = $this->saltRepo->getByMedicineId($medicine['id']);

            // Fetch Same-Salt Alternatives (excluding this medicine)
            $medicine['same_salt_alternatives'] = $this->getSameSaltAlternatives($medicine['id']);

            return $medicine;
        });
    }

    /**
     * Search suggestions with highlights, type (medicine|salt), and rx flag (LIMIT 8, 60s cache).
     */
    public function getSuggestions(string $query): array
    {
        $clean = trim($query);
        if (mb_strlen($clean) < 2) {
            return [];
        }

        $version = $this->cache->getVersion('catalog');
        $cacheKey = "suggest:v{$version}:" . md5(mb_strtolower($clean));

        return $this->cache->remember($cacheKey, 60, function () use ($clean) {
            $prefix = $clean . '%';
            $contains = '%' . $clean . '%';

            // 1. Search Medicines
            $sqlMeds = "
                SELECT m.id, m.name, m.slug, m.generic_name, m.requires_rx, c.name as category_name,
                       CASE
                           WHEN m.name LIKE ? THEN 1
                           WHEN m.generic_name LIKE ? THEN 2
                           WHEN m.name LIKE ? THEN 3
                           ELSE 4
                       END AS match_rank
                FROM medicines m
                LEFT JOIN categories c ON m.category_id = c.id
                WHERE m.deleted_at IS NULL AND m.is_active = 1
                  AND (m.name LIKE ? OR m.generic_name LIKE ?)
                ORDER BY match_rank ASC, m.name ASC
                LIMIT 6
            ";
            $meds = $this->db->fetchAll($sqlMeds, [$prefix, $prefix, $contains, $contains, $contains]);

            // 2. Search Salts
            $sqlSalts = "
                SELECT s.id, s.name,
                       CASE WHEN s.name LIKE ? THEN 1 ELSE 2 END AS match_rank
                FROM salts s
                WHERE s.name LIKE ?
                ORDER BY match_rank ASC, s.name ASC
                LIMIT 4
            ";
            $salts = $this->db->fetchAll($sqlSalts, [$prefix, $contains]);

            $results = [];

            // Add Medicine matches
            foreach ($meds as $m) {
                $pos = mb_stripos($m['name'], $clean);
                $highlight = $pos !== false ? ['start' => $pos, 'length' => mb_strlen($clean)] : null;
                $subtitle = $m['generic_name'] ? "{$m['generic_name']} · {$m['category_name']}" : $m['category_name'];

                $results[] = [
                    'type' => 'medicine',
                    'id' => (int)$m['id'],
                    'title' => $m['name'],
                    'subtitle' => $subtitle,
                    'slug' => $m['slug'],
                    'requires_rx' => (bool)$m['requires_rx'],
                    'highlight' => $highlight,
                ];
            }

            // Add Salt matches
            foreach ($salts as $s) {
                if (count($results) >= 8) {
                    break;
                }
                $pos = mb_stripos($s['name'], $clean);
                $highlight = $pos !== false ? ['start' => $pos, 'length' => mb_strlen($clean)] : null;

                $results[] = [
                    'type' => 'salt',
                    'id' => (int)$s['id'],
                    'title' => $s['name'],
                    'subtitle' => 'Composition / Active Salt',
                    'slug' => null,
                    'requires_rx' => false,
                    'highlight' => $highlight,
                ];
            }

            return array_slice($results, 0, 8);
        });
    }

    /**
     * Invalidate cached catalog and suggestions by bumping version.
     */
    public function invalidateCatalogCache(): void
    {
        $this->cache->bumpVersion('catalog');
    }

    private function fetchMedicinesFromDb(array $filters, array $pagination): array
    {
        $today = date('Y-m-d');
        $where = ['m.deleted_at IS NULL', 'm.is_active = 1'];
        $params = [];

        // 1. Category Filter
        if (!empty($filters['category'])) {
            if (is_numeric($filters['category'])) {
                $where[] = 'm.category_id = ?';
                $params[] = (int)$filters['category'];
            } else {
                $where[] = '(c.slug = ? OR c.name = ?)';
                $params[] = (string)$filters['category'];
                $params[] = (string)$filters['category'];
            }
        }

        // 2. Manufacturer Filter
        if (!empty($filters['manufacturer'])) {
            if (is_numeric($filters['manufacturer'])) {
                $where[] = 'm.manufacturer_id = ?';
                $params[] = (int)$filters['manufacturer'];
            } else {
                $where[] = 'mf.name = ?';
                $params[] = (string)$filters['manufacturer'];
            }
        }

        // 3. Requires Rx Filter
        if (isset($filters['requires_rx']) && $filters['requires_rx'] !== '') {
            $where[] = 'm.requires_rx = ?';
            $params[] = (int)(bool)$filters['requires_rx'];
        }

        // 4. In Stock Filter
        if (!empty($filters['in_stock'])) {
            $where[] = 'EXISTS (
                SELECT 1 FROM batches b_stock
                WHERE b_stock.medicine_id = m.id
                  AND b_stock.exp_date > ?
                  AND (b_stock.quantity - b_stock.reserved_quantity) > 0
                  AND b_stock.disposal_status = \'none\'
            )';
            $params[] = $today;
        }

        // 5. Search Query q (matches name, generic_name, or composition salt)
        if (!empty($filters['q'])) {
            $term = '%' . trim((string)$filters['q']) . '%';
            $where[] = '(m.name LIKE ? OR m.generic_name LIKE ? OR EXISTS (
                SELECT 1 FROM medicine_salt ms
                JOIN salts s ON ms.salt_id = s.id
                WHERE ms.medicine_id = m.id AND s.name LIKE ?
            ))';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        // 6. Min Price Filter
        if (isset($filters['min_price']) && is_numeric($filters['min_price'])) {
            $where[] = '(
                SELECT b_p.selling_price FROM batches b_p
                WHERE b_p.medicine_id = m.id
                  AND b_p.exp_date > ?
                  AND (b_p.quantity - b_p.reserved_quantity) > 0
                  AND b_p.disposal_status = "none"
                ORDER BY b_p.exp_date ASC, b_p.id ASC
                LIMIT 1
            ) >= ?';
            $params[] = $today;
            $params[] = (float)$filters['min_price'];
        }

        // 7. Max Price Filter
        if (isset($filters['max_price']) && is_numeric($filters['max_price'])) {
            $where[] = '(
                SELECT b_p.selling_price FROM batches b_p
                WHERE b_p.medicine_id = m.id
                  AND b_p.exp_date > ?
                  AND (b_p.quantity - b_p.reserved_quantity) > 0
                  AND b_p.disposal_status = "none"
                ORDER BY b_p.exp_date ASC, b_p.id ASC
                LIMIT 1
            ) <= ?';
            $params[] = $today;
            $params[] = (float)$filters['max_price'];
        }

        // Build Whitelisted Sorting with mandatory ID tie-breaker
        $sort = strtolower(trim((string)($filters['sort'] ?? 'name_asc')));
        $orderBy = match ($sort) {
            'name_desc' => 'ORDER BY m.name DESC, m.id DESC',
            'price', 'price_asc' => 'ORDER BY selling_price ASC, m.id ASC',
            'price_desc' => 'ORDER BY selling_price DESC, m.id DESC',
            'newest' => 'ORDER BY m.id DESC',
            default => 'ORDER BY m.name ASC, m.id ASC', // relevance / name_asc
        };

        // Check if cursor pagination is requested
        $isCursorPagination = isset($pagination['cursor']) || (isset($pagination['type']) && $pagination['type'] === 'cursor');

        if ($isCursorPagination) {
            $limit = min(Paginator::MAX_CURSOR_LIMIT, max(1, (int)($pagination['limit'] ?? Paginator::DEFAULT_CURSOR_LIMIT)));
            $cursorString = $pagination['cursor'] ?? null;
            $cursorData = Paginator::decodeCursor($cursorString);

            if ($cursorData && isset($cursorData['id'])) {
                $cursorId = (int)$cursorData['id'];
                if (str_contains($orderBy, 'm.id DESC')) {
                    $where[] = 'm.id < ?';
                } else {
                    $where[] = 'm.id > ?';
                }
                $params[] = $cursorId;
            }

            $whereSql = implode(' AND ', $where);
            $queryLimit = $limit + 1; // Fetch 1 extra to determine has_more

            $sql = $this->getCatalogSelectSql($today, $whereSql, $orderBy, "LIMIT {$queryLimit}");
            $rawItems = $this->db->fetchAll($sql, $params);

            $hasMore = count($rawItems) > $limit;
            if ($hasMore) {
                array_pop($rawItems);
            }

            $items = array_map([$this, 'formatCatalogItem'], $rawItems);
            $lastItem = end($items);
            $nextCursor = ($hasMore && $lastItem) ? Paginator::encodeCursor(['id' => $lastItem['id']]) : null;

            return Paginator::cursor($items, $limit, $nextCursor, $hasMore);
        }

        // Standard Offset Pagination
        $page = max(1, (int)($pagination['page'] ?? 1));
        $perPage = min(Paginator::MAX_OFFSET_PER_PAGE, max(1, (int)($pagination['per_page'] ?? Paginator::DEFAULT_OFFSET_PER_PAGE)));
        $offset = ($page - 1) * $perPage;

        $whereSql = implode(' AND ', $where);

        // Count Total
        $countSql = "
            SELECT COUNT(DISTINCT m.id) as total
            FROM medicines m
            LEFT JOIN categories c ON m.category_id = c.id
            LEFT JOIN manufacturers mf ON m.manufacturer_id = mf.id
            WHERE {$whereSql}
        ";
        $countRow = $this->db->fetchOne($countSql, $params);
        $total = (int)($countRow['total'] ?? 0);

        // Fetch Page Items
        $sql = $this->getCatalogSelectSql($today, $whereSql, $orderBy, "LIMIT {$perPage} OFFSET {$offset}");
        $rawItems = $this->db->fetchAll($sql, $params);
        $items = array_map([$this, 'formatCatalogItem'], $rawItems);

        return Paginator::offset($page, $perPage, $total, $items);
    }

    private function getCatalogSelectSql(string $today, string $whereSql, string $orderBy, string $limitSql): string
    {
        return "
            SELECT
                m.id,
                m.name,
                m.slug,
                m.generic_name,
                m.category_id,
                c.name AS category_name,
                c.slug AS category_slug,
                m.manufacturer_id,
                mf.name AS manufacturer_name,
                m.schedule,
                m.requires_rx,
                m.pack_size,
                m.image,
                (
                    SELECT b.selling_price
                    FROM batches b
                    WHERE b.medicine_id = m.id
                      AND b.exp_date > '{$today}'
                      AND (b.quantity - b.reserved_quantity) > 0
                      AND b.disposal_status = 'none'
                    ORDER BY b.exp_date ASC, b.id ASC
                    LIMIT 1
                ) AS selling_price,
                (
                    SELECT b.mrp
                    FROM batches b
                    WHERE b.medicine_id = m.id
                      AND b.exp_date > '{$today}'
                      AND (b.quantity - b.reserved_quantity) > 0
                      AND b.disposal_status = 'none'
                    ORDER BY b.exp_date ASC, b.id ASC
                    LIMIT 1
                ) AS mrp,
                COALESCE(
                    (
                        SELECT SUM(b.quantity - b.reserved_quantity)
                        FROM batches b
                        WHERE b.medicine_id = m.id
                          AND b.exp_date > '{$today}'
                          AND b.disposal_status = 'none'
                    ),
                    0
                ) AS stock
            FROM medicines m
            LEFT JOIN categories c ON m.category_id = c.id
            LEFT JOIN manufacturers mf ON m.manufacturer_id = mf.id
            WHERE {$whereSql}
            {$orderBy}
            {$limitSql}
        ";
    }

    private function formatCatalogItem(array $item): array
    {
        $stock = (int)($item['stock'] ?? 0);
        return [
            'id' => (int)$item['id'],
            'name' => $item['name'],
            'slug' => $item['slug'],
            'generic_name' => $item['generic_name'] ?? null,
            'category_id' => isset($item['category_id']) ? (int)$item['category_id'] : null,
            'category_name' => $item['category_name'] ?? null,
            'category_slug' => $item['category_slug'] ?? null,
            'manufacturer_id' => isset($item['manufacturer_id']) && $item['manufacturer_id'] !== null ? (int)$item['manufacturer_id'] : null,
            'manufacturer_name' => $item['manufacturer_name'] ?? null,
            'schedule' => $item['schedule'] ?? 'OTC',
            'requires_rx' => (bool)($item['requires_rx'] ?? false),
            'pack_size' => $item['pack_size'] ?? null,
            'image' => $item['image'] ?? null,
            'selling_price' => isset($item['selling_price']) && $item['selling_price'] !== null ? (float)$item['selling_price'] : null,
            'mrp' => isset($item['mrp']) && $item['mrp'] !== null ? (float)$item['mrp'] : null,
            'stock' => $stock,
            'in_stock' => $stock > 0,
        ];
    }

    private function getSameSaltAlternatives(int $medicineId): array
    {
        $today = date('Y-m-d');
        $sql = "
            SELECT DISTINCT m.id, m.name, m.slug, m.generic_name, m.category_id,
                            c.name as category_name, c.slug as category_slug,
                            m.pack_size, m.schedule, m.requires_rx, m.image,
                            (
                                SELECT b.selling_price FROM batches b
                                WHERE b.medicine_id = m.id
                                  AND b.exp_date > '{$today}'
                                  AND (b.quantity - b.reserved_quantity) > 0
                                  AND b.disposal_status = 'none'
                                ORDER BY b.exp_date ASC, b.id ASC
                                LIMIT 1
                            ) as selling_price,
                            (
                                SELECT b.mrp FROM batches b
                                WHERE b.medicine_id = m.id
                                  AND b.exp_date > '{$today}'
                                  AND (b.quantity - b.reserved_quantity) > 0
                                  AND b.disposal_status = 'none'
                                ORDER BY b.exp_date ASC, b.id ASC
                                LIMIT 1
                            ) as mrp,
                            COALESCE(
                                (
                                    SELECT SUM(b.quantity - b.reserved_quantity) FROM batches b
                                    WHERE b.medicine_id = m.id
                                      AND b.exp_date > '{$today}'
                                      AND b.disposal_status = 'none'
                                ),
                                0
                            ) as stock
            FROM medicines m
            JOIN categories c ON m.category_id = c.id
            JOIN medicine_salt ms ON m.id = ms.medicine_id
            WHERE ms.salt_id IN (SELECT salt_id FROM medicine_salt WHERE medicine_id = ?)
              AND m.id != ?
              AND m.deleted_at IS NULL AND m.is_active = 1
            LIMIT 4
        ";

        $rows = $this->db->fetchAll($sql, [$medicineId, $medicineId]);
        return array_map([$this, 'formatCatalogItem'], $rows);
    }
}
