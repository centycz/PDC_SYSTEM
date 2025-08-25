<?php
/**
 * Database migration for partial receipt functionality
 * This script implements the database schema changes required for split payments and receipt tracking
 */

// Database connection
function getDbConnection() {
    try {
        $pdo = new PDO('mysql:host=127.0.0.1;dbname=pizza_orders;charset=utf8mb4', 'pizza_user', 'pizza');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("SET NAMES utf8mb4");
        $pdo->exec("SET CHARACTER SET utf8mb4");
        return $pdo;
    } catch (PDOException $e) {
        echo "Connection failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}

$pdo = getDbConnection();
$pdo->beginTransaction();

try {
    echo "Starting partial receipts migration...\n";

    // 1. Ensure counters table exists (for receipt sequence)
    echo "1. Creating counters table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS counters ( 
            name VARCHAR(50) PRIMARY KEY, 
            current_value BIGINT NOT NULL 
        )
    ");
    
    $pdo->exec("
        INSERT IGNORE INTO counters (name, current_value) 
        VALUES ('receipt', 0)
    ");
    echo "   ✓ Counters table ready\n";

    // 2. Alter completed_payments to support receipt metadata & partial receipts
    echo "2. Altering completed_payments table...\n";
    
    // Check if columns already exist
    $columns = $pdo->query("SHOW COLUMNS FROM completed_payments")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('receipt_number', $columns)) {
        $pdo->exec("
            ALTER TABLE completed_payments
            ADD COLUMN receipt_number BIGINT UNIQUE AFTER id
        ");
        echo "   ✓ Added receipt_number column\n";
    } else {
        echo "   - receipt_number column already exists\n";
    }
    
    if (!in_array('employee_name', $columns)) {
        $pdo->exec("
            ALTER TABLE completed_payments
            ADD COLUMN employee_name VARCHAR(100) NULL AFTER payment_method
        ");
        echo "   ✓ Added employee_name column\n";
    } else {
        echo "   - employee_name column already exists\n";
    }
    
    if (!in_array('items_json', $columns)) {
        $pdo->exec("
            ALTER TABLE completed_payments
            ADD COLUMN items_json MEDIUMTEXT NULL AFTER employee_name
        ");
        echo "   ✓ Added items_json column\n";
    } else {
        echo "   - items_json column already exists\n";
    }
    
    if (!in_array('printed_at', $columns)) {
        $pdo->exec("
            ALTER TABLE completed_payments
            ADD COLUMN printed_at TIMESTAMP NULL AFTER paid_at
        ");
        echo "   ✓ Added printed_at column\n";
    } else {
        echo "   - printed_at column already exists\n";
    }
    
    if (!in_array('reprint_count', $columns)) {
        $pdo->exec("
            ALTER TABLE completed_payments
            ADD COLUMN reprint_count INT NOT NULL DEFAULT 0 AFTER printed_at
        ");
        echo "   ✓ Added reprint_count column\n";
    } else {
        echo "   - reprint_count column already exists\n";
    }

    // 3. Track paid portion of each item
    echo "3. Adding paid_quantity tracking...\n";
    
    // Check order_items table
    $orderItemsColumns = $pdo->query("SHOW COLUMNS FROM order_items")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('paid_quantity', $orderItemsColumns)) {
        $pdo->exec("
            ALTER TABLE order_items 
            ADD COLUMN paid_quantity INT NOT NULL DEFAULT 0 AFTER quantity
        ");
        echo "   ✓ Added paid_quantity to order_items\n";
    } else {
        echo "   - paid_quantity already exists in order_items\n";
    }

    // 4. Add helpful indexes
    echo "4. Creating indexes...\n";
    
    try {
        $pdo->exec("
            CREATE INDEX idx_order_items_paid ON order_items (order_id, paid_quantity)
        ");
        echo "   ✓ Created index idx_order_items_paid\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "   - Index idx_order_items_paid already exists\n";
        } else {
            throw $e;
        }
    }
    
    try {
        $pdo->exec("
            CREATE INDEX idx_completed_payments_table ON completed_payments (table_number)
        ");
        echo "   ✓ Created index idx_completed_payments_table\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "   - Index idx_completed_payments_table already exists\n";
        } else {
            throw $e;
        }
    }

    $pdo->commit();
    echo "\n✅ Migration completed successfully!\n";
    echo "The database is now ready for partial receipt functionality.\n";

} catch (Exception $e) {
    $pdo->rollback();
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}