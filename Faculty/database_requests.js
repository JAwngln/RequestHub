// Database Vehicle Request System
// This replaces the localStorage system with database calls

class DatabaseRequestSystem {
    constructor() {
        this.apiEndpoint = 'database_requests.php';
    }

    // Add a new vehicle request
    async addRequest(requestData) {
        try {
            const response = await fetch(`${this.apiEndpoint}?action=add_request`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestData)
            });
            
            const result = await response.json();
            return result;
        } catch (error) {
            console.error('Error submitting request:', error);
            return { error: 'Failed to submit request: ' + error.message };
        }
    }

    // Get all requests
    async getAllRequests() {
        try {
            const response = await fetch(`${this.apiEndpoint}?action=get_all_requests`);
            const requests = await response.json();
            return requests;
        } catch (error) {
            console.error('Error loading requests:', error);
            return [];
        }
    }

    // Get pending requests
    async getPendingRequests() {
        try {
            const response = await fetch(`${this.apiEndpoint}?action=get_pending_requests`);
            const requests = await response.json();
            return requests;
        } catch (error) {
            console.error('Error loading pending requests:', error);
            return [];
        }
    }

    // Get borrowed items
    async getBorrowedItems() {
        try {
            const response = await fetch(`${this.apiEndpoint}?action=get_borrowed_items`);
            const items = await response.json();
            return items;
        } catch (error) {
            console.error('Error loading borrowed items:', error);
            return [];
        }
    }

    // Update request status
    async updateRequestStatus(requestId, status, notes = '') {
        try {
            const formData = new FormData();
            formData.append('request_id', requestId);
            formData.append('status', status);
            formData.append('notes', notes);
            
            const response = await fetch(`${this.apiEndpoint}?action=update_status`, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            return result;
        } catch (error) {
            console.error('Error updating status:', error);
            return { error: 'Failed to update request' };
        }
    }

    // Delete a request
    async deleteRequest(requestId) {
        try {
            const formData = new FormData();
            formData.append('request_id', requestId);
            
            const response = await fetch(`${this.apiEndpoint}?action=delete_request`, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            return result;
        } catch (error) {
            console.error('Error deleting request:', error);
            return { error: 'Failed to delete request' };
        }
    }

    // Get statistics
    async getStatistics() {
        try {
            const response = await fetch(`${this.apiEndpoint}?action=get_statistics`);
            const stats = await response.json();
            return stats;
        } catch (error) {
            console.error('Error loading statistics:', error);
            return { pending: 0, approved: 0, declined: 0 };
        }
    }
}

// Create global instance
window.databaseRequestSystem = new DatabaseRequestSystem();

// Helper functions for Faculty (same interface as before)
window.submitSharedVehicleRequest = async function(formData) {
    return await window.databaseRequestSystem.addRequest(formData);
};

// Helper functions for Planning Officer (same interface as before)
window.getAllVehicleRequests = async function() {
    return await window.databaseRequestSystem.getAllRequests();
};

window.approveVehicleRequest = async function(requestId, notes = '') {
    return await window.databaseRequestSystem.updateRequestStatus(requestId, 'approved', notes);
};

window.declineVehicleRequest = async function(requestId, notes = '') {
    return await window.databaseRequestSystem.updateRequestStatus(requestId, 'declined', notes);
};

window.getVehicleStatistics = async function() {
    return await window.databaseRequestSystem.getStatistics();
};

// Helper function for borrowed items
window.loadBorrowedItems = async function() {
    return await window.databaseRequestSystem.getBorrowedItems();
};

console.log('Database Request System loaded');
