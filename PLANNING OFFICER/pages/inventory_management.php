<?php
session_start();
if (!isset($_SESSION['email']) || trim($_SESSION['role']) !== 'planning_officer') {
    header("Location: ../../auth/login.php");
    exit();
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/Prototype/connect.php';

// Fetch inventory items from database
$items = [];
$sql = "SELECT id, item_name, quantity FROM inventory_management ORDER BY item_name ASC";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
}
?>

<link href="/Prototype/assets/fonts/material-icons.css" rel="stylesheet">
<link href="/Prototype/assets/fonts/poppins.css" rel="stylesheet">

<style>
/* ================= INVENTORY MANAGEMENT STYLES ================= */

/* Pull the card closer to the top bar; scoped to this page because the
   style block is removed with the fragment on navigation. */
#mainContent {
    padding-top: 16px;
}

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

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
    margin-bottom: 16px;
    padding-bottom: 12px;
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

/* Inline search group: input + Search + Clear on one row, right of the title */
.filter-controls {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    flex: 0 0 auto;
    flex-wrap: nowrap;
}

.filter-field {
    flex: 0 0 300px;
    min-width: 0;
}

.search-input {
    width: 100%;
    height: 38px;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    background: #ffffff;
    color: #111827;
    font-family: inherit;
    font-size: 13px;
    box-sizing: border-box;
}

.search-input::placeholder {
    color: #9ca3af;
}

.search-input:focus {
    outline: none;
    border-color: #1E5E3A;
    box-shadow: 0 0 0 3px rgba(30, 94, 58, 0.12);
}

.filter-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    height: 38px;
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

.filter-btn .material-icons {
    font-size: 18px;
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

/* Right-side toolbar action (matches the Vehicle Requests Summary button) */
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

/* Add / edit form */
.form-section {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 18px;
}

.form-section h3 {
    color: #111827;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    margin: 0 0 16px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 4px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-group label {
    color: #6b7280;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.form-input {
    width: 100%;
    height: 40px;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    background: #ffffff;
    color: #111827;
    font-family: inherit;
    font-size: 13.5px;
    box-sizing: border-box;
}

.form-input:focus {
    outline: none;
    border-color: #1E5E3A;
    box-shadow: 0 0 0 3px rgba(30, 94, 58, 0.12);
}

.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #e5e7eb;
}

/* Inventory cards */
.request-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    width: 100%;
    max-width: 100%;
    margin: 0;
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

.status-badge.normal-stock {
    background: #ecfdf3;
    border-color: #abefc6;
    color: #067647;
}

.status-badge.low-stock {
    background: #fffbeb;
    border-color: #fde68a;
    color: #92400e;
}

.status-badge.out-stock {
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

.item-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #e5e7eb;
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

.item-action-btn.edit {
    border: 1px solid #1E5E3A;
    background: #1E5E3A;
    color: #ffffff;
}

.item-action-btn.edit:hover {
    background: #174a2e;
    border-color: #174a2e;
}

.item-action-btn.delete {
    border: 1px solid #fecdca;
    background: #ffffff;
    color: #b42318;
}

.item-action-btn.delete:hover {
    background: #fef3f2;
    border-color: #fda29b;
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
        padding: 18px;
    }

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
        flex: 1 1 auto;
        min-width: 0;
    }
}

