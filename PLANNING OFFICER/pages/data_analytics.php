<?php
session_start();
if (!isset($_SESSION['email']) || trim($_SESSION['role']) !== 'planning_officer') {
    header("Location: ../../auth/login.php");
    exit();
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/Prototype/connect.php';
require_once __DIR__ . '/../includes/year_filter.php';

// Period scope for every query below. A year is always chosen (this year by default) and
// a month may be added on top of it, so the report opens on "now" and can be walked
// backwards month by month.
$monthNames = array(
    1 => 'January', 2 => 'February', 3 => 'March',     4 => 'April',
    5 => 'May',     6 => 'June',     7 => 'July',      8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
);

$currentYear  = (int)date('Y');
$currentMonth = (int)date('n');

// Year: any real calendar year, including one with no requests yet, so a brand new year
// can be opened as soon as it starts. The range is clamped and cast to int, which is what
// keeps the value safe to interpolate into SQL below. A missing or bad value falls back to
// the current year, so the report is never unscoped.
$selectedYear = (string)$currentYear;
$requestedYear = isset($_GET['year']) ? trim((string)$_GET['year']) : '';
if ($requestedYear !== '' && preg_match('/^\d{4}$/', $requestedYear)) {
    $parsedYear = (int)$requestedYear;
    if ($parsedYear >= 2000 && $parsedYear <= 2100) {
        $selectedYear = (string)$parsedYear;
    }
}

// Month: missing means "whatever month it is now", so opening the report lands on the
// current period. "all" is the explicit All Months choice; otherwise 1-12.
$requestedMonth = isset($_GET['month']) ? strtolower(trim((string)$_GET['month'])) : '';
$selectedMonth  = $currentMonth;
if ($requestedMonth === 'all' || $requestedMonth === '') {
    if ($requestedMonth === 'all') {
        $selectedMonth = null;
    }
} elseif (preg_match('/^\d{1,2}$/', $requestedMonth)) {
    $parsedMonth = (int)$requestedMonth;
    $selectedMonth = ($parsedMonth >= 1 && $parsedMonth <= 12) ? $parsedMonth : $currentMonth;
} else {
    $selectedMonth = $currentMonth;
}

$periodLabel = $selectedYear . ($selectedMonth !== null
    ? ' - ' . $monthNames[$selectedMonth]
    : '');

// Years come from the trip dates, not the submission dates, so the dropdown offers
// exactly the years the report can actually be filtered to.
$dataYears = array_values(array_unique(array_merge(
    getAvailableYears($conn, 'vehicle_requests', 'travel_date'),
    getAvailableYears($conn, 'requested_items', 'when_needed')
)));
// Only years that can actually be reported on: the years holding trip data, plus
// the current year. No future year is pre-offered - a new year appears on its own
// once the calendar rolls over on 1 January, via getAvailableYears().
$analyticsYears = $dataYears;

// There is no manual year entry, so a year that holds no data is not reachable.
// A hand-edited URL falls back to the current year, which is always selectable,
// so the dropdown can never disagree with what the report is filtered to.
if (!in_array((int)$selectedYear, $analyticsYears, true)) {
    $selectedYear = (string)$currentYear;
}
rsort($analyticsYears);

// The period is measured by the trip date, matching the vehicle request list, the
// Summary export and the Trip Ticket year. Scoping by request_date put a request
// filed in 2026 for a 2025 trip into the 2026 bucket, so choosing 2025 showed nothing.
$vehicleDateCol = 'travel_date';
$itemDateCol   = 'when_needed';

$yearInt   = (int)$selectedYear;
$monthInt  = $selectedMonth !== null ? (int)$selectedMonth : 0;
$monthPad  = $selectedMonth !== null ? sprintf('%02d', $selectedMonth) : '01';

$yearScopeVehicle = " AND YEAR($vehicleDateCol) = $yearInt";
$yearScopeItems   = " AND YEAR($itemDateCol) = $yearInt";

if ($selectedMonth !== null) {
    $yearScopeVehicle .= " AND MONTH($vehicleDateCol) = $monthInt";
    $yearScopeItems   .= " AND MONTH($itemDateCol) = $monthInt";
    // Trend charts follow the selected month, otherwise a month filter would look empty.
    $trendStart = "DATE('$yearInt-$monthPad-01')";
    $trendSpan  = 'INTERVAL 1 MONTH';
} else {
    // Whole year, otherwise picking an older year would show empty charts.
    $trendStart = "DATE('$yearInt-01-01')";
    $trendSpan  = 'INTERVAL 1 YEAR';
}
$trendScopeVehicle = " AND $vehicleDateCol >= $trendStart AND $vehicleDateCol < DATE_ADD($trendStart, $trendSpan)";
$trendScopeItems   = " AND $itemDateCol   >= $trendStart AND $itemDateCol   < DATE_ADD($trendStart, $trendSpan)";

// Fetch analytics data
$analytics_data = [];

// Get vehicle requests statistics
$vehicle_stats = [];
$sql = "SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
    COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
    COUNT(CASE WHEN status = 'declined' THEN 1 END) as declined,
    DATE(travel_date) as travel_date
    FROM vehicle_requests 
    WHERE 1=1$trendScopeVehicle
    GROUP BY DATE(travel_date)
    ORDER BY travel_date DESC";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $vehicle_stats[] = $row;
    }
}

