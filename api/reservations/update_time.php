<?php
/**
 * Update Reservation Time API Endpoint
 * POST /api/reservations/update_time.php
 * Allows rescheduling reservations with proper validation
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
    // Only allow POST method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Metoda není povolena']);
        exit;
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Neplatný JSON vstup']);
        exit;
    }

    // Validate required fields
    if (!isset($input['id']) || !isset($input['date']) || !isset($input['time'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'ID, datum a čas jsou povinné']);
        exit;
    }

    // Validate ID
    $id = intval($input['id']);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Neplatné ID rezervace']);
        exit;
    }

    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['date'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Neplatný formát data (YYYY-MM-DD)']);
        exit;
    }

    // Validate time format
    if (!preg_match('/^\d{1,2}:\d{2}$/', $input['time'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Neplatný formát času (H:MM)']);
        exit;
    }

    // Optional table number
    $tableNumber = null;
    if (isset($input['table_number']) && $input['table_number'] !== null) {
        $tableNumber = intval($input['table_number']);
        if ($tableNumber <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Neplatné číslo stolu']);
            exit;
        }
    }

    // Load reservation library
    require_once __DIR__ . '/../../includes/reservations_lib.php';

    // Call updateReservationTime function
    $result = updateReservationTime($id, $input['date'], $input['time'], $tableNumber);

    if ($result['ok']) {
        http_response_code(200);
        echo json_encode([
            'ok' => true,
            'message' => 'Rezervace byla úspěšně aktualizována',
            'data' => $result['updated']
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => $result['error']
        ]);
    }

} catch (Exception $e) {
    error_log("Error in update_time.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Vnitřní chyba serveru'
    ]);
}