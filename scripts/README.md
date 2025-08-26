# Database Migration Scripts

This directory contains database migration scripts to ensure the PDC System database schema is properly configured.

## Available Migrations

### migrate_order_items.php
**Purpose**: Ensures the `order_items` table exists with all required columns and indexes.

**What it does**:
- Creates `order_items` table if it doesn't exist
- Adds missing columns: `paid_quantity`, `payment_method`, `paid_at`, `prepared_at`, `created_at`, `updated_at`
- Converts ENUM status columns to VARCHAR(40) for operational flexibility
- Creates performance indexes
- Fully idempotent - safe to run multiple times

**Usage**:
```bash
php migrate_order_items.php
```

### migrate_partial_receipts.php
**Purpose**: Implements partial receipt/split payment functionality.

**What it does**:
- Creates `counters` table for sequential receipt numbering
- Adds receipt metadata columns to `completed_payments` table
- Adds `paid_quantity` tracking to `order_items` table
- Creates performance indexes

**Usage**:
```bash
php migrate_partial_receipts.php
```

### migrate_all.php
**Purpose**: Runs all migrations in the correct order with unified reporting.

**What it does**:
- Executes `migrate_order_items.php` first
- Executes `migrate_partial_receipts.php` second  
- Provides progress reporting and error handling
- Stops on first failure to prevent inconsistent state

**Usage**:
```bash
php migrate_all.php
```

## Execution Notes

### Requirements
- PHP 7.4+ with PDO MySQL extension
- MySQL database server running
- Database user with DDL privileges (CREATE, ALTER, INDEX)

### Database Connection
Scripts use these connection parameters:
- Host: `127.0.0.1`
- Database: `pizza_orders`
- User: `pizza_user`
- Password: `pizza`

### Safety Features
All migration scripts are:
- **Idempotent**: Safe to run multiple times without causing issues
- **Transactional**: Changes are rolled back if any step fails  
- **Non-destructive**: Only add/modify, never delete existing data
- **Verbose**: Provide clear console output showing progress

### Production Deployment
For production use:

1. **Always backup first**:
   ```bash
   mysqldump pizza_orders > backup_$(date +%Y%m%d_%H%M%S).sql
   ```

2. **Test on staging environment first**

3. **Run during maintenance window**:
   ```bash
   php migrate_all.php
   ```

4. **Verify application functionality**

### Common Issues

**Connection refused**: Ensure MySQL is running and accessible.

**Access denied**: Verify database user has sufficient privileges:
```sql
GRANT CREATE, ALTER, INDEX, INSERT, UPDATE, DELETE, SELECT ON pizza_orders.* TO 'pizza_user'@'%';
```

**Migration fails mid-process**: Check the error message. All scripts use transactions, so partial failures are rolled back automatically.

## Related Documentation

- [Database Schema Documentation](../docs/database-schema.md) - Complete schema reference
- [PARTIAL_RECEIPTS.md](../PARTIAL_RECEIPTS.md) - Partial payment implementation details