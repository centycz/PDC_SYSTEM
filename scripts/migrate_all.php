<?php
/**
 * Aggregate migration script
 * Runs all database migrations in the correct order
 */

echo "=== PDC SYSTEM DATABASE MIGRATIONS ===\n\n";

$scriptDir = __DIR__;
$migrations = [
    'migrate_order_items.php' => 'Order Items Table Migration',
    'migrate_partial_receipts.php' => 'Partial Receipts Migration'
];

$success = true;
$executed = 0;

foreach ($migrations as $script => $description) {
    $scriptPath = $scriptDir . '/' . $script;
    
    if (!file_exists($scriptPath)) {
        echo "⚠️  Warning: Migration script $script not found, skipping...\n\n";
        continue;
    }
    
    echo "📋 Running: $description\n";
    echo "   Script: $script\n";
    echo str_repeat('-', 50) . "\n";
    
    // Execute the migration script
    ob_start();
    $exitCode = 0;
    
    try {
        include $scriptPath;
    } catch (Exception $e) {
        $exitCode = 1;
        echo "Error: " . $e->getMessage() . "\n";
    }
    
    $output = ob_get_clean();
    echo $output;
    
    if ($exitCode === 0 && strpos($output, '❌') === false) {
        echo "✅ $description completed successfully\n\n";
        $executed++;
    } else {
        echo "❌ $description failed\n\n";
        $success = false;
        break; // Stop on first failure
    }
}

echo str_repeat('=', 60) . "\n";
if ($success) {
    echo "🎉 All migrations completed successfully! ($executed/" . count($migrations) . " executed)\n";
    echo "Your database schema is now up to date.\n";
    exit(0);
} else {
    echo "💥 Migration process failed. Please check the errors above.\n";
    echo "Database may be in an inconsistent state.\n";
    exit(1);
}