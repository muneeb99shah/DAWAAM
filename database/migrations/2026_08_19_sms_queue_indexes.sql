-- Dawaam - Database Migration: SMS Queue & Notification Logs Performance Indexes
-- Date: 2026-08-19

CREATE INDEX IF NOT EXISTS `idx_alerts_created_id` ON `alerts` (`created_at` DESC, `id` DESC);
CREATE INDEX IF NOT EXISTS `idx_alerts_product_id` ON `alerts` (`product_id`);
CREATE INDEX IF NOT EXISTS `idx_nl_alert_status` ON `notification_logs` (`alert_id`, `status`);
CREATE INDEX IF NOT EXISTS `idx_nl_channel_status` ON `notification_logs` (`channel_used`, `status`);
