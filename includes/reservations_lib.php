<?php
/**
 * Reservations Library - Business logic for restaurant reservations
 * Handles 2-hour blocks, collision detection, and status workflows
 */

// Database connection helper
function getReservationDb() {
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
 * Kontroluje kolize rezervací pro daný stůl a časové okno
 * @param PDO $pdo Database connection
 * @param DateTime $startDatetime Start datetime of new reservation
 * @param DateTime $endDatetime End datetime of new reservation  
 * @param int $tableNumber Table number
 * @param int|null $excludeId ID rezervace k vyloučení (při editaci)
 * @return array|null Kolizní rezervace nebo null
 */
function findCollision($pdo, $startDatetime, $endDatetime, $tableNumber, $excludeId = null) {
    $sql = "
        SELECT * FROM reservations 
        WHERE table_number = ? 
        AND status NOT IN ('cancelled', 'no_show')
        AND (
            (start_datetime < ? AND end_datetime > ?) OR
            (start_datetime < ? AND end_datetime > ?)
        )
    ";
    
    $params = [$tableNumber, $endDatetime->format('Y-m-d H:i:s'), $startDatetime->format('Y-m-d H:i:s'),
               $startDatetime->format('Y-m-d H:i:s'), $endDatetime->format('Y-m-d H:i:s')];
    
    if ($excludeId) {
        $sql .= " AND id != ?";
        $params[] = $excludeId;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Vytvoří novou rezervaci s automatickým 2-hodinovým blokem
 * @param array $data Reservation data
 * @return array Result with success/error
 */
function createReservation($data) {
    try {
        $pdo = getReservationDb();
        
        // Validate required fields
        $required = ['customer_name', 'phone', 'party_size', 'reservation_date', 'reservation_time'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['ok' => false, 'error' => "Pole '$field' je povinné"];
            }
        }
        
        // Validate 30-minute intervals
        $time = $data['reservation_time'];
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            return ['ok' => false, 'error' => 'Neplatný formát času'];
        }
        
        $timeParts = explode(':', $time);
        $minutes = intval($timeParts[1]);
        if ($minutes !== 0 && $minutes !== 30) {
            return ['ok' => false, 'error' => 'Rezervace je možná pouze na celé a půl hodiny'];
        }
        
        // Validate within opening hours
        $openingHours = getOpeningHours($pdo, $data['reservation_date']);
        if (!validateWithinOpeningHours($time, $openingHours['open_time'], $openingHours['close_time'])) {
            return ['ok' => false, 'error' => 'Rezervace mimo otevírací dobu (' . $openingHours['open_time'] . '-' . $openingHours['close_time'] . ')'];
        }
        
        // Create start and end datetime (2-hour blocks)
        $dateStr = $data['reservation_date'] . ' ' . $data['reservation_time'];
        $startDatetime = new DateTime($dateStr);
        $endDatetime = clone $startDatetime;
        $endDatetime->add(new DateInterval('PT2H'));
        
        // Check for collision if table_number is specified
        if (!empty($data['table_number'])) {
            $collision = findCollision($pdo, $startDatetime, $endDatetime, $data['table_number']);
            if ($collision) {
                return ['ok' => false, 'error' => 'Kolize s existující rezervací na stole ' . $data['table_number'] . ' v čase ' . $collision['reservation_time']];
            }
        }
        
        // Fallback for older table structure - try to check if new columns exist
        $hasNewColumns = false;
        try {
            $pdo->query("SELECT start_datetime FROM reservations LIMIT 1");
            $hasNewColumns = true;
        } catch (PDOException $e) {
            // Old structure without start_datetime/end_datetime
        }
        
        if ($hasNewColumns) {
            $sql = "
                INSERT INTO reservations 
                (customer_name, phone, email, party_size, reservation_date, reservation_time, 
                 table_number, status, notes, start_datetime, end_datetime, created_by, source, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ";
            $params = [
                $data['customer_name'],
                $data['phone'], 
                $data['email'] ?? null,
                $data['party_size'],
                $data['reservation_date'],
                $data['reservation_time'],
                $data['table_number'] ?? null,
                $data['status'] ?? 'pending',
                $data['notes'] ?? null,
                $startDatetime->format('Y-m-d H:i:s'),
                $endDatetime->format('Y-m-d H:i:s'),
                $_SESSION['order_user'] ?? null,
                'web'
            ];
        } else {
            // Fallback to old table structure
            $sql = "
                INSERT INTO reservations 
                (customer_name, phone, email, party_size, reservation_date, reservation_time, 
                 table_number, status, notes, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ";
            $params = [
                $data['customer_name'],
                $data['phone'], 
                $data['email'] ?? null,
                $data['party_size'],
                $data['reservation_date'],
                $data['reservation_time'],
                $data['table_number'] ?? null,
                $data['status'] ?? 'pending',
                $data['notes'] ?? null
            ];
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $reservationId = $pdo->lastInsertId();
        
        // Trigger dough recalculation if this reservation is for today
        try {
            triggerDoughRecalcIfToday($data['reservation_date']);
        } catch (Throwable $e) {
            error_log("Trigger recalc after createReservation failed: " . $e->getMessage());
        }
        
        return ['ok' => true, 'id' => $reservationId];
        
    } catch (Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Aktualizuje stav rezervace
 * @param int $id Reservation ID
 * @param string $status New status
 * @return array Result with success/error
 */
function updateStatus($id, $status) {
    try {
        $pdo = getReservationDb();
        
        $allowedStatuses = ['pending', 'confirmed', 'seated', 'finished', 'cancelled', 'no_show'];
        if (!in_array($status, $allowedStatuses)) {
            return ['ok' => false, 'error' => 'Neplatný stav rezervace'];
        }
        
        // Get reservation date for recalculation trigger
        $stmt = $pdo->prepare("SELECT reservation_date FROM reservations WHERE id = ?");
        $stmt->execute([$id]);
        $reservationDate = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("UPDATE reservations SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $id]);
        
        if ($stmt->rowCount() === 0) {
            return ['ok' => false, 'error' => 'Rezervace nenalezena'];
        }
        
        // Trigger dough recalculation if this reservation is for today
        if ($reservationDate) {
            try {
                triggerDoughRecalcIfToday($reservationDate);
            } catch (Throwable $e) {
                error_log("Trigger recalc after updateStatus failed: " . $e->getMessage());
            }
        }
        
        return ['ok' => true];
        
    } catch (Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Posadí hosty (změna na seated + aktualizace restaurant_tables)
 * @param int $id Reservation ID
 * @return array Result with success/error
 */
function seatReservation($id) {
    try {
        $pdo = getReservationDb();
        $pdo->beginTransaction();
        
        // Get reservation details
        $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ?");
        $stmt->execute([$id]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reservation) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['ok' => false, 'error' => 'Rezervace nenalezena'];
        }
        
        // Check if reservation can be seated
        if (!in_array($reservation['status'], ['pending', 'confirmed'])) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['ok' => false, 'error' => 'Rezervaci nelze posadit ze stavu: ' . $reservation['status']];
        }
        
        // Update reservation status to seated
        $stmt = $pdo->prepare("UPDATE reservations SET status = 'seated', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        
        // Update restaurant_tables if table is assigned and exists
        if ($reservation['table_number']) {
            // Check if table exists and is free
            $stmt = $pdo->prepare("SELECT status FROM restaurant_tables WHERE table_number = ?");
            $stmt->execute([$reservation['table_number']]);
            $table = $stmt->fetch();
            
            if ($table && $table['status'] === 'free') {
                // Update table status to occupied
                $stmt = $pdo->prepare("UPDATE restaurant_tables SET status = 'occupied', session_start = NOW() WHERE table_number = ?");
                $stmt->execute([$reservation['table_number']]);
                
                // Create active table session if none exists
                $stmt = $pdo->prepare("SELECT id FROM table_sessions WHERE table_number = ? AND is_active = 1");
                $stmt->execute([$reservation['table_number']]);
                if (!$stmt->fetch()) {
                    $stmt = $pdo->prepare("INSERT INTO table_sessions (table_number, start_time, is_active) VALUES (?, NOW(), 1)");
                    $stmt->execute([$reservation['table_number']]);
                }
            }
        }
        
        $pdo->commit();
        
        error_log("Seated reservation $id for table {$reservation['table_number']}");
        
        // Trigger dough recalculation if this reservation is for today
        try {
            triggerDoughRecalcIfToday($reservation['reservation_date']);
        } catch (Throwable $e) {
            error_log("Trigger recalc after seatReservation failed: " . $e->getMessage());
        }
        
        return ['ok' => true];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Dokončí rezervaci (seated -> finished + aktualizace stolů)
 * @param int $id Reservation ID  
 * @return array Result with success/error
 */
function finishReservation($id) {
    try {
        $pdo = getReservationDb();
        $pdo->beginTransaction();
        
        // Get reservation details
        $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ?");
        $stmt->execute([$id]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reservation) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['ok' => false, 'error' => 'Rezervace nenalezena'];
        }
        
        // Check if reservation can be finished
        if ($reservation['status'] !== 'seated') {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['ok' => false, 'error' => 'Dokončit lze pouze rezervaci ve stavu "seated"'];
        }
        
        // Update reservation status to finished
        $stmt = $pdo->prepare("UPDATE reservations SET status = 'finished', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        
        // Release table and session if table is assigned
        if ($reservation['table_number']) {
            releaseTableAndSession($pdo, $reservation['table_number'], true); // true = set to 'to_clean'
        }
        
        $pdo->commit();
        
        error_log("Finished reservation $id, table {$reservation['table_number']} set to 'to_clean'");
        
        // Trigger dough recalculation if this reservation is for today
        try {
            triggerDoughRecalcIfToday($reservation['reservation_date']);
        } catch (Throwable $e) {
            error_log("Trigger recalc after finishReservation failed: " . $e->getMessage());
        }
        
        return ['ok' => true];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Zruší rezervaci (any -> cancelled)
 * @param int $id Reservation ID
 * @return array Result with success/error  
 */
function cancelReservation($id) {
    try {
        $pdo = getReservationDb();
        $pdo->beginTransaction();
        
        // Get full reservation details 
        $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ?");
        $stmt->execute([$id]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reservation) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['ok' => false, 'error' => 'Rezervace nenalezena'];
        }
        
        // Check if reservation can be cancelled (not already finished)  
        if (in_array($reservation['status'], ['finished', 'cancelled', 'no_show'])) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['ok' => false, 'error' => 'Rezervaci nelze zrušit ze stavu: ' . $reservation['status']];
        }
        
        // Update reservation status to cancelled
        $stmt = $pdo->prepare("UPDATE reservations SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        
        // Release table and session if reservation was seated or has active session
        if ($reservation['table_number']) {
            if ($reservation['status'] === 'seated') {
                // Release seated reservation - set table to 'to_clean' 
                releaseTableAndSession($pdo, $reservation['table_number'], true);
                error_log("Cancelled seated reservation $id, table {$reservation['table_number']} set to 'to_clean'");
            } else {
                // Check if there's an active session without active orders
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as active_orders 
                    FROM table_sessions ts
                    LEFT JOIN orders o ON ts.table_number = o.table_number AND o.status NOT IN ('completed', 'cancelled')
                    WHERE ts.table_number = ? AND ts.is_active = 1
                ");
                $stmt->execute([$reservation['table_number']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result && $result['active_orders'] == 0) {
                    // No active orders, safe to release table
                    releaseTableAndSession($pdo, $reservation['table_number'], false); // false = set to 'free'
                    error_log("Cancelled reservation $id, table {$reservation['table_number']} released (no active orders)");
                }
            }
        }
        
        $pdo->commit();
        
        error_log("Cancelled reservation $id (was {$reservation['status']})");
        
        // Trigger dough recalculation if this reservation is for today
        try {
            triggerDoughRecalcIfToday($reservation['reservation_date']);
        } catch (Throwable $e) {
            error_log("Trigger recalc after cancelReservation failed: " . $e->getMessage());
        }
        
        return ['ok' => true];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Načte rezervace podle filtru
 * @param array $filters Filters (date, status, table_number)
 * @return array Reservations array
 */
function getReservations($filters = []) {
    try {
        $pdo = getReservationDb();
        
        $sql = "SELECT * FROM reservations WHERE 1=1";
        $params = [];
        
        if (!empty($filters['date'])) {
            $sql .= " AND reservation_date = ?";
            $params[] = $filters['date'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['table_number'])) {
            $sql .= " AND table_number = ?";
            $params[] = $filters['table_number'];
        }
        
        $sql .= " ORDER BY reservation_date, reservation_time";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        throw new Exception('Chyba při načítání rezervací: ' . $e->getMessage());
    }
}

/**
 * Create opening hours table if it doesn't exist
 * @param PDO $pdo Database connection
 */
function createOpeningHoursTableIfNotExists($pdo) {
    try {
        $sql = "
            CREATE TABLE IF NOT EXISTS reservation_opening_hours (
                date DATE PRIMARY KEY,
                open_time TIME NOT NULL,
                close_time TIME NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $pdo->exec($sql);
    } catch (PDOException $e) {
        // Silently ignore if table creation fails due to permissions
        error_log("Could not create reservation_opening_hours table: " . $e->getMessage());
    }
}

/**
 * Get opening hours for a specific date
 * @param PDO $pdo Database connection
 * @param string $date Date in YYYY-MM-DD format
 * @return array Array with open_time and close_time
 */
function getOpeningHours($pdo, $date) {
    try {
        // Try to create table if it doesn't exist
        createOpeningHoursTableIfNotExists($pdo);
        
        $stmt = $pdo->prepare("SELECT open_time, close_time FROM reservation_opening_hours WHERE date = ?");
        $stmt->execute([$date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return [
                'open_time' => $result['open_time'],
                'close_time' => $result['close_time']
            ];
        } else {
            // Return defaults if no specific hours set
            return [
                'open_time' => '10:00',
                'close_time' => '23:00'
            ];
        }
    } catch (Exception $e) {
        error_log("Error getting opening hours: " . $e->getMessage());
        // Return defaults on error
        return [
            'open_time' => '10:00',
            'close_time' => '23:00'
        ];
    }
}

/**
 * Set opening hours for a specific date
 * @param PDO $pdo Database connection
 * @param string $date Date in YYYY-MM-DD format
 * @param string $openTime Opening time in HH:MM format
 * @param string $closeTime Closing time in HH:MM format
 * @return array Result array with ok status
 */
function setOpeningHours($pdo, $date, $openTime, $closeTime) {
    try {
        // Try to create table if it doesn't exist
        createOpeningHoursTableIfNotExists($pdo);
        
        $sql = "
            INSERT INTO reservation_opening_hours (date, open_time, close_time) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                open_time = VALUES(open_time),
                close_time = VALUES(close_time),
                updated_at = CURRENT_TIMESTAMP
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$date, $openTime, $closeTime]);
        
        return ['ok' => true];
        
    } catch (Exception $e) {
        error_log("Error setting opening hours: " . $e->getMessage());
        return ['ok' => false, 'error' => 'Chyba při ukládání otevírací doby: ' . $e->getMessage()];
    }
}

/**
 * Validate if reservation time is within opening hours
 * @param string $startTime Start time in HH:MM format
 * @param string $openTime Opening time in HH:MM format  
 * @param string $closeTime Closing time in HH:MM format
 * @return bool True if within opening hours
 */
function validateWithinOpeningHours($startTime, $openTime, $closeTime) {
    $start = strtotime($startTime);
    $open = strtotime($openTime);
    $close = strtotime($closeTime);
    
    // Reservation must start at or after opening time
    if ($start < $open) {
        return false;
    }
    
    // Reservation end (start + 2 hours) must be at or before closing time
    $end = $start + (2 * 3600); // Add 2 hours
    if ($end > $close) {
        return false;
    }
    
    return true;
}

/**
 * Get seated reservation for a table at the current time
 * @param int $tableNumber Table number
 * @param string|null $now Current datetime (default: now)
 * @return array|null Reservation data or null if not found
 */
function getSeatedReservationForTable($tableNumber, $now = null) {
    try {
        $pdo = getReservationDb();
        
        if ($now === null) {
            $now = date('Y-m-d H:i:s');
        }
        
        // Find seated reservation for this table that is currently active
        $stmt = $pdo->prepare("
            SELECT * FROM reservations 
            WHERE table_number = ? 
            AND status = 'seated'
            AND reservation_date = DATE(?)
        ");
        $stmt->execute([$tableNumber, $now]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        
    } catch (Exception $e) {
        error_log("Error finding seated reservation for table $tableNumber: " . $e->getMessage());
        return null;
    }
}

/**
 * Centralized helper to release table and session
 * @param PDO $pdo Database connection
 * @param int $tableNumber Table number to release
 * @param bool $setToClean Set table status to 'to_clean' instead of 'free'
 * @return bool Success status
 */
function releaseTableAndSession($pdo, $tableNumber, $setToClean = false) {
    try {
        error_log("Releasing table $tableNumber, setToClean=" . ($setToClean ? 'true' : 'false'));
        
        // Close active table session
        $stmt = $pdo->prepare("UPDATE table_sessions SET is_active = 0, end_time = NOW() WHERE table_number = ? AND is_active = 1");
        $stmt->execute([$tableNumber]);
        
        // Set table status
        $newStatus = $setToClean ? 'to_clean' : 'free';
        $stmt = $pdo->prepare("UPDATE restaurant_tables SET status = ?, session_start = NULL WHERE table_number = ?");
        $stmt->execute([$newStatus, $tableNumber]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error releasing table $tableNumber: " . $e->getMessage());
        return false;
    }
}

/**
 * Update reservation time/date with proper validation
 * @param int $id Reservation ID
 * @param string $newDate New date in Y-m-d format
 * @param string $newTime New time in H:i format
 * @param int|null $newTableNumber Optional new table number
 * @return array Result with ok status and details
 */
function updateReservation($id, $data) {
    try {
        $pdo = getReservationDb();

        // Načti původní
        $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ?");
        $stmt->execute([$id]);
        $orig = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$orig) {
            return ['ok' => false, 'error' => 'Rezervace nenalezena'];
        }

        $newDate = $data['reservation_date'] ?? $orig['reservation_date'];
        $newTime = $data['reservation_time'] ?? $orig['reservation_time'];

        if (!preg_match('/^\d{2}:\d{2}$/', $newTime)) {
            return ['ok' => false, 'error' => 'Neplatný formát času'];
        }
        $m = (int)substr($newTime, 3, 2);
        if ($m !== 0 && $m !== 30) {
            return ['ok' => false, 'error' => 'Rezervace je možná pouze na celé a půl hodiny'];
        }

        // Otevírací doba
        $opening = getOpeningHours($pdo, $newDate);
        if (!validateWithinOpeningHours($newTime, $opening['open_time'], $opening['close_time'])) {
            return ['ok' => false, 'error' => 'Mimo otevírací dobu ('.$opening['open_time'].'-'.$opening['close_time'].')'];
        }

        $start = new DateTime($newDate.' '.$newTime);
        $end   = clone $start;
        $end->add(new DateInterval('PT2H'));

        // Nový stůl (NULL je odstranění)
        $newTable = array_key_exists('table_number', $data)
            ? ($data['table_number'] === '' ? null : $data['table_number'])
            : $orig['table_number'];

        // Kolize
        if ($newTable) {
            $collision = findCollision($pdo, $start, $end, $newTable, $id);
            if ($collision) {
                return ['ok' => false, 'error' => 'Kolize se stávající rezervací ID '.$collision['id'].' na stole '.$newTable];
            }
        }

        $finalStatus = $orig['status'];
        if (array_key_exists('status', $data) && $data['status'] !== $orig['status']) {
            $allowed = ['pending','confirmed','seated','finished','cancelled','no_show'];
            if (!in_array($data['status'], $allowed)) {
                return ['ok' => false, 'error' => 'Neplatný status'];
            }
            $finalStatus = $data['status'];
        }

        // Zjištění nezměněnosti
        $noChange =
            $newDate === $orig['reservation_date'] &&
            $newTime === $orig['reservation_time'] &&
            $newTable == $orig['table_number'] &&
            (!isset($data['party_size']) || $data['party_size'] == $orig['party_size']) &&
            (!isset($data['customer_name']) || $data['customer_name'] == $orig['customer_name']) &&
            (!isset($data['phone']) || $data['phone'] == $orig['phone']) &&
            (!isset($data['email']) || $data['email'] == $orig['email']) &&
            (!isset($data['notes']) || $data['notes'] == $orig['notes']) &&
            $finalStatus === $orig['status'];

        if ($noChange) {
            return [
                'ok' => true,
                'unchanged' => true,
                'reservation' => [
                    'id' => $id,
                    'reservation_date' => $newDate,
                    'reservation_time' => $newTime,
                    'table_number' => $newTable,
                    'status' => $finalStatus,
                    'party_size' => $orig['party_size']
                ]
            ];
        }

        // Sestavení SET
        $fields = [];
        $params = [];

        $set = function($col, $val) use (&$fields, &$params) {
            $fields[] = "$col = ?";
            $params[] = ($val === '') ? null : $val;
        };

        $set('reservation_date', $newDate);
        $set('reservation_time', $newTime);
        $set('start_datetime', $start->format('Y-m-d H:i:s'));
        $set('end_datetime', $end->format('Y-m-d H:i:s'));
        $set('table_number', $newTable);

        foreach (['customer_name','phone','email','party_size','notes'] as $f) {
            if (array_key_exists($f, $data)) {
                $set($f, $data[$f]);
            }
        }
        if ($finalStatus !== $orig['status']) {
            $set('status', $finalStatus);
        }
        $fields[] = "updated_at = NOW()";

        $sql = "UPDATE reservations SET ".implode(', ', $fields)." WHERE id = ?";
        $params[] = $id;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Trigger recalculation logic - more comprehensive checking
        $wasAllocating = in_array($orig['status'], ['confirmed','seated']);
        $isAllocating = in_array($finalStatus, ['confirmed','seated']);
        $isToday = ($newDate === date('Y-m-d'));
        $isOriginalToday = ($orig['reservation_date'] === date('Y-m-d'));
        $partySizeChanged = isset($data['party_size']) && $data['party_size'] != $orig['party_size'];
        
        // Trigger recalculation if:
        // 1. Status changed from/to allocating states and involves today
        // 2. Party size changed for today's allocating reservation
        // 3. Date moved from today to another day (or vice versa) for allocating reservation
        $shouldRecalc = false;
        
        if ($isToday && ($wasAllocating !== $isAllocating)) {
            $shouldRecalc = true; // Status change affecting today's allocation
        } elseif ($isOriginalToday && ($orig['reservation_date'] !== $newDate) && $wasAllocating) {
            $shouldRecalc = true; // Moved away from today while allocating
        } elseif ($isToday && $isAllocating && $partySizeChanged) {
            $shouldRecalc = true; // Party size changed for today's allocating reservation
        }
        
        if ($shouldRecalc) {
            try { 
                triggerDoughRecalcIfToday($isToday ? $newDate : $orig['reservation_date']); 
            } catch (Throwable $e) {
                error_log("[updateReservation] recalc error: ".$e->getMessage());
            }
        }

        return [
            'ok' => true,
            'reservation' => [
                'id' => $id,
                'reservation_date' => $newDate,
                'reservation_time' => $newTime,
                'table_number' => $newTable,
                'status' => $finalStatus
            ]
        ];
    } catch (Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Zachováme updateReservationTime jako wrapper kvůli případným starším voláním
 */
function updateReservationTime($id, $newDate, $newTime, $newTableNumber = null) {
    $data = [
        'reservation_date' => $newDate,
        'reservation_time' => $newTime
    ];
    if ($newTableNumber !== null) {
        $data['table_number'] = $newTableNumber;
    }
    return updateReservation($id, $data);
}

/**
 * Trigger dough recalculation if the reservation date is today
 */
function triggerDoughRecalcIfToday($reservationDate) {
    require_once __DIR__ . '/dough_auto.php';
    return recalcTodayIf($reservationDate);
}