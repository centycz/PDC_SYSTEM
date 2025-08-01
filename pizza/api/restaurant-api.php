<?php
define('AUTO_PRINT_ENABLED', true);
define('PRINTER_RPI_IP', '192.168.30.203');
define('PRINTER_RPI_PORT', 5000);
define('PRINTER_TIMEOUT', 5);

ob_start();
session_start();

// Získáme JSON data
$jsonBody = json_decode(file_get_contents('php://input'), true);
if (isset($jsonBody['employee_name'])) {
    $_SESSION['employee_name'] = mb_convert_encoding($jsonBody['employee_name'], 'UTF-8');
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

function getDb() {
    static $pdo = null;
    if ($pdo) return $pdo;
    try {
        $pdo = new PDO('mysql:host=127.0.0.1;dbname=pizza_orders;charset=utf8mb4', 'pizza_user', 'pizza');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("SET NAMES utf8mb4");
        $pdo->exec("SET CHARACTER SET utf8mb4");
    } catch (PDOException $e) {
        error_log('Connection failed: ' . $e->getMessage());
        jsend(false, null, 'Database connection error: ' . $e->getMessage());
        exit;
    }
    return $pdo;
}

function sendToPrinter($order_id, $pdo) {
    if (!AUTO_PRINT_ENABLED) {
        error_log("Auto-print disabled");
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT o.*, oi.item_name, oi.quantity, oi.unit_price, oi.note, oi.item_type,
                   ts.table_number, rt.table_code
            FROM orders o 
            JOIN order_items oi ON o.id = oi.order_id 
            JOIN table_sessions ts ON o.table_session_id = ts.id
            JOIN restaurant_tables rt ON ts.table_number = rt.table_number
            WHERE o.id = ? AND oi.item_type IN ('pizza', 'pasta', 'predkrm', 'dezert')
            ORDER BY oi.id
        ");
        $stmt->execute([$order_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($items)) {
            error_log("No kitchen items found for order $order_id");
            file_put_contents('/tmp/restaurant_debug.log', "No kitchen items found for order $order_id\n", FILE_APPEND);
            return true;
        }
        
        $order = $items[0];
        $print_data = [
            'order_id' => $order['id'],
            'table_code' => $order['table_code'] ?? $order['table_number'],
            'employee_name' => $order['employee_name'],
            'customer_name' => $order['customer_name'],
            'created_at' => $order['created_at'],
            'items' => []
        ];
        
        foreach ($items as $item) {
    $print_data['items'][] = [
        'item_type' => $item['item_type'],        // Přidej item_type
        'category' => $item['item_type'],         // A category
        'item_name' => $item['item_name'],
        'quantity' => $item['quantity'],
        'note' => $item['note'] ?: ''
    ];
}
     // DEBUG - vypis co posilam na tiskárnu
file_put_contents('/tmp/restaurant_debug.log', "Print data items: " . print_r($print_data['items'], true) . "\n", FILE_APPEND);
   
$rpi_url = 'http://' . PRINTER_RPI_IP . ':' . PRINTER_RPI_PORT . '/print-order';
$json_data = json_encode($print_data);
        
        file_put_contents('/tmp/restaurant_debug.log', "Sending to printer: $rpi_url\n", FILE_APPEND);
        file_put_contents('/tmp/restaurant_debug.log', "Print data: $json_data\n", FILE_APPEND);
        
        $response = false;
        $http_code = 0;
        $error = '';
        
        // ✅ ZKUSIT cURL POKUD JE DOSTUPNÝ
        if (function_exists('curl_init')) {
    file_put_contents('/tmp/restaurant_debug.log', "Using cURL for printing\n", FILE_APPEND);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $rpi_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);  // ✅ TADY JE $json_data OK
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'User-Agent: Restaurant-API/1.0'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, PRINTER_TIMEOUT);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  // ✅ PŘIDEJTE PRO NGROK HTTPS
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
            
       } else {
    // ✅ FALLBACK: POUŽÍT file_get_contents s SSL kontextem
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $json_data,
            'timeout' => PRINTER_TIMEOUT,
            'ignore_errors' => true
        ],
        'ssl' => [                           // ✅ PŘIDÁNO PRO HTTPS
            'verify_peer' => false,          // ✅ Vypnout SSL ověření pro ngrok
            'verify_peer_name' => false,     // ✅ Vypnout ověření názvu
            'allow_self_signed' => true      // ✅ Povolit self-signed certifikáty
        ]
    ]);
    
    $response = @file_get_contents($rpi_url, false, $context);
            
            if ($response !== false) {
                // Parsovat HTTP hlavičky pro získání status kódu
                if (isset($http_response_header) && !empty($http_response_header)) {
                    $status_line = $http_response_header[0];
                    if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $status_line, $matches)) {
                        $http_code = intval($matches[1]);
                    } else {
                        $http_code = 200; // Předpokládáme úspěch pokud nelze parsovat
                    }
                } else {
                    $http_code = 200; // Předpokládáme úspěch
                }
            } else {
                $http_code = 500;
                $error = 'Connection failed using file_get_contents';
            }
        }
        
        file_put_contents('/tmp/restaurant_debug.log', "Printer response: HTTP $http_code, Error: '$error', Response: '$response'\n", FILE_APPEND);
        
        if ($error) {
            error_log("Printer error: $error");
            return false;
        }
        
        if ($http_code === 200) {
            $stmt = $pdo->prepare("UPDATE orders SET print_status = 'printed', printed_at = NOW() WHERE id = ?");
            $stmt->execute([$order_id]);
            error_log("Order $order_id sent to printer successfully");
            file_put_contents('/tmp/restaurant_debug.log', "✅ Order $order_id sent to printer successfully\n", FILE_APPEND);
            return true;
        } else {
            error_log("Printer HTTP error: $http_code, Response: $response");
            file_put_contents('/tmp/restaurant_debug.log', "❌ Printer HTTP error: $http_code\n", FILE_APPEND);
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Printer error: " . $e->getMessage());
        file_put_contents('/tmp/restaurant_debug.log', "❌ Printer exception: " . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}

function jsend($success, $data = null, $error = '') {
    $response = [
        'success' => $success,
        'data' => $data,
        'error' => $success ? null : $error
    ];
    
    echo json_encode($response, 
        JSON_UNESCAPED_UNICODE | 
        JSON_UNESCAPED_SLASHES | 
        JSON_PRETTY_PRINT
    );
    exit;
}

function getJsonBody() {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true);
}

$action = $_GET['action'] ?? '';

