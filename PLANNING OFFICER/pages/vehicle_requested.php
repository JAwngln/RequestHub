<?php
session_start();
if (!isset($_SESSION['email']) || trim($_SESSION['role']) !== 'planning_officer') {
    header("Location: ../../auth/login.php");
    exit();
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/Prototype/connect.php';
require_once __DIR__ . '/../includes/fleet_schema.php';
require_once __DIR__ . '/../includes/year_filter.php';

// Guarantees vehicle_requests.vehicle_id exists before this page joins on it.
ensureFleetSchema($conn);

$currentYear = (int)date('Y');
$availableYears = getAvailableYears($conn, 'vehicle_requests', 'travel_date');

// Deep link used by the sidebar badge. The badge counts pending requests across all
// years, but this page defaults to the current year, so without ?show=pending a badge
// reading "1" would land on a list that looks empty. Opening the badge clears the
// year and month filters so every pending request is actually on screen.
$showPendingAll = (isset($_GET['show']) && $_GET['show'] === 'pending');

// Fetch vehicle requests from database.
// Columns are listed explicitly instead of "vr.*" because attachment_data is a
// LONGBLOB: pulling it in made json_encode() below fail on malformed UTF-8, which
// returned false, rendered the script as "var allVehicleRequestsData = ;" and threw a
// SyntaxError that silently killed every handler in that block. Attachment bytes are
// served on demand by view_attachment.php, so they must never reach this page's JSON.
$requests = [];
$sql = "SELECT vr.id, vr.ticket_no, vr.ticket_year, vr.travel_date, vr.destination,
                vr.purpose, vr.passengers, vr.driver, vr.vehicle_id, vr.requested_by, vr.user_email,
               vr.attachment_name, vr.request_date, vr.status, vr.reviewed_at,
               vr.notes, vr.created_at,
               vf.plate_number AS vehicle_plate, vf.vehicle_type AS vehicle_type,
               vf.model AS vehicle_model,
        (SELECT CONCAT(u.firstName, ' ', u.lastName) FROM users u 
         WHERE u.email = vr.user_email
            OR ((vr.user_email IS NULL OR vr.user_email = '') AND LOWER(CONCAT(u.firstName, ' ', u.lastName)) = LOWER(vr.requested_by))) AS faculty_name
        FROM vehicle_requests vr
        LEFT JOIN vehicle_fleet vf ON vf.id = vr.vehicle_id
        ORDER BY vr.id DESC";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
}

/**
 * Trip Ticket label: the per-travel-year sequence, zero-padded to 001, 002 ...
 *
 * Display-only. vehicle_requests.id still drives every approve/print action, so
 * it must never be shown as the ticket. Keep this in sync with the JS
 * formatTicketNo() below - both render paths must agree or the number flickers.
 */
function ticketLabel($row) {
    if (!is_array($row) || !isset($row['ticket_no']) || $row['ticket_no'] === null || $row['ticket_no'] === '') {
        return '---';
    }
    return sprintf('%03d', (int)$row['ticket_no']);
}
?>

<link href="/Prototype/assets/fonts/material-icons.css" rel="stylesheet">
<link href="/Prototype/assets/fonts/poppins.css" rel="stylesheet">

<style>
/* ================= VEHICLE REQUESTED STYLES ================= */
/* Scoped to prevent conflicts with dashboard buttons */

h2 {
    text-align: left;
}

/* Attachment Viewer Modal */
.attachment-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.75);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
    box-sizing: border-box;
}

.attachment-modal.show {
    display: flex;
}

.attachment-modal-content {
    background: white;
    border-radius: 10px;
    max-width: 85%;
    max-height: 90%;
    width: auto;
    padding: 20px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.3);
    position: relative;
    overflow: auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    transition: width 0.2s ease, height 0.2s ease, max-width 0.2s ease, max-height 0.2s ease, padding 0.2s ease;
}

.attachment-modal-topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    width: 100%;
    margin-bottom: 10px;
}

.attachment-modal-close {
    background: #dc3545;
    color: white;
    border: none;
    border-radius: 50%;
    width: 34px;
    height: 34px;
    font-size: 18px;
    cursor: pointer;
    margin-bottom: 0;
    align-self: flex-start;
}

.attachment-modal-fullscreen {
    background: #007bff;
    color: white;
    border: none;
    border-radius: 50%;
    width: 34px;
    height: 34px;
    font-size: 18px;
    cursor: pointer;
    align-self: flex-start;
}

/* Fullscreen state: fills the whole viewport */
.attachment-modal.show-fullscreen .attachment-modal-content {
    max-width: 100%;
    max-height: 100%;
    width: 100%;
    height: 100%;
    border-radius: 0;
    padding: 15px;
    box-sizing: border-box;
    background: #ffffff;
    box-shadow: none;
}

/* Make the content wrapper fill the whole screen so the image can enlarge */
.attachment-modal.show-fullscreen #attachmentModalContent {
    flex: 1;
    width: 100%;
    min-height: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: auto;
}

.attachment-modal.show-fullscreen .attachment-modal img {
    width: 100%;
    height: 100%;
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    border-radius: 0;
    -ms-interpolation-mode: nearest-neighbor;
}

.attachment-modal.show-fullscreen .attachment-modal iframe {
    width: 100%;
    min-height: 92vh;
}

.attachment-modal img {
    max-width: 100%;
    max-height: 70vh;
    border-radius: 6px;
    display: block;
}

.attachment-modal iframe {
    width: 100%;
    min-height: 60vh;
    border: none;
    border-radius: 6px;
}

.attachment-modal .modal-filename {
    font-weight: 600;
    color: #1c6b2f;
    margin-bottom: 10px;
    text-align: center;
    word-break: break-all;
}

.view-attachment-btn {
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
    border: none;
    border-radius: 6px;
    padding: 6px 12px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    margin-top: 5px;
    transition: all 0.2s ease;
}

.view-attachment-btn:hover {
    background: linear-gradient(135deg, #0056b3, #004085);
}

/* Button Styles - Scoped to prevent dashboard conflicts */
.vehicle-requested-container .btn {
    padding: 10px;
    border-radius: 20px;
    border: none;
    cursor: pointer;
    font-weight: bold;
    font-size: 14px;
    transition: all 0.2s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-align: center;
    width: auto;
    margin-bottom: 10px;
}

#mainContent .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 3px 6px rgba(0,0,0,0.15);
}

#mainContent .btn:active {
    transform: translateY(0);
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

#mainContent .btn.primary {
    background: #007bff;
    color: white;
}

#mainContent .btn.primary:hover {
    background: #0056b3;
}

#mainContent .btn.secondary {
    background: #1c6b2f;
    color: white;
    border: 1px solid white;
}

#mainContent .btn.secondary:hover {
    background: #155724;
    border-color: #155724;
}

#mainContent .btn.pending {
    background: #ffc107;
    color: #856404;
}

#mainContent .btn.pending:hover {
    background: #e0a800;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(255,193,7,0.3);
}

#mainContent .btn.success {
    background: #28a745;
    color: white;
}

#mainContent .btn.success:hover {
    background: #1e7e34;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(40,167,69,0.3);
}

#mainContent .btn.danger {
    background: #dc3545;
    color: white;
}

#mainContent .btn.danger:hover {
    background: #c82333;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(220,53,69,0.3);
}

/* Paper Form - Matching Other Pages */
.paper-form {
    background: white;
    border-radius: 10px;
    padding: 30px;
    margin: 20px auto;
    max-width: 1200px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    border: 1px solid #e0e0e0;
}