@media (max-width: 600px) {
    .filter-controls {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        align-items: end;
    }

    .filter-field {
        grid-column: 1 / -1;
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

    .vr-toolbar .filter-btn,
    .vr-toolbar .vr-summary-btn {
        width: 100%;
    }

    .request-item {
        padding: 16px;
    }

    .request-details {
        grid-template-columns: 1fr;
    }

    .form-actions {
        flex-direction: column-reverse;
    }

    .form-actions .filter-btn {
        width: 100%;
    }
}
</style>

<div class="paper-form">
        <div class="page-header">
            <div class="page-heading">
                <h1>INVENTORY MANAGEMENT</h1>
                <p class="page-subtitle">Track supply stock levels and keep item quantities up to date.</p>
            </div>

            <div class="filter-controls" role="search" aria-label="Search inventory">
                <div class="filter-field">
                    <input type="text" id="searchInput" placeholder="Search items as you type..." class="search-input" aria-label="Search inventory" autocomplete="off" oninput="searchItems()">
                </div>
                <button type="button" class="filter-btn filter-btn-primary" onclick="searchItems()">Search</button>
                <button type="button" class="filter-btn filter-btn-ghost" onclick="clearSearch()">Clear</button>
            </div>
        </div>

        <div class="vr-toolbar">
            <div class="vr-tabs" role="tablist" aria-label="Filter inventory by stock level">
                <button type="button" class="vr-tab active" data-stock="all" role="tab" aria-selected="true" onclick="filterInventoryByStatus('all')">All Items</button>
                <button type="button" class="vr-tab" data-stock="in-stock" role="tab" aria-selected="false" onclick="filterInventoryByStatus('in-stock')">In Stock</button>
                <button type="button" class="vr-tab" data-stock="low-stock" role="tab" aria-selected="false" onclick="filterInventoryByStatus('low-stock')">Low Stock</button>
                <button type="button" class="vr-tab" data-stock="out-stock" role="tab" aria-selected="false" onclick="filterInventoryByStatus('out-stock')">Out of Stock</button>
            </div>

            <button type="button" class="vr-summary-btn" onclick="showAddForm()">
                <span class="material-icons">add_circle</span>
                Add New Item
            </button>
        </div>

        <!-- Add/Edit Item Form -->
        <div id="itemForm" class="form-section" style="display: none;">
            <h3 id="formTitle">Add New Item</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label for="itemName">Item Name</label>
                    <input type="text" id="itemName" class="form-input" placeholder="Enter item name">
                </div>
                <div class="form-group">
                    <label for="itemQuantity">Quantity</label>
                    <input type="number" id="itemQuantity" class="form-input" placeholder="Enter quantity" min="0">
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="filter-btn filter-btn-ghost" onclick="cancelForm()">Cancel</button>
                <button type="button" class="filter-btn filter-btn-primary" onclick="saveItem()">Save Item</button>
            </div>
        </div>

        <div class="request-list" id="inventoryList">
            <?php if (empty($items)): ?>
                <div class="empty-message">
                    <h3>No inventory items found</h3>
                    <p>There are no inventory items to display.</p>
                </div>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <?php
                        $itemQty = (int)$item['quantity'];
                        $stockKey = $itemQty <= 0 ? 'out' : ($itemQty <= 5 ? 'low' : 'in');
                        $stockClass = $stockKey === 'out' ? 'out-stock' : ($stockKey === 'low' ? 'low-stock' : 'normal-stock');
                        $stockLabel = $stockKey === 'out' ? 'Out of Stock' : ($stockKey === 'low' ? 'Low Stock' : 'In Stock');
                        $stockNote = $stockKey === 'out' ? 'No stock available' : ($stockKey === 'low' ? 'Needs restocking' : 'Sufficient stock');
                    ?>
                    <div class="request-item">
                        <div class="request-header">
                            <div class="request-heading">
                                <span class="request-number">#<?php echo (int)$item['id']; ?></span>
                                <span class="request-subject"><?php echo htmlspecialchars($item['item_name']); ?></span>
                            </div>
                            <span class="status-badge <?php echo $stockClass; ?>">
                                <?php echo $stockLabel; ?>
                            </span>
                        </div>
                        <div class="request-details">
                            <div class="detail-item">
                                <span class="detail-label">Item Name</span>
                                <span class="detail-value"><?php echo htmlspecialchars($item['item_name']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Quantity</span>
                                <span class="detail-value"><?php echo htmlspecialchars($item['quantity']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Stock Status</span>
                                <span class="detail-value"><?php echo $stockNote; ?></span>
                            </div>
                        </div>
                        <div class="item-actions">
                            <button type="button" class="item-action-btn edit" onclick="editItem(<?php echo (int)$item['id']; ?>)">Edit</button>
                            <button type="button" class="item-action-btn delete" onclick="deleteItem(<?php echo (int)$item['id']; ?>)">Delete</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
</div>

<script>
    // Pass PHP data to JavaScript
    // Use var (not const) so re-execution of this inline script doesn't throw a redeclaration error
    var allInventoryData = <?php echo json_encode($items); ?>;
    var currentEditId = null;
    window.inventoryStockFilter = 'all';

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

    // Initialize inventory management
    setTimeout(() => {
        window.inventoryItems = allInventoryData;
        displayInventoryItems(allInventoryData);
    }, 100);

    // Filter inventory by stock level
    function filterInventoryByStatus(status) {
        window.inventoryStockFilter = status;

        document.querySelectorAll('.vr-tab[data-stock]').forEach(tab => {
            const isActive = tab.dataset.stock === status;
            tab.classList.toggle('active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        searchItems();
    }

    // Show add form
    function showAddForm() {
        document.getElementById('formTitle').textContent = 'Add New Item';
        document.getElementById('itemForm').style.display = 'block';
        clearForm();
        currentEditId = null;
        
        // Scroll up to the add/edit form
        const form = document.getElementById('itemForm');
        if (form) {
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
    
    // Edit item
    function editItem(itemId) {
        const item = allInventoryData.find(i => i.id == itemId);
        if (!item) return;
        
        document.getElementById('formTitle').textContent = 'Edit Item';
        document.getElementById('itemForm').style.display = 'block';
        
        // Populate form with item data
        document.getElementById('itemName').value = item.item_name || '';
        document.getElementById('itemQuantity').value = item.quantity || '';
        
        currentEditId = itemId;
        
        // Scroll up to the edit form so the user can see it
        const form = document.getElementById('itemForm');
        if (form) {
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
    
    // Save item (add or update)
    async function saveItem() {
        const itemName = document.getElementById('itemName').value.trim();
        const quantity = document.getElementById('itemQuantity').value;
        
        if (!itemName || !quantity) {
            showAppAlert('Please fill in Item Name and Quantity.', 'Missing Information', null, { type: 'warning' });
            return;
        }
        
        try {
            let response;
            
            if (currentEditId) {
                // Update existing item
                const formData = new FormData();
                formData.append('action', 'update_quantity');
                formData.append('item_id', currentEditId);
                formData.append('quantity', quantity);
                
                response = await fetch('/Prototype/inventory_api.php', {
                    method: 'POST',
                    body: formData
                });
            } else {
                // Add new item
                const formData = new FormData();
                formData.append('action', 'add_item');
                formData.append('name', itemName);
                formData.append('quantity', quantity);
                
                response = await fetch('/Prototype/inventory_api.php', {
                    method: 'POST',
                    body: formData
                });
            }
            
            const result = await response.json();
            
            if (result.success) {
                showAppAlert(currentEditId ? 'Item updated successfully!' : 'Item added successfully!', 'Success', null, { type: 'success' });
                cancelForm();
                await refreshInventory(); // Refresh the list in place (stays on this page)
            } else {
                showAppAlert('Error: ' + (result.error || 'Unknown error'), 'Unable to Save Item', null, { type: 'error' });
            }
        } catch (error) {
            console.error('Error saving item:', error);
            showAppAlert('Error saving item. Please try again.', 'Something Went Wrong', null, { type: 'error' });
        }
    }
    
    // Delete item
    async function deleteItem(itemId) {
        showAppConfirm('Are you sure you want to delete this item?', 'Delete Item?', function () {
            performDeleteItem(itemId);
        }, null, { type: 'danger', okText: 'Delete', cancelText: 'Cancel' });
    }

    async function performDeleteItem(itemId) {
        try {
            const formData = new FormData();
            formData.append('action', 'delete_item');
            formData.append('item_id', itemId);
            
            const response = await fetch('/Prototype/inventory_api.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                showAppAlert('Item deleted successfully!', 'Item Deleted', null, { type: 'success' });
                await refreshInventory(); // Refresh the list in place (stays on this page)
            } else {
                showAppAlert('Error: ' + (result.error || 'Unknown error'), 'Unable to Delete Item', null, { type: 'error' });
            }
        } catch (error) {
            console.error('Error deleting item:', error);
            showAppAlert('Error deleting item. Please try again.', 'Something Went Wrong', null, { type: 'error' });
        }
    }
    
    // Search items
    function searchItems() {
        const searchInput = document.getElementById('searchInput');
        const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
        
        let filteredItems = allInventoryData;
        if (searchTerm) {
            filteredItems = allInventoryData.filter(item => {
                return String(item.item_name || '').toLowerCase().includes(searchTerm);
            });
        }
        
        displayInventoryItems(filteredItems);
    }
    
    // Clear search
    function clearSearch() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) searchInput.value = '';
        searchItems();
    }
    
    // Cancel form
    function cancelForm() {
        document.getElementById('itemForm').style.display = 'none';
        clearForm();
        currentEditId = null;
    }
    
    // Clear form
    function clearForm() {
        document.getElementById('itemName').value = '';
        document.getElementById('itemQuantity').value = '';
    }
    
    // Map a quantity to its stock state (threshold = 5 units)
    function getStockKey(quantity) {
        const qty = Number(quantity) || 0;
        if (qty <= 0) return 'out';
        if (qty <= 5) return 'low';
        return 'in';
    }

    // Display inventory items as cards (always sorted A-Z by item name)
    function displayInventoryItems(items) {
        const inventoryList = document.getElementById('inventoryList');
        if (!inventoryList) return;

        const stockFilter = window.inventoryStockFilter || 'all';
        const sortedItems = (items || []).slice().sort((a, b) => {
            return String(a.item_name || '').localeCompare(String(b.item_name || ''), undefined, { sensitivity: 'base' });
        });
        const visibleItems = sortedItems.filter(item => {
            const stockKey = getStockKey(item.quantity);
            if (stockFilter === 'low-stock') return stockKey === 'low';
            if (stockFilter === 'in-stock') return stockKey === 'in';
            if (stockFilter === 'out-stock') return stockKey === 'out';
            return true;
        });

        if (visibleItems.length === 0) {
            const label = stockFilter === 'low-stock'
                ? 'low stock items found'
                : (stockFilter === 'in-stock'
                    ? 'in-stock items found'
                    : (stockFilter === 'out-stock' ? 'out-of-stock items found' : 'items found'));
            inventoryList.innerHTML = `
                <div class="empty-message">
                    <h3>No ${label}</h3>
                    <p>There are no inventory items matching the current search and filters.</p>
                </div>
            `;
            return;
        }

        inventoryList.innerHTML = visibleItems.map(item => {
            const stockKey = getStockKey(item.quantity);
            const stockClass = stockKey === 'out' ? 'out-stock' : (stockKey === 'low' ? 'low-stock' : 'normal-stock');
            const stockLabel = stockKey === 'out' ? 'Out of Stock' : (stockKey === 'low' ? 'Low Stock' : 'In Stock');
            const stockNote = stockKey === 'out' ? 'No stock available' : (stockKey === 'low' ? 'Needs restocking' : 'Sufficient stock');
            return `
                <div class="request-item">
                    <div class="request-header">
                        <div class="request-heading">
                            <span class="request-number">#${escapeHtml(item.id)}</span>
                            <span class="request-subject">${escapeHtml(item.item_name)}</span>
                        </div>
                        <span class="status-badge ${stockClass}">
                            ${stockLabel}
                        </span>
                    </div>
                    <div class="request-details">
                        <div class="detail-item">
                            <span class="detail-label">Item Name</span>
                            <span class="detail-value">${escapeHtml(item.item_name)}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Quantity</span>
                            <span class="detail-value">${escapeHtml(item.quantity)}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Stock Status</span>
                            <span class="detail-value">${stockNote}</span>
                        </div>
                    </div>
                    <div class="item-actions">
                        <button type="button" class="item-action-btn edit" onclick="editItem(${Number(item.id)})">Edit</button>
                        <button type="button" class="item-action-btn delete" onclick="deleteItem(${Number(item.id)})">Delete</button>
                    </div>
                </div>
            `;
        }).join('');
    }
    
    // Refresh inventory list from the server (keeps the page, no reload)
    async function refreshInventory() {
        try {
            const response = await fetch('/Prototype/inventory_api.php', {
                method: 'POST',
                body: new URLSearchParams({ action: 'get_items' })
            });
            const result = await response.json();
            if (result.success && Array.isArray(result.items)) {
                allInventoryData = result.items;
                window.inventoryItems = allInventoryData;
                searchItems();
            }
        } catch (error) {
            console.error('Error refreshing inventory:', error);
        }
    }
    
    // Live search is driven by the input/keyup handlers on the search field
    // itself, so results filter as the user types without clicking "Search".
    // This binding only adds the Enter-key shortcut (and keeps repeat loads safe).
    function bindInventorySearch() {
        const searchInput = document.getElementById('searchInput');
        if (!searchInput || searchInput.dataset.liveSearchBound === '1') return;

        searchInput.dataset.liveSearchBound = '1';
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchItems();
            }
        });
    }

    bindInventorySearch();
    document.addEventListener('DOMContentLoaded', bindInventorySearch);

    // Poll the server so stock changes (e.g. faculty submissions) appear live
    // without a manual refresh. Stop once the inventory page is no longer shown.
    function startInventoryPolling() {
        if (window.inventoryPollTimer) {
            clearInterval(window.inventoryPollTimer);
        }
        window.inventoryPollTimer = setInterval(function () {
            if (document.hidden) return;
            if (!document.getElementById('inventoryList')) {
                clearInterval(window.inventoryPollTimer);
                window.inventoryPollTimer = null;
                return;
            }
            refreshInventory();
        }, 5000);
    }

    startInventoryPolling();
</script>

<?php include __DIR__ . '/../includes/custom_alert_modal.php'; ?>
