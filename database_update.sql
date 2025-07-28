-- SQL script to update order_users table for enhanced user management
-- Run this script to update the database for role-based access control

-- Add user_role column with default value 'user'
ALTER TABLE order_users ADD COLUMN IF NOT EXISTS user_role ENUM('admin', 'ragazzi', 'user') DEFAULT 'user';

-- Add is_active column with default value 1 (active)
ALTER TABLE order_users ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1;

-- Add email column for user contact information
ALTER TABLE order_users ADD COLUMN IF NOT EXISTS email VARCHAR(255) DEFAULT NULL;

-- Add created_at column with default current timestamp
ALTER TABLE order_users ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Update existing admin users to have 'admin' role
UPDATE order_users SET user_role = 'admin' WHERE is_admin = 1;

-- Ensure all users are active by default
UPDATE order_users SET is_active = 1 WHERE is_active IS NULL;

-- Keep is_admin column for backward compatibility but user_role is now the primary role system