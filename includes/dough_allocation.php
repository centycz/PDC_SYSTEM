<?php
/**
 * Automated Dough Allocation System
 * Calculates reserved vs walk-in pizza allocation based on reservations
 *
 * Logika:
 *  - Rezervované těsto = součet ceil(party_size * DOUGH_FACTOR) pro všechny dnešní
 *    rezervace ve stavech ('confirmed','seated') (chráníme pizzy pro potvrzené + již usazené)
 *  - Walk-in = pizza_total - pizza_reserved (>=0)
 *  - DOUGH_FACTOR lze upravit podle zkušeností (default 1.2 pro konzistenci s dashboard odhadem)
 */

if (!defined('DOUGH_FACTOR')) {
    define('DOUGH_FACTOR', 1.2); // Pokud chceš starých 0.95, změň zde
}
if (!defined('DOUGH_MIN_PER_RES')) {
    define('DOUGH_MIN_PER_RES', 1);
}

/**
 * DB připojení pro tabulky v pizza_orders
 */
function getPizzaOrdersDb() {
    static $pdo = null;
    if ($pdo) return $pdo;
    try {
        $pdo = new PDO('mysql:host=127.0.0.1;dbname=pizza_orders;charset=utf8mb4', 'pizza_user', 'pizza');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("SET NAMES utf8mb4");
        $pdo->exec("SET CHARACTER SET utf8mb4");
    } catch (PDOException $e) {
        throw new Exception('Database connection error: ' . $e->getMessage());
    }
    return $pdo;
}

/**
 * Přepočet denní alokace těsta
 * @param string $date Y-m-d
 * @param bool $createIfMissing vytvořit záznam pokud neexistuje
 */
function recalcDailyDoughAllocation($date, $createIfMissing = true) {
    try {
        $pdo = getPizzaOrdersDb();
        
        $stmt = $pdo->prepare("SELECT * FROM daily_supplies WHERE date = ?");
        $stmt->execute([$date]);
        $dailySupplies = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$dailySupplies && $createIfMissing) {
            $stmt = $pdo->prepare("
                INSERT INTO daily_supplies (date, pizza_total, pizza_reserved, pizza_walkin,
                                            burrata_total, burrata_reserved, burrata_walkin,
                                            updated_by, updated_at)
                VALUES (?, 120, 0, 120, 15, 12, 3, 'AUTO-ALLOC', NOW())
            ");
            $stmt->execute([$date]);
            $stmt = $pdo->prepare("SELECT * FROM daily_supplies WHERE date = ?");
            $stmt->execute([$date]);
            $dailySupplies = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if (!$dailySupplies) {
            return ['ok' => false, 'error' => 'Daily supplies record not found for date: ' . $date];
        }
        
        // Bereme confirmed + seated
        $stmt = $pdo->prepare("
            SELECT id, party_size
            FROM reservations
            WHERE reservation_date = ?
              AND status IN ('confirmed','seated')
        ");
        $stmt->execute([$date]);
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $totalReservedDough = 0;
        foreach ($reservations as $res) {
            $ps = (int)$res['party_size'];
            $dough = max(DOUGH_MIN_PER_RES, ceil($ps * DOUGH_FACTOR));
            $totalReservedDough += $dough;
        }
        
        $pizzaTotal    = (int)$dailySupplies['pizza_total'];
        $pizzaUsed     = (int)($dailySupplies['pizza_used'] ?? 0);
        $pizzaReserved = min($pizzaTotal, $totalReservedDough);
        $pizzaWalkin   = max(0, $pizzaTotal - $pizzaReserved - $pizzaUsed);
        
        $stmt = $pdo->prepare("
            UPDATE daily_supplies
               SET pizza_reserved = ?, pizza_walkin = ?, updated_by = 'AUTO-ALLOC', updated_at = NOW()
             WHERE date = ?
        ");
        $stmt->execute([$pizzaReserved, $pizzaWalkin, $date]);
        
        error_log("[DOUGH RECALC] date=$date reservations=".count($reservations)." reserved=$pizzaReserved used=$pizzaUsed walkin=$pizzaWalkin factor=".DOUGH_FACTOR);
        
        return [
            'ok' => true,
            'date' => $date,
            'pizza_total' => $pizzaTotal,
            'pizza_used' => $pizzaUsed,
            'pizza_reserved' => $pizzaReserved,
            'pizza_walkin' => $pizzaWalkin,
            'reservations_count' => count($reservations),
            'total_reserved_dough' => $totalReservedDough,
            'factor' => DOUGH_FACTOR
        ];
        
    } catch (Exception $e) {
        error_log("[DOUGH RECALC ERROR] ".$e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Atomicky navýší pizza_used o zadané množství a přepočítá pizza_walkin
 * @param string $date Datum Y-m-d
 * @param int $qty Množství pizz k přičtení
 * @param string $by Kdo/co způsobilo změnu (pro logging)
 * @return bool Success status
 */
function incrementDailyPizzaUsed($date, $qty, $by = 'ORDER') {
    if ($qty <= 0) return false;
    
    try {
        $pdo = getPizzaOrdersDb();
        
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
        }
        
        // Lock row for update
        $stmt = $pdo->prepare("SELECT id, pizza_total, pizza_used, pizza_reserved FROM daily_supplies WHERE date = ? FOR UPDATE");
        $stmt->execute([$date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            // Create default record if missing
            $stmt = $pdo->prepare("
                INSERT INTO daily_supplies (date, pizza_total, burrata_total, pizza_used, burrata_used, 
                                          updated_by, updated_at, pizza_reserved, pizza_walkin, 
                                          burrata_reserved, burrata_walkin) 
                VALUES (?, 120, 15, 0, 0, ?, NOW(), 0, 0, 0, 0)
            ");
            $stmt->execute([$date, $by]);
            
            // Re-fetch the created record
            $stmt = $pdo->prepare("SELECT id, pizza_total, pizza_used, pizza_reserved FROM daily_supplies WHERE date = ? FOR UPDATE");
            $stmt->execute([$date]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        $pizzaUsed = (int)$row['pizza_used'] + $qty;
        $pizzaTotal = (int)$row['pizza_total'];
        $pizzaReserved = (int)$row['pizza_reserved'];
        $pizzaWalkin = max(0, $pizzaTotal - $pizzaReserved - $pizzaUsed);
        
        $stmt = $pdo->prepare("
            UPDATE daily_supplies 
            SET pizza_used = ?, pizza_walkin = ?, updated_by = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$pizzaUsed, $pizzaWalkin, $by, $row['id']]);
        
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
        
        error_log("[PIZZA USED +$qty] date=$date used=$pizzaUsed walkin=$pizzaWalkin reserved=$pizzaReserved by=$by");
        
        return true;
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[incrementDailyPizzaUsed ERROR] ' . $e->getMessage());
        return false;
    }
}

// triggerDoughRecalcIfToday je definována v reservations_lib.php