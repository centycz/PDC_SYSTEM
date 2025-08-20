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
        $pizzaReserved = min($pizzaTotal, $totalReservedDough);
        $pizzaWalkin   = max(0, $pizzaTotal - $pizzaReserved);
        
        $stmt = $pdo->prepare("
            UPDATE daily_supplies
               SET pizza_reserved = ?, pizza_walkin = ?, updated_by = 'AUTO-ALLOC', updated_at = NOW()
             WHERE date = ?
        ");
        $stmt->execute([$pizzaReserved, $pizzaWalkin, $date]);
        
        error_log("[DOUGH RECALC] date=$date reservations=".count($reservations)." reserved=$pizzaReserved walkin=$pizzaWalkin factor=".DOUGH_FACTOR);
        
        return [
            'ok' => true,
            'date' => $date,
            'pizza_total' => $pizzaTotal,
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

// triggerDoughRecalcIfToday je definována v reservations_lib.php