// Get item requests statistics
$item_stats = [];
$sql = "SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN action = 'pending' THEN 1 END) as pending,
    COUNT(CASE WHEN action = 'approved' THEN 1 END) as approved,
    COUNT(CASE WHEN action = 'declined' THEN 1 END) as declined,
    DATE(when_needed) as when_needed
    FROM requested_items 
    WHERE 1=1$trendScopeItems
    GROUP BY DATE(when_needed)
    ORDER BY when_needed DESC";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $item_stats[] = $row;
    }
}

// Get overall statistics
$overall_stats = [];
$sql = "SELECT 
    'vehicle' as type,
    COUNT(*) as total,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
    COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
    COUNT(CASE WHEN status = 'declined' THEN 1 END) as declined
    FROM vehicle_requests
    WHERE 1=1$yearScopeVehicle
    UNION ALL
    SELECT 
    'item' as type,
    COUNT(*) as total,
    COUNT(CASE WHEN action = 'pending' THEN 1 END) as pending,
    COUNT(CASE WHEN action = 'approved' THEN 1 END) as approved,
    COUNT(CASE WHEN action = 'declined' THEN 1 END) as declined
    FROM requested_items
    WHERE 1=1$yearScopeItems";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $overall_stats[] = $row;
    }
}

// Get top destinations for vehicle requests (all requests)
$top_destinations = [];
$sql = "SELECT destination, COUNT(*) as count,
    COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending
    FROM vehicle_requests 
    WHERE 1=1$yearScopeVehicle
    GROUP BY destination 
    ORDER BY count DESC 
    LIMIT 10";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $top_destinations[] = $row;
    }
}

// Get top requested items (all requests)
$top_items = [];
$sql = "SELECT items, SUM(quantity) as total_quantity, COUNT(*) as request_count,
    COUNT(CASE WHEN action = 'approved' THEN 1 END) as approved,
    COUNT(CASE WHEN action = 'pending' THEN 1 END) as pending
    FROM requested_items 
    WHERE 1=1$yearScopeItems
    GROUP BY items 
    ORDER BY request_count DESC 
    LIMIT 10";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $top_items[] = $row;
    }
}
?>

<link href="/Prototype/assets/fonts/material-icons.css" rel="stylesheet">
<link href="/Prototype/assets/fonts/poppins.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* ================= DATA ANALYTICS STYLES ================= */
/* Scoped to prevent conflicts with dashboard buttons */

.page-header {
    text-align: center;
    margin-bottom: 40px;
}

.page-header h1 {
    color: #1a4d2e;
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 10px;
    text-transform: uppercase;
    letter-spacing: 2px;
}

.page-header p {
    color: #6c757d;
    font-size: 16px;
    margin: 0;
}

/* Year scope selector */
.analytics-year-filter {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin: 0 auto 30px;
    padding: 12px 18px;
    background: #ffffff;
    border: 1px solid #e1e5e9;
    border-radius: 8px;
    width: fit-content;
}