.paper-form h1 {
    color: #1a4d2e;
    text-align: center;
    margin-bottom: 30px;
    font-size: 28px;
    font-weight: 600;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
}

/* Time Filter Section - Matching Other Pages */
.time-filter-section {
    background: rgba(26, 77, 46, 0.05);
    border: 1px solid rgba(26, 77, 46, 0.1);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    justify-content: center;
}

.time-select {
    padding: 8px 12px;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    font-size: 14px;
    background: white;
    min-width: 150px;
    cursor: pointer;
    height: 36px;
    box-sizing: border-box;
}

.time-select:focus {
    outline: none;
    border-color: #1a4d2e;
    box-shadow: 0 0 0 2px rgba(26, 77, 46, 0.3);
}

.time-filter-section .btn {
    padding: 8px 16px;
    font-size: 13px;
    min-width: auto;
    white-space: nowrap;
    height: 36px;
    box-sizing: border-box;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

/* Filter Section - Matching Other Pages */
.filter-section {
    background: rgba(26, 77, 46, 0.05);
    border: 1px solid rgba(26, 77, 46, 0.1);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    justify-content: center;
}

.filter-section .btn {
    margin: 0;
    white-space: nowrap;
    padding: 10px 20px;
    border-radius: 20px;
    border: none;
    cursor: pointer;
    font-weight: bold;
    font-size: 14px;
    transition: all 0.2s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    min-width: 140px;
}

/* Colored Filter Buttons - Original Design */
.filter-section .btn.primary {
    background: #007bff;
    color: white;
}

.filter-section .btn.primary:hover {
    background: #0056b3;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,87,255,0.3);
}

.filter-section .btn.pending {
    background: #ffc107;
    color: #856404;
}

.filter-section .btn.pending:hover {
    background: #e0a800;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(255,193,7,0.3);
}

.filter-section .btn.success {
    background: #28a745;
    color: white;
}

.filter-section .btn.success:hover {
    background: #1e7e34;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(40,167,69,0.3);
}

.filter-section .btn.danger {
    background: #dc3545;
    color: white;
}

.filter-section .btn.danger:hover {
    background: #c82333;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(220,53,69,0.3);
}

.filter-section .btn.summary {
    background: #1a4d2e;
    color: white;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.filter-section .btn.summary:hover {
    background: #0f3520;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(26,77,46,0.35);
}

/* Summary Export Modal */
.summary-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.6);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.summary-modal.show {
    display: flex;
}

.summary-modal-content {
    background: white;
    border-radius: 10px;
    width: 100%;
    max-width: 1000px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    overflow: hidden;
}

.summary-modal-header {
    padding: 18px 22px;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #1a4d2e;
    color: white;
    flex-shrink: 0;
}

.summary-modal-header h2 {
    margin: 0;
    font-size: 1.15rem;
    color: white;
    text-align: left;
}

.summary-modal-close {
    background: none;
    border: none;
    color: white;
    font-size: 1.8rem;
    cursor: pointer;
    line-height: 1;
    padding: 0 4px;
}

.summary-modal-body {
    padding: 20px 22px;
    overflow-y: auto;
    flex: 1;
}

.summary-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    align-items: center;
    margin-bottom: 20px;
}

