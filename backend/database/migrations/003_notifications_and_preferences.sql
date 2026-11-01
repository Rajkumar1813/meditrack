-- Migration: 003_notifications_and_preferences.sql
-- User notification preferences and refill reminder enhancements

CREATE TABLE IF NOT EXISTS user_notification_preferences (
  user_id BIGINT UNSIGNED PRIMARY KEY,
  order_updates TINYINT(1) NOT NULL DEFAULT 1,
  refill_reminders TINYINT(1) NOT NULL DEFAULT 1,
  promotional_push TINYINT(1) NOT NULL DEFAULT 1,
  stock_alerts TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE refill_reminders ADD COLUMN snoozed_until DATE NULL AFTER remind_at;
ALTER TABLE refill_reminders ADD COLUMN last_notified_at TIMESTAMP NULL AFTER status;

