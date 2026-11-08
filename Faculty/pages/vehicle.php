<link href="/Prototype/assets/fonts/material-icons.css" rel="stylesheet">
    <link href="/Prototype/assets/fonts/poppins.css" rel="stylesheet">
    <link rel="stylesheet" href="pages/vehiclebtn.css?v=6">
</head>
<body>

<h1>VEHICLE REQUEST</h1>

<div class="request-layout">

  <!-- LEFT -->
  <div class="form-wrapper">
    <form class="paper-form" id="vehicleForm" enctype="multipart/form-data">
      <div class="line-row">
        <label>Date:</label>
        <input type="date" id="todayDate" value="<?php echo date('Y-m-d'); ?>" step="1" lang="en-US" readonly>
      </div>
      
      <div class="line-row">
        <label>Date of Travel:</label>
        <input type="date" name="travelDate" class="line-input" id="travelDate" step="1" lang="en-US" placeholder="mm/dd/yyyy" min="<?php echo date('Y-m-d'); ?>" required>
      </div>

      <div class="line-row">
        <label>Destination:</label>
        <input type="text" name="destination" class="line-input" id="destination" required>
      </div>

      <div class="line-row">
        <label>Purpose:</label>
        <input type="text" name="purpose" class="line-input" id="purpose" required>
      </div>

      <div class="line-row">
        <label>Passengers Name:</label>
        <input type="text" name="passengers" class="line-input" id="passengers" required>
      </div>

      <div class="line-row">
        <label>Requested by:</label> 
        <input type="text" name="requestedBy" class="line-input" id="requestedBy" required>
      </div>

      <div class="line-row attachment-row">
        <label>Attach File (Authority to Travel)</label>
        <input type="file" name="attachment" id="attachment" accept="image/jpeg,image/jpg,image/png,image/gif" capture="environment" required style="margin-top:5px;">
        <small style="display:block; margin-top:4px; color:#b45309; font-size:12px;">
          Reminder: Picture files only (JPG, JPEG, PNG, GIF). PDF and other document files are not accepted.
        </small>
      </div>

            <div class="actions" style="text-align:center; margin-top:10px;">
                <button type="button" class="submit-btn" onclick="submitVehicleRequest()">SUBMIT</button>
            </div>
        </form>
    </div>

    <!-- REQUEST LIST -->
    <div class="request-list">
        <h2>Requested List</h2>
        <div id="requestListContainer">
            <p class="empty-message">No requests yet</p>
        </div>
    </div>

</div>

<script src="vehicle.js?v=9"></script>

