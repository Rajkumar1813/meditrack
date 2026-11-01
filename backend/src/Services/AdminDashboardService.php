<?php

declare(strict_types=1);

namespace MediTrack\Services;

use MediTrack\Core\Database;
use MediTrack\Repositories\SettingRepository;

class AdminDashboardService
{
    private Database $db;
    private SettingRepository $settingRepo;

    public function __construct(Database $db, SettingRepository $settingRepo)
    {
        $this->db = $db;
        $this->settingRepo = $settingRepo;
    }

    public function getStats(): array
    {
        $today = date('Y-m-d');
        $in30Days = date('Y-m-d', strtotime('+30 days'));
        $in90Days = date('Y-m-d', strtotime('+90 days'));
        $threshold = (int)($this->settingRepo->get('low_stock_threshold', '10') ?? 10);

        // 1. Total Medicines (active, not deleted)
        $medRow = $this->db->fetchOne('SELECT COUNT(*) as count FROM medicines WHERE deleted_at IS NULL AND is_active = 1');
        $totalMedicines = (int)($medRow['count'] ?? 0);

        // 2. Total Units & Total Stock Value (unexpired, non-disposed)
        $unitsRow = $this->db->fetchOne(
            "SELECT COALESCE(SUM(b.quantity - b.reserved_quantity), 0) as total_units,
                    COALESCE(SUM(b.quantity * b.purchase_price), 0) as stock_value
             FROM batches b
             JOIN medicines m ON b.medicine_id = m.id
             WHERE m.deleted_at IS NULL AND b.exp_date > ? AND b.disposal_status = 'none'",
            [$today]
        );
        $totalUnits = (int)($unitsRow['total_units'] ?? 0);
        $stockValue = (float)($unitsRow['stock_value'] ?? 0.0);

        // 3. Expiring within 30 days
        $exp30Row = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM batches b
             JOIN medicines m ON b.medicine_id = m.id
             WHERE m.deleted_at IS NULL AND b.exp_date >= ? AND b.exp_date <= ? AND b.disposal_status = 'none' AND b.quantity > 0",
            [$today, $in30Days]
        );
        $expiring30d = (int)($exp30Row['count'] ?? 0);

        // 4. Expired Batches (non-disposed)
        $expRow = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM batches b
             JOIN medicines m ON b.medicine_id = m.id
             WHERE m.deleted_at IS NULL AND b.exp_date < ? AND b.disposal_status = 'none'",
            [$today]
        );
        $expiredCount = (int)($expRow['count'] ?? 0);

        // 5. Low Stock Medicines (available unexpired units <= threshold)
        $lowStockRows = $this->db->fetchAll(
            "SELECT m.id,
                    COALESCE(SUM(CASE WHEN b.exp_date > ? AND b.disposal_status = 'none' THEN (b.quantity - b.reserved_quantity) ELSE 0 END), 0) as avail
             FROM medicines m
             LEFT JOIN batches b ON m.id = b.medicine_id
             WHERE m.deleted_at IS NULL AND m.is_active = 1
             GROUP BY m.id
             HAVING avail <= ?",
            [$today, $threshold]
        );
        $lowStockCount = count($lowStockRows);

        // 6. Category Breakdown
        $categoryBreakdown = $this->db->fetchAll(
            "SELECT c.id, c.name, c.slug,
                    COUNT(DISTINCT m.id) as medicine_count,
                    COALESCE(SUM(CASE WHEN b.exp_date > ? AND b.disposal_status = 'none' THEN (b.quantity - b.reserved_quantity) ELSE 0 END), 0) as stock_units
             FROM categories c
             LEFT JOIN medicines m ON c.id = m.category_id AND m.deleted_at IS NULL AND m.is_active = 1
             LEFT JOIN batches b ON m.id = b.medicine_id
             WHERE c.is_active = 1
             GROUP BY c.id, c.name, c.slug
             ORDER BY c.sort_order ASC, c.name ASC",
            [$today]
        );

        // 7. Expiry Overview
        $safeRow = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM batches b JOIN medicines m ON b.medicine_id = m.id
             WHERE m.deleted_at IS NULL AND b.exp_date > ? AND b.disposal_status = 'none'",
            [$in90Days]
        );
        $within90Row = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM batches b JOIN medicines m ON b.medicine_id = m.id
             WHERE m.deleted_at IS NULL AND b.exp_date > ? AND b.exp_date <= ? AND b.disposal_status = 'none'",
            [$in30Days, $in90Days]
        );

        $expiryOverview = [
            'safe' => (int)($safeRow['count'] ?? 0),
            'within_90d' => (int)($within90Row['count'] ?? 0),
            'within_30d' => $expiring30d,
            'expired' => $expiredCount,
        ];

        // 8. Recently Added Batches
        $recentlyAdded = $this->db->fetchAll(
            "SELECT b.id, b.batch_number, b.quantity, b.selling_price, b.exp_date, b.created_at,
                    m.name as medicine_name, m.slug as medicine_slug, c.name as category_name
             FROM batches b
             JOIN medicines m ON b.medicine_id = m.id
             LEFT JOIN categories c ON m.category_id = c.id
             WHERE m.deleted_at IS NULL
             ORDER BY b.id DESC
             LIMIT 8"
        );

        // 9. Active Alerts
        $activeAlerts = [];
        if ($expiredCount > 0) {
            $activeAlerts[] = [
                'type' => 'expired',
                'severity' => 'danger',
                'count' => $expiredCount,
                'message' => "{$expiredCount} batch(es) have expired and need disposal/return.",
                'action_url' => '/admin/expired',
            ];
        }
        if ($expiring30d > 0) {
            $activeAlerts[] = [
                'type' => 'expiring',
                'severity' => 'warning',
                'count' => $expiring30d,
                'message' => "{$expiring30d} batch(es) are expiring within the next 30 days.",
                'action_url' => '/admin/expiring?days=30',
            ];
        }
        if ($lowStockCount > 0) {
            $activeAlerts[] = [
                'type' => 'low_stock',
                'severity' => 'info',
                'count' => $lowStockCount,
                'message' => "{$lowStockCount} medicine(s) are at or below the minimum stock threshold ({$threshold} units).",
                'action_url' => '/admin/low-stock',
            ];
        }

        // 10. Orders Today + Revenue Today
        $ordersToday = 0;
        $revenueToday = 0.0;
        try {
            $orderRow = $this->db->fetchOne(
                "SELECT COUNT(*) as order_count, COALESCE(SUM(total_amount), 0) as revenue
                 FROM orders
                 WHERE DATE(created_at) = ? AND payment_status = 'paid'",
                [$today]
            );
            $ordersToday = (int)($orderRow['order_count'] ?? 0);
            $revenueToday = (float)($orderRow['revenue'] ?? 0.0);
        } catch (\Throwable) {
            // Orders table might be empty or tested prior to phase 5
        }

        return [
            'total_medicines' => $totalMedicines,
            'total_units' => $totalUnits,
            'stock_value' => $stockValue,
            'low_stock' => $lowStockCount,
            'expiring_30d' => $expiring30d,
            'expired' => $expiredCount,
            'threshold' => $threshold,
            'category_breakdown' => $categoryBreakdown,
            'expiry_overview' => $expiryOverview,
            'recently_added' => $recentlyAdded,
            'active_alerts' => $activeAlerts,
            'orders_today' => $ordersToday,
            'revenue_today' => $revenueToday,
        ];
    }
}

