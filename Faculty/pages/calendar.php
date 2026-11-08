<?php
session_start();
if (!isset($_SESSION['email']) || trim($_SESSION['role']) !== 'faculty') {
    header("Location: ../../auth/login.php");
    exit();
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/Prototype/connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Prototype/time_format.php';
require_once __DIR__ . '/../../PLANNING OFFICER/includes/fleet_schema.php';
ensureFleetSchema($conn);

// Create calendar_events table if it doesn't exist
$createTableSQL = "CREATE TABLE IF NOT EXISTS calendar_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_date DATE NOT NULL,
    event_time TIME,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    created_by VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($createTableSQL);

// The Faculty calendar is strictly read-only. Refuse every write attempt so
// calendar management cannot be triggered by a crafted request either.
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'The Faculty calendar is view-only.']);
    exit;
}

// Get current year and month. Both are cast to int: date('Y') returns a string, and
// mixing that with ints makes the strict in_array() checks below fail, which duplicated
// a year in the dropdown and left nothing marked as selected.
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : null;
$view = isset($_GET['view']) ? $_GET['view'] : 'year';

// Years offered in the year dropdown: every year that actually holds a vehicle
// request or a calendar event, plus the current year. Derived from data rather than
// a hardcoded range, so history never falls off the list and a new year appears on
// its own once the calendar reaches 1 January.
$calendarYears = [];
$years_result = $conn->query("SELECT DISTINCT YEAR(travel_date) AS y FROM vehicle_requests
                              WHERE travel_date IS NOT NULL
                              UNION
                              SELECT DISTINCT YEAR(event_date) AS y FROM calendar_events
                              WHERE event_date IS NOT NULL");
if ($years_result) {
    while ($row = $years_result->fetch_assoc()) {
        if (!empty($row['y'])) {
            $calendarYears[] = (int)$row['y'];
        }
    }
}
if (!in_array((int)date('Y'), $calendarYears, true)) {
    $calendarYears[] = (int)date('Y');
}
// Keep a year requested in the URL selectable, so the control always matches the view.
if (!in_array($year, $calendarYears, true)) {
    $calendarYears[] = $year;
}
rsort($calendarYears);

// Get every calendar event for the current year or month. Faculty see schedules
// created by any Planning Officer/Admin, not only their own entries.
if ($view === 'month' && $month) {
    $events_sql = "SELECT * FROM calendar_events 
                   WHERE MONTH(event_date) = ? AND YEAR(event_date) = ?
                   ORDER BY event_date, event_time";
    $stmt = $conn->prepare($events_sql);
    $stmt->bind_param("ii", $month, $year);
} else {
    $events_sql = "SELECT * FROM calendar_events 
                   WHERE YEAR(event_date) = ?
                   ORDER BY event_date, event_time";
    $stmt = $conn->prepare($events_sql);
    $stmt->bind_param("i", $year);
}
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get vehicle requests for current year or month.
// Columns are listed explicitly rather than "vr.*": attachment_data is a LONGBLOB of raw
// file bytes, so "vr.*" made the json_encode() of allEvents below fail on malformed
// UTF-8 and emit "const allEvents = ;", a SyntaxError that disables the whole calendar.
// Attachment bytes are fetched on demand via view_attachment.php instead.
$vrCols = "vr.id, vr.ticket_no, vr.ticket_year, vr.travel_date,
           vr.destination, vr.purpose, vr.passengers, vr.requested_by, vr.user_email,
           vr.attachment_name, vr.request_date, vr.status, vr.reviewed_at, vr.notes,
           vr.created_at, vr.vehicle_id, vr.driver AS request_driver";
$vrFaculty = "(SELECT CONCAT(u.firstName, ' ', u.lastName) FROM users u 
              WHERE u.email = vr.user_email
                 OR ((vr.user_email IS NULL OR vr.user_email = '') AND LOWER(CONCAT(u.firstName, ' ', u.lastName)) = LOWER(vr.requested_by))) AS faculty_name";
// Fleet vehicle and its assigned driver, shown alongside each request on the calendar.
$vrVehicle = "vf.plate_number AS vehicle_plate,
              vf.vehicle_type AS vehicle_type,
              vf.model AS vehicle_model,
              d.full_name AS fleet_driver_name";
$vrJoins = "LEFT JOIN vehicle_fleet vf ON vf.id = vr.vehicle_id
            LEFT JOIN drivers d ON d.id = vf.driver_id";

// Get vehicle requests for current year or month
if ($view === 'month' && $month) {
    $requests_sql = "SELECT $vrCols, $vrFaculty, $vrVehicle
                     FROM vehicle_requests vr 
                     $vrJoins
                     WHERE MONTH(vr.travel_date) = ? AND YEAR(vr.travel_date) = ?
                     ORDER BY vr.travel_date";
    $stmt_requests = $conn->prepare($requests_sql);
    $stmt_requests->bind_param("ii", $month, $year);
} else {
    $requests_sql = "SELECT $vrCols, $vrFaculty, $vrVehicle
                     FROM vehicle_requests vr 
                     $vrJoins
                     WHERE YEAR(vr.travel_date) = ?
                     ORDER BY vr.travel_date";
    $stmt_requests = $conn->prepare($requests_sql);
    $stmt_requests->bind_param("i", $year);
}
$stmt_requests->execute();
$vehicle_requests = $stmt_requests->get_result()->fetch_all(MYSQLI_ASSOC);

// Group events and requests by date
$events_by_date = [];
foreach ($events as $event) {
    $events_by_date[$event['event_date']][] = [
        'type' => 'calendar_event',
        'data' => $event
    ];
}