try {
    $pdo = getDb();
    $pdo->exec("SET NAMES 'utf8mb4'");
    $today = date('Y-m-d');

    // Check if daily statistics exist for today
    $q = $pdo->prepare("SELECT COUNT(*) FROM daily_stats WHERE date = ?");
    $q->execute([$today]);
    $count = $q->fetchColumn();

    if ($count == 0) {
        $pdo->prepare("INSERT INTO daily_stats (date, total_orders, total_pizzas, total_drinks, total_revenue, avg_preparation_time, burnt_items) VALUES (?, 0, 0, 0, 0, 0, 0)")
            ->execute([$today]);
    }

    // ZÁKLADNÍ NAČÍTÁNÍ DAT
    if ($action === 'tables') {
        try {
            $q = $pdo->query("
                SELECT 
                    rt.table_number,
                    rt.table_code,
                    rt.status,
                    rt.notes,
                    tl.name as location_name,
                    tl.id as location_id,
                    tc.name as category_name,
                    tc.id as category_id,
                    tl.display_order as location_order,
                    tc.display_order as category_order
                FROM restaurant_tables rt
                LEFT JOIN table_locations tl ON rt.location_id = tl.id
                LEFT JOIN table_categories tc ON rt.category_id = tc.id
                ORDER BY COALESCE(tl.display_order,999), COALESCE(tc.display_order,999), rt.table_number
            ");
            
            if (!$q) {
                throw new Exception("Chyba při načítání stolů");
            }
            
            $tables = $q->fetchAll(PDO::FETCH_ASSOC);
            jsend(true, ['tables' => $tables]);
        } catch (Exception $e) {
            jsend(false, null, 'Chyba při načítání stolů: ' . $e->getMessage());
        }
    }

    if ($action === 'drink-categories') {
        $sql = "SELECT DISTINCT category FROM drink_types WHERE is_active = 1 ORDER BY display_order, category";
        $q = $pdo->query($sql);
        $categories = $q->fetchAll(PDO::FETCH_COLUMN);
        jsend(true, ['categories' => $categories]);
    }

    if ($action === 'pizza-categories') {
        $sql = "SELECT DISTINCT category FROM pizza_types WHERE is_active = 1 ORDER BY display_order, category";
        $q = $pdo->query($sql);
        $categories = $q->fetchAll(PDO::FETCH_COLUMN);
        jsend(true, ['categories' => $categories]);
    }

    if ($action === 'pizza-menu') {
        $q = $pdo->query("SELECT * FROM pizza_types WHERE is_active=1 ORDER BY display_order, name");
        $pizzas = $q->fetchAll(PDO::FETCH_ASSOC);
        jsend(true, ['pizzas' => $pizzas]);
    }

    if ($action === 'drink-menu') {
        $q = $pdo->query("SELECT * FROM drink_types WHERE is_active=1 ORDER BY display_order, name");
        $drinks = $q->fetchAll(PDO::FETCH_ASSOC);
        jsend(true, ['drinks' => $drinks]);
    }

    if ($action === 'pizza-menu-admin') {
        $sql = "SELECT * FROM pizza_types ORDER BY category, name";
        $q = $pdo->query($sql);
        $pizzas = $q->fetchAll(PDO::FETCH_ASSOC);
        jsend(true, ['pizzas' => $pizzas]);
    }

    if ($action === 'drink-menu-admin') {
        $sql = "SELECT * FROM drink_types ORDER BY category, name";
        $q = $pdo->query($sql);
        $drinks = $q->fetchAll(PDO::FETCH_ASSOC);
        jsend(true, ['drinks' => $drinks]);
    }

    if ($action === 'get-employees') {
        try {
            $q = $pdo->query("SELECT id, name FROM employees");
            $employees = $q->fetchAll(PDO::FETCH_ASSOC);
            jsend(true, ['employees' => $employees]);
        } catch (Exception $e) {
            error_log('Get employees failed: ' . $e->getMessage());
            jsend(false, null, 'Načtení zaměstnanců selhalo: ' . $e->getMessage());
        }
    }
        // TABLE ORDERS
    if ($action === 'table-orders') {
        $table_number = intval($_GET['table_number'] ?? 0);
        $q = $pdo->prepare("
            SELECT ts.*, rt.table_code 
            FROM table_sessions ts
            JOIN restaurant_tables rt ON ts.table_number = rt.table_number
            WHERE ts.table_number=? AND ts.is_active=1 
            ORDER BY ts.id DESC LIMIT 1
        ");
        $q->execute([$table_number]);
        $session = $q->fetch();
        if (!$session) jsend(true, ['orders'=>[]]);
        $session_id = $session['id'];
        
        $q2 = $pdo->prepare("
            SELECT o.*, rt.table_code
            FROM orders o
            JOIN table_sessions ts ON o.table_session_id = ts.id
            JOIN restaurant_tables rt ON ts.table_number = rt.table_number
            WHERE o.table_session_id=? 
            ORDER BY o.created_at DESC
        ");
        $q2->execute([$session_id]);
        $orders = $q2->fetchAll(PDO::FETCH_ASSOC);
        foreach ($orders as &$o) {
            $q3 = $pdo->prepare("SELECT * FROM order_items WHERE order_id=?");
            $q3->execute([$o['id']]);
            $o['items'] = $q3->fetchAll(PDO::FETCH_ASSOC);
        }
        jsend(true, ['orders' => $orders]);
    }

    // ADD ORDER - HLAVNÍ FUNKCE
    if ($action === 'add-order') {
    file_put_contents('/tmp/restaurant_debug.log', "\n=== ADD-ORDER DEBUG START ===\n", FILE_APPEND);
    file_put_contents('/tmp/restaurant_debug.log', "Current time: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    file_put_contents('/tmp/restaurant_debug.log', "Raw input: " . file_get_contents('php://input') . "\n", FILE_APPEND);
    
    try {
        $body = getJsonBody();
        file_put_contents('/tmp/restaurant_debug.log', "Parsed JSON: " . json_encode($body) . "\n", FILE_APPEND);
            
            $table_number = intval($body['table'] ?? 0);
            $items = $body['items'] ?? [];
            $customer_name = $body['customer_name'] ?? '';
            $employee_name = $body['employee_name'] ?? '';
            
            file_put_contents('/tmp/restaurant_debug.log', 
                "Parsed data: table=$table_number, items=" . count($items) . ", customer=$customer_name, employee=$employee_name\n", 
                FILE_APPEND
            );
            
            if ($table_number <= 0) {
                file_put_contents('/tmp/restaurant_debug.log', "ERROR: Invalid table number\n", FILE_APPEND);
                jsend(false, null, "Není vybrán stůl!");
                exit;
            }
            
            if (!is_array($items) || count($items) < 1) {
                file_put_contents('/tmp/restaurant_debug.log', "ERROR: Invalid items\n", FILE_APPEND);
                jsend(false, null, "Chybí položky objednávky!");
                exit;
            }

            file_put_contents('/tmp/restaurant_debug.log', "Starting database transaction\n", FILE_APPEND);
            $pdo->beginTransaction();
            
            // Check/create table session
            $s = $pdo->prepare("SELECT id FROM table_sessions WHERE table_number=? AND is_active=1 LIMIT 1");
            $s->execute([$table_number]);
            $row = $s->fetch();
            
            if ($row) {
                $table_session_id = $row['id'];
                file_put_contents('/tmp/restaurant_debug.log', "Found existing session: $table_session_id\n", FILE_APPEND);
            } else {
                $pdo->prepare("INSERT INTO table_sessions (table_number, start_time, is_active) VALUES (?, NOW(), 1)")
                    ->execute([$table_number]);
                $table_session_id = $pdo->lastInsertId();
                file_put_contents('/tmp/restaurant_debug.log', "Created new session: $table_session_id\n", FILE_APPEND);
                
                $pdo->prepare("UPDATE restaurant_tables SET status='occupied', session_start=NOW() WHERE table_number=?")
                    ->execute([$table_number]);
            }

            // Use employee_name from JSON or session
            $final_employee_name = $employee_name ?: ($_SESSION['employee_name'] ?? '');
            file_put_contents('/tmp/restaurant_debug.log', "Final employee name: $final_employee_name\n", FILE_APPEND);

            // Create order
            $stmt = $pdo->prepare("INSERT INTO orders (table_session_id, created_at, status, order_type, customer_name, employee_name) VALUES (?, NOW(), 'pending', 'other', ?, ?)");
            $result = $stmt->execute([$table_session_id, $customer_name, $final_employee_name]);
            
            if (!$result) {
                file_put_contents('/tmp/restaurant_debug.log', "ERROR: Failed to insert order\n", FILE_APPEND);
                throw new Exception("Failed to create order");
            }
            
            $order_id = $pdo->lastInsertId();
            file_put_contents('/tmp/restaurant_debug.log', "Created order ID: $order_id\n", FILE_APPEND);

            // Update daily stats
            $stmt = $pdo->prepare("UPDATE daily_stats SET total_orders = total_orders + 1 WHERE date = ?");
            $stmt->execute([$today]);

            // Insert order items
            $hasPizza = false;
            foreach ($items as $index => $item) {
                file_put_contents('/tmp/restaurant_debug.log', "Processing item $index: " . print_r($item, true) . "\n", FILE_APPEND);
                
                $itemType = $item['type'] ?? 'pizza';
                if ($itemType === 'pizza') {
                    $hasPizza = true;
                }
                
                $result = $pdo->prepare("INSERT INTO order_items (order_id, item_type, item_name, quantity, unit_price, note, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'pending')")
                    ->execute([
                        $order_id,
                        $itemType,
                        $item['name'] ?? '',
                        intval($item['quantity'] ?? 1),
                        floatval($item['unit_price'] ?? 0),
                        $item['note'] ?? ''
                    ]);
                    
                if (!$result) {
                    file_put_contents('/tmp/restaurant_debug.log', "ERROR: Failed to insert item $index\n", FILE_APPEND);
                    throw new Exception("Failed to insert order item");
                }
            }

            // ✅ NOVÁ LOGIKA: Automaticky povolit samostatné pasta/dezerty
            if (!$hasPizza) {
                // Pokud objednávka nemá pizzu, automaticky povolit pasta a dezerty
                $stmt = $pdo->prepare("
                    UPDATE order_items 
                    SET status = 'preparing',
                        note = CONCAT(COALESCE(note, ''), ' - Automaticky povoleno (bez pizzy)')
                    WHERE order_id = ? 
                    AND item_type IN ('pasta', 'dezert')
                    AND status = 'pending'
                ");
                $stmt->execute([$order_id]);
                
                $affectedItems = $stmt->rowCount();
                if ($affectedItems > 0) {
                    error_log("✅ AUTO-RELEASE: Automatically released $affectedItems items for order $order_id (no pizza)");
                    file_put_contents('/tmp/restaurant_debug.log', "✅ AUTO-RELEASE: Released $affectedItems items (no pizza)\n", FILE_APPEND);
                }
            }

            $pdo->commit();
            file_put_contents('/tmp/restaurant_debug.log', "Transaction committed successfully\n", FILE_APPEND);
            
            // Try to send to printer
            $print_success = false;
            try {
                $print_success = sendToPrinter($order_id, $pdo);
                file_put_contents('/tmp/restaurant_debug.log', "Print result: " . ($print_success ? 'SUCCESS' : 'FAILED') . "\n", FILE_APPEND);
            } catch (Exception $e) {
                file_put_contents('/tmp/restaurant_debug.log', "Print error: " . $e->getMessage() . "\n", FILE_APPEND);
            }
            
            // Return success response
            $response = [
                'success' => true,
                'data' => [
                    'order_id' => $order_id,
                    'printed' => $print_success,
                    'print_status' => $print_success ? 'sent' : 'failed'
                ],
                'error' => null
            ];
            
            file_put_contents('/tmp/restaurant_debug.log', "Sending response: " . json_encode($response) . "\n", FILE_APPEND);
            
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
            
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            $error = "Chyba při vytváření objednávky: " . $e->getMessage();
            file_put_contents('/tmp/restaurant_debug.log', "EXCEPTION: $error\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
            
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'data' => null,
                'error' => $error
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // PROXY PRINT - OPRAVENÁ FUNKCE
if ($action === 'proxy-print') {
    $body = getJsonBody();
    
   $rpi_url = 'http://' . PRINTER_RPI_IP . ':' . PRINTER_RPI_PORT . '/print-order';
    $json_data = json_encode($body);
    
    file_put_contents('/tmp/restaurant_debug.log', "PROXY-PRINT: Sending to $rpi_url\n", FILE_APPEND);
    
    $response = false;
    $http_code = 0;
    $error = '';
    
    // ✅ ZKUSIT cURL POKUD JE DOSTUPNÝ
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $rpi_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'User-Agent: Restaurant-API/1.0'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, PRINTER_TIMEOUT);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
    } else {
        // ✅ FALLBACK: POUŽÍT file_get_contents
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $json_data,
                'timeout' => PRINTER_TIMEOUT,
                'ignore_errors' => true
            ]
        ]);
        
        $response = @file_get_contents($rpi_url, false, $context);
        
        if ($response !== false) {
            if (isset($http_response_header) && !empty($http_response_header)) {
                $status_line = $http_response_header[0];
                if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $status_line, $matches)) {
                    $http_code = intval($matches[1]);
                } else {
                    $http_code = 200;
                }
            } else {
                $http_code = 200;
            }
        } else {
            $http_code = 500;
            $error = 'Connection failed using file_get_contents';
        }
    }
    
    file_put_contents('/tmp/restaurant_debug.log', "PROXY-PRINT result: HTTP $http_code, Error: '$error'\n", FILE_APPEND);
    
    if ($error) {
        jsend(false, null, "Printer connection error: $error");
        exit;
    }
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        jsend(true, $result);
    } else {
        jsend(false, null, "Printer HTTP error: $http_code");
    }
    exit;
}

    // ADD TABLE
    if ($action === 'add-table') {
        $body = getJsonBody();
        $table_number = intval($body['table_number'] ?? 0);
        $table_code = $body['table_code'] ?? '';
        $location_name = $body['location_name'] ?? 'Auto';
        $category_name = $body['category_name'] ?? 'Auto';

        if (!$table_number || !$table_code) jsend(false, null, "Chybí číslo stolu nebo kód stolu!");

        try {
            // Najdi ID lokace
            $stmt = $pdo->prepare("SELECT id FROM table_locations WHERE name = ?");
            $stmt->execute([$location_name]);
            $location = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$location) {
                $pdo->prepare("INSERT INTO table_locations (name) VALUES (?)")->execute([$location_name]);
                $location_id = $pdo->lastInsertId();
            } else {
                $location_id = $location['id'];
            }

            // Najdi ID kategorie
            $stmt = $pdo->prepare("SELECT id FROM table_categories WHERE name = ?");
            $stmt->execute([$category_name]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$category) {
                $pdo->prepare("INSERT INTO table_categories (name) VALUES (?)")->execute([$category_name]);
                $category_id = $pdo->lastInsertId();
            } else {
                $category_id = $category['id'];
            }

            // Vlož stůl
            $stmt = $pdo->prepare("INSERT INTO restaurant_tables (table_number, table_code, status, location_id, category_id) VALUES (?, ?, 'free', ?, ?)");
            $stmt->execute([$table_number, $table_code, $location_id, $category_id]);
            jsend(true);

        } catch (Exception $e) {
            error_log('Add table failed: ' . $e->getMessage());
            jsend(false, null, 'Přidání stolu selhalo: ' . $e->getMessage());
        }
    }

    // MARK TABLE AS CLEANED
    if ($action === 'mark-table-as-cleaned') {
        $body = getJsonBody();
        $table_number = intval($body['table_number'] ?? 0);
        if ($table_number <= 0) jsend(false, null, "Chybí číslo stolu!");
        try {
            $stmt = $pdo->prepare("UPDATE restaurant_tables SET status='free' WHERE table_number=?");
            $stmt->execute([$table_number]);

            if ($stmt->rowCount() > 0) {
                jsend(true);
            } else {
                jsend(false, null, "Table not found or already free.");
            }
        } catch (Exception $e) {
            jsend(false, null, 'Mark as cleaned failed: ' . $e->getMessage());
        }
    }

    // UPDATE TABLE NOTE
    if ($action === 'update-table-note') {
        $table_number = intval($_GET['table_number'] ?? 0);
        $order_id = intval($_GET['order_id'] ?? 0);

        if (!$table_number || !$order_id) {
            jsend(false, null, "Chybí číslo stolu nebo ID objednávky!");
        }

        try {
            $pdo->beginTransaction();

            $currentTime = date('H:i:s');
            $printNote = " - Tisk: " . $currentTime;
            
            $stmt = $pdo->prepare("
                UPDATE restaurant_tables 
                SET notes = IF(notes IS NULL OR notes = '', ?, CONCAT(notes, ?))
                WHERE table_number = ?
            ");
            $stmt->execute([$printNote, $printNote, $table_number]);

            $stmt = $pdo->prepare("
                UPDATE orders 
                SET printed_at = NOW()
                WHERE id = ?
            ");
            $result = $stmt->execute([$order_id]);

            $check = $pdo->prepare("SELECT printed_at, (SELECT notes FROM restaurant_tables WHERE table_number = ?) as notes FROM orders WHERE id = ?");
            $check->execute([$table_number, $order_id]);
            $result = $check->fetch(PDO::FETCH_ASSOC);

            $pdo->commit();
            jsend(true, [
                'printed_at' => $result['printed_at'],
                'notes' => $result['notes'],
                'table_number' => $table_number,
                'order_id' => $order_id
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            jsend(false, null, 'Aktualizace stavu tisku selhala: ' . $e->getMessage());
        }
    }


    // SESSION BILL
    if ($action === 'session-bill') {
        $table_number = intval($_GET['table_number'] ?? 0);
        $q = $pdo->prepare("SELECT id FROM table_sessions WHERE table_number=? AND is_active=1 ORDER BY id DESC LIMIT 1");
        $q->execute([$table_number]);
        $session = $q->fetch();
        if (!$session) jsend(true, ['items'=>[]]);
        $session_id = $session['id'];
        $q2 = $pdo->prepare("
            SELECT oi.* FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            WHERE o.table_session_id=? AND oi.status NOT IN ('cancelled')
            ORDER BY oi.item_name, oi.id
        ");
        $q2->execute([$session_id]);
        $items = $q2->fetchAll(PDO::FETCH_ASSOC);
        jsend(true, ['items' => $items]);
    }

    // GET ORDER DETAILS
    if ($action === 'get-order-details') {
        $order_id = intval($_GET['id'] ?? 0);
        if (!$order_id) jsend(false, null, "Chybí ID objednávky!");

        try {
            $orderSql = "
                SELECT o.*, ts.table_number, rt.table_code 
                FROM orders o 
                LEFT JOIN table_sessions ts ON o.table_session_id = ts.id 
                LEFT JOIN restaurant_tables rt ON ts.table_number = rt.table_number 
                WHERE o.id = ?
            ";
            $stmt = $pdo->prepare($orderSql);
            $stmt->execute([$order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                jsend(false, null, "Objednávka nenalezena!");
                return;
            }

            $itemsSql = "SELECT * FROM order_items WHERE order_id = ?";
            $stmt = $pdo->prepare($itemsSql);
            $stmt->execute([$order_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $order['items'] = $items;
            
            jsend(true, $order);
        } catch (Exception $e) {
            jsend(false, null, "Chyba při načítání detailů objednávky");
        }
    }
        // KITCHEN ITEMS
    if ($action === 'kitchen-items') {
        $q = $pdo->query("
            SELECT 
                oi.*,
                o.table_session_id,
                o.created_at,
                o.printed_at,
                o.id as order_id,
                ts.table_number,
                rt.table_code,
                rt.notes,
                rt.status as table_status
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            JOIN table_sessions ts ON o.table_session_id = ts.id
            JOIN restaurant_tables rt ON ts.table_number = rt.table_number
            WHERE oi.item_type = 'pizza'
            AND oi.status IN ('pending','preparing')
            ORDER BY 
                CASE WHEN o.printed_at IS NULL THEN 0 ELSE 1 END,
                o.created_at DESC
        ");
        
        if (!$q) {
            jsend(false, null, "Chyba při načítání položek pro kuchyň");
            return;
        }

        $items = $q->fetchAll(PDO::FETCH_ASSOC);
        jsend(true, ['items' => $items]);
    }

      // ALL KITCHEN ITEMS
    if ($action === 'all-kitchen-items') {
        $q = $pdo->query("
            SELECT 
                oi.*,
                o.table_session_id,
                o.created_at,
                o.printed_at,
                o.id as order_id,
                ts.table_number,
                rt.table_code,
                rt.notes,
                rt.status as table_status
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            JOIN table_sessions ts ON o.table_session_id = ts.id
            JOIN restaurant_tables rt ON ts.table_number = rt.table_number
            WHERE oi.item_type IN ('pizza', 'pasta', 'predkrm', 'dezert', 'drink')
            AND oi.status IN ('pending','preparing')
            ORDER BY 
                CASE WHEN o.printed_at IS NULL THEN 0 ELSE 1 END,
                o.created_at DESC
        ");
        
        if (!$q) {
            jsend(false, null, "Chyba při načítání všech položek pro kuchyň");
            return;
        }

        $items = $q->fetchAll(PDO::FETCH_ASSOC);
        jsend(true, ['items' => $items]);
    }
    // RELEASE DESSERT WITH TIMING - DEZERTY S ČASOVÁNÍM
    if ($action === 'release-dessert-with-timing') {
        $body = getJsonBody();
        $table_number = intval($body['table_number'] ?? 0);
        $delay_minutes = intval($body['delay_minutes'] ?? 0);
        $serve_note = $body['serve_note'] ?? '';
        
        if (!$table_number) {
            jsend(false, null, 'Chybí číslo stolu!');
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            
            // Najdi aktivní session stolu
            $stmt = $pdo->prepare("SELECT id FROM table_sessions WHERE table_number = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$table_number]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                $pdo->rollBack();
                jsend(false, null, 'Stůl nemá aktivní session!');
                exit;
            }
            
            $sessionId = $session['id'];
            
            // Povolit dezerty s časovým označením
            $stmt = $pdo->prepare("
                UPDATE order_items oi
                JOIN orders o ON oi.order_id = o.id
                SET oi.status = 'preparing',
                    oi.note = CONCAT(COALESCE(oi.note, ''), ' - ', ?)
                WHERE o.table_session_id = ?
                AND oi.item_type = 'dezert'
                AND oi.status = 'pending'
            ");
            $stmt->execute([$serve_note, $sessionId]);
            $affected = $stmt->rowCount();
            
            $pdo->commit();
            
            jsend(true, [
                'message' => "Povoleno $affected dezertů pro stůl $table_number",
                'table_number' => $table_number,
                'delay_minutes' => $delay_minutes,
                'serve_note' => $serve_note,
                'affected_items' => $affected
            ]);
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            jsend(false, null, 'Chyba při povolování dezertů: ' . $e->getMessage());
        }
    }
     
       // SPRÁVA PIZZ
    if ($action === 'add-pizza') {
        $body = getJsonBody();
        $type = $body['type'] ?? '';
        $name = $body['name'] ?? '';
        $description = $body['description'] ?? '';
        $price = $body['price'] ?? 0;
        $cost_price = $body['cost_price'] ?? 0;
        $is_active = $body['is_active'] ?? 0;
        $category = $body['category'] ?? 'pizza';

        if (!$type || !$name) jsend(false, null, 'Chybí typ nebo název položky!');

        try {
            $stmt = $pdo->prepare("INSERT INTO pizza_types (type, name, description, price, cost_price, is_active, category) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$type, $name, $description, $price, $cost_price, $is_active, $category]);
            jsend(true);
        } catch (Exception $e) {
            jsend(false, null, 'Přidání položky selhalo: ' . $e->getMessage());
        }
    }

    if ($action === 'edit-pizza') {
        $type = $_GET['type'] ?? '';
        $body = getJsonBody();
        $name = $body['name'] ?? '';
        $description = $body['description'] ?? '';
        $price = $body['price'] ?? 0;
        $cost_price = $body['cost_price'] ?? 0;
        $is_active = $body['is_active'] ?? 0;
        $category = $body['category'] ?? 'pizza';

        if (!$type || !$name) jsend(false, null, 'Chybí typ nebo název položky!');

        try {
            $stmt = $pdo->prepare("UPDATE pizza_types SET name=?, description=?, price=?, cost_price=?, is_active=?, category=? WHERE type=?");
            $stmt->execute([$name, $description, $price, $cost_price, $is_active, $category, $type]);
            jsend(true);
        } catch (Exception $e) {
            jsend(false, null, 'Úprava položky selhala: ' . $e->getMessage());
        }
    }

    if ($action === 'toggle-pizza') {
        $type = $_GET['type'] ?? '';
        if (!$type) jsend(false, null, 'Chybí typ pizzy!');

        try {
            $stmt = $pdo->prepare("SELECT is_active FROM pizza_types WHERE type=?");
            $stmt->execute([$type]);
            $pizza = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pizza) {
                jsend(false, null, 'Pizza nenalezena!');
                return;
            }

            $new_status = $pizza['is_active'] == 1 ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE pizza_types SET is_active=? WHERE type=?");
            $stmt->execute([$new_status, $type]);
            jsend(true);
        } catch (Exception $e) {
            jsend(false, null, 'Změna stavu pizzy selhala: ' . $e->getMessage());
        }
    }

    if ($action === 'delete-pizza') {
        $type = $_GET['type'] ?? '';
        if (!$type) jsend(false, null, 'Chybí typ pizzy!');

        try {
            $stmt = $pdo->prepare("DELETE FROM pizza_types WHERE type=?");
            $stmt->execute([$type]);
            jsend(true);
        } catch (Exception $e) {
            jsend(false, null, 'Smazání pizzy selhalo: ' . $e->getMessage());
        }
    }
        // PASTA KITCHEN ITEMS - NOVÝ ENDPOINT
    if ($action === 'pasta-kitchen-items') {
        $q = $pdo->query("
            SELECT 
                oi.*,
                o.table_session_id,
                o.created_at,
                o.printed_at,
                o.id as order_id,
                ts.table_number,
                rt.table_code,
                rt.notes,
                rt.status as table_status,
                -- Přidáme pasta_status pro synchronizaci
                CASE 
                    WHEN oi.item_type = 'predkrm' THEN 'ready_to_cook'
                    WHEN oi.item_type = 'pasta' AND oi.status = 'pending' THEN 'waiting_for_release'
                    WHEN oi.item_type = 'pasta' AND oi.status = 'preparing' THEN 'ready_for_pasta'
                    WHEN oi.item_type = 'dezert' AND oi.status = 'pending' THEN 'waiting_for_main'
                    WHEN oi.item_type = 'dezert' AND oi.status = 'preparing' THEN 'dessert_time'
                    ELSE 'unknown'
                END as pasta_status
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            JOIN table_sessions ts ON o.table_session_id = ts.id
            JOIN restaurant_tables rt ON ts.table_number = rt.table_number
            WHERE oi.item_type IN ('pasta', 'predkrm', 'dezert')
            AND oi.status IN ('pending','preparing')
            ORDER BY 
                CASE 
                    WHEN oi.item_type = 'predkrm' THEN 1
                    WHEN oi.item_type = 'pasta' AND oi.status = 'preparing' THEN 2
                    WHEN oi.item_type = 'pasta' AND oi.status = 'pending' THEN 3
                    WHEN oi.item_type = 'dezert' THEN 4
                    ELSE 5
                END,
                CASE WHEN o.printed_at IS NULL THEN 0 ELSE 1 END,
                o.created_at DESC
        ");
        
        if (!$q) {
            jsend(false, null, "Chyba při načítání položek pro pasta kuchyň");
            return;
        }

        $items = $q->fetchAll(PDO::FETCH_ASSOC);
        jsend(true, ['items' => $items]);
    }

    // RELEASE PASTA ITEMS - SYNCHRONIZACE
    if ($action === 'release-pasta-items') {
        $body = getJsonBody();
        $table_number = intval($body['table_number'] ?? 0);
        $release_type = $body['release_type'] ?? 'pasta'; // 'pasta' nebo 'dessert'
        
        if (!$table_number) {
            jsend(false, null, 'Chybí číslo stolu!');
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            
            // Najdi aktivní session stolu
            $stmt = $pdo->prepare("SELECT id FROM table_sessions WHERE table_number = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$table_number]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                $pdo->rollBack();
                jsend(false, null, 'Stůl nemá aktivní session!');
                exit;
            }
            
            $sessionId = $session['id'];
            
            if ($release_type === 'pasta') {
                // Povolit všechny pending pasta položky pro tento stůl
                $stmt = $pdo->prepare("
                    UPDATE order_items oi
                    JOIN orders o ON oi.order_id = o.id
                    SET oi.status = 'preparing',
                        oi.note = CONCAT(COALESCE(oi.note, ''), ' - Pizza hotová, pasta povolena')
                    WHERE o.table_session_id = ?
                    AND oi.item_type = 'pasta'
                    AND oi.status = 'pending'
                ");
                $stmt->execute([$sessionId]);
                $affected = $stmt->rowCount();
                
                $message = "Povoleno $affected past pro stůl $table_number";
                
            } elseif ($release_type === 'dessert') {
                // Povolit dezerty po hlavním chodu
                $stmt = $pdo->prepare("
                    UPDATE order_items oi
                    JOIN orders o ON oi.order_id = o.id
                    SET oi.status = 'preparing',
                        oi.note = CONCAT(COALESCE(oi.note, ''), ' - Hlavní chod hotový, dezert povolen')
                    WHERE o.table_session_id = ?
                    AND oi.item_type = 'dezert'
                    AND oi.status = 'pending'
                ");
                $stmt->execute([$sessionId]);
                $affected = $stmt->rowCount();
                
                $message = "Povoleno $affected dezertů pro stůl $table_number";
            } else {
                $pdo->rollBack();
                jsend(false, null, 'Neplatný typ povolení!');
                exit;
            }
            
            $pdo->commit();
            
            jsend(true, [
                'message' => $message,
                'table_number' => $table_number,
                'release_type' => $release_type,
                'affected_items' => $affected
            ]);
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            jsend(false, null, 'Chyba při povolování položek: ' . $e->getMessage());
        }
    }

    // AUTO RELEASE - AUTOMATICKÁ SYNCHRONIZACE
    if ($action === 'auto-release-check') {
        try {
            $pdo->beginTransaction();
            $releasedItems = 0;
            
            // Najdi stoly kde jsou hotové pizzy a čekají pasty
            $stmt = $pdo->query("
                SELECT DISTINCT o.table_session_id, ts.table_number
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                JOIN table_sessions ts ON o.table_session_id = ts.id
                WHERE oi.item_type = 'pizza' 
                AND oi.status = 'ready'
                AND EXISTS (
                    SELECT 1 FROM order_items oi2 
                    JOIN orders o2 ON oi2.order_id = o2.id 
                    WHERE o2.table_session_id = o.table_session_id 
                    AND oi2.item_type = 'pasta' 
                    AND oi2.status = 'pending'
                )
            ");
            
            $readyTables = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($readyTables as $table) {
                // Povolit pasty pro tento stůl
                $stmt = $pdo->prepare("
                    UPDATE order_items oi
                    JOIN orders o ON oi.order_id = o.id
                    SET oi.status = 'preparing',
                        oi.note = CONCAT(COALESCE(oi.note, ''), ' - AUTO: Pizza hotová')
                    WHERE o.table_session_id = ?
                    AND oi.item_type = 'pasta'
                    AND oi.status = 'pending'
                ");
                $stmt->execute([$table['table_session_id']]);
                $releasedItems += $stmt->rowCount();
            }
            
            // Najdi stoly kde jsou hotové hlavní chody a čekají dezerty
            $stmt = $pdo->query("
                SELECT DISTINCT o.table_session_id, ts.table_number
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                JOIN table_sessions ts ON o.table_session_id = ts.id
                WHERE oi.item_type IN ('pizza', 'pasta') 
                AND oi.status = 'ready'
                AND NOT EXISTS (
                    SELECT 1 FROM order_items oi2 
                    JOIN orders o2 ON oi2.order_id = o2.id 
                    WHERE o2.table_session_id = o.table_session_id 
                    AND oi2.item_type IN ('pizza', 'pasta')
                    AND oi2.status IN ('pending', 'preparing')
                )
                AND EXISTS (
                    SELECT 1 FROM order_items oi3 
                    JOIN orders o3 ON oi3.order_id = o3.id 
                    WHERE o3.table_session_id = o.table_session_id 
                    AND oi3.item_type = 'dezert' 
                    AND oi3.status = 'pending'
                )
            ");
            
            $dessertReadyTables = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($dessertReadyTables as $table) {
                // Povolit dezerty pro tento stůl
                $stmt = $pdo->prepare("
                    UPDATE order_items oi
                    JOIN orders o ON oi.order_id = o.id
                    SET oi.status = 'preparing',
                        oi.note = CONCAT(COALESCE(oi.note, ''), ' - AUTO: Hlavní chod hotový')
                    WHERE o.table_session_id = ?
                    AND oi.item_type = 'dezert'
                    AND oi.status = 'pending'
                ");
                $stmt->execute([$table['table_session_id']]);
                $releasedItems += $stmt->rowCount();
            }
            
            $pdo->commit();
            
            jsend(true, [
                'released_items' => $releasedItems,
                'pasta_tables' => count($readyTables),
                'dessert_tables' => count($dessertReadyTables)
            ]);
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            jsend(false, null, 'Chyba při automatické synchronizaci: ' . $e->getMessage());
        }
    }
    
    
if ($action === 'cancel-items') {
    $body = getJsonBody();
    $itemsToCancel = $body['items_to_cancel'] ?? [];
    $totalCancelQuantity = intval($body['total_cancel_quantity'] ?? 0);
    $reason = $body['reason'] ?? '';
    $itemName = $body['item_name'] ?? '';
    $unitPrice = floatval($body['unit_price'] ?? 0);
    
    if (!is_array($itemsToCancel) || empty($itemsToCancel)) {
        jsend(false, null, 'Chybí položky k zrušení!');
        exit;
    }
    
    if ($totalCancelQuantity < 1) {
        jsend(false, null, 'Neplatné množství ke zrušení!');
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        $processedItems = [];
        $affectedTableSessions = []; // ✅ NOVÉ - sledujeme ovlivněné sessions
        
        foreach ($itemsToCancel as $itemData) {
            $itemId = intval($itemData['id']);
            $cancelQty = intval($itemData['cancel_qty']);
            $currentQty = intval($itemData['current_qty']);
            
            if ($cancelQty <= 0 || $cancelQty > $currentQty) {
                continue;
            }
            
            // Zkontroluj status položky a získej info o session
            $stmt = $pdo->prepare("
                SELECT oi.status, oi.item_name, oi.quantity, oi.order_id,
                       o.table_session_id, ts.table_number
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id  
                JOIN table_sessions ts ON o.table_session_id = ts.id
                WHERE oi.id = ?
            ");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$item) {
                continue;
            }
            
            if ($item['status'] === 'paid') {
                $pdo->rollBack();
                jsend(false, null, "Položka '{$item['item_name']}' je už zaplacená a nelze ji zrušit!");
                exit;
            }
            
            // ✅ NOVÉ - sledujeme table session
            $affectedTableSessions[$item['table_session_id']] = $item['table_number'];
            
            if ($cancelQty < $currentQty) {
                // Rozdělí položku - zmenší původní a vytvoří novou zrušenou
                $remainingQty = $currentQty - $cancelQty;
                
                // Aktualizuj původní položku
                $stmt = $pdo->prepare("UPDATE order_items SET quantity = ? WHERE id = ?");
                $stmt->execute([$remainingQty, $itemId]);
                
                // Vytvoř novou zrušenou položku
                $noteText = $reason ? " - Zrušeno: $reason" : " - Zrušeno";
                $stmt = $pdo->prepare("
                    INSERT INTO order_items (order_id, item_type, item_name, quantity, unit_price, note, status) 
                    SELECT order_id, item_type, item_name, ?, unit_price, 
                           CONCAT(COALESCE(note, ''), ?), 
                           'cancelled'
                    FROM order_items WHERE id = ?
                ");
                $stmt->execute([$cancelQty, $noteText, $itemId]);
                
            } else {
                // Zruš celou položku
                $noteUpdate = $reason ? 
                    "CONCAT(COALESCE(note, ''), ' - Zrušeno: ', ?)" : 
                    "COALESCE(note, '')";
                $params = $reason ? [$reason, $itemId] : [$itemId];
                
                $stmt = $pdo->prepare("UPDATE order_items SET status = 'cancelled', note = $noteUpdate WHERE id = ?");
                $stmt->execute($params);
            }
            
            $processedItems[] = [
                'id' => $itemId,
                'cancelled_qty' => $cancelQty,
                'remaining_qty' => max(0, $currentQty - $cancelQty)
            ];
        }
        
        if (empty($processedItems)) {
            $pdo->rollBack();
            jsend(false, null, 'Žádné položky nebyly zpracovány!');
            exit;
        }
        
        // ✅ NOVÁ LOGIKA - zkontroluj jestli uvolnit stoly
        $tablesFreed = [];
        foreach ($affectedTableSessions as $sessionId => $tableNumber) {
            // Spočítej aktivní (nezrušené) položky v session
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                WHERE o.table_session_id = ? 
                AND oi.status NOT IN ('cancelled')
            ");
            $stmt->execute([$sessionId]);
            $activeItems = $stmt->fetchColumn();
            
            file_put_contents('/tmp/restaurant_debug.log', 
                "CANCEL-CHECK: Table $tableNumber, Session $sessionId has $activeItems active items\n", 
                FILE_APPEND
            );
            
            // Pokud nezbývají žádné aktivní položky, uzavři session a uvolni stůl
            if ($activeItems == 0) {
                // Uzavři session
                $stmt = $pdo->prepare("
                    UPDATE table_sessions 
                    SET is_active = 0, end_time = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$sessionId]);
                
                // Uvolni stůl
                $stmt = $pdo->prepare("
                    UPDATE restaurant_tables 
                    SET status = 'free', 
                        session_start = NULL, 
                        last_order_at = NULL, 
                        total_amount = 0.00,
                        notes = NULL
                    WHERE table_number = ?
                ");
                $stmt->execute([$tableNumber]);
                
                $tablesFreed[] = $tableNumber;
                
                error_log("✅ AUTO-FREE: Table $tableNumber freed after cancelling all items");
                file_put_contents('/tmp/restaurant_debug.log', 
                    "✅ AUTO-FREE: Table $tableNumber freed - no active items remaining\n", 
                    FILE_APPEND
                );
            }
        }
        
        // Log pro audit
        error_log("CANCELLED ITEMS: User cancelled $totalCancelQuantity x '$itemName' Reason: '$reason'");
        
        $pdo->commit();
        
        $message = "Úspěšně zrušeno {$totalCancelQuantity}× {$itemName}";
        if (!empty($tablesFreed)) {
            $message .= " (Uvolněny stoly: " . implode(', ', $tablesFreed) . ")";
        }
        
        jsend(true, [
            'message' => $message,
            'processed_items' => $processedItems,
            'reason' => $reason,
            'tables_freed' => $tablesFreed  // ✅ NOVÉ - info o uvolněných stolech
        ]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsend(false, null, 'Chyba při rušení položek: ' . $e->getMessage());
    }
}
    // KITCHEN STATS
    if ($action === 'kitchen-stats') {
        $q = $pdo->query("
            SELECT 
                oi.item_type,
                oi.quantity
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            WHERE oi.item_type IN ('pizza', 'pasta', 'predkrm', 'dezert', 'drink')
            AND oi.status IN ('pending','preparing','ready')
            ORDER BY o.created_at DESC
        ");
        
        if (!$q) {
            jsend(false, null, "Chyba při načítání statistik kuchyně");
            return;
        }

        $items = $q->fetchAll(PDO::FETCH_ASSOC);
        jsend(true, ['items' => $items]);
    }

    // BAR ITEMS
    if ($action === 'bar-items') {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    oi.*,
                    o.table_session_id,
                    o.created_at,
                    o.printed_at,
                    o.id as order_id,
                    ts.table_number,
                    rt.table_code,
                    rt.notes,
                    rt.status as table_status
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                JOIN table_sessions ts ON o.table_session_id = ts.id
                JOIN restaurant_tables rt ON ts.table_number = rt.table_number
                WHERE oi.item_type IN ('drink', 'pivo', 'vino', 'nealko', 'spritz', 'negroni', 'koktejl', 'digestiv')
                AND oi.status IN ('pending','preparing')
                ORDER BY 
                    CASE WHEN o.printed_at IS NULL THEN 0 ELSE 1 END,
                    o.created_at DESC
            ");
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            jsend(true, ['items' => $items]);
        } catch (Exception $e) {
            jsend(false, null, 'Chyba při načítání bar items: ' . $e->getMessage());
        }
    }

    // READY ITEMS
    if ($action === 'ready-items') {
        $q = $pdo->query(
            "SELECT oi.*, o.table_session_id, ts.table_number, rt.table_code
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            JOIN table_sessions ts ON o.table_session_id = ts.id
            JOIN restaurant_tables rt ON ts.table_number = rt.table_number
            WHERE oi.status = 'ready'
            ORDER BY oi.id"
        );
        $items = $q->fetchAll(PDO::FETCH_ASSOC);
        jsend(true, ['items' => $items]);
    }

// Přidejte do restaurant-api.php test endpoint:
if ($action === 'test-curl') {
    $curl_available = function_exists('curl_init');
    $curl_version = $curl_available ? curl_version() : null;
    
    jsend(true, [
        'curl_available' => $curl_available,
        'curl_version' => $curl_version,
        'php_version' => phpversion(),
        'loaded_extensions' => get_loaded_extensions()
    ]);
}

    // ITEM STATUS - ROZŠÍŘENO O SYNCHRONIZACI
    if ($action === 'item-status') {
        $body = getJsonBody();
        $item_id = intval($body['item_id'] ?? 0);
        $status = $body['status'] ?? '';
        $note = $body['note'] ?? '';

        if (!$item_id || !$status) jsend(false, null, "Chybí položka nebo status!");

        $q = $pdo->prepare("
            SELECT oi.item_name, oi.unit_price, oi.item_type, oi.quantity, oi.note,
                   o.table_session_id, ts.table_number
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            JOIN table_sessions ts ON o.table_session_id = ts.id
            WHERE oi.id = ?
        ");
        $q->execute([$item_id]);
        $item = $q->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            jsend(false, null, "Položka nenalezena!");
            return;
        }

        try {
            $pdo->beginTransaction();
            
            // Původní logika pro spálené pizzy
            if ($item['item_type'] === 'pizza' && $status === 'pending' && $note === 'Spalena') {
                $stmt = $pdo->prepare("INSERT INTO burnt_pizzas_log (pizza_id, pizza_name, total, burnt_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$item_id, $item['item_name'], $item['unit_price']]);
                $stmt = $pdo->prepare("UPDATE daily_stats SET burnt_items = burnt_items + 1 WHERE date = ?");
                $stmt->execute([$today]);
            }

            // Aktualizuj status položky
            if ($status === 'ready') {
                $stmt = $pdo->prepare("UPDATE order_items SET status = ?, note = ?, prepared_at = NOW() WHERE id = ?");
                $stmt->execute([$status, $note, $item_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE order_items SET status = ?, note = ? WHERE id = ?");
                $stmt->execute([$status, $note, $item_id]);
            }

            // ✅ NOVÁ SYNCHRONIZAČNÍ LOGIKA
            $table_session_id = $item['table_session_id'];
            $table_number = $item['table_number'];
            
            // Pokud je pizza hotová, zkontroluj jestli povolit pasty
            if ($item['item_type'] === 'pizza' && $status === 'ready') {
                // Zkontroluj jestli na tomto stole čekají pasty
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM order_items oi
                    JOIN orders o ON oi.order_id = o.id
                    WHERE o.table_session_id = ?
                    AND oi.item_type = 'pasta'
                    AND oi.status = 'pending'
                ");
                $stmt->execute([$table_session_id]);
                $waitingPasta = $stmt->fetchColumn();
                
                if ($waitingPasta > 0) {
                    // Automaticky povolit pasty
                    $stmt = $pdo->prepare("
                        UPDATE order_items oi
                        JOIN orders o ON oi.order_id = o.id
                        SET oi.status = 'preparing',
                            oi.note = CONCAT(COALESCE(oi.note, ''), ' - Pizza hotová, pasta povolena')
                        WHERE o.table_session_id = ?
                        AND oi.item_type = 'pasta'
                        AND oi.status = 'pending'
                    ");
                    $stmt->execute([$table_session_id]);
                    
                    error_log("✅ AUTO-RELEASE: Released $waitingPasta pasta items for table $table_number");
                }
            }
            
            // Pokud je hlavní chod (pizza nebo pasta) hotový, zkontroluj dezerty
            if (in_array($item['item_type'], ['pizza', 'pasta']) && $status === 'ready') {
                // Zkontroluj jestli jsou všechny hlavní chody hotové
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM order_items oi
                    JOIN orders o ON oi.order_id = o.id
                    WHERE o.table_session_id = ?
                    AND oi.item_type IN ('pizza', 'pasta')
                    AND oi.status IN ('pending', 'preparing')
                ");
                $stmt->execute([$table_session_id]);
                $remainingMain = $stmt->fetchColumn();
                
                if ($remainingMain == 0) {
                    // Všechny hlavní chody hotové, povolit dezerty
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) FROM order_items oi
                        JOIN orders o ON oi.order_id = o.id
                        WHERE o.table_session_id = ?
                        AND oi.item_type = 'dezert'
                        AND oi.status = 'pending'
                    ");
                    $stmt->execute([$table_session_id]);
                    $waitingDesserts = $stmt->fetchColumn();
                    
                    if ($waitingDesserts > 0) {
                        $stmt = $pdo->prepare("
                            UPDATE order_items oi
                            JOIN orders o ON oi.order_id = o.id
                            SET oi.status = 'preparing',
                                oi.note = CONCAT(COALESCE(oi.note, ''), ' - Hlavní chod hotový, dezert povolen')
                            WHERE o.table_session_id = ?
                            AND oi.item_type = 'dezert'
                            AND oi.status = 'pending'
                        ");
                        $stmt->execute([$table_session_id]);
                        
                        error_log("✅ AUTO-RELEASE: Released $waitingDesserts dessert items for table $table_number");
                    }
                }
            }

            $pdo->commit();
            jsend(true);
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            jsend(false, null, "Chyba při aktualizaci statusu: " . $e->getMessage());
        }
    }

    // SPRÁVA NÁPOJŮ
    if ($action === 'add-drink') {
        $body = getJsonBody();
        $type = $body['type'] ?? '';
        $name = $body['name'] ?? '';
        $description = $body['description'] ?? '';
        $price = $body['price'] ?? 0;
        $cost_price = $body['cost_price'] ?? 0;
        $is_active = $body['is_active'] ?? 0;
        $category = $body['category'] ?? '';

        if (!$type || !$name) jsend(false, null, 'Chybí typ nebo název nápoje!');

        try {
            $stmt = $pdo->prepare("INSERT INTO drink_types (type, name, description, price, cost_price, is_active, category) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$type, $name, $description, $price, $cost_price, $is_active, $category]);
            jsend(true);
        } catch (Exception $e) {
            jsend(false, null, 'Přidání nápoje selhalo: ' . $e->getMessage());
        }
    }

    if ($action === 'edit-drink') {
        $type = $_GET['type'] ?? '';
        $body = getJsonBody();
        $name = $body['name'] ?? '';
        $description = $body['description'] ?? '';
        $price = $body['price'] ?? 0;
        $cost_price = $body['cost_price'] ?? 0;
        $is_active = $body['is_active'] ?? 0;
        $category = $body['category'] ?? '';

        if (!$type || !$name) jsend(false, null, 'Chybí typ nebo název nápoje!');

        try {
            $stmt = $pdo->prepare("UPDATE drink_types SET name=?, description=?, price=?, cost_price=?, is_active=?, category=? WHERE type=?");
            $stmt->execute([$name, $description, $price, $cost_price, $is_active, $category, $type]);
            jsend(true);
        } catch (Exception $e) {
            jsend(false, null, 'Úprava nápoje selhala: ' . $e->getMessage());
        }
    }

    if ($action === 'toggle-drink') {
        $type = $_GET['type'] ?? '';
        if (!$type) jsend(false, null, 'Chybí typ nápoje!');

        try {
            $stmt = $pdo->prepare("SELECT is_active FROM drink_types WHERE type=?");
            $stmt->execute([$type]);
            $drink = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$drink) {
                jsend(false, null, 'Nápoj nenalezen!');
                return;
            }

            $new_status = $drink['is_active'] == 1 ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE drink_types SET is_active=? WHERE type=?");
            $stmt->execute([$new_status, $type]);
            jsend(true);
        } catch (Exception $e) {
            jsend(false, null, 'Změna stavu nápoje selhala: ' . $e->getMessage());
        }
    }

    if ($action === 'delete-drink') {
        $type = $_GET['type'] ?? '';
        if (!$type) jsend(false, null, 'Chybí typ nápoje!');

        try {
            $stmt = $pdo->prepare("DELETE FROM drink_types WHERE type = ?");
            $result = $stmt->execute([$type]);
            
            if ($result) {
                jsend(true);
            } else {
                jsend(false, null, 'Nápoj se nepodařilo smazat');
            }
        } catch (Exception $e) {
            jsend(false, null, 'Smazání nápoje selhalo: ' . $e->getMessage());
        }
    }
   
   if ($action === 'test-ngrok') {
    $test_url = 'https://' . PRINTER_RPI_IP . '/status';
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 5,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);
    
    $response = @file_get_contents($test_url, false, $context);
    
    jsend(true, [
        'test_url' => $test_url,
        'response' => $response,
        'headers' => $http_response_header ?? []
    ]);
    exit;
}
   
   
        // PAY ITEMS
   
   
    if ($action === 'pay-items') {
    $body = getJsonBody();
    $items = $body['items'] ?? [];
    $paymentMethod = $body['payment_method'] ?? 'cash';
    $tableNumber = intval($body['table_number'] ?? 0);  // ✅ PŘIDÁNO
    $closeSession = $body['close_session'] ?? false;    // ✅ PŘIDÁNO
    
    if (!is_array($items) || empty($items)) {
        jsend(false, null, 'Chybí položky k zaplacení!');
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        $paidItemIds = [];
        
        // Zaplatit vybrané položky
        foreach ($items as $item) {
            $itemId = intval($item['id']);
            $quantity = intval($item['quantity']);
            
            if ($itemId <= 0 || $quantity <= 0) continue;
            
            $stmt = $pdo->prepare("
                UPDATE order_items 
                SET status = 'paid', 
                    payment_method = ?,
                    paid_at = NOW() 
                WHERE id = ? AND status = 'delivered'
            ");
            $result = $stmt->execute([$paymentMethod, $itemId]);
            
            if ($result && $stmt->rowCount() > 0) {
                $paidItemIds[] = $itemId;
            }
        }
        
        // ✅ NOVÁ LOGIKA: Zkontroluj jestli jsou všechny položky zaplacené
        if ($tableNumber > 0) {
            // Najdi aktivní session
            $stmt = $pdo->prepare("SELECT id FROM table_sessions WHERE table_number = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$tableNumber]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($session) {
                $sessionId = $session['id'];
                
                // Spočítej všechny nezrušené položky v session
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as total_items
                    FROM order_items oi
                    JOIN orders o ON oi.order_id = o.id
                    WHERE o.table_session_id = ? 
                    AND oi.status NOT IN ('cancelled')
                ");
                $stmt->execute([$sessionId]);
                $totalItems = $stmt->fetchColumn();
                
                // Spočítej zaplacené položky
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as paid_items
                    FROM order_items oi
                    JOIN orders o ON oi.order_id = o.id
                    WHERE o.table_session_id = ? 
                    AND oi.status = 'paid'
                ");
                $stmt->execute([$sessionId]);
                $paidItems = $stmt->fetchColumn();
                
                // ✅ KLÍČOVÁ LOGIKA: Pokud jsou všechny položky zaplacené, uzavři session
                if ($totalItems > 0 && $paidItems >= $totalItems) {
                    // Uzavři session
                    $stmt = $pdo->prepare("
                        UPDATE table_sessions 
                        SET is_active = 0, end_time = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$sessionId]);
                    
                    // Resetuj stůl
                    $stmt = $pdo->prepare("
                        UPDATE restaurant_tables 
                        SET status = 'free', 
                            session_start = NULL, 
                            last_order_at = NULL, 
                            total_amount = 0.00,
                            notes = NULL
                        WHERE table_number = ?
                    ");
                    $stmt->execute([$tableNumber]);
                    
                    error_log("✅ Session closed for table $tableNumber - all items paid ($paidItems/$totalItems)");
                    file_put_contents('/tmp/restaurant_debug.log', 
                        "✅ AUTO-CLOSE: Table $tableNumber session closed - all items paid ($paidItems/$totalItems)\n", 
                        FILE_APPEND
                    );
                    
                    $fullyPaid = true;
                } else {
                    $fullyPaid = false;
                    error_log("⏳ Table $tableNumber session remains active - paid: $paidItems, total: $totalItems");
                    file_put_contents('/tmp/restaurant_debug.log', 
                        "⏳ PARTIAL: Table $tableNumber session remains active - paid: $paidItems, total: $totalItems\n", 
                        FILE_APPEND
                    );
                }
            } else {
                $fullyPaid = false;
            }
        } else {
            $fullyPaid = false;
        }
        
        $pdo->commit();
        
        jsend(true, [
            'message' => 'Položky byly úspěšně zaplaceny',
            'payment_method' => $paymentMethod,
            'paid_items' => count($paidItemIds),
            'paid_item_ids' => $paidItemIds,
            'table_closed' => $fullyPaid ?? false,  // ✅ PŘIDÁNO
            'table_number' => $tableNumber
        ]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("❌ Payment error: " . $e->getMessage());
        file_put_contents('/tmp/restaurant_debug.log', 
            "❌ PAYMENT ERROR: " . $e->getMessage() . "\n", 
            FILE_APPEND
        );
        
        jsend(false, null, 'Chyba při platbě: ' . $e->getMessage());
    }
}

    // CLEAR DAILY STATS
    if ($action === 'clear-daily-stats') {
        try {
            ob_clean();
            
            $stmt = $pdo->prepare("
                UPDATE daily_stats 
                SET total_orders = 0,
                    total_pizzas = 0,
                    total_drinks = 0,
                    total_revenue = 0,
                    burnt_items = 0,
                    avg_preparation_time = 0,
                    avg_kitchen_time = 0,
                    avg_bar_time = 0
                WHERE date = ?
            ");
            $stmt->execute([$today]);
            $rowCount = $stmt->rowCount();

            $stmt = $pdo->prepare("DELETE FROM burnt_pizzas_log WHERE DATE(burnt_at) = ?");
            $stmt->execute([$today]);
            $burntDeleted = $stmt->rowCount();

            $response = [
                'success' => true,
                'data' => [
                    'message' => "Denní statistiky byly vymazány! (Smazáno {$burntDeleted} spálených pizz)",
                    'deleted_burnt_pizzas' => $burntDeleted
                ],
                'error' => null
            ];
            
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($response, 
                JSON_UNESCAPED_UNICODE | 
                JSON_UNESCAPED_SLASHES | 
                JSON_PRETTY_PRINT
            );
            exit;

        } catch (Exception $e) {
            ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'data' => null,
                'error' => 'Smazání denních statistik selhalo: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // DODATEČNÉ AKCE PRO ZAMĚSTNANCE
    if ($action === 'get-employees') {
        try {
            $q = $pdo->query("SELECT id, name FROM employees");
            $employees = $q->fetchAll(PDO::FETCH_ASSOC);
            jsend(true, ['employees' => $employees]);
        } catch (Exception $e) {
            jsend(false, null, 'Načtení zaměstnanců selhalo: ' . $e->getMessage());
        }
    }

    // DUPLICITNÍ AKCE - DOPLŇUJI PRO JISTOTU
    if ($action === 'get-order-details') {
        $order_id = intval($_GET['id'] ?? 0);
        if (!$order_id) jsend(false, null, "Chybí ID objednávky!");

        try {
            $orderSql = "
                SELECT o.*, ts.table_number, rt.table_code 
                FROM orders o 
                LEFT JOIN table_sessions ts ON o.table_session_id = ts.id 
                LEFT JOIN restaurant_tables rt ON ts.table_number = rt.table_number 
                WHERE o.id = ?
            ";
            $stmt = $pdo->prepare($orderSql);
            $stmt->execute([$order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                jsend(false, null, "Objednávka nenalezena!");
                return;
            }

            $itemsSql = "SELECT * FROM order_items WHERE order_id = ?";
            $stmt = $pdo->prepare($itemsSql);
            $stmt->execute([$order_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $order['items'] = $items;
            
            jsend(true, $order);
        } catch (Exception $e) {
            jsend(false, null, "Chyba při načítání detailů objednávky");
        }
    }
 if ($action === 'change-table') {
    $body = getJsonBody();
    $fromTable = intval($body['from_table'] ?? 0);
    $toTable = intval($body['to_table'] ?? 0);
    
    if (!$fromTable || !$toTable) {
        jsend(false, null, 'Chybí čísla stolů!');
        exit;
    }
    
    if ($fromTable === $toTable) {
        jsend(false, null, 'Nelze přesunout na stejný stůl!');
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Najdi aktivní session původního stolu
        $stmt = $pdo->prepare("SELECT id FROM table_sessions WHERE table_number = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$fromTable]);
        $fromSession = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$fromSession) {
            $pdo->rollBack();
            jsend(false, null, 'Původní stůl nemá aktivní session!');
            exit;
        }
        
        // Zkontroluj, jestli cílový stůl už nemá aktivní session
        $stmt = $pdo->prepare("SELECT id FROM table_sessions WHERE table_number = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$toTable]);
        $toSession = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($toSession) {
            $pdo->rollBack();
            jsend(false, null, 'Cílový stůl už má aktivní objednávky!');
            exit;
        }
        
        // Zkontroluj, jestli cílový stůl existuje
        $stmt = $pdo->prepare("SELECT table_number FROM restaurant_tables WHERE table_number = ?");
        $stmt->execute([$toTable]);
        if (!$stmt->fetch()) {
            $pdo->rollBack();
            jsend(false, null, 'Cílový stůl neexistuje!');
            exit;
        }
        
        // Ukončit starou session
        $stmt = $pdo->prepare("UPDATE table_sessions SET is_active = 0, end_time = NOW() WHERE id = ?");
        $stmt->execute([$fromSession['id']]);
        
        // Vytvořit novou session pro cílový stůl
        $stmt = $pdo->prepare("INSERT INTO table_sessions (table_number, start_time, is_active) VALUES (?, NOW(), 1)");
        $stmt->execute([$toTable]);
        $newSessionId = $pdo->lastInsertId();
        
        // Přesunout všechny objednávky na novou session
        $stmt = $pdo->prepare("UPDATE orders SET table_session_id = ? WHERE table_session_id = ?");
        $stmt->execute([$newSessionId, $fromSession['id']]);
        
        // Aktualizovat stav stolů
        $stmt = $pdo->prepare("UPDATE restaurant_tables SET status = 'free' WHERE table_number = ?");
        $stmt->execute([$fromTable]);
        
        $stmt = $pdo->prepare("UPDATE restaurant_tables SET status = 'occupied', session_start = NOW() WHERE table_number = ?");
        $stmt->execute([$toTable]);
        
        $pdo->commit();
        
        jsend(true, [
            'message' => "Objednávky byly přesunuty ze stolu $fromTable na stůl $toTable",
            'from_table' => $fromTable,
            'to_table' => $toTable,
            'new_session_id' => $newSessionId
        ]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsend(false, null, 'Chyba při změně stolu: ' . $e->getMessage());
    }
}
 
 // HISTORIE OBJEDNÁVEK
    if ($action === 'order-history') {
        $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        $tableNumber = $_GET['table_number'] ?? null;
        $employeeName = $_GET['employee_name'] ?? null;

        try {
            // Základní SQL pro objednávky
            $sql = "
                SELECT DISTINCT
                    o.id,
                    o.created_at,
                    o.customer_name,
                    o.employee_name,
                    ts.table_number,
                    rt.table_code
                FROM orders o
                JOIN table_sessions ts ON o.table_session_id = ts.id
                LEFT JOIN restaurant_tables rt ON ts.table_number = rt.table_number
                WHERE DATE(o.created_at) BETWEEN ? AND ?
                AND EXISTS (
                    SELECT 1 FROM order_items oi 
                    WHERE oi.order_id = o.id 
                    AND oi.status = 'paid'
                )
            ";
            
            $params_array = [$dateFrom, $dateTo];
            
            // Filtry
            if ($tableNumber) {
                $sql .= " AND ts.table_number = ?";
                $params_array[] = $tableNumber;
            }
            
            if ($employeeName) {
                $sql .= " AND o.employee_name = ?";
                $params_array[] = $employeeName;
            }
            
            $sql .= " ORDER BY o.created_at DESC LIMIT 100";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params_array);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Pro každou objednávku načteme položky
            foreach ($orders as &$order) {
                $itemsSql = "
                    SELECT 
                        oi.item_name,
                        oi.quantity,
                        oi.unit_price,
                        oi.note,
                        oi.item_type
                    FROM order_items oi
                    WHERE oi.order_id = ?
                    AND oi.status = 'paid'
                    ORDER BY oi.id
                ";
                
                $itemsStmt = $pdo->prepare($itemsSql);
                $itemsStmt->execute([$order['id']]);
                $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            jsend(true, ['orders' => $orders]);
            
        } catch (PDOException $e) {
            jsend(false, null, $e->getMessage());
        }
    }

    if ($action === 'employees-list') {
        try {
            $sql = "
                SELECT 
                    o.employee_name as name,
                    COUNT(DISTINCT o.id) as order_count
                FROM orders o
                WHERE o.employee_name IS NOT NULL 
                AND o.employee_name != ''
                AND DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY o.employee_name
                ORDER BY order_count DESC
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            jsend(true, ['employees' => $employees]);
        } catch (PDOException $e) {
            jsend(false, null, $e->getMessage());
        }
    }
// RESERVATION ENDPOINTS - MUSÍ BÝT PŘED DEFAULT CASE!

// Add reservation
if ($action === 'add-reservation') {
    $body = getJsonBody();
    
    $customer_name = $body['customer_name'] ?? '';
    $phone = $body['phone'] ?? '';
    $email = $body['email'] ?? '';
    $party_size = intval($body['party_size'] ?? 0);
    $reservation_date = $body['reservation_date'] ?? '';
    $reservation_time = $body['reservation_time'] ?? '';
    $table_number = $body['table_number'] ?? null;
    $notes = $body['notes'] ?? '';
    
    if (empty($customer_name) || empty($phone) || $party_size <= 0 || empty($reservation_date) || empty($reservation_time)) {
        jsend(false, null, "Všechna povinná pole musí být vyplněna!");
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO reservations (customer_name, phone, email, party_size, reservation_date, reservation_time, table_number, notes, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $result = $stmt->execute([$customer_name, $phone, $email, $party_size, $reservation_date, $reservation_time, $table_number, $notes]);
        
        if ($result) {
            jsend(true, ['reservation_id' => $pdo->lastInsertId()], "Rezervace byla úspěšně vytvořena!");
        } else {
            jsend(false, null, "Chyba při vytváření rezervace!");
        }
    } catch (Exception $e) {
        jsend(false, null, "Chyba databáze: " . $e->getMessage());
    }
}

// Get reservations
if ($action === 'get-reservations') {
    $date = $_GET['date'] ?? '';
    $status = $_GET['status'] ?? '';
    
    $sql = "SELECT * FROM reservations WHERE 1=1";
    $params = [];
    
    if ($date) {
        $sql .= " AND reservation_date = ?";
        $params[] = $date;
    }
    
    if ($status) {
        $sql .= " AND status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY reservation_date, reservation_time";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        jsend(true, $reservations);
    } catch (Exception $e) {
        jsend(false, null, "Chyba při načítání rezervací: " . $e->getMessage());
    }
}

// Update reservation
if ($action === 'update-reservation') {
    $body = getJsonBody();
    
    $id = intval($body['id'] ?? 0);
    $status = $body['status'] ?? '';
    
    if ($id <= 0) {
        jsend(false, null, "Neplatné ID rezervace!");
        exit;
    }
    
    try {
        if (!empty($status)) {
            $stmt = $pdo->prepare("UPDATE reservations SET status = ?, updated_at = NOW() WHERE id = ?");
            $result = $stmt->execute([$status, $id]);
        } else {
            // Full update if other fields are provided
            $customer_name = $body['customer_name'] ?? '';
            $phone = $body['phone'] ?? '';
            $email = $body['email'] ?? '';
            $party_size = intval($body['party_size'] ?? 0);
            $reservation_date = $body['reservation_date'] ?? '';
            $reservation_time = $body['reservation_time'] ?? '';
            $table_number = $body['table_number'] ?? null;
            $notes = $body['notes'] ?? '';
            
            $stmt = $pdo->prepare("
                UPDATE reservations 
                SET customer_name = ?, phone = ?, email = ?, party_size = ?, 
                    reservation_date = ?, reservation_time = ?, table_number = ?, 
                    notes = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $result = $stmt->execute([$customer_name, $phone, $email, $party_size, $reservation_date, $reservation_time, $table_number, $notes, $id]);
        }
        
        if ($result) {
            jsend(true, null, "Rezervace byla aktualizována!");
        } else {
            jsend(false, null, "Chyba při aktualizaci rezervace!");
        }
    } catch (Exception $e) {
        jsend(false, null, "Chyba databáze: " . $e->getMessage());
    }
}

// Cancel reservation
if ($action === 'cancel-reservation') {
    $body = getJsonBody();
    $id = intval($body['id'] ?? 0);
    
    if ($id <= 0) {
        jsend(false, null, "Neplatné ID rezervace!");
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE reservations SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        if ($result) {
            jsend(true, null, "Rezervace byla zrušena!");
        } else {
            jsend(false, null, "Chyba při rušení rezervace!");
        }
    } catch (Exception $e) {
        jsend(false, null, "Chyba databáze: " . $e->getMessage());
    }
}

// Get tables with reservations
if ($action === 'tables-with-reservations') {
    $date = $_GET['date'] ?? date('Y-m-d');
    
    try {
        // Get all tables
        $stmt = $pdo->query("SELECT * FROM restaurant_tables ORDER BY table_number");
        $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get reservations for the date
        $stmt = $pdo->prepare("
            SELECT * FROM reservations 
            WHERE reservation_date = ? AND status != 'cancelled'
            ORDER BY table_number, reservation_time
        ");
        $stmt->execute([$date]);
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group reservations by table
        $reservationsByTable = [];
        foreach ($reservations as $reservation) {
            $tableNum = $reservation['table_number'] ?? 0;
            if (!isset($reservationsByTable[$tableNum])) {
                $reservationsByTable[$tableNum] = [];
            }
            $reservationsByTable[$tableNum][] = $reservation;
        }
        
        // Add reservations to tables
        foreach ($tables as &$table) {
            $table['reservations'] = $reservationsByTable[$table['table_number']] ?? [];
        }
        
        jsend(true, $tables);
    } catch (Exception $e) {
        jsend(false, null, "Chyba při načítání stolů: " . $e->getMessage());
    }
}

// Get reservation details - NOVÝ ENDPOINT
if ($action === 'get-reservation-details') {
    $id = intval($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        jsend(false, null, "Neplatné ID rezervace!");
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ?");
        $stmt->execute([$id]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($reservation) {
            jsend(true, $reservation);
        } else {
            jsend(false, null, "Rezervace nenalezena!");
        }
    } catch (Exception $e) {
        jsend(false, null, "Chyba při načítání rezervace: " . $e->getMessage());
    }
}

// Delete reservation - NOVÝ ENDPOINT
if ($action === 'delete-reservation') {
    $body = getJsonBody();
    $id = intval($body['id'] ?? 0);
    
    if ($id <= 0) {
        jsend(false, null, "Neplatné ID rezervace!");
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM reservations WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        if ($result && $stmt->rowCount() > 0) {
            jsend(true, null, "Rezervace byla úspěšně smazána!");
        } else {
            jsend(false, null, "Rezervace nebyla nalezena nebo už byla smazána!");
        }
    } catch (Exception $e) {
        jsend(false, null, "Chyba databáze: " . $e->getMessage());
    }
}

// Update reservation - ROZŠÍŘENÝ ENDPOINT (původní upravte)
if ($action === 'update-reservation') {
    $body = getJsonBody();
    
    $id = intval($body['id'] ?? 0);
    
    if ($id <= 0) {
        jsend(false, null, "Neplatné ID rezervace!");
        exit;
    }
    
    try {
        // Pokud je zadán pouze status, aktualizuj jen status
        if (isset($body['status']) && count(array_filter($body, function($k) { 
            return !in_array($k, ['id', 'status']); 
        }, ARRAY_FILTER_USE_KEY)) === 0) {
            
            $status = $body['status'];
            $stmt = $pdo->prepare("UPDATE reservations SET status = ?, updated_at = NOW() WHERE id = ?");
            $result = $stmt->execute([$status, $id]);
            
        } else {
            // Úplná aktualizace rezervace
            $customer_name = $body['customer_name'] ?? '';
            $phone = $body['phone'] ?? '';
            $email = $body['email'] ?? '';
            $party_size = intval($body['party_size'] ?? 0);
            $reservation_date = $body['reservation_date'] ?? '';
            $reservation_time = $body['reservation_time'] ?? '';
            $table_number = $body['table_number'] ?? null;
            $notes = $body['notes'] ?? '';
            
            if (empty($customer_name) || empty($phone) || $party_size <= 0 || empty($reservation_date) || empty($reservation_time)) {
                jsend(false, null, "Všechna povinná pole musí být vyplněna!");
                exit;
            }
            
            $stmt = $pdo->prepare("
                UPDATE reservations 
                SET customer_name = ?, phone = ?, email = ?, party_size = ?, 
                    reservation_date = ?, reservation_time = ?, table_number = ?, 
                    notes = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $result = $stmt->execute([$customer_name, $phone, $email, $party_size, $reservation_date, $reservation_time, $table_number, $notes, $id]);
        }
        
        if ($result) {
            jsend(true, null, "Rezervace byla aktualizována!");
        } else {
            jsend(false, null, "Chyba při aktualizaci rezervace!");
        }
    } catch (Exception $e) {
        jsend(false, null, "Chyba databáze: " . $e->getMessage());
    }
}

    // DEFAULT CASE
    jsend(false, null, 'Neznámá akce: ' . $action);

} catch (Exception $e) {
    jsend(false, null, $e->getMessage());
}
?>

