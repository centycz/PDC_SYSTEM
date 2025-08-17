<?php
// Test version without session check
session_start();

// Prevent caching and Firefox dialog on refresh after POST
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Mock session data for testing
$_SESSION['order_user'] = 'test_user';
$_SESSION['order_full_name'] = 'Test User';
$_SESSION['user_role'] = 'admin';
$_SESSION['username'] = 'test';

// Get user information from session
$user_name = $_SESSION['order_user'];
$full_name = $_SESSION['order_full_name'];
$user_role = $_SESSION['user_role'];

// Připojení k databázi - using SQLite for testing if MySQL is not available
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=pizza_orders;charset=utf8mb4', 'pizza_user', 'pizza');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    // Use dummy data if DB is not available
    $date = date('Y-m-d');
    $pizza_total = 120;
    $burrata_total = 15;
    $pizza_remaining = 80;
    $burrata_remaining = 10;
    $daily_orders = 25;
    $daily_revenue = "15,250";
    $avg_order = "610";
    $peak_time = "19:30";
    $total_reservations = 12;
    $past_reservations = 5;
    $upcoming_reservations = 7;
    $total_upcoming_people = 18;
    $total_people_today = 32;
    $next_reservation = [
        'customer_name' => 'Novák',
        'reservation_time' => '18:30:00',
        'party_size' => 4,
        'table_number' => 3
    ];
    $pizzy_count = 8;
    $pasty_count = 3;
    $predkrmy_count = 2;
    $dezerty_count = 1;
    $waiting_time = 25;
    $pizza_percentage = 67;
    $burrata_percentage = 67;
    $pizza_alert = false;
    $burrata_alert = false;
    
    // Add dummy debug info
    $debug_info = ['pizza_calculation' => 'Test mode - no DB connection'];
    
    goto skip_db_logic;
}

// Continue with original database logic...
$date = date('Y-m-d');

// Mock all the database queries with sample data for testing
$pizza_total = 120;
$burrata_total = 15;
$pizza_remaining = 80;
$burrata_remaining = 10;
$daily_orders = 25;
$daily_revenue = "15,250";
$avg_order = "610";
$peak_time = "19:30";
$total_reservations = 12;
$past_reservations = 5;
$upcoming_reservations = 7;
$total_upcoming_people = 18;
$total_people_today = 32;
$next_reservation = [
    'customer_name' => 'Novák',
    'reservation_time' => '18:30:00',
    'party_size' => 4,
    'table_number' => 3
];
$pizzy_count = 8;
$pasty_count = 3;
$predkrmy_count = 2;
$dezerty_count = 1;
$waiting_time = 25;
$pizza_percentage = 67;
$burrata_percentage = 67;
$pizza_alert = false;
$burrata_alert = false;
$debug_info = ['pizza_calculation' => 'Test mode - mock data'];

skip_db_logic:
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Status Dashboard - Pizza dal Cortile</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .header {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #333;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-info {
            text-align: right;
            color: #666;
            font-size: 0.9rem;
        }

        .stats-panel {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .panel-title {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .stat-item {
            text-align: center;
            padding: 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 12px;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 Pizza dal Cortile - Aktuální stav (TEST)</h1>
        <div class="header-info">
            <div>📅 <?= date('d.m.Y H:i:s') ?></div>
            <div>👤 Test mode</div>
        </div>
    </div>

    <!-- Rezervace panel -->
    <div class="stats-panel">
        <div class="panel-title">
            📅 Rezervace na dnes
        </div>
        
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-number" id="total-reservations"><?= $total_reservations ?></div>
                <div class="stat-label">📋 Celkem rezervací</div>
            </div>
            
            <div class="stat-item">
                <div class="stat-number" id="past-reservations"><?= $past_reservations ?></div>
                <div class="stat-label">✅ Již proběhly</div>
            </div>
            
            <div class="stat-item">
                <div class="stat-number" id="upcoming-reservations"><?= $upcoming_reservations ?></div>
                <div class="stat-label">⏰ Nadcházející</div>
            </div>
            
            <div class="stat-item">
                <div class="stat-number" id="total-upcoming-people"><?= $total_upcoming_people ?></div>
                <div class="stat-label">👥 Lidí čeká</div>
            </div>
            
            <div class="stat-item">
                <div class="stat-number" id="total-people-today"><?= $total_people_today ?></div>
                <div class="stat-label">👥 Lidí celkem dnes</div>
            </div>
            
            <div class="stat-item">
                <?php if ($next_reservation): ?>
                    <div class="stat-number" style="font-size: 1.2em;"><?= date('H:i', strtotime($next_reservation['reservation_time'])) ?></div>
                    <div class="stat-label">⏭️ Nejbližší</div>
                    <div style="font-size: 0.8em; color: #666; margin-top: 5px;">
                        <?= htmlspecialchars($next_reservation['customer_name']) ?> (<?= $next_reservation['party_size'] ?> osob)
                        <?php if ($next_reservation['table_number']): ?>
                            <br>Stůl <?= $next_reservation['table_number'] ?>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="stat-number">--:--</div>
                    <div class="stat-label">⏭️ Nejbližší</div>
                    <div style="font-size: 0.8em; color: #666; margin-top: 5px;">Žádné další rezervace</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>