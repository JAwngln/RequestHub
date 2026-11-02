// Shared Vehicle Request System
// This file manages vehicle requests that can be accessed by both Faculty and Planning Officer

class SharedRequestSystem {
    constructor() {
        this.storageKey = 'vehicleRequests';
        this.initializeStorage();
    }

    // Initialize localStorage if it doesn't exist
    initializeStorage() {
        if (!localStorage.getItem(this.storageKey)) {
            localStorage.setItem(this.storageKey, JSON.stringify([]));
        }
    }

    // Add a new vehicle request
    addRequest(requestData) {
        const requests = this.getAllRequests();
        const newRequest = {
            id: Date.now(), // Unique timestamp ID
            ...requestData,
            status: 'pending', // pending, approved, declined
            createdAt: new Date().toISOString(),
            requestedBy: requestData.requestedBy || 'Unknown Faculty'
        };
        
        requests.push(newRequest);
        this.saveRequests(requests);
        
        console.log('New vehicle request added:', newRequest);
        return newRequest;
    }

    // Get all requests
    getAllRequests() {
        try {
            return JSON.parse(localStorage.getItem(this.storageKey) || '[]');
        } catch (error) {
            console.error('Error reading requests:', error);
            return [];
        }
    }

    // Save requests to localStorage
    saveRequests(requests) {
        localStorage.setItem(this.storageKey, JSON.stringify(requests));
    }

    // Update request status (for Planning Officer)
    updateRequestStatus(requestId, status, notes = '') {
        const requests = this.getAllRequests();
        const requestIndex = requests.findIndex(r => r.id == requestId);
        
        if (requestIndex !== -1) {
            requests[requestIndex].status = status;
            requests[requestIndex].reviewedAt = new Date().toISOString();
            requests[requestIndex].notes = notes;
            this.saveRequests(requests);
            return true;
        }
        return false;
    }

    // Get requests by status
    getRequestsByStatus(status) {
        return this.getAllRequests().filter(request => request.status === status);
    }

    // Delete a request
    deleteRequest(requestId) {
        const requests = this.getAllRequests();
        const filteredRequests = requests.filter(r => r.id != requestId);
        this.saveRequests(filteredRequests);
        return true;
    }

    // Clear all requests (for testing)
    clearAllRequests() {
        localStorage.setItem(this.storageKey, JSON.stringify([]));
    }
}

// Create global instance
window.sharedRequestSystem = new SharedRequestSystem();

// Helper functions for Faculty
window.submitSharedVehicleRequest = function(formData) {
    return window.sharedRequestSystem.addRequest(formData);
};

// Helper functions for Planning Officer
window.getAllVehicleRequests = function() {
    return window.sharedRequestSystem.getAllRequests();
};

window.approveVehicleRequest = function(requestId, notes = '') {
    return window.sharedRequestSystem.updateRequestStatus(requestId, 'approved', notes);
};

window.declineVehicleRequest = function(requestId, notes = '') {
    return window.sharedRequestSystem.updateRequestStatus(requestId, 'declined', notes);
};

console.log('Shared Request System loaded');
