<?php
/**
 * Opening hours endpoint
 * GET /api/reservations/opening_hours.php?date=YYYY-MM-DD
 * POST /api/reservations/opening_hours.php
 * Content-Type: application/json
 */

session_start();

// Check authentication
if (!isset($_SESSION['order_user'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Neautorizovaný přístup']);
    exit;
}

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../includes/reservations_lib.php';
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get opening hours for a specific date
        $date = $_GET['date'] ?? date('Y-m-d');
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new Exception('Neplatný formát data');
        }
        
        $pdo = getReservationDb();
        $hours = getOpeningHours($pdo, $date);
        
        echo json_encode([
            'ok' => true,
            'open_time' => $hours['open_time'],
            'close_time' => $hours['close_time']
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Set opening hours for a specific date
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Neplatný JSON formát');
        }
        
        // Validate required fields
        if (empty($data['date']) || empty($data['open_time']) || empty($data['close_time'])) {
            throw new Exception('Povinná pole: date, open_time, close_time');
        }
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date'])) {
            throw new Exception('Neplatný formát data');
        }
        
        // Validate time format (HH:MM)
        if (!preg_match('/^\d{2}:\d{2}$/', $data['open_time']) || !preg_match('/^\d{2}:\d{2}$/', $data['close_time'])) {
            throw new Exception('Neplatný formát času (HH:MM)');
        }
        
        // Validate that opening time is before closing time
        if ($data['open_time'] >= $data['close_time']) {
            throw new Exception('Čas otevření musí být dříve než čas zavření');
        }
        
        $pdo = getReservationDb();
        $result = saveOpeningHours($pdo, $data['date'], $data['open_time'], $data['close_time']);
        
        if ($result['ok']) {
            echo json_encode([
                'ok' => true,
                'message' => 'Otevírací hodiny byly úspěšně uloženy'
            ]);
        } else {
            http_response_code(400);
            echo json_encode($result);
        }
        
    } else {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Povoleny pouze GET a POST metody']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}