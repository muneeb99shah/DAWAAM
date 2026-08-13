-- Dawaam - Database Migration: POS Multi-Item Cart & Line Items Table
-- Date: 2026-08-11

-- Table: sale_items
CREATE TABLE IF NOT EXISTS `sale_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sale_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_si_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_si_prod` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill sale_items for existing single-item sales
INSERT IGNORE INTO `sale_items` (`sale_id`, `product_id`, `quantity`, `unit_price`, `total_price`, `created_at`)
SELECT `id`, `product_id`, `quantity`, `unit_price`, `subtotal`, `created_at`
FROM `sales` WHERE `id` NOT IN (SELECT DISTINCT `sale_id` FROM `sale_items`);
