// Planning Officer Dashboard Script

// Global variables
let currentFilter = 'pending'; // Default to pending instead of all
let allRequests = [];
let currentTimeFilter = { month: '', year: '' };

// Toggle notification box
function toggleNotif() {
    const notifBox = document.getElementById('notifBox');
    if (notifBox) {
        if (notifBox.style.display === 'block') {
            notifBox.style.display = 'none';
        } else {
            notifBox.style.display = 'block';
            // Check for new requests when opening notifications
            checkForNewRequests();
        }
    }
}

// Clear all notifications
function clearNotifications() {
    // Hide notification badge
    const badge = document.querySelector('.notification-badge');
    if (badge) {
        badge.style.display = 'none';
    }
    
    // Clear notification box
    const notifBox = document.getElementById('notifBox');
    if (notifBox) {
        notifBox.innerHTML = '<p>No new notifications</p>';
    }
    
    // Close notification box after clearing
    setTimeout(() => {
        if (notifBox) {
            notifBox.style.display = 'none';
        }
    }, 1000);
}

// Close notification box when clicking outside
document.addEventListener('click', function(event) {
    const notifBox = document.getElementById('notifBox');
    const topRight = document.querySelector('.top-right');
    
    if (notifBox && notifBox.style.display === 'block') {
        if (!topRight.contains(event.target)) {
            notifBox.style.display = 'none';
        }
    }
});

// Show vehicle dashboard content
function showVehicleDashboard() {
    console.log('showVehicleDashboard called');
    const mainContent = document.getElementById('mainContent');
    
    if (!mainContent) {
        console.error('mainContent element not found');
        return;
    }
    
    console.log('Setting vehicle dashboard HTML');
    
    mainContent.innerHTML = `
        <div class="paper-form">
            <h1 style="text-align: center; margin-top: 50px;">VEHICLE REQUESTS</h1>
            
            <!-- Time Filter Controls -->
            <div class="time-filter-section">
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
                
                <select id="yearFilter" class="time-select">
                    <option value="">All Years</option>
                </select>
                
                <button class="btn secondary" onclick="applyTimeFilter()">APPLY FILTER</button>
                <button class="btn primary" onclick="clearTimeFilter()">CLEAR FILTER</button>
            </div>
            
            <!-- Filter Buttons -->
            <div class="filter-section">
                <button class="btn pending" onclick="filterByStatus('pending')">SHOW PENDING</button>
                <button class="btn success" onclick="filterByStatus('approved')">SHOW APPROVED</button>
                <button class="btn danger" onclick="filterByStatus('declined')">SHOW DECLINED</button>
            </div>
            
            <div class="request-list" id="vehicleRequestList">
                <div class="loading">
                    <p>Loading vehicle requests...</p>
                </div>
            </div>
        </div>
    `;
    
    console.log('Vehicle dashboard HTML set');
    
    // Initialize the vehicle dashboard
    initializeYearFilter();
    loadVehicleRequests();
}

// Format time from 24-hour to 12-hour format
function openChat(chatId) {
    // Remove active class from all conversations
    document.querySelectorAll('.conversation-item').forEach(item => {
        item.classList.remove('active');
    });
    
    // Add active class to selected conversation
    event.currentTarget.classList.add('active');
    
    // Update chat header based on selected conversation
    const chatNames = {
        1: 'John Doe (Faculty)',
        2: 'Jane Smith (Faculty)', 
        3: 'Mike Johnson (Staff)'
    };
    
    const chatUserInfo = document.querySelector('.chat-user-name');
    if (chatUserInfo) {
        chatUserInfo.textContent = chatNames[chatId] || 'Unknown User';
    }
    
    // Load chat messages (in real app, this would fetch from database)
    loadChatMessages(chatId);
}