.analytics-year-filter label {
    font-size: 13px;
    font-weight: 600;
    color: #495057;
    white-space: nowrap;
}

.analytics-year-filter select {
    min-width: 160px;
    padding: 9px 12px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 14px;
    font-family: inherit;
    background: white;
    color: #212529;
    cursor: pointer;
    height: 40px;
    box-sizing: border-box;
}

.analytics-year-filter select:focus {
    outline: none;
    border-color: #1a4d2e;
    box-shadow: 0 0 0 3px rgba(26, 77, 46, 0.15);
}

/* Separates the Month control from the Year control. */
.analytics-year-filter .analytics-month-label {
    margin-left: 14px;
}

/* Shown instead of placeholder bars when a chosen year holds no requests. */
.chart-empty {
    width: 100%;
    margin: 0;
    padding: 26px 16px;
    text-align: center;
    font-size: 14px;
    color: #6c757d;
    background: #f8f9fa;
    border: 1px dashed #ced4da;
    border-radius: 6px;
    /* The bar chart is a flex row aligned to the end, so centre the message in it. */
    align-self: center;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    text-align: center;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.15);
}

.stat-card h3 {
    color: #1c6b2f;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 15px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.stat-circle {
    width: 80px;
    height: 80px;
    background: #28a745;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px auto;
    box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
}

.stat-circle .stat-value {
    font-size: 24px;
    font-weight: 700;
    color: white;
    text-align: center;
    line-height: 1;
}

.stat-card .stat-label {
    color: #6c757d;
    font-size: 14px;
    margin-bottom: 15px;
}

/* Chart Containers */
.chart-section {
    background: white;
    border-radius: 12px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.chart-section h2 {
    color: #1c6b2f;
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.chart-container {
    position: relative;
    height: 300px;
    margin-bottom: 20px;
}

/* Top Lists */
.top-list {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin-top: 20px;
}

.top-list h4 {
    color: #1c6b2f;
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 15px;
}

.top-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #e9ecef;
}

.top-item:last-child {
    border-bottom: none;
}

.top-item-name {
    font-weight: 500;
    color: #2c3e50;
}

.top-item-count {
    background: #1c6b2f;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

/* Bar Chart Styles */
.bar-chart {
    padding: 20px;
}

.bar-item {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
    gap: 15px;
}

.bar-label {
    width: 150px;
    font-size: 12px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #333;
}

.bar-track {
    flex: 1;
    height: 25px;
    background: #f0f0f0;
    border-radius: 4px;
    position: relative;
    overflow: hidden;
}

.bar-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.5s ease;
    min-width: 2px;
}

.bar-value {
    width: 40px;
    font-size: 14px;
    font-weight: bold;
    text-align: right;
    color: #333;
}

/* Vertical Bar Chart Styles */
.vertical-bar-chart {
    display: flex;
    align-items: flex-end;
    justify-content: flex-start;
    gap: 10px;
    height: 300px;
    padding: 20px 20px 12px;
    /* A busy chart must never push past its card. overflow-x is only a safety net:
       the rules below keep the bars inside the box in the first place. */
    overflow-x: auto;
    overflow-y: hidden;
    min-width: 0;
}

.vertical-bar-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    /* flex-basis 0 + min-width 0 is what makes this responsive: the bars share the
       row evenly and are allowed to shrink. The default min-width:auto would refuse to
       shrink below the text, which is how a long destination name broke the layout. */
    flex: 1 1 0;
    min-width: 0;
    max-width: 120px;
}

.vertical-bar-container {
    /* Track follows the item width instead of a fixed 60px, so a narrow column gets a
       narrow bar rather than one that overflows it. */
    width: 100%;
    max-width: 60px;
    height: 200px;
    background: #f0f0f0;
    border-radius: 4px 4px 0 0;
    position: relative;
    margin-bottom: 10px;
}

.vertical-bar-fill {
    width: 100%;
    border-radius: 4px 4px 0 0;
    position: absolute;
    bottom: 0;
    transition: height 0.5s ease;
    min-height: 2px;
}

