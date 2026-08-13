-- Dawaam - Database Migration: POS Checkout Complete Professional Payment Flow
-- Date: 2026-08-11

-- Add Columns to sales table
ALTER TABLE `sales`
  ADD COLUMN IF NOT EXISTS `customer_name` VARCHAR(100) NOT NULL DEFAULT 'Walk-in Customer',
  ADD COLUMN IF NOT EXISTS `customer_phone` VARCHAR(30) NULL,
  ADD COLUMN IF NOT EXISTS `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS `discount_type` ENUM('fixed', 'percent') NOT NULL DEFAULT 'fixed',
  ADD COLUMN IF NOT EXISTS `discount_val` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS `payment_method` ENUM('cash', 'card', 'bank_transfer', 'mobile_wallet') NOT NULL DEFAULT 'cash',
  ADD COLUMN IF NOT EXISTS `payment_ref` VARCHAR(100) NULL,
  ADD COLUMN IF NOT EXISTS `amount_received` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS `change_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS `remaining_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS `payment_status` ENUM('paid', 'partial', 'unpaid') NOT NULL DEFAULT 'paid';

-- Backfill subtotal for existing records where subtotal is 0
UPDATE `sales` SET `subtotal` = `total_price` WHERE `subtotal` = 0.00;
UPDATE `sales` SET `amount_received` = `total_price` WHERE `amount_received` = 0.00;
