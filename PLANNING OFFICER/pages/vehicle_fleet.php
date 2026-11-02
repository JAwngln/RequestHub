<?php
session_start();
if (!isset($_SESSION['email']) || trim($_SESSION['role']) !== 'planning_officer') {
    header("Location: ../../auth/login.php");
    exit();
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/Prototype/connect.php';
require_once __DIR__ . '/../includes/fleet_schema.php';

// The module owns its schema, so a fresh install that opens the fleet page before
// calling the API still gets the drivers table and the vehicle <-> driver link.
ensureFleetSchema($conn);

$openAdd = (($_GET['open'] ?? '') === 'add');

$vehicles = [];
$sql = "SELECT vf.*,
               d.full_name AS driver_name,
               d.license_number AS driver_license,
               d.contact_number AS driver_contact,
               d.status AS driver_status
        FROM vehicle_fleet vf
        LEFT JOIN drivers d ON d.id = vf.driver_id
        ORDER BY vf.plate_number";
$result = $conn->query($sql);
if (!$result) {
    // Never hide the fleet if the join is unavailable for any reason.
    $result = $conn->query("SELECT * FROM vehicle_fleet ORDER BY plate_number");
}
if ($result) {
    $vehicles = $result->fetch_all(MYSQLI_ASSOC);
}

$drivers = [];
$driverResult = $conn->query("SELECT * FROM drivers ORDER BY full_name");
if ($driverResult) {
    $drivers = $driverResult->fetch_all(MYSQLI_ASSOC);
}
$conn->close();

$activeDrivers = array_values(array_filter($drivers, fn($d) => $d['status'] === 'active'));

$totalVehicles      = count($vehicles);
$availableVehicles  = count(array_filter($vehicles, fn($v) => $v['status'] === 'available'));
$maintenanceCount   = count(array_filter($vehicles, fn($v) => $v['status'] === 'maintenance'));
$retiredCount       = count(array_filter($vehicles, fn($v) => $v['status'] === 'retired'));
$assignedCount      = count(array_filter($vehicles, fn($v) => !empty($v['driver_id'])));

// Used by the roster to show where a driver is currently assigned.
$driverAssignments = [];
foreach ($vehicles as $vehicle) {
    if (!empty($vehicle['driver_id'])) {
        $driverAssignments[(int)$vehicle['driver_id']] = $vehicle['plate_number'];
    }
}

$statusLabels = [
    'available' => 'Available',
    'maintenance' => 'IN MAINTENANCE',
    'retired' => 'Retired',
];

// Payload for the client-side dialogs. Kept in a data attribute (not a JSON
// <script>) because the dashboard re-executes every inline script it loads.
$vehiclePayload = array_map(function ($v) {
    return [
        'id'              => (int)$v['id'],
        'plate_number'    => (string)$v['plate_number'],
        'vehicle_type'    => (string)$v['vehicle_type'],
        'capacity'        => (int)$v['capacity'],
        'model'           => (string)($v['model'] ?? ''),
        'vehicle_name'    => (string)($v['vehicle_name'] ?? ''),
        'year_manufactured' => (int)($v['year_manufactured'] ?? 0),
        'fuel_type'       => (string)($v['fuel_type'] ?? ''),
        'status'          => (string)$v['status'],
        'driver_id'       => !empty($v['driver_id']) ? (int)$v['driver_id'] : 0,
    ];
}, $vehicles);

$driverPayload = array_map(function ($d) use ($driverAssignments) {
    return [
        'id'             => (int)$d['id'],
        'full_name'      => (string)$d['full_name'],
        'license_number' => (string)$d['license_number'],
        'contact_number' => (string)($d['contact_number'] ?? ''),
        'status'         => (string)$d['status'],
        'vehicle_plate'  => $driverAssignments[(int)$d['id']] ?? '',
    ];
}, $drivers);

$fleetJson = json_encode(
    ['vehicles' => $vehiclePayload, 'drivers' => $driverPayload],
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link href="/Prototype/assets/fonts/poppins.css" rel="stylesheet">
    <link href="/Prototype/assets/fonts/material-icons.css" rel="stylesheet">
    <style>
        /* ================= VEHICLE FLEET ================= */
        .fleet-page {
            --fleet-green: #1E5E3A;
            --fleet-green-dark: #174a2e;
            --fleet-border: #e5e7eb;
            --fleet-muted: #6b7280;
            --fleet-text: #111827;
            color: var(--fleet-text);
            font-family: "Poppins", "Segoe UI", Arial, sans-serif;
        }

        .fleet-page [hidden] {
            display: none !important;
        }

        .fleet-shell {
            max-width: 1180px;
            margin: 0 auto;
        }

        .fleet-panel {
            background: #ffffff;
            border: 1px solid var(--fleet-border);
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04);
            padding: 22px 24px 24px;
        }

        /* ---------- Header + inline controls ---------- */
        .fleet-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px 24px;
            flex-wrap: nowrap;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--fleet-border);
        }

        .fleet-head-title {
            min-width: 210px;
            flex: 0 1 auto;
        }

        .fleet-head-title h1 {
            margin: 0;
            font-size: 21px;
            font-weight: 700;
            letter-spacing: 0.06em;
            line-height: 1.25;
        }

        .fleet-head-title p {
            margin: 5px 0 0;
            color: var(--fleet-muted);
            font-size: 12.5px;
            line-height: 1.5;
        }

        .fleet-head-controls {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: nowrap;
            flex: 1 1 auto;
            min-width: 0;
            justify-content: flex-end;
        }

        .fleet-search {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            height: 38px;
            padding: 0 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #ffffff;
            flex: 1 1 180px;
            max-width: 300px;
            min-width: 130px;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .fleet-search:focus-within {
            border-color: var(--fleet-green);
            box-shadow: 0 0 0 3px rgba(30, 94, 58, 0.12);
        }

        .fleet-search .material-icons {
            font-size: 18px;
            color: #9ca3af;
        }

        .fleet-search input {
            width: 100%;
            min-width: 0;
            border: 0;
            outline: none;
            background: transparent;
            font-family: inherit;
            font-size: 12.5px;
            color: var(--fleet-text);
        }

        .fleet-select {
            position: relative;
            display: inline-flex;
            align-items: center;
            height: 38px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #ffffff;
            padding-left: 10px;
        }

        .fleet-select:focus-within {
            border-color: var(--fleet-green);
            box-shadow: 0 0 0 3px rgba(30, 94, 58, 0.12);
        }

        .fleet-select .material-icons {
            font-size: 17px;
            color: #9ca3af;
        }

        .fleet-select select {
            height: 100%;
            border: 0;
            outline: none;
            background: transparent;
            padding: 0 26px 0 6px;
            font-family: inherit;
            font-size: 12.5px;
            font-weight: 500;
            color: #374151;
            cursor: pointer;
        }

        .fleet-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            height: 38px;
            padding: 0 15px;
            border: 1px solid transparent;
            border-radius: 8px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.03em;
            white-space: nowrap;
            cursor: pointer;
            transition: background-color 0.18s ease, border-color 0.18s ease, color 0.18s ease;
        }

        .fleet-btn .material-icons {
            font-size: 18px;
        }

        .fleet-btn-primary {
            background: var(--fleet-green);
            border-color: var(--fleet-green);
            color: #ffffff;
        }

        .fleet-btn-primary:hover {
            background: var(--fleet-green-dark);
            border-color: var(--fleet-green-dark);
        }

        .fleet-btn-ghost {
            background: #ffffff;
            border-color: #d1d5db;
            color: #374151;
        }

        .fleet-btn-ghost:hover {
            background: #f9fafb;
            border-color: #9ca3af;
        }

        .fleet-btn-danger {
            background: #ffffff;
            border-color: #fecdca;
            color: #b42318;
        }

        .fleet-btn-danger:hover {
            background: #fef3f2;
            border-color: #fda29b;
        }

        .fleet-btn:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }

        /* ---------- Stats ---------- */
        .fleet-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(135px, 1fr));
            gap: 10px;
            margin: 18px 0 16px;
        }

        .fleet-stat {
            border: 1px solid var(--fleet-border);
            border-radius: 10px;
            padding: 12px 14px;
            background: #ffffff;
        }

        .fleet-stat-label {
            color: var(--fleet-muted);
            font-size: 9.5px;
            font-weight: 600;
            letter-spacing: 0.09em;
            text-transform: uppercase;
        }

        .fleet-stat-value {
            margin-top: 4px;
            font-size: 20px;
            font-weight: 700;
            line-height: 1.2;
        }

        /* ---------- List header ---------- */
        .fleet-list-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .fleet-result-count {
            color: var(--fleet-muted);
            font-size: 12px;
            font-weight: 500;
        }

        .fleet-clear {
            border: 0;
            background: transparent;
            color: var(--fleet-green);
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            padding: 4px 2px;
        }

        .fleet-clear:hover {
            text-decoration: underline;
        }

        /* ---------- Vehicle grid ---------- */
        .fleet-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
            gap: 12px;
        }

        .fleet-card {
            display: flex;
            flex-direction: column;
            border: 1px solid var(--fleet-border);
            border-radius: 10px;
            padding: 16px 18px;
            background: #ffffff;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .fleet-card:hover {
            border-color: #d1d5db;
            box-shadow: 0 2px 6px rgba(16, 24, 40, 0.05);
        }

        .fleet-card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .fleet-plate {
            display: block;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.03em;
            line-height: 1.3;
            overflow-wrap: anywhere;
        }

        .fleet-model {
            display: block;
            margin-top: 2px;
            color: var(--fleet-muted);
            font-size: 12px;
            line-height: 1.4;
        }

        .fleet-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            flex-shrink: 0;
            padding: 3px 9px;
            border: 1px solid transparent;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.06em;
            line-height: 1.6;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .fleet-status::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
            opacity: 0.85;
        }

        .fleet-status-available {
            background: #ecfdf3;
            border-color: #abefc6;
            color: #067647;
        }

        .fleet-status-maintenance {
            background: #fffbeb;
            border-color: #fde68a;
            color: #92400e;
        }

        .fleet-status-retired {
            background: #fef3f2;
            border-color: #fecdca;
            color: #b42318;
        }

        .fleet-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 18px;
            margin: 14px 0 0;
        }

        .fleet-meta dt {
            color: var(--fleet-muted);
            font-size: 9.5px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .fleet-meta dd {
            margin: 2px 0 0;
            font-size: 13px;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .fleet-meta-wide {
            grid-column: 1 / -1;
        }

        .fleet-unassigned {
            color: #9ca3af;
            font-style: italic;
        }

        .fleet-driver-note {
            display: block;
            color: #9ca3af;
            font-size: 10.5px;
        }

        .fleet-card-actions {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: auto;
            padding-top: 14px;
        }

        .fleet-action {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            height: 31px;
            padding: 0 10px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            background: #ffffff;
            color: #374151;
            font-family: inherit;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.18s ease, border-color 0.18s ease, color 0.18s ease;
        }

        .fleet-action .material-icons {
            font-size: 15px;
        }

        .fleet-action:hover {
            background: #f9fafb;
            border-color: #9ca3af;
        }

        .fleet-action-primary {
            border-color: var(--fleet-green);
            color: var(--fleet-green);
        }

        .fleet-action-primary:hover {
            background: #f0f8f3;
        }

        .fleet-action-warn {
            border-color: #fde68a;
            color: #92400e;
        }

        .fleet-action-warn:hover {
            background: #fffbeb;
            border-color: #fcd34d;
        }

        .fleet-action-ok {
            border-color: #abefc6;
            color: #067647;
        }

        .fleet-action-ok:hover {
            background: #ecfdf3;
            border-color: #75e0a7;
        }

        .fleet-action-danger {
            border-color: #fecdca;
            color: #b42318;
        }

        .fleet-action-danger:hover {
            background: #fef3f2;
            border-color: #fda29b;
        }

        .fleet-action-quiet {
            border-color: transparent;
            color: #6b7280;
            margin-left: auto;
        }

        .fleet-action-quiet:hover {
            border-color: #e5e7eb;
            background: #f9fafb;
        }

        /* ---------- Empty state ---------- */
        .fleet-empty {
            padding: 34px 20px;
            border: 1px dashed #d1d5db;
            border-radius: 10px;
            background: #f9fafb;
            text-align: center;
            color: var(--fleet-muted);
        }

        .fleet-empty .material-icons {
            font-size: 38px;
            color: #d1d5db;
        }

        .fleet-empty h3 {
            margin: 6px 0 4px;
            color: #374151;
            font-size: 14px;
        }

        .fleet-empty p {
            margin: 0;
            font-size: 12.5px;
        }

        /* ---------- Driver roster ---------- */
        .fleet-roster {
            margin-top: 16px;
            border: 1px solid var(--fleet-border);
            border-radius: 10px;
            background: #ffffff;
            overflow: hidden;
        }

        .fleet-roster > summary {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            cursor: pointer;
            list-style: none;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.03em;
            color: #374151;
            user-select: none;
        }

        .fleet-roster > summary::-webkit-details-marker {
            display: none;
        }

        .fleet-roster > summary:hover {
            background: #f9fafb;
        }

        .fleet-roster > summary .material-icons {
            font-size: 18px;
            color: #6b7280;
        }

        .fleet-roster-count {
            color: var(--fleet-muted);
            font-weight: 500;
        }

        .fleet-roster > summary .fleet-chevron {
            margin-left: auto;
            transition: transform 0.18s ease;
        }

        .fleet-roster[open] > summary .fleet-chevron {
            transform: rotate(180deg);
        }

        .fleet-roster-list {
            border-top: 1px solid var(--fleet-border);
        }

        .fleet-roster-row {
            display: grid;
            grid-template-columns: minmax(140px, 1.4fr) minmax(110px, 0.9fr) minmax(110px, 0.9fr) minmax(90px, 0.8fr) auto;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            font-size: 12.5px;
        }

        .fleet-roster-row + .fleet-roster-row {
            border-top: 1px solid #f3f4f6;
        }

        .fleet-roster-name {
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        .fleet-roster-muted {
            color: var(--fleet-muted);
            overflow-wrap: anywhere;
        }

        .fleet-roster-actions {
            display: flex;
            gap: 5px;
        }

        .fleet-roster-empty {
            padding: 16px;
            color: var(--fleet-muted);
            font-size: 12.5px;
        }

        .fleet-pill {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 999px;
            background: #f3f4f6;
            color: #4b5563;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .fleet-pill-active {
            background: #ecfdf3;
            color: #067647;
        }

        .fleet-pill-inactive {
            background: #f3f4f6;
            color: #6b7280;
        }

        /* ---------- Dialogs ---------- */
        .fleet-dialog {
            position: fixed;
            inset: 0;
            margin: auto;
            width: min(720px, calc(100vw - 32px));
            height: fit-content;
            max-height: calc(100vh - 48px);
            padding: 0;
            border: 0;
            border-radius: 14px;
            background: #ffffff;
            color: var(--fleet-text);
            box-shadow: 0 24px 60px rgba(16, 24, 40, 0.24);
            font-family: "Poppins", "Segoe UI", Arial, sans-serif;
            overflow: hidden;
        }

        .fleet-dialog-sm {
            width: min(460px, calc(100vw - 32px));
        }

        .fleet-dialog::backdrop {
            background: rgba(17, 24, 39, 0.5);
        }

        .fleet-dialog-form {
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 48px);
            margin: 0;
        }

        .fleet-dialog-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 22px 14px;
            border-bottom: 1px solid var(--fleet-border);
        }

        .fleet-dialog-head h2 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .fleet-dialog-head p {
            margin: 3px 0 0;
            color: var(--fleet-muted);
            font-size: 12px;
            line-height: 1.5;
        }

        .fleet-icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 30px;
            height: 30px;
            border: 1px solid transparent;
            border-radius: 7px;
            background: transparent;
            color: #6b7280;
            cursor: pointer;
        }

        .fleet-icon-btn:hover {
            background: #f3f4f6;
            color: #111827;
        }

        .fleet-icon-btn .material-icons {
            font-size: 18px;
        }

        .fleet-dialog-body {
            padding: 18px 22px 4px;
            overflow-y: auto;
        }

        .fleet-dialog-foot {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            padding: 14px 22px;
            border-top: 1px solid var(--fleet-border);
            background: #fafafa;
        }

        .fleet-form-section + .fleet-form-section {
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px dashed var(--fleet-border);
        }

        .fleet-form-section h3 {
            display: flex;
            align-items: center;
            gap: 6px;
            margin: 0 0 12px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #374151;
        }

        .fleet-form-section h3 .material-icons {
            font-size: 16px;
            color: var(--fleet-green);
        }

        .fleet-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 16px;
        }

        .fleet-field {
            display: flex;
            flex-direction: column;
            gap: 5px;
            min-width: 0;
        }

        .fleet-field-full {
            grid-column: 1 / -1;
        }

        .fleet-field label {
            color: var(--fleet-muted);
            font-size: 9.5px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .fleet-field input,
        .fleet-field select {
            width: 100%;
            height: 38px;
            padding: 6px 10px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            background: #ffffff;
            color: var(--fleet-text);
            font-family: inherit;
            font-size: 13px;
            box-sizing: border-box;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .fleet-field input:focus,
        .fleet-field select:focus {
            outline: none;
            border-color: var(--fleet-green);
            box-shadow: 0 0 0 3px rgba(30, 94, 58, 0.12);
        }

        .fleet-field input.is-invalid,
        .fleet-field select.is-invalid {
            border-color: #f04438;
            box-shadow: 0 0 0 3px rgba(240, 68, 56, 0.12);
        }

        .fleet-hint {
            margin: 0;
            color: #9ca3af;
            font-size: 10.5px;
            line-height: 1.45;
        }

        .fleet-driver-modes {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .fleet-driver-modes label {
            position: relative;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
        }

        .fleet-driver-modes input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .fleet-driver-modes span {
            display: inline-flex;
            align-items: center;
            height: 32px;
            padding: 0 12px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            background: #ffffff;
            color: #4b5563;
            font-size: 11.5px;
            font-weight: 600;
            transition: background-color 0.18s ease, border-color 0.18s ease, color 0.18s ease;
        }

        .fleet-driver-modes input:checked + span {
            border-color: var(--fleet-green);
            background: #f0f8f3;
            color: var(--fleet-green-dark);
        }

        .fleet-driver-modes input:focus-visible + span {
            outline: 2px solid var(--fleet-green);
            outline-offset: 2px;
        }

        .fleet-form-error {
            margin-bottom: 14px;
            padding: 10px 12px;
            border: 1px solid #fecdca;
            border-radius: 8px;
            background: #fef3f2;
            color: #b42318;
            font-size: 12px;
            line-height: 1.5;
        }

        .fleet-assign-summary {
            margin: 0 0 12px;
            padding: 10px 12px;
            border: 1px solid var(--fleet-border);
            border-radius: 8px;
            background: #f9fafb;
            font-size: 12.5px;
            color: #374151;
        }

        .fleet-assign-summary strong {
            color: #111827;
        }

        /* ---------- Responsive ---------- */
        @media (max-width: 900px) {
            .fleet-panel {
                padding: 18px;
            }

            .fleet-head {
                flex-wrap: wrap;
            }

            .fleet-head-controls {
                flex-wrap: wrap;
                justify-content: flex-start;
                flex-basis: 100%;
                min-width: 0;
            }

            .fleet-search {
                max-width: none;
            }
        }

        @media (max-width: 620px) {
            .fleet-stats {
                grid-template-columns: 1fr 1fr;
            }

            .fleet-grid {
                grid-template-columns: 1fr;
            }

            .fleet-head-controls > .fleet-btn,
            .fleet-select,
            .fleet-search {
                flex: 1 1 100%;
                max-width: none;
            }

            .fleet-head-controls > .fleet-btn {
                width: 100%;
            }

            .fleet-form-grid {
                grid-template-columns: 1fr;
            }

            .fleet-dialog-foot .fleet-btn {
                flex: 1 1 0;
            }

            .fleet-roster-row {
                grid-template-columns: 1fr auto;
                row-gap: 4px;
            }

            .fleet-roster-row .fleet-roster-muted {
                grid-column: 1 / -1;
            }

            .fleet-roster-actions {
                grid-column: 2;
                grid-row: 1;
            }
        }
    </style>
</head>
<body>
    <div class="fleet-page" data-open-add="<?php echo $openAdd ? '1' : '0'; ?>">
        <div id="fleetData" data-json="<?php echo htmlspecialchars($fleetJson, ENT_QUOTES, 'UTF-8'); ?>"></div>

        <div class="fleet-shell">
            <div class="fleet-panel">
                <header class="fleet-head">
                    <div class="fleet-head-title">
                        <h1>VEHICLE FLEET</h1>
                        <p>Vehicles and drivers in one place &mdash; register, assign, and track availability.</p>
                    </div>

                    <div class="fleet-head-controls">
                        <label class="fleet-search" for="fleetSearch">
                            <span class="material-icons">search</span>
                            <input type="search" id="fleetSearch" placeholder="Search plate, model, or driver" autocomplete="off">
                        </label>

                        <div class="fleet-select">
                            <span class="material-icons">filter_list</span>
                            <select id="fleetStatusFilter" aria-label="Filter vehicles by status">
                                <option value="all">All statuses</option>
                                <option value="available">Available</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="retired">Retired</option>
                            </select>
                        </div>

                        <button type="button" class="fleet-btn fleet-btn-ghost" onclick="openFleetDriverModal()">
                            <span class="material-icons">person_add</span>
                            Add Driver
                        </button>

                        <button type="button" class="fleet-btn fleet-btn-primary" onclick="openFleetVehicleModal()">
                            <span class="material-icons">add</span>
                            Add Vehicle
                        </button>
                    </div>
                </header>

                <section class="fleet-stats" aria-label="Fleet summary">
                    <div class="fleet-stat">
                        <div class="fleet-stat-label">Total Vehicles</div>
                        <div class="fleet-stat-value"><?php echo $totalVehicles; ?></div>
                    </div>
                    <div class="fleet-stat">
                        <div class="fleet-stat-label">Available</div>
                        <div class="fleet-stat-value"><?php echo $availableVehicles; ?></div>
                    </div>
                    <div class="fleet-stat">
                        <div class="fleet-stat-label">In Maintenance</div>
                        <div class="fleet-stat-value"><?php echo $maintenanceCount; ?></div>
                    </div>
                    <div class="fleet-stat">
                        <div class="fleet-stat-label">Retired</div>
                        <div class="fleet-stat-value"><?php echo $retiredCount; ?></div>
                    </div>
                    <div class="fleet-stat">
                        <div class="fleet-stat-label">Active Drivers</div>
                        <div class="fleet-stat-value"><?php echo count($activeDrivers); ?></div>
                    </div>
                </section>

                <div class="fleet-list-head">
                    <span class="fleet-result-count" id="fleetResultCount" aria-live="polite">
                        <?php echo $totalVehicles; ?> vehicle<?php echo $totalVehicles === 1 ? '' : 's'; ?>
                        &middot; <?php echo $assignedCount; ?> with a driver assigned
                    </span>
                    <button type="button" class="fleet-clear" id="fleetClearFilters" onclick="resetFleetFilters()" hidden>
                        Clear filters
                    </button>
                </div>

                <div class="fleet-empty" id="fleetEmpty" <?php echo empty($vehicles) ? '' : 'hidden'; ?>>
                    <span class="material-icons">directions_car</span>
                    <h3><?php echo empty($vehicles) ? 'No vehicles in the fleet yet' : 'No vehicles match your filters'; ?></h3>
                    <p><?php echo empty($vehicles) ? 'Add a vehicle (and its driver) to get started.' : 'Try a different search term or status.'; ?></p>
                </div>

                <div class="fleet-grid" id="vehicleGrid" <?php echo empty($vehicles) ? 'hidden' : ''; ?>>
                    <?php foreach ($vehicles as $vehicle): ?>
                        <?php
                            $plate      = (string)$vehicle['plate_number'];
                            $typeLabel  = ucfirst((string)$vehicle['vehicle_type']);
                            $modelLabel = trim((string)($vehicle['model'] ?? ''));
                            if ($modelLabel === '') {
                                $modelLabel = trim((string)($vehicle['vehicle_name'] ?? ''));
                            }
                            $driverId   = !empty($vehicle['driver_id']) ? (int)$vehicle['driver_id'] : 0;
                            $driverName = $driverId && !empty($vehicle['driver_name']) ? (string)$vehicle['driver_name'] : '';
                            $searchText = mb_strtolower(implode(' ', [
                                $plate,
                                $typeLabel,
                                $modelLabel,
                                $driverName,
                                (string)($vehicle['fuel_type'] ?? ''),
                            ]));
                            $status = (string)$vehicle['status'];
                        ?>
                        <article class="fleet-card"
                                 data-vehicle-id="<?php echo (int)$vehicle['id']; ?>"
                                 data-status="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>"
                                 data-search="<?php echo htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="fleet-card-top">
                                <div>
                                    <span class="fleet-plate"><?php echo htmlspecialchars($plate); ?></span>
                                    <span class="fleet-model"><?php echo htmlspecialchars($typeLabel); ?></span>
                                </div>
                                <span class="fleet-status fleet-status-<?php echo htmlspecialchars($status); ?>">
                                    <?php echo htmlspecialchars($statusLabels[$status] ?? ucfirst($status)); ?>
                                </span>
                            </div>

                            <dl class="fleet-meta">
                                <div class="fleet-meta-wide">
                                    <dt>Model</dt>
                                    <dd><?php echo $modelLabel !== '' ? htmlspecialchars($modelLabel) : '&mdash;'; ?></dd>
                                </div>
                                <div>
                                    <dt>Assigned Driver</dt>
                                    <dd>
                                        <?php if ($driverName !== ''): ?>
                                            <?php echo htmlspecialchars($driverName); ?>
                                            <?php if (($vehicle['driver_status'] ?? 'active') !== 'active'): ?>
                                                <span class="fleet-driver-note">Inactive driver</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="fleet-unassigned">Unassigned</span>
                                        <?php endif; ?>
                                    </dd>
                                </div>
                                <div>
                                    <dt>Capacity</dt>
                                    <dd><?php echo (int)$vehicle['capacity']; ?> passengers</dd>
                                </div>
                            </dl>

                            <div class="fleet-card-actions">
                                <button type="button" class="fleet-action fleet-action-primary"
                                        onclick="openFleetVehicleModal(<?php echo (int)$vehicle['id']; ?>)">
                                    <span class="material-icons">edit</span> Edit
                                </button>
                                <button type="button" class="fleet-action"
                                        onclick="openFleetAssignModal(<?php echo (int)$vehicle['id']; ?>)">
                                    <span class="material-icons">person_add</span>
                                    <?php echo $driverId ? 'Change Driver' : 'Assign Driver'; ?>
                                </button>
                                <?php if ($status === 'retired'): ?>
                                    <button type="button" class="fleet-action fleet-action-warn"
                                            onclick="restoreFleetVehicle(<?php echo (int)$vehicle['id']; ?>, '<?php echo htmlspecialchars($plate, ENT_QUOTES); ?>')">
                                        <span class="material-icons">restart_alt</span> Restore
                                    </button>
                                <?php elseif ($status === 'maintenance'): ?>
                                    <button type="button" class="fleet-action fleet-action-ok"
                                            onclick="restoreFleetVehicle(<?php echo (int)$vehicle['id']; ?>, '<?php echo htmlspecialchars($plate, ENT_QUOTES); ?>')">
                                        <span class="material-icons">check_circle</span> Set Available
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="fleet-action fleet-action-warn"
                                            onclick="setFleetMaintenance(<?php echo (int)$vehicle['id']; ?>, '<?php echo htmlspecialchars($plate, ENT_QUOTES); ?>')">
                                        <span class="material-icons">build_circle</span> Set to Maintenance
                                    </button>
                                <?php endif; ?>
                                <button type="button" class="fleet-action fleet-action-quiet fleet-action-danger"
                                        onclick="removeFleetVehicle(<?php echo (int)$vehicle['id']; ?>, '<?php echo htmlspecialchars($plate, ENT_QUOTES); ?>')"
                                        title="Remove vehicle from the fleet">
                                    <span class="material-icons">delete</span> Remove
                                </button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <details class="fleet-roster" id="fleetRoster">
                    <summary>
                        <span class="material-icons">badge</span>
                        <span>Driver roster</span>
                        <span class="fleet-roster-count">
                            <?php echo count($activeDrivers); ?> active<?php echo count($drivers) !== count($activeDrivers) ? ' &middot; ' . (count($drivers) - count($activeDrivers)) . ' inactive' : ''; ?>
                        </span>
                        <span class="material-icons fleet-chevron">expand_more</span>
                    </summary>
                    <div class="fleet-roster-list">
                        <?php if (empty($drivers)): ?>
                            <div class="fleet-roster-empty">No drivers yet. Use <strong>Add Driver</strong> or add one with a vehicle.</div>
                        <?php else: ?>
                            <?php foreach ($drivers as $driver): ?>
                                <?php
                                    $assignedPlate = $driverAssignments[(int)$driver['id']] ?? '';
                                    $isActive = $driver['status'] === 'active';
                                ?>
                                <div class="fleet-roster-row">
                                    <div class="fleet-roster-name">
                                        <?php echo htmlspecialchars((string)$driver['full_name']); ?>
                                        <span class="fleet-pill <?php echo $isActive ? 'fleet-pill-active' : 'fleet-pill-inactive'; ?>">
                                            <?php echo $isActive ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </div>
                                    <div class="fleet-roster-muted"><?php echo htmlspecialchars((string)$driver['license_number']); ?></div>
                                    <div class="fleet-roster-muted">
                                        <?php echo !empty($driver['contact_number']) ? htmlspecialchars((string)$driver['contact_number']) : '&mdash;'; ?>
                                    </div>
                                    <div class="fleet-roster-muted">
                                        <?php echo $assignedPlate !== '' ? htmlspecialchars($assignedPlate) : 'Unassigned'; ?>
                                    </div>
                                    <div class="fleet-roster-actions">
                                        <button type="button" class="fleet-action"
                                                onclick="openFleetDriverModal(<?php echo (int)$driver['id']; ?>)">
                                            <span class="material-icons">edit</span>
                                        </button>
                                        <button type="button" class="fleet-action fleet-action-danger"
                                                onclick="removeFleetDriver(<?php echo (int)$driver['id']; ?>, '<?php echo htmlspecialchars((string)$driver['full_name'], ENT_QUOTES); ?>')"
                                                title="Delete driver">
                                            <span class="material-icons">delete</span>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </details>
            </div>
        </div>

        <!-- ============ Add / Edit Vehicle + Driver ============ -->
        <dialog class="fleet-dialog" id="fleetVehicleDialog" aria-labelledby="fleetVehicleDialogTitle">
            <form class="fleet-dialog-form" id="fleetVehicleForm" onsubmit="return false;">
                <div class="fleet-dialog-head">
                    <div>
                        <h2 id="fleetVehicleDialogTitle">Add vehicle</h2>
                        <p>Register the vehicle and assign a driver in one step.</p>
                    </div>
                    <button type="button" class="fleet-icon-btn" onclick="closeFleetDialog('fleetVehicleDialog')" aria-label="Close">
                        <span class="material-icons">close</span>
                    </button>
                </div>

                <div class="fleet-dialog-body">
                    <div class="fleet-form-error" id="fleetVehicleError" role="alert" hidden></div>

                    <section class="fleet-form-section">
                        <h3><span class="material-icons">directions_car</span> Vehicle details</h3>
                        <div class="fleet-form-grid">
                            <div class="fleet-field">
                                <label for="fleetPlate">Plate number *</label>
                                <input type="text" id="fleetPlate" placeholder="e.g., ABC 1234" autocomplete="off">
                            </div>
                            <div class="fleet-field">
                                <label for="fleetVehicleType">Vehicle type *</label>
                                <select id="fleetVehicleType">
                                    <option value="">Select type</option>
                                    <option value="sedan">Sedan</option>
                                    <option value="suv">SUV</option>
                                    <option value="van">Van</option>
                                    <option value="truck">Truck</option>
                                    <option value="motorcycle">Motorcycle</option>
                                    <option value="bus">Bus</option>
                                </select>
                            </div>
                            <div class="fleet-field">
                                <label for="fleetModel">Model / name</label>
                                <input type="text" id="fleetModel" placeholder="e.g., Toyota Innova 2023" autocomplete="off">
                            </div>
                            <div class="fleet-field">
                                <label for="fleetCapacity">Capacity (passengers) *</label>
                                <input type="number" id="fleetCapacity" min="1" max="80" placeholder="e.g., 16">
                            </div>
                            <div class="fleet-field">
                                <label for="fleetYear">Year manufactured</label>
                                <input type="number" id="fleetYear" min="1990" max="<?php echo date('Y') + 1; ?>" placeholder="e.g., 2023">
                            </div>
                            <div class="fleet-field">
                                <label for="fleetFuel">Fuel type</label>
                                <select id="fleetFuel">
                                    <option value="">Select fuel</option>
                                    <option value="gasoline">Gasoline</option>
                                    <option value="diesel">Diesel</option>
                                    <option value="electric">Electric</option>
                                    <option value="hybrid">Hybrid</option>
                                </select>
                            </div>
                            <div class="fleet-field">
                                <label for="fleetStatus">Status</label>
                                <select id="fleetStatus">
                                    <option value="available">Available</option>
                                    <option value="maintenance">Maintenance</option>
                                    <option value="retired">Retired</option>
                                </select>
                            </div>
                        </div>
                    </section>

                    <section class="fleet-form-section">
                        <h3><span class="material-icons">badge</span> Assigned driver</h3>

                        <div class="fleet-driver-modes" role="radiogroup" aria-label="Driver assignment">
                            <label>
                                <input type="radio" name="fleetDriverMode" value="none" checked onchange="syncFleetDriverMode()">
                                <span>No driver</span>
                            </label>
                            <label>
                                <input type="radio" name="fleetDriverMode" value="existing" onchange="syncFleetDriverMode()">
                                <span>Assign existing</span>
                            </label>
                            <label>
                                <input type="radio" name="fleetDriverMode" value="new" onchange="syncFleetDriverMode()">
                                <span>Add new driver</span>
                            </label>
                        </div>

                        <div class="fleet-form-grid" id="fleetExistingDriverWrap" hidden>
                            <div class="fleet-field fleet-field-full">
                                <label for="fleetDriverSelect">Driver</label>
                                <select id="fleetDriverSelect"></select>
                                <p class="fleet-hint" id="fleetDriverHint">A driver can only be assigned to one vehicle. Selecting a driver who is on another vehicle moves them here.</p>
                            </div>
                        </div>

                        <div class="fleet-form-grid" id="fleetNewDriverWrap" hidden>
                            <div class="fleet-field">
                                <label for="fleetDriverName">Full name *</label>
                                <input type="text" id="fleetDriverName" placeholder="e.g., Juan D. Dela Cruz" autocomplete="off">
                            </div>
                            <div class="fleet-field">
                                <label for="fleetDriverLicense">License number *</label>
                                <input type="text" id="fleetDriverLicense" placeholder="e.g., N01-23-456789" autocomplete="off">
                            </div>
                            <div class="fleet-field fleet-field-full">
                                <label for="fleetDriverContact">Contact number</label>
                                <input type="text" id="fleetDriverContact" placeholder="e.g., 0917 000 0000" autocomplete="off">
                            </div>
                        </div>
                    </section>
                </div>

                <div class="fleet-dialog-foot">
                    <button type="button" class="fleet-btn fleet-btn-ghost" onclick="closeFleetDialog('fleetVehicleDialog')">Cancel</button>
                    <button type="button" class="fleet-btn fleet-btn-primary" id="fleetVehicleSubmit" onclick="submitFleetVehicle()">
                        Save vehicle
                    </button>
                </div>
            </form>
        </dialog>

        <!-- ============ Add / Edit Driver ============ -->
        <dialog class="fleet-dialog fleet-dialog-sm" id="fleetDriverDialog" aria-labelledby="fleetDriverDialogTitle">
            <form class="fleet-dialog-form" id="fleetDriverForm" onsubmit="return false;">
                <div class="fleet-dialog-head">
                    <div>
                        <h2 id="fleetDriverDialogTitle">Add driver</h2>
                        <p>Drivers can be attached to a vehicle now or later.</p>
                    </div>
                    <button type="button" class="fleet-icon-btn" onclick="closeFleetDialog('fleetDriverDialog')" aria-label="Close">
                        <span class="material-icons">close</span>
                    </button>
                </div>

                <div class="fleet-dialog-body">
                    <div class="fleet-form-error" id="fleetDriverError" role="alert" hidden></div>

                    <div class="fleet-form-grid">
                        <div class="fleet-field fleet-field-full">
                            <label for="driverFormName">Full name *</label>
                            <input type="text" id="driverFormName" placeholder="e.g., Juan D. Dela Cruz" autocomplete="off">
                        </div>
                        <div class="fleet-field fleet-field-full">
                            <label for="driverFormLicense">License number *</label>
                            <input type="text" id="driverFormLicense" placeholder="e.g., N01-23-456789" autocomplete="off">
                        </div>
                        <div class="fleet-field fleet-field-full">
                            <label for="driverFormContact">Contact number</label>
                            <input type="text" id="driverFormContact" placeholder="e.g., 0917 000 0000" autocomplete="off">
                        </div>
                        <div class="fleet-field fleet-field-full">
                            <label for="driverFormStatus">Status</label>
                            <select id="driverFormStatus">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="fleet-dialog-foot">
                    <button type="button" class="fleet-btn fleet-btn-ghost" onclick="closeFleetDialog('fleetDriverDialog')">Cancel</button>
                    <button type="button" class="fleet-btn fleet-btn-primary" id="fleetDriverSubmit" onclick="submitFleetDriver()">
                        Save driver
                    </button>
                </div>
            </form>
        </dialog>

        <!-- ============ Assign / Change Driver ============ -->
        <dialog class="fleet-dialog fleet-dialog-sm" id="fleetAssignDialog" aria-labelledby="fleetAssignDialogTitle">
            <form class="fleet-dialog-form" id="fleetAssignForm" onsubmit="return false;">
                <div class="fleet-dialog-head">
                    <div>
                        <h2 id="fleetAssignDialogTitle">Assign driver</h2>
                        <p>Choose who drives this vehicle.</p>
                    </div>
                    <button type="button" class="fleet-icon-btn" onclick="closeFleetDialog('fleetAssignDialog')" aria-label="Close">
                        <span class="material-icons">close</span>
                    </button>
                </div>

                <div class="fleet-dialog-body">
                    <div class="fleet-form-error" id="fleetAssignError" role="alert" hidden></div>
                    <p class="fleet-assign-summary" id="fleetAssignSummary"></p>

                    <div class="fleet-form-grid">
                        <div class="fleet-field fleet-field-full">
                            <label for="fleetAssignSelect">Driver</label>
                            <select id="fleetAssignSelect"></select>
                            <p class="fleet-hint" id="fleetAssignHint"></p>
                        </div>
                    </div>
                </div>

                <div class="fleet-dialog-foot">
                    <button type="button" class="fleet-btn fleet-btn-ghost" onclick="openFleetDriverModal()">
                        <span class="material-icons">person_add</span> New driver
                    </button>
                    <button type="button" class="fleet-btn fleet-btn-ghost" onclick="closeFleetDialog('fleetAssignDialog')">Cancel</button>
                    <button type="button" class="fleet-btn fleet-btn-primary" id="fleetAssignSubmit" onclick="submitFleetAssign()">
                        Save assignment
                    </button>
                </div>
            </form>
        </dialog>
    </div>

    <script>
    (function () {
        'use strict';

        var API = '/Prototype/database_requests.php';
        var FILTER_KEY = 'fleetFilters';

        var state = {
            vehicles: [],
            drivers: [],
            editingVehicleId: 0,
            editingDriverId: 0,
            assignVehicleId: 0,
            pendingAssignVehicleId: 0
        };

        function readFleetData() {
            var el = document.getElementById('fleetData');
            if (!el) return;
            try {
                var parsed = JSON.parse(el.dataset.json || '{}');
                state.vehicles = Array.isArray(parsed.vehicles) ? parsed.vehicles : [];
                state.drivers = Array.isArray(parsed.drivers) ? parsed.drivers : [];
            } catch (error) {
                console.error('Could not read fleet data', error);
            }
        }

        function apiPost(params) {
            var body = new URLSearchParams(params);
            return fetch(API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            }).then(function (response) {
                return response.json();
            });
        }

        function byId(list, id) {
            id = Number(id) || 0;
            for (var i = 0; i < list.length; i++) {
                if (Number(list[i].id) === id) return list[i];
            }
            return null;
        }

        function showError(id, message) {
            var box = document.getElementById(id);
            if (!box) return;
            box.textContent = message || 'Something went wrong.';
            box.hidden = false;
        }

        function clearError(id) {
            var box = document.getElementById(id);
            if (box) {
                box.hidden = true;
                box.textContent = '';
            }
        }

        function clearInvalid() {
            var fields = document.querySelectorAll('.fleet-dialog .is-invalid');
            for (var i = 0; i < fields.length; i++) {
                fields[i].classList.remove('is-invalid');
            }
        }

        function markInvalid(fieldId) {
            var field = document.getElementById(fieldId);
            if (!field) return;
            field.classList.add('is-invalid');
            if (typeof field.focus === 'function') field.focus();
        }

        function openDialog(id) {
            var dialog = document.getElementById(id);
            if (!dialog) return;
            if (typeof dialog.showModal === 'function') {
                if (!dialog.open) dialog.showModal();
            } else {
                dialog.setAttribute('open', 'open');
            }
        }

        function closeDialog(id) {
            var dialog = document.getElementById(id);
            if (!dialog) return;
            if (typeof dialog.close === 'function' && dialog.open) {
                dialog.close();
            } else {
                dialog.removeAttribute('open');
            }
        }

        function driverOptionLabel(driver) {
            var label = driver.full_name + ' — ' + driver.license_number;
            if (driver.vehicle_plate) {
                label += ' (on ' + driver.vehicle_plate + ')';
            }
            if (driver.status !== 'active') {
                label += ' [inactive]';
            }
            return label;
        }

        function fillDriverSelect(select, currentId) {
            if (!select) return;
            select.innerHTML = '';
            var placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Select a driver';
            select.appendChild(placeholder);

            var current = byId(state.drivers, currentId);
            var seen = {};

            state.drivers.forEach(function (driver) {
                if (driver.status !== 'active' && driver.id !== currentId) return;
                var option = document.createElement('option');
                option.value = String(driver.id);
                option.textContent = driverOptionLabel(driver);
                if (Number(driver.id) === Number(currentId)) option.selected = true;
                select.appendChild(option);
                seen[driver.id] = true;
            });

            // Keep a now-inactive driver visible when editing their vehicle so the
            // form does not silently reset to "no driver".
            if (current && !seen[current.id]) {
                var option = document.createElement('option');
                option.value = String(current.id);
                option.textContent = driverOptionLabel(current);
                option.selected = true;
                select.appendChild(option);
            }
        }

        function setDriverMode(mode) {
            var radios = document.querySelectorAll('input[name="fleetDriverMode"]');
            for (var i = 0; i < radios.length; i++) {
                radios[i].checked = radios[i].value === mode;
            }
            syncFleetDriverMode();
        }

        function syncFleetDriverMode() {
            var selected = document.querySelector('input[name="fleetDriverMode"]:checked');
            var mode = selected ? selected.value : 'none';
            var existing = document.getElementById('fleetExistingDriverWrap');
            var fresh = document.getElementById('fleetNewDriverWrap');
            if (existing) existing.hidden = mode !== 'existing';
            if (fresh) fresh.hidden = mode !== 'new';
        }

        /* ---------------- Add / edit vehicle ---------------- */

        function openFleetVehicleModal(vehicleId) {
            readFleetData();
            clearInvalid();
            clearError('fleetVehicleError');

            var vehicle = byId(state.vehicles, vehicleId);
            state.editingVehicleId = vehicle ? Number(vehicle.id) : 0;

            var title = document.getElementById('fleetVehicleDialogTitle');
            var submit = document.getElementById('fleetVehicleSubmit');
            if (title) title.textContent = vehicle ? 'Edit vehicle' : 'Add vehicle';
            if (submit) submit.textContent = vehicle ? 'Save changes' : 'Add vehicle';

            document.getElementById('fleetPlate').value = vehicle ? vehicle.plate_number : '';
            document.getElementById('fleetVehicleType').value = vehicle ? vehicle.vehicle_type : '';
            document.getElementById('fleetModel').value = vehicle ? (vehicle.model || vehicle.vehicle_name || '') : '';
            document.getElementById('fleetCapacity').value = vehicle ? String(vehicle.capacity || '') : '';
            document.getElementById('fleetYear').value = vehicle && vehicle.year_manufactured ? String(vehicle.year_manufactured) : '';
            document.getElementById('fleetFuel').value = vehicle ? (vehicle.fuel_type || '') : '';
            document.getElementById('fleetStatus').value = vehicle ? (vehicle.status || 'available') : 'available';

            document.getElementById('fleetDriverName').value = '';
            document.getElementById('fleetDriverLicense').value = '';
            document.getElementById('fleetDriverContact').value = '';

            var select = document.getElementById('fleetDriverSelect');
            fillDriverSelect(select, vehicle ? vehicle.driver_id : 0);

            if (vehicle && vehicle.driver_id) {
                setDriverMode('existing');
            } else {
                setDriverMode('none');
            }

            openDialog('fleetVehicleDialog');
        }

        function submitFleetVehicle() {
            clearInvalid();
            clearError('fleetVehicleError');

            var plate = document.getElementById('fleetPlate').value.trim();
            var type = document.getElementById('fleetVehicleType').value;
            var capacity = document.getElementById('fleetCapacity').value;
            var model = document.getElementById('fleetModel').value.trim();
            var year = document.getElementById('fleetYear').value;
            var fuel = document.getElementById('fleetFuel').value;
            var status = document.getElementById('fleetStatus').value;
            var modeEl = document.querySelector('input[name="fleetDriverMode"]:checked');
            var driverMode = modeEl ? modeEl.value : 'none';

            if (!plate) {
                markInvalid('fleetPlate');
                showError('fleetVehicleError', 'Plate number is required.');
                return;
            }
            if (!type) {
                markInvalid('fleetVehicleType');
                showError('fleetVehicleError', 'Vehicle type is required.');
                return;
            }
            if (!capacity || Number(capacity) < 1) {
                markInvalid('fleetCapacity');
                showError('fleetVehicleError', 'Capacity must be at least 1 passenger.');
                return;
            }

            var params = {
                action: 'save_fleet_vehicle',
                id: state.editingVehicleId || 0,
                plate_number: plate,
                vehicle_type: type,
                capacity: capacity,
                model: model,
                year_manufactured: year,
                fuel_type: fuel,
                status: status,
                driver_mode: driverMode
            };

            if (driverMode === 'existing') {
                var driverId = document.getElementById('fleetDriverSelect').value;
                if (!driverId) {
                    markInvalid('fleetDriverSelect');
                    showError('fleetVehicleError', 'Select a driver or switch to "No driver".');
                    return;
                }
                params.driver_id = driverId;
            } else if (driverMode === 'new') {
                var newName = document.getElementById('fleetDriverName').value.trim();
                var newLicense = document.getElementById('fleetDriverLicense').value.trim();
                if (!newName) {
                    markInvalid('fleetDriverName');
                    showError('fleetVehicleError', 'Driver name is required.');
                    return;
                }
                if (!newLicense) {
                    markInvalid('fleetDriverLicense');
                    showError('fleetVehicleError', 'Driver license number is required.');
                    return;
                }
                params.driver_full_name = newName;
                params.driver_license = newLicense;
                params.driver_contact = document.getElementById('fleetDriverContact').value.trim();
            }

            var submit = document.getElementById('fleetVehicleSubmit');
            if (submit) {
                submit.disabled = true;
                submit.dataset.originalText = submit.textContent;
                submit.textContent = 'Saving...';
            }

            apiPost(params).then(function (result) {
                if (result && result.success) {
                    closeDialog('fleetVehicleDialog');
                    refreshFleet();
                    return;
                }
                var field = result && result.field;
                if (field) {
                    var fieldMap = {
                        plate_number: 'fleetPlate',
                        vehicle_type: 'fleetVehicleType',
                        capacity: 'fleetCapacity',
                        year_manufactured: 'fleetYear',
                        driver_id: 'fleetDriverSelect',
                        full_name: 'fleetDriverName',
                        license_number: 'fleetDriverLicense',
                        contact_number: 'fleetDriverContact'
                    };
                    markInvalid(fieldMap[field] || field);
                }
                showError('fleetVehicleError', (result && result.error) || 'Could not save the vehicle.');
            }).catch(function (error) {
                console.error(error);
                showError('fleetVehicleError', 'Could not reach the server. Please try again.');
            }).finally(function () {
                if (submit) {
                    submit.disabled = false;
                    submit.textContent = submit.dataset.originalText || 'Save vehicle';
                }
            });
        }

        /* ---------------- Add / edit driver ---------------- */

        function openFleetDriverModal(driverId) {
            readFleetData();
            clearInvalid();
            clearError('fleetDriverError');

            var driver = byId(state.drivers, driverId);
            state.editingDriverId = driver ? Number(driver.id) : 0;

            var title = document.getElementById('fleetDriverDialogTitle');
            var submit = document.getElementById('fleetDriverSubmit');
            if (title) title.textContent = driver ? 'Edit driver' : 'Add driver';
            if (submit) submit.textContent = driver ? 'Save changes' : 'Add driver';

            document.getElementById('driverFormName').value = driver ? driver.full_name : '';
            document.getElementById('driverFormLicense').value = driver ? driver.license_number : '';
            document.getElementById('driverFormContact').value = driver ? (driver.contact_number || '') : '';
            document.getElementById('driverFormStatus').value = driver ? driver.status : 'active';

            // Opened from the assign dialog: return there once the driver exists.
            var assignDialog = document.getElementById('fleetAssignDialog');
            state.pendingAssignVehicleId = assignDialog && assignDialog.open ? state.assignVehicleId : 0;

            openDialog('fleetDriverDialog');
        }

        function submitFleetDriver() {
            clearInvalid();
            clearError('fleetDriverError');

            var name = document.getElementById('driverFormName').value.trim();
            var license = document.getElementById('driverFormLicense').value.trim();
            var contact = document.getElementById('driverFormContact').value.trim();
            var status = document.getElementById('driverFormStatus').value;

            if (!name) {
                markInvalid('driverFormName');
                showError('fleetDriverError', 'Driver name is required.');
                return;
            }
            if (!license) {
                markInvalid('driverFormLicense');
                showError('fleetDriverError', 'Driver license number is required.');
                return;
            }

            var submit = document.getElementById('fleetDriverSubmit');
            if (submit) {
                submit.disabled = true;
                submit.dataset.originalText = submit.textContent;
                submit.textContent = 'Saving...';
            }

            apiPost({
                action: 'save_driver',
                id: state.editingDriverId || 0,
                full_name: name,
                license_number: license,
                contact_number: contact,
                status: status
            }).then(function (result) {
                if (result && result.success) {
                    closeDialog('fleetDriverDialog');
                    var returnToAssign = state.pendingAssignVehicleId;
                    state.pendingAssignVehicleId = 0;
                    if (returnToAssign) {
                        // Reload so the roster at the bottom includes the new driver,
                        // then reopen the assignment step with them preselected.
                        try {
                            sessionStorage.setItem('fleetPendingAssign', JSON.stringify({
                                vehicleId: returnToAssign,
                                driverId: result.id
                            }));
                        } catch (error) { /* storage can be blocked */ }
                    }
                    refreshFleet();
                    return;
                }
                var field = result && result.field;
                if (field === 'license_number') field = 'driverFormLicense';
                if (field === 'full_name') field = 'driverFormName';
                if (field === 'contact_number') field = 'driverFormContact';
                if (field) markInvalid(field);
                showError('fleetDriverError', (result && result.error) || 'Could not save the driver.');
            }).catch(function (error) {
                console.error(error);
                showError('fleetDriverError', 'Could not reach the server. Please try again.');
            }).finally(function () {
                if (submit) {
                    submit.disabled = false;
                    submit.textContent = submit.dataset.originalText || 'Save driver';
                }
            });
        }

        /* ---------------- Assign / change driver ---------------- */

        function openFleetAssignModal(vehicleId, preselectDriverId) {
            readFleetData();
            clearError('fleetAssignError');
            clearInvalid();

            var vehicle = byId(state.vehicles, vehicleId);
            if (!vehicle) return;

            state.assignVehicleId = Number(vehicle.id);

            var summary = document.getElementById('fleetAssignSummary');
            if (summary) {
                summary.innerHTML = '';
                summary.appendChild(document.createTextNode('Vehicle: '));
                var strong = document.createElement('strong');
                strong.textContent = vehicle.plate_number;
                summary.appendChild(strong);
                if (vehicle.model || vehicle.vehicle_name) {
                    summary.appendChild(document.createTextNode(' — ' + (vehicle.model || vehicle.vehicle_name)));
                }
            }

            var select = document.getElementById('fleetAssignSelect');
            select.innerHTML = '';
            var none = document.createElement('option');
            none.value = '0';
            none.textContent = '— Unassigned —';
            select.appendChild(none);

            var selectedId = preselectDriverId ? Number(preselectDriverId) : Number(vehicle.driver_id || 0);
            var seen = {};

            state.drivers.forEach(function (driver) {
                if (driver.status !== 'active' && Number(driver.id) !== selectedId) return;
                var option = document.createElement('option');
                option.value = String(driver.id);
                option.textContent = driverOptionLabel(driver);
                if (Number(driver.id) === selectedId) option.selected = true;
                select.appendChild(option);
                seen[driver.id] = true;
            });

            if (selectedId && !seen[selectedId]) {
                var current = byId(state.drivers, selectedId);
                if (current) {
                    var option = document.createElement('option');
                    option.value = String(current.id);
                    option.textContent = driverOptionLabel(current);
                    option.selected = true;
                    select.appendChild(option);
                }
            }

            var hint = document.getElementById('fleetAssignHint');
            if (hint) {
                hint.textContent = state.drivers.length
                    ? 'Selecting a driver who is on another vehicle moves them to this one.'
                    : 'No drivers yet. Use "New driver" to add one.';
            }

            openDialog('fleetAssignDialog');
        }

        function submitFleetAssign() {
            clearError('fleetAssignError');
            var select = document.getElementById('fleetAssignSelect');
            var driverId = select ? select.value : '0';
            var submit = document.getElementById('fleetAssignSubmit');

            if (submit) {
                submit.disabled = true;
                submit.dataset.originalText = submit.textContent;
                submit.textContent = 'Saving...';
            }

            apiPost({
                action: 'assign_driver',
                vehicle_id: state.assignVehicleId,
                driver_id: driverId
            }).then(function (result) {
                if (result && result.success) {
                    closeDialog('fleetAssignDialog');
                    refreshFleet();
                    return;
                }
                showError('fleetAssignError', (result && result.error) || 'Could not assign the driver.');
            }).catch(function (error) {
                console.error(error);
                showError('fleetAssignError', 'Could not reach the server. Please try again.');
            }).finally(function () {
                if (submit) {
                    submit.disabled = false;
                    submit.textContent = submit.dataset.originalText || 'Save assignment';
                }
            });
        }

        /* ---------------- Vehicle actions ---------------- */

        function setFleetMaintenance(vehicleId, plate) {
            window.showAppConfirm(
                'Move ' + plate + ' to maintenance? It will be hidden from Faculty vehicle requests until you set it back to available.',
                'Set vehicle to maintenance?',
                function () {
                    apiPost({ action: 'update_vehicle_status', vehicle_id: vehicleId, status: 'maintenance' })
                        .then(function (result) {
                            if (result && result.success) {
                                refreshFleet();
                            } else {
                                window.showAppAlert((result && result.error) || 'Could not move the vehicle to maintenance.', 'Unable to update', null, { type: 'error' });
                            }
                        })
                        .catch(function () {
                            window.showAppAlert('Could not reach the server. Please try again.', 'Something went wrong', null, { type: 'error' });
                        });
                },
                null,
                { type: 'warning', okText: 'Yes, set to maintenance', cancelText: 'Cancel' }
            );
        }

        function restoreFleetVehicle(vehicleId, plate) {
            window.showAppConfirm(
                'Set ' + plate + ' back to available? Faculty will be able to request it again.',
                'Set vehicle available?',
                function () {
                    apiPost({ action: 'update_vehicle_status', vehicle_id: vehicleId, status: 'available' })
                        .then(function (result) {
                            if (result && result.success) {
                                refreshFleet();
                            } else {
                                window.showAppAlert((result && result.error) || 'Could not update the vehicle.', 'Unable to update', null, { type: 'error' });
                            }
                        })
                        .catch(function () {
                            window.showAppAlert('Could not reach the server. Please try again.', 'Something went wrong', null, { type: 'error' });
                        });
                },
                null,
                { type: 'confirm', okText: 'Yes, set available', cancelText: 'Cancel' }
            );
        }

        function removeFleetVehicle(vehicleId, plate) {
            window.showAppConfirm(
                'Remove ' + plate + ' from the fleet? This cannot be undone.',
                'Remove vehicle?',
                function () {
                    apiPost({ action: 'delete_vehicle_from_fleet', vehicle_id: vehicleId })
                        .then(function (result) {
                            if (result && result.success) {
                                refreshFleet();
                            } else {
                                window.showAppAlert((result && result.error) || 'Could not remove the vehicle.', 'Unable to remove', null, { type: 'error' });
                            }
                        })
                        .catch(function () {
                            window.showAppAlert('Could not reach the server. Please try again.', 'Something went wrong', null, { type: 'error' });
                        });
                },
                null,
                { type: 'danger', okText: 'Remove vehicle', cancelText: 'Cancel' }
            );
        }

        /* ---------------- Driver actions ---------------- */

        function removeFleetDriver(driverId, name) {
            window.showAppConfirm(
                'Delete driver ' + name + '? Any vehicle assigned to them will be left unassigned.',
                'Delete driver?',
                function () {
                    apiPost({ action: 'delete_driver', driver_id: driverId })
                        .then(function (result) {
                            if (result && result.success) {
                                refreshFleet();
                            } else {
                                window.showAppAlert((result && result.error) || 'Could not delete the driver.', 'Unable to delete driver', null, { type: 'error' });
                            }
                        })
                        .catch(function () {
                            window.showAppAlert('Could not reach the server. Please try again.', 'Something went wrong', null, { type: 'error' });
                        });
                },
                null,
                { type: 'danger', okText: 'Delete driver', cancelText: 'Cancel' }
            );
        }

        /* ---------------- Search + status filter ---------------- */

        function applyFleetFilters() {
            var search = document.getElementById('fleetSearch');
            var status = document.getElementById('fleetStatusFilter');
            var term = search ? search.value.trim().toLowerCase() : '';
            var statusValue = status ? status.value : 'all';

            var cards = document.querySelectorAll('#vehicleGrid .fleet-card');
            var visible = 0;

            for (var i = 0; i < cards.length; i++) {
                var card = cards[i];
                var matchesStatus = statusValue === 'all' || card.dataset.status === statusValue;
                var matchesTerm = !term || (card.dataset.search || '').indexOf(term) !== -1;
                var show = matchesStatus && matchesTerm;
                card.hidden = !show;
                if (show) visible++;
            }

            var hasCards = cards.length > 0;
            var empty = document.getElementById('fleetEmpty');
            var grid = document.getElementById('vehicleGrid');
            if (grid) grid.hidden = !hasCards || visible === 0;
            if (empty) {
                empty.hidden = !hasCards || visible > 0;
                if (hasCards) {
                    var heading = empty.querySelector('h3');
                    var copy = empty.querySelector('p');
                    if (heading) heading.textContent = 'No vehicles match your filters';
                    if (copy) copy.textContent = 'Try a different search term or status.';
                }
            }

            var count = document.getElementById('fleetResultCount');
            if (count) {
                var label = statusValue === 'all' ? 'vehicle' : statusValue + ' vehicle';
                count.textContent = visible + ' ' + label + (visible === 1 ? '' : 's') + ' shown';
            }

            var clear = document.getElementById('fleetClearFilters');
            if (clear) clear.hidden = !term && statusValue === 'all';

            try {
                sessionStorage.setItem(FILTER_KEY, JSON.stringify({ term: term, status: statusValue }));
            } catch (error) { /* storage can be blocked */ }
        }

        function resetFleetFilters() {
            var search = document.getElementById('fleetSearch');
            var status = document.getElementById('fleetStatusFilter');
            if (search) search.value = '';
            if (status) status.value = 'all';
            applyFleetFilters();
        }

        function restoreFleetFilters() {
            var saved = null;
            try {
                saved = JSON.parse(sessionStorage.getItem(FILTER_KEY) || 'null');
            } catch (error) { /* ignore */ }
            if (!saved) return;
            var search = document.getElementById('fleetSearch');
            var status = document.getElementById('fleetStatusFilter');
            if (search && saved.term) search.value = saved.term;
            if (status && saved.status) status.value = saved.status;
        }

        function refreshFleet() {
            if (typeof window.loadPage === 'function') {
                window.loadPage('pages/vehicle_fleet.php');
            } else {
                window.location.reload();
            }
        }

        /* ---------------- Init ---------------- */

        readFleetData();

        var search = document.getElementById('fleetSearch');
        if (search) search.addEventListener('input', applyFleetFilters);
        var status = document.getElementById('fleetStatusFilter');
        if (status) status.addEventListener('change', applyFleetFilters);

        restoreFleetFilters();
        applyFleetFilters();

        var dialogs = ['fleetVehicleDialog', 'fleetDriverDialog', 'fleetAssignDialog'];
        dialogs.forEach(function (id) {
            var dialog = document.getElementById(id);
            if (!dialog) return;
            dialog.addEventListener('click', function (event) {
                if (event.target === dialog) closeDialog(id);
            });
        });

        var pendingAssign = null;
        try {
            pendingAssign = JSON.parse(sessionStorage.getItem('fleetPendingAssign') || 'null');
            if (pendingAssign) sessionStorage.removeItem('fleetPendingAssign');
        } catch (error) { /* storage can be blocked */ }
        if (pendingAssign && pendingAssign.vehicleId) {
            openFleetAssignModal(pendingAssign.vehicleId, pendingAssign.driverId);
        }

        var page = document.querySelector('.fleet-page');
        if (page && page.dataset.openAdd === '1') {
            openFleetVehicleModal();
        }

        // Expose the handlers used by inline markup.
        window.openFleetVehicleModal = openFleetVehicleModal;
        window.openFleetDriverModal = openFleetDriverModal;
        window.openFleetAssignModal = openFleetAssignModal;
        window.closeFleetDialog = closeDialog;
        window.syncFleetDriverMode = syncFleetDriverMode;
        window.submitFleetVehicle = submitFleetVehicle;
        window.submitFleetDriver = submitFleetDriver;
        window.submitFleetAssign = submitFleetAssign;
        window.setFleetMaintenance = setFleetMaintenance;
        window.restoreFleetVehicle = restoreFleetVehicle;
        window.removeFleetVehicle = removeFleetVehicle;
        window.removeFleetDriver = removeFleetDriver;
        window.applyFleetFilters = applyFleetFilters;
        window.resetFleetFilters = resetFleetFilters;
    })();
    </script>
<?php include __DIR__ . '/../includes/custom_alert_modal.php'; ?>
</body>
</html>
