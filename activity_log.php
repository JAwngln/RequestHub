<?php
/**
 * Faculty activity logging.
 *
 * Every meaningful faculty action (sign in, request submission, cancellation,
 * account change) is recorded here so the Planning Officer can review it from
 * the Activity Logs page. Logging must never break the action it is recording,
 * so all failures are swallowed after being written to the PHP error log.
 *
 * Retention policy: records older than ACTIVITY_LOG_RETENTION_DAYS (90 days by
 * default) are deleted automatically. Cleanup runs whenever a new action is
 * logged and whenever the Planning Officer opens the Activity Logs page, so no
 * external cron job is required. Override the window by defining the constant
 * before this file is included.
 */

if (!function_exists('ensure_activity_logs_table')) {
    /**
     * Create the activity_logs table on first use.
     * Idempotent and cached per request.
     */
    function ensure_activity_logs_table($conn)
    {
        static $done = false;
        if ($done || !($conn instanceof mysqli)) {
            return;
        }
        $done = true;

        $sql = "CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL DEFAULT NULL,
            user_email VARCHAR(100) NOT NULL,
            user_name VARCHAR(150) NOT NULL,
            role VARCHAR(30) NOT NULL DEFAULT 'faculty',
            action VARCHAR(50) NOT NULL,
            description VARCHAR(500) NOT NULL,
            target_type VARCHAR(50) NULL DEFAULT NULL,
            target_id INT NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_activity_created (created_at),
            INDEX idx_activity_user (user_email),
            INDEX idx_activity_action (action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!$conn->query($sql)) {
            error_log('ensure_activity_logs_table: ' . $conn->error);
        }
    }
}

if (!defined('ACTIVITY_LOG_RETENTION_DAYS')) {
    /**
     * How long activity records are kept before they are deleted automatically.
     * Define this constant in a config file loaded before activity_log.php to
     * override the 90-day default.
     */
    define('ACTIVITY_LOG_RETENTION_DAYS', 90);
}

if (!function_exists('activity_log_retention_days')) {
    /**
     * Retention window in days, always at least one day.
     */
    function activity_log_retention_days()
    {
        $days = (int)ACTIVITY_LOG_RETENTION_DAYS;
        return $days > 0 ? $days : 90;
    }
}

if (!function_exists('purge_expired_activity_logs')) {
    /**
     * Delete activity rows that fall outside the retention window.
     *
     * Runs at most once per request unless $force is true, so a request that
     * records several actions does not repeat the same delete. The created_at
     * index keeps the cleanup cheap.
     *
     * @param mysqli $conn
     * @param bool   $force
     * @return int Number of expired rows removed
     */
    function purge_expired_activity_logs($conn, $force = false)
    {
        static $alreadyPurged = false;

        if (!($conn instanceof mysqli)) {
            return 0;
        }
        if ($alreadyPurged && !$force) {
            return 0;
        }
        $alreadyPurged = true;

        ensure_activity_logs_table($conn);

        $days = activity_log_retention_days();
        $cutoff = (new DateTimeImmutable('now'))->modify('-' . $days . ' days')->format('Y-m-d H:i:s');

        $stmt = $conn->prepare("DELETE FROM activity_logs WHERE created_at < ?");
        if (!$stmt) {
            error_log('purge_expired_activity_logs prepare: ' . $conn->error);
            return 0;
        }

        $stmt->bind_param("s", $cutoff);
        $ok = $stmt->execute();
        $deleted = $ok ? max(0, (int)$stmt->affected_rows) : 0;
        if (!$ok) {
            error_log('purge_expired_activity_logs execute: ' . $stmt->error);
        }
        $stmt->close();

        return $deleted;
    }
}

if (!function_exists('log_activity')) {
    /**
     * Record one activity row.
     *
     * User details come from the session unless they are supplied in $options,
     * which lets registration record the account before a session exists.
     *
     * @param mysqli $conn
     * @param string $action      Short machine key, e.g. vehicle_request_submitted
     * @param string $description Human-readable sentence shown in the log page
     * @param array  $options     user_id, email, name, role, target_type, target_id
     * @return bool
     */
    function log_activity($conn, $action, $description, array $options = [])
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }

        ensure_activity_logs_table($conn);

        // Keep the table inside the retention window whenever a new action is
        // recorded, even if nobody opens the Activity Logs page.
        purge_expired_activity_logs($conn);

        $email = trim((string)($options['email'] ?? ($_SESSION['email'] ?? '')));
        if ($email === '') {
            return false;
        }

        $name = trim((string)($options['name'] ?? ''));
        if ($name === '') {
            $first = trim((string)($_SESSION['firstName'] ?? ''));
            $last = trim((string)($_SESSION['lastName'] ?? ''));
            $name = trim($first . ' ' . $last);
        }
        if ($name === '') {
            $name = $email;
        }

        $role = trim((string)($options['role'] ?? ($_SESSION['role'] ?? 'faculty')));
        if ($role === '') {
            $role = 'faculty';
        }

        $userId = isset($options['user_id']) && $options['user_id'] !== null
            ? (int)$options['user_id']
            : (int)($_SESSION['userId'] ?? 0);
        $userId = $userId > 0 ? $userId : null;

        $targetType = isset($options['target_type']) && $options['target_type'] !== ''
            ? (string)$options['target_type']
            : null;
        $targetId = isset($options['target_id']) && $options['target_id'] !== null
            ? (int)$options['target_id']
            : null;

        $description = trim((string)$description);
        if (function_exists('mb_substr')) {
            $description = mb_substr($description, 0, 500);
        } else {
            $description = substr($description, 0, 500);
        }

        $action = trim((string)$action);
        if ($action === '') {
            return false;
        }

        // Types: user_id(i), email(s), name(s), role(s), action(s), description(s),
        // target_type(s), target_id(i).
        $sql = "INSERT INTO activity_logs
                    (user_id, user_email, user_name, role, action, description, target_type, target_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log('log_activity prepare: ' . $conn->error);
            return false;
        }

        $stmt->bind_param(
            "issssssi",
            $userId,
            $email,
            $name,
            $role,
            $action,
            $description,
            $targetType,
            $targetId
        );

        $ok = $stmt->execute();
        if (!$ok) {
            error_log('log_activity execute: ' . $stmt->error);
        }
        $stmt->close();

        return (bool)$ok;
    }
}
