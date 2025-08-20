<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__.'/../../../includes/dough_allocation.php';

if (!isset($_SESSION['order_user'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'Neautorizovaný přístup']);
    exit;
}

function jsonFail($code,$msg){
    http_response_code($code);
    echo json_encode(['ok'=>false,'error'=>$msg]);
    return false;
}

try {
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        jsonFail(400,'Prázdné tělo požadavku');
        return;
    }
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        jsonFail(400,'Neplatný JSON payload');
        return;
    }

    $items = $payload['items'] ?? [];
    if (!is_array($items) || count($items) === 0) {
        jsonFail(400,'Žádné položky objednávky');
        return;
    }

    $date = date('Y-m-d');
    $pdo = getPizzaOrdersDb();
    if (!$pdo->inTransaction()) $pdo->beginTransaction();

    // Table session ID – pokud nepřišlo, použij jednoduchý generátor (čas + random)
    $tableSessionId = (int)($payload['table_session_id'] ?? 0);
    if ($tableSessionId <= 0) {
        $tableSessionId = (int)(time() . rand(10,99));
    }

    $employeeName = $payload['employee_name'] ?? null;
    $customerName = $payload['customer_name'] ?? null;

    // Určíme typ objednávky: pokud alespoň jedna položka není pizza, můžeme později rozdělit – nyní 'pizza'
    $orderType = 'pizza';

    $stmt = $pdo->prepare("INSERT INTO orders (table_session_id,status,order_type,created_at,employee_name,customer_name,is_reserved) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$tableSessionId,'pending',$orderType,date('Y-m-d H:i:s'),$employeeName,$customerName,0]);
    $orderId = $pdo->lastInsertId();

    $pizzaCount = 0;

    $itemIns = $pdo->prepare("INSERT INTO kitchen_order_items (order_id,pizza_type,pizza_name,price,quantity,status,created_at,updated_at) VALUES (?,?,?,?,?,'waiting',NOW(),NOW())");

    foreach ($items as $it) {
        if (!is_array($it)) continue;
        $qty  = (int)($it['qty'] ?? 1); if ($qty < 1) $qty = 1;
        $type = $it['type'] ?? 'pizza';
        $pid  = $it['id'] ?? null;
        $pname= $it['name'] ?? '';
        $price= (float)($it['price'] ?? 0);

        // Počítáme jen pizzy (type === 'pizza') do spotřeby
        if ($type === 'pizza') $pizzaCount += $qty;

        $itemIns->execute([$orderId,$pid,$pname,$price,$qty]);
    }

    if ($pdo->inTransaction()) $pdo->commit();

    // Spotřeba těsta
    $consumedLogged = false;
    if ($pizzaCount > 0) {
        if (function_exists('incrementDailyPizzaUsed')) {
            $consumedLogged = incrementDailyPizzaUsed($date, $pizzaCount, 'ORDER');
        }
        if (!$consumedLogged) {
            // Fallback ručně
            try {
                $pdo2 = getPizzaOrdersDb();
                $pdo2->beginTransaction();
                $sel = $pdo2->prepare("SELECT id,pizza_total,pizza_used,pizza_reserved FROM daily_supplies WHERE date=? FOR UPDATE");
                $sel->execute([$date]);
                $row = $sel->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    $pdo2->prepare("INSERT INTO daily_supplies (date,pizza_total,burrata_total,pizza_used,burrata_used,updated_by,updated_at,pizza_reserved,pizza_walkin,burrata_reserved,burrata_walkin) VALUES (?,120,15,0,0,'ORDER',NOW(),0,0,0,0)")->execute([$date]);
                    $sel->execute([$date]);
                    $row = $sel->fetch(PDO::FETCH_ASSOC);
                }
                $pizzaUsedNew = (int)$row['pizza_used'] + $pizzaCount;
                $pizzaTotal   = (int)$row['pizza_total'];
                $pizzaReserved= (int)$row['pizza_reserved'];
                $pizzaWalkin  = max(0, $pizzaTotal - $pizzaReserved - $pizzaUsedNew);
                $upd = $pdo2->prepare("UPDATE daily_supplies SET pizza_used=?, pizza_walkin=?, updated_by='ORDER', updated_at=NOW() WHERE id=?");
                $upd->execute([$pizzaUsedNew,$pizzaWalkin,$row['id']]);
                $pdo2->commit();
                error_log("[FALLBACK PIZZA_USED +$pizzaCount] date=$date used=$pizzaUsedNew walkin=$pizzaWalkin reserved=$pizzaReserved");
            } catch (Throwable $fbE) {
                if (isset($pdo2) && $pdo2->inTransaction()) $pdo2->rollBack();
                error_log('[FALLBACK PIZZA_USED ERROR] '.$fbE->getMessage());
            }
        }
    }

    $printerUnavailable = false;
    try {
        // Volání tiskárny (pokud implementováno) obalit try/catch
        // printOrder($orderId);
    } catch (Throwable $pe) {
        $printerUnavailable = true;
        error_log('[PRINT ERROR] '.$pe->getMessage());
    }

    echo json_encode([
        'ok' => true,
        'order_id' => (int)$orderId,
        'pizza_count' => $pizzaCount,
        'printer_unavailable' => $printerUnavailable
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('[ORDER_CREATE FATAL] '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Interní chyba serveru']);
}