foreach ($vehicle_requests as $request) {
    $events_by_date[$request['travel_date']][] = [
        'type' => 'vehicle_request',
        'data' => $request
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - Faculty</title>
    <link href="/Prototype/assets/fonts/material-icons.css" rel="stylesheet">
    <link href="/Prototype/assets/fonts/poppins.css" rel="stylesheet">
    <style>
        /* ================= CALENDAR STYLES ================= */
        /* Scoped to prevent conflicts with dashboard buttons */

        /* The reset is scoped to the calendar so it cannot restyle the dashboard shell. */
        .calendar-container,
        .calendar-container *,
        .calendar-container *::before,
        .calendar-container *::after {
            box-sizing: border-box;
        }

        .calendar-container {
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        }

        .calendar-container .paper-form {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin: 20px auto;
            max-width: 1200px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 20px;
            background: linear-gradient(135deg, #148139 0%, #00582c 100%);
            color: white;
            border-radius: 8px;
        }

        .calendar-nav {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .calendar-month-nav {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .calendar-year-picker {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .month-year {
            font-size: 24px;
            font-weight: bold;
        }

        .calendar-year-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            opacity: 0.85;
        }

        .calendar-year-select {
            padding: 9px 14px;
            font-size: 18px;
            font-weight: bold;
            font-family: inherit;
            color: #1a4d2e;
            background: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 8px;
            cursor: pointer;
            min-width: 110px;
        }

        .calendar-year-select:hover {
            background: #f2f7f4;
        }

        .calendar-year-select:focus {
            outline: none;
            border-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.35);
        }

        /* Button Styles - Scoped to prevent dashboard conflicts */
        .calendar-container .btn {
            padding: 10px 20px;
            border: 1px solid #ccc;
            border-radius: 20px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            width: auto;
            margin-bottom: 10px;
            text-decoration: none;
            display: inline-block;
        }

        .calendar-container .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 6px rgba(0,0,0,0.15);
        }

        .calendar-container .btn:active {
            transform: translateY(0);
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        .calendar-container .btn.primary {
            background: #0c9217;
            color: white;
        }

        .calendar-container .btn.primary:hover {
            background: #0a7c12;
        }

        .calendar-container .btn.secondary {
            background: #6c757d;
            color: white;
        }

        .calendar-container .btn.secondary:hover {
            background: #5a6268;
        }

        .calendar-container .btn.success {
            background: #28a745;
            color: white;
        }

        .calendar-container .btn.success:hover {
            background: #218838;
        }

        .calendar-container .btn.danger {
            background: #dc3545;
            color: white;
        }

        .calendar-container .btn.danger:hover {
            background: #c82333;
        }

        .calendar-container .btn.sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: #ddd;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .calendar-day-header {
            background: #1a4d2e;
            color: white;
            padding: 15px 10px;
            text-align: center;
            font-weight: bold;
            font-size: 14px;
        }

        .calendar-day {
            background: white;
            min-height: 100px;
            padding: 8px;
            cursor: pointer;
            transition: background 0.2s;
            position: relative;
            border-right: 1px solid #eee;
            border-bottom: 1px solid #eee;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .calendar-day:hover {
            background: #f8f9fa;
        }

        .calendar-day.other-month {
            color: #ccc;
            background: #fafafa;
        }

        .calendar-day.today {
            background: #e3f2fd;
        }

        .calendar-day.has-events {
            background: #fff3e0;
        }

        .day-number {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .event-item {
            background: #fff7e6;
            border: 1px solid #ffb74d;
            border-left: 3px solid #ff9800;
            color: #7a4f01;
            padding: 2px 5px;
            border-radius: 4px;
            font-size: 9px;
            margin-top: 3px;
            cursor: pointer;
            line-height: 1.35;
            width: 100%;
            max-width: 100%;
            white-space: normal;
            overflow-wrap: break-word;
            word-break: break-word;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            box-sizing: border-box;
        }

        .event-item.request-event {
            -webkit-line-clamp: 4;
        }

        .event-vehicle,
        .event-driver {
            opacity: 0.85;
        }

        .event-item:hover {
            background: #fff0cf;
        }

        .event-item.more-indicator {
            background: #ffe0b2;
            border-color: #ffb74d;
            color: #a96500;
            font-weight: bold;
            -webkit-line-clamp: unset;
        }

        .event-item.calendar-event-item {
            background: #e3f2fd;
            border: 1px solid #90caf9;
            border-left: 3px solid #1976d2;
            color: #0d47a1;
        }

        .calendar-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
        }

        .year-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .month-container {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .clickable-month {
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .clickable-month:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            border-color: #1a4d2e;
        }

        .clickable-month:hover .month-title {
            color: #1a4d2e;
        }

        .month-title {
            text-align: center;
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 10px;
            color: #1a4d2e;
        }

        .mini-calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
            font-size: 11px;
        }

        .mini-day-header {
            background: #1a4d2e;
            color: white;
            padding: 5px 2px;
            text-align: center;
            font-weight: bold;
            font-size: 10px;
        }

        .mini-day {
            background: white;
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 1px solid #eee;
            font-size: 10px;
            position: relative;
            transition: background 0.2s;
        }

        .mini-day:hover {
            background: #f8f9fa;
        }

        .mini-day.other-month {
            color: #ccc;
            background: #fafafa;
        }

        .mini-day.today {
            background: #e3f2fd;
            font-weight: bold;
        }

        .mini-day.has-events {
            background: #fff3e0;
        }

        .mini-event-dot {
            position: absolute;
            bottom: 2px;
            left: 50%;
            transform: translateX(-50%);
            width: 3px;
            height: 3px;
            background: #ff9800;
            border-radius: 50%;
        }

        .legend {
            display: flex;
            gap: 20px;
            font-size: 12px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .legend-color {
            width: 15px;
            height: 15px;
            border-radius: 3px;
        }

        .events-section {
            margin-top: 30px;
        }

        .events-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .events-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .event-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }

        .event-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .event-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .event-title {
            font-weight: bold;
            font-size: 16px;
            color: #333;
            flex: 1;
        }

        .event-actions {
            display: flex;
            gap: 5px;
        }

        .event-date-time {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            color: #666;
            font-size: 14px;
        }

        .event-description {
            color: #666;
            font-size: 14px;
            line-height: 1.4;
        }

        /* Vehicle Requests Modal Styles */
        .requests-list {
            max-height: 400px;
            overflow-y: auto;
            margin-bottom: 20px;
        }

        .request-item {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }

        .request-header h4 {
            margin: 0;
            color: #333;
            font-size: 16px;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-badge.pending {
            background: #ffc107;
            color: #856404;
        }

        .status-badge.approved {
            background: #28a745;
            color: white;
        }

        .status-badge.declined {
            background: #dc3545;
            color: white;
        }

        .request-details {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .detail-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 14px;
        }

        .detail-row strong {
            min-width: 120px;
            color: #333;
        }

        .priority-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .priority-high {
            background: #dc3545;
            color: white;
        }

        .priority-medium {
            background: #ffc107;
            color: #856404;
        }

        .priority-low {
            background: #28a745;
            color: white;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-title {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
            padding: 0;
            width: 30px;
            height: 30px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #1a4d2e;
            box-shadow: 0 0 0 2px rgba(26, 77, 46, 0.2);
        }

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ccc;
        }
        
        .calendar-container {
    overflow-x: hidden;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

        /* ============ TABLET / SMALL LAPTOP ============ */
    @media (max-width: 1100px) {
        .calendar-container .paper-form {
            padding: 18px;
        }

        .calendar-header {
            padding: 15px;
        }

        .month-year {
            font-size: 20px;
        }

        .calendar-grid {
            font-size: 11px;
        }

        .calendar-day {
            min-height: 80px;
            padding: 5px;
        }

        .day-number {
            font-size: 12px;
            margin-bottom: 3px;
        }

        .event-item {
            font-size: 8px;
            padding: 1px 4px;
        }

        .year-grid {
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }
    }

        @media (max-width: 768px) {
    /* Fix horizontal scroll */
    .calendar-container,
    .calendar-container .paper-form {
        overflow-x: hidden;
        width: 100%;
        margin: 0;
        padding: 10px;
        border-radius: 0;
    }

    /* Year grid: 2 columns on mobile instead of 4 */
    .year-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }

    .month-container {
        padding: 10px;
    }

    .month-title {
        font-size: 13px;
        margin-bottom: 6px;
    }

    /* Calendar header stacks on mobile */
    .calendar-header {
        flex-direction: column;
        gap: 12px;
        padding: 14px;
    }

    /* Stack the month arrows and the year dropdown onto separate lines so the
       "September 2026" label and the select can never overrun each other. */
    .calendar-nav {
        flex-direction: column;
        gap: 10px;
        width: 100%;
    }

    .calendar-month-nav {
        gap: 12px;
        max-width: 100%;
    }

    .calendar-year-picker {
        gap: 8px;
        justify-content: center;
        width: 100%;
    }

    .month-year {
        font-size: 18px;
        text-align: center;
        min-width: 0;
    }

    .calendar-year-select {
        font-size: 16px;
        padding: 9px 14px;
        min-width: 120px;
        min-height: 44px;
    }

    .calendar-header > div:last-child {
        display: flex;
        justify-content: center;
        gap: 10px;
        flex-wrap: wrap;
        width: 100%;
    }

    /* Month view grid */
    .calendar-grid {
        font-size: 11px;
    }

    .calendar-day {
        min-height: 62px;
        padding: 4px;
    }

    .day-number {
        font-size: 11px;
    }

    .event-item {
        font-size: 8px;
        padding: 1px 4px;
        -webkit-line-clamp: 2;
    }

    .event-item.request-event {
        -webkit-line-clamp: 4;
    }

    /* Footer */
    .calendar-footer {
        flex-direction: column;
        gap: 12px;
        align-items: flex-start;
    }

    .legend {
        flex-wrap: wrap;
        gap: 10px;
    }

    /* Modal full width on mobile */
    .modal-content {
        width: 95%;
        max-width: 100%;
        padding: 16px;
        max-height: 85vh;
    }

    .detail-row {
        flex-direction: column;
        gap: 2px;
    }

    .detail-row strong {
        min-width: unset;
    }

    /* Mini calendar cells */
    .mini-day {
        font-size: 9px;
    }

    .mini-day-header {
        font-size: 9px;
        padding: 3px 1px;
    }

    /* Buttons in header */
    .calendar-container .btn {
        padding: 8px 14px;
        font-size: 13px;
    }
}

@media (max-width: 400px) {
    .year-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
    }

    .month-title {
        font-size: 12px;
    }

    .mini-day,
    .mini-day-header {
        font-size: 8px;
    }

    /* Longest month names (September, November) plus two arrow buttons still have
       to fit one line, so tighten the row further on small phones. */
    .calendar-month-nav {
        gap: 8px;
    }

    .month-year {
        font-size: 16px;
    }

    .calendar-year-select {
        min-width: 104px;
    }
}

/* ================= ENTERPRISE DASHBOARD REFRESH ================= */
.calendar-container {
    margin: 0 auto;
    max-width: 1180px;
    width: 100%;
}

.calendar-container .paper-form {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04);
    margin: 0 auto;
    max-width: 1180px;
    padding: 24px 28px 28px;
}

.calendar-container .paper-form h1,
.page-header h1 {
    color: #111827;
    font-size: 22px;
    font-weight: 700;
    letter-spacing: 0.06em;
    line-height: 1.25;
    margin: 0;
    text-align: left;
    text-shadow: none;
}

.page-header {
    align-items: flex-end;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    flex-wrap: wrap;
    gap: 24px;
    justify-content: space-between;
    margin-bottom: 18px;
    padding-bottom: 18px;
    text-align: left;
}

.page-heading {
    flex: 1 1 280px;
    min-width: 220px;
}

.page-subtitle {
    color: #6b7280;
    font-size: 12.5px;
    line-height: 1.5;
    margin: 6px 0 0;
}

.view-only-badge {
    background: #eef5f0;
    border: 1px solid #abefc6;
    border-radius: 999px;
    color: #1E5E3A;
    display: inline-block;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.08em;
    padding: 3px 9px;
    text-transform: uppercase;
    vertical-align: middle;
}

.header-actions {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

/* Toolbar buttons */
.calendar-container .btn {
    align-items: center;
    background: #ffffff;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    box-shadow: none;
    color: #374151;
    cursor: pointer;
    display: inline-flex;
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    gap: 7px;
    height: 38px;
    justify-content: center;
    letter-spacing: 0.03em;
    margin: 0;
    padding: 0 16px;
    text-align: center;
    text-decoration: none;
    transition: background-color 0.18s ease, border-color 0.18s ease, color 0.18s ease;
    width: auto;
}

.calendar-container .btn:hover {
    background: #f9fafb;
    border-color: #9ca3af;
    box-shadow: none;
    transform: none;
}

.calendar-container .btn:active {
    box-shadow: none;
    transform: none;
}

.calendar-container .btn .material-icons {
    font-size: 18px;
}

.calendar-container .btn.primary,
.calendar-container .btn.success {
    background: #1E5E3A;
    border-color: #1E5E3A;
    color: #ffffff;
}

.calendar-container .btn.primary:hover,
.calendar-container .btn.success:hover {
    background: #174a2e;
    border-color: #174a2e;
}

.calendar-container .btn.secondary {
    background: #ffffff;
    border-color: #d1d5db;
    color: #374151;
}

.calendar-container .btn.secondary:hover {
    background: #f9fafb;
    border-color: #9ca3af;
}

.calendar-container .btn.danger {
    background: #ffffff;
    border-color: #fecdca;
    color: #b42318;
}

.calendar-container .btn.danger:hover {
    background: #fef3f2;
    border-color: #fda29b;
}

.calendar-container .btn.sm {
    font-size: 11.5px;
    height: 32px;
    padding: 0 12px;
}

/* Month / year toolbar */
.calendar-header {
    align-items: center;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    color: #111827;
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    justify-content: space-between;
    margin-bottom: 16px;
    padding: 12px 16px;
}

.calendar-nav {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
}

.calendar-month-nav {
    align-items: center;
    display: flex;
    gap: 10px;
}

.calendar-month-nav .btn {
    height: 34px;
    padding: 0;
    width: 34px;
}

.month-year {
    color: #111827;
    font-size: 16px;
    font-weight: 700;
    letter-spacing: 0.02em;
}

.calendar-year-picker {
    align-items: center;
    display: flex;
    gap: 8px;
}

.calendar-year-label {
    color: #6b7280;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.08em;
    opacity: 1;
    text-transform: uppercase;
}

.calendar-year-select {
    background: #ffffff;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    color: #111827;
    cursor: pointer;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    height: 38px;
    min-width: 104px;
    padding: 8px 12px;
}

.calendar-year-select:hover {
    background: #ffffff;
    border-color: #9ca3af;
}

.calendar-year-select:focus {
    border-color: #1E5E3A;
    box-shadow: 0 0 0 3px rgba(30, 94, 58, 0.12);
    outline: none;
}

/* Month grid */
.calendar-grid {
    background: #e5e7eb;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    gap: 1px;
    margin-bottom: 16px;
    overflow: hidden;
}

.calendar-day-header {
    background: #1E5E3A;
    color: #ffffff;
    font-size: 10.5px;
    font-weight: 600;
    letter-spacing: 0.08em;
    padding: 11px 8px;
    text-align: center;
    text-transform: uppercase;
}

.calendar-day {
    background: #ffffff;
    border: 0;
    min-height: 92px;
    padding: 8px;
}

.calendar-day:hover {
    background: #f9fafb;
}

.calendar-day.other-month {
    background: #f9fafb;
    color: #9ca3af;
}

.calendar-day.has-events {
    background: #fff7e6;
    box-shadow: inset 0 0 0 1px #f6c667;
}

.calendar-day.today {
    background: #eef5f0;
    box-shadow: inset 0 0 0 1px rgba(30, 94, 58, 0.3);
}

.calendar-day.today.has-events {
    background: #fff3d6;
    box-shadow: inset 0 0 0 2px #1E5E3A;
}

.day-number {
    color: #1f2937;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 4px;
}

.calendar-day.other-month .day-number {
    color: #9ca3af;
}

.calendar-day.today .day-number {
    color: #1E5E3A;
    font-weight: 700;
}

.event-item {
    background: #ecfdf3;
    border: 1px solid #abefc6;
    border-left: 3px solid #1E5E3A;
    border-radius: 4px;
    color: #067647;
    font-size: 9px;
    padding: 2px 5px;
}

.event-item:hover {
    background: #dcfae6;
}

.event-item.calendar-event-item {
    background: #eff6ff;
    border-color: #bfdbfe;
    border-left-color: #1d4ed8;
    color: #1d4ed8;
    -webkit-line-clamp: 3;
}

.event-time {
    display: block;
    font-size: 8px;
    font-weight: 600;
    line-height: 1.2;
    margin-top: 1px;
    opacity: 0.85;
}

.event-item.more-indicator {
    background: #f3f4f6;
    border-color: #e5e7eb;
    border-left-color: #9ca3af;
    color: #4b5563;
}

/* Year grid */
.year-grid {
    gap: 12px;
    margin-bottom: 16px;
}

.month-container {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    box-shadow: none;
    padding: 16px;
}

.clickable-month:hover {
    border-color: #1E5E3A;
    box-shadow: 0 1px 2px rgba(16, 24, 40, 0.06);
    transform: none;
}

.month-title {
    color: #111827;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.04em;
    margin-bottom: 10px;
    text-transform: uppercase;
}

.clickable-month:hover .month-title {
    color: #1E5E3A;
}

.mini-calendar {
    gap: 2px;
}

.mini-day-header {
    background: #1E5E3A;
    border-radius: 3px;
    color: #ffffff;
    font-size: 9.5px;
    padding: 4px 1px;
}

.mini-day {
    background: #ffffff;
    border: 1px solid #f3f4f6;
    border-radius: 3px;
    color: #4b5563;
    font-size: 10px;
}

.mini-day:hover {
    background: #f3f4f6;
}

.mini-day.other-month {
    background: #f9fafb;
    color: #9ca3af;
}

.mini-day.has-events {
    background: #fff7e6;
    box-shadow: inset 0 0 0 1px #f6c667;
}

.mini-day.today {
    background: #eef5f0;
    color: #1E5E3A;
    font-weight: 700;
}

.mini-day.today.has-events {
    background: #fff3d6;
    box-shadow: inset 0 0 0 2px #1E5E3A;
}

.mini-event-dot {
    background: #1E5E3A;
    height: 4px;
    width: 4px;
}

/* Footer / legend */
.calendar-footer {
    border-top: 1px solid #e5e7eb;
    margin-top: 16px;
    padding-top: 16px;
}

.legend {
    color: #6b7280;
    font-size: 11.5px;
    gap: 16px;
}

.legend-color {
    border-radius: 3px;
    height: 13px;
    width: 13px;
}

/* Event cards (if rendered) */
.events-section {
    margin-top: 20px;
}

.events-header h3 {
    color: #111827;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.event-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    box-shadow: none;
    margin-bottom: 10px;
    padding: 16px 18px;
}

.event-card:hover {
    box-shadow: none;
    transform: none;
}

.event-title {
    color: #111827;
    font-size: 14px;
    font-weight: 600;
}

.event-date-time,
.event-description {
    color: #6b7280;
    font-size: 12.5px;
}

/* Modals */
.modal {
    background: rgba(16, 24, 40, 0.5);
}

.modal-content {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    padding: 26px;
}

.modal-header {
    border-bottom: 1px solid #e5e7eb;
    margin-bottom: 18px;
    padding-bottom: 14px;
}

.modal-title {
    color: #111827;
    font-size: 16px;
    font-weight: 700;
    letter-spacing: 0.04em;
}

.close-btn {
    color: #9ca3af;
    transition: color 0.18s ease;
}

.close-btn:hover {
    color: #111827;
}

.form-group label {
    color: #6b7280;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.08em;
    margin-bottom: 6px;
    text-transform: uppercase;
}

.form-group input,
.form-group textarea,
.form-group select {
    background: #ffffff;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    color: #111827;
    font-family: inherit;
    font-size: 13.5px;
    padding: 9px 12px;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    border-color: #1E5E3A;
    box-shadow: 0 0 0 3px rgba(30, 94, 58, 0.12);
    outline: none;
}

.form-actions {
    gap: 10px;
    margin-top: 18px;
}

/* Request cards inside the day modal */
.requests-list {
    margin-bottom: 14px;
}

.request-item {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    margin-bottom: 10px;
    padding: 16px 18px;
}

.request-header {
    border-bottom: 1px solid #f3f4f6;
    margin-bottom: 12px;
    padding-bottom: 10px;
}

.request-header h4 {
    color: #111827;
    font-size: 13.5px;
    font-weight: 600;
}

.detail-row {
    color: #4b5563;
    font-size: 12.5px;
    gap: 8px;
}

.detail-row strong {
    color: #6b7280;
    font-size: 10px;
    letter-spacing: 0.06em;
    min-width: 120px;
    padding-top: 2px;
    text-transform: uppercase;
}

.status-badge {
    border: 1px solid transparent;
    border-radius: 999px;
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: 0.06em;
    padding: 4px 10px;
}

.status-badge.pending {
    background: #fffbeb;
    border-color: #fde68a;
    color: #92400e;
}

.status-badge.approved {
    background: #ecfdf3;
    border-color: #abefc6;
    color: #067647;
}

.status-badge.declined {
    background: #fef3f2;
    border-color: #fecdca;
    color: #b42318;
}

.priority-badge {
    border: 1px solid transparent;
    border-radius: 999px;
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: 0.06em;
    padding: 3px 9px;
}

.priority-high {
    background: #fef3f2;
    border-color: #fecdca;
    color: #b42318;
}

.priority-medium {
    background: #fffbeb;
    border-color: #fde68a;
    color: #92400e;
}

.priority-low {
    background: #ecfdf3;
    border-color: #abefc6;
    color: #067647;
}

.empty-state {
    color: #6b7280;
    font-size: 12.5px;
}

@media (max-width: 1100px) {
    .calendar-container .paper-form {
        padding: 20px;
    }

    .calendar-day {
        min-height: 78px;
        padding: 6px;
    }
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

    .header-actions {
        width: 100%;
    }

    .calendar-header {
        align-items: stretch;
        flex-direction: column;
    }

    .calendar-nav {
        width: 100%;
    }
}

@media (max-width: 768px) {
    .calendar-container {
        padding: 0;
    }

    .calendar-container .paper-form {
        border-radius: 10px;
        margin: 0 auto;
        overflow: visible;
        padding: 14px;
    }

    .page-header h1 {
        font-size: 20px;
    }

    .header-actions .btn {
        flex: 1 1 auto;
    }

    .calendar-header {
        padding: 12px;
    }

    .calendar-month-nav {
        justify-content: space-between;
        width: 100%;
    }

    .month-year {
        font-size: 15px;
    }

    .calendar-year-picker {
        justify-content: space-between;
        width: 100%;
    }

    .calendar-year-select {
        flex: 1 1 auto;
        min-width: 0;
    }

    .year-grid {
        gap: 10px;
    }

    .calendar-day {
        min-height: 64px;
        padding: 4px;
    }

    .calendar-day-header {
        font-size: 9.5px;
        padding: 8px 4px;
    }

    .event-item {
        font-size: 8px;
    }

    .modal-content {
        max-width: none;
        padding: 18px;
        width: 95%;
    }

    .detail-row strong {
        min-width: 0;
    }
}

@media (max-width: 400px) {
    .calendar-container .paper-form {
        padding: 10px;
    }

    .header-actions .btn {
        width: 100%;
    }

    .calendar-month-nav .btn {
        height: 32px;
        width: 32px;
    }

    .month-year {
        font-size: 14px;
    }

    .calendar-year-select {
        min-width: 0;
    }
}

</style>
</head>
<body>
    <div class="calendar-container">
        <div class="paper-form">
            <div class="page-header">
                <div class="page-heading">
                    <h1>CALENDAR <span class="view-only-badge">View Only</span></h1>
                    <p class="page-subtitle">View all scheduled vehicle trips and events created by the Planning Officer or Admin.</p>
                </div>

                <div class="header-actions">
                    <?php if ($view === 'month'): ?>
                        <a href="#" onclick="window.loadPage('pages/calendar.php?view=year&year=<?php echo $year; ?>'); return false;" class="btn secondary">
                            <span class="material-icons">arrow_back</span>
                            Back to Year
                        </a>
                    <?php endif; ?>
                    <a href="#" onclick="window.loadPage('pages/calendar.php?view=month&month=<?php echo (int)date('n'); ?>&year=<?php echo (int)date('Y'); ?>'); return false;" class="btn secondary">Today</a>
                </div>
            </div>

        <div class="calendar-header">
            <div class="calendar-nav">
                <?php if ($view === 'month' && $month): ?>
                    <!-- Month view keeps month-by-month arrows; the year itself is picked from the dropdown -->
                    <div class="calendar-month-nav">
                        <a href="#" onclick="window.loadPage('pages/calendar.php?view=month&month=<?php echo $month == 1 ? 12 : $month - 1; ?>&year=<?php echo $month == 1 ? $year - 1 : $year; ?>'); return false;" class="btn secondary">
                            <span class="material-icons" style="font-size: 18px; vertical-align: middle;">chevron_left</span>
                        </a>
                        <div class="month-year">
                            <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?>
                        </div>
                        <a href="#" onclick="window.loadPage('pages/calendar.php?view=month&month=<?php echo $month == 12 ? 1 : $month + 1; ?>&year=<?php echo $month == 12 ? $year + 1 : $year; ?>'); return false;" class="btn secondary">
                            <span class="material-icons" style="font-size: 18px; vertical-align: middle;">chevron_right</span>
                        </a>
                    </div>
                <?php endif; ?>
                <div class="calendar-year-picker">
                    <label for="calendarYearSelect" class="calendar-year-label">Year</label>
                    <select id="calendarYearSelect" class="calendar-year-select"
                            onchange="window.loadPage('pages/calendar.php?view=<?php echo $view === 'month' ? 'month' : 'year'; ?><?php echo ($view === 'month' && $month) ? '&month=' . (int)$month : ''; ?>&year=' + this.value);">
                        <?php foreach ($calendarYears as $y): ?>
                            <option value="<?php echo (int)$y; ?>" <?php echo (int)$y === $year ? 'selected' : ''; ?>><?php echo (int)$y; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <?php if ($view === 'month' && $month): ?>
            <!-- Month View -->
            <div class="calendar-grid">
                <?php
                // Day headers
                $dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                foreach ($dayHeaders as $day) {
                    echo '<div class="calendar-day-header">' . $day . '</div>';
                }
                
                // Get first day of month and number of days
                $firstDay = date('w', mktime(0, 0, 0, $month, 1, $year));
                $daysInMonth = date('t', mktime(0, 0, 0, $month, 1, $year));
                $daysInPrevMonth = date('t', mktime(0, 0, 0, $month - 1, 1, $year));
                
                // Previous month trailing days
                for ($i = $firstDay - 1; $i >= 0; $i--) {
                    $day = $daysInPrevMonth - $i;
                    echo '<div class="calendar-day other-month">';
                    echo '<div class="day-number">' . $day . '</div>';
                    echo '</div>';
                }
                
                // Current month days
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $currentDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $isToday = ($currentDate === date('Y-m-d'));
                    $hasEvents = isset($events_by_date[$currentDate]);
                    
                    echo '<div class="calendar-day' . ($isToday ? ' today' : '') . ($hasEvents ? ' has-events' : '') . '" onclick="openEventModal(\'' . $currentDate . '\')">';
                    echo '<div class="day-number">' . $day . '</div>';
                    
                    if ($hasEvents) {
                        // Show calendar events (own events) - styled differently
                        foreach ($events_by_date[$currentDate] as $event_item) {
                            if ($event_item['type'] === 'calendar_event') {
                                $event = $event_item['data'];
                                $eventTime = format_event_time($event['event_time'] ?? '');
                                echo '<div class="event-item calendar-event-item">';
                                echo htmlspecialchars($event['title']);
                                if ($eventTime !== '') {
                                    echo '<span class="event-time">' . htmlspecialchars($eventTime) . '</span>';
                                }
                                echo '</div>';
                            }
                        }

                        // Show requested by and destination information for vehicle requests
                        $request_count = 0;
                        foreach ($events_by_date[$currentDate] as $event_item) {
                            if ($event_item['type'] === 'vehicle_request') {
                                $request = $event_item['data'];
                                $vehicleLabel = trim(($request['vehicle_plate'] ?? '') . ' ' . (!empty($request['vehicle_model']) ? $request['vehicle_model'] : ($request['vehicle_type'] ?? '')));
                                $driverLabel = !empty($request['request_driver']) ? $request['request_driver'] : ($request['fleet_driver_name'] ?? '');
                                echo '<div class="event-item request-event">';
                                echo '<strong>' . htmlspecialchars($request['destination']) . '</strong><br>';
                                echo 'by ' . htmlspecialchars(!empty($request['faculty_name']) ? $request['faculty_name'] : $request['requested_by']);
                                if ($vehicleLabel !== '') {
                                    echo '<br><span class="event-vehicle">Vehicle: ' . htmlspecialchars($vehicleLabel) . '</span>';
                                }
                                if ($driverLabel !== '') {
                                    echo '<br><span class="event-driver">Driver: ' . htmlspecialchars($driverLabel) . '</span>';
                                }
                                echo '</div>';
                                $request_count++;
                                // Limit to showing max 2 requests to avoid overcrowding
                                if ($request_count >= 2) break;
                            }
                        }
                        // If there are more than 2 requests, show a count
                        if ($request_count < count(array_filter($events_by_date[$currentDate], function($item) {
                            return $item['type'] === 'vehicle_request';
                        }))) {
                            echo '<div class="event-item more-indicator">+' . (count(array_filter($events_by_date[$currentDate], function($item) {
                                return $item['type'] === 'vehicle_request';
                            })) - $request_count) . ' more</div>';
                        }
                    }
                    
                    echo '</div>';
                }
                
                // Next month leading days
                $totalCells = $firstDay + $daysInMonth;
                $remainingCells = 35 - $totalCells;
                for ($day = 1; $day <= $remainingCells; $day++) {
                    echo '<div class="calendar-day other-month">';
                    echo '<div class="day-number">' . $day . '</div>';
                    echo '</div>';
                }
                ?>
            </div>
        <?php else: ?>
            <!-- Year View -->
            <div class="year-grid">
                <?php
                // Generate all 12 months
                for ($m = 1; $m <= 12; $m++) {
                    echo '<div class="month-container clickable-month" onclick="window.loadPage(\'pages/calendar.php?view=month&month=' . $m . '&year=' . $year . '\')">';
                    echo '<div class="month-title">' . date('F', mktime(0, 0, 0, $m, 1, $year)) . '</div>';
                    echo '<div class="mini-calendar">';
                    
                    // Day headers for mini calendar
                    $dayHeaders = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
                    foreach ($dayHeaders as $day) {
                        echo '<div class="mini-day-header">' . $day . '</div>';
                    }
                    
                    // Get first day of month and number of days
                    $firstDay = date('w', mktime(0, 0, 0, $m, 1, $year));
                    $daysInMonth = date('t', mktime(0, 0, 0, $m, 1, $year));
                    $daysInPrevMonth = date('t', mktime(0, 0, 0, $m - 1, 1, $year));
                    
                    // Previous month trailing days
                    for ($i = $firstDay - 1; $i >= 0; $i--) {
                        $day = $daysInPrevMonth - $i;
                        echo '<div class="mini-day other-month">' . $day . '</div>';
                    }
                    
                    // Current month days
                    for ($day = 1; $day <= $daysInMonth; $day++) {
                        $currentDate = sprintf('%04d-%02d-%02d', $year, $m, $day);
                        $isToday = ($currentDate === date('Y-m-d'));
                        $hasEvents = isset($events_by_date[$currentDate]);
                        
                        echo '<div class="mini-day' . ($isToday ? ' today' : '') . ($hasEvents ? ' has-events' : '') . '" onclick="window.loadPage(\'pages/calendar.php?view=month&month=' . $m . '&year=' . $year . '\')">';
                        echo $day;
                        if ($hasEvents) {
                            $dayLabels = [];
                            foreach ($events_by_date[$currentDate] as $event_item) {
                                if ($event_item['type'] === 'calendar_event') {
                                    $label = $event_item['data']['title'];
                                    $timeLabel = format_event_time($event_item['data']['event_time'] ?? '');
                                    if ($timeLabel !== '') {
                                        $label .= ' (' . $timeLabel . ')';
                                    }
                                    $dayLabels[] = $label;
                                } elseif ($event_item['type'] === 'vehicle_request') {
                                    $dayLabels[] = 'Vehicle request: ' . $event_item['data']['destination'];
                                }
                            }
                            echo '<div class="mini-event-dot" title="' . htmlspecialchars(implode(' | ', $dayLabels)) . '"></div>';
                        }
                        echo '</div>';
                    }
                    
                    // Next month leading days
                    $totalCells = $firstDay + $daysInMonth;
                    $remainingCells = 35 - $totalCells;
                    for ($day = 1; $day <= $remainingCells; $day++) {
                        echo '<div class="mini-day other-month">' . $day . '</div>';
                    }
                    
                    echo '</div>';
                    echo '</div>';
                }
                ?>
            </div>
        <?php endif; ?>

        <div class="calendar-footer">
            <div class="legend">
                <div class="legend-item">
                    <div class="legend-color" style="background: #eef5f0; border: 2px solid #1E5E3A;"></div>
                    <span>Today</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #ecfdf3; border: 1px solid #abefc6;"></div>
                    <span>Has Requests</span>
                </div>
            </div>
        </div>
    </div>
    </div>

    <!-- Vehicle Requests Modal -->
    <div class="modal" id="requestsModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Schedule for <span id="selectedDate"></span></div>
                <button class="close-btn" onclick="closeRequestsModal()">&times;</button>
            </div>
            <div class="requests-list" id="requestsList">
                <!-- Vehicle requests will be loaded here -->
            </div>
            <div class="form-actions">
                <button type="button" class="btn secondary" onclick="closeRequestsModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        // Make functions global so they work with dynamic loading
        window.zoomToMonth = function(month, year) {
            window.location.href = `?view=month&month=${month}&year=${year}`;
        };

        // Render a stored MySQL TIME ("07:30:00") as a friendly clock time
        // ("7:30 AM"). Anything unexpected is shown unchanged.
        window.formatEventTime = function(value) {
            const text = String(value || '').trim();
            const match = text.match(/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/);
            if (!match) return text;

            const hour24 = Number(match[1]);
            const minute = Number(match[2]);
            const second = Number(match[3] || 0);
            if (hour24 > 23 || minute > 59 || second > 59) return text;

            const period = hour24 >= 12 ? 'PM' : 'AM';
            const hour12 = hour24 % 12 === 0 ? 12 : hour24 % 12;
            return `${hour12}:${String(minute).padStart(2, '0')} ${period}`;
        };

        window.openEventModal = function(date = '') {
            document.getElementById('selectedDate').textContent = date;
            document.getElementById('requestsModal').style.display = 'block';

            <?php
            // Pass the events data to JavaScript
            echo "const allEvents = " . json_encode($events_by_date) . ";";
            ?>

            const eventsForDate = [];
            const requestsForDate = [];

            if (allEvents[date]) {
                allEvents[date].forEach(event_item => {
                    if (event_item.type === 'calendar_event') {
                        eventsForDate.push(event_item.data);
                    } else if (event_item.type === 'vehicle_request') {
                        requestsForDate.push(event_item.data);
                    }
                });
            }

            const requestsList = document.getElementById('requestsList');
            let html = '';

            // Show calendar events
            if (eventsForDate.length > 0) {
                html += '<h4 style="margin-bottom: 10px; color: #1976d2;">CALENDAR EVENTS</h4>';
                eventsForDate.forEach(event => {
                    html += `
                        <div class="request-item" style="border-left: 4px solid #1976d2;">
                            <div class="request-header">
                                <h4>${event.title}</h4>
                            </div>
                            <div class="request-details">
                                ${event.event_time ? `<div class="detail-row"><strong>TIME:</strong> ${window.formatEventTime(event.event_time)}</div>` : ''}
                                ${event.description ? `<div class="detail-row"><strong>DESCRIPTION:</strong> ${event.description}</div>` : ''}
                            </div>
                        </div>
                    `;
                });
            }

            // Show vehicle requests
            if (requestsForDate.length > 0) {
                html += '<h4 style="margin: 15px 0 10px; color: #1a4d2e;">VEHICLE REQUESTS</h4>';
                requestsForDate.forEach((request, index) => {
                    const vehicleLabel = [request.vehicle_plate, request.vehicle_model || request.vehicle_type].filter(Boolean).join(' ');
                    const driverLabel = request.request_driver || request.fleet_driver_name || '';
                    html += `
                        <div class="request-item">
                            <div class="request-header">
                                <h4>Request #${request.id} - ${request.faculty_name ? request.faculty_name : request.requested_by}</h4>
                            </div>
                            <div class="request-details">
                                <div class="detail-row">
                                    <strong>REQUESTED BY:</strong> ${request.faculty_name ? request.faculty_name : request.requested_by}
                                </div>
                                <div class="detail-row">
                                    <strong>DESTINATION:</strong> ${request.destination}
                                </div>
                                <div class="detail-row">
                                    <strong>PURPOSE:</strong> ${request.purpose}
                                </div>
                                <div class="detail-row">
                                    <strong>PASSENGERS:</strong> ${request.passengers}
                                </div>
                                <div class="detail-row">
                                    <strong>VEHICLE:</strong> ${vehicleLabel || 'Not assigned yet'}
                                </div>
                                <div class="detail-row">
                                    <strong>DRIVER:</strong> ${driverLabel || 'Not assigned yet'}
                                </div>
                                <div class="detail-row">
                                    <strong>TRAVEL DATE:</strong> ${request.travel_date}
                                </div>
                                <div class="detail-row">
                                    <strong>REQUEST DATE:</strong> ${new Date(request.request_date).toLocaleString()}
                                </div>
                                ${request.notes ? `<div class="detail-row"><strong>NOTES:</strong> ${request.notes}</div>` : ''}
                            </div>
                        </div>
                    `;
                });
            }

            if (html === '') {
                html = '<p style="text-align: center; color: #666;">No events or vehicle requests for this date.</p>';
            }

            requestsList.innerHTML = html;
        };

        window.closeRequestsModal = function() {
            const modal = document.getElementById('requestsModal');
            if (modal) {
                modal.style.display = 'none';
            }
        };

        // Close modal when clicking outside
        window.onclick = function(event) {
            const requestsModal = document.getElementById('requestsModal');
            if (event.target === requestsModal) {
                window.closeRequestsModal();
            }
        }
        // Keyboard shortcuts (bound once, even when the calendar is reloaded)
        if (!window.facultyCalendarEscapeBound) {
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    window.closeRequestsModal();
                }
            });
            window.facultyCalendarEscapeBound = true;
        }

        // Keep the read-only calendar in sync with schedules added or changed by
        // the Planning Officer/Admin. Refresh only while the calendar is mounted
        // and no schedule modal is open, so it never interrupts someone reading.
        if (window.facultyCalendarRefreshTimer) {
            clearInterval(window.facultyCalendarRefreshTimer);
        }
        window.facultyCalendarRefreshTimer = setInterval(function () {
            if (!document.querySelector('.calendar-container')) {
                clearInterval(window.facultyCalendarRefreshTimer);
                window.facultyCalendarRefreshTimer = null;
                return;
            }
            if (document.hidden) return;
            const modal = document.getElementById('requestsModal');
            if (modal && modal.style.display === 'block') return;

            const calendarQuery = '?view=<?php echo $view === 'month' ? 'month' : 'year'; ?><?php echo ($view === 'month' && $month) ? '&month=' . (int)$month : ''; ?>&year=<?php echo (int)$year; ?>';
            if (typeof window.loadPage === 'function') {
                window.loadPage('pages/calendar.php' + calendarQuery);
            } else {
                window.location.href = window.location.pathname + calendarQuery;
            }
        }, 60000);
    </script>
<?php include __DIR__ . '/../includes/custom_alert_modal.php'; ?>

</body>
</html>
