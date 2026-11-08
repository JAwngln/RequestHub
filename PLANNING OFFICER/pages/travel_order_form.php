<?php
session_start();
if (!isset($_SESSION['email']) || trim($_SESSION['role']) !== 'planning_officer') {
    header("Location: ../../auth/login.php");
    exit();
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/Prototype/connect.php';
require_once __DIR__ . '/../includes/fleet_schema.php';

// The fleet schema include guarantees the drivers table and vehicle link exist
// even on a fresh install opened straight from a request.
ensureFleetSchema($conn);

// Planning Officer dispatch roster. Only available vehicles and active drivers
// are offered for a new trip. Each vehicle also carries its Vehicle Fleet driver
// assignment, so choosing a vehicle can select the same driver the fleet page has
// linked to it. The script below re-adds a stored plate or driver name that is
// missing from these lists so an already-approved trip can still be reprinted
// after a vehicle goes to maintenance or a driver is deactivated.
$vehicleOptions = [];
$vehicleResult = $conn->query("SELECT vf.id, vf.plate_number, vf.vehicle_type, vf.capacity,
                                      vf.model, vf.status, vf.driver_id,
                                      d.full_name AS driver_name
                               FROM vehicle_fleet vf
                               LEFT JOIN drivers d
                                      ON d.id = vf.driver_id
                                     AND d.status = 'active'
                               WHERE vf.status = 'available'
                               ORDER BY vf.plate_number");
if ($vehicleResult) {
    $vehicleOptions = $vehicleResult->fetch_all(MYSQLI_ASSOC);
}

$driverOptions = [];
$driverResult = $conn->query("SELECT id, full_name
                              FROM drivers
                              WHERE status = 'active'
                              ORDER BY full_name");
if ($driverResult) {
    $driverOptions = $driverResult->fetch_all(MYSQLI_ASSOC);
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CvSU Travel Request - A4</title>
    <link href="/Prototype/assets/fonts/material-icons.css" rel="stylesheet">
    <style>
        /* General styling for screen and print */
        body {
            font-family: "Arial", sans-serif;
            font-size: 11px;
            color: #000;
            margin: 0;
            padding: 0;
            background-color: #f0f0f0;
        }

        /* The A4 Sheet container */
        .a4-page {
            width: 210mm;
            min-height: 297mm;
            padding: 15mm;
            margin: 10mm auto;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            box-sizing: border-box;
            position: relative;
        }

        @media print {
            body { background: none; }
            .a4-page {
                margin: 0;
                box-shadow: none;
                width: 100%;
            }
            @page {
                size: A4;
                margin: 0;
            }
        }

        /* --- UPDATED HEADER STYLES TO MOVE LOGOS CLOSER & MAKE THEM BIGGER --- */
        .header {
            display: flex;
            justify-content: center; 
            align-items: center;
            text-align: center;
            margin-bottom: 25px;
            
            /* Add a defined gap to pull logos close to the text */
            gap: 5px; 
            
            /* Center the entire header block on the page */
            width: 650px; 
            margin-left: auto;
            margin-right: auto;
        }

        /* Set a flexible width for the text block so it doesn't move */
        .header-center {
            flex-grow: 0;
            width: 380px; 
        }
        .header-center h1 { font-size: 16px; margin: 0; }
        .header-center p { margin: 2px 0; font-size: 10px; }

        .logo-container {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-grow: 0;
        }

        #cvsu-logo {
            /* RESIZED LARGER (from 80px) */
            width: 90px; 
            height: auto;
            
        }
        
        #socotec-logo {
            /* RESIZED LARGER (from 120px) */
            width: 110px; 
            height: auto;
        }
        /* --- END UPDATED HEADER STYLES --- */

        .section-title { font-weight: bold; margin-top: 15px; display: block; }
        .sub-section { margin-left: 15px; }

        .flex-row { display: flex; gap: 10px; align-items: flex-end; margin: 5px 0; }
        .underline { border-bottom: 1px solid black; flex-grow: 1; min-height: 1.2em; padding-left: 5px; }

        /* Plain-text values shown on the printed Travel Order. The selects are
           only used for editing on screen, so the printed document always shows
           the dispatched vehicle and driver as clean, unboxed text. */
        .dispatch-print-value {
            display: none;
            flex: 1;
            min-height: 1.2em;
            padding-left: 5px;
            border-bottom: 1px solid black;
            font-weight: 600;
        }

        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        th, td {
            border: 1px solid black;
            padding: 4px;
            font-size: 10px;
            text-align: center;
        }

        /* Signature area */
        .sig-block {
            text-align: center;
            width: 200px;
            margin-top: 20px;
        }

        .footer-version {
            position: absolute;
            bottom: 15mm;
            right: 15mm;
            font-size: 9px;
        }
        
        /* Print styles to fit on one A4 page */
        @media print {
            body { background: none; }
            .a4-page {
                margin: 0;
                box-shadow: none;
                width: 100%;
                height: 265mm; /* Reduced height to create more blank space at bottom */
                overflow: hidden;
                transform: scale(0.98);
                transform-origin: top left;
            }
            @page {
                size: A4;
                margin: 0;
            }
            
            /* Ensure footer version stays visible when printing */
            .footer-version {
                position: absolute;
                bottom: 8mm; /* Positioned closer to bottom within the reduced height */
                right: 15mm;
                font-size: 9px;
                color: black !important;
            }
            
            /* Swap the dispatch dropdowns for plain printed values */
            .dispatch-select {
                display: none !important;
            }

            .dispatch-print-value {
                display: inline-block !important;
                /* Match the on-screen select width so the printed Vehicle/Driver
                   line stays the same length instead of stretching across the row. */
                flex: 0 0 150px;
                width: 150px;
                box-sizing: border-box;
            }

            /* Hide dropdown arrows when printing */
            select {
                -webkit-appearance: none;
                -moz-appearance: none;
                appearance: none;
                border-bottom: 1px solid black !important;
                border-top: none !important;
                border-left: none !important;
                border-right: none !important;
                background: transparent !important;
                color: black !important;
                pointer-events: none;
            }
            
            /* Hide button section when printing */
            .button-section {
                display: none !important;
            }
            
            /* Hide action buttons when printing */
            .action-buttons {
                display: none !important;
            }
        }
        
        /* Action Buttons Styles */
        .action-buttons {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
            display: flex;
            gap: 10px;
            background: white;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .btn-back, .btn-print {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 10px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
        }
        
        .btn-back:hover {
            background: #5a6268;
            transform: translateY(-1px);
        }
        
        .btn-print {
            background: #007bff;
            color: white;
        }
        
        .btn-print:hover {
            background: #0056b3;
            transform: translateY(-1px);
        }
        
        .material-icons {
            font-size: 18px;
        }
    </style>
</head>
<body>

<!-- Action Buttons - Outside Paper Area -->
<div class="action-buttons">
    <button class="btn-back" onclick="goBack()">
        <span class="material-icons">arrow_back</span>
        Back
    </button>
    <button class="btn-print" onclick="printWithConfirmation()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink: 0;"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
        Print
    </button>
</div>

<div class="a4-page">
   <div class="header">
        <div class="logo-container">
            <img src="cvsu-logo2.png" alt="CvSU Logo" id="cvsu-logo">
        </div>

        <div class="header-center">
            <p>Republic of the Philippines</p>
            <h1>CAVITE STATE UNIVERSITY NAIC</h1>
            <p>(Formerly CAVITE COLLEGE OF FISHERIES)</p>
            <p>Bucana Malaki, Naic, Cavite</p>
            <p>www.cvsu-naic.edu.ph</p>
        </div>

        <div class="logo-container">
            <img src="socotec-logo.png" alt="SOCOTEC Logo" id="socotec-logo">
        </div>
    </div>
    
    <div class="section-title">I. PRE-TRAVEL REQUEST</div>
    <div class="sub-section">
        <p>A. TRAVEL ORDER</p>
        <p style="text-indent: 50px; font-size: 13px;">Official travel is hereby granted to the following personnel:</p>
        
        <div style="text-align:center; margin: 10px auto; width: 300px;">
            <div class="name-line" ><U><b>QUENNIE M. BILUGAN</b></U></div>
            <div>Head of Party</div>
        </div>

        <div class="flex-row">Other passenger(s): <div class="underline" id="passengers"></div></div>
        <div class="flex-row">Date of travel: <div class="underline" id="travelDate" style="flex-grow:0; width:150px;"></div> Destination(s): <div class="underline" id="destination"></div></div>
        <div class="flex-row">Purpose of Travel: <div class="underline" id="purpose"></div></div>

        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-top: 5px;">
            <div style="margin-left: 300px; display: flex; justify-content: center;">
                <p>Approved:</p>
            </div>
            <div class="sig-block" style="margin-left: 50px">
                <div class="name-line"><STRONG>ARMINA B. PUGAY</STRONG></d  iv>
                <div>Campus Administrator</div>
            </div>
        </div>
    </div>

    <div class="sub-section">
        <p><strong>B. VEHICLE DISPATCH:</strong></p>
        <div style="display:flex; justify-content: space-between;">
            <div style="width: 45%;">
                <div class="flex-row">Trip Ticket: <div class="underline" id="tripTicket"></div></div>
                <div class="flex-row">Vehicle: 
                    <select id="vehicleSelect" class="dispatch-select" style="border: none; border-bottom: 1px solid black; background: transparent; width: 150px;">
                        <option value="">Select Vehicle</option>
                        <?php foreach ($vehicleOptions as $opt): ?>
                            <?php
                                $vehicleType = trim((string)($opt['vehicle_type'] ?? ''));
                                $vehicleLabel = (string)$opt['plate_number'];
                                if ($vehicleType !== '') {
                                    $vehicleLabel .= ' — ' . ucfirst($vehicleType);
                                }
                            ?>
                            <option value="<?php echo htmlspecialchars($opt['plate_number'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-driver-name="<?php echo htmlspecialchars((string)($opt['driver_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($vehicleLabel); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="dispatch-print-value" id="vehiclePrintValue"></span>
                </div>
                <div class="flex-row">Driver: 
                    <select id="driverSelect" class="dispatch-select" style="border: none; border-bottom: 1px solid black; background: transparent; width: 150px;">
                        <option value="">Select Driver</option>
                        <?php foreach ($driverOptions as $driver): ?>
                            <option value="<?php echo htmlspecialchars($driver['full_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($driver['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="dispatch-print-value" id="driverPrintValue"></span>
                </div>
            </div>
            <div style="width: 30%; margin-right: 100px;">
                <div class="flex-row">Report: <div class="underline"></div></div>
                <div class="flex-row">Place: <div class="underline"></div></div>
                <div class="flex-row">Time: <div class="underline"></div></div>
                <div class="sig-block" style="width:100%; margin-top:10px;">
                    <div class="name-line"><strong>MR. BUGGIE JOHN B. CATURAY</strong></div>
                    <div>Head, PPSS</div>
                </div>
            </div>
        </div>
    </div>

    <div class="section-title">II. TRAVEL REPORT</div>
    <div class="sub-section">
        <p>A. ITINERARY AND RECORD OF TRAVEL</p>
        <table>
            <tr>
                <th rowspan="2">Date</th>
                <th colspan="4">Departure/Origin</th>
                <th rowspan="2">Date</th>
                <th colspan="4">Arrival/Destination</th>
            </tr>
            <tr>
                <th>Place</th><th>Odometer<br>reading</th><th>Time</th><th>Security<br>Guard on<br>duty</th>
                 <th>Place</th><th>Odometer<br>reading</th><th>Time</th><th>Security<br>Guard on<br>duty</th>
            </tr>
            <tr style="height:25px;">
                <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
            </tr>
            <tr style="height:25px;">
                <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
            </tr>
            <tr style="height:25px;">
                <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
            </tr>
        </table>

         <p>B. FUEL AND LUBRICANT REPORT</p>
        <div style="display: flex; justify-content: space-between;">
            <div style="width: 48%;">
                <ul id="side-a">
                    <li><p>Balance in tank:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;____________________L</p></li>
                    <li><p>Purchased during trip:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;____________________L</p></li>
                    <li><p>Fuel consumed during trip:____________________L</p></li>
                    <li><p>Balance in tank:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;____________________L</p></li>
                </ul>
            </div>
            <div style="width: 48%;">
                <ul id="side-b">
                    <li><p>Engine oil:____________________L</p></li>
                    <li><p>Gear oil:&nbsp;&nbsp;&nbsp;&nbsp;____________________L</p></li>
                    <li><p>Brake fluid:____________________L</p></li>
                    <li><p>ATF:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;____________________L</p></li>
                </ul>
            </div>
        </div>

          <p>C. INCIDENT AND OBSERVATION REPORT</p>
        <table>
            <tr>
                <th rowspan="1">Breakdown/Incident During Trip</th>
                <th colspan="1">Vehicle Performance Observation</th>
            </tr>
            <tr>
                <td><br><br></td>
                <td></td>
            </tr>
        </table>
        
          




    </div>



    <div class="section-title">III. POST TRAVEL REPORT</div>
    <div class="sub-section">
        <div style="display:flex; justify-content: space-between;">
            <div style="width: 45%;">
                <div class="flex-row">Odometer: Final Reading: <div class="underline"></div></div>
                <div class="flex-row" style="margin-left: 55px;">Initial Reading: <div class="underline"></div></div>
                <div class="flex-row" style="margin-left: 55px;">Dist. Reading: <div class="underline"></div> km</div>
                <div class="flex-row" style="margin-left: 55px;">Noted by:  <div class="underline"></div> </div>

                <div class="sig-block" style="margin-top:20px; margin-left: 100px;">
    
                    <div class="name-line">(Security Guard on Duty)</div>
                </div>
            </div>
            <div style="width: 51%; text-align: center;">
                <div class="flex-row">Driver: <div class="underline"></div> Date: <div class="underline"></div></div>
                <div class="flex-row">Passenger: <div class="underline"></div> Date: <div class="underline"></div></div>
                <div class="flex-row">Verified by: <div class="underline"></div> Date: <div class="underline"></div></div>
            </div>
        </div>
    </div>

    <div class="footer-version">V01-2018-06-05</div>
</div>

<!-- Placeholder Section - Outside A4 Paper to maintain layout -->
<div class="button-section" style="text-align: center; margin: 20px 0; padding: 20px;">
    <!-- Empty placeholder to maintain A4 formatting -->
</div>

<script>
        // Function to collect form data and send to final travel order
        function submitToFinal() {
            // Collect form data
            const formData = {
                passengers: document.getElementById('passengers').textContent || '',
                travelDate: document.getElementById('travelDate').textContent || '',
                destination: document.getElementById('destination').textContent || '',
                purpose: document.getElementById('purpose').textContent || '',
                tripTicket: document.getElementById('tripTicket').textContent || '',
                vehicle: document.getElementById('vehicleSelect')?.value || '',
                driver: document.getElementById('driverSelect')?.value || ''
            };
            
            // Create URL with form data
            const params = new URLSearchParams({
                request: JSON.stringify(formData)
            });
            
            // Open travel order final with form data
            window.open('travel_order_final.php?' + params.toString(), '_blank');
        }

        // A vehicle's driver assignment on the Vehicle Fleet page is the default
        // driver for that dispatch. Manual driver choices remain allowed.
        function syncDriverFromVehicle() {
            const vehicleSelect = document.getElementById('vehicleSelect');
            const driverSelect = document.getElementById('driverSelect');
            if (!vehicleSelect || !driverSelect) return;

            const selected = vehicleSelect.options[vehicleSelect.selectedIndex];
            const driverName = selected ? (selected.dataset.driverName || '') : '';
            if (!driverName) return;

            const hasMatch = Array.from(driverSelect.options).some(o => o.value === driverName);
            if (!hasMatch) {
                const opt = document.createElement('option');
                opt.value = driverName;
                opt.textContent = driverName;
                driverSelect.appendChild(opt);
            }
            driverSelect.value = driverName;
        }

        // Mirror each dispatch selection into the plain-text line that is shown
        // when the Travel Order is printed.
        function syncDispatchPrintValues() {
            const pairs = [
                ['vehicleSelect', 'vehiclePrintValue'],
                ['driverSelect', 'driverPrintValue']
            ];
            pairs.forEach(function(pair) {
                const select = document.getElementById(pair[0]);
                const output = document.getElementById(pair[1]);
                if (!select || !output) return;
                const option = select.options[select.selectedIndex];
                output.textContent = option && option.value ? option.textContent : '';
            });
        }
        window.syncDispatchPrintValues = syncDispatchPrintValues;

        document.addEventListener('DOMContentLoaded', function() {
            const vehicleSelect = document.getElementById('vehicleSelect');
            const driverSelect = document.getElementById('driverSelect');
            if (vehicleSelect) {
                vehicleSelect.addEventListener('change', function() {
                    syncDriverFromVehicle();
                    syncDispatchPrintValues();
                });
            }
            if (driverSelect) driverSelect.addEventListener('change', syncDispatchPrintValues);
        });

        // Auto-populate fields with request data from URL parameters
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const requestData = urlParams.get('request');
            
            if (requestData) {
                try {
                    const request = JSON.parse(decodeURIComponent(requestData));
                    
                    // Populate the fields with request data
                    document.getElementById('passengers').textContent = request.passengers || request.names || '';
                    document.getElementById('travelDate').textContent = request.date || request.travel_date || '';
                    document.getElementById('destination').textContent = request.destination || '';
                    document.getElementById('purpose').textContent = request.purpose || request.reason || '';
                    document.getElementById('tripTicket').textContent =
        (request.ticket_no === null || request.ticket_no === undefined)
            ? '---'
            : String(request.ticket_no).padStart(3, '0');
                    
                    // Set driver dropdown if available. request.driver is the
                    // officer's saved choice from a previous print, so reopening
                    // the form keeps the same driver. An inactive/removed driver
                    // is re-added so old travel orders still reprint correctly.
                    if (request.driver) {
                        const driverSelect = document.getElementById('driverSelect');
                        if (driverSelect) {
                            const hasMatch = Array.from(driverSelect.options).some(o => o.value === request.driver);
                            if (!hasMatch) {
                                const opt = document.createElement('option');
                                opt.value = request.driver;
                                opt.textContent = request.driver;
                                driverSelect.appendChild(opt);
                            }
                            driverSelect.value = request.driver;
                        }
                    }
                    
                    // Set vehicle dropdown if available. Prefer the officer-saved
                    // plate, then fall back to older request payload shapes.
                    const savedVehicle = request.vehicle_plate || request.vehicle || request.plate_number || '';
                    if (savedVehicle) {
                        const vehicleSelect = document.getElementById('vehicleSelect');
                        if (vehicleSelect) {
                            const hasMatch = Array.from(vehicleSelect.options).some(o => o.value === savedVehicle);
                            if (hasMatch) {
                                vehicleSelect.value = savedVehicle;
                            } else {
                                // Vehicle not in the current available list - add it so the data is preserved
                                const opt = document.createElement('option');
                                opt.value = savedVehicle;
                                opt.textContent = savedVehicle;
                                vehicleSelect.appendChild(opt);
                                vehicleSelect.value = savedVehicle;
                            }
                        }
                    }

                    // No saved driver yet: use the fleet's assigned driver for the
                    // restored vehicle, if it has one.
                    if (!request.driver) {
                        syncDriverFromVehicle();
                    }
                    
                    // Auto-populate date fields with current date if not set
                    const now = new Date();
                    const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
                    const dateInputs = document.querySelectorAll('input[type="date"]');
                    dateInputs.forEach(input => {
                        if (!input.value) {
                            input.value = today;
                        }
                    });
                } catch (error) {
                    console.error('Error parsing request data:', error);
                }
            } else {
                // Auto-populate date fields with current date
                const now = new Date();
                const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
                const dateInputs = document.querySelectorAll('input[type="date"]');
                dateInputs.forEach(input => {
                    if (!input.value) {
                        input.value = today;
                    }
                });
            }

            syncDispatchPrintValues();
        });
        
        // Function to go back to previous page
        function goBack() {
            window.close();
            // If window cannot be closed (opened by user), go back in history
            setTimeout(() => {
                window.history.back();
            }, 100);
        }
        
        // Function to print with confirmation and approval
        function printWithConfirmation() {
            // Get request ID from URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const requestData = urlParams.get('request');
            
            if (requestData) {
                try {
                    const request = JSON.parse(decodeURIComponent(requestData));
                    approveAndPrint(request.id);
                } catch (error) {
                    console.error('Error parsing request data:', error);
                    showAppConfirm('Are you sure you want to print this travel order?', 'Print Travel Order?', function () {
                        window.print();
                    }, null, { type: 'confirm', okText: 'Print', cancelText: 'Cancel' });
                }
            } else {
                // No request ID found, just print
                showAppConfirm('Are you sure you want to print this travel order?', 'Print Travel Order?', function () {
                    window.print();
                }, null, { type: 'confirm', okText: 'Print', cancelText: 'Cancel' });
            }
        }

        function approveAndPrint(requestId) {
            showAppConfirm('Are you sure you want to approve this request and print this travel order?', 'Approve & Print?', function () {
                // Persist the dispatch decision made in Section B. The approved
                // request summary reports the DRIVER column and reprints restore
                // the vehicle, so both must be saved before the trip is approved.
                const driver = document.getElementById('driverSelect')?.value || '';
                const vehicle = document.getElementById('vehicleSelect')?.value || '';

                function saveDispatch(action, field, value) {
                    const body = new URLSearchParams({ action: action, id: String(requestId) });
                    body.set(field, value);
                    return fetch('../../database_requests.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: body.toString()
                    })
                    .then(response => response.json())
                    .catch(error => {
                        // A failed dispatch save must not block approval of the
                        // trip itself - the request is still valid.
                        console.error('Failed to save ' + field + ':', error);
                        return { success: true };
                    });
                }

                saveDispatch('set_vehicle', 'vehicle', vehicle)
                .then(() => saveDispatch('set_driver', 'driver', driver))
                .then(() => {
                    // Approve the request
                    return fetch('../../database_requests.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=update_status&id=${requestId}&status=approved&notes=Approved and printed`
                    });
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAppAlert('Request approved successfully!', 'Request Approved', function () {
                            window.print();
                            setTimeout(() => {
                                window.close();
                            }, 1000);
                        }, { type: 'success' });
                    } else {
                        showAppAlert('Error: ' + (data.error || 'Failed to approve request'), 'Unable to Approve Request', null, { type: 'error' });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    console.error('Error details:', error.message);
                    showAppAlert('An error occurred while approving the request: ' + error.message, 'Something Went Wrong', null, { type: 'error' });
                });
            }, null, { type: 'confirm', okText: 'Approve & Print', cancelText: 'Cancel' });
        }
    </script>

<?php include __DIR__ . '/../includes/custom_alert_modal.php'; ?>

</body>
</html>