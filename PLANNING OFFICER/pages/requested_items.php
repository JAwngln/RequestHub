<?php
session_start();
if (!isset($_SESSION['email']) || trim($_SESSION['role']) !== 'planning_officer') {
    header("Location: ../../auth/login.php");
    exit();
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/Prototype/connect.php';
require_once __DIR__ . '/../includes/year_filter.php';

$currentYear = (int)date('Y');
$availableYears = getAvailableYears($conn, 'requested_items', 'when_needed');

// Fetch requested items from database
$items = [];
$sql = "SELECT * FROM requested_items ORDER BY id DESC";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
}

// The page opens on the Pending tab, so render only pending items in the initial
// server-side pass. The full list is still sent to JavaScript below for the other tabs.
$initialItems = array_values(array_filter($items, function ($item) {
    return strtolower($item['action'] ?? 'pending') === 'pending';
}));
?>

<link href="/Prototype/assets/fonts/material-icons.css" rel="stylesheet">
<link href="/Prototype/assets/fonts/poppins.css" rel="stylesheet">

<style>
/* ================= REQUESTED ITEMS STYLES ================= */
/* Scoped to prevent conflicts with dashboard buttons */

/* Planning Officer Base Styles */
.requested-items-container .paper-form {
    background: white;
    border-radius: 10px;
    padding: 30px;
    margin: 20px auto;
    max-width: 1200px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    border: 1px solid #e0e0e0;
}

.requested-items-container .paper-form h1 {
    color: #1a4d2e;
    text-align: center;
    margin-bottom: 30px;
    font-size: 28px;
    font-weight: 600;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
}

/* Time Filter Section */
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
}

.time-select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: white;
    font-size: 14px;
    min-width: 120px;
}

.time-select:focus {
    outline: none;
    border-color: #1a4d2e;
    box-shadow: 0 0 0 2px rgba(26, 77, 46, 0.2);
}

/* Status Filter Section */
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
}

/* Colored Filter Buttons - Matching Vehicle Requested Page */
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

/* Button Styles - Scoped to prevent dashboard conflicts */
.requested-items-container .btn {
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
}

.requested-items-container .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 3px 6px rgba(0,0,0,0.15);
}

.requested-items-container .btn:active {
    transform: translateY(0);
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.requested-items-container .btn.primary {
    background: #007bff;
    color: white;
}

.requested-items-container .btn.primary:hover {
    background: #0056b3;
}

.requested-items-container .btn.secondary {
    background: #1c6b2f;
    color: white;
}

.requested-items-container .btn.secondary:hover {
    background: #155724;
}

.requested-items-container .btn.pending {
    background: #ffc107;
    color: #856404;
}

.requested-items-container .btn.pending:hover {
    background: #e0a800;
}

.requested-items-container .btn.success {
    background: #28a745;
    color: white;
}

.requested-items-container .btn.success:hover {
    background: #218838;
}

.requested-items-container .btn.danger {
    background: #dc3545;
    color: white;
}

.requested-items-container .btn.danger:hover {
    background: #c82333;
}

/* Request Table */
.request-table {
      width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin: 20px 0;
}

.request-table thead {
    background: #1a4d2e;
    color: white;
}

.request-table th {
    background: #1a4d2e;
    color: white;
    padding: 12px 15px;
    text-align: left;
    font-weight: 600;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #155724;
}

.request-table td {
    padding: 12px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 14px;
    vertical-align: middle;
}

.request-table tbody tr {
    transition: background-color 0.2s ease;
}

.request-table tbody tr:hover {
    background: rgba(26, 77, 46, 0.05);
}

/* Action Buttons in Table */
.request-table .btn {
    display: inline-block;
    padding: 6px 14px;
    border-radius: 20px;
    border: none;
    cursor: pointer;
    font-weight: bold;
    font-size: 12px;
    transition: all 0.2s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin: 2px;
    white-space: nowrap;
}

.request-table .btn.success {
    background: #28a745;
    color: white;
}

.request-table .btn.success:hover {
    background: #1e7e34;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(40,167,69,0.3);
}

.request-table .btn.danger {
    background: #dc3545;
    color: white;
}

.request-table .btn.danger:hover {
    background: #c82333;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(220,53,69,0.3);
}

/* Loading State */
.loading {
    text-align: center;
    padding: 40px;
    color: #6c757d;
    font-style: italic;
}

.loading p {
    margin: 0;
    font-size: 16px;
}

/* Empty State */
.empty-message {
    text-align: center;
    padding: 40px;
    color: #6c757d;
}

.empty-message h3 {
    margin-bottom: 10px;
    color: #495057;
    font-size: 18px;
}

.empty-message p {
    margin: 0;
    font-size: 14px;
}

/* Error State */
.error-message {
    text-align: center;
    padding: 40px;
    color: #dc3545;
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    border-radius: 4px;
    margin: 20px 0;
}

.error-message p {
    margin: 0;
    font-size: 14px;
}

/* Status Badge Styles */
.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: 1px solid;
    display: inline-block;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
    border-color: #ffeaa7;
}