// Load chat messages
function loadChatMessages(chatId) {
    const chatMessages = document.getElementById('chatMessages');
    
    // Sample messages for different chats
    const messages = {
        1: [
            { type: 'received', text: 'Hi, I need to request a vehicle for tomorrow.', time: '10:30 AM' },
            { type: 'sent', text: 'Sure, I can help you with that. What time?', time: '10:32 AM' },
            { type: 'received', text: 'Around 8:00 AM. We need to go to the research facility.', time: '10:33 AM' },
            { type: 'sent', text: 'Perfect. I\'ll arrange the vehicle for you.', time: '10:35 AM' }
        ],
        2: [
            { type: 'received', text: 'Thank you for approving my request!', time: '9:15 AM' },
            { type: 'sent', text: 'You\'re welcome! Have a great trip.', time: '9:20 AM' }
        ],
        3: [
            { type: 'received', text: 'Is the vehicle available tomorrow?', time: '8:45 AM' },
            { type: 'sent', text: 'Let me check the availability for you.', time: '8:50 AM' }
        ]
    };
    
    const chatMessageHtml = (messages[chatId] || []).map(msg => `
        <div class="message ${msg.type}">
            ${msg.type === 'received' ? `
                <div class="message-avatar">
                    <img src="../cvsu-logo.png" alt="User">
                </div>
            ` : ''}
            <div class="message-content">
                <div class="message-text">${msg.text}</div>
                <div class="message-time">${msg.time}</div>
            </div>
        </div>
    `).join('');
    
    if (chatMessages) {
        chatMessages.innerHTML = chatMessageHtml;
        // Scroll to bottom
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
}

// Send message
function sendMessage() {
    const messageInput = document.getElementById('messageInput');
    const messageText = messageInput.value.trim();
    
    if (!messageText) return;
    
    const chatMessages = document.getElementById('chatMessages');
    const currentTime = new Date().toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        hour12: true 
    });
    
    const newMessage = document.createElement('div');
    newMessage.className = 'message sent';
    newMessage.innerHTML = `
        <div class="message-content">
            <div class="message-text">${messageText}</div>
            <div class="message-time">${currentTime}</div>
        </div>
    `;
    
    chatMessages.appendChild(newMessage);
    messageInput.value = '';
    
    // Scroll to bottom
    chatMessages.scrollTop = chatMessages.scrollHeight;
    
    // Simulate response (in real app, this would be handled by backend)
    setTimeout(() => {
        simulateResponse();
    }, 1000);
}

// Simulate response (for demo purposes)
function simulateResponse() {
    const chatMessages = document.getElementById('chatMessages');
    const responses = [
        'I understand. Let me check that for you.',
        'Sure, I can help with that.',
        'Thank you for your message.',
        'I\'ll look into this right away.'
    ];
    
    const randomResponse = responses[Math.floor(Math.random() * responses.length)];
    const currentTime = new Date().toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        hour12: true 
    });
    
    const responseMessage = document.createElement('div');
    responseMessage.className = 'message received';
    responseMessage.innerHTML = `
        <div class="message-avatar">
            <img src="../cvsu-logo.png" alt="User">
        </div>
        <div class="message-content">
            <div class="message-text">${randomResponse}</div>
            <div class="message-time">${currentTime}</div>
        </div>
    `;
    
    chatMessages.appendChild(responseMessage);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// Handle message input key press
function handleMessageKeyPress(event) {
    if (event.key === 'Enter') {
        sendMessage();
    }
}

// Show a styled modal when available, fall back to a native alert
function poNotify(message, title, type) {
    if (typeof window.showAppAlert === 'function') {
        window.showAppAlert(message, title, null, { type: type || 'info' });
    } else {
        alert(message);
    }
}

// Start new chat
function startNewChat() {
    poNotify('New chat feature coming soon!', 'Coming Soon', 'info');
}

function toggleChatInfo() {
    poNotify('User info feature coming soon!', 'Coming Soon', 'info');
}

// Close chat and go back to dashboard
function closeChat() {
    loadVehicleRequests(); // Go back to vehicle requests dashboard
}

// Show dashboard (vehicle requests)
function showDashboard() {
    loadVehicleRequests();
}

// Initialize year dropdown
function initializeYearFilter() {
    const yearFilter = document.getElementById('yearFilter');
    if (!yearFilter) {
        console.log('Year filter element not found, skipping initialization');
        return;
    }
    
    const currentYear = new Date().getFullYear();
    
    // Add years from current year back to 3 years ago
    for (let year = currentYear; year >= currentYear - 3; year--) {
        const option = document.createElement('option');
        option.value = year;
        option.textContent = year;
        yearFilter.appendChild(option);
    }
}

