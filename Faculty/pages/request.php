<?php
session_start();
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ../../auth/login.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_request') {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/Prototype/connect.php';

    header('Content-Type: application/json');

    // Get faculty name from session
    $names = isset($_SESSION['firstName']) && isset($_SESSION['lastName']) 
        ? $_SESSION['firstName'] . ' ' . $_SESSION['lastName'] 
        : (isset($_SESSION['email']) ? $_SESSION['email'] : 'Faculty User');
    
    $items = trim((string)($_POST['items'] ?? ''));
    $quantity = (int)($_POST['quantity'] ?? 0);
    $reason = trim((string)($_POST['reason'] ?? ''));
    $lowStockThreshold = 5;

    // Accept both date-only (YYYY-MM-DD) and legacy datetime values, but always
    // store the calendar date so no time or timezone is attached to the request.
    $when_needed_raw = trim((string)($_POST['when_needed'] ?? ''));
    $when_needed = null;
    if ($when_needed_raw !== '') {
        $when_needed_date = preg_split('/[T ]/', $when_needed_raw)[0];
        $parsed_date = DateTimeImmutable::createFromFormat('!Y-m-d', $when_needed_date);
        if (!$parsed_date || $parsed_date->format('Y-m-d') !== $when_needed_date) {
            echo json_encode(['success' => false, 'message' => 'Please select a valid date for When Needed.']);
            exit;
        }
        $when_needed = $parsed_date->format('Y-m-d');
    }

    if ($names === '' || $items === '' || $quantity <= 0 || $reason === '') {
        echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
        exit;
    }

    // Reserve the requested stock in the same transaction as the request insert.
    // This keeps inventory and requests consistent, and prevents two faculty
    // members from both reserving the last units at the same time.
    try {
        $conn->begin_transaction();

        $stockStmt = $conn->prepare("SELECT id, quantity FROM inventory_management WHERE item_name = ? FOR UPDATE");
        if (!$stockStmt) {
            throw new RuntimeException('Unable to check inventory right now.');
        }
        $stockStmt->bind_param("s", $items);
        $stockStmt->execute();
        $stockRow = $stockStmt->get_result()->fetch_assoc();
        $stockStmt->close();

        if (!$stockRow) {
            throw new RuntimeException('The selected item is no longer available in inventory.');
        }

        $itemId = (int)$stockRow['id'];
        $availableQuantity = (int)$stockRow['quantity'];

        if ($availableQuantity <= 0) {
            throw new RuntimeException('The selected item is out of stock.');
        }
        if ($quantity > $availableQuantity) {
            throw new RuntimeException("Only {$availableQuantity} unit(s) of {$items} are available.");
        }

        $newQuantity = $availableQuantity - $quantity;

        $updateStockStmt = $conn->prepare("UPDATE inventory_management SET quantity = ? WHERE id = ?");
        if (!$updateStockStmt) {
            throw new RuntimeException('Unable to update inventory right now.');
        }
        $updateStockStmt->bind_param("ii", $newQuantity, $itemId);
        if (!$updateStockStmt->execute()) {
            throw new RuntimeException('Unable to update inventory: ' . $updateStockStmt->error);
        }
        $updateStockStmt->close();

        $userEmail = $_SESSION['email'] ?? '';
        $sql = "INSERT INTO requested_items (names, items, quantity, reason, when_needed, user_email, action) VALUES (?, ?, ?, ?, ?, ?, 'pending')";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Unable to submit the request right now.');
        }
        $stmt->bind_param("ssisss", $names, $items, $quantity, $reason, $when_needed, $userEmail);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Error submitting request: ' . $stmt->error);
        }
        $insertId = $conn->insert_id;
        $stmt->close();
        
        // Create notification for Planning Officers
        $notifSql = "INSERT INTO notifications (title, message, type, recipient_role, sender_name, related_id) VALUES (?, ?, ?, ?, ?, ?)";
        $notifStmt = $conn->prepare($notifSql);
        if ($notifStmt) {
            $title = "New Item Request";
            $message = "A new item request has been submitted by {$names} for {$items}.";
            $notifType = 'item_request';
            $notifRole = 'planning_officer';
            $notifStmt->bind_param("sssssi", $title, $message, $notifType, $notifRole, $names, $insertId);
            $notifStmt->execute();
            $notifStmt->close();
        }

        $conn->commit();

        $stockStatus = $newQuantity <= 0 ? 'out' : ($newQuantity <= $lowStockThreshold ? 'low' : 'in');
        echo json_encode([
            'success' => true,
            'message' => 'Request submitted successfully!',
            'new_quantity' => $newQuantity,
            'stock_status' => $stockStatus,
            'low_stock_threshold' => $lowStockThreshold
        ]);
    } catch (Throwable $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Request Item</title>
    <link href="/Prototype/assets/fonts/material-icons.css" rel="stylesheet">
    <link href="/Prototype/assets/fonts/poppins.css" rel="stylesheet">
    <link rel="stylesheet" href="pages/vehiclebtn.css?v=3">
    
    <style>
        /* Additional styles for request form specific elements */
        body {
            margin: 0;
            padding: 0;
            font-family: "Poppins", sans-serif;
            background: #f5f5f5;
        }
        
        .form-wrapper {
            flex: 0 0 55%;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: stretch;
            position: relative;
            height: 100%;
            overflow: visible;
            padding: 10px 30px 30px 30px;
            box-sizing: border-box;
            background: linear-gradient(135deg, #ffffff, #fafbfc);
            border-right: 1px solid #e9ecef;
            box-shadow: 2px 0 8px rgba(0, 0, 0, 0.04);
            min-width: 0;
        }
        
        .paper-form {
            margin-top: 0;
            padding-top: 0;
        }

        .request-card {
            width: 100%;
            max-width: 640px;
            margin: 0 auto;
            padding: 28px;
            box-sizing: border-box;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            box-shadow: 0 10px 28px rgba(17, 24, 39, 0.08);
        }

        .request-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 0;
        }

        .form-field--full {
            grid-column: 1 / -1;
        }

        .form-field label {
            color: #374151;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .form-field select,
        .form-field input,
        .form-field textarea {
            width: 100%;
            min-width: 0;
            padding: 12px 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #ffffff;
            color: #111827;
            font-family: "Poppins", sans-serif;
            font-size: 14px;
            line-height: 1.5;
            box-sizing: border-box;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .form-field select:focus,
        .form-field input:focus,
        .form-field textarea:focus {
            outline: none;
            border-color: #1c6b2f;
            box-shadow: 0 0 0 3px rgba(28, 107, 47, 0.12);
        }

        .form-field textarea {
            min-height: 110px;
            resize: vertical;
        }

        .form-field input[type="date"] {
            color-scheme: light;
        }

        .request-form-actions {
            margin-top: 24px;
        }

        .request-form-actions .submit-btn {
            width: 100%;
            margin-left: 0;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
            vertical-align: middle;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status-approved {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-declined {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .status-cancelled {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        /* Live stock indicator shown under the item picker before submitting */
        .faculty-stock-indicator {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 8px;
            min-height: 22px;
        }

        .faculty-stock-indicator:empty {
            display: none;
        }

        .faculty-stock-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 9px;
            border: 1px solid transparent;
            border-radius: 999px;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.05em;
            line-height: 1.5;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .faculty-stock-badge::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
            opacity: 0.85;
        }

        .faculty-stock-badge.in {
            background: #ecfdf3;
            border-color: #abefc6;
            color: #067647;
        }

        .faculty-stock-badge.low {
            background: #fffbeb;
            border-color: #fde68a;
            color: #92400e;
        }

        .faculty-stock-badge.out {
            background: #fef3f2;
            border-color: #fecdca;
            color: #b42318;
        }

        .faculty-stock-qty {
            color: #6b7280;
            font-size: 11.5px;
            font-weight: 500;
        }
        
        h1 {
            text-align: center;
            color: #1c6b2f;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 30px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .form-wrapper {
                padding: 10px 20px 20px 20px;
                box-sizing: border-box;
            }
        }
        
        @media (max-width: 992px) {
            .request-layout {
                flex-direction: column;
            }
            
            .form-wrapper {
                flex: 1;
                width: 100%;
                padding: 15px;
                border-right: none;
                border-bottom: 1px solid #e9ecef;
            }
            
            .request-list {
                flex: 1;
                width: 100%;
                padding: 15px;
            }
        }
        
        @media (max-width: 768px) {
            h1 {
                font-size: 24px;
                margin-bottom: 20px;
            }
            
            .form-wrapper {
                padding: 10px;
            }
            
            .request-card {
                padding: 20px;
            }

            .request-form-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
        }
        
        @media (max-width: 576px) {
            h1 {
                font-size: 20px;
                margin-bottom: 15px;
            }
            
            .form-wrapper {
                padding: 8px;
            }
            
            .request-card {
                padding: 16px;
            }

            .form-field label {
                font-size: 11px;
            }

            .form-field select,
            .form-field input,
            .form-field textarea {
                padding: 10px 12px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>

<h1>REQUEST ITEMS</h1>

<div class="request-layout">

    <div class="form-wrapper">
        <div class="request-card">
            <form class="paper-form" id="requestForm">
                <div class="request-form-grid">
                    <div class="form-field form-field--full">
                        <label for="itemSelect">ITEM</label>
                        <select id="itemSelect" name="items">
                            <option disabled selected>SELECT ITEM</option>
                        </select>
                        <div id="selectedItemStock" class="faculty-stock-indicator" aria-live="polite"></div>
                    </div>

                    <div class="form-field">
                        <label for="quantityInput">QUANTITY</label>
                        <input type="number" id="quantityInput" name="quantity" min="1" inputmode="numeric" placeholder="Enter quantity">
                    </div>

                    <div class="form-field">
                        <label for="whenNeededInput">DATE / WHEN NEEDED</label>
                        <input type="date" id="whenNeededInput" name="when_needed">
                    </div>

                    <div class="form-field form-field--full">
                        <label for="reasonInput">REASON</label>
                        <textarea id="reasonInput" name="reason" rows="3" placeholder="Enter reason"></textarea>
                    </div>
                </div>

                <div class="request-form-actions">
                    <button type="button" class="submit-btn" onclick="submitRequest()">SUBMIT</button>
                </div>
            </form>
        </div>
    </div>

    <!-- REQUESTED LIST -->
    <div class="request-list">
        <h2>Requested List</h2>
        <div id="requestListContainer">
            <p class="empty-message">No requested items yet</p>
        </div>
    </div>

</div>

<script>
// Global variables
window.itemRequests = [];
window.itemRequestIdCounter = 1;
window.isSubmitting = false;

// Show a styled modal when available, fall back to a native alert
function itemNotify(message, title, type) {
    if (typeof window.showAppAlert === 'function') {
        window.showAppAlert(message, title, null, { type: type || 'info' });
    } else {
        alert(message);
    }
}

// Normalize a date or datetime value to a calendar date (YYYY-MM-DD).
// Keeps the stored/submitted value free of time and timezone information.
function toDateOnly(value) {
    const raw = String(value || '').trim();
    const match = raw.match(/^(\d{4}-\d{2}-\d{2})/);
    return match ? match[1] : '';
}

// Live inventory snapshot for the item picker. It is refreshed after every
// submission and by the background poll, so stock badges stay current without
// a full page refresh.
window.facultyInventoryCache = window.facultyInventoryCache || {};
window.facultyLowStockThreshold = window.facultyLowStockThreshold || 5;

function getFacultyStockKey(quantity) {
    const qty = Number(quantity) || 0;
    const threshold = Number(window.facultyLowStockThreshold) || 5;
    if (qty <= 0) return 'out';
    if (qty <= threshold) return 'low';
    return 'in';
}

function getFacultyStockLabel(stockKey) {
    if (stockKey === 'out') return 'Out of Stock';
    if (stockKey === 'low') return 'Low Stock';
    return 'In Stock';
}

// Render the badge for whichever item is currently selected.
function updateSelectedItemStock() {
    const select = document.getElementById('itemSelect');
    const container = document.getElementById('selectedItemStock');
    if (!select || !container) return;

    const item = select.value ? window.facultyInventoryCache[select.value] : null;
    if (!item) {
        container.innerHTML = '';
        container.className = 'faculty-stock-indicator';
        const emptyQuantityInput = document.getElementById('quantityInput');
        if (emptyQuantityInput) {
            emptyQuantityInput.removeAttribute('max');
        }
        return;
    }

    const stockKey = getFacultyStockKey(item.quantity);
    const quantityLabel = Number(item.quantity) > 0
        ? `${Number(item.quantity)} available`
        : 'No stock available';
    container.className = 'faculty-stock-indicator ' + stockKey;
    container.innerHTML = `<span class="faculty-stock-badge ${stockKey}">${getFacultyStockLabel(stockKey)}</span><span class="faculty-stock-qty">${quantityLabel}</span>`;

    // Never let the quantity field exceed what is actually on hand.
    const quantityInput = document.getElementById('quantityInput');
    if (quantityInput) {
        if (Number(item.quantity) > 0) {
            quantityInput.max = String(Number(item.quantity));
        } else {
            quantityInput.removeAttribute('max');
        }
    }
}

function bindFacultyItemSelect() {
    const select = document.getElementById('itemSelect');
    if (!select || select.dataset.stockBound === '1') return;
    select.dataset.stockBound = '1';
    select.addEventListener('change', updateSelectedItemStock);
    updateSelectedItemStock();
}

// Submit request function
window.submitRequest = function() {
    if (window.isSubmitting) {
        itemNotify('Your request is already being submitted. Please wait a moment.', 'Please Wait', 'info');
        return;
    }
    
    window.isSubmitting = true;
    
    const submitBtn = document.querySelector('.submit-btn');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';
    }
    
    // Get form data
    const select = document.getElementById('itemSelect');
    const quantity = document.getElementById('quantityInput');
    const whenNeeded = document.getElementById('whenNeededInput');
    const reason = document.getElementById('reasonInput');
    const whenNeededDate = whenNeeded ? toDateOnly(whenNeeded.value) : '';
    
    // Validate form data
    if (!select || !quantity || !whenNeeded || !reason || !select.value || !quantity.value || Number(quantity.value) <= 0 || !whenNeededDate || !reason.value.trim()) {
        itemNotify('Please complete the item, quantity, when needed, and reason fields.', 'Missing Information', 'warning');
        window.isSubmitting = false;
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'SUBMIT';
        }
        return;
    }
    
    // Check the selected item against the latest stock snapshot
    const selectedItem = select.value ? window.facultyInventoryCache[select.value] : null;
    if (!selectedItem) {
        itemNotify('Please select a valid item from the inventory list.', 'Select an Item', 'warning');
        window.isSubmitting = false;
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'SUBMIT';
        }
        return;
    }

    if (Number(selectedItem.quantity) <= 0) {
        itemNotify('The selected item is out of stock. Please select a different item.', 'Item Out of Stock', 'warning');
        window.isSubmitting = false;
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'SUBMIT';
        }
        return;
    }

    if (Number(quantity.value) > Number(selectedItem.quantity)) {
        itemNotify(`Only ${selectedItem.quantity} unit(s) of ${selectedItem.name} are available.`, 'Not Enough Stock', 'warning');
        window.isSubmitting = false;
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'SUBMIT';
        }
        return;
    }
    
    // Get faculty name from session
    const formData = new FormData();
    formData.append('action', 'submit_request');
    formData.append('names', '<?php echo isset($_SESSION["firstName"]) && isset($_SESSION["lastName"]) ? $_SESSION["firstName"] . " " . $_SESSION["lastName"] : (isset($_SESSION["email"]) ? $_SESSION["email"] : "Faculty User"); ?>');
    formData.append('items', select.value);
    formData.append('quantity', quantity.value);
    formData.append('when_needed', whenNeededDate);
    formData.append('reason', reason.value);
    
    // Send to database
    fetch('/Prototype/Faculty/pages/request.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (data.success) {
                // Apply the server's new stock value right away; the dropdown is
                // reloaded again below so the badge and options stay in sync.
                if (selectedItem && typeof data.new_quantity !== 'undefined') {
                    selectedItem.quantity = Number(data.new_quantity);
                    selectedItem.stockKey = data.stock_status || getFacultyStockKey(selectedItem.quantity);
                }
                itemNotify(data.message || 'Your item request has been submitted successfully.', 'Request Submitted', 'success');
                // Reload requested items; the finally block below refreshes stock.
                loadRequestedItems();

                // Clear the form only after a successful submission. A rejected
                // submission keeps the user's entries so they can adjust them.
                select.value = '';
                quantity.value = '';
                whenNeeded.value = '';
                reason.value = '';
                quantity.removeAttribute('max');
                updateSelectedItemStock();
            } else {
                itemNotify(data.message || 'Failed to submit the request.', 'Submission Failed', 'error');
            }
        } catch (e) {
            itemNotify('The server returned an unexpected response. Please try again.', 'Something Went Wrong', 'error');
        }
    })
    .catch(error => {
        itemNotify('Unable to submit your request right now. Please try again.', 'Something Went Wrong', 'error');
    })
    .finally(() => {
        // Reset submission flag
        window.isSubmitting = false;
        
        // Re-enable submit button
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'SUBMIT';
        }
        
        // Refresh stock availability (and populate the select after a successful
        // submission reset). A failed submission keeps the user's current inputs.
        loadInventoryItems();
    });
};

