-- Migration: Add support for reserved vs walk-in inventory tracking
-- Date: 2025-01-18

-- Step 1: Add is_reserved column to orders table
ALTER TABLE `orders` ADD COLUMN `is_reserved` BOOLEAN DEFAULT FALSE AFTER `employee_name`;

-- Step 2: Extend daily_supplies table to track reserved vs walk-in inventory
ALTER TABLE `daily_supplies` 
ADD COLUMN `pizza_reserved` INT(11) NOT NULL DEFAULT 0 AFTER `pizza_used`,
ADD COLUMN `pizza_walkin` INT(11) NOT NULL DEFAULT 0 AFTER `pizza_reserved`,
ADD COLUMN `burrata_reserved` INT(11) NOT NULL DEFAULT 0 AFTER `burrata_used`, 
ADD COLUMN `burrata_walkin` INT(11) NOT NULL DEFAULT 0 AFTER `burrata_reserved`;

-- Step 3: Initialize existing records with walk-in values (backward compatibility)
UPDATE `daily_supplies` 
SET 
    `pizza_walkin` = `pizza_total`,
    `burrata_walkin` = `burrata_total`
WHERE `pizza_walkin` = 0 AND `burrata_walkin` = 0;

-- Step 4: Add index for performance
ALTER TABLE `orders` ADD INDEX `idx_is_reserved` (`is_reserved`);