// Vehicle Request JavaScript - Based on original design

// Global variables
let currentFilter = 'pending'; // Default to pending
let allRequests = [];
let currentTimeFilter = { month: '', year: '' };

// Load vehicle requests from database
async function loadVehicleRequests() {
    const requestList = document.getElementById('vehicleRequestList');
    if (!requestList) return;

    // Show loading state
    const tbody = requestList.querySelector('tbody');
    tbody.innerHTML = `
        <tr>
            <td colspan="7" style="text-align: center; padding: 20px;">
                <div class="loading">
                    <p>Loading vehicle requests...</p>
                </div>
            </td>
        </tr>
    `;

    try {
        const response = await fetch('/Prototype/simple_get_requests.php');
        const requests = await response.json();
        
        // Store all requests for filtering
        allRequests = requests;
        
        // Load pending requests by default
        filterByStatus(currentFilter);
        
    } catch (error) {
        console.error('Error loading vehicle requests:', error);
        tbody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align: center; padding: 20px;">
                    <div class="error-message">
                        <p>Error loading vehicle requests. Please try again.</p>
                    </div>
                </td>
            </tr>
        `;
    }
}

// Filter requests by status
function filterByStatus(status) {
    currentFilter = status;
    
    if (!allRequests || allRequests.length === 0) {
        return;
    }
    
    // Filter by status
    let filteredRequests = allRequests.filter(request => {
        const requestStatus = request.status || 'pending';
        
        if (status === 'pending') {
            return requestStatus === 'pending';
        } else if (status === 'approved') {
            return requestStatus === 'approved';
        } else if (status === 'declined') {
            return requestStatus === 'declined';
        }
        return false;
    });
    
    // Apply time filter
    filteredRequests = filterByTime(filteredRequests);
    
    displayRequests(filteredRequests, status);
}

// Filter requests by time
function filterByTime(requests) {
    if (!currentTimeFilter.month && !currentTimeFilter.year) {
        return requests;
    }
    
    return requests.filter(request => {
        if (!request.request_date) return false;
        
        const requestDate = new Date(request.request_date);
        const requestMonth = requestDate.getMonth() + 1;
        const requestYear = requestDate.getFullYear();
        
        const monthMatch = !currentTimeFilter.month || requestMonth == currentTimeFilter.month;
        const yearMatch = !currentTimeFilter.year || requestYear == currentTimeFilter.year;
        
        return monthMatch && yearMatch;
    });
}

// Display requests in table
function displayRequests(requests, status) {
    const requestList = document.getElementById('vehicleRequestList');
    if (!requestList) return;
    
    const tbody = requestList.querySelector('tbody');
    
    if (requests.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align: center; padding: 20px;">
                    <div class="empty-message">
                        <h3>No ${status} requests found</h3>
                        <p>There are no ${status} vehicle requests to display${currentTimeFilter.month || currentTimeFilter.year ? ' for the selected time period' : ''}.</p>
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    // Generate HTML for filtered requests
    const requestsHTML = requests.map((request, index) => {
        // Format dates
        const travelDate = formatDate(request.travel_date);
        const timeSent = getRelativeTime(request.created_at || request.request_date);
        
        return `
        <tr>
            <td>${index + 1}</td>
            <td>${request.requested_by}</td>
            <td>${request.destination}</td>
            <td>${travelDate}</td>
            <td class="purpose-cell">${request.purpose}</td>
            <td>${timeSent}</td>
            <td>
                <span class="status-badge ${request.status}">${request.status || 'pending'}</span>
            </td>
        </tr>
        `;
    }).join('');
    
    tbody.innerHTML = requestsHTML;
}

// Format date
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;
    
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    };
    return date.toLocaleDateString('en-US', options);
}

// Calculate relative time
function getRelativeTime(dateString) {
    if (!dateString) return 'Unknown time';
    
    const now = new Date();
    const pastDate = new Date(dateString);
    const diffMs = now - pastDate;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);
    
    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins} minute${diffMins !== 1 ? 's' : ''} ago`;
    if (diffHours < 24) return `${diffHours} hour${diffHours !== 1 ? 's' : ''} ago`;
    if (diffDays < 7) return `${diffDays} day${diffDays !== 1 ? 's' : ''} ago`;
    
    return formatDate(dateString);
}

// Apply time filter
function applyTimeFilter() {
    const monthFilter = document.getElementById('monthFilter');
    const yearFilter = document.getElementById('yearFilter');
    
    currentTimeFilter.month = monthFilter.value;
    currentTimeFilter.year = yearFilter.value;
    
    // Re-apply current status filter with time filter
    filterByStatus(currentFilter);
}

// Clear time filter
function clearTimeFilter() {
    document.getElementById('monthFilter').value = '';
    document.getElementById('yearFilter').value = '';
    currentTimeFilter = { month: '', year: '' };
    
    // Re-apply current status filter without time filter
    filterByStatus(currentFilter);
}

// Initialize year dropdown
function initializeYearFilter() {
    const yearFilter = document.getElementById('yearFilter');
    if (!yearFilter) return;
    
    const currentYear = new Date().getFullYear();
    yearFilter.innerHTML = '<option value="">All Years</option>';
    
    for (let year = currentYear; year >= currentYear - 5; year--) {
        yearFilter.innerHTML += `<option value="${year}">${year}</option>`;
    }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    initializeYearFilter();
    loadVehicleRequests();
});

// Make functions global
window.loadVehicleRequests = loadVehicleRequests;
window.filterByStatus = filterByStatus;
window.applyTimeFilter = applyTimeFilter;
window.clearTimeFilter = clearTimeFilter;
