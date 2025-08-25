<?php
/**
 * reservations_lib.php
 *
 * Logika rezervací s podporou variabilní délky (manual_duration_minutes).
 * Když manual_duration_minutes je NULL, chová se jako původní 2h blok.
 */

/////////////////////////////
// DB CONNECTION
/////////////////////////////
function getReservationDb() {
    static $pdo = null;
    if ($pdo) return $pdo;
    try {
        $pdo = new PDO(
            'mysql:host=127.0.0.1;dbname=pizza_orders;charset=utf8mb4',
            'pizza_user',
            'pizza',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        $pdo->exec("SET NAMES utf8mb4");
        $pdo->exec("SET CHARACTER SET utf8mb4");
    } catch (PDOException $e) {
        throw new Exception('Database connection error: ' . $e->getMessage());
    }
    return $pdo;
}

/////////////////////////////
// HELPERS
/////////////////////////////

/**
 * Kolize (časové překrytí) – vrací první kolidující rezervaci nebo false.
 */
function findCollision(PDO $pdo, DateTime $start, DateTime $end, int $tableNumber, ?int $excludeId = null) {
    $sql = "
        SELECT *
        FROM reservations
        WHERE table_number = ?
          AND status NOT IN ('cancelled','no_show')
          AND (
                (start_datetime < ? AND end_datetime > ?)
             OR (start_datetime < ? AND end_datetime > ?)
          )
    ";
    $params = [
        $tableNumber,
        $end->format('Y-m-d H:i:s'),
        $start->format('Y-m-d H:i:s'),
        $start->format('Y-m-d H:i:s'),
        $end->format('Y-m-d H:i:s')
    ];
    if ($excludeId) {
        $sql .= " AND id != ?";
        $params[] = $excludeId;
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row ?: false;
}

/**
 * Ověří, že čas HH:MM je uvnitř otevírací doby.
 */
function validateWithinOpeningHours(string $time, string $open, string $close): bool {
    return ($time >= $open && $time <= $close);
}

/**
 * Získá bezpečně manuální délku (minuty) nebo null.
 */
function extractManualDuration($value, int $min=15, int $max=360): ?int {
    if ($value === '' || $value === null) return null;
    if (!is_numeric($value)) return null;
    $v = (int)$value;
    if ($v < $min || $v > $max) return null;
    return $v;
}

/////////////////////////////
// OPENING HOURS
/////////////////////////////

function createOpeningHoursTableIfNotExists(PDO $pdo) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS reservation_opening_hours (
                date DATE PRIMARY KEY,
                open_time TIME NOT NULL,
                close_time TIME NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (Throwable $e) {
        error_log('Cannot create reservation_opening_hours: '.$e->getMessage());
    }
}

function getOpeningHours(PDO $pdo, string $date): array {
    try {
        createOpeningHoursTableIfNotExists($pdo);
        $st = $pdo->prepare("SELECT open_time, close_time FROM reservation_opening_hours WHERE date=?");
        $st->execute([$date]);
        $row = $st->fetch();
        if ($row) return $row;
        // Default fallback (u tebe se pak přemapuje na 16–22)
        return ['open_time'=>'10:00','close_time'=>'23:00'];
    } catch (Throwable $e) {
        error_log('getOpeningHours error: '.$e->getMessage());
        return ['open_time'=>'10:00','close_time'=>'23:00'];
    }
}

function setOpeningHours(PDO $pdo, string $date, string $open, string $close): array {
    try {
        createOpeningHoursTableIfNotExists($pdo);
        $st = $pdo->prepare("
            INSERT INTO reservation_opening_hours (date, open_time, close_time)
            VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE open_time=VALUES(open_time), close_time=VALUES(close_time), updated_at=CURRENT_TIMESTAMP
        ");
        $st->execute([$date,$open,$close]);
        return ['ok'=>true];
    } catch (Throwable $e) {
        return ['ok'=>false,'error'=>$e->getMessage()];
    }
}

/////////////////////////////
// CORE CRUD
/////////////////////////////

/**
 * Vytvoření rezervace (variabilní délka).
 */
function createReservation(array $data): array {
    try {
        $pdo = getReservationDb();

        $required = ['customer_name','phone','party_size','reservation_date','reservation_time'];
        foreach ($required as $f) {
            if (empty($data[$f])) {
                return ['ok'=>false,'error'=>"Pole '$f' je povinné"];
            }
        }

        $time = $data['reservation_time'];
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            return ['ok'=>false,'error'=>'Neplatný formát času'];
        }
        // omezení jen na 00 nebo 30 (dle stávající logiky)
        $m = (int)explode(':', $time)[1];
        if (!in_array($m, [0,30], true)) {
            return ['ok'=>false,'error'=>'Rezervace je možná pouze na celé a půl hodiny'];
        }

        $opening = getOpeningHours($pdo, $data['reservation_date']);
        if (!validateWithinOpeningHours($time, $opening['open_time'], $opening['close_time'])) {
            return ['ok'=>false,'error'=>'Mimo otevírací dobu ('.$opening['open_time'].' - '.$opening['close_time'].')'];
        }

        $manualDuration = extractManualDuration($data['manual_duration_minutes'] ?? null);
        $start = new DateTime($data['reservation_date'].' '.$time);
        $end   = clone $start;
        if ($manualDuration !== null) {
            $end->modify("+{$manualDuration} minutes");
        } else {
            $end->modify("+120 minutes"); // default 2h
        }

        $tableNumber = !empty($data['table_number']) ? (int)$data['table_number'] : null;
        if ($tableNumber) {
            $collision = findCollision($pdo, $start, $end, $tableNumber);
            if ($collision) {
                return ['ok'=>false,'error'=>"Kolize se stávající rezervací (ID {$collision['id']})"];
            }
        }

        // Ověření přítomnosti sloupců (start_datetime, end_datetime, manual_duration_minutes)
        $hasExtendedCols = false;
        try {
            $pdo->query("SELECT start_datetime, manual_duration_minutes FROM reservations LIMIT 1");
            $hasExtendedCols = true;
        } catch (Throwable $e) {}

        if ($hasExtendedCols) {
            $st = $pdo->prepare("
                INSERT INTO reservations
                (customer_name, phone, email, party_size,
                 reservation_date, reservation_time,
                 table_number, status, notes,
                 start_datetime, end_datetime, manual_duration_minutes,
                 created_at, updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())
            ");
            $st->execute([
                $data['customer_name'],
                $data['phone'],
                $data['email'] ?? null,
                (int)$data['party_size'],
                $data['reservation_date'],
                $time,
                $tableNumber,
                $data['status'] ?? 'pending',
                $data['notes'] ?? null,
                $start->format('Y-m-d H:i:s'),
                $end->format('Y-m-d H:i:s'),
                $manualDuration
            ]);
        } else {
            // Fallback – starší struktura
            $st = $pdo->prepare("
                INSERT INTO reservations
                (customer_name, phone, email, party_size,
                 reservation_date, reservation_time,
                 table_number, status, notes,
                 created_at, updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())
            ");
            $st->execute([
                $data['customer_name'],
                $data['phone'],
                $data['email'] ?? null,
                (int)$data['party_size'],
                $data['reservation_date'],
                $time,
                $tableNumber,
                $data['status'] ?? 'pending',
                $data['notes'] ?? null
            ]);
        }

        return ['ok'=>true,'id'=>$pdo->lastInsertId()];
    } catch (Throwable $e) {
        return ['ok'=>false,'error'=>$e->getMessage()];
    }
}

/**
 * Update rezervace (mění čas, stůl, délku, stav atd.)
 */
function updateReservation(array $data): array {
    try {
        $pdo = getReservationDb();
        if (empty($data['id'])) return ['ok'=>false,'error'=>'Chybí ID'];

        $st = $pdo->prepare("SELECT * FROM reservations WHERE id=?");
        $st->execute([$data['id']]);
        $orig = $st->fetch();
        if (!$orig) return ['ok'=>false,'error'=>'Rezervace nenalezena'];

        $date = $data['reservation_date'] ?? $orig['reservation_date'];
        $time = $data['reservation_time'] ?? $orig['reservation_time'];

        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            return ['ok'=>false,'error'=>'Neplatný formát času'];
        }
        $m = (int)explode(':',$time)[1];
        if (!in_array($m,[0,30],true)) {
            return ['ok'=>false,'error'=>'Povoleny jsou pouze celé a půl hodiny'];
        }

        $opening = getOpeningHours($pdo, $date);
        if (!validateWithinOpeningHours($time, $opening['open_time'], $opening['close_time'])) {
            return ['ok'=>false,'error'=>'Mimo otevírací dobu'];
        }

        $manualDuration = array_key_exists('manual_duration_minutes', $data)
            ? extractManualDuration($data['manual_duration_minutes'])
            : (isset($orig['manual_duration_minutes']) ? extractManualDuration($orig['manual_duration_minutes']) : null);

        $start = new DateTime($date.' '.$time);
        $end   = clone $start;
        if ($manualDuration !== null) {
            $end->modify("+{$manualDuration} minutes");
        } else {
            $end->modify("+120 minutes");
        }

        $newTable = array_key_exists('table_number',$data)
            ? (!empty($data['table_number'])?(int)$data['table_number']:null)
            : $orig['table_number'];

        if ($newTable) {
            $collision = findCollision($pdo, $start, $end, $newTable, (int)$orig['id']);
            if ($collision) {
                return ['ok'=>false,'error'=>"Kolize s rezervací ID {$collision['id']}"];
            }
        }

        $newStatus = $data['status'] ?? $orig['status'];

        // Zjištění struktury tabulky
        $hasExtendedCols = false;
        try {
            $pdo->query("SELECT start_datetime, manual_duration_minutes FROM reservations LIMIT 1");
            $hasExtendedCols = true;
        } catch (Throwable $e) {}

        if ($hasExtendedCols) {
            $sql = "
                UPDATE reservations
                SET customer_name=?,
                    phone=?,
                    email=?,
                    party_size=?,
                    reservation_date=?,
                    reservation_time=?,
                    table_number=?,
                    status=?,
                    notes=?,
                    start_datetime=?,
                    end_datetime=?,
                    manual_duration_minutes=?,
                    updated_at=NOW()
                WHERE id=?
            ";
            $params = [
                $data['customer_name'] ?? $orig['customer_name'],
                $data['phone'] ?? $orig['phone'],
                $data['email'] ?? $orig['email'],
                $data['party_size'] ?? $orig['party_size'],
                $date,
                $time,
                $newTable,
                $newStatus,
                $data['notes'] ?? $orig['notes'],
                $start->format('Y-m-d H:i:s'),
                $end->format('Y-m-d H:i:s'),
                $manualDuration,
                $orig['id']
            ];
        } else {
            $sql = "
                UPDATE reservations
                SET customer_name=?,
                    phone=?,
                    email=?,
                    party_size=?,
                    reservation_date=?,
                    reservation_time=?,
                    table_number=?,
                    status=?,
                    notes=?,
                    updated_at=NOW()
                WHERE id=?
            ";
            $params = [
                $data['customer_name'] ?? $orig['customer_name'],
                $data['phone'] ?? $orig['phone'],
                $data['email'] ?? $orig['email'],
                $data['party_size'] ?? $orig['party_size'],
                $date,
                $time,
                $newTable,
                $newStatus,
                $data['notes'] ?? $orig['notes'],
                $orig['id']
            ];
        }

        $upd = $pdo->prepare($sql);
        $upd->execute($params);

        return ['ok'=>true];
    } catch (Throwable $e) {
        return ['ok'=>false,'error'=>$e->getMessage()];
    }
}

/**
 * Obecná změna stavu – vhodné pro confirm / no_show / ad hoc změny.
 */
function setReservationStatus(int $id, string $status): array {
    try {
        $pdo = getReservationDb();
        $allowed = ['pending','confirmed','seated','finished','cancelled','no_show'];
        if (!in_array($status, $allowed, true)) {
            return ['ok'=>false,'error'=>'Neplatný stav'];
        }
        $st = $pdo->prepare("SELECT reservation_date FROM reservations WHERE id=?");
        $st->execute([$id]);
        $date = $st->fetchColumn();
        if (!$date) return ['ok'=>false,'error'=>'Rezervace nenalezena'];

        $u = $pdo->prepare("UPDATE reservations SET status=?, updated_at=NOW() WHERE id=?");
        $u->execute([$status,$id]);

        return ['ok'=>true];
    } catch (Throwable $e) {
        return ['ok'=>false,'error'=>$e->getMessage()];
    }
}

/**
 * Zjednodušené posazení – můžeš případně doplnit další kroky (table_sessions).
 */
function seatReservation(int $id): array {
    try {
        $pdo = getReservationDb();
        $pdo->beginTransaction();
        $st = $pdo->prepare("SELECT * FROM reservations WHERE id=? FOR UPDATE");
        $st->execute([$id]);
        $res = $st->fetch();
        if (!$res) { $pdo->rollBack(); return ['ok'=>false,'error'=>'Rezervace nenalezena']; }
        if (!in_array($res['status'], ['pending','confirmed'])) {
            $pdo->rollBack(); return ['ok'=>false,'error'=>'Nelze posadit ze stavu '.$res['status']];
        }
        $up = $pdo->prepare("UPDATE reservations SET status='seated', updated_at=NOW() WHERE id=?");
        $up->execute([$id]);
        // Zde by šlo přidat vytvoření table_sessions pokud není.
        $pdo->commit();
        return ['ok'=>true];
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        return ['ok'=>false,'error'=>$e->getMessage()];
    }
}

/**
 * Dokončení – uzavření + případné uvolnění stolu.
 */
function finishReservation(int $id): array {
    try {
        $pdo = getReservationDb();
        $pdo->beginTransaction();
        $st = $pdo->prepare("SELECT * FROM reservations WHERE id=? FOR UPDATE");
        $st->execute([$id]);
        $res = $st->fetch();
        if (!$res) { $pdo->rollBack(); return ['ok'=>false,'error'=>'Nenalezeno']; }
        if ($res['status'] !== 'seated') {
            $pdo->rollBack(); return ['ok'=>false,'error'=>'Nelze dokončit ze stavu '.$res['status']];
        }
        $up = $pdo->prepare("UPDATE reservations SET status='finished', updated_at=NOW() WHERE id=?");
        $up->execute([$id]);

        if (!empty($res['table_number'])) {
            // Zavřít aktivní session + označit stůl jako to_clean
            $pdo->prepare("UPDATE table_sessions SET is_active=0, end_time=NOW()
                           WHERE table_number=? AND is_active=1")->execute([$res['table_number']]);
            $pdo->prepare("UPDATE restaurant_tables SET status='to_clean', session_start=NULL
                           WHERE table_number=?")->execute([$res['table_number']]);
        }

        $pdo->commit();
        return ['ok'=>true];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok'=>false,'error'=>$e->getMessage()];
    }
}

/**
 * Zrušení
 */
function cancelReservation(int $id): array {
    try {
        $pdo = getReservationDb();
        $st = $pdo->prepare("SELECT * FROM reservations WHERE id=?");
        $st->execute([$id]);
        $res = $st->fetch();
        if (!$res) return ['ok'=>false,'error'=>'Nenalezeno'];
        if (in_array($res['status'], ['finished','cancelled','no_show'], true)) {
            return ['ok'=>false,'error'=>'Nelze zrušit ze stavu '.$res['status']];
        }
        $pdo->prepare("UPDATE reservations SET status='cancelled', updated_at=NOW() WHERE id=?")->execute([$id]);
        if (!empty($res['table_number'])) {
            freeTableIfNoActive($pdo, (int)$res['table_number']);
        }
        return ['ok'=>true];
    } catch (Throwable $e) {
        return ['ok'=>false,'error'=>$e->getMessage()];
    }
}

/**
 * Výpis rezervací dle filtrů.
 */
function getReservations(array $filters = []): array {
    $pdo = getReservationDb();
    $sql = "SELECT * FROM reservations WHERE 1=1";
    $p = [];
    if (!empty($filters['date'])) {
        $sql .= " AND reservation_date = ?";
        $p[] = $filters['date'];
    }
    if (!empty($filters['status'])) {
        $sql .= " AND status = ?";
        $p[] = $filters['status'];
    }
    if (!empty($filters['table_number'])) {
        $sql .= " AND table_number = ?";
        $p[] = $filters['table_number'];
    }
    $sql .= " ORDER BY reservation_date, reservation_time";
    $st = $pdo->prepare($sql);
    $st->execute($p);
    return $st->fetchAll();
}

/////////////////////////////
// TABLE / SESSION HELPERS
/////////////////////////////

/**
 * Uvolní stůl pokud nemá aktivní session, aktivní objednávky a dnešní živou rezervaci.
 */
function freeTableIfNoActive(PDO $pdo, int $tableNumber, array $activeOrderStatuses = ['pending','preparing','in_progress','served'], bool $ignoreTodayReservations=false): bool {
    // Aktivní session?
    $q = $pdo->prepare("SELECT 1 FROM table_sessions WHERE table_number=? AND is_active=1 LIMIT 1");
    $q->execute([$tableNumber]);
    if ($q->fetch()) return false;

    // Aktivní objednávka?
    if ($activeOrderStatuses) {
        $in = "'" . implode("','", array_map('addslashes',$activeOrderStatuses)) . "'";
        $q = $pdo->prepare("
            SELECT 1
            FROM orders o
            JOIN table_sessions ts ON o.table_session_id = ts.id
            WHERE ts.table_number=? AND o.status IN ($in)
            LIMIT 1
        ");
        $q->execute([$tableNumber]);
        if ($q->fetch()) return false;
    }

    if (!$ignoreTodayReservations) {
        $q = $pdo->prepare("
            SELECT 1 FROM reservations
            WHERE table_number=?
              AND reservation_date = CURDATE()
              AND status NOT IN ('cancelled','finished','no_show')
            LIMIT 1
        ");
        $q->execute([$tableNumber]);
        if ($q->fetch()) return false;
    }

    $upd = $pdo->prepare("UPDATE restaurant_tables SET status='free', session_start=NULL
                          WHERE table_number=? AND status IN ('occupied','to_clean')");
    $upd->execute([$tableNumber]);
    return true;
}