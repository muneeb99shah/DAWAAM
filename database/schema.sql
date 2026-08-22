-- Dawaam Local Business Continuity System Database Schema
-- DBMS: MySQL 8.0+ / MariaDB 10.4+

SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `dawaam_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `dawaam_db`;

-- Drop existing tables to ensure clean schema build
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `contact_messages`;
DROP TABLE IF EXISTS `services`;
DROP TABLE IF EXISTS `service_categories`;
DROP TABLE IF EXISTS `sync_conflicts`;
DROP TABLE IF EXISTS `sync_log`;
DROP TABLE IF EXISTS `alerts`;
DROP TABLE IF EXISTS `sales`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `user_roles`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `users`;

-- --------------------------------------------------------
-- Table: users
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_code` VARCHAR(20) NOT NULL UNIQUE COMMENT 'Unique identifier, e.g. DW-0001',
  `name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: roles
-- --------------------------------------------------------
CREATE TABLE `roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `slug` VARCHAR(50) NOT NULL UNIQUE,
  `description` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: permissions
-- --------------------------------------------------------
CREATE TABLE `permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `module` VARCHAR(50) NOT NULL COMMENT 'e.g. products, sales, users',
  `action` VARCHAR(50) NOT NULL COMMENT 'e.g. view, create, edit, delete',
  `permission_key` VARCHAR(100) NOT NULL UNIQUE COMMENT 'e.g. products.view',
  `description` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Pivot Table: user_roles
-- --------------------------------------------------------
CREATE TABLE `user_roles` (
  `user_id` INT NOT NULL,
  `role_id` INT NOT NULL,
  PRIMARY KEY (`user_id`, `role_id`),
  CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Pivot Table: role_permissions
-- --------------------------------------------------------
CREATE TABLE `role_permissions` (
  `role_id` INT NOT NULL,
  `permission_id` INT NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: categories
-- --------------------------------------------------------
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: products
-- --------------------------------------------------------
CREATE TABLE `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT NULL,
  `name` VARCHAR(150) NOT NULL,
  `sku` VARCHAR(50) UNIQUE NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `stock_qty` INT NOT NULL DEFAULT 0,
  `low_stock_threshold` INT NOT NULL DEFAULT 5,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_prod_cat` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: sales
-- --------------------------------------------------------
CREATE TABLE `sales` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sale_code` VARCHAR(30) NOT NULL UNIQUE,
  `user_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `customer_name` VARCHAR(100) NOT NULL DEFAULT 'Walk-in Customer',
  `customer_phone` VARCHAR(30) NULL,
  `customer_email` VARCHAR(100) NULL,
  `customer_address` TEXT NULL,
  `customer_tax_id` VARCHAR(50) NULL,
  `quantity` INT NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_type` ENUM('fixed','percent') NOT NULL DEFAULT 'fixed',
  `discount_val` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_price` DECIMAL(10,2) NOT NULL,
  `payment_method` ENUM('cash','card','bank_transfer','mobile_wallet') NOT NULL DEFAULT 'cash',
  `payment_ref` VARCHAR(100) NULL,
  `amount_received` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `change_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `remaining_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` ENUM('paid','partial','unpaid') NOT NULL DEFAULT 'paid',
  `sold_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_sales_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  CONSTRAINT `fk_sales_prod` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: alerts
-- --------------------------------------------------------
CREATE TABLE `alerts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NULL,
  `type` ENUM('low_stock', 'big_sale', 'critical_event') NOT NULL,
  `message` TEXT NOT NULL,
  `is_sent` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 = Pending, 1 = Sent via SMS',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_alerts_prod` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: sync_log
-- --------------------------------------------------------
CREATE TABLE `sync_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `table_name` VARCHAR(50) NOT NULL,
  `record_id` INT NOT NULL,
  `action` ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
  `synced` TINYINT(1) NOT NULL DEFAULT 0,
  `sync_status` ENUM('pending', 'synced', 'failed', 'conflict') NOT NULL DEFAULT 'pending',
  `error_message` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `synced_at` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: sync_conflicts
-- --------------------------------------------------------
CREATE TABLE `sync_conflicts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `table_name` VARCHAR(50) NOT NULL,
  `record_id` INT NOT NULL,
  `local_data` LONGTEXT NOT NULL,
  `remote_data` LONGTEXT NOT NULL,
  `status` ENUM('unresolved', 'resolved') NOT NULL DEFAULT 'unresolved',
  `resolved_at` DATETIME NULL,
  `resolved_by` INT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_sc_user` FOREIGN KEY (`resolved_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: service_categories
-- --------------------------------------------------------
CREATE TABLE `service_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: services
-- --------------------------------------------------------
CREATE TABLE `services` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `description` TEXT NOT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `image_path` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_services_cat` FOREIGN KEY (`category_id`) REFERENCES `service_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: contact_messages
-- --------------------------------------------------------
CREATE TABLE `contact_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `message` TEXT NOT NULL,
  `submitted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: audit_logs
-- --------------------------------------------------------
CREATE TABLE `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `action` VARCHAR(100) NOT NULL,
  `module` VARCHAR(50) NOT NULL,
  `record_id` INT NULL,
  `description` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- SEED DATA SETUP
-- ========================================================

