<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['order_user'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'Neautorizovanı pøístup']);
    exit;
}

require_once __DIR__ . '/../../../includes/reservations_lib.php';

try {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        throw new Exception('Neplatnı JSON');
    }
    if (empty($payload['id'])) {
        throw new Exception('Chybí ID');
    }
    $id = (int)$payload['id'];
    unset($payload['id']);

    $result = updateReservation($id, $payload);
    if (!$result['ok']) {
        http_response_code(400);
    }
    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}