.vertical-bar-label {
    font-size: 11px;
    text-align: center;
    color: #333;
    margin-bottom: 5px;
    line-height: 1.2;
    width: 100%;
    min-width: 0;
    /* Two lines at most. The full name stays available via the title attribute. */
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    overflow-wrap: anywhere;
    min-height: 26px;
}

.vertical-bar-value {
    font-size: 14px;
    font-weight: bold;
    color: #333;
    text-align: center;
    max-width: 100%;
}

/* ================= SMALL LAPTOP RESPONSIVE ================= */
@media (max-width: 1280px) {

    .paper-form {
        padding: 20px 24px 24px;
    }

    .page-header h1 {
        font-size: 26px;
        letter-spacing: 1px;
    }

    .page-header p {
        font-size: 14px;
    }

    .chart-section {
        padding: 20px;
        min-width: 0;
    }

    /* Stack the two chart sections so each bar chart gets full width */
    .stats-grid-charts {
        grid-template-columns: 1fr;
        gap: 20px;
    }

    /* Keep stat cards adaptive but a bit smaller on laptops */
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }

    /* Shorter tracks and tighter gaps to leave room for the labels. */
    .vertical-bar-chart {
        height: 270px;
        gap: 8px;
    }

    .vertical-bar-container {
        height: 165px;
    }

    .vertical-bar-label {
        font-size: 10px;
        min-height: 24px;
    }

    .vertical-bar-value {
        font-size: 13px;
    }

    /* Scrollbar styling */
    .vertical-bar-chart::-webkit-scrollbar {
        height: 6px;
    }

    .vertical-bar-chart::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 10px;
    }
}

/* Responsive Design */
/* ================= MOBILE RESPONSIVE FIX ================= */
@media (max-width: 768px) {

    .paper-form {
        padding: 16px;
    }

    .chart-section {
        padding: 15px;
        min-width: 0;
    }

    .stats-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }

    .page-header h1 {
        font-size: 22px;
        letter-spacing: 1px;
    }

    .page-header p {
        font-size: 13px;
    }

    /* ===== Vertical Charts =====
       Items keep flex:1 1 0 from the base rules so they shrink to fit the phone.
       min-width: max-content is deliberately NOT used here: on a scrolling flex
       container it makes the container itself grow past the card instead of scrolling. */
    .vertical-bar-chart {
        gap: 6px;
        height: 230px;
        padding: 16px 12px 10px;
    }

    .vertical-bar-container {
        height: 140px;
        max-width: 40px;
    }

    .vertical-bar-label {
        font-size: 9px;
        min-height: 22px;
    }

    .vertical-bar-value {
        font-size: 12px;
        margin-top: 3px;
    }

    /* Scrollbar styling */
    .vertical-bar-chart::-webkit-scrollbar {
        height: 6px;
    }

    .vertical-bar-chart::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 10px;
    }

    /* ===== Stat Cards ===== */
    .stat-card {
        padding: 18px;
    }

    .stat-circle {
        width: 65px;
        height: 65px;
    }

    .stat-circle .stat-value {
        font-size: 20px;
    }

    /* ===== Header Icons ===== */
    .chart-section h2 {
        font-size: 16px;
    }
}

/* ================= ENTERPRISE DASHBOARD REFRESH ================= */
/* Pull the card closer to the top bar; scoped to this page because the
   style block is removed with the fragment on navigation. */
#mainContent {
    padding-top: 16px;
}

/* Shared shell with the Vehicle Requests page */
.paper-form {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04);
    margin: 0 auto;
    max-width: 1180px;
    padding: 16px 28px 28px;
}

.paper-form h1 {
    color: #111827;
    font-size: 22px;
    font-weight: 700;
    letter-spacing: 0.06em;
    line-height: 1.25;
    margin: 0;
    text-align: left;
    text-shadow: none;
}

/* Header + filter row */
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
}

.page-heading {
    flex: 1 1 280px;
    min-width: 220px;
}

