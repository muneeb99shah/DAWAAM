-- Dawaam - Database Migration: Notification Subsystem & Gateway Settings
-- Date: 2026-08-11

-- Table: notification_numbers
CREATE TABLE IF NOT EXISTS `notification_numbers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `phone_number` VARCHAR(30) NOT NULL UNIQUE,
  `status` ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `receive_whatsapp` TINYINT(1) NOT NULL DEFAULT 1,
  `receive_sms` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: gateway_settings
CREATE TABLE IF NOT EXISTS `gateway_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(50) NOT NULL UNIQUE,
  `setting_value` TEXT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: notification_logs
CREATE TABLE IF NOT EXISTS `notification_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `alert_id` INT NULL,
  `recipient_name` VARCHAR(100) NULL,
  `recipient_phone` VARCHAR(30) NOT NULL,
  `message` TEXT NOT NULL,
  `primary_channel` ENUM('whatsapp', 'sms') NOT NULL DEFAULT 'whatsapp',
  `channel_used` ENUM('whatsapp', 'sms') NOT NULL DEFAULT 'sms',
  `status` ENUM('pending', 'sending', 'sent', 'delivered', 'failed', 'retrying') NOT NULL DEFAULT 'pending',
  `provider` VARCHAR(50) NOT NULL DEFAULT 'android_app',
  `provider_msg_id` VARCHAR(100) NULL,
  `provider_response` TEXT NULL,
  `error_message` TEXT NULL,
  `retry_count` INT NOT NULL DEFAULT 0,
  `next_retry_at` DATETIME NULL,
  `fallback_channel` ENUM('whatsapp', 'sms') NULL,
  `fallback_reason` TEXT NULL,
  `sent_at` DATETIME NULL,
  `delivered_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_nl_alert` FOREIGN KEY (`alert_id`) REFERENCES `alerts`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Default Gateway Settings (Sanitized Placeholders)
INSERT INTO `gateway_settings` (`setting_key`, `setting_value`) VALUES
('whatsapp_enabled', '1'),
('whatsapp_provider', 'whatsapp_cloud_api'),
('whatsapp_graph_version', 'v20.0'),
('whatsapp_phone_number_id', '109823471092837'),
('whatsapp_access_token', 'dawaam_wa_secret_token_2026'),
('whatsapp_account_id', '109823471092837'),
('whatsapp_sender_number', '+1234567890'),
('whatsapp_webhook_url', 'http://localhost:8000/api/v1/notifications/webhook.php'),
('sms_enabled', '1'),
('sms_provider', 'android_app'),
('sms_api_url', 'http://192.168.108.55:8080/send'),
('sms_api_token', 'dawaam_secret_token_2026'),
('sms_sender_id', 'DAWAAM_SMS'),
('sms_webhook_url', 'http://localhost:8000/api/v1/notifications/webhook.php')
ON DUPLICATE KEY UPDATE `setting_key` = VALUES(`setting_key`);
