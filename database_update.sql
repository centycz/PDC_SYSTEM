-- SQL script to add user_role column to order_users table
-- Run this script to update the database for role-based access control

-- Add user_role column with default value 'user'
ALTER TABLE order_users ADD COLUMN user_role ENUM('admin', 'ragazzi', 'user') DEFAULT 'user';

-- Update existing admin users to have 'admin' role
UPDATE order_users SET user_role = 'admin' WHERE is_admin = 1;

-- Keep is_admin column for backward compatibility but user_role is now the primary role system