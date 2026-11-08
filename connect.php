<?php

// Keep PHP's clock on the same timezone as the MySQL server so relative
// notification times ("x minutes ago") are accurate instead of being offset
// by the host machine's timezone.
date_default_timezone_set('Asia/Manila');

$host="127.0.0.1";
$port="3306";
$user="root";
$pass="";
$db="login";
$conn=new mysqli($host,$user,$pass,$db,$port);
if($conn->connect_error){
    echo "Failed to connect DB".$conn->connect_error;
} else {
    // Pin the session timezone too, so TIMESTAMP columns and NOW() always
    // agree with PHP regardless of the server's SYSTEM timezone setting.
    @$conn->query("SET time_zone = '+08:00'");
}

/**
 * Attachments are stored in the database (vehicle_requests.attachment_data LONGBLOB)
 * and are read back through view_attachment.php. The previous cleanupOldUploads()
 * sweep that deleted faculty_uploads files and NULLed attachment_name after 7 days
 * was removed with the move to database storage - leaving it in place would have
 * silently destroyed attachments a week after they were submitted.
 */

/**
 * Per-year Trip Ticket numbering.
 *
 * vehicle_requests.id stays the primary key and keeps driving every internal
 * operation (approve, print, status, notifications). It is NOT the ticket number.
 * ticket_no is display-only: a per-travel-year sequence that restarts at 1 each
 * January, so the first request of 2027 is 001.
 *
 * Idempotent and throttled: runs at most once a day via a marker file, and every
 * step checks its own precondition, so it is safe to call on every page load.
 */
function ensureTicketNumbering($conn) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $marker = __DIR__ . '/.ticket_migration';
    $today = date('Y-m-d');
    if (file_exists($marker) && @file_get_contents($marker) === $today) {
        return;
    }
    @touch($marker);

    // 1. Add the columns if they are missing.
    $cols = [];
    $r = $conn->query("SHOW COLUMNS FROM vehicle_requests");
    if ($r) {
        while ($c = $r->fetch_assoc()) {
            $cols[] = $c['Field'];
        }
    }
    if (!in_array('ticket_no', $cols, true)) {
        $conn->query("ALTER TABLE vehicle_requests
                      ADD COLUMN ticket_no INT NULL DEFAULT NULL AFTER id");
    }
    if (!in_array('ticket_year', $cols, true)) {
        $conn->query("ALTER TABLE vehicle_requests
                      ADD COLUMN ticket_year SMALLINT NULL DEFAULT NULL AFTER ticket_no");
    }

    // 2. Backfill any row that still has no ticket (rows written by an older build,
    //    or by a code path that skipped allocation). Number them AFTER the highest
    //    ticket already used in that travel year.
    //    The previous version restarted at 1 for each year using ROW_NUMBER() over
    //    only the NULL rows, which collided with uniq_ticket (ticket_year, ticket_no)
    //    whenever that year already had numbered rows. query() returns false on such
    //    an error and the result was never checked, so the row stayed NULL forever
    //    and the Trip Ticket rendered as "---". Assign per row so affected_rows tells
    //    us whether the number actually landed.
    $left = $conn->query("SELECT id, travel_date FROM vehicle_requests
                          WHERE ticket_no IS NULL AND travel_date IS NOT NULL
                          ORDER BY travel_date ASC, id ASC");
    if ($left) {
        $nextByYear = [];
        while ($row = $left->fetch_assoc()) {
            $y = (int)date('Y', strtotime($row['travel_date']));
            if (!isset($nextByYear[$y])) {
                $q = $conn->prepare("SELECT COALESCE(MAX(ticket_no), 0) + 1 AS n
                                     FROM vehicle_requests WHERE ticket_year = ?");
                $q->bind_param("i", $y);
                $q->execute();
                $nextByYear[$y] = (int)($q->get_result()->fetch_assoc()['n'] ?? 1);
                $q->close();
            }
            $assign = $conn->prepare("UPDATE vehicle_requests
                                      SET ticket_no = ?, ticket_year = ?
                                      WHERE id = ? AND ticket_no IS NULL");
            $assign->bind_param("iii", $nextByYear[$y], $y, $row['id']);
            $assign->execute();
            if ($assign->affected_rows > 0) {
                $nextByYear[$y]++;
            } else {
                error_log("ensureTicketNumbering: could not assign a ticket to vehicle_requests.id={$row['id']} ({$assign->error})");
            }
            $assign->close();
        }
    }

    // A row with no travel_date can never be numbered (there is no year to group by).
    // Requests require a travel date, so this should always be zero; log it if not.
    $orphans = $conn->query("SELECT COUNT(*) c FROM vehicle_requests WHERE ticket_no IS NULL");
    if ($orphans) {
        $stillNull = (int)$orphans->fetch_assoc()['c'];
        if ($stillNull > 0) {
            error_log("ensureTicketNumbering: $stillNull vehicle_requests row(s) still have no ticket");
        }
    }

    // 3. Enforce uniqueness so a duplicate ticket number can never be saved.
    $r = $conn->query("SHOW INDEX FROM vehicle_requests WHERE Key_name = 'uniq_ticket'");
    if ($r && $r->num_rows === 0) {
        $conn->query("ALTER TABLE vehicle_requests
                      ADD UNIQUE KEY uniq_ticket (ticket_year, ticket_no)");
    }
}

ensureTicketNumbering($conn);

/**
 * Driver assigned on the travel order form.
 *
 * The officer picks a driver in travel_order_form.php and that choice is saved
 * here when they approve/print. The approved-request summary reads this column
 * for its DRIVER field.
 *
 * Idempotent: one SHOW COLUMNS check per request, so it is safe on every load.
 * It lives here because every read path already requires connect.php.
 */
function ensureDriverColumn($conn) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $col = $conn->query("SHOW COLUMNS FROM vehicle_requests LIKE 'driver'");
    if ($col && $col->num_rows === 0) {
        if (!$conn->query("ALTER TABLE vehicle_requests
                           ADD COLUMN driver VARCHAR(100) NULL DEFAULT NULL AFTER passengers")) {
            error_log("ensureDriverColumn: could not add vehicle_requests.driver (" . $conn->error . ")");
        }
    }
}

ensureDriverColumn($conn);

/**
 * Passenger names are entered as one free-text line and can hold more than the
 * five names the old VARCHAR(50) column assumed. With MySQL not running in
 * strict mode, anything longer than the column width was silently truncated on
 * insert, so the Planning Officer list and the printed Travel Order showed a
 * cut-off list. Widen the column to TEXT once and leave it alone afterwards.
 *
 * Idempotent: one SHOW COLUMNS check per request, so it is safe on every load.
 */
function ensurePassengerColumn($conn) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $col = $conn->query("SHOW COLUMNS FROM vehicle_requests LIKE 'passengers'");
    if (!$col || !($row = $col->fetch_assoc())) {
        return;
    }

    $type = strtolower((string)($row['Type'] ?? ''));
    $alreadyWide = strpos($type, 'text') !== false || strpos($type, 'blob') !== false;
    if ($alreadyWide) {
        return;
    }

    if (!$conn->query("ALTER TABLE vehicle_requests MODIFY passengers TEXT NOT NULL")) {
        error_log("ensurePassengerColumn: could not widen vehicle_requests.passengers (" . $conn->error . ")");
    }
}

ensurePassengerColumn($conn);
?>