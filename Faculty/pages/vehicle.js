// Show a styled modal when available, fall back to a native alert
function vehicleNotify(message, title, type) {
    if (typeof window.showAppAlert === 'function') {
        window.showAppAlert(message, title, null, { type: type || 'info' });
    } else {
        alert(message);
    }
}

// Normalize a date or datetime value to a calendar date (YYYY-MM-DD).
function toDateOnly(value) {
    const raw = String(value || '').trim();
    const match = raw.match(/^(\d{4}-\d{2}-\d{2})/);
    return match ? match[1] : '';
}

// Build today's date from local calendar parts so UTC conversion cannot shift
// the picked day to yesterday/tomorrow.
function todayLocalDate() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// Submit vehicle request function - clean version - v3
window.submitVehicleRequest = async function() {
    console.log('Vehicle.js submitVehicleRequest called - v3');
    
    // Get form values directly
    const travelDate = toDateOnly(document.getElementById('travelDate').value);
    const destination = document.getElementById('destination').value;
    const purpose = document.getElementById('purpose').value;
    const passengers = document.getElementById('passengers').value;
    const requestedBy = document.getElementById('requestedBy').value;
    
    console.log('Form values:', {
        travelDate,
        destination,
        purpose,
        passengers,
        requestedBy
    });
    
    // Validate required fields
    if (!travelDate || !destination || !purpose || !passengers || !requestedBy) {
        let missingFields = [];
        if (!travelDate) missingFields.push('Travel Date');
        if (!destination) missingFields.push('Destination');
        if (!purpose) missingFields.push('Purpose');
        if (!passengers) missingFields.push('Passengers');
        if (!requestedBy) missingFields.push('Requested By');
        
        vehicleNotify('Please fill in all required fields: ' + missingFields.join(', '), 'Missing Information', 'warning');
        return;
    }
    
    // Validate attachment file
    const attachment = document.getElementById('attachment');
    if (!attachment || !attachment.files || attachment.files.length === 0) {
        vehicleNotify('Please attach the Authority To Travel File before submitting.', 'Attachment Required', 'warning');
        return;
    }
    
    // Show loading state
    const submitBtn = document.querySelector('button[onclick="submitVehicleRequest()"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = 'Submitting...';
    submitBtn.disabled = true;
    
    try {
        // Simple form data submission
        const formData = new FormData();
        formData.append('travelDate', travelDate);
        formData.append('destination', destination);
        formData.append('purpose', purpose);
        formData.append('passengers', passengers);
        formData.append('requestedBy', requestedBy);
        formData.append('attachment', attachment.files[0]);
        
        const response = await fetch('../simple_submit.php', {
            method: 'POST',
            body: formData
        });
        
        // Check if response is ok before parsing JSON
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
            vehicleNotify('Your vehicle request has been submitted successfully.', 'Request Submitted', 'success');
            
            // Clear form
            document.getElementById('travelDate').value = '';
            document.getElementById('destination').value = '';
            document.getElementById('purpose').value = '';
            document.getElementById('passengers').value = '';
            document.getElementById('requestedBy').value = '';
            document.getElementById('attachment').value = '';
            
            // Re-set today's date
            const todayDate = document.getElementById('todayDate');
            const today = todayLocalDate();
            if (todayDate) {
                todayDate.value = today;
            }
            
            // Refresh the request list
            if (typeof window.displayRequests === 'function') {
                await window.displayRequests();
            }
        } else {
            vehicleNotify('Error: ' + (result.error || 'Unknown error occurred'), 'Submission Failed', 'error');
        }
    } catch (error) {
        console.error('Error submitting request:', error);
        vehicleNotify('Error submitting request. Please try again.', 'Something Went Wrong', 'error');
    } finally {
        // Restore button state
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
};

// Set today's date when page loads
window.onload = function() {
    const todayDate = document.getElementById('todayDate');
    if (todayDate) {
        const today = todayLocalDate();
        todayDate.value = today;
        todayDate.readOnly = true;
    }
    
    // Load requests
    if (typeof window.displayRequests === 'function') {
        window.displayRequests();
    }
};

console.log("Vehicle.js loaded successfully");