.page-header h1 {
    color: #111827;
    font-size: 22px;
    font-weight: 700;
    letter-spacing: 0.06em;
    line-height: 1.25;
    margin: 0;
    text-align: left;
    text-transform: uppercase;
}

.page-header .page-subtitle,
.page-header p {
    margin: 6px 0 0;
    color: #6b7280;
    font-size: 12.5px;
    line-height: 1.5;
}

/* Year and Month sit inline on one row, aligned with the page title */
.filter-controls {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
    flex: 0 1 auto;
    flex-wrap: nowrap;
}

.filter-field {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 8px;
    flex: 0 1 auto;
    min-width: 0;
}

.filter-label {
    color: #6b7280;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    white-space: nowrap;
}

.filter-controls .time-select {
    width: auto;
    min-width: 120px;
    height: 38px;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    background: #ffffff;
    color: #111827;
    font-family: inherit;
    font-size: 13px;
    box-sizing: border-box;
    cursor: pointer;
}

.filter-controls .time-select:focus {
    outline: none;
    border-color: #1E5E3A;
    box-shadow: 0 0 0 3px rgba(30, 94, 58, 0.12);
}

/* Sections and cards */
.chart-section {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04);
    margin-bottom: 16px;
    min-width: 0;
    padding: 20px 24px 24px;
}

.chart-section h2 {
    align-items: center;
    border-bottom: 1px solid #f3f4f6;
    color: #111827;
    display: flex;
    font-size: 12px;
    font-weight: 700;
    gap: 8px;
    letter-spacing: 0.06em;
    margin: 0 0 18px;
    padding-bottom: 12px;
    text-transform: uppercase;
}

.chart-section h2 .material-icons {
    color: #1E5E3A;
    font-size: 18px;
}

.stats-grid {
    gap: 12px;
    margin-bottom: 0;
}

.stats-grid-charts .chart-section {
    margin-bottom: 0;
}

.stat-card {
    align-items: stretch;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    box-shadow: none;
    padding: 18px 20px;
    text-align: left;
    transition: border-color 0.18s ease;
}

.stat-card:hover {
    border-color: #d1d5db;
    box-shadow: none;
    transform: none;
}

.stat-card-top {
    align-items: center;
    display: flex;
    gap: 10px;
    justify-content: space-between;
    margin-bottom: 6px;
}

.stat-card .stat-label,
.stat-label {
    color: #6b7280;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.08em;
    margin: 0;
    text-transform: uppercase;
}

.stat-total-badge {
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
    border-radius: 999px;
    color: #374151;
    flex-shrink: 0;
    font-size: 10.5px;
    font-weight: 600;
    letter-spacing: 0.03em;
    padding: 3px 9px;
    white-space: nowrap;
}

.stat-value-large {
    color: #111827;
    font-size: 30px;
    font-weight: 700;
    line-height: 1.2;
}

.stat-breakdown {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 18px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #f3f4f6;
}

.stat-breakdown-item {
    align-items: center;
    display: inline-flex;
    gap: 6px;
}

.stat-dot {
    border-radius: 50%;
    display: inline-block;
    flex-shrink: 0;
    height: 7px;
    width: 7px;
}

.stat-dot.dot-pending {
    background: #f59e0b;
}

.stat-dot.dot-approved {
    background: #067647;
}

.stat-dot.dot-declined {
    background: #b42318;
}

.stat-breakdown-value {
    color: #1f2937;
    font-size: 13px;
    font-weight: 700;
    line-height: 1;
}

