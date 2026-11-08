<?php
session_start();
if (!isset($_SESSION['email']) || trim($_SESSION['role']) !== 'planning_officer') {
    header("Location: ../../auth/login.php");
    exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/Prototype/connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Prototype/activity_log.php';

ensure_activity_logs_table($conn);

// Enforce the retention window before reading the list and statistics so the
// Planning Officer never sees records that should already have been removed.
purge_expired_activity_logs($conn, true);
$retentionDays = activity_log_retention_days();

$perPage = 20;
$pageNum = max(1, (int)($_GET['log_page'] ?? 1));
$search = trim((string)($_GET['q'] ?? ''));
$facultyFilter = trim((string)($_GET['faculty'] ?? ''));
$groupFilter = (string)($_GET['group'] ?? 'all');
$dateFilter = (string)($_GET['date'] ?? 'all');

$groupActions = [
    'login' => ['login', 'logout'],
    'vehicle' => ['vehicle_request_submitted', 'vehicle_request_cancelled'],
    'item' => ['item_request_submitted', 'item_request_cancelled'],
    'account' => ['register', 'account_updated', 'password_changed', 'account_deleted'],
];
if (!isset($groupActions[$groupFilter]) && $groupFilter !== 'all') {
    $groupFilter = 'all';
}
if (!in_array($dateFilter, ['all', 'today', '7', '30'], true)) {
    $dateFilter = 'all';
}

$where = ["role = 'faculty'"];

if ($search !== '') {
    // Escape LIKE wildcards so a literal % or _ searches for that character.
    $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
    $like = $conn->real_escape_string($like);
    $where[] = "(user_name LIKE '%{$like}%' OR user_email LIKE '%{$like}%' OR description LIKE '%{$like}%')";
}

if ($facultyFilter !== '') {
    $facultyEsc = $conn->real_escape_string($facultyFilter);
    $where[] = "user_email = '{$facultyEsc}'";
}

if ($groupFilter !== 'all') {
    $quotedActions = array_map(function ($action) use ($conn) {
        return "'" . $conn->real_escape_string($action) . "'";
    }, $groupActions[$groupFilter]);
    $where[] = 'action IN (' . implode(', ', $quotedActions) . ')';
}

if ($dateFilter === 'today') {
    $where[] = 'DATE(created_at) = CURDATE()';
} elseif ($dateFilter === '7') {
    $where[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
} elseif ($dateFilter === '30') {
    $where[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
}

$whereSql = implode(' AND ', $where);

$totalRows = 0;
$countResult = $conn->query("SELECT COUNT(*) AS c FROM activity_logs WHERE {$whereSql}");
if ($countResult) {
    $totalRows = (int)$countResult->fetch_assoc()['c'];
}

$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($pageNum > $totalPages) {
    $pageNum = $totalPages;
}
$offset = ($pageNum - 1) * $perPage;

$logs = [];
$logResult = $conn->query(
    "SELECT id, user_id, user_email, user_name, role, action, description, target_type, target_id, created_at
     FROM activity_logs
     WHERE {$whereSql}
     ORDER BY created_at DESC, id DESC
     LIMIT {$offset}, {$perPage}"
);
if ($logResult) {
    while ($row = $logResult->fetch_assoc()) {
        $logs[] = $row;
    }
}

// Overall statistics, independent of the active filters.
$stats = [
    'total' => 0,
    'today' => 0,
    'faculty' => 0,
    'last' => null,
];
$statsResult = $conn->query(
    "SELECT COUNT(*) AS total,
            COUNT(DISTINCT user_email) AS faculty_count,
            COALESCE(SUM(DATE(created_at) = CURDATE()), 0) AS today_count,
            MAX(created_at) AS last_activity
     FROM activity_logs
     WHERE role = 'faculty'"
);
if ($statsResult) {
    $row = $statsResult->fetch_assoc();
    $stats['total'] = (int)($row['total'] ?? 0);
    $stats['today'] = (int)($row['today_count'] ?? 0);
    $stats['faculty'] = (int)($row['faculty_count'] ?? 0);
    $stats['last'] = $row['last_activity'] ?? null;
}

$facultyOptions = [];
$facultyResult = $conn->query(
    "SELECT user_email, MAX(user_name) AS display_name, COUNT(*) AS total
     FROM activity_logs
     WHERE role = 'faculty'
     GROUP BY user_email
     ORDER BY display_name ASC"
);
if ($facultyResult) {
    while ($row = $facultyResult->fetch_assoc()) {
        $facultyOptions[] = $row;
    }
}

$conn->close();

function activity_log_meta($action)
{
    $map = [
        'login' => ['label' => 'Signed in', 'group' => 'login', 'icon' => 'login'],
        'logout' => ['label' => 'Signed out', 'group' => 'login', 'icon' => 'logout'],
        'register' => ['label' => 'Account created', 'group' => 'account', 'icon' => 'person_add'],
        'account_updated' => ['label' => 'Account updated', 'group' => 'account', 'icon' => 'manage_accounts'],
        'password_changed' => ['label' => 'Password changed', 'group' => 'account', 'icon' => 'lock_reset'],
        'account_deleted' => ['label' => 'Account deleted', 'group' => 'account', 'icon' => 'person_remove'],
        'vehicle_request_submitted' => ['label' => 'Vehicle request', 'group' => 'vehicle', 'icon' => 'directions_car'],
        'vehicle_request_cancelled' => ['label' => 'Vehicle cancelled', 'group' => 'vehicle', 'icon' => 'cancel'],
        'item_request_submitted' => ['label' => 'Item request', 'group' => 'item', 'icon' => 'inventory_2'],
        'item_request_cancelled' => ['label' => 'Item cancelled', 'group' => 'item', 'icon' => 'remove_shopping_cart'],
    ];

    if (isset($map[$action])) {
        return $map[$action];
    }

    return [
        'label' => ucwords(str_replace('_', ' ', (string)$action)),
        'group' => 'account',
        'icon' => 'history',
    ];
}

function activity_log_url(array $overrides = [])
{
    $params = [
        'q' => $_GET['q'] ?? '',
        'faculty' => $_GET['faculty'] ?? '',
        'group' => $_GET['group'] ?? 'all',
        'date' => $_GET['date'] ?? 'all',
        'log_page' => $_GET['log_page'] ?? 1,
    ];

    foreach ($overrides as $key => $value) {
        $params[$key] = $value;
    }

    $params = array_filter($params, function ($value) {
        return $value !== '' && $value !== null && $value !== 'all';
    });

    // Page 1 is the implicit default; keep other values (including a literal
    // search for "1") intact.
    if (isset($params['log_page']) && (int)$params['log_page'] === 1) {
        unset($params['log_page']);
    }

    $query = http_build_query($params);
    return 'pages/activity_logs.php' . ($query !== '' ? '?' . $query : '');
}

$showingFrom = $totalRows > 0 ? $offset + 1 : 0;
$showingTo = $offset + count($logs);
$lastActivityLabel = $stats['last'] ? date('M j, Y g:i A', strtotime($stats['last'])) : 'No activity yet';
?>

<link href="/Prototype/assets/fonts/material-icons.css" rel="stylesheet">
<link href="/Prototype/assets/fonts/poppins.css" rel="stylesheet">

<style>
    .activity-page {
        margin: 0 auto;
        max-width: 1180px;
    }

    .activity-card {
        background: var(--ppss-surface);
        border: 1px solid var(--ppss-border);
        border-radius: 12px;
        box-shadow: 0 1px 2px var(--ppss-shadow);
        padding: 18px 24px 24px;
    }

    .activity-header {
        align-items: center;
        border-bottom: 1px solid var(--ppss-border);
        display: flex;
        flex-wrap: wrap;
        gap: 18px;
        justify-content: space-between;
        margin-bottom: 18px;
        padding-bottom: 14px;
    }

    .activity-heading {
        flex: 1 1 280px;
        min-width: 220px;
    }

    .activity-heading h1 {
        color: var(--ppss-heading);
        font-size: 22px;
        font-weight: 700;
        letter-spacing: 0.06em;
        line-height: 1.25;
        margin: 0;
    }

    .activity-subtitle {
        color: var(--ppss-muted);
        font-size: 12.5px;
        line-height: 1.5;
        margin: 6px 0 0;
    }

    .activity-retention {
        align-items: center;
        color: var(--ppss-muted);
        display: flex;
        font-size: 11.5px;
        gap: 5px;
        line-height: 1.5;
        margin: 8px 0 0;
    }

    .activity-retention .material-icons {
        color: var(--ppss-primary);
        font-size: 15px;
    }

    .activity-retention strong {
        color: var(--ppss-text);
        font-weight: 600;
    }

    .activity-refresh {
        align-items: center;
        background: var(--ppss-primary);
        border: 1px solid transparent;
        border-radius: 8px;
        color: var(--ppss-on-primary);
        cursor: pointer;
        display: inline-flex;
        font-family: inherit;
        font-size: 12px;
        font-weight: 600;
        gap: 6px;
        height: 38px;
        letter-spacing: 0.03em;
        padding: 0 15px;
    }

    .activity-refresh:hover {
        background: var(--ppss-primary-hover);
    }

    .activity-refresh .material-icons {
        font-size: 17px;
    }

    .activity-stats {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        margin-bottom: 18px;
    }

    .activity-stat {
        align-items: center;
        background: var(--ppss-surface-2);
        border: 1px solid var(--ppss-border);
        border-radius: 10px;
        display: flex;
        gap: 11px;
        min-width: 0;
        padding: 12px 14px;
    }

    .activity-stat-icon {
        align-items: center;
        background: var(--ppss-primary-soft);
        border-radius: 9px;
        color: var(--ppss-primary);
        display: inline-flex;
        flex: 0 0 36px;
        height: 36px;
        justify-content: center;
        width: 36px;
    }

    .activity-stat-icon .material-icons {
        font-size: 19px;
    }

    .activity-stat-copy {
        min-width: 0;
    }

    .activity-stat-value {
        color: var(--ppss-heading);
        font-size: 18px;
        font-weight: 700;
        line-height: 1.2;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .activity-stat-value.is-date {
        font-size: 12.5px;
        font-weight: 600;
    }

    .activity-stat-label {
        color: var(--ppss-muted);
        font-size: 11px;
        letter-spacing: 0.04em;
        margin-top: 2px;
        text-transform: uppercase;
    }

    .activity-filters {
        align-items: end;
        background: var(--ppss-surface-2);
        border: 1px solid var(--ppss-border);
        border-radius: 10px;
        display: grid;
        gap: 10px;
        grid-template-columns: minmax(190px, 1.4fr) minmax(160px, 1fr) minmax(130px, 0.8fr) minmax(120px, 0.7fr) auto;
        margin-bottom: 16px;
        padding: 13px;
    }

    .activity-filter {
        display: flex;
        flex-direction: column;
        gap: 5px;
        min-width: 0;
    }

    .activity-filter label {
        color: var(--ppss-muted);
        font-size: 10.5px;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .activity-input,
    .activity-select {
        background: var(--ppss-input-bg);
        border: 1px solid var(--ppss-input-border);
        border-radius: 8px;
        box-sizing: border-box;
        color: var(--ppss-text);
        font-family: inherit;
        font-size: 12.5px;
        height: 36px;
        min-width: 0;
        padding: 6px 10px;
        width: 100%;
    }

    .activity-input:focus,
    .activity-select:focus {
        border-color: var(--ppss-primary);
        box-shadow: 0 0 0 3px rgba(30, 94, 58, 0.12);
        outline: none;
    }

    .activity-filter-actions {
        display: flex;
        gap: 7px;
    }

    .activity-btn {
        align-items: center;
        border: 1px solid transparent;
        border-radius: 8px;
        cursor: pointer;
        display: inline-flex;
        font-family: inherit;
        font-size: 12px;
        font-weight: 600;
        gap: 5px;
        height: 36px;
        justify-content: center;
        padding: 0 14px;
        white-space: nowrap;
    }

    .activity-btn-primary {
        background: var(--ppss-primary);
        color: var(--ppss-on-primary);
    }

    .activity-btn-primary:hover {
        background: var(--ppss-primary-hover);
    }

    .activity-btn-ghost {
        background: var(--ppss-surface);
        border-color: var(--ppss-border-strong);
        color: var(--ppss-text);
    }

    .activity-btn-ghost:hover {
        background: var(--ppss-surface-3);
    }

    .activity-table-wrap {
        border: 1px solid var(--ppss-border);
        border-radius: 10px;
        overflow-x: auto;
    }

    .activity-table {
        border-collapse: collapse;
        min-width: 820px;
        width: 100%;
    }

    .activity-table th,
    .activity-table td {
        border-bottom: 1px solid var(--ppss-border);
        padding: 11px 13px;
        text-align: left;
        vertical-align: top;
    }

    .activity-table th {
        background: var(--ppss-surface-2);
        color: var(--ppss-muted);
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .activity-table tbody tr:last-child td {
        border-bottom: none;
    }

    .activity-table tbody tr:hover {
        background: var(--ppss-surface-2);
    }

    .activity-when {
        color: var(--ppss-text);
        font-size: 12px;
        font-weight: 600;
        line-height: 1.4;
        white-space: nowrap;
    }

    .activity-when small {
        color: var(--ppss-muted);
        display: block;
        font-size: 11px;
        font-weight: 500;
        margin-top: 2px;
    }

    .activity-person {
        align-items: center;
        display: flex;
        gap: 9px;
        min-width: 170px;
    }

    .activity-avatar {
        align-items: center;
        background: var(--ppss-primary-soft);
        border: 1px solid var(--ppss-border);
        border-radius: 50%;
        color: var(--ppss-primary);
        display: inline-flex;
        flex: 0 0 32px;
        font-size: 11px;
        font-weight: 700;
        height: 32px;
        justify-content: center;
        width: 32px;
    }

    .activity-person-name {
        color: var(--ppss-text);
        font-size: 12.5px;
        font-weight: 600;
        line-height: 1.35;
    }

    .activity-person-email {
        color: var(--ppss-muted);
        font-size: 11px;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .activity-badge {
        align-items: center;
        border: 1px solid transparent;
        border-radius: 999px;
        display: inline-flex;
        font-size: 11px;
        font-weight: 600;
        gap: 5px;
        line-height: 1.3;
        padding: 4px 9px;
        white-space: nowrap;
    }

    .activity-badge .material-icons {
        font-size: 14px;
    }

    .activity-badge-login {
        background: var(--ppss-info-soft);
        border-color: var(--ppss-info-border);
        color: var(--ppss-info);
    }

    .activity-badge-vehicle {
        background: var(--ppss-success-soft);
        border-color: var(--ppss-success-border);
        color: var(--ppss-success);
    }

    .activity-badge-item {
        background: var(--ppss-warning-soft);
        border-color: var(--ppss-warning-border);
        color: var(--ppss-warning);
    }

    .activity-badge-account {
        background: var(--ppss-surface-3);
        border-color: var(--ppss-border-strong);
        color: var(--ppss-text);
    }

    .activity-details {
        color: var(--ppss-text);
        font-size: 12px;
        line-height: 1.5;
        max-width: 420px;
        overflow-wrap: anywhere;
    }

    .activity-empty {
        color: var(--ppss-muted);
        padding: 46px 18px;
        text-align: center;
    }

    .activity-empty .material-icons {
        color: var(--ppss-faint);
        display: block;
        font-size: 40px;
        margin-bottom: 10px;
    }

    .activity-empty strong {
        color: var(--ppss-heading);
        display: block;
        font-size: 14px;
        margin-bottom: 4px;
    }

    .activity-footer {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        justify-content: space-between;
        margin-top: 14px;
    }

    .activity-summary {
        color: var(--ppss-muted);
        font-size: 12px;
    }

    .activity-pagination {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
    }

    .activity-page-btn {
        align-items: center;
        background: var(--ppss-surface);
        border: 1px solid var(--ppss-border-strong);
        border-radius: 7px;
        color: var(--ppss-text);
        cursor: pointer;
        display: inline-flex;
        font-family: inherit;
        font-size: 12px;
        font-weight: 600;
        height: 32px;
        justify-content: center;
        min-width: 32px;
        padding: 0 9px;
    }

    .activity-page-btn:hover:not(:disabled) {
        background: var(--ppss-surface-3);
        border-color: var(--ppss-primary);
        color: var(--ppss-primary);
    }

    .activity-page-btn.is-active {
        background: var(--ppss-primary);
        border-color: var(--ppss-primary);
        color: var(--ppss-on-primary);
        cursor: default;
    }

    .activity-page-btn:disabled {
        cursor: not-allowed;
        opacity: 0.5;
    }

    @media (max-width: 960px) {
        .activity-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .activity-filters {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .activity-filter-actions {
            grid-column: 1 / -1;
        }
    }

    @media (max-width: 560px) {
        .activity-card {
            padding: 14px;
        }

        .activity-stats {
            grid-template-columns: 1fr;
        }

        .activity-filters {
            grid-template-columns: 1fr;
        }

        .activity-header {
            align-items: flex-start;
        }

        .activity-refresh {
            width: 100%;
        }

        .activity-footer {
            align-items: flex-start;
            flex-direction: column;
        }
    }
</style>

<div class="activity-page">
    <div class="activity-card">
        <div class="activity-header">
            <div class="activity-heading">
                <h1>FACULTY ACTIVITY LOGS</h1>
                <p class="activity-subtitle">
                    Review what faculty accounts have been doing: sign-ins, vehicle and item requests,
                    cancellations, and account changes.
                </p>
                <p class="activity-retention">
                    <span class="material-icons">auto_delete</span>
                    Logs are kept for <strong><?php echo number_format($retentionDays); ?> days</strong>
                    and older entries are removed automatically.
                </p>
            </div>
            <button type="button" class="activity-refresh"
                    onclick="window.activityLogNavigate('<?php echo htmlspecialchars(activity_log_url(['log_page' => $pageNum]), ENT_QUOTES); ?>')">
                <span class="material-icons">refresh</span>
                Refresh
            </button>
        </div>

        <div class="activity-stats">
            <div class="activity-stat">
                <span class="activity-stat-icon"><span class="material-icons">history</span></span>
                <div class="activity-stat-copy">
                    <div class="activity-stat-value"><?php echo number_format($stats['total']); ?></div>
                    <div class="activity-stat-label">Total activities</div>
                </div>
            </div>
            <div class="activity-stat">
                <span class="activity-stat-icon"><span class="material-icons">today</span></span>
                <div class="activity-stat-copy">
                    <div class="activity-stat-value"><?php echo number_format($stats['today']); ?></div>
                    <div class="activity-stat-label">Today</div>
                </div>
            </div>
            <div class="activity-stat">
                <span class="activity-stat-icon"><span class="material-icons">group</span></span>
                <div class="activity-stat-copy">
                    <div class="activity-stat-value"><?php echo number_format($stats['faculty']); ?></div>
                    <div class="activity-stat-label">Faculty tracked</div>
                </div>
            </div>
            <div class="activity-stat">
                <span class="activity-stat-icon"><span class="material-icons">schedule</span></span>
                <div class="activity-stat-copy">
                    <div class="activity-stat-value is-date"><?php echo htmlspecialchars($lastActivityLabel); ?></div>
                    <div class="activity-stat-label">Last activity</div>
                </div>
            </div>
        </div>

        <form class="activity-filters" onsubmit="return window.applyActivityLogFilters(event)">
            <div class="activity-filter">
                <label for="activitySearch">Search</label>
                <input type="search" id="activitySearch" class="activity-input"
                       value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Name, email, or details...">
            </div>

            <div class="activity-filter">
                <label for="activityFaculty">Faculty</label>
                <select id="activityFaculty" class="activity-select">
                    <option value="">All faculty</option>
                    <?php foreach ($facultyOptions as $option): ?>
                        <?php
                        $optionEmail = (string)$option['user_email'];
                        $optionName = trim((string)$option['display_name']);
                        $optionLabel = $optionName !== '' ? $optionName : $optionEmail;
                        ?>
                        <option value="<?php echo htmlspecialchars($optionEmail); ?>" <?php echo $facultyFilter === $optionEmail ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($optionLabel . ' (' . $optionEmail . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="activity-filter">
                <label for="activityGroup">Activity type</label>
                <select id="activityGroup" class="activity-select">
                    <option value="all" <?php echo $groupFilter === 'all' ? 'selected' : ''; ?>>All activity</option>
                    <option value="login" <?php echo $groupFilter === 'login' ? 'selected' : ''; ?>>Sign in / sign out</option>
                    <option value="vehicle" <?php echo $groupFilter === 'vehicle' ? 'selected' : ''; ?>>Vehicle requests</option>
                    <option value="item" <?php echo $groupFilter === 'item' ? 'selected' : ''; ?>>Item requests</option>
                    <option value="account" <?php echo $groupFilter === 'account' ? 'selected' : ''; ?>>Account changes</option>
                </select>
            </div>

            <div class="activity-filter">
                <label for="activityDate">Date</label>
                <select id="activityDate" class="activity-select">
                    <option value="all" <?php echo $dateFilter === 'all' ? 'selected' : ''; ?>>All time</option>
                    <option value="today" <?php echo $dateFilter === 'today' ? 'selected' : ''; ?>>Today</option>
                    <option value="7" <?php echo $dateFilter === '7' ? 'selected' : ''; ?>>Last 7 days</option>
                    <option value="30" <?php echo $dateFilter === '30' ? 'selected' : ''; ?>>Last 30 days</option>
                </select>
            </div>

            <div class="activity-filter-actions">
                <button type="submit" class="activity-btn activity-btn-primary">Apply</button>
                <button type="button" class="activity-btn activity-btn-ghost"
                        onclick="window.clearActivityLogFilters()">Clear</button>
            </div>
        </form>

        <div class="activity-table-wrap">
            <?php if (empty($logs)): ?>
                <div class="activity-empty">
                    <span class="material-icons">history_toggle_off</span>
                    <strong>No activity found</strong>
                    <span>Try clearing the filters, or check back after faculty use the system.</span>
                </div>
            <?php else: ?>
                <table class="activity-table">
                    <thead>
                        <tr>
                            <th>Date &amp; Time</th>
                            <th>Faculty</th>
                            <th>Activity</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <?php
                            $meta = activity_log_meta($log['action']);
                            $name = trim((string)$log['user_name']);
                            if ($name === '') {
                                $name = (string)$log['user_email'];
                            }
                            $nameParts = preg_split('/\s+/', $name);
                            $initials = '';
                            if (!empty($nameParts[0])) {
                                $initials .= strtoupper(substr($nameParts[0], 0, 1));
                            }
                            if (count($nameParts) > 1) {
                                $initials .= strtoupper(substr(end($nameParts), 0, 1));
                            }
                            $timestamp = strtotime((string)$log['created_at']);
                            ?>
                            <tr>
                                <td>
                                    <div class="activity-when">
                                        <?php echo $timestamp ? htmlspecialchars(date('M j, Y', $timestamp)) : '—'; ?>
                                        <small><?php echo $timestamp ? htmlspecialchars(date('g:i A', $timestamp)) : ''; ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div class="activity-person">
                                        <span class="activity-avatar"><?php echo htmlspecialchars($initials ?: '?'); ?></span>
                                        <div>
                                            <div class="activity-person-name"><?php echo htmlspecialchars($name); ?></div>
                                            <div class="activity-person-email"><?php echo htmlspecialchars((string)$log['user_email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="activity-badge activity-badge-<?php echo htmlspecialchars($meta['group']); ?>">
                                        <span class="material-icons"><?php echo htmlspecialchars($meta['icon']); ?></span>
                                        <?php echo htmlspecialchars($meta['label']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="activity-details"><?php echo htmlspecialchars((string)$log['description']); ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="activity-footer">
            <div class="activity-summary">
                <?php if ($totalRows > 0): ?>
                    Showing <?php echo number_format($showingFrom); ?>–<?php echo number_format($showingTo); ?>
                    of <?php echo number_format($totalRows); ?> activit<?php echo $totalRows === 1 ? 'y' : 'ies'; ?>
                <?php else: ?>
                    No activities match the current filters.
                <?php endif; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="activity-pagination" aria-label="Activity log pages">
                    <button type="button" class="activity-page-btn"
                            <?php echo $pageNum <= 1 ? 'disabled' : ''; ?>
                            onclick="window.activityLogNavigate('<?php echo htmlspecialchars(activity_log_url(['log_page' => max(1, $pageNum - 1)]), ENT_QUOTES); ?>')">
                        Prev
                    </button>

                    <?php
                    $startPage = max(1, $pageNum - 2);
                    $endPage = min($totalPages, $pageNum + 2);
                    if ($startPage > 1) {
                        echo '<button type="button" class="activity-page-btn" onclick="window.activityLogNavigate(\''
                            . htmlspecialchars(activity_log_url(['log_page' => 1]), ENT_QUOTES)
                            . '\')">1</button>';
                        if ($startPage > 2) {
                            echo '<span class="activity-page-btn" style="border:none;background:transparent;cursor:default;">…</span>';
                        }
                    }
                    for ($p = $startPage; $p <= $endPage; $p++):
                    ?>
                        <button type="button"
                                class="activity-page-btn <?php echo $p === $pageNum ? 'is-active' : ''; ?>"
                                <?php echo $p === $pageNum ? 'aria-current="page"' : ''; ?>
                                onclick="window.activityLogNavigate('<?php echo htmlspecialchars(activity_log_url(['log_page' => $p]), ENT_QUOTES); ?>')">
                            <?php echo $p; ?>
                        </button>
                    <?php endfor; ?>
                    <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?>
                            <span class="activity-page-btn" style="border:none;background:transparent;cursor:default;">…</span>
                        <?php endif; ?>
                        <button type="button" class="activity-page-btn"
                                onclick="window.activityLogNavigate('<?php echo htmlspecialchars(activity_log_url(['log_page' => $totalPages]), ENT_QUOTES); ?>')">
                            <?php echo $totalPages; ?>
                        </button>
                    <?php endif; ?>

                    <button type="button" class="activity-page-btn"
                            <?php echo $pageNum >= $totalPages ? 'disabled' : ''; ?>
                            onclick="window.activityLogNavigate('<?php echo htmlspecialchars(activity_log_url(['log_page' => min($totalPages, $pageNum + 1)]), ENT_QUOTES); ?>')">
                        Next
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    window.activityLogNavigate = function (url) {
        if (typeof window.loadPage === 'function') {
            window.loadPage(url);
        } else {
            // Direct page view (without the dashboard shell): resolve the
            // dashboard-relative URL against the Planning Officer folder.
            window.location.href = '/Prototype/PLANNING%20OFFICER/' + url;
        }
    };

    window.applyActivityLogFilters = function (event) {
        if (event) event.preventDefault();

        const params = new URLSearchParams();
        const search = document.getElementById('activitySearch');
        const faculty = document.getElementById('activityFaculty');
        const group = document.getElementById('activityGroup');
        const date = document.getElementById('activityDate');

        if (search && search.value.trim() !== '') params.set('q', search.value.trim());
        if (faculty && faculty.value !== '') params.set('faculty', faculty.value);
        if (group && group.value !== 'all') params.set('group', group.value);
        if (date && date.value !== 'all') params.set('date', date.value);

        const query = params.toString();
        window.activityLogNavigate('pages/activity_logs.php' + (query ? '?' + query : ''));
        return false;
    };

    window.clearActivityLogFilters = function () {
        window.activityLogNavigate('pages/activity_logs.php');
    };
</script>