-- Default Roles
INSERT INTO `roles` (`id`, `name`, `slug`, `description`) VALUES
(1, 'Super Admin', 'super_admin', 'Full access to all modules and configurations'),
(2, 'Pharmacist', 'pharmacist', 'Access to pharmacy sales, inventory, and alerts'),
(3, 'Sales Staff', 'sales_staff', 'Access to recorded sales and basic stock queries'),
(4, 'Inventory Manager', 'inventory_manager', 'Access to products and stock updates');

-- Default Seed Users (Password for all default seed accounts: Admin@1234)
-- Hashed via password_hash('Admin@1234', PASSWORD_BCRYPT)
INSERT INTO `users` (`id`, `user_code`, `name`, `username`, `email`, `password_hash`, `status`) VALUES
(1, 'DW-0001', 'System Administrator', 'admin', 'admin@dawaam.local', '$2y$10$aXLnZ5T1J/7rs8.Zq0AZpuW9p9FhL.tZ3rrITBZHeWDU4bSWkKIe6', 'active'),
(2, 'DW-0002', 'Dr. Tariq Ahmed', 'tariq_pharm', 'tariq@dawaam.local', '$2y$10$aXLnZ5T1J/7rs8.Zq0AZpuW9p9FhL.tZ3rrITBZHeWDU4bSWkKIe6', 'active');

-- Assign Roles
INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES
(1, 1),
(2, 2);

-- Essential Granular Permissions
INSERT INTO `permissions` (`id`, `module`, `action`, `permission_key`, `description`) VALUES
(1, 'products', 'view', 'products.view', 'View product listing and details'),
(2, 'products', 'create', 'products.create', 'Add new products'),
(3, 'products', 'edit', 'products.edit', 'Edit product information'),
(4, 'products', 'delete', 'products.delete', 'Remove products'),
(5, 'inventory', 'view', 'inventory.view', 'View stock levels'),
(6, 'inventory', 'adjust', 'inventory.adjust', 'Adjust stock quantities'),
(7, 'sales', 'view', 'sales.view', 'View sale transactions'),
(8, 'sales', 'create', 'sales.create', 'Process new sales'),
(9, 'alerts', 'view', 'alerts.view', 'View urgent system alerts'),
(10, 'alerts', 'manage', 'alerts.manage', 'Trigger and manage alerts'),
(11, 'users', 'view', 'users.view', 'View registered staff'),
(12, 'users', 'create', 'users.create', 'Create new staff accounts'),
(13, 'users', 'edit', 'users.edit', 'Edit staff accounts'),
(14, 'users', 'disable', 'users.disable', 'Disable staff accounts'),
(15, 'sync', 'view', 'sync.view', 'View offline sync status'),
(16, 'sync', 'manage', 'sync.manage', 'Execute sync and resolve conflicts'),
(17, 'reports', 'view', 'reports.view', 'Access executive reports'),
(18, 'conflicts', 'view', 'conflicts.view', 'View sync conflict logs'),
(19, 'conflicts', 'resolve', 'conflicts.resolve', 'Resolve data sync conflicts');

-- Role Permission Maps (Super Admin gets all)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions`;

-- Pharmacist permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(2, 1), (2, 2), (2, 3), (2, 5), (2, 6), (2, 7), (2, 8), (2, 9);

-- Product Categories
INSERT INTO `categories` (`id`, `name`, `description`) VALUES
(1, 'Antibiotics', 'Prescription antibacterial medications'),
(2, 'Insulin & Diabetes', 'Blood glucose management products'),
(3, 'Pain Relief', 'Analgesics and anti-inflammatory drugs'),
(4, 'Cardiovascular', 'Heart and blood pressure treatments');

-- Sample Pharmacy Products
INSERT INTO `products` (`id`, `category_id`, `name`, `sku`, `price`, `stock_qty`, `low_stock_threshold`) VALUES
(1, 2, 'Humulin R Insulin 100IU/ml', 'INS-HUM-001', 2500.00, 2, 5),
(2, 1, 'Amoxicillin 500mg Capsules', 'ANT-AMX-500', 350.00, 45, 10),
(3, 3, 'Panadol Extra 500mg (Pack of 100)', 'PNL-EXT-100', 480.00, 18, 10),
(4, 4, 'Lopressor 50mg Tablets', 'CAR-LOP-050', 620.00, 4, 8);

-- Sample Service Categories
INSERT INTO `service_categories` (`id`, `name`) VALUES
(1, 'Network Continuity Solutions'),
(2, 'Emergency Hardware & Messaging');

-- Sample Services
INSERT INTO `services` (`id`, `category_id`, `title`, `description`, `price`, `image_path`) VALUES
(1, 1, 'Local Network Sync Setup', 'Configures high-speed local LAN and Wi-Fi synchronization allowing multi-device POS and operational continuity without internet connectivity.', 15000.00, 'assets/images/service-lan.svg'),
(2, 2, 'SMS Emergency Gateway Setup', 'Integrates Android SMS Gateway equipment for critical event notifications directly over SIM cellular towers during internet blackouts.', 12000.00, 'assets/images/service-sms.svg'),
(3, 1, 'Cloud Data Mirroring & Recovery', 'Provides automatic background record synchronization, conflict resolution, and central server backup when WAN access restores.', 20000.00, 'assets/images/service-cloud.svg'),
(4, 1, 'POS & Local Server Deployment', 'Full local hardware installation of Dawaam server software, database engine, and user permission hierarchies.', 25000.00, 'assets/images/service-pos.svg');

SET FOREIGN_KEY_CHECKS = 1;
