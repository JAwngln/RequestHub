// Simple Dashboard Functions for Planning Officer (for home.html loading)

// Get all vehicle requests from database
window.getAllVehicleRequests = async function() {
    try {
        console.log('Fetching from: /Prototype/simple_get_requests.php');
        const response = await fetch('/Prototype/simple_get_requests.php');
        console.log('Response status:', response.status);
        const requests = await response.json();
        console.log('Loaded requests from database:', requests);
        return requests;
    } catch (error) {
        console.error('Error loading requests:', error);
        return [];
    }
};

// Get statistics from database
window.getVehicleStatistics = async function() {
    try {
        const response = await fetch('/Prototype/simple_get_requests.php');
        const requests = await response.json();
        
        // Calculate statistics
        const stats = {
            pending: 0,
            approved: 0,
            declined: 0
        };
        
        requests.forEach(request => {
            const status = request.status || 'pending';
            if (stats.hasOwnProperty(status)) {
                stats[status]++;
            }
        });
        
        return stats;
    } catch (error) {
        console.error('Error loading statistics:', error);
        return { pending: 0, approved: 0, declined: 0 };
    }
};

// Approve vehicle request
window.approveVehicleRequest = async function(requestId, notes = '') {
    try {
        const formData = new FormData();
        formData.append('request_id', requestId);
        formData.append('status', 'approved');
        formData.append('notes', notes);
        
        const response = await fetch('/Prototype/simple_update_status.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        console.log('Request approved:', result);
        return result;
    } catch (error) {
        console.error('Error approving request:', error);
        return { error: 'Failed to approve request' };
    }
};

// Decline vehicle request
window.declineVehicleRequest = async function(requestId, notes = '') {
    try {
        const formData = new FormData();
        formData.append('request_id', requestId);
        formData.append('status', 'declined'); // Store as 'declined' in database
        formData.append('notes', notes);
        
        const response = await fetch('/Prototype/simple_update_status.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        console.log('Request declined:', result);
        return result;
    } catch (error) {
        console.error('Error declining request:', error);
        return { error: 'Failed to decline request' };
    }
};

console.log('Simple Dashboard System loaded (for home.html)');