.summary-month-picker {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.summary-month-label {
    font-size: 13px;
    font-weight: 600;
    color: #495057;
    white-space: nowrap;
}

.summary-month-select {
    min-width: 190px;
    padding: 10px 12px;
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

.summary-month-select:focus {
    outline: none;
    border-color: #1a4d2e;
    box-shadow: 0 0 0 3px rgba(26, 77, 46, 0.15);
}

.summary-table-wrap {
    overflow-x: auto;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
}

.summary-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.summary-table th {
    background: #1a4d2e;
    color: white;
    padding: 10px 12px;
    text-align: left;
    font-weight: 600;
    white-space: nowrap;
}

.summary-table td {
    padding: 9px 12px;
    border-bottom: 1px solid #eee;
    vertical-align: top;
}

.summary-table tr:nth-child(even) td {
    background: #fafafa;
}

.summary-table .month-group td {
    background: #e8f5e9;
    font-weight: 700;
    color: #1a4d2e;
    padding: 8px 12px;
}

.summary-modal-footer {
    padding: 16px 22px;
    border-top: 1px solid #e0e0e0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    background: #fafafa;
    flex-shrink: 0;
}

.summary-download-btn {
    background: #28a745;
    color: white;
    border: none;
    padding: 11px 22px;
    border-radius: 6px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.summary-download-btn:hover {
    background: #1e7e34;
}

.summary-download-btn.disabled {
    background: #adb5bd;
    cursor: not-allowed;
    pointer-events: none;
}

.summary-meta {
    font-size: 13px;
    color: #6c757d;
}

.summary-empty {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
}

.summary-empty i {
    font-size: 3rem;
    color: #dee2e6;
    display: block;
    margin-bottom: 12px;
}

@media (max-width: 600px) {
    .summary-modal-footer {
        flex-direction: column;
        align-items: stretch;
    }

    .summary-download-btn {
        justify-content: center;
    }
}

/* Request List */
.request-list {
    margin-top: 20px;
}

/* Request Table Container */
.table-container {
    max-height: 500px;
    overflow-y: auto;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    margin: 20px 0;
    background: white;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

/* Request Table */
.request-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 0;
    overflow: visible;
    margin: 0;
}

/* Status Badge - Original Design */
.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    display: inline-block;
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

/* Loading and Empty States - Original Design */
.loading {
    text-align: center;
    padding: 20px;
    color: #6c757d;
    font-style: italic;
}

.empty-message {
    text-align: center;
    padding: 30px;
    color: #6c757d;
}

.empty-message h3 {
    margin-bottom: 10px;
    color: #495057;
}

.empty-message p {
    margin: 0;
    font-size: 14px;
}

/* Error Message - Original Design */
.error-message {
    text-align: center;
    padding: 20px;
    color: #dc3545;
    background: rgba(220, 53, 69, 0.1);
    border-radius: 8px;
    margin: 20px 0;
}

/* Responsive Design - Faculty Style with Chrome Fixes */
/* ===== RESPONSIVE FIXES ===== */

/* Chrome-specific box-sizing fix */
* {
    -webkit-box-sizing: border-box;
    -moz-box-sizing: border-box;
    box-sizing: border-box;
}

/* Tablet */
@media (max-width: 768px) {
    .paper-form {
        padding: 16px;
        margin: 10px;
        border-radius: 8px;
        max-width: 100%;
        width: 100%;
    }

    .paper-form h1 {
        font-size: 22px;
        margin-bottom: 20px;
    }

    .time-filter-section {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
        padding: 14px;
        -webkit-flex-direction: column;
        -webkit-align-items: stretch;
    }

    .time-select {
        width: 100%;
        box-sizing: border-box;
        -webkit-box-sizing: border-box;
    }

    .time-filter-section .btn {
        width: 100%;
        height: 40px;
        min-width: unset;
    }

    .filter-section {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
        padding: 14px;
        -webkit-flex-direction: column;
        -webkit-align-items: stretch;
    }

    .filter-section .btn {
        width: 100%;
        min-width: unset;
        box-sizing: border-box;
        -webkit-box-sizing: border-box;
        text-align: center;
    }

    .request-table {
        font-size: 12px;
    }

    .request-table th,
    .request-table td {
        padding: 8px 10px;
    }

   .decision-options {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 15px;
}

    .reason-input {
    width: 100%;
    box-sizing: border-box;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    margin-top: 4px;
}

    .decision-actions {
        flex-direction: column;
        gap: 8px;
        -webkit-flex-direction: column;
    }


}

/* Mobile phones (Chrome fix) */
@media (max-width: 576px) {
    .paper-form {
        padding: 12px;
        margin: 6px;
    }

    .paper-form h1 {
        font-size: 18px;
    }

    .time-filter-section,
    .filter-section {
        padding: 12px;
        gap: 8px;
    }

    .filter-section .btn {
        font-size: 13px;
        padding: 10px 16px;
        border-radius: 20px;
        width: 100%;
        -webkit-appearance: none;
    }

    .request-item {
        padding: 12px;
    }

    .request-header {
        flex-wrap: wrap;
        gap: 6px;
        -webkit-flex-wrap: wrap;
    }

    .status-badge {
        font-size: 11px;
        padding: 3px 10px;
    }

    .request-details p {
        font-size: 13px;
    }

    .table-container {
        margin: 10px 0;
    }

    .time-select {
        font-size: 14px;
        -webkit-appearance: none;
    }
}

/* Extra small phones */
@media (max-width: 400px) {
    .paper-form {
        padding: 10px;
        margin: 4px;
    }

    .paper-form h1 {
        font-size: 16px;
    }

    .filter-section .btn {
        font-size: 12px;
        padding: 8px 12px;
    }

    .request-item {
        padding: 10px;
    }

    .request-details p {
        font-size: 12px;
    }
}

/* Request Item Styles */
.request-item {
    background: white;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 15px;
    margin-bottom: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.request-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.request-number {
    font-size: 14px;
    font-weight: bold;
    color: #1c6b2f;
}

.request-date {
    color: #666;
    font-size: 14px;
}

.request-details {
    margin-bottom: 15px;
}

.request-details p {
    margin: 5px 0;
    font-size: 14px;
}

.request-actions {
    text-align: right;
}

/* Decision Section */
.decision-section {
    margin-top: 20px;
    padding: 15px;
    background: #f8f9fa;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
}



.radio-option {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    font-size: 14px;
    width: 100%;
}

.radio-option input[type="radio"] {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    cursor: pointer;
    margin: 0;
}

.radio-option span {
    flex: 1;
}


.reason-input:focus {
    outline: none;
    border-color: #1a4d2e;
    box-shadow: 0 0 0 2px rgba(26, 77, 46, 0.2);
}

/* ================= ENTERPRISE DASHBOARD REFRESH ================= */
/* Header + filter row */
.paper-form {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04);
    margin: 0 auto;
    max-width: 1180px;
    padding: 24px 28px 28px;
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

.page-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 24px;
    flex-wrap: wrap;
    margin-bottom: 16px;
    padding-bottom: 18px;
    border-bottom: 1px solid #e5e7eb;
}

.page-heading {
    flex: 1 1 280px;
    min-width: 220px;
}

.page-subtitle {
    margin: 6px 0 0;
    color: #6b7280;
    font-size: 12.5px;
    line-height: 1.5;
}

.filter-controls {
    display: flex;
    align-items: flex-end;
    gap: 10px;
    flex-wrap: wrap;
}

.filter-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.filter-label {
    color: #6b7280;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.filter-controls .time-select {
    height: 36px;
    min-width: 132px;
    padding: 6px 10px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    background: #ffffff;
    color: #111827;
    font-family: inherit;
    font-size: 13px;
    cursor: pointer;
}

.filter-controls .time-select:focus {
    outline: none;
    border-color: #1E5E3A;
    box-shadow: 0 0 0 3px rgba(30, 94, 58, 0.12);
}

.filter-btn {
    height: 36px;
    padding: 0 16px;
    border: 1px solid transparent;
    border-radius: 8px;
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.03em;
    cursor: pointer;
    transition: background-color 0.18s ease, border-color 0.18s ease, color 0.18s ease;
}

.filter-btn-primary {
    background: #1E5E3A;
    color: #ffffff;
}

.filter-btn-primary:hover {
    background: #174a2e;
}

.filter-btn-ghost {
    background: #ffffff;
    border-color: #d1d5db;
    color: #374151;
}

.filter-btn-ghost:hover {
    background: #f9fafb;
    border-color: #9ca3af;
}

/* Status segmented control + summary action */
.vr-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.vr-tabs {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    max-width: 100%;
    padding: 4px;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    background: #f3f4f6;
    overflow-x: auto;
}

.vr-tab {
    appearance: none;
    border: 0;
    border-radius: 7px;
    background: transparent;
    color: #4b5563;
    padding: 8px 16px;
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.03em;
    white-space: nowrap;
    cursor: pointer;
    transition: background-color 0.18s ease, color 0.18s ease;
}

.vr-tab:hover {
    background: rgba(255, 255, 255, 0.7);
    color: #111827;
}

.vr-tab.active {
    background: #ffffff;
    color: #1E5E3A;
    box-shadow: 0 1px 2px rgba(16, 24, 40, 0.08);
}

.vr-tab:focus-visible {
    outline: 2px solid #1E5E3A;
    outline-offset: 2px;
}

.vr-summary-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    height: 38px;
    padding: 0 16px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    background: #ffffff;
    color: #1E5E3A;
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.03em;
    cursor: pointer;
    transition: background-color 0.18s ease, border-color 0.18s ease;
}

.vr-summary-btn:hover {
    background: #f0f7f3;
    border-color: #1E5E3A;
}

.vr-summary-btn .material-icons {
    font-size: 18px;
}

/* Cards */
.table-container {
    max-height: none;
    margin: 0;
    border: 0;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
    overflow: visible;
}

.request-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    width: 100%;
    max-width: 100%;
    margin-top: 0;
}

.request-item {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 18px 20px;
    margin: 0;
    box-shadow: none;
}

.request-item:hover {
    border-color: #d1d5db;
}

.request-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 14px;
}

.request-heading {
    display: flex;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 8px;
    min-width: 0;
}

.request-number {
    color: #1E5E3A;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.02em;
    white-space: nowrap;
}

.request-subject {
    color: #374151;
    font-size: 13px;
    font-weight: 500;
    overflow-wrap: anywhere;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    flex-shrink: 0;
    padding: 4px 10px;
    border: 1px solid transparent;
    border-radius: 999px;
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: 0.06em;
    line-height: 1.5;
    text-transform: uppercase;
    white-space: nowrap;
}

.status-badge::before {
    content: "";
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
    opacity: 0.85;
}

.status-badge.status-pending,
.status-badge.pending {
    background: #fffbeb;
    border-color: #fde68a;
    color: #92400e;
}

.status-badge.status-approved,
.status-badge.approved {
    background: #ecfdf3;
    border-color: #abefc6;
    color: #067647;
}

.status-badge.status-declined,
.status-badge.declined {
    background: #fef3f2;
    border-color: #fecdca;
    color: #b42318;
}

.request-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px 22px;
    margin-bottom: 0;
}

.request-details p {
    margin: 0;
    font-size: 14px;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-width: 0;
}