<script>
window.displayRequests = async function() {
    const container = document.getElementById('requestListContainer');
    
    if (!container) {
        console.error("requestListContainer not found");
        return;
    }
    
    try {
        // Get vehicle requests only
        const vehicleResponse = await fetch('/Prototype/database_requests.php?table=vehicle_requests&action=get_all_requests');
        const data = await vehicleResponse.json();
        
        console.log('Vehicle requests API response:', data);
        
        // Handle response structure
        let vehicleRequests = [];
        if (data && data.requests) {
            vehicleRequests = data.requests;
            console.log('Using data.requests:', vehicleRequests);
        } else if (Array.isArray(data)) {
            vehicleRequests = data;
            console.log('Using data as array:', vehicleRequests);
        } else {
            console.log('Unexpected response structure:', data);
        }
        
        let html = '';
        console.log('Vehicle requests length:', vehicleRequests.length);
        
        // Format a date-only string (YYYY-MM-DD) without timezone shifting
        const formatDate = (val) => {
            if (!val) return 'Not specified';
            const parts = String(val).split('T')[0].split('-');
            if (parts.length === 3) {
                const d = new Date(parseInt(parts[0],10), parseInt(parts[1],10) - 1, parseInt(parts[2],10));
                return d.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
            }
            return new Date(val).toLocaleDateString();
        };
        
        const targetId = Number(window.notificationTargetId) || 0;

        if (vehicleRequests.length > 0) {
            vehicleRequests.forEach((request, index) => {
                html += `
                    <div class="request-item" id="vehicle-request-${Number(request.id)}">
                        <h4>Vehicle Request #${String(request.ticket_no).padStart(3, '0')}</h4>
                        <div class="request-details">
                            <p><strong>Destination:</strong> ${request.destination}</p>
                            <p><strong>Date of Travel:</strong> ${formatDate(request.travel_date || request.request_date)}</p>
                            <p><strong>Purpose:</strong> ${request.purpose}</p>
                            <p><strong>Passengers Name:</strong> ${request.passengers}</p>
                            ${request.vehicle_plate ? `<p><strong>Assigned Vehicle:</strong> ${request.vehicle_plate}</p>` : ''}
                            <p><strong>Requested by:</strong> ${request.requested_by}</p>
                            ${request.attachment_name ? `<p><strong>Attachment:</strong> <button type="button" class="view-attachment-btn" data-request-id="${request.id}" data-filename="${request.attachment_name}">View Authority To Travel File</button></p>` : ''}
                            <p><strong>Status:</strong> <span class="status-badge status-${request.status || 'pending'}">${(request.status || 'pending').toUpperCase()}</span></p>
                            ${request.status === 'declined' && request.notes ? `<div class="decline-reason"><strong>Reason for Declining:</strong> ${request.notes}</div>` : ''}
                        </div>
                        <div class="request-actions">
                            <button type="button" class="cancel-btn" onclick="showCancelModal(${request.id})">Cancel Request</button>
                        </div>
                    </div>
                `;
            });
        } else {
            html = '<p class="empty-message">No vehicle requests yet</p>';
        }
        
        container.innerHTML = html;
        if (targetId) {
            const target = document.getElementById(`vehicle-request-${targetId}`);
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
        
    } catch (error) {
        console.error('Error loading requests:', error);
        container.innerHTML = '<p class="empty-message">Error loading requests</p>';
    }
};

window.cancelVehicleRequest = function(requestId) {
    window.showCancelModal(requestId);
};

window.showCancelModal = function(requestId) {
    window.showAppConfirm(
        'Are you sure you want to cancel and remove this vehicle request? This action cannot be undone.',
        'Cancel Request?',
        function () {
            window.confirmVehicleCancel(requestId);
        },
        null,
        { type: 'danger', okText: 'Yes, Cancel', cancelText: 'No, Keep' }
    );
};

window.closeCancelModal = function() {
    if (typeof window.closeAppAlert === 'function') {
        window.closeAppAlert();
    }
};

window.confirmVehicleCancel = async function(requestId) {
    window.closeCancelModal();
    
    try {
        const formData = new FormData();
        formData.append('action', 'cancel');
        formData.append('id', requestId);
        formData.append('table', 'vehicle_requests');
        
        const res = await fetch('/Prototype/database_requests.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        
        if (data.success) {
            window.showAppAlert('The vehicle request has been cancelled and removed.', 'Request Cancelled', null, { type: 'success' });
            window.displayRequests();
        } else {
            window.showAppAlert(data.message || 'Failed to cancel request.', 'Unable to Cancel', null, { type: 'error' });
        }
    } catch (error) {
        console.error('Error cancelling request:', error);
        window.showAppAlert('An error occurred while cancelling the request. Please try again.', 'Something Went Wrong', null, { type: 'error' });
    }
};

window.onload = function() {
    window.displayRequests();
    // Also load requested items to ensure consistency
    if (typeof loadRequestedItems === 'function') {
        loadRequestedItems();
    }
};

// Open the modal with the given URL and title
function openFacultyModal(url, filename) {
    const modal = document.getElementById('facultyAttachmentModal');
    const content = document.getElementById('facultyAttachmentContent');
    const filenameEl = document.getElementById('facultyAttachmentModalFilename');
    
    if (!modal) {
        window.open(url, '_blank');
        return;
    }
    
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
window.facultyViewAttachment = function(requestId, filename) {
    // Attachments live in the database; view_attachment.php streams the stored bytes.
    openFacultyModal('/Prototype/view_attachment.php?id=' + encodeURIComponent(requestId), filename || 'Authority To Travel File');
};

// Close the faculty attachment modal (global)
window.closeFacultyAttachmentModal = function() {
    const modal = document.getElementById('facultyAttachmentModal');
    const content = document.getElementById('facultyAttachmentContent');
    if (modal) {
        modal.classList.remove('show');
        content.innerHTML = '';
    }
};

// Event delegation for faculty attachment view buttons
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.view-attachment-btn');
    if (btn && btn.dataset.requestId) {
        window.facultyViewAttachment(btn.dataset.requestId, btn.dataset.filename);
    }
});
</script>

<!-- Attachment Viewer Modal -->
<div class="attachment-modal" id="facultyAttachmentModal" onclick="if(event.target === this) closeFacultyAttachmentModal()">
    <div class="attachment-modal-content">
        <button class="attachment-modal-close" onclick="closeFacultyAttachmentModal()">&times;</button>
        <div class="modal-filename" id="facultyAttachmentModalFilename"></div>
        <div id="facultyAttachmentContent"></div>
    </div>
</div>

<?php include __DIR__ . '/../includes/custom_alert_modal.php'; ?>