.status-approved {
    background: #d4edda;
    color: #155724;
    border-color: #c3e6cb;
}

.status-declined {
    background: #f8d7da;
    color: #721c24;
    border-color: #f5c6cb;
}

/* Tablet screens - make the table stretch to fill the available width */
@media (min-width: 769px) and (max-width: 1024px) {
    .request-table {
        width: 100%;
        min-width: 0;
        font-size: 13px;
    }

    .request-table th,
    .request-table td {
        padding: 10px 8px;
        white-space: normal;
        word-wrap: break-word;
        word-break: break-word;
    }

    .request-table th {
        font-size: 12px;
        white-space: normal;
    }

    .request-table .btn {
        display: inline-block;
        padding: 6px 10px;
        font-size: 11px;
        margin: 2px 0;
        white-space: nowrap;
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .paper-form {
        padding: 20px;
        margin: 10px;
    }
    
    .time-filter-section,
    .filter-section {
        flex-direction: column;
        align-items: stretch;
    }
    
    .time-select {
        width: 100%;
    }
    
    .request-table {
        font-size: 12px;
    }
    
    .request-table th,
    .request-table td {
        padding: 8px 6px;
    }
    
    .paper-form h1 {
        font-size: 24px;
    }
}

/* Phone-specific responsive design */
@media (max-width: 480px) {
    .paper-form {
        padding: 10px;
        margin: 5px;
    }
    
    .paper-form h1 {
        font-size: 16px;
        margin-top: 10px;
    }
    
    .time-filter-section,
    .filter-section {
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
        padding: 12px;
    }

    .time-select {
        width: 100%;
        box-sizing: border-box;
    }
    
    .filter-section .btn,
    .time-filter-section .btn {
        width: 100%;
        padding: 10px;
        font-size: 12px;
        margin: 0;
        min-width: 0;
        box-sizing: border-box;
        text-align: center;
    }

    /* Convert table to card layout on phones */
    .table-wrapper,
    .table-container {
        max-height: none;
        overflow: visible;
        width: 100%;
    }

    .request-table {
        width: 100%;
        min-width: 0;
        font-size: 13px;
        box-shadow: none;
        border-radius: 0;
        margin: 0;
    }

    /* Hide the header row */
    .request-table thead {
        display: none;
    }

    .request-table tbody tr {
        display: block;
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        padding: 12px 14px;
        margin-bottom: 12px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        width: 100%;
        box-sizing: border-box;
    }

    .request-table td {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 8px 0;
        border-bottom: 1px solid #f0f0f0;
        font-size: 13px;
        white-space: normal;
        word-wrap: break-word;
        word-break: break-word;
    }

    .request-table td:last-child {
        border-bottom: none;
        padding-top: 10px;
    }

    .request-table td::before {
        content: attr(data-label);
        font-weight: 600;
        color: #6c757d;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        flex-shrink: 0;
        margin-right: 12px;
        min-width: 80px;
    }

    /* Empty state row - don't style as a label/value pair */
    .request-table tr:has(td[colspan]) td {
        display: block;
        text-align: center;
    }

    .request-table tr:has(td[colspan]) td::before {
        content: none;
    }

    .status-badge {
        padding: 4px 8px;
        font-size: 10px;
    }

    .request-table td:last-child > * {
        justify-content: flex-end;
    }

    /* Action buttons in table */
    .request-table .btn {
        padding: 6px 12px;
        font-size: 11px;
        margin: 2px 0 2px 4px;
        white-space: nowrap;
    }

    .status-approved {
        font-size: 11px;
    }

    /* Don't hide columns - they're all shown in the card layout */
    .request-table th:nth-child(n),
    .request-table td:nth-child(n) {
        display: block;
    }
}

/* Extra small screens (very narrow portrait) */
@media (max-width: 360px) {
    .request-table tbody tr {
        padding: 10px 10px;
    }

    .request-table td {
        font-size: 12px;
        padding: 7px 0;
    }

    .request-table td::before {
        min-width: 70px;
        font-size: 10px;
    }
}

/* Mobile S (320px) - Smallest mobile screens */
@media (max-width: 320px) {
    .paper-form {
        padding: 5px;
        margin: 2px;
    }
    
    .paper-form h1 {
        font-size: 14px;
        margin-top: 5px;
    }
    
    .table-wrapper,
    .table-container {
        max-height: none;
        overflow: visible;
    }
    
    .request-table td {
        font-size: 11px;
        padding: 6px 0;
    }

    .request-table td::before {
        min-width: 60px;
        font-size: 9px;
    }
    
    /* Adjust status badges for very small screens */
    .status-badge {
        padding: 3px 6px;
        font-size: 9px;
    }
    
    /* Make buttons even smaller */
    .filter-section .btn,
    .time-filter-section .btn {
        padding: 8px;
        font-size: 10px;
    }
    
    /* Action buttons in table */
    .request-table .btn {
        padding: 5px 8px;
        font-size: 10px;
        margin: 2px 0 2px 4px;
    }
    
    .status-approved {
        font-size: 10px;
    }
}
/* ================= ENTERPRISE LAYOUT (matches Vehicle Requests) ================= */
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

/* Status segmented control */
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

/* Cards */
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
        border-radius: 12px;
        padding: 22px 24px;
        margin: 0;
        box-shadow: 0 1px 2px rgba(16, 24, 40, 0.02);
        transition: box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .request-item:hover {
        border-color: #d1d5db;
        box-shadow: 0 2px 6px rgba(16, 24, 40, 0.04);
    }

    .request-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 1px solid #f3f4f6;
    }

    .request-heading {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 0;
        flex: 1;
    }

    .request-number {
        color: #1E5E3A;
        font-size: 13px;
        font-weight: 700;
        letter-spacing: 0.02em;
        white-space: nowrap;
    }

    .request-subject {
        color: #111827;
        font-size: 16px;
        font-weight: 600;
        line-height: 1.4;
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
    text-align: center;
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
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px 24px;
        margin-bottom: 0;
        background: #f9fafb;
        padding: 14px 16px;
        border-radius: 8px;
        border: 1px solid #f3f4f6;
    }

    .detail-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
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
        color: #111827;
        font-size: 14px;
        line-height: 1.5;
        overflow-wrap: anywhere;
        font-weight: 500;
    }

