<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['order_user'])) {
    header('Location: login.php');
    exit;
}

// Get user information from session
$user_name = $_SESSION['order_user'];
$full_name = $_SESSION['order_full_name'];
$user_role = $_SESSION['user_role'];

// Připojení k databázi
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=pizza_orders;charset=utf8mb4', 'pizza_user', 'pizza');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Chyba připojení: " . $e->getMessage());
}

// Zpracování aktualizace zásob
if ($_POST['action'] ?? false) {
    if ($_POST['action'] === 'update_supplies') {
        try {
            $pizza_total = (int)$_POST['pizza_total'];
            $burrata_total = (int)$_POST['burrata_total'];
            $date = date('Y-m-d');
            
            $stmt = $pdo->prepare("
                INSERT INTO daily_supplies (date, pizza_total, burrata_total, updated_by, updated_at) 
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                pizza_total = VALUES(pizza_total), 
                burrata_total = VALUES(burrata_total),
                updated_by = VALUES(updated_by),
                updated_at = NOW()
            ");
            $stmt->execute([$date, $pizza_total, $burrata_total, $_SESSION['username'] ?? 'centycz']);
            
            $success_message = "Zásoby byly úspěšně aktualizovány!";
        } catch(PDOException $e) {
            $error_message = "Chyba při ukládání: " . $e->getMessage();
        }
    }
    
    // ✅ RESET DAY - PŘESUNUTÝ NAHORU!
    

 if ($_POST['action'] === 'reset_day') {
    try {
        $date = date('Y-m-d');
        
        // ✅ SMAZAT dnešní objednávky
        $stmt = $pdo->prepare("DELETE FROM orders WHERE DATE(created_at) = ?");
        $stmt->execute([$date]);
        
        $stmt = $pdo->prepare("DELETE FROM burnt_pizzas_log WHERE DATE(burnt_at) = ?");
        $stmt->execute([$date]);
        
        // Resetovat zásoby
        $stmt = $pdo->prepare("
            INSERT INTO daily_supplies (date, pizza_total, burrata_total, updated_by, updated_at) 
            VALUES (?, 120, 15, ?, NOW())
            ON DUPLICATE KEY UPDATE 
            pizza_total = 120, 
            burrata_total = 15,
            updated_by = VALUES(updated_by),
            updated_at = NOW()
        ");
        $stmt->execute([$date, $_SESSION['username'] ?? 'centycz']);
        
        header("Location: status_dashboard.php?reset=success");
        exit;
    } catch(PDOException $e) {
        $error_message = "Chyba při resetování: " . $e->getMessage();
    }
}
}

$date = date('Y-m-d');
if (isset($_GET['reset']) && $_GET['reset'] === 'success') {
    $success_message = "🔄 Zásoby byly resetovány na nový den!";
}
// Načtení aktuálních zásob
// Načtení aktuálních zásob s automatickým resetem
$stmt = $pdo->prepare("SELECT * FROM daily_supplies WHERE date = ?");
$stmt->execute([$date]);
$supplies = $stmt->fetch(PDO::FETCH_ASSOC);

// ✅ AUTOMATICKÝ RESET - pokud pro dnešek neexistují zásoby, vytvoř defaultní
if (!$supplies) {
    $stmt = $pdo->prepare("
        INSERT INTO daily_supplies (date, pizza_total, burrata_total, updated_by, updated_at) 
        VALUES (?, 120, 15, 'AUTO-RESET', NOW())
    ");
    $stmt->execute([$date]);
    
    $pizza_total = 120;
    $burrata_total = 15;
    $success_message = "🔄 Nový den! Zásoby automaticky nastaveny na výchozí hodnoty.";
} else {
    $pizza_total = $supplies['pizza_total'];
    $burrata_total = $supplies['burrata_total'];
}

// Počítání kuchyně - jen aktivně připravované
$debug_info = [];
$kitchen_items = [];

try {
    $stmt = $pdo->prepare("
        SELECT 
            oi.id,
            oi.item_name,
            oi.item_type,
            oi.quantity,
            oi.status as item_status,
            oi.note,
            o.id as order_id,
            o.status as order_status,
            o.created_at
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id 
        WHERE oi.item_type IN ('pizza', 'pasta', 'predkrm', 'dezert')
        AND oi.status IN ('pending', 'preparing')
        ORDER BY o.created_at DESC
        LIMIT 50
    ");
    $stmt->execute();
    $kitchen_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $debug_info['query_result'] = count($kitchen_items) . ' položek načteno (jen pending+preparing)';
    $debug_info['query_time'] = date('H:i:s');
    
    $stmt2 = $pdo->prepare("
        SELECT COUNT(*) as active_sessions
        FROM table_sessions ts 
        WHERE ts.is_active = 1
    ");
    $stmt2->execute();
    $active_sessions = $stmt2->fetch(PDO::FETCH_ASSOC)['active_sessions'];
    $debug_info['active_sessions'] = $active_sessions;
    
    $stmt3 = $pdo->prepare("
        SELECT 
            oi.id,
            oi.item_name,
            oi.item_type,
            oi.quantity,
            oi.status as item_status,
            oi.note,
            o.id as order_id,
            o.status as order_status,
            ts.table_number,
            o.created_at
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id 
        JOIN table_sessions ts ON o.table_session_id = ts.id
        WHERE ts.is_active = 1
        AND oi.item_type IN ('pizza', 'pasta', 'predkrm', 'dezert')
        AND oi.status IN ('pending', 'preparing')
        ORDER BY o.created_at DESC
    ");
    $stmt3->execute();
    $kitchen_items_full = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    
    $debug_info['full_query_result'] = count($kitchen_items_full) . ' položek s active sessions (jen pending+preparing)';
    
    if (count($kitchen_items_full) > 0) {
        $kitchen_items = $kitchen_items_full;
        $debug_info['used_query'] = 'full (s table_sessions + jen preparing)';
    } else {
        $debug_info['used_query'] = 'basic (bez table_sessions + jen preparing)';
    }
    
    $stmt4 = $pdo->prepare("
        SELECT COUNT(*) as ready_items
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id 
        WHERE oi.item_type IN ('pizza', 'pasta', 'predkrm', 'dezert')
        AND oi.status = 'ready'
    ");
    $stmt4->execute();
    $ready_count = $stmt4->fetch(PDO::FETCH_ASSOC)['ready_items'];
    $debug_info['ready_items'] = $ready_count . ' položek je ready (nepočítáme)';
    
} catch(PDOException $e) {
    $debug_info['query_error'] = $e->getMessage();
}

// Počítání kategorií
$pizzaCount = 0;
$pastaCount = 0;
$appetizerCount = 0;
$dessertCount = 0;

$debug_info['items_detail'] = [];

if ($kitchen_items && count($kitchen_items) > 0) {
    foreach ($kitchen_items as $item) {
        $quantity = (int)($item['quantity'] ?? 1);
        $itemType = $item['item_type'] ?? '';
        
        $debug_info['items_detail'][] = [
            'id' => $item['id'],
            'name' => $item['item_name'],
            'type' => $itemType,
            'quantity' => $quantity,
            'status' => $item['item_status'],
            'table' => $item['table_number'] ?? 'N/A',
            'created' => $item['created_at']
        ];
        
        switch($itemType) {
            case 'pizza':
                $pizzaCount += $quantity;
                break;
            case 'pasta':
                $pastaCount += $quantity;
                break;
            case 'predkrm':
                $appetizerCount += $quantity;
                break;
            case 'dezert':
                $dessertCount += $quantity;
                break;
        }
    }
}

$pizzy_count = $pizzaCount;
$pasty_count = $pastaCount;
$predkrmy_count = $appetizerCount;
$dezerty_count = $dessertCount;

if ($pizzy_count <= 5) {
    $waiting_time = 15;
} elseif ($pizzy_count <= 10) {
    $waiting_time = 25;
} elseif ($pizzy_count <= 15) {
    $waiting_time = 35;
} else {
    $waiting_time = 45;
}

// ✅ NOVÁ LOGIKA POČÍTÁNÍ ZÁSOB - POUŽÍVÁ BURNT_PIZZAS_LOG
try {
    // Počítáme normální pizzy
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(oi.quantity), 0) as normal_pizzas
        FROM orders o 
        JOIN order_items oi ON o.id = oi.order_id 
        WHERE DATE(o.created_at) = ? 
        AND oi.item_type = 'pizza'
        AND oi.status IN ('pending', 'preparing', 'ready', 'delivered', 'paid')
    ");
    $stmt->execute([$date]);
    $normal_pizzas = $stmt->fetch(PDO::FETCH_ASSOC)['normal_pizzas'] ?? 0;
    
    // ✅ KLÍČOVÁ ČÁST: Počítáme spálené pizzy z burnt_pizzas_log
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as burned_pizzas
        FROM burnt_pizzas_log 
        WHERE DATE(burnt_at) = ?
    ");
    $stmt->execute([$date]);
    $burned_pizzas = $stmt->fetch(PDO::FETCH_ASSOC)['burned_pizzas'] ?? 0;
    
    // ✅ VÝPOČET: normální pizzy + 2x spálené (protože spálená = nové těsto)
    $pizza_used = $normal_pizzas + $burned_pizzas;

$debug_info['pizza_calculation'] = "Objednané pizzy: {$normal_pizzas}, Dodatečně spálené: {$burned_pizzas}, Celková spotřeba těsta: {$pizza_used}";
    
} catch(PDOException $e) {
    $pizza_used = 0;
    $debug_info['pizza_calc_error'] = $e->getMessage();
}


// Burrata zůstává stejná
// ✅ NOVÁ LOGIKA PRO BURRATU - stejně jako u pizzy
try {
    // Počítáme jen DODANÉ položky s burratou (ne spálené!)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(oi.quantity), 0) as burrata_used
        FROM orders o 
        JOIN order_items oi ON o.id = oi.order_id 
        WHERE DATE(o.created_at) = ? 
        AND (oi.item_name LIKE '%burrata%' OR oi.item_name LIKE '%Burrata%')
        AND oi.status IN ('pending', 'preparing', 'ready', 'delivered', 'paid')
    ");
    $stmt->execute([$date]);
    $burrata_used = $stmt->fetch(PDO::FETCH_ASSOC)['burrata_used'] ?? 0;
    
    $debug_info['burrata_calculation'] = "Dodané položky s burratou: {$burrata_used} (spálené se nepočítají - burrata se dává až na hotovou pizzu)";
    
} catch(PDOException $e) {
    $burrata_used = 0;
    $debug_info['burrata_calc_error'] = $e->getMessage();
}
$pizza_remaining = max(0, $pizza_total - $pizza_used);
$burrata_remaining = max(0, $burrata_total - $burrata_used);

$pizza_percentage = $pizza_total > 0 ? ($pizza_remaining / $pizza_total) * 100 : 0;
$burrata_percentage = $burrata_total > 0 ? ($burrata_remaining / $burrata_total) * 100 : 0;

// Denní statistiky
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT o.id) as daily_orders,
            COALESCE(SUM(oi.quantity * oi.unit_price), 0) as daily_revenue
        FROM orders o 
        JOIN order_items oi ON o.id = oi.order_id 
        WHERE DATE(o.created_at) = ?
    ");
    $stmt->execute([$date]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $daily_orders = $stats['daily_orders'] ?? 0;
    $daily_revenue = number_format($stats['daily_revenue'] ?? 0, 0, ',', ' ');
    $avg_order = $daily_orders > 0 ? number_format(($stats['daily_revenue'] ?? 0) / $daily_orders, 0, ',', ' ') : 0;
    
    $stmt = $pdo->prepare("
        SELECT HOUR(o.created_at) as peak_hour, COUNT(*) as orders_count
        FROM orders o 
        WHERE DATE(o.created_at) = ?
        GROUP BY HOUR(o.created_at)
        ORDER BY orders_count DESC
        LIMIT 1
    ");
    $stmt->execute([$date]);
    $peak_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $peak_time = $peak_data ? sprintf('%02d:00', $peak_data['peak_hour']) : '--:--';
    
} catch(PDOException $e) {
    $daily_orders = 0;
    $daily_revenue = "0";
    $avg_order = "0";
    $peak_time = "--:--";
}

$low_pizza_threshold = $pizza_total * 0.2;
$low_burrata_threshold = $burrata_total * 0.2;

$pizza_alert = $pizza_remaining <= $low_pizza_threshold;
$burrata_alert = $burrata_remaining <= $low_burrata_threshold;
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

        .debug-box {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 0.85rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .debug-title {
            font-weight: bold;
            color: #495057;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .status-panel {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
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

        .kitchen-note {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 4px;
            font-size: 0.9rem;
            color: #1565c0;
        }

        .burned-note {
            background: #fff3e0;
            border-left: 4px solid #ff9800;
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 4px;
            font-size: 0.9rem;
            color: #e65100;
        }

        .status-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .status-card {
            text-align: center;
            padding: 20px 15px;
            border-radius: 12px;
            border-left: 5px solid;
            background: #f8f9fa;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .status-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .status-card.pizzy { border-color: #2196F3; }
        .status-card.pasty { border-color: #FF9800; }
        .status-card.predkrmy { border-color: #4CAF50; }
        .status-card.dezerty { border-color: #F44336; }

        .status-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 10px 0;
            color: #333;
        }

        .status-label {
            font-size: 0.9rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .waiting-time {
            text-align: center;
            background: linear-gradient(135deg, #9C27B0, #E91E63);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-top: 15px;
        }

        .waiting-time-number {
            font-size: 2rem;
            font-weight: bold;
        }

        .supplies-list {
            list-style: none;
        }

        .supply-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .supply-item:last-child {
            border-bottom: none;
        }

        .supply-name {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }

        .supply-status {
            font-weight: bold;
        }

        .supply-status.good { color: #4CAF50; }
        .supply-status.warning { color: #FF9800; }
        .supply-status.critical { color: #F44336; }

        .progress-bar {
            width: 100px;
            height: 8px;
            background: #eee;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s;
        }

        .progress-fill.good { background: #4CAF50; }
        .progress-fill.warning { background: #FF9800; }
        .progress-fill.critical { background: #F44336; }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: pulse 2s infinite;
        }

        .alert.critical {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }

        .alert.warning {
            background: #fff3cd;
            color: #856404;
            border: 2px solid #ffeaa7;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .edit-supplies {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            border: 2px dashed #ddd;
        }

        .edit-supplies.editing {
            border-color: #667eea;
            background: #f0f4ff;
        }

        .edit-form {
            display: none;
        }

        .edit-form.active {
            display: block;
        }

        .form-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .form-row label {
            min-width: 80px;
            font-weight: 500;
        }

        .form-row input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a6fd8;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-edit {
            background: #28a745;
            color: white;
            font-size: 0.8rem;
            padding: 6px 12px;
        }

        .btn-edit:hover {
            background: #218838;
        }

        .message {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-weight: 500;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .stats-panel {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            grid-column: 1 / -1;
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

        .refresh-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 10px 15px;
            border-radius: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-size: 0.8rem;
            color: #666;
        }

        .back-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: transform 0.2s;
        }

        .back-btn:hover {
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .status-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="refresh-indicator" id="refreshIndicator">
        🔄 Automatická aktualizace: <span id="countdown">15</span>s
    </div>

    <div class="header">
        <h1>📊 Pizza dal Cortile - Aktuální stav</h1>
        <div class="header-info">
            <div>📅 <?= date('d.m.Y H:i:s') ?></div>
            <div>👤 Přihlášen: <?= $_SESSION['username'] ?? 'centycz' ?></div>
        </div>
    </div>

    

    <?php if (isset($success_message)): ?>
        <div class="message success"><?= $success_message ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="message error"><?= $error_message ?></div>
    <?php endif; ?>

    <?php if ($pizza_alert): ?>
        <div class="alert critical">
            🚨 KRITICKÉ: Zbývá jen <?= $pizza_remaining ?> pizz z <?= $pizza_total ?>! Připrav další těsto!
        </div>
    <?php endif; ?>

    <?php if ($burrata_alert): ?>
        <div class="alert warning">
            ⚠️ POZOR: Zbývá jen <?= $burrata_remaining ?> porcí burraty z <?= $burrata_total ?>!
        </div>
    <?php endif; ?>

    <div class="dashboard-grid">
        <!-- Levý panel - Kuchyň v reálném čase -->
        <div class="status-panel">
            <div class="panel-title">
                👨‍🍳 Kuchyň v reálném čase
            </div>
            
            <div class="kitchen-note">
                💡 <strong>Poznámka:</strong> Zobrazuje se jen jídlo, které se AKTIVNĚ PŘIPRAVUJE (pending, preparing). Hotové jídlo připravené k podání (ready) už není započítané.
            </div>
            
            <?php if (isset($burned_pizzas) && $burned_pizzas > 0): ?>
            <div class="burned-note">
                🔥 <strong>Spálené pizzy dnes:</strong> <?= $burned_pizzas ?> pizz (spotřeba <?= $burned_pizzas * 2 ?> kusů těsta)
            </div>
            <?php endif; ?>
            
            <div class="status-cards">
                <div class="status-card pizzy">
                    <div class="status-number" id="pizzy-count"><?= $pizzy_count ?></div>
                    <div class="status-label">🍕 Pizzy</div>
                </div>
                
                <div class="status-card pasty">
                    <div class="status-number" id="pasty-count"><?= $pasty_count ?></div>
                    <div class="status-label">🍝 Pasty</div>
                </div>
                
                <div class="status-card predkrmy">
                    <div class="status-number" id="predkrmy-count"><?= $predkrmy_count ?></div>
                    <div class="status-label">🥗 Předkrmy</div>
                </div>
                
                <div class="status-card dezerty">
                    <div class="status-number" id="dezerty-count"><?= $dezerty_count ?></div>
                    <div class="status-label">🍰 Dezerty</div>
                </div>
            </div>

            <div class="waiting-time">
                <div class="waiting-time-number" id="waiting-time"><?= $waiting_time ?></div>
                <div>⏱️ Odhadovaná čekací doba (minuty)</div>
            </div>
        </div>

        <!-- Pravý panel - Zásoby -->
        <div class="status-panel">
      <div class="panel-title">
    📦 Zásoby na dnes
    <button class="btn btn-edit" onclick="toggleEdit()">✏️ Upravit</button>
    <button class="btn" style="background: #e74c3c; color: white; margin-left: 5px;" onclick="resetDay()">🔄 Nový den</button>
</div>

            
            <ul class="supplies-list">
                <li class="supply-item">
                    <div class="supply-name">
                        🍕 Pizzy <small>(zahrnuje spálené)</small>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span class="supply-status <?= $pizza_percentage > 50 ? 'good' : ($pizza_percentage > 20 ? 'warning' : 'critical') ?>">
                            <?= $pizza_remaining ?>/<?= $pizza_total ?>
                        </span>
                        <div class="progress-bar">
                            <div class="progress-fill <?= $pizza_percentage > 50 ? 'good' : ($pizza_percentage > 20 ? 'warning' : 'critical') ?>" 
                                 style="width: <?= $pizza_percentage ?>%"></div>
                        </div>
                    </div>
                </li>
                
                <li class="supply-item">
                    <div class="supply-name">
                        🧀 Burrata <small>(odečítáno při objednání)</small>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span class="supply-status <?= $burrata_percentage > 50 ? 'good' : ($burrata_percentage > 20 ? 'warning' : 'critical') ?>">
                            <?= $burrata_remaining ?> porcí
                        </span>
                        <div class="progress-bar">
                            <div class="progress-fill <?= $burrata_percentage > 50 ? 'good' : ($burrata_percentage > 20 ? 'warning' : 'critical') ?>" 
                                 style="width: <?= $burrata_percentage ?>%"></div>
                        </div>
                    </div>
                </li>
                
                         </ul>

            <!-- Editační formulář -->
            <div class="edit-supplies" id="editSupplies">
                <div class="edit-form" id="editForm">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_supplies">
                        
                        <div class="form-row">
                            <label>🍕 Pizzy:</label>
                            <input type="number" name="pizza_total" value="<?= $pizza_total ?>" min="0" max="500" required>
                            <span style="font-size: 0.8rem; color: #666;">celkem na den</span>
                        </div>
                        
                        <div class="form-row">
                            <label>🧀 Burrata:</label>
                            <input type="number" name="burrata_total" value="<?= $burrata_total ?>" min="0" max="100" required>
                            <span style="font-size: 0.8rem; color: #666;">porcí na den</span>
                        </div>
                        
                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary">💾 Uložit</button>
                            <button type="button" class="btn btn-secondary" onclick="cancelEdit()">❌ Zrušit</button>
                        </div>
                    </form>
                </div>
                
                <div id="editHint">
                    <p style="text-align: center; color: #666; font-size: 0.9rem;">
                        💡 Klikněte na "Upravit" pro změnu denních zásob
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Dolní panel - Dnešní statistiky -->
    <div class="stats-panel">
        <div class="panel-title">
            📈 Dnešní statistiky
        </div>
        
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-number" id="daily-revenue"><?= $daily_revenue ?></div>
                <div class="stat-label">💰 Tržby (Kč)</div>
            </div>
            
            <div class="stat-item">
                <div class="stat-number" id="daily-orders"><?= $daily_orders ?></div>
                <div class="stat-label">📋 Objednávky</div>
            </div>
            
            <div class="stat-item">
                <div class="stat-number" id="avg-order"><?= $avg_order ?></div>
                <div class="stat-label">🎯 Průměr (Kč)</div>
            </div>
            
            <div class="stat-item">
                <div class="stat-number" id="peak-time"><?= $peak_time ?></div>
                <div class="stat-label">⚡ Špička</div>
            </div>
        </div>
    </div>

    <div style="margin-top: 20px; text-align: center;">
        <a href="/index.php" class="back-btn">
    ← Zpět
</a>

    </div>

    <script>
        let countdownTimer = 15;
        
      function resetDay() {
     if (confirm('🔄 NOVÝ DEN\n\nTohle SMAŽE všechny dnešní objednávky a resetuje zásoby:\n🍕 Pizzy: 120 ks\n🧀 Burrata: 15 ks\n\n⚠️ POZOR: Ztratíš dnešní statistiky!\n\nOpravdu pokračovat?')) {
        // ✅ ZASTAVIT AUTO-REFRESH
        clearInterval(refreshInterval);
        
        const form = document.createElement('form');
        form.method = 'POST';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'reset_day';
        
        form.appendChild(actionInput);
        document.body.appendChild(form);
        form.submit();
    }
}
        function updateCountdown() {
            const countdownElement = document.getElementById('countdown');
            countdownElement.textContent = countdownTimer;
            
            if (countdownTimer <= 0) {
                location.reload();
                countdownTimer = 15;
            } else {
                countdownTimer--;
            }
        }
        
        function toggleEdit() {
            const editSupplies = document.getElementById('editSupplies');
            const editForm = document.getElementById('editForm');
            const editHint = document.getElementById('editHint');
            
            editSupplies.classList.toggle('editing');
            editForm.classList.toggle('active');
            editHint.style.display = editForm.classList.contains('active') ? 'none' : 'block';
        }
        
        function cancelEdit() {
            const editSupplies = document.getElementById('editSupplies');
            const editForm = document.getElementById('editForm');
            const editHint = document.getElementById('editHint');
            
            editSupplies.classList.remove('editing');
            editForm.classList.remove('active');
            editHint.style.display = 'block';
        }
        
        // Spustit countdown timer
        const refreshInterval = setInterval(updateCountdown, 1000);
        
        // Kritické zásoby notifikace
        function checkCriticalSupplies() {
            const pizzaRemaining = <?= $pizza_remaining ?>;
            const pizzaTotal = <?= $pizza_total ?>;
            const burrataRemaining = <?= $burrata_remaining ?>;
            const burrataTotal = <?= $burrata_total ?>;
            
            if (pizzaRemaining <= pizzaTotal * 0.1 && pizzaRemaining > 0) {
                if (Notification.permission === "granted") {
                    new Notification("🚨 KRITICKÉ ZÁSOBY PIZZ!", {
                        body: `Zbývá jen ${pizzaRemaining} pizz! Připrav další těsto!`,
                        icon: "🍕"
                    });
                }
            }
            
            if (burrataRemaining <= burrataTotal * 0.2 && burrataRemaining > 0) {
                if (Notification.permission === "granted") {
                    new Notification("⚠️ POZOR - BURRATA!", {
                        body: `Zbývá jen ${burrataRemaining} porcí burraty!`,
                        icon: "🧀"
                    });
                }
            }
        }
        
        // Požádat o povolení notifikací
        if (Notification.permission === "default") {
            Notification.requestPermission();
        }
        
        // Kontrola každých 30 sekund
        setInterval(checkCriticalSupplies, 30000);
        
        // Zvukové upozornění při vysoké zátěži
        function checkAlerts() {
            const pizzyCount = parseInt(document.getElementById('pizzy-count').textContent);
            if (pizzyCount >= 15) {
                console.log('ALERT: Vysoká zátěž kuchyně!');
            }
        }
        
        setInterval(checkAlerts, 5000);
    </script>
</body>
</html>