// Format when_needed (date-only or datetime) without timezone shift
function formatItemWhenNeeded(val) {
    if (!val) return 'Not specified';
    const match = String(val).match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (!match) return String(val);
    const d = new Date(parseInt(match[1], 10), parseInt(match[2], 10) - 1, parseInt(match[3], 10));
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
}

// Load requested items
function loadRequestedItems() {
    fetch('/Prototype/database_requests.php?table=requested_items&action=get_all_requests')
        .then(response => {
            return response.json();
        })
        .then(data => {
                        
            // Handle response structure
            let requests = [];
            
            if (data && data.requests) {
                requests = data.requests;
            } else if (Array.isArray(data)) {
                requests = data;
            } else {
                requests = [];
            }
            const container = document.getElementById('requestListContainer');
            if (!container) {
                return;
            }
            
            if (requests.length === 0 || !requests) {
                container.innerHTML = '<p class="empty-message">No requested items yet</p>';
                if (typeof window.scheduleNotificationTargetClear === 'function') {
                    window.scheduleNotificationTargetClear(window.notificationTargetId);
                } else {
                    window.notificationTargetId = '';
                }
                return;
            }
            
            const targetId = Number(window.notificationTargetId) || 0;
            const html = requests.map(item => `
                <div class="request-item" data-id="${item.id}" id="requested-item-${item.id}">
                    <h4>Request #${item.id} - ${item.names}</h4>
                    <div class="request-details">
                        <p><strong>Items:</strong> ${item.quantity}x ${item.items}</p>
                        <p><strong>Reason:</strong> ${item.reason}</p>
                        <p><strong>When Needed:</strong> ${formatItemWhenNeeded(item.when_needed)}</p>
                        <p><strong>Status:</strong> <span class="status-badge status-${item.action || 'pending'}">${(item.action || 'pending').toUpperCase()}</span></p>
                    </div>
                    <div class="request-actions">
                        ${item.action === 'pending' ? `<button class="cancel-btn" onclick="showCancelModal(${item.id})">Cancel Request</button>` : '<span class="status-text-approved">No longer pending</span>'}
                    </div>
                </div>
            `).join('');
            
            container.innerHTML = html;

            // Deep link from a notification: highlight the referenced request
            // without hiding the rest of the request history.
            if (targetId) {
                const target = document.getElementById(`requested-item-${targetId}`);
                if (target) {
                    if (typeof window.highlightNotificationTarget === 'function') {
                        window.highlightNotificationTarget(target);
                    } else {
                        target.classList.add('notification-highlight');
                        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
                if (typeof window.scheduleNotificationTargetClear === 'function') {
                    window.scheduleNotificationTargetClear(targetId);
                } else {
                    window.notificationTargetId = '';
                }
            }
        })
        .catch(error => {
            const container = document.getElementById('requestListContainer');
            if (container) {
                container.innerHTML = '<p class="empty-message">Error loading requested items</p>';
            }
        });
}

// Load inventory items for the dropdown, always A-Z by item name
function loadInventoryItems() {
    return fetch('/Prototype/get_inventory_items.php', { cache: 'no-store' })
        .then(response => response.json())
        .then(data => {
            if (!data || !data.success || !Array.isArray(data.items)) {
                return;
            }

            const threshold = Number(data.low_stock_threshold);
            if (!isNaN(threshold) && threshold > 0) {
                window.facultyLowStockThreshold = threshold;
            }

            // Client-side sort is a safety net for older/cached API responses.
            const items = data.items.slice().sort((a, b) => {
                return String(a.name || '').localeCompare(String(b.name || ''), undefined, { sensitivity: 'base' });
            });

            const cache = {};
            items.forEach(item => {
                const quantity = Number(item.quantity) || 0;
                cache[item.name] = {
                    id: item.id,
                    name: item.name,
                    quantity: quantity,
                    stockKey: item.stock_status || getFacultyStockKey(quantity)
                };
            });
            window.facultyInventoryCache = cache;

            const select = document.getElementById('itemSelect');
            if (!select) {
                return;
            }

            // Keep whichever item the user already picked, if it still exists.
            const previousValue = select.value;
            select.innerHTML = '<option disabled selected>SELECT ITEM</option>';

            items.forEach(item => {
                const quantity = Number(item.quantity) || 0;
                const stockKey = getFacultyStockKey(quantity);
                const option = document.createElement('option');
                option.value = item.name;
                option.dataset.stock = stockKey;
                option.dataset.quantity = String(quantity);

                if (quantity <= 0) {
                    option.disabled = true;
                    option.style.color = '#999';
                    option.textContent = `${item.name} (Out of Stock)`;
                } else {
                    option.textContent = `${item.name} (${getFacultyStockLabel(stockKey)}: ${quantity} available)`;
                }

                select.appendChild(option);
            });

            if (previousValue && cache[previousValue]) {
                select.value = previousValue;
            }

            bindFacultyItemSelect();
            updateSelectedItemStock();
        })
        .catch(error => {
            // Keep the last known stock snapshot if the refresh fails
        });
}

// Auto-load inventory items when page loads
document.addEventListener('DOMContentLoaded', function() {
    loadInventoryItems();
    loadRequestedItems();
});

// Also call loadInventoryItems after a delay to ensure DOM is ready
setTimeout(function() {
    if (typeof loadInventoryItems === 'function') {
        loadInventoryItems();
    }
}, 500);

// Call loadRequestedItems to display only item requests
setTimeout(function() {
    if (typeof loadRequestedItems === 'function') {
        loadRequestedItems();
    }
}, 100);

// Poll the server so stock reserved by other faculty members appears live.
// The interval stops itself once the request page is no longer displayed.
function startFacultyInventoryPolling() {
    if (window.facultyInventoryPollTimer) {
        clearInterval(window.facultyInventoryPollTimer);
    }
    window.facultyInventoryPollTimer = setInterval(function() {
        if (document.hidden) return;
        if (!document.getElementById('itemSelect')) {
            clearInterval(window.facultyInventoryPollTimer);
            window.facultyInventoryPollTimer = null;
            return;
        }
        loadInventoryItems();
    }, 5000);
}

startFacultyInventoryPolling();
// Cancel Request Modal
function showCancelModal(requestId) {
    if (typeof window.showAppConfirm === 'function') {
        window.showAppConfirm(
            'Are you sure you want to cancel this request? This action cannot be undone.',
            'Cancel Request?',
            function () { confirmCancelRequest(requestId); },
            null,
            { type: 'danger', okText: 'Yes, Cancel', cancelText: 'No, Keep' }
        );
    } else if (confirm('Are you sure you want to cancel this request? This action cannot be undone.')) {
        confirmCancelRequest(requestId);
    }
}

function hideCancelModal() {
    if (typeof window.closeAppAlert === 'function') {
        window.closeAppAlert();
    }
}

function confirmCancelRequest(requestId) {
    cancelRequest(requestId);
}

function cancelRequest(requestId) {
    fetch('/Prototype/database_requests.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `table=requested_items&action=cancel&id=${requestId}`
    })
    .then(response => {
        return response.json();
    })
    .then(data => {
        if (data.success) {
            itemNotify('Your request has been cancelled successfully.', 'Request Cancelled', 'success');
            loadRequestedItems(); // Refresh the list
            loadInventoryItems(); // Reserved stock was returned to inventory
        } else {
            itemNotify('Error cancelling request: ' + (data.message || 'Unknown error'), 'Unable to Cancel', 'error');
        }
    })
    .catch(error => {
        console.error('Cancel request error:', error);
        itemNotify('An error occurred while cancelling the request. Please try again.', 'Something Went Wrong', 'error');
    });
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('facAlertModal');
    if (modal && event.target === modal && typeof window.closeAppAlert === 'function') {
        window.closeAppAlert();
    }
}
</script>

<style>
/* Mobile Responsive Styles */
@media (max-width: 768px) {
    .request-form {
        padding: 20px;
        margin: 10px;
    }
    
    .request-form h2 {
        font-size: 24px;
        margin-bottom: 20px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        font-size: 14px;
        margin-bottom: 8px;
    }
    
    .form-group input,
    .form-group textarea {
        font-size: 16px;
        padding: 12px;
    }
    
    .form-group textarea {
        min-height: 100px;
    }
    
    .submit-btn {
        padding: 15px;
        font-size: 16px;
        margin-top: 20px;
    }
    
    .request-list {
        padding: 20px;
        margin: 10px;
    }
    
    .request-list h2 {
        font-size: 24px;
        margin-bottom: 20px;
    }
    
    .request-item {
        padding: 15px;
        margin-bottom: 15px;
    }
    
    .request-item h4 {
        font-size: 16px;
        margin-bottom: 10px;
    }
    
    .request-item p {
        font-size: 14px;
        margin-bottom: 8px;
    }
    
    .request-item strong {
        font-size: 14px;
    }
    
    .request-actions {
        margin-top: 15px;
    }
    
    .view-btn, .cancel-btn {
        padding: 8px 16px;
        font-size: 12px;
    }
    
    .modal-content {
        padding: 20px;
        margin: 10px;
    }
    
    .modal-content h3 {
        font-size: 18px;
    }
    
    .modal-content p {
        font-size: 14px;
    }
}

@media (max-width: 480px) {
    .request-form {
        padding: 15px;
        margin: 5px;
    }
    
    .request-form h2 {
        font-size: 20px;
        margin-bottom: 15px;
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    .form-group label {
        font-size: 13px;
        margin-bottom: 6px;
    }
    
    .form-group input,
    .form-group textarea {
        font-size: 14px;
        padding: 10px;
    }
    
    .form-group textarea {
        min-height: 80px;
    }
    
    .submit-btn {
        padding: 12px;
        font-size: 14px;
        margin-top: 15px;
    }
    
    .request-list {
        padding: 15px;
        margin: 5px;
    }
    
    .request-list h2 {
        font-size: 20px;
        margin-bottom: 15px;
    }
    
    .request-item {
        padding: 12px;
        margin-bottom: 12px;
    }
    
    .request-item h4 {
        font-size: 14px;
        margin-bottom: 8px;
    }
    
    .request-item p {
        font-size: 13px;
        margin-bottom: 6px;
    }
    
    .request-item strong {
        font-size: 13px;
    }
    
    .request-actions {
        margin-top: 12px;
    }
    
    .view-btn, .cancel-btn {
        padding: 6px 12px;
        font-size: 11px;
    }
    
    .modal-content {
        padding: 15px;
        margin: 5px;
    }
    
    .modal-content h3 {
        font-size: 16px;
    }
    
    .modal-content p {
        font-size: 13px;
    }
}
</style>

<?php include __DIR__ . '/../includes/custom_alert_modal.php'; ?>

</body>
</html>