.decision-section {
    margin-top: 16px;
    padding: 16px 0 0;
    border: 0;
    border-top: 1px solid #e5e7eb;
    border-radius: 0;
    background: transparent;
}

.decision-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 0;
}

.item-action-btn {
    height: 34px;
    padding: 0 16px;
    border-radius: 8px;
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.02em;
    cursor: pointer;
    transition: background-color 0.18s ease, border-color 0.18s ease, color 0.18s ease;
}

.item-action-btn.approve {
    border: 1px solid #1E5E3A;
    background: #1E5E3A;
    color: #ffffff;
}

.item-action-btn.approve:hover {
    background: #174a2e;
    border-color: #174a2e;
}

.item-action-btn.decline {
    border: 1px solid #fecdca;
    background: #ffffff;
    color: #b42318;
}

.item-action-btn.decline:hover {
    background: #fef3f2;
    border-color: #fda29b;
}

.item-action-btn:disabled {
    opacity: 0.6;
    cursor: default;
}

.processed-note {
    color: #6b7280;
    font-size: 12.5px;
}

.empty-message {
    padding: 34px 20px;
    border: 1px dashed #d1d5db;
    border-radius: 10px;
    background: #f9fafb;
    color: #6b7280;
    text-align: center;
}

.empty-message h3 {
    margin: 0 0 6px;
    color: #374151;
    font-size: 15px;
}

