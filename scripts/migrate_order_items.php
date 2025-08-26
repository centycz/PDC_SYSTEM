<?php
/**
 * Database migration for order_items table
 * This script ensures the order_items table exists with all required columns
 * and handles schema upgrades to prevent 1054 Unknown column errors
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
    echo "Starting order_items table migration...\n";

    // 1. Check if order_items table exists, create if not
    echo "1. Ensuring order_items table exists...\n";
    
    $tableExists = false;
    try {
        $pdo->query("SELECT 1 FROM order_items LIMIT 1");
        $tableExists = true;
        echo "   - order_items table already exists\n";
    } catch (PDOException $e) {
        // Table doesn't exist, create it
        $pdo->exec("
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "   ✓ Created order_items table with full schema\n";
        $tableExists = true;
    }

    if ($tableExists) {
        // 2. Check existing columns and add missing ones
        echo "2. Checking and adding missing columns...\n";
        
        $columns = $pdo->query("SHOW COLUMNS FROM order_items")->fetchAll(PDO::FETCH_COLUMN);
        
        // Add paid_quantity if missing
        if (!in_array('paid_quantity', $columns)) {
            $pdo->exec("
                ALTER TABLE order_items 
                ADD COLUMN paid_quantity INT NOT NULL DEFAULT 0 AFTER quantity
            ");
            echo "   ✓ Added paid_quantity column\n";
        } else {
            echo "   - paid_quantity column already exists\n";
        }
        
        // Add payment_method if missing
        if (!in_array('payment_method', $columns)) {
            $pdo->exec("
                ALTER TABLE order_items 
                ADD COLUMN payment_method VARCHAR(20) NULL AFTER status
            ");
            echo "   ✓ Added payment_method column\n";
        } else {
            echo "   - payment_method column already exists\n";
        }
        
        // Add paid_at if missing
        if (!in_array('paid_at', $columns)) {
            $pdo->exec("
                ALTER TABLE order_items 
                ADD COLUMN paid_at TIMESTAMP NULL AFTER payment_method
            ");
            echo "   ✓ Added paid_at column\n";
        } else {
            echo "   - paid_at column already exists\n";
        }
        
        // Add prepared_at if missing
        if (!in_array('prepared_at', $columns)) {
            $pdo->exec("
                ALTER TABLE order_items 
                ADD COLUMN prepared_at TIMESTAMP NULL AFTER paid_at
            ");
            echo "   ✓ Added prepared_at column\n";
        } else {
            echo "   - prepared_at column already exists\n";
        }
        
        // Add created_at if missing
        if (!in_array('created_at', $columns)) {
            $pdo->exec("
                ALTER TABLE order_items 
                ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER prepared_at
            ");
            echo "   ✓ Added created_at column\n";
        } else {
            echo "   - created_at column already exists\n";
        }
        
        // Add updated_at if missing
        if (!in_array('updated_at', $columns)) {
            $pdo->exec("
                ALTER TABLE order_items 
                ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at
            ");
            echo "   ✓ Added updated_at column\n";
        } else {
            echo "   - updated_at column already exists\n";
        }

        // 3. Check and upgrade status column if it's ENUM
        echo "3. Checking status column type...\n";
        
        $statusColumn = $pdo->query("SHOW COLUMNS FROM order_items WHERE Field = 'status'")->fetch(PDO::FETCH_ASSOC);
        if ($statusColumn) {
            $columnType = strtoupper($statusColumn['Type']);
            if (strpos($columnType, 'ENUM') === 0) {
                // Extract ENUM values
                preg_match_all("/'([^']+)'/", $columnType, $matches);
                $currentValues = $matches[1];
                
                // Required status values
                $requiredValues = ['pending', 'preparing', 'ready', 'delivered', 'paid', 'cancelled', 'problem', 'waiting_for_release', 'ready_for_pasta', 'dessert_time'];
                $missingValues = array_diff($requiredValues, $currentValues);
                
                if (!empty($missingValues)) {
                    echo "   ! Status column is ENUM missing values: " . implode(', ', $missingValues) . "\n";
                    echo "   ✓ Converting status column to VARCHAR(40) for flexibility\n";
                    
                    try {
                        $pdo->exec("ALTER TABLE order_items MODIFY COLUMN status VARCHAR(40) NOT NULL DEFAULT 'pending'");
                        echo "   ✓ Converted status column to VARCHAR(40)\n";
                    } catch (PDOException $e) {
                        if (strpos($e->getMessage(), 'Data truncated') !== false) {
                            echo "   ! Warning: Some ENUM values may be truncated. Manual review recommended.\n";
                        } else {
                            throw $e;
                        }
                    }
                } else {
                    echo "   - Status ENUM contains all required values\n";
                }
            } else {
                echo "   - Status column is already VARCHAR or compatible type\n";
            }
        }

        // 4. Ensure required indexes exist
        echo "4. Creating required indexes...\n";
        
        $indexes = [
            'idx_order_id' => 'CREATE INDEX idx_order_id ON order_items (order_id)',
            'idx_status' => 'CREATE INDEX idx_status ON order_items (status)',
            'idx_item_type_status' => 'CREATE INDEX idx_item_type_status ON order_items (item_type, status)',
            'idx_order_paid_quantity' => 'CREATE INDEX idx_order_paid_quantity ON order_items (order_id, paid_quantity)',
            'idx_paid_at' => 'CREATE INDEX idx_paid_at ON order_items (paid_at)'
        ];
        
        foreach ($indexes as $indexName => $indexSql) {
            try {
                $pdo->exec($indexSql);
                echo "   ✓ Created index $indexName\n";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                    echo "   - Index $indexName already exists\n";
                } else {
                    throw $e;
                }
            }
        }
    }

    $pdo->commit();
    echo "\n✅ Order items table migration completed successfully!\n";
    echo "The order_items table is now ready with all required columns and indexes.\n";

} catch (Exception $e) {
    $pdo->rollback();
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}