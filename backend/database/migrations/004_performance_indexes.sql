-- Migration: 004_performance_indexes.sql
-- Missing indexes identified during Phase 14 Performance & Security Audit

ALTER TABLE prescriptions ADD INDEX idx_prescriptions_user (user_id, created_at);
ALTER TABLE medicines ADD INDEX idx_medicines_active_slug (is_active, slug);
ALTER TABLE order_items ADD INDEX idx_order_med (order_id, medicine_id);
ALTER TABLE batches ADD INDEX idx_fefo_lookup (medicine_id, quantity, exp_date);
ALTER TABLE notifications ADD INDEX idx_user_unread (user_id, read_at);