.stat-breakdown-label {
    color: #6b7280;
    font-size: 10.5px;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

/* Charts */
.vertical-bar-chart {
    background: #f9fafb;
    border: 1px solid #f3f4f6;
    border-radius: 8px;
}

.vertical-bar-container {
    background: #e5e7eb;
}

.vertical-bar-fill {
    background: #1E5E3A;
}

.vertical-bar-label {
    color: #4b5563;
}

.vertical-bar-value {
    color: #111827;
    font-weight: 700;
}

.top-list {
    background: #f9fafb;
    border: 1px solid #f3f4f6;
    border-radius: 8px;
}

.top-list h4 {
    color: #111827;
    font-size: 12px;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.top-item {
    border-bottom-color: #e5e7eb;
}

.top-item-name {
    color: #1f2937;
    font-size: 13px;
}

.top-item-count {
    background: #ecfdf3;
    border: 1px solid #abefc6;
    color: #067647;
    font-size: 11px;
    font-weight: 700;
}

.chart-empty {
    background: #f9fafb;
    border: 1px dashed #d1d5db;
    border-radius: 8px;
    color: #6b7280;
    font-size: 12.5px;
}

.bar-label,
.bar-value {
    color: #1f2937;
}

@media (max-width: 900px) {
    .page-header {
        align-items: flex-start;
        flex-direction: column;
        gap: 14px;
    }

    .page-heading {
        flex: 0 0 auto;
    }

    .filter-controls {
        width: 100%;
        justify-content: flex-start;
    }

    .filter-field {
        flex: 0 1 auto;
    }
}

@media (max-width: 600px) {
    .chart-section {
        padding: 16px;
    }

    .filter-controls {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        align-items: end;
    }

    .filter-field {
        min-width: 0;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .stat-card {
        padding: 16px;
    }

    .stat-value-large {
        font-size: 26px;
    }
}

</style>

<div class="paper-form">
    <div class="page-header">
        <div class="page-heading">
            <h1>DATA ANALYTICS</h1>
            <p class="page-subtitle">Comprehensive overview of vehicle and item requests.</p>
        </div>

        <!-- Period scope. The year defaults to the current year and the month to the current
             month, so the report always opens on "now". "All Months" widens the chosen year
             back to twelve months. The year list is built from the years that actually hold
             data, so a new year appears by itself once the calendar reaches 1 January. -->
        <form method="get" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] ?? 'data_analytics.php'); ?>" class="filter-controls" id="analyticsYearForm" data-spa-base="pages/data_analytics.php">
            <label class="filter-field" for="yearSelect">
                <span class="filter-label">Report Year</span>
                <select id="yearSelect" class="time-select">
                    <?php foreach ($analyticsYears as $y): ?>
                        <option value="<?php echo $y; ?>"
                            <?php echo (string)$y === $selectedYear ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="filter-field" for="monthSelect">
                <span class="filter-label">Month</span>
                <select id="monthSelect" class="time-select">
                    <option value="all" <?php echo $selectedMonth === null ? 'selected' : ''; ?>>All Months</option>
                    <?php foreach ($monthNames as $num => $name): ?>
                        <option value="<?php echo $num; ?>" <?php echo $selectedMonth === $num ? 'selected' : ''; ?>><?php echo $name; ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <noscript>
                <span style="font-size:13px;color:#6c757d;">JavaScript is required to switch report periods.</span>
            </noscript>
        </form>
    </div>

    <?php if (empty($overall_stats[0]['total']) && empty($overall_stats[1]['total'])): ?>
        <p class="chart-empty" style="width:fit-content;margin:0 0 20px;">
            No requests were recorded in <?php echo htmlspecialchars($periodLabel); ?> yet.
        </p>
    <?php endif; ?>

    <script>
    // Wrapped in an IIFE and stored on window: home.php re-runs this file's inline
    // scripts on every SPA navigation, so a bare top-level const/let would throw
    // "already declared" the second time the page is opened.
    (function () {
        var form = document.getElementById('analyticsYearForm');
        var select = document.getElementById('yearSelect');
        var monthSelect = document.getElementById('monthSelect');
        if (!form || !select || !monthSelect) return;

        // The year is always sent. The month is always sent too, because an absent
        // month means "current month" server side, so All Months has to travel as
        // the explicit value "all" to survive a page reload.
        function go(year) {
            var y = String(year == null ? select.value : year).trim();
            if (!/^\d{4}$/.test(y)) return;

            var month = monthSelect.value === '' ? 'all' : monthSelect.value;
            var params = ['year=' + encodeURIComponent(y), 'month=' + encodeURIComponent(month)];
            var url = form.getAttribute('data-spa-base') + '?' + params.join('&');

            if (typeof window.loadPage === 'function') {
                // Stay inside the SPA instead of reloading the whole dashboard.
                window.loadPage(url);
            } else {
                window.location.href = url;
            }
        }

        select.addEventListener('change', function () {
            go(select.value);
        });

        monthSelect.addEventListener('change', function () {
            go(select.value);
        });
    })();
    </script>

    <!-- Overall Statistics -->
    <div class="chart-section">
        <h2>
            <span class="material-icons">analytics</span>
            Overall Statistics
        </h2>
        <div class="stats-grid">
            <?php foreach ($overall_stats as $stat): ?>
                <div class="stat-card">
                    <div class="stat-card-top">
                        <span class="stat-label"><?php echo ucfirst($stat['type']); ?> Requests</span>
                        <span class="stat-total-badge"><?php echo $stat['total']; ?> Total</span>
                    </div>
                    <div class="stat-value-large"><?php echo $stat['total']; ?></div>
                    <div class="stat-breakdown">
                        <div class="stat-breakdown-item">
                            <span class="stat-dot dot-pending"></span>
                            <span class="stat-breakdown-value"><?php echo $stat['pending']; ?></span>
                            <span class="stat-breakdown-label">Pending</span>
                        </div>
                        <div class="stat-breakdown-item">
                            <span class="stat-dot dot-approved"></span>
                            <span class="stat-breakdown-value"><?php echo $stat['approved']; ?></span>
                            <span class="stat-breakdown-label">Approved</span>
                        </div>
                        <div class="stat-breakdown-item">
                            <span class="stat-dot dot-declined"></span>
                            <span class="stat-breakdown-value"><?php echo $stat['declined']; ?></span>
                            <span class="stat-breakdown-label">Declined</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Top Destinations -->
    <div class="stats-grid stats-grid-charts">
        <div class="chart-section">
            <h2>
                <span class="material-icons">location_on</span>
                Top Destinations
            </h2>
            <div class="vertical-bar-chart">
                <?php if (empty($top_destinations)): ?>
                    <p class="chart-empty">No vehicle requests for <?php echo htmlspecialchars($periodLabel); ?>.</p>
                <?php else: ?>
                <?php
                $destinations_data = $top_destinations;
                $max_dest = max(array_column($destinations_data, 'count'));
                foreach ($destinations_data as $dest): 
                    // Percentage of the track rather than a fixed pixel height, so the
                    // bar still fits when the track shrinks on smaller screens.
                    $heightPct = $max_dest > 0 ? ($dest['count'] / $max_dest) * 100 : 0;
                    $destName = (string)$dest['destination'];
                ?>
                    <div class="vertical-bar-item">
                        <div class="vertical-bar-container">
                            <div class="vertical-bar-fill" style="height: <?php echo $heightPct; ?>%; background: #1E5E3A;"></div>
                        </div>
                        <div class="vertical-bar-label" title="<?php echo htmlspecialchars($destName); ?>"><?php echo htmlspecialchars($destName); ?></div>
                        <div class="vertical-bar-value"><?php echo $dest['count']; ?></div>
                    </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top Requested Items -->
        <div class="chart-section">
            <h2>
                <span class="material-icons">inventory</span>
                Top Requested Items
            </h2>
            <div class="vertical-bar-chart">
                <?php if (empty($top_items)): ?>
                    <p class="chart-empty">No item requests for <?php echo htmlspecialchars($periodLabel); ?>.</p>
                <?php else: ?>
                <?php
                $items_data = $top_items;
                $max_items = max(array_column($items_data, 'request_count'));
                foreach ($items_data as $item): 
                    $heightPct = $max_items > 0 ? ($item['request_count'] / $max_items) * 100 : 0;
                    $itemName = (string)$item['items'];
                ?>
                    <div class="vertical-bar-item">
                        <div class="vertical-bar-container">
                            <div class="vertical-bar-fill" style="height: <?php echo $heightPct; ?>%; background: #5a9e6f;"></div>
                        </div>
                        <div class="vertical-bar-label" title="<?php echo htmlspecialchars($itemName); ?>"><?php echo htmlspecialchars($itemName); ?></div>
                        <div class="vertical-bar-value"><?php echo $item['request_count']; ?></div>
                    </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

