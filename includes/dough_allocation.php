<?php
/**
 * Automated Dough Allocation System
 * Calculates reserved vs walk-in pizza allocation based on reservations
 */

// Configuration constants
define('DOUGH_FACTOR', 0.95);
define('DOUGH_MIN_PER_RES', 1);

/**
 * Get database connection for pizza orders (reuse existing function if available)
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
 * Recalculates daily dough allocation based on current reservations
 * @param string $date Date in Y-m-d format
 * @param bool $createIfMissing Create daily_supplies record if missing
 * @return array Result with success/error and allocation numbers
 */
function recalcDailyDoughAllocation($date, $createIfMissing = true) {
    try {
        $pdo = getPizzaOrdersDb();
        
        // Load or initialize daily_supplies for date
        $stmt = $pdo->prepare("SELECT * FROM daily_supplies WHERE date = ?");
        $stmt->execute([$date]);
        $dailySupplies = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$dailySupplies && $createIfMissing) {
            // Create with default values
            $stmt = $pdo->prepare("
                INSERT INTO daily_supplies (date, pizza_total, pizza_reserved, pizza_walkin, burrata_total, burrata_reserved, burrata_walkin, updated_by, updated_at) 
                VALUES (?, 120, 0, 120, 15, 12, 3, 'AUTO-ALLOC', NOW())
            ");
            $stmt->execute([$date]);
            
            // Reload the record
            $stmt = $pdo->prepare("SELECT * FROM daily_supplies WHERE date = ?");
            $stmt->execute([$date]);
            $dailySupplies = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if (!$dailySupplies) {
            return ['ok' => false, 'error' => 'Daily supplies record not found for date: ' . $date];
        }
        
        // Query reservations with confirmed or seated status for the date
        $stmt = $pdo->prepare("
            SELECT id, party_size 
            FROM reservations 
            WHERE reservation_date = ? 
            AND status IN ('confirmed', 'seated')
        ");
        $stmt->execute([$date]);
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate reserved dough allocation
        $totalReservedDough = 0;
        foreach ($reservations as $reservation) {
            $partySize = (int)$reservation['party_size'];
            $doughForReservation = max(DOUGH_MIN_PER_RES, ceil($partySize * DOUGH_FACTOR));
            $totalReservedDough += $doughForReservation;
        }
        
        // Cap reserved dough to not exceed total available
        $pizzaTotal = (int)$dailySupplies['pizza_total'];
        $pizzaReserved = min($totalReservedDough, $pizzaTotal);
        $pizzaWalkin = $pizzaTotal - $pizzaReserved;
        
        // Update daily_supplies with new allocation
        $stmt = $pdo->prepare("
            UPDATE daily_supplies 
            SET pizza_reserved = ?, pizza_walkin = ?, updated_by = 'AUTO-ALLOC', updated_at = NOW()
            WHERE date = ?
        ");
        $stmt->execute([$pizzaReserved, $pizzaWalkin, $date]);
        
        return [
            'ok' => true,
            'date' => $date,
            'pizza_total' => $pizzaTotal,
            'pizza_reserved' => $pizzaReserved,
            'pizza_walkin' => $pizzaWalkin,
            'reservations_count' => count($reservations),
            'total_reserved_dough' => $totalReservedDough
        ];
        
    } catch (Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Triggers dough recalculation if the date is today
 * @param string $date Date in Y-m-d format
 * @return array|null Result if recalculation was triggered, null otherwise
 */
function triggerDoughRecalcIfToday($date) {
    if ($date === date('Y-m-d')) {
        return recalcDailyDoughAllocation($date, false);
    }
    return null;
}