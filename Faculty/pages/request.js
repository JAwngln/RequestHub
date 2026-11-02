// Request page specific JavaScript
console.log("Request.js loaded successfully");

// Initialize request form when page loads
function initRequestForm() {
    console.log("Initializing request form...");
    
    // Load inventory items for dropdown
    if (typeof loadInventoryItems === 'function') {
        loadInventoryItems();
    }
    
    // Load requested items
    if (typeof loadRequestedItems === 'function') {
        loadRequestedItems();
    }
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (typeof initRequestForm === 'function') {
        initRequestForm();
    }
});

// Also initialize with a delay to ensure DOM is fully ready
setTimeout(function() {
    if (typeof initRequestForm === 'function') {
        initRequestForm();
    }
}, 500);
