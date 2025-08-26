# Database Schema Documentation

## Overview

This document describes the required database schema for the PDC System, with focus on the `order_items` table and migration processes.

## Order Items Table

The `order_items` table is central to the order management system and must contain all required columns to prevent runtime errors.

### Required Schema

```sql
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    item_type VARCHAR(50) NOT NULL DEFAULT 'pizza',
    item_name VARCHAR(255) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    quantity INT NOT NULL DEFAULT 1,
    paid_quantity INT NOT NULL DEFAULT 0,
    note TEXT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'pending',
    payment_method VARCHAR(20) NULL,
    paid_at TIMESTAMP NULL,
    prepared_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order_id (order_id),
    INDEX idx_status (status),
    INDEX idx_item_type_status (item_type, status),
    INDEX idx_order_paid_quantity (order_id, paid_quantity),
    INDEX idx_paid_at (paid_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Column Descriptions

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT AUTO_INCREMENT | Primary key |
| `order_id` | INT NOT NULL | Foreign key to orders table |
| `item_type` | VARCHAR(50) | Type of item (pizza, pasta, dessert, etc.) |
| `item_name` | VARCHAR(255) | Name/description of the item |
| `unit_price` | DECIMAL(10,2) | Price per unit |
| `quantity` | INT | Total quantity ordered |
| `paid_quantity` | INT | Quantity that has been paid for (for partial payments) |
| `note` | TEXT | Optional notes/special instructions |
| `status` | VARCHAR(40) | Current status of the item |
| `payment_method` | VARCHAR(20) | Payment method used (cash, card, etc.) |
| `paid_at` | TIMESTAMP | When the item was paid for |
| `prepared_at` | TIMESTAMP | When the item was prepared |
| `created_at` | TIMESTAMP | When the record was created |
| `updated_at` | TIMESTAMP | When the record was last updated |

### Status Values

The `status` column supports the following values:
- `pending` - Item has been ordered but not started
- `preparing` - Item is being prepared
- `ready` - Item is ready for service
- `delivered` - Item has been delivered to customer
- `paid` - Item has been paid for
- `cancelled` - Item was cancelled
- `problem` - There's an issue with the item
- `waiting_for_release` - Waiting for kitchen release
- `ready_for_pasta` - Ready for pasta preparation
- `dessert_time` - Ready for dessert service

**Note**: The status column is implemented as `VARCHAR(40)` instead of ENUM for operational flexibility and to avoid ALTER TABLE operations when adding new status values.

### Indexes

The table includes several indexes for optimal performance:

- `idx_order_id` - Fast lookup by order
- `idx_status` - Fast filtering by status
- `idx_item_type_status` - Fast filtering by item type and status
- `idx_order_paid_quantity` - Optimizes partial payment queries
- `idx_paid_at` - Fast filtering by payment date

## Migration Scripts

### Running Migrations

The system provides two migration scripts:

#### 1. Order Items Migration
```bash
php scripts/migrate_order_items.php
```

This script:
- Creates the `order_items` table if it doesn't exist
- Adds missing columns to existing tables
- Converts ENUM status columns to VARCHAR if needed
- Creates required indexes
- Is fully idempotent - safe to run multiple times

#### 2. Partial Receipts Migration
```bash
php scripts/migrate_partial_receipts.php
```

This script handles:
- Receipt numbering system
- Partial payment tracking
- Receipt printing metadata
- Performance indexes

#### 3. Run All Migrations
```bash
php scripts/migrate_all.php
```

This convenience script runs both migrations in the correct order and provides unified progress reporting.

### Migration Safety

All migration scripts are:
- **Idempotent** - Safe to run multiple times
- **Transactional** - Roll back on errors
- **Non-destructive** - Only add, never remove data
- **Verbose** - Provide clear progress feedback

### Production Deployment

For production deployments:

1. **Backup your database** before running migrations
2. Run migrations during maintenance windows
3. Test migrations on staging environment first
4. Use `migrate_all.php` for consistency

Example deployment process:
```bash
# 1. Backup
mysqldump pizza_orders > backup_$(date +%Y%m%d_%H%M%S).sql

# 2. Run migrations
php scripts/migrate_all.php

# 3. Verify application functionality
# Test order creation and payment processing
```

## Common Issues and Solutions

### 1054 Unknown Column Errors

**Symptom**: Application fails with "Unknown column 'updated_at'" or similar errors.

**Solution**: Run the order items migration:
```bash
php scripts/migrate_order_items.php
```

### ENUM Status Issues

**Symptom**: Cannot insert new status values, getting "Data truncated" errors.

**Solution**: The migration automatically converts ENUM to VARCHAR(40) for flexibility.

### Missing Indexes

**Symptom**: Slow queries on order_items table.

**Solution**: Run the migration to ensure all performance indexes are created.

## Related Documentation

- [PARTIAL_RECEIPTS.md](../PARTIAL_RECEIPTS.md) - Detailed documentation on partial payment functionality
- [API Documentation] - Application API endpoints and usage

## Database Connection

The system uses these connection parameters:
- Host: `127.0.0.1`
- Database: `pizza_orders`
- User: `pizza_user`
- Password: `pizza`
- Charset: `utf8mb4`

Ensure your database server is configured to accept these connections and the user has appropriate privileges for DDL operations during migrations.