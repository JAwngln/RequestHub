// Show a styled modal when available, fall back to a native alert
function vehicleNotify(message, title, type) {
    if (typeof window.showAppAlert === 'function') {
        window.showAppAlert(message, title, null, { type: type || 'info' });
    } else {
        alert(message);
    }
}

// Show the list of things the user must fix, both as a summary and under the
// affected fields. Falls back to the styled modal on older shells.
function reportVehicleErrors(errors) {
    if (typeof window.showFormErrors === 'function') {
        window.showFormErrors('vehicleForm', errors, {
            heading: 'Please correct the following before submitting your vehicle request:'
        });
        return;
    }

    vehicleNotify(errors.map(function (item) { return item.message; }).join('\n'), 'Please Check the Form', 'warning');
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

    if (typeof window.clearFormErrors === 'function') {
        window.clearFormErrors('vehicleForm');
    }
    
    // Get form values directly
    const travelDate = toDateOnly(document.getElementById('travelDate').value);
    const destination = document.getElementById('destination').value.trim();
    const purpose = document.getElementById('purpose').value.trim();
    const passengers = document.getElementById('passengers').value.trim();
    const requestedBy = document.getElementById('requestedBy').value.trim();
    
    console.log('Form values:', {
        travelDate,
        destination,
        purpose,
        passengers,
        requestedBy
    });
    
    // Collect every problem at once so the user can fix the whole form in one
    // pass instead of discovering one missing field at a time.
    const errors = [];
    if (!travelDate) {
        errors.push({ field: 'travelDate', message: 'Select the date of travel.' });
    } else if (travelDate < todayLocalDate()) {
        errors.push({ field: 'travelDate', message: 'The date of travel cannot be in the past. Choose today or a future date.' });
    }
    if (!destination) {
        errors.push({ field: 'destination', message: 'Enter where the vehicle is going (destination).' });
    }
    if (!purpose) {
        errors.push({ field: 'purpose', message: 'Enter the purpose of the trip.' });
    }
    if (!passengers) {
        errors.push({ field: 'passengers', message: 'Enter the name(s) of the passengers.' });
    }
    if (!requestedBy) {
        errors.push({ field: 'requestedBy', message: 'Enter the name of the person requesting the vehicle.' });
    }

    if (errors.length > 0) {
        reportVehicleErrors(errors);
        return;
    }
    
    // Validate attachment file
    const attachment = document.getElementById('attachment');
    if (!attachment || !attachment.files || attachment.files.length === 0) {
        reportVehicleErrors([{
            field: 'attachment',
            message: 'Attach a photo of the Authority to Travel (JPG, JPEG, PNG, or GIF).'
        }]);
        return;
    }

    // Picture files only (JPG, JPEG, PNG, GIF)
    const allowedImageTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    const allowedImageExts = ['jpg', 'jpeg', 'png', 'gif'];
    const attachmentFile = attachment.files[0];
    const attachmentExt = (attachmentFile.name.split('.').pop() || '').toLowerCase();
    if (!allowedImageTypes.includes(attachmentFile.type) && !allowedImageExts.includes(attachmentExt)) {
        attachment.value = '';
        reportVehicleErrors([{
            field: 'attachment',
            message: 'Only picture files are accepted (JPG, JPEG, PNG, or GIF). Attach an image of the Authority to Travel.'
        }]);
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