.detail-label {
    color: #6b7280;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.detail-value {
    color: #1f2937;
    font-size: 13.5px;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

.detail-item-full {
    grid-column: 1 / -1;
}

.view-attachment-btn {
    width: fit-content;
    margin-top: 0;
    padding: 6px 10px;
    border: 1px solid #bfdbfe;
    border-radius: 6px;
    background: #eff6ff;
    color: #1d4ed8;
    font-family: inherit;
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.18s ease;
}

.view-attachment-btn:hover {
    background: #dbeafe;
}

.decision-section {
    margin-top: 16px;
    padding: 16px 0 0;
    border: 0;
    border-top: 1px solid #e5e7eb;
    border-radius: 0;
    background: transparent;
}

.decision-options {
    display: flex;
    align-items: center;
    gap: 14px 22px;
    flex-wrap: wrap;
    margin-bottom: 0;
}

.radio-option {
    width: auto;
    color: #374151;
    font-size: 13px;
}

.decision-actions {
    display: flex;
    gap: 10px;
    margin-top: 12px;
}

.decision-actions .btn {
    margin-bottom: 0;
}

.empty-message {
    padding: 34px 20px;
    border: 1px dashed #d1d5db;
    border-radius: 10px;
    background: #f9fafb;
    color: #6b7280;
}

.empty-message h3 {
    color: #374151;
}

@media (max-width: 900px) {
    .paper-form {
        margin: 0 auto;
        padding: 18px;
    }

    .page-header {
        align-items: flex-start;
        flex-direction: column;
        gap: 14px;
    }

    /* In a column layout the flex-basis applies vertically; reset it so the
       heading does not reserve 280px of empty height. */
    .page-heading {
        flex: 0 0 auto;
    }

    .filter-controls {
        width: 100%;
    }

    .filter-field {
        flex: 1 1 130px;
    }

    .filter-controls .time-select {
        width: 100%;
    }
}

@media (max-width: 600px) {
    .filter-controls {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        align-items: end;
    }

    .filter-btn {
        width: 100%;
    }

    .vr-toolbar {
        align-items: stretch;
    }

    .vr-tabs {
        width: 100%;
    }

    .vr-tab {
        flex: 1 1 0;
        min-width: 0;
        padding: 8px 6px;
        font-size: 11px;
        text-align: center;
    }

    .vr-summary-btn {
        width: 100%;
    }

    .request-item {
        padding: 16px;
    }

    .request-details {
        grid-template-columns: 1fr;
    }

    .request-header {
        gap: 10px;
    }
}

</style>
<div class="paper-form">
    <div class="page-header">
        <div class="page-heading">
            <h1>VEHICLE REQUESTS</h1>
            <p class="page-subtitle">Review, approve, and track faculty vehicle requests.</p>
        </div>

        <div class="filter-controls" aria-label="Filter requests by travel period">
            <label class="filter-field">
                <span class="filter-label">Month</span>
                <select id="monthFilter" class="time-select">
                    <option value="">All Months</option>
                    <option value="1">January</option>
                    <option value="2">February</option>
                    <option value="3">March</option>
                    <option value="4">April</option>
                    <option value="5">May</option>
                    <option value="6">June</option>
                    <option value="7">July</option>
                    <option value="8">August</option>
                    <option value="9">September</option>
                    <option value="10">October</option>
                    <option value="11">November</option>
                    <option value="12">December</option>
                </select>
            </label>

            <label class="filter-field">
                <span class="filter-label">Year</span>
                <select id="yearFilter" class="time-select">
                    <?php foreach ($availableYears as $y): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y === $currentYear ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endforeach; ?>
                    <option value="">All Years</option>
                </select>
            </label>

            <button type="button" class="filter-btn filter-btn-primary" onclick="applyTimeFilter()">Apply Filter</button>
            <button type="button" class="filter-btn filter-btn-ghost" onclick="clearTimeFilter()">Clear Filter</button>
        </div>
    </div>

    <div class="vr-toolbar">
        <div class="vr-tabs" role="tablist" aria-label="Filter requests by status">
            <button type="button" class="vr-tab active" data-status="all" role="tab" aria-selected="true" onclick="filterByStatus('all')">All Requests</button>
            <button type="button" class="vr-tab" data-status="pending" role="tab" aria-selected="false" onclick="filterByStatus('pending')">Pending</button>
            <button type="button" class="vr-tab" data-status="approved" role="tab" aria-selected="false" onclick="filterByStatus('approved')">Approved</button>
            <button type="button" class="vr-tab" data-status="declined" role="tab" aria-selected="false" onclick="filterByStatus('declined')">Declined</button>
        </div>

        <button type="button" class="vr-summary-btn" onclick="openSummaryModal()">
            <span class="material-icons">table_view</span>
            Summary
        </button>
    </div>

    <div class="table-container">
        <div class="request-list" id="vehicleRequestList">
        <?php if (empty($requests)): ?>
            <div class="empty-message">
                <h3>No vehicle requests found</h3>
                <p>There are no vehicle requests to display.</p>
            </div>
        <?php else: ?>
            <?php foreach ($requests as $index => $request): ?>
                <div class="request-item" id="vehicle-request-<?php echo (int)$request['id']; ?>">
                    <div class="request-header">
                        <div class="request-heading">
                            <span class="request-number">#<?php echo ticketLabel($request); ?></span>
                            <span class="request-subject"><?php echo htmlspecialchars(!empty($request['faculty_name']) ? $request['faculty_name'] : $request['requested_by']); ?></span>
                        </div>
                        <span class="status-badge status-<?php echo strtolower($request['status']); ?>"><?php echo strtoupper($request['status']); ?></span>
                    </div>
                    <div class="request-details">
                        <div class="detail-item">
                            <span class="detail-label">Requested By</span>
                            <span class="detail-value"><?php echo htmlspecialchars($request['requested_by']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Destination</span>
                            <span class="detail-value"><?php echo htmlspecialchars($request['destination']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Purpose</span>
                            <span class="detail-value"><?php echo htmlspecialchars($request['purpose']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Passengers</span>
                            <span class="detail-value"><?php echo htmlspecialchars($request['passengers']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Travel Date</span>
                            <span class="detail-value"><?php echo date('F j, Y', strtotime($request['travel_date'])); ?></span>
                        </div>
                        <?php if (!empty($request['attachment_name'])): ?>
                            <div class="detail-item detail-item-full">
                                <span class="detail-label">Attachment</span>
                                <button type="button" class="view-attachment-btn" data-request-id="<?php echo $request['id']; ?>">View Authority To Travel File</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($request['status'] === 'pending'): ?>
                        <div class="decision-section">
                            <div class="decision-options">
                                <label class="radio-option">
                                    <input type="radio" name="decision-<?php echo $request['id']; ?>" value="approved" onchange="toggleDecisionButtons(<?php echo $request['id']; ?>, this.value)">
                                    <span>Approved</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="decision-<?php echo $request['id']; ?>" value="declined" onchange="toggleDecisionButtons(<?php echo $request['id']; ?>, this.value)">
                                    <span>Disapproved due to:</span>
                                </label>
                                <input type="text" id="reason-<?php echo $request['id']; ?>" placeholder="Enter reason..." class="reason-input" style="display: none;">
                            </div>
                            <div class="decision-actions" id="decision-actions-<?php echo $request['id']; ?>" style="display: none;">
                                <button class="btn primary" id="proceed-btn-<?php echo $request['id']; ?>" style="display: none;" onclick="proceedToPrint(<?php echo $request['id']; ?>)">Proceed to Print</button>
                                <button class="btn danger" id="submit-btn-<?php echo $request['id']; ?>" style="display: none;" onclick="submitDecision(<?php echo $request['id']; ?>)">Submit</button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    // Pass PHP data to JavaScript for filtering
    // Use var (not const) so re-execution of this inline script doesn't throw a redeclaration error
    // JSON_INVALID_UTF8_SUBSTITUTE plus the ?: '[]' fallback mean a single bad byte can
    // never again produce "var allVehicleRequestsData = ;", which is a SyntaxError that
    // would take down the filters, attachment viewer and summary on this page at once.
    var allVehicleRequestsData = <?php
        $requestsJson = json_encode($requests, JSON_INVALID_UTF8_SUBSTITUTE);
        echo $requestsJson === false ? '[]' : $requestsJson;
    ?>;
    
    // Parse a date-only value (or legacy datetime) as a LOCAL calendar date so
    // the day never shifts when the browser is behind/ahead of UTC.
    function parseDateOnly(value) {
        const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!match) return null;
        const d = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
        return isNaN(d.getTime()) ? null : d;
    }

    function formatDateOnly(value) {
        const d = parseDateOnly(value);
        return d ? d.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A';
    }
    
    // Display filtered requests
    function displayFilteredRequests(requests, status) {
        const requestList = document.getElementById('vehicleRequestList');
        if (!requestList) {
            console.error('vehicleRequestList element not found');
            return;
        }
        
        if (requests.length === 0) {
            const label = status === 'all' ? 'vehicle' : status;
            requestList.innerHTML = `
                <div class="empty-message">
                    <h3>No ${label} requests found</h3>
                    <p>There are no ${label} requests to display for the selected filters.</p>
                </div>
            `;
            return;
        }
        
        let html = '';
        requests.forEach(request => {
            const statusBadge = `<span class="status-badge status-${request.status}">${request.status.toUpperCase()}</span>`;
            const decisionSection = request.status === 'pending' ? `
                <div class="decision-section">
                    <div class="decision-options">
                        <label class="radio-option">
                            <input type="radio" name="decision-${request.id}" value="approved" onchange="toggleDecisionButtons(${request.id}, this.value)">
                            <span>Approved</span>
                        </label>
                        <label class="radio-option">
                                    <input type="radio" name="decision-${request.id}" value="declined" onchange="toggleDecisionButtons(${request.id}, this.value)">
                                    <span>Disapproved due to:</span>
                        </label>
                        <input type="text" id="reason-${request.id}" placeholder="Enter reason..." class="reason-input" style="display: none;">
                    </div>
                    <div class="decision-actions" id="decision-actions-${request.id}" style="display: none;">
                        <button class="btn primary" id="proceed-btn-${request.id}" style="display: none;" onclick="proceedToPrint(${request.id})">Proceed to Print</button>
                        <button class="btn danger" id="submit-btn-${request.id}" style="display: none;" onclick="submitDecision(${request.id})">Submit</button>
                    </div>
                </div>
            ` : request.status === 'approved' ? `
                <div class="decision-section">
                    <div class="decision-actions">
                        <button class="btn primary" onclick="reprintRequest(${request.id})">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; margin-right: 4px;"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
                            Reprint Request
                        </button>
                    </div>
                </div>
            ` : '';
            
            html += `
                <div class="request-item" id="vehicle-request-${Number(request.id)}">
                    <div class="request-header">
                        <div class="request-heading">
                            <span class="request-number">#${formatTicketNo(request)}</span>
                            <span class="request-subject">${request.faculty_name ? request.faculty_name : request.requested_by}</span>
                        </div>
                        ${statusBadge}
                    </div>
                    <div class="request-details">
                        <div class="detail-item">
                            <span class="detail-label">Requested By</span>
                            <span class="detail-value">${request.requested_by}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Destination</span>
                            <span class="detail-value">${request.destination}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Purpose</span>
                            <span class="detail-value">${request.purpose}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Passengers</span>
                            <span class="detail-value">${request.passengers}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Travel Date</span>
                            <span class="detail-value">${formatDateOnly(request.travel_date)}</span>
                        </div>
                        ${request.attachment_name ? `<div class="detail-item detail-item-full"><span class="detail-label">Attachment</span><button type="button" class="view-attachment-btn" data-request-id="${request.id}">View Authority To Travel File</button></div>` : ''}
                    </div>
                    ${decisionSection}
                </div>
            `;
        });
        
        requestList.innerHTML = html;
    }
    
    // Toggle decision buttons based on radio selection
    function toggleDecisionButtons(requestId, decision) {
        const actionsDiv = document.getElementById('decision-actions-' + requestId);
        const proceedBtn = document.getElementById('proceed-btn-' + requestId);
        const submitBtn = document.getElementById('submit-btn-' + requestId);
        const reasonInput = document.getElementById('reason-' + requestId);
        
        // Show the actions container
        actionsDiv.style.display = 'flex';
        
        if (decision === 'approved') {
            // Show proceed button, hide submit button and reason
            proceedBtn.style.display = 'inline-block';
            submitBtn.style.display = 'none';
            reasonInput.style.display = 'none';
        } else if (decision === 'declined') {
            // Show submit button and reason, hide proceed button
            proceedBtn.style.display = 'none';
            submitBtn.style.display = 'inline-block';
            reasonInput.style.display = 'inline-block';
        }
    }
    
    // Submit decision for declined requests
    function submitDecision(requestId) {
        const reasonInput = document.getElementById('reason-' + requestId);
        const reason = reasonInput.value.trim();
        
        if (!reason) {
            showAppAlert('Please provide a reason for declining.', 'Reason Required', null, { type: 'warning' });
            return;
        }
        
        showAppConfirm('Are you sure you want to decline this request?', 'Decline Request?', function () {
            // Send the decline decision to the backend
            fetch('../database_requests.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_status&id=${requestId}&status=declined&notes=${encodeURIComponent(reason)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAppAlert('Request declined successfully!\nReason: ' + reason, 'Request Declined', function () {
                        location.reload();
                    }, { type: 'success' });
                } else {
                    showAppAlert('Error: ' + (data.error || 'Failed to update request'), 'Unable to Decline Request', null, { type: 'error' });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAppAlert('An error occurred while updating the request.', 'Something Went Wrong', null, { type: 'error' });
            });
        }, null, { type: 'danger', okText: 'Decline', cancelText: 'Cancel' });
    }
    
    // Proceed to print for approved requests
    function proceedToPrint(requestId) {
        // Find the specific request data
        const requestData = allVehicleRequestsData.find(req => req.id == requestId);
        // Redirect to travel order form with request data
        window.open('pages/travel_order_form.php?request=' + encodeURIComponent(JSON.stringify(requestData)), '_blank');
    }
    
    // Reprint approved requests
    function reprintRequest(requestId) {
        // Find the specific request data
        const requestData = allVehicleRequestsData.find(req => req.id == requestId);
        if (!requestData) {
            showAppAlert('Request data not found.', 'Request Not Found', null, { type: 'error' });
            return;
        }
        
        // Open travel order form in new tab
        window.open('pages/travel_order_form.php?request=' + encodeURIComponent(JSON.stringify(requestData)), '_blank');
    }
    
    // Open the modal with the given URL and title
    function openModal(url, filename) {
        const modal = document.getElementById('attachmentModal');
        const content = document.getElementById('attachmentModalContent');
        const filenameEl = document.getElementById('attachmentModalFilename');
        
        filenameEl.textContent = filename;
        const ext = (filename.split('.').pop() || '').toLowerCase();
        const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext);
        
        content.innerHTML = '';
        if (isImage) {
            const img = document.createElement('img');
            img.src = url;
            img.alt = filename;
            content.appendChild(img);
        } else {
            const iframe = document.createElement('iframe');
            iframe.src = url;
            content.appendChild(iframe);
        }
        
        modal.classList.add('show');
    }
    
    // Open attachment in modal viewer (global)
    window.viewAttachment = function(requestId) {
        const request = allVehicleRequestsData.find(req => req.id == requestId);
        if (!request) {
            showAppAlert('Request data not found.', 'Request Not Found', null, { type: 'error' });
            return;
        }
        // Attachments live in the database; view_attachment.php streams the stored bytes.
        openModal('/Prototype/view_attachment.php?id=' + encodeURIComponent(requestId), request.attachment_name || 'Authority To Travel File');
    };
    
    // Close the attachment modal (global)
    window.closeAttachmentModal = function() {
        const modal = document.getElementById('attachmentModal');
        const content = document.getElementById('attachmentModalContent');
        modal.classList.remove('show');
        modal.classList.remove('show-fullscreen');
        content.innerHTML = '';
    };

    // Toggle fullscreen for the attachment viewer (global)
    window.toggleAttachmentFullscreen = function() {
        const modal = document.getElementById('attachmentModal');
        if (!modal) return;
        const isFull = modal.classList.contains('show-fullscreen');
        modal.classList.toggle('show-fullscreen');
        const btn = document.getElementById('attachmentFullscreenBtn');
        if (btn) {
            btn.title = !isFull ? 'Exit fullscreen' : 'Toggle fullscreen';
        }

        // Explicitly scale the image to fill the viewport (guarantees enlargement
        // regardless of CSS cascade / browser caching).
        const img = modal.querySelector('.attachment-modal img');
        if (img) {
            if (!isFull) {
                // Enter fullscreen: fill the viewport while keeping aspect ratio
                img.style.width = '100%';
                img.style.height = '100%';
                img.style.objectFit = 'contain';
                img.style.maxWidth = '100vw';
                img.style.maxHeight = '100vh';
            } else {
                // Exit fullscreen: restore sensible in-modal sizing
                img.style.width = '';
                img.style.height = '';
                img.style.objectFit = '';
                img.style.maxWidth = '';
                img.style.maxHeight = '';
            }
        }

        // Re-center content for fullscreen
        const content = document.getElementById('attachmentModalContent');
        if (content) {
            content.style.display = 'flex';
            content.style.alignItems = 'center';
            content.style.justifyContent = 'center';
            if (isFull) {
                content.style.display = '';
                content.style.alignItems = '';
                content.style.justifyContent = '';
            }
        }
    };

    // Close fullscreen with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('attachmentModal');
            if (modal && modal.classList.contains('show-fullscreen')) {
                modal.classList.remove('show-fullscreen');
            } else if (modal && modal.classList.contains('show')) {
                window.closeAttachmentModal();
            }
        }
    });

    // Event delegation for attachment view buttons
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.view-attachment-btn');
        if (btn && btn.dataset.requestId) {
            window.viewAttachment(btn.dataset.requestId);
        }
    });

    // ===== Time and status filter state =====
    // Use window properties (not let/const) so re-execution of this inline script doesn't throw a redeclaration error
    window.currentStatusFilter = <?php echo $showPendingAll ? "'pending'" : "'all'"; ?>;
    window.currentTimeFilter = { month: '', year: '' };
    window.currentRequestYear = <?php echo $currentYear; ?>;
    window.showPendingAll = <?php echo $showPendingAll ? 'true' : 'false'; ?>;

    // Trip Ticket is the per-travel-year sequence, zero-padded (001, 002 ...).
    // Display-only; vehicle_requests.id still drives every approve/print action.
    // MUST stay in sync with the PHP ticketLabel() helper, and MUST be defined at
    // top level before any render - both render paths have to agree or the ticket
    // number flickers between the id and the real ticket.
    window.formatTicketNo = function (row) {
        if (!row || row.ticket_no === null || row.ticket_no === undefined) {
            return '---';
        }
        return String(row.ticket_no).padStart(3, '0');
    };

    // The list defaults to the current calendar year, so a new year starts with a
    // clean list while past years stay one dropdown away. Years come from the
    // server (data-derived) rather than a hardcoded range.
    function initializeYearFilter() {
        const yearFilter = document.getElementById('yearFilter');
        if (!yearFilter) return;
        if (!yearFilter.options.length) {
            yearFilter.innerHTML = '<option value="">All Years</option>';
        }
        // ?show=pending arrives with the year and month cleared so pending requests
        // from any trip year are visible; otherwise default to the current year.
        if (window.showPendingAll) {
            yearFilter.value = '';
            window.currentTimeFilter.year = '';
        } else {
            yearFilter.value = String(window.currentRequestYear);
            window.currentTimeFilter.year = window.currentRequestYear;
        }
    }

    // Keep the segmented status control in sync with the active filter
    function updateStatusTabs() {
        document.querySelectorAll('.vr-tab[data-status]').forEach(tab => {
            const isActive = tab.dataset.status === window.currentStatusFilter;
            tab.classList.toggle('active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
    }

    // Make sure a notification's request is visible under the current filters.
    // Only adjust the filters that would hide it, so an active status or time
    // filter is preserved whenever the target already matches it.
    function alignVehicleFiltersToTarget(target) {
        if (!target) return;

        const rawStatus = String(target.status || 'pending').toLowerCase();
        const targetStatus = ['pending', 'approved', 'declined'].includes(rawStatus) ? rawStatus : 'all';
        if (window.currentStatusFilter !== 'all' && window.currentStatusFilter !== targetStatus) {
            window.currentStatusFilter = targetStatus;
        }

        const targetDate = parseDateOnly(target.travel_date);
        const currentMonth = String(window.currentTimeFilter.month || '');
        const currentYear = String(window.currentTimeFilter.year || '');
        const monthMatches = !currentMonth || (targetDate && String(targetDate.getMonth() + 1) === currentMonth);
        const yearMatches = !currentYear || (targetDate && String(targetDate.getFullYear()) === currentYear);

        if (!monthMatches || !yearMatches) {
            const monthFilter = document.getElementById('monthFilter');
            const yearFilter = document.getElementById('yearFilter');
            if (targetDate) {
                const month = String(targetDate.getMonth() + 1);
                const year = String(targetDate.getFullYear());
                window.currentTimeFilter = { month, year };
                if (monthFilter) monthFilter.value = month;
                if (yearFilter) yearFilter.value = year;
            } else {
                window.currentTimeFilter = { month: '', year: '' };
                if (monthFilter) monthFilter.value = '';
                if (yearFilter) yearFilter.value = '';
            }
        }
    }

    // Combine status + time filtering and re-render
    function renderFiltered() {
        updateStatusTabs();
        let filtered = allVehicleRequestsData;
        const targetId = Number(window.notificationTargetId) || 0;

        if (targetId && window.notificationTargetAlignedId !== targetId) {
            const target = (allVehicleRequestsData || []).find(request => Number(request.id) === targetId);
            if (target) {
                alignVehicleFiltersToTarget(target);
                updateStatusTabs();
                window.notificationTargetAlignedId = targetId;
            }
        }

        // Apply status filter
        if (window.currentStatusFilter !== 'all') {
            filtered = filtered.filter(request => (request.status || 'pending') === window.currentStatusFilter);
        }

        // Apply time (month/year) filter based on travel_date
        if (window.currentTimeFilter.month || window.currentTimeFilter.year) {
            filtered = filtered.filter(request => {
                // Requests saved without a travel date must never be hidden by a
                // year/month filter, otherwise they become unreachable.
                if (!request.travel_date) return window.currentTimeFilter.month === '';
                const d = parseDateOnly(request.travel_date);
                if (!d) return false;
                const monthMatch = !window.currentTimeFilter.month || (d.getMonth() + 1) == window.currentTimeFilter.month;
                const yearMatch = !window.currentTimeFilter.year || d.getFullYear() == window.currentTimeFilter.year;
                return monthMatch && yearMatch;
            });
        }

        displayFilteredRequests(filtered, window.currentStatusFilter === 'all' ? 'all' : window.currentStatusFilter);

        // Scroll to and briefly highlight the record that was opened from a
        // notification, then consume the deep-link id after the highlight window.
        if (targetId) {
            const targetElement = document.getElementById('vehicle-request-' + targetId);
            if (targetElement) {
                if (typeof window.highlightNotificationTarget === 'function') {
                    window.highlightNotificationTarget(targetElement);
                } else {
                    targetElement.classList.add('notification-highlight');
                    targetElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
            if (typeof window.scheduleNotificationTargetClear === 'function') {
                window.scheduleNotificationTargetClear(targetId);
            } else {
                window.notificationTargetId = '';
            }
        }
    }

    // Filter by status (overrides global version that points to the table layout)
    function filterByStatus(status) {
        window.currentStatusFilter = status;
        renderFiltered();
    }

    // Apply time filter (overrides global version)
    function applyTimeFilter() {
        const monthFilter = document.getElementById('monthFilter');
        const yearFilter = document.getElementById('yearFilter');
        window.currentTimeFilter.month = monthFilter ? monthFilter.value : '';
        window.currentTimeFilter.year = yearFilter ? yearFilter.value : '';
        renderFiltered();
    }

    // Clear time filter (overrides global version) - returns to the current-year default
    function clearTimeFilter() {
        const monthFilter = document.getElementById('monthFilter');
        const yearFilter = document.getElementById('yearFilter');
        if (monthFilter) monthFilter.value = '';
        if (yearFilter) yearFilter.value = String(window.currentRequestYear);
        window.currentTimeFilter = { month: '', year: String(window.currentRequestYear) };
        renderFiltered();
    }

    // Refresh vehicle request data from the server (used when returning focus to this page)
    function refreshVehicleRequests() {
        // Only act while this page is the active view
        if (!document.getElementById('vehicleRequestList') || !allVehicleRequestsData) {
            return;
        }
        fetch('/Prototype/database_requests.php?table=vehicle_requests&action=get_all_requests')
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data && data.requests && data.requests.length >= 0) {
                    allVehicleRequestsData = data.requests;
                    window.allRequests = allVehicleRequestsData;
                    renderFiltered();
                }
            })
            .catch(function(err) {
                console.error('Failed to refresh vehicle requests:', err);
            });
    }

    // Refresh the list when the tab regains focus (e.g. returning from a travel order tab)
    if (!window.__vrFocusBound) {
        window.__vrFocusBound = true;
        window.addEventListener('focus', function() {
            setTimeout(refreshVehicleRequests, 150);
        });
    }

    // Load vehicle requests when this page is loaded
    setTimeout(() => {
        // Initialize with server data
        window.allRequests = allVehicleRequestsData;
        initializeYearFilter();
        renderFiltered();
    }, 100);

    // ===== Approved vehicle request summary / Excel export =====
    // Use window props (not const) so re-execution of this inline script doesn't throw a redeclaration error
    window.SUMMARY_ENDPOINT = '/Prototype/PLANNING%20OFFICER/pages/vehicle_request_summary.php';
    // Column order of the summary table AND the Excel export.
    // MUST stay in sync with $headers in vehicle_request_summary.php.
    window.SUMMARY_HEADERS = ['TICKET NUMBER', 'REQUESTED BY', 'DATE OF TRAVEL', 'DRIVER', 'PASSENGER', 'DESTINATION', 'PURPOSE'];
    window.summaryYears = [];
    window.summaryQuarters = [];
    window.summaryMonths = [];
    window.summaryYear = '';
    window.summaryQuarter = '';
    window.summarySelection = '';

    function buildSummaryQuery(action) {
        let url = SUMMARY_ENDPOINT + '?action=' + action;
        if (window.summaryYear) {
            url += '&year=' + encodeURIComponent(window.summaryYear);
        }
        if (window.summaryQuarter) {
            url += '&quarter=' + encodeURIComponent(window.summaryQuarter);
        }
        if (window.summarySelection) {
            url += '&month=' + encodeURIComponent(window.summarySelection);
        }
        return url;
    }

    window.openSummaryModal = async function() {
        const modal = document.getElementById('summaryModal');
        modal.classList.add('show');
        document.getElementById('summaryBody').innerHTML =
            '<div class="summary-empty"><i class="material-icons">hourglass_empty</i><p>Loading approved requests...</p></div>';

        try {
            const res = await fetch(buildSummaryQuery('months'));
            const data = await res.json();
            window.summaryYears = data.years || [];
            window.summaryQuarters = data.quarters || [];
            window.summaryMonths = data.months || [];
            window.summaryYear = '';
            window.summaryQuarter = '';
            window.summarySelection = '';
            renderSummaryYearOptions();
            renderSummaryQuarterOptions();
            renderSummaryMonthOptions();
            await loadSummaryPreview();
        } catch (err) {
            console.error('Failed to load summary:', err);
            document.getElementById('summaryBody').innerHTML =
                '<div class="summary-empty"><i class="material-icons">error</i><p>Could not load the summary. Please try again.</p></div>';
        }
    };

    window.closeSummaryModal = function() {
        document.getElementById('summaryModal').classList.remove('show');
    };

    function renderSummaryYearOptions() {
        const select = document.getElementById('summaryYearSelect');
        if (!window.summaryYears.length) {
            select.innerHTML = '<option value="">All Years</option>';
            return;
        }
        const total = window.summaryYears.reduce(function(a, y) { return a + y.count; }, 0);
        let html = '<option value="">All Years (' + total + ')</option>';
        window.summaryYears.forEach(function(y) {
            html += '<option value="' + escapeHtml(y.value) + '">' + escapeHtml(y.label) + ' (' + y.count + ')</option>';
        });
        select.innerHTML = html;
        select.value = window.summaryYear;
    }

    function renderSummaryQuarterOptions() {
        const select = document.getElementById('summaryQuarterSelect');
        const wrapper = document.getElementById('summaryQuarterPicker');
        if (!window.summaryQuarters.length) {
            wrapper.style.display = 'none';
            select.innerHTML = '';
            return;
        }
        wrapper.style.display = 'flex';

        const total = window.summaryQuarters.reduce(function(a, q) { return a + q.count; }, 0);
        const scope = window.summaryYear ? ' in ' + window.summaryYear : '';
        let html = '<option value="">All Quarters' + scope + ' (' + total + ')</option>';
        window.summaryQuarters.forEach(function(q) {
            html += '<option value="' + escapeHtml(q.value) + '">' + escapeHtml(q.label) + ' (' + q.count + ')</option>';
        });
        select.innerHTML = html;
        select.value = window.summaryQuarter;
    }

    function renderSummaryMonthOptions() {
        const select = document.getElementById('summaryMonthSelect');
        const wrapper = document.getElementById('summaryMonthPicker');
        if (!window.summaryMonths.length) {
            wrapper.style.display = 'none';
            select.innerHTML = '';
            return;
        }
        wrapper.style.display = 'flex';

        const total = window.summaryMonths.reduce(function(a, m) { return a + m.count; }, 0);
        let scope = '';
        if (window.summaryQuarter) {
            scope = ' in Q' + window.summaryQuarter + (window.summaryYear ? ' ' + window.summaryYear : '');
        } else if (window.summaryYear) {
            scope = ' in ' + window.summaryYear;
        }
        let html = '<option value="">All Months' + scope + ' (' + total + ')</option>';
        window.summaryMonths.forEach(function(m) {
            html += '<option value="' + escapeHtml(m.value) + '">' + escapeHtml(m.label) + ' (' + m.count + ')</option>';
        });
        select.innerHTML = html;
        select.value = window.summarySelection;
    }

    window.selectSummaryMonth = function(month) {
        window.summarySelection = month || '';
        loadSummaryPreview();
    };

    window.selectSummaryQuarter = async function(quarter) {
        window.summaryQuarter = quarter || '';
        window.summarySelection = '';
        document.getElementById('summaryBody').innerHTML =
            '<div class="summary-empty"><i class="material-icons">hourglass_empty</i><p>Loading approved requests...</p></div>';

        // Re-query months so the list only shows months inside the chosen quarter
        try {
            const res = await fetch(buildSummaryQuery('months'));
            const data = await res.json();
            window.summaryMonths = data.months || [];
        } catch (err) {
            console.error('Failed to load months:', err);
            window.summaryMonths = [];
        }
        renderSummaryMonthOptions();
        await loadSummaryPreview();
    };

    window.selectSummaryYear = async function(year) {
        window.summaryYear = year || '';
        window.summaryQuarter = '';
        window.summarySelection = '';
        document.getElementById('summaryBody').innerHTML =
            '<div class="summary-empty"><i class="material-icons">hourglass_empty</i><p>Loading approved requests...</p></div>';

        // Re-query so quarter/month counts only reflect the chosen year
        try {
            const res = await fetch(buildSummaryQuery('months'));
            const data = await res.json();
            window.summaryQuarters = data.quarters || [];
            window.summaryMonths = data.months || [];
        } catch (err) {
            console.error('Failed to load summary filters:', err);
            window.summaryQuarters = [];
            window.summaryMonths = [];
        }
        renderSummaryQuarterOptions();
        renderSummaryMonthOptions();
        await loadSummaryPreview();
    };

    async function loadSummaryPreview() {
        const body = document.getElementById('summaryBody');
        const url = buildSummaryQuery('preview');
        try {
            const res = await fetch(url);
            const data = await res.json();
            if (!data.rows.length) {
                body.innerHTML = '<div class="summary-empty"><i class="material-icons">event_busy</i>'
                              + '<p>No approved vehicle requests for this period.</p></div>';
                updateSummaryMeta(data.label, 0);
                return;
            }

            let html = '<div class="summary-table-wrap"><table class="summary-table"><thead><tr>';
            SUMMARY_HEADERS.forEach(function(h) { html += '<th>' + h + '</th>'; });
            html += '</tr></thead><tbody>';

            let lastMonth = null;
            data.rows.forEach(function(r) {
                const mKey = r.travel_month || 'undated';
                if (mKey !== lastMonth) {
                    const count = data.rows.filter(function(x) {
                        return (x.travel_month || 'undated') === mKey;
                    }).length;
                    html += '<tr class="month-group"><td colspan="' + SUMMARY_HEADERS.length + '">'
                          + escapeHtml(monthLabel(mKey)) + ' &nbsp;(' + count + ')</td></tr>';
                    lastMonth = mKey;
                }
                html += '<tr>';
                html += '<td>' + formatTicketNo(r) + '</td>';
                html += '<td>' + escapeHtml(r.requested_by) + '</td>';
                html += '<td>' + escapeHtml(r.travel_date) + '</td>';
                // Driver saved from the travel order form; --- when not yet assigned.
                html += '<td>' + escapeHtml(r.driver || '---') + '</td>';
                html += '<td>' + escapeHtml(r.passengers) + '</td>';
                html += '<td>' + escapeHtml(r.destination) + '</td>';
                html += '<td>' + escapeHtml(r.purpose) + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';
            body.innerHTML = html;
            updateSummaryMeta(data.label, data.rows.length);
        } catch (err) {
            console.error('Failed to load summary preview:', err);
            body.innerHTML = '<div class="summary-empty"><i class="material-icons">error</i>'
                           + '<p>Could not load the preview. Please try again.</p></div>';
        }
    }

    function updateSummaryMeta(label, count) {
        document.getElementById('summaryMeta').textContent = label + ' - ' + count + ' approved request' + (count === 1 ? '' : 's');
        const link = document.getElementById('summaryDownload');
        link.setAttribute('href', buildSummaryQuery('download'));
        link.setAttribute('download', '');
        link.classList.toggle('disabled', count === 0);
    }

    function monthLabel(key) {
        if (key === 'undated') return 'Undated';
        const parts = key.split('-');
        const date = new Date(Number(parts[0]), Number(parts[1]) - 1, 1);
        return date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }
</script>

<!-- Approved Request Summary / Excel Export Modal -->
<div class="summary-modal" id="summaryModal" onclick="if(event.target === this) closeSummaryModal()">
    <div class="summary-modal-content">
        <div class="summary-modal-header">
            <h2>Approved Vehicle Request Summary</h2>
            <button class="summary-modal-close" onclick="closeSummaryModal()" title="Close">&times;</button>
        </div>
        <div class="summary-modal-body">
            <div class="summary-filters">
                <div class="summary-month-picker" id="summaryYearPicker">
                    <label class="summary-month-label" for="summaryYearSelect">Year</label>
                    <select id="summaryYearSelect" class="summary-month-select" onchange="selectSummaryYear(this.value)"></select>
                </div>
                <div class="summary-month-picker" id="summaryQuarterPicker">
                    <label class="summary-month-label" for="summaryQuarterSelect">Quarter</label>
                    <select id="summaryQuarterSelect" class="summary-month-select" onchange="selectSummaryQuarter(this.value)"></select>
                </div>
                <div class="summary-month-picker" id="summaryMonthPicker">
                    <label class="summary-month-label" for="summaryMonthSelect">Month</label>
                    <select id="summaryMonthSelect" class="summary-month-select" onchange="selectSummaryMonth(this.value)"></select>
                </div>
            </div>
            <div id="summaryBody"></div>
        </div>
        <div class="summary-modal-footer">
            <span class="summary-meta" id="summaryMeta"></span>
            <a class="summary-download-btn" id="summaryDownload" href="#">
                <i class="material-icons" style="font-size:18px;">download</i>Download Excel
            </a>
        </div>
    </div>
</div>

<!-- Attachment Viewer Modal -->
<div class="attachment-modal" id="attachmentModal" onclick="if(event.target === this) closeAttachmentModal()">
    <div class="attachment-modal-content">
        <div class="attachment-modal-topbar">
            <button class="attachment-modal-close" onclick="closeAttachmentModal()" title="Close">&times;</button>
            <button class="attachment-modal-fullscreen" id="attachmentFullscreenBtn" onclick="toggleAttachmentFullscreen()" title="Toggle fullscreen">&#x26F6;</button>
        </div>
        <div class="modal-filename" id="attachmentModalFilename"></div>
        <div id="attachmentModalContent"></div>
    </div>
</div>

</div>
</div>

<?php include __DIR__ . '/../includes/custom_alert_modal.php'; ?>
