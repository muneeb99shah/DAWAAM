-- Dawaam Database Query Optimization & Performance Indexes Migration
-- Target: High-performance indexing for 100,000+ to 1,000,000+ records

-- 1. Sales & Transaction Line Items Indexes
CREATE INDEX IF NOT EXISTS `idx_sales_sold_id` ON `sales` (`sold_at`, `id`);
CREATE INDEX IF NOT EXISTS `idx_sales_user_date` ON `sales` (`user_id`, `sold_at`);
CREATE INDEX IF NOT EXISTS `idx_sales_code` ON `sales` (`sale_code`);
CREATE INDEX IF NOT EXISTS `idx_sale_items_sale` ON `sale_items` (`sale_id`);
CREATE INDEX IF NOT EXISTS `idx_sale_items_product` ON `sale_items` (`product_id`);

-- 2. Product Catalog & Category Indexes
CREATE INDEX IF NOT EXISTS `idx_products_stock_thresh` ON `products` (`stock_qty`, `low_stock_threshold`);
CREATE INDEX IF NOT EXISTS `idx_products_cat_id` ON `products` (`category_id`);
CREATE INDEX IF NOT EXISTS `idx_products_sku` ON `products` (`sku`);
CREATE INDEX IF NOT EXISTS `idx_products_name` ON `products` (`name`);

-- 3. System Alerts & Notifications Indexes
CREATE INDEX IF NOT EXISTS `idx_alerts_sent_date` ON `alerts` (`is_sent`, `created_at`);
CREATE INDEX IF NOT EXISTS `idx_alerts_type` ON `alerts` (`type`);

-- 4. User Accounts & RBAC Matrix Indexes
CREATE INDEX IF NOT EXISTS `idx_users_status_date` ON `users` (`status`, `created_at`);
CREATE INDEX IF NOT EXISTS `idx_users_username` ON `users` (`username`);
CREATE INDEX IF NOT EXISTS `idx_user_roles_uid_rid` ON `user_roles` (`user_id`, `role_id`);
CREATE INDEX IF NOT EXISTS `idx_role_perms_rid_pid` ON `role_permissions` (`role_id`, `permission_id`);
CREATE INDEX IF NOT EXISTS `idx_user_perms_uid_pid` ON `user_permissions` (`user_id`, `permission_id`, `state`);

-- 5. Offline Sync Log & Audit Logs Indexes
CREATE INDEX IF NOT EXISTS `idx_sync_synced_date` ON `sync_log` (`synced`, `created_at`);
CREATE INDEX IF NOT EXISTS `idx_contact_messages_date` ON `contact_messages` (`submitted_at`);
CREATE INDEX IF NOT EXISTS `idx_audit_logs_date_uid` ON `audit_logs` (`created_at`, `user_id`);