.empty-message p {
    margin: 0;
    font-size: 12.5px;
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

    /* In a column layout the flex-basis applies vertically; reset it. */
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
            <h1>REQUESTED ITEMS</h1>
            <p class="page-subtitle">Review, approve, and track faculty item requests.</p>
        </div>

        <div class="filter-controls" aria-label="Filter requests by needed date">
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
            <button type="button" class="vr-tab" data-status="all" role="tab" aria-selected="false" onclick="showAllItems()">All Requests</button>
            <button type="button" class="vr-tab active" data-status="pending" role="tab" aria-selected="true" onclick="filterItemsByStatus('pending')">Pending</button>
            <button type="button" class="vr-tab" data-status="approved" role="tab" aria-selected="false" onclick="filterItemsByStatus('approved')">Approved</button>
            <button type="button" class="vr-tab" data-status="declined" role="tab" aria-selected="false" onclick="filterItemsByStatus('declined')">Declined</button>
        </div>
    </div>


    <div class="request-list" id="requestList">
        <?php if (empty($initialItems)): ?>
            <div class="empty-message">
                <h3>No pending requested items found</h3>
                <p>There are no pending requested items for the current year.</p>
            </div>
        <?php else: ?>
            <?php foreach ($initialItems as $item): ?>
                <?php $itemStatus = strtolower($item['action'] ?? 'pending'); ?>
                <div class="request-item" id="requested-item-<?php echo (int)$item['id']; ?>">
                    <div class="request-header">
                        <div class="request-heading">
                            <span class="request-number">REQUEST #<?php echo htmlspecialchars($item['id']); ?></span>
                            <span class="request-subject"><?php echo htmlspecialchars($item['items']); ?></span>
                        </div>
                        <span class="status-badge status-<?php echo htmlspecialchars($itemStatus); ?>"><?php echo strtoupper($itemStatus); ?></span>
                    </div>

                    <div class="request-details">
                        <div class="detail-item">
                            <span class="detail-label">Requested By</span>
                            <span class="detail-value"><?php echo htmlspecialchars($item['names']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Item</span>
                            <span class="detail-value"><?php echo htmlspecialchars($item['items']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Quantity</span>
                            <span class="detail-value"><?php echo htmlspecialchars($item['quantity']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">When Needed</span>
                            <span class="detail-value"><?php echo $item['when_needed'] ? date('F j, Y', strtotime($item['when_needed'])) : 'Not specified'; ?></span>
                        </div>
                        <div class="detail-item" style="grid-column: 1 / -1;">
                            <span class="detail-label">Reason</span>
                            <span class="detail-value"><?php echo nl2br(htmlspecialchars($item['reason'])); ?></span>
                        </div>
                    </div>

                    <div class="decision-section">
                        <div class="decision-actions">
                            <?php if ($itemStatus === 'pending'): ?>
                                <button type="button" class="item-action-btn approve" data-item-id="<?php echo (int)$item['id']; ?>" onclick="updateItemStatus(<?php echo (int)$item['id']; ?>, 'approved')">Approve</button>
                                <button type="button" class="item-action-btn decline" data-item-id="<?php echo (int)$item['id']; ?>" onclick="updateItemStatus(<?php echo (int)$item['id']; ?>, 'declined')">Decline</button>
                            <?php else: ?>
                                <span class="processed-note">Already processed</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<script>
    // Pass PHP data to JavaScript for filtering
    // Use var (not const) so re-execution of this inline script doesn't throw a redeclaration error
    var allItemsData = <?php echo json_encode($items); ?>;
    
    // Update item status (approve/decline)
    function updateItemStatus(itemId, newStatus) {
        console.log('Updating item status:', itemId, newStatus);
        
        const isDecline = newStatus === 'declined';
        showAppConfirm(
            `Are you sure you want to ${newStatus} this request?`,
            isDecline ? 'Decline Request?' : 'Approve Request?',
            function () {
                performItemStatusUpdate(itemId, newStatus);
            },
            null,
            {
                type: isDecline ? 'danger' : 'confirm',
                okText: isDecline ? 'Decline' : 'Approve',
                cancelText: 'Cancel'
            }
        );
    }

    function performItemStatusUpdate(itemId, newStatus) {
        const formData = new FormData();
        formData.append('action', 'update_status');
        formData.append('request_id', itemId);
        formData.append('status', newStatus);
        formData.append('table', 'requested_items');
        
        // Show loading state
        const buttons = document.querySelectorAll(`.item-action-btn[data-item-id="${itemId}"]`);
        buttons.forEach(btn => {
            btn.disabled = true;
            btn.textContent = 'Processing...';
        });
        
        fetch('/Prototype/database_requests.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.success) {
                showAppAlert(`Request ${newStatus} successfully!`, newStatus === 'approved' ? 'Request Approved' : 'Request Declined', null, { type: 'success' });
                // Update the item in our local data array
                const itemIndex = allItemsData.findIndex(item => item.id == itemId);
                if (itemIndex !== -1) {
                    allItemsData[itemIndex].action = newStatus;
                }
                
                // Re-apply current filter to update display
                renderItemsFiltered();
            } else {
                showAppAlert('Error: ' + (data.error || 'Failed to update status'), 'Unable to Update Request', null, { type: 'error' });
                // Rebuild the list so the action buttons are restored
                renderItemsFiltered();
            }
        })
        .catch(error => {
            console.error('Error updating status:', error);
            showAppAlert('An error occurred while updating the request status. Please try again.', 'Something Went Wrong', null, { type: 'error' });
            // Rebuild the list so the action buttons are restored
            renderItemsFiltered();
        });
    }
    
    // ===== Filter state (use window props so re-execution doesn't throw) =====
    window.itemsStatusFilter = 'pending';
    window.itemsTimeFilter = { month: '', year: '' };
    window.itemsCurrentYear = <?php echo $currentYear; ?>;

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
        return d ? d.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'Not specified';
    }

    // Keep the segmented status control in sync with the active filter
    function updateItemsTabs() {
        document.querySelectorAll('.vr-tab[data-status]').forEach(tab => {
            const isActive = tab.dataset.status === window.itemsStatusFilter;
            tab.classList.toggle('active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function (character) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[character];
        });
    }

    // Make sure a notification's request is visible under the current filters.
    // Only adjust the filters that would hide it, so an active status or time
    // filter is preserved whenever the target already matches it.
    function alignItemsFiltersToTarget(target) {
        if (!target) return;

        const rawStatus = String(target.action || target.status || 'pending').toLowerCase();
        const targetStatus = ['pending', 'approved', 'declined'].includes(rawStatus) ? rawStatus : 'all';
        if (window.itemsStatusFilter !== 'all' && window.itemsStatusFilter !== targetStatus) {
            window.itemsStatusFilter = targetStatus;
        }

        const targetDate = parseDateOnly(target.when_needed);
        const currentMonth = String(window.itemsTimeFilter.month || '');
        const currentYear = String(window.itemsTimeFilter.year || '');
        let hiddenByTime = false;

        if (!targetDate) {
            // An item without a when_needed date is visible unless a month filter
            // is active (the year filter intentionally ignores undated rows).
            hiddenByTime = currentMonth !== '';
        } else {
            const monthMatches = !currentMonth || String(targetDate.getMonth() + 1) === currentMonth;
            const yearMatches = !currentYear || String(targetDate.getFullYear()) === currentYear;
            hiddenByTime = !monthMatches || !yearMatches;
        }

        if (hiddenByTime) {
            const monthFilter = document.getElementById('monthFilter');
            const yearFilter = document.getElementById('yearFilter');
            if (targetDate) {
                const month = String(targetDate.getMonth() + 1);
                const year = String(targetDate.getFullYear());
                window.itemsTimeFilter = { month, year };
                if (monthFilter) monthFilter.value = month;
                if (yearFilter) yearFilter.value = year;
            } else {
                // Drop the month filter so the undated request is reachable; the
                // year filter does not hide undated rows.
                window.itemsTimeFilter.month = '';
                if (monthFilter) monthFilter.value = '';
            }
        }
    }

    // Render items applying current status + time filters
    function renderItemsFiltered() {
        updateItemsTabs();
        let filtered = allItemsData || [];
        const targetId = Number(window.notificationTargetId) || 0;

        if (targetId && window.notificationTargetAlignedId !== targetId) {
            const target = filtered.find(item => Number(item.id) === targetId);
            if (target) {
                alignItemsFiltersToTarget(target);
                updateItemsTabs();
                window.notificationTargetAlignedId = targetId;
            }
        }

        // Apply status filter
        if (window.itemsStatusFilter !== 'all') {
            filtered = filtered.filter(item => {
                const itemStatus = item.action || item.status || 'pending';
                return itemStatus === window.itemsStatusFilter;
            });
        }

        // Apply time (month/year) filter based on when_needed
        if (window.itemsTimeFilter.month || window.itemsTimeFilter.year) {
            filtered = filtered.filter(item => {
                // Items saved without a when_needed date must never be hidden by a
                // year/month filter, otherwise they become unreachable.
                if (!item.when_needed) return window.itemsTimeFilter.month === '';
                const d = parseDateOnly(item.when_needed);
                if (!d) return false;
                const monthMatch = !window.itemsTimeFilter.month || (d.getMonth() + 1) == window.itemsTimeFilter.month;
                const yearMatch = !window.itemsTimeFilter.year || d.getFullYear() == window.itemsTimeFilter.year;
                return monthMatch && yearMatch;
            });
        }

        const label = window.itemsStatusFilter === 'all' ? 'all' : window.itemsStatusFilter;
        displayFilteredItems(filtered, label);

        // Scroll to and briefly highlight the record that was opened from a
        // notification, then consume the deep-link id after the highlight window.
        if (targetId) {
            const targetElement = document.getElementById('requested-item-' + targetId);
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

    // Show all items (clear status filter)
    function showAllItems() {
        window.itemsStatusFilter = 'all';
        renderItemsFiltered();
    }
    
    // Filter items by status
    function filterItemsByStatus(status) {
        window.itemsStatusFilter = status;
        renderItemsFiltered();
    }
    
    // Display filtered items as cards
    function displayFilteredItems(items, status) {
        const requestList = document.getElementById('requestList');
        if (!requestList) return;

        if (items.length === 0) {
            const label = status === 'all' ? 'requested' : status + ' requested';
            requestList.innerHTML = `
                <div class="empty-message">
                    <h3>No ${label} items found</h3>
                    <p>There are no ${label} items for the selected filters.</p>
                </div>
            `;
            return;
        }

        let html = '';
        items.forEach(item => {
            const rawStatus = String(item.action || item.status || 'pending').toLowerCase();
            const itemStatus = ['pending', 'approved', 'declined'].includes(rawStatus) ? rawStatus : 'pending';
            const isPending = itemStatus === 'pending';
            const whenNeeded = item.when_needed ? formatDateOnly(item.when_needed) : 'Not specified';
            const reasonHtml = escapeHtml(item.reason || '').replace(/\n/g, '<br>');

            html += `
                <div class="request-item" id="requested-item-${Number(item.id)}">
                    <div class="request-header">
                        <div class="request-heading">
                            <span class="request-number">REQUEST #${escapeHtml(item.id)}</span>
                            <span class="request-subject">${escapeHtml(item.items)}</span>
                        </div>
                        <span class="status-badge status-${itemStatus}">${itemStatus.toUpperCase()}</span>
                    </div>

                    <div class="request-details">
                        <div class="detail-item">
                            <span class="detail-label">Requested By</span>
                            <span class="detail-value">${escapeHtml(item.names)}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Item</span>
                            <span class="detail-value">${escapeHtml(item.items)}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Quantity</span>
                            <span class="detail-value">${escapeHtml(item.quantity)}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">When Needed</span>
                            <span class="detail-value">${escapeHtml(whenNeeded)}</span>
                        </div>
                        <div class="detail-item" style="grid-column: 1 / -1;">
                            <span class="detail-label">Reason</span>
                            <span class="detail-value">${reasonHtml}</span>
                        </div>
                    </div>

                    <div class="decision-section">
                        <div class="decision-actions">
                            ${isPending
                                ? `<button type="button" class="item-action-btn approve" data-item-id="${Number(item.id)}" onclick="updateItemStatus(${Number(item.id)}, 'approved')">Approve</button>
                                   <button type="button" class="item-action-btn decline" data-item-id="${Number(item.id)}" onclick="updateItemStatus(${Number(item.id)}, 'declined')">Decline</button>`
                                : '<span class="processed-note">Already processed</span>'}
                        </div>
                    </div>
                </div>
            `;
        });

        requestList.innerHTML = html;
    }
    
    // Initialize year filter: server-rendered options are data-derived, so only
    // the current-year default needs to be applied here.
    function initializeYearFilter() {
        const yearFilter = document.getElementById('yearFilter');
        if (!yearFilter) return;
        if (!yearFilter.options.length) {
            yearFilter.innerHTML = '<option value="">All Years</option>';
        }
        yearFilter.value = String(window.itemsCurrentYear);
        window.itemsTimeFilter.year = String(window.itemsCurrentYear);
    }

    // Apply time filter
    function applyTimeFilter() {
        const monthFilter = document.getElementById('monthFilter');
        const yearFilter = document.getElementById('yearFilter');
        window.itemsTimeFilter.month = monthFilter ? monthFilter.value : '';
        window.itemsTimeFilter.year = yearFilter ? yearFilter.value : '';
        renderItemsFiltered();
    }

    // Clear time filter - returns to the current-year default
    function clearTimeFilter() {
        const monthFilter = document.getElementById('monthFilter');
        const yearFilter = document.getElementById('yearFilter');
        if (monthFilter) monthFilter.value = '';
        if (yearFilter) yearFilter.value = String(window.itemsCurrentYear);
        window.itemsTimeFilter = { month: '', year: String(window.itemsCurrentYear) };
        renderItemsFiltered();
    }
    
    // Auto-load requested items when this page is loaded
    setTimeout(() => {
        // Initialize with server data
        window.allItems = allItemsData;
        initializeYearFilter();
        
        // Automatically filter to pending items on page load
        filterItemsByStatus('pending');
    }, 100);
</script>
</div>

<?php include __DIR__ . '/../includes/custom_alert_modal.php'; ?>