// Apply time filter
function applyTimeFilter() {
    const monthFilter = document.getElementById('monthFilter');
    const yearFilter = document.getElementById('yearFilter');
    
    if (!monthFilter || !yearFilter) {
        console.error('Filter elements not found');
        return;
    }
    
    currentTimeFilter.month = monthFilter.value;
    currentTimeFilter.year = yearFilter.value;
    
    // Re-apply current status filter with time filter
    if (typeof currentFilter !== 'undefined') {
        filterByStatus(currentFilter);
    } else {
        console.error('currentFilter not defined');
    }
}

// Clear time filter
function clearTimeFilter() {
    const monthFilter = document.getElementById('monthFilter');
    const yearFilter = document.getElementById('yearFilter');
    
    if (!monthFilter || !yearFilter) {
        console.error('Filter elements not found');
        return;
    }
    
    monthFilter.value = '';
    yearFilter.value = '';
    currentTimeFilter = { month: '', year: '' };
    
    // Re-apply current status filter without time filter
    if (typeof currentFilter !== 'undefined') {
        filterByStatus(currentFilter);
    } else {
        console.error('currentFilter not defined');
    }
}

// Filter requests by time
function filterByTime(requests) {
    if (!currentTimeFilter.month && !currentTimeFilter.year) {
        return requests;
    }
    
    return requests.filter(request => {
        if (!request.created_at) return false;
        
        const requestDate = new Date(request.created_at);
        const requestMonth = requestDate.getMonth() + 1; // getMonth() returns 0-11
        const requestYear = requestDate.getFullYear();
        
        const monthMatch = !currentTimeFilter.month || requestMonth == currentTimeFilter.month;
        const yearMatch = !currentTimeFilter.year || requestYear == currentTimeFilter.year;
        
        return monthMatch && yearMatch;
    });
}

// Check for new requests and show notification
async function checkForNewRequests() {
    // The dashboard shell (home.php) now owns the notification UI. Delegate to the
    // clickable, server-backed list instead of replacing it with static legacy
    // entries, which had no click handlers and blocked deep-linking.
    if (typeof loadNotifications === 'function') {
        await loadNotifications();
        const badge = document.getElementById('notificationBadge');
        return parseInt(badge ? badge.textContent : '0', 10) || 0;
    }

    try {
        const response = await fetch('/Prototype/simple_get_requests.php');
        const requests = await response.json();
        
        const pendingRequests = requests.filter(req => req.status === 'pending');
        const count = pendingRequests.length;
        
        // Update notification badge
        const badge = document.querySelector('.notification-badge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }
        
        // Update notification box content
        const notifBox = document.getElementById('notifBox');
        if (notifBox) {
            if (pendingRequests.length > 0) {
                // Create notification list
                const notificationList = pendingRequests.slice(0, 5).map(request => `
                    <div class="notification-item">
                        <strong>New request by:</strong> ${request.requested_by}<br>
                        <small>Destination: ${request.destination}</small><br>
                        <small>Time: ${getRelativeTime(request.created_at)}</small>
                    </div>
                `).join('');
                
                notifBox.innerHTML = `
                    <div class="notification-header">
                        <strong>${pendingRequests.length} New Request${pendingRequests.length > 1 ? 's' : ''}</strong>
                        <button class="clear-notifications-btn" onclick="clearNotifications()">Clear All</button>
                    </div>
                    ${notificationList}
                    ${pendingRequests.length > 5 ? `<div class="notification-more">...and ${pendingRequests.length - 5} more</div>` : ''}
                `;
            } else {
                notifBox.innerHTML = '<p>No new notifications</p>';
            }
        }
        
        return count;
    } catch (error) {
        console.error('Error checking notifications:', error);
        return 0;
    }
}

// Load vehicle requests from database
async function loadVehicleRequests() {
    const requestList = document.getElementById('vehicleRequestList');
    
    if (!requestList) return;

    // Show loading state
    requestList.innerHTML = `
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
        
        // Check for new requests
        await checkForNewRequests();
        
        // Load pending requests by default (no 'all' option anymore)
        await filterByStatus('pending');
        
    } catch (error) {
        console.error('Error loading requests:', error);
        requestList.innerHTML = `
            <tr>
                <td colspan="7" style="text-align: center; padding: 20px;">
                    <div class="error">
                        <p>Error loading requests. Please refresh the page.</p>
                    </div>
                </td>
            </tr>
        `;
    }
}

// Format date to "March 12, 2026" format
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString; // Return original if invalid
    
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
    const diffInSeconds = Math.floor((now - pastDate) / 1000);
    
    if (diffInSeconds < 60) {
        return diffInSeconds <= 1 ? 'Just now' : `${diffInSeconds} seconds ago`;
    }
    
    const diffInMinutes = Math.floor(diffInSeconds / 60);
    if (diffInMinutes < 60) {
        return diffInMinutes === 1 ? '1 minute ago' : `${diffInMinutes} minutes ago`;
    }
    
    const diffInHours = Math.floor(diffInMinutes / 60);
    if (diffInHours < 24) {
        return diffInHours === 1 ? '1 hour ago' : `${diffInHours} hours ago`;
    }
    
    const diffInDays = Math.floor(diffInHours / 24);
    if (diffInDays < 7) {
        return diffInDays === 1 ? '1 day ago' : `${diffInDays} days ago`;
    }
    
    const diffInWeeks = Math.floor(diffInDays / 7);
    if (diffInWeeks < 4) {
        return diffInWeeks === 1 ? '1 week ago' : `${diffInWeeks} weeks ago`;
    }
    
    const diffInMonths = Math.floor(diffInDays / 30);
    if (diffInMonths < 12) {
        return diffInMonths === 1 ? '1 month ago' : `${diffInMonths} months ago`;
    }
    
    const diffInYears = Math.floor(diffInDays / 365);
    return diffInYears === 1 ? '1 year ago' : `${diffInYears} years ago`;
}

// Toggle details row
function toggleDetails(requestId) {
    const detailsRow = document.getElementById(`details-${requestId}`);
    const mainRow = detailsRow.previousElementSibling;
    
    if (detailsRow.classList.contains('show')) {
        detailsRow.classList.remove('show');
        mainRow.classList.remove('expanded');
    } else {
        // Close other open details rows
        document.querySelectorAll('.details-row.show').forEach(row => {
            row.classList.remove('show');
            row.previousElementSibling.classList.remove('expanded');
        });
        
        // Open this details row
        detailsRow.classList.add('show');
        mainRow.classList.add('expanded');
    }
}

// Format time from 24-hour to 12-hour format
function formatTime(timeString) {
    if (!timeString || timeString === 'Not specified') {
        return 'Not specified';
    }
    
    // Split time to get hours and minutes (ignore seconds)
    const timeParts = timeString.split(':');
    let hours = parseInt(timeParts[0]);
    const minutes = timeParts[1];
    
    // Convert to 12-hour format
    const period = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12; // 0 should be 12
    
    return `${hours}:${minutes} ${period}`;
}

// Filter requests by status
function filterByStatus(status) {
    currentFilter = status;
    const requestList = document.getElementById('vehicleRequestList');
    
    if (!requestList) return;
    
    // Filter requests based on status (no 'all' option anymore)
    let filteredRequests = allRequests.filter(request => request.status === status);
    
    // Apply time filter
    filteredRequests = filterByTime(filteredRequests);
    
    if (filteredRequests.length === 0) {
        requestList.innerHTML = `
            <table class="request-table">
                <thead>
                    <tr>
                        <th>NO#</th>
                        <th>NAMES</th>
                        <th>DESTINATION</th>
                        <th>DATE OF TRAVEL</th>
                        <th>PURPOSE</th>
                        <th>TIME SENT</th>
                        <th>STATUS</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 20px;">
                            <div class="empty-message">
                                <h3>No ${status} requests found</h3>
                                <p>There are no ${status} vehicle requests to display${currentTimeFilter.month || currentTimeFilter.year ? ' for the selected time period' : ''}.</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        `;
        return;
    }
    
    // Generate HTML for filtered requests (same as in loadVehicleRequests)
    const requestsHTML = filteredRequests.map((request, index) => {
        // Calculate relative time
        const timeSent = getRelativeTime(request.created_at);
        
        // Format travel date
        const travelDate = formatDate(request.travel_date);
        
        return `
        <tr class="clickable" onclick="toggleDetails(${request.id})">
            <td>${index + 1}</td>
            <td>${request.requested_by}</td>
            <td>${request.destination}</td>
            <td>${travelDate}</td>
            <td class="purpose-cell">${request.purpose}</td>
            <td>${timeSent}</td>
            <td>
                <span class="status-badge ${request.status}">${request.status}</span>
            </td>
        </tr>
        <tr class="details-row" id="details-${request.id}">
            <td colspan="7">
                <div class="details-content">
                    <div class="details-grid">
                        <div class="detail-item">
                            <span class="detail-label">Requested By:</span>
                            <span class="detail-value">${request.requested_by}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Destination:</span>
                            <span class="detail-value">${request.destination}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Travel Date:</span>
                            <span class="detail-value">${travelDate}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Purpose:</span>
                            <span class="detail-value purpose-field">${request.purpose}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Time Sent:</span>
                            <span class="detail-value">${timeSent}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Status:</span>
                            <span class="detail-value">
                                <span class="status-badge ${request.status}">${request.status}</span>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Request Date:</span>
                            <span class="detail-value">${request.created_at || 'N/A'}</span>
                        </div>
                    </div>
                    <div class="action-buttons">
                        <button class="action-btn print" onclick="printRequest(${request.id})">Print Request</button>
        ${request.status === 'declined' || request.status === 'approved' ? `
            <button class="action-btn edit" onclick="editDecision(${request.id}, '${request.status}')">Edit Decision</button>
                        ` : ''}
                    </div>
                    ${request.status === 'pending' ? `
                        <div class="decision-section">
                            <div class="decision-options">
                                <label class="radio-option">
                                    <input type="radio" name="decision-${request.id}" value="approved" checked onchange="handleDecisionChange(${request.id})">
                                    <span>Approved</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="decision-${request.id}" value="declined" onchange="handleDecisionChange(${request.id})">
                                    <span>Declined</span>
                                </label>
                            </div>
                            <div class="decline-reason-container" id="reason-container-${request.id}" style="display: none;">
                                <label class="reason-label">Due to:</label>
                                <input type="text" 
                                       class="decline-reason" 
                                       id="reason-${request.id}" 
                                       placeholder="Enter reason for declining..."
                                       disabled>
                            </div>
                            <button class="action-btn submit" onclick="submitDecision(${request.id})">Submit Decision</button>
                        </div>
                    ` : ''}
                    ${request.status === 'declined' || request.status === 'approved' ? `
                        <div class="edit-decision-section" id="edit-section-${request.id}" style="display: none;">
                            <div class="decision-options">
                                <label class="radio-option">
                                    <input type="radio" name="edit-decision-${request.id}" value="approved" ${request.status === 'approved' ? 'checked' : ''} onchange="handleEditDecisionChange(${request.id})">
                                    <span>Approved</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="edit-decision-${request.id}" value="declined" ${request.status === 'declined' ? 'checked' : ''} onchange="handleEditDecisionChange(${request.id})">
                                    <span>Declined</span>
                                </label>
                            </div>
                            <div class="decline-reason-container" id="edit-reason-container-${request.id}" ${request.status === 'declined' ? '' : 'style="display: none;"'}>
                                <label class="reason-label">Due to:</label>
                                <input type="text" 
                                       class="decline-reason" 
                                       id="edit-reason-${request.id}" 
                                       placeholder="Enter reason for declining..."
                                       value="${request.notes || ''}">
                            </div>
                            <button class="action-btn submit" onclick="submitEditDecision(${request.id})">Update Decision</button>
                            <button class="action-btn cancel" onclick="cancelEditDecision(${request.id})">Cancel</button>
                        </div>
                    ` : ''}
                </div>
            </td>
        </tr>
    `;
    }).join('');

    requestList.innerHTML = `
        <table class="request-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Requested By</th>
                    <th>Destination</th>
                    <th>Travel Date</th>
                    <th>Purpose</th>
                    <th>Time Sent</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                ${requestsHTML}
            </tbody>
        </table>
    `;
}

// Edit decision for both approved and declined requests
function editDecision(requestId, currentStatus) {
    const editSection = document.getElementById(`edit-section-${requestId}`);
    const editButton = event.target;
    
    // Hide the edit button and show the edit section
    editButton.style.display = 'none';
    editSection.style.display = 'block';
    
    // Initialize the reason container visibility based on current status
    const reasonContainer = document.getElementById(`edit-reason-container-${requestId}`);
    if (currentStatus === 'declined') {
        reasonContainer.style.display = 'flex';
    } else {
        reasonContainer.style.display = 'none';
    }
}

// Handle edit decision change
function handleEditDecisionChange(requestId) {
    const selectedOption = document.querySelector(`input[name="edit-decision-${requestId}"]:checked`);
    const reasonContainer = document.getElementById(`edit-reason-container-${requestId}`);
    const reasonInput = document.getElementById(`edit-reason-${requestId}`);
    
    if (selectedOption && selectedOption.value === 'declined') {
        reasonContainer.style.display = 'flex';
        reasonInput.disabled = false;
        reasonInput.required = true;
    } else {
        reasonContainer.style.display = 'none';
        reasonInput.disabled = true;
        reasonInput.required = false;
    }
}

// Submit edit decision
async function submitEditDecision(requestId) {
    const selectedOption = document.querySelector(`input[name="edit-decision-${requestId}"]:checked`);
    const reasonInput = document.getElementById(`edit-reason-${requestId}`);
    
    if (!selectedOption) {
        poNotify('Please select a decision.', 'Missing Information', 'warning');
        return;
    }
    
    const decision = selectedOption.value;
    let notes = '';
    
    if (decision === 'declined') {
        notes = reasonInput.value.trim();
        if (!notes) {
            poNotify('Please enter a reason for declining.', 'Reason Required', 'warning');
            reasonInput.focus();
            return;
        }
    } else {
        notes = 'Approved by Planning Officer (Updated)';
    }
    
    try {
        const formData = new FormData();
        formData.append('request_id', requestId);
        formData.append('status', decision);
        formData.append('notes', notes);
        
        const response = await fetch('/Prototype/simple_update_status.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            poNotify(`Request ${decision === 'approved' ? 'approved' : 'declined'} successfully!`, 'Request Updated', 'success');
            // Reload requests to show updated status
            await loadVehicleRequests();
            await checkForNewRequests();
        } else {
            poNotify('Error updating request: ' + (result.error || 'Unknown error'), 'Unable to Update Request', 'error');
        }
    } catch (error) {
        console.error('Error submitting decision:', error);
        poNotify('Error submitting decision. Please try again.', 'Something Went Wrong', 'error');
    }
}

// Cancel edit decision
function cancelEditDecision(requestId) {
    const editSection = document.getElementById(`edit-section-${requestId}`);
    const editButton = document.querySelector(`button[onclick*="editDecision(${requestId}"]`);
    
    // Show the edit button and hide the edit section
    editButton.style.display = 'inline-flex';
    editSection.style.display = 'none';
}

// Handle radio button changes
function handleDecisionChange(requestId) {
    const selectedOption = document.querySelector(`input[name="decision-${requestId}"]:checked`);
    const reasonContainer = document.getElementById(`reason-container-${requestId}`);
    const reasonInput = document.getElementById(`reason-${requestId}`);
    
    if (selectedOption && selectedOption.value === 'declined') {
        reasonContainer.style.display = 'block';
        reasonInput.disabled = false;
        reasonInput.required = true;
        reasonInput.focus();
    } else {
        reasonContainer.style.display = 'none';
        reasonInput.disabled = true;
        reasonInput.required = false;
        reasonInput.value = '';
    }
}

// Submit decision
async function submitDecision(requestId) {
    const selectedOption = document.querySelector(`input[name="decision-${requestId}"]:checked`);
    const reasonInput = document.getElementById(`reason-${requestId}`);
    
    if (!selectedOption) {
        poNotify('Please select a decision.', 'Missing Information', 'warning');
        return;
    }
    
    const decision = selectedOption.value;
    let notes = '';
    
    if (decision === 'declined') {
        notes = reasonInput.value.trim();
        if (!notes) {
            poNotify('Please enter a reason for declining.', 'Reason Required', 'warning');
            reasonInput.focus();
            return;
        }
    } else {
        notes = 'Approved by Planning Officer';
    }
    
    try {
        const formData = new FormData();
        formData.append('request_id', requestId);
        formData.append('status', decision);
        formData.append('notes', notes);
        
        const response = await fetch('/Prototype/simple_update_status.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            poNotify(`Request ${decision === 'approved' ? 'approved' : 'declined'} successfully!`, 'Request Updated', 'success');
            // Reload requests to show updated status
            await loadVehicleRequests();
            await checkForNewRequests();
        } else {
            poNotify('Error updating request: ' + (result.error || 'Unknown error'), 'Unable to Update Request', 'error');
        }
    } catch (error) {
        console.error('Error submitting decision:', error);
        poNotify('Error submitting decision. Please try again.', 'Something Went Wrong', 'error');
    }
}

// Approve request (kept for backward compatibility)
async function approveRequest(requestId) {
    try {
        const formData = new FormData();
        formData.append('request_id', requestId);
        formData.append('status', 'approved');
        formData.append('notes', 'Approved by Planning Officer');
        
        const response = await fetch('/Prototype/simple_update_status.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Reload requests to show updated status
            await loadVehicleRequests();
            await checkForNewRequests();
        } else {
            poNotify('Error approving request: ' + (result.error || 'Unknown error'), 'Unable to Approve Request', 'error');
        }
    } catch (error) {
        console.error('Error approving request:', error);
        poNotify('Error approving request. Please try again.', 'Something Went Wrong', 'error');
    }
}

// Decline request
async function declineRequest(requestId) {
    const reason = prompt('Please enter reason for declining:');
    
    if (!reason) {
        return; // User cancelled
    }
    
    try {
        const formData = new FormData();
        formData.append('request_id', requestId);
        formData.append('status', 'declined');
        formData.append('notes', reason);
        
        const response = await fetch('/Prototype/simple_update_status.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Reload requests to show updated status
            await loadVehicleRequests();
            await checkForNewRequests();
        } else {
            poNotify('Error declining request: ' + (result.error || 'Unknown error'), 'Unable to Decline Request', 'error');
        }
    } catch (error) {
        console.error('Error declining request:', error);
        poNotify('Error declining request. Please try again.', 'Something Went Wrong', 'error');
    }
}

// Print request
function printRequest(requestId) {
    // Open print window
    const printWindow = window.open('', '_blank');
    
    // Create print content (simplified version)
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Vehicle Request Print</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                .header { text-align: center; border-bottom: 2px solid #1c6b2f; padding-bottom: 20px; }
                .content { margin: 20px 0; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Cavite State University - Naic</h1>
                <h2>Vehicle Request Details</h2>
            </div>
            <div class="content">
                <p><strong>Request ID:</strong> ${requestId}</p>
                <p><strong>Date:</strong> ${new Date().toLocaleDateString()}</p>
                <hr>
                <p>This is a printed copy of vehicle request #${requestId}</p>
            </div>
            <div class="footer">
                <p>Generated on ${new Date().toLocaleString()}</p>
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.print();
}

// Show contact information
function showContactInfo() {
    poNotify('Contact Information:\n\nEmail: planning@cvsu.edu.ph\nPhone: (046) 414-1234\nOffice: Physical Plant and Security Service\nCavite State University - Naic Campus', 'Contact Information', 'info');
}

// Auto-refresh for new notifications
setInterval(async () => {
    await checkForNewRequests();
}, 30000); // Check every 30 seconds

// Initial load and check
window.addEventListener('load', async () => {
    initializeYearFilter(); // Populate year dropdown
    await loadVehicleRequests();
    await checkForNewRequests();
});
