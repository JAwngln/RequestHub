<?php
session_start();
include 'connect.php';

// Clear any output buffers to prevent HTML mixing with JSON
if (ob_get_length()) ob_clean();

class DatabaseRequestSystem {
    private $conn;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
        $this->createTableIfNotExists();

        // Fleet + driver tables and the vehicle <-> driver link live in one shared
        // include so the fleet page and this API always agree on the structure.
        require_once __DIR__ . '/PLANNING OFFICER/includes/fleet_schema.php';
        ensureFleetSchema($this->conn);
    }
    
    // Create vehicle_requests, requested_items, and notifications tables if they don't exist
    private function createTableIfNotExists() {
        $sql = "CREATE TABLE IF NOT EXISTS vehicle_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_no INT NULL DEFAULT NULL,
            ticket_year SMALLINT NULL DEFAULT NULL,
            travel_date DATE NOT NULL,
            destination VARCHAR(255) NOT NULL,
            purpose TEXT NOT NULL,
            passengers VARCHAR(50) NOT NULL,
            driver VARCHAR(100) NULL DEFAULT NULL,
            requested_by VARCHAR(100) NOT NULL,
            request_date DATETIME NOT NULL,
            status ENUM('pending', 'approved', 'declined') DEFAULT 'pending',
            reviewed_at DATETIME NULL,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_ticket (ticket_year, ticket_no)
        )";
        
        if (!$this->conn->query($sql)) {
            die("Error creating table: " . $this->conn->error);
        }
        
        // Migrate older installs that used the 'rejected' status name to 'declined'
        $this->conn->query("UPDATE vehicle_requests SET status = 'declined' WHERE status = 'rejected'");
        $statusCol = $this->conn->query("SHOW COLUMNS FROM vehicle_requests LIKE 'status'");
        if ($statusCol && ($statusRow = $statusCol->fetch_assoc()) && strpos($statusRow['Type'], "'rejected'") !== false) {
            $this->conn->query("ALTER TABLE vehicle_requests MODIFY status ENUM('pending', 'approved', 'declined') DEFAULT 'pending'");
        }
        
        // Add user_email column to vehicle_requests if it doesn't exist
        $checkCol = $this->conn->query("SHOW COLUMNS FROM vehicle_requests LIKE 'user_email'");
        if ($checkCol && $checkCol->num_rows === 0) {
            $this->conn->query("ALTER TABLE vehicle_requests ADD COLUMN user_email VARCHAR(255) NULL AFTER requested_by");
        }

        // Optional preferred fleet vehicle for a faculty request.
        $checkVehicle = $this->conn->query("SHOW COLUMNS FROM vehicle_requests LIKE 'vehicle_id'");
        if ($checkVehicle && $checkVehicle->num_rows === 0) {
            $this->conn->query("ALTER TABLE vehicle_requests ADD COLUMN vehicle_id INT NULL AFTER passengers");
        }
        
        $sql = "CREATE TABLE IF NOT EXISTS requested_items (
            id INT(11) NOT NULL AUTO_INCREMENT,
            names VARCHAR(255) NOT NULL,
            items VARCHAR(255) NOT NULL,
            quantity INT(11) NOT NULL,
            reason TEXT NOT NULL,
            when_needed DATE NULL,
            action VARCHAR(50) DEFAULT 'pending',
            request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status_updated_date TIMESTAMP NULL DEFAULT NULL,
            updated_by VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY (id)
        )";
        
        if (!$this->conn->query($sql)) {
            die("Error creating table: " . $this->conn->error);
        }
        
        // Migrate older installs that used the 'rejected' action name to 'declined'
        $this->conn->query("UPDATE requested_items SET action = 'declined' WHERE action = 'rejected'");
        $actionCol = $this->conn->query("SHOW COLUMNS FROM requested_items LIKE 'action'");
        if ($actionCol && ($actionRow = $actionCol->fetch_assoc()) && strpos($actionRow['Type'], "'rejected'") !== false) {
            $this->conn->query("ALTER TABLE requested_items MODIFY action ENUM('pending', 'approved', 'declined') DEFAULT 'pending'");
        }
        
        $sql = "CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type ENUM('vehicle_request', 'item_request', 'account_update', 'system') NOT NULL,
            recipient_role ENUM('faculty', 'planning_officer', 'all') NOT NULL,
            sender_id INT NULL,
            sender_name VARCHAR(255) NULL,
            related_id INT NULL,
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            read_at TIMESTAMP NULL
        )";
        
        if (!$this->conn->query($sql)) {
            die("Error creating notifications table: " . $this->conn->error);
        }
        
        // Add user_email column to requested_items if it doesn't exist
        $checkCol2 = $this->conn->query("SHOW COLUMNS FROM requested_items LIKE 'user_email'");
        if ($checkCol2 && $checkCol2->num_rows === 0) {
            $this->conn->query("ALTER TABLE requested_items ADD COLUMN user_email VARCHAR(255) NULL AFTER names");
        }
        
        // Add recipient_email column to notifications if it doesn't exist
        $checkCol3 = $this->conn->query("SHOW COLUMNS FROM notifications LIKE 'recipient_email'");
        if ($checkCol3 && $checkCol3->num_rows === 0) {
            $this->conn->query("ALTER TABLE notifications ADD COLUMN recipient_email VARCHAR(255) NULL AFTER recipient_role");
        }

        // Create badge_seen table to track when user last viewed sections
        $sqlBadgeSeen = "CREATE TABLE IF NOT EXISTS badge_seen (
            id INT AUTO_INCREMENT PRIMARY KEY,
            section VARCHAR(50) NOT NULL UNIQUE,
            seen_at DATETIME NOT NULL
        )";
        $this->conn->query($sqlBadgeSeen);
        
        // Create calendar_events table if it doesn't exist
        $createTableSQL = "CREATE TABLE IF NOT EXISTS calendar_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            event_date DATE NOT NULL,
            event_time TIME,
            priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
            created_by VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $this->conn->query($createTableSQL);

        // Create vehicle_fleet table if it doesn't exist
        $createVehicleFleetSQL = "CREATE TABLE IF NOT EXISTS vehicle_fleet (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vehicle_name VARCHAR(100) NULL,
            vehicle_type VARCHAR(50) NOT NULL,
            plate_number VARCHAR(20) NOT NULL UNIQUE,
            capacity INT NOT NULL DEFAULT 1,
            model VARCHAR(100),
            year_manufactured INT,
            status ENUM('available', 'maintenance', 'retired') DEFAULT 'available',
            fuel_type VARCHAR(50),
            insurance_expiry DATE,
            last_maintenance DATE,
            created_by VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $this->conn->query($createVehicleFleetSQL);

        // Older versions created vehicle_name as NOT NULL without a default, which made inserts fail
        $vehicleNameColumn = $this->conn->query("SHOW COLUMNS FROM vehicle_fleet LIKE 'vehicle_name'");
        if ($vehicleNameColumn && ($nameRow = $vehicleNameColumn->fetch_assoc()) && $nameRow['Null'] === 'NO') {
            $this->conn->query("ALTER TABLE vehicle_fleet MODIFY vehicle_name VARCHAR(100) NULL");
        }
    }
    
    // Create notification for new requests
    public function createNotification($title, $message, $type, $recipientRole, $senderName = null, $relatedId = null, $recipientEmail = null) {
        $sql = "INSERT INTO notifications (title, message, type, recipient_role, sender_name, related_id, recipient_email) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sssssis", $title, $message, $type, $recipientRole, $senderName, $relatedId, $recipientEmail);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    
    // Get notifications for a specific role, filtered by recipient_email for faculty
    public function getNotifications($role) {
        $sessionRole = trim($_SESSION['role'] ?? '');
        if (!in_array($sessionRole, ['faculty', 'planning_officer'], true)) {
            return ['notifications' => [], 'unread_count' => 0];
        }
        $role = $sessionRole;
        $userEmail = $_SESSION['email'] ?? '';
        
        if ($role === 'faculty') {
            if ($userEmail === '') {
                return ['notifications' => [], 'unread_count' => 0];
            }
            $sql = "SELECT * FROM notifications
                    WHERE recipient_email = ? OR recipient_role = 'all'
                    ORDER BY created_at DESC LIMIT 10";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("s", $userEmail);
        } else {
            $sql = "SELECT * FROM notifications WHERE recipient_role = ? OR recipient_role = 'all' ORDER BY created_at DESC LIMIT 10";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("s", $role);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $row['time_ago'] = $this->timeAgo($row['created_at']);
            // Expose the deep-link fields the dashboards use to route a click.
            // related_id is the database id of the vehicle/item request.
            $row['request_id'] = isset($row['related_id']) ? (int)$row['related_id'] : null;
            $row['request_type'] = $row['type'] ?? null;
            $notifications[] = $row;
        }
        $stmt->close();
        return ['notifications' => $notifications, 'unread_count' => $this->getUnreadCount($role)];
    }
    
    // Count unread notifications for a specific role, filtered by recipient_email for faculty
    public function getUnreadCount($role) {
        $sessionRole = trim($_SESSION['role'] ?? '');
        if (!in_array($sessionRole, ['faculty', 'planning_officer'], true)) {
            return 0;
        }
        $role = $sessionRole;
        $userEmail = $_SESSION['email'] ?? '';
        
        if ($role === 'faculty') {
            if ($userEmail === '') {
                return 0;
            }
            $sql = "SELECT COUNT(*) as count FROM notifications
                    WHERE (recipient_email = ? OR recipient_role = 'all') AND is_read = FALSE";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("s", $userEmail);
        } else {
            $sql = "SELECT COUNT(*) as count FROM notifications WHERE (recipient_role = ? OR recipient_role = 'all') AND is_read = FALSE";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("s", $role);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return (int)($row['count'] ?? 0);
    }
    
    // Convert a timestamp to a human readable "x ago" string
    private function timeAgo($timestamp) {
        $date = new DateTime($timestamp);
        $now = new DateTime();
        $diff = $now->diff($date);
        
        if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
        if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
        if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
        if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        if ($diff->i > 0) return $diff->i . ' min' . ($diff->i > 1 ? 's' : '') . ' ago';
        return 'Just now';
    }
    
    // Mark notification as read
    public function markNotificationAsRead($notificationId) {
        $notificationId = (int)$notificationId;
        if ($notificationId <= 0) {
            return false;
        }

        $role = trim($_SESSION['role'] ?? '');
        if (!in_array($role, ['faculty', 'planning_officer'], true)) {
            return false;
        }

        if ($role === 'faculty') {
            $userEmail = $_SESSION['email'] ?? '';
            if ($userEmail === '') {
                return false;
            }
            $sql = "UPDATE notifications SET is_read = TRUE, read_at = COALESCE(read_at, NOW())
                    WHERE id = ? AND ((recipient_role = 'faculty' AND recipient_email = ?)
                                      OR recipient_role = 'all')";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("is", $notificationId, $userEmail);
        } else {
            $sql = "UPDATE notifications SET is_read = TRUE, read_at = COALESCE(read_at, NOW())
                    WHERE id = ? AND (recipient_role = ? OR recipient_role = 'all')";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("is", $notificationId, $role);
        }
        $success = $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $stmt->close();

        if ($success && $affectedRows > 0) {
            return true;
        }

        // The notification may already have been marked read by another tab.
        // Treat that as success, but only when it belongs to this user.
        if (!$success) {
            return false;
        }

        if ($role === 'faculty') {
            $checkSql = "SELECT id FROM notifications
                         WHERE id = ? AND ((recipient_role = 'faculty' AND recipient_email = ?)
                                           OR recipient_role = 'all')
                         LIMIT 1";
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->bind_param("is", $notificationId, $userEmail);
        } else {
            $checkSql = "SELECT id FROM notifications
                         WHERE id = ? AND (recipient_role = ? OR recipient_role = 'all')
                         LIMIT 1";
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->bind_param("is", $notificationId, $role);
        }
        $checkStmt->execute();
        $exists = (bool)$checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        return $exists;
    }
    
    // Clear all notifications for a specific role, filtered by recipient_email for faculty
    public function clearAllNotifications($role) {
        $role = trim($_SESSION['role'] ?? '');
        if (!in_array($role, ['faculty', 'planning_officer'], true)) {
            return false;
        }
        $userEmail = $_SESSION['email'] ?? '';
        
        if ($role === 'faculty') {
            if ($userEmail === '') {
                return false;
            }
            $sql = "UPDATE notifications SET is_read = TRUE, read_at = NOW()
                    WHERE (recipient_email = ? OR recipient_role = 'all') AND is_read = FALSE";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("s", $userEmail);
        } else {
            $sql = "UPDATE notifications SET is_read = TRUE, read_at = NOW() WHERE (recipient_role = ? OR recipient_role = 'all') AND is_read = FALSE";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("s", $role);
        }
        $success = $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        return $success && $affectedRows > 0;
    }
    
    // ------------------------------------------------------------------
    // Vehicle fleet + driver management
    // ------------------------------------------------------------------

    /**
     * Plates are compared without spaces and case-insensitively, so
     * "ABC 1234" and "abc1234" can never both be saved.
     */
    private function plateNeedle(string $value): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim($value)) ?? '');
    }

    private function licenseNeedle(string $value): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim($value)) ?? '');
    }

    /** Validate a plate number and return an error message, or null when valid. */
    private function validatePlateNumber(string $plate): ?string
    {
        $needle = $this->plateNeedle($plate);
        if (strlen($needle) < 4 || strlen($needle) > 12) {
            return 'Plate number must contain 4-12 letters and numbers (for example, ABC 1234).';
        }
        if (!preg_match('/[A-Z]/', $needle) || !preg_match('/[0-9]/', $needle)) {
            return 'Plate number must include at least one letter and one digit.';
        }
        if (!preg_match('/^[A-Za-z0-9 .-]+$/', $plate)) {
            return 'Plate number contains characters that are not allowed. Use letters, numbers, spaces, periods, or hyphens.';
        }
        return null;
    }

    /** Validate a driver name and return an error message, or null when valid. */
    private function validateDriverName(string $name): ?string
    {
        $length = mb_strlen($name);
        if ($length < 2 || $length > 100) {
            return 'Driver name must be between 2 and 100 characters.';
        }
        if (!preg_match('/^[\p{L}.\' -]+$/u', $name)) {
            return 'Driver name can only contain letters, spaces, periods, hyphens, and apostrophes.';
        }
        return null;
    }

    /** Validate a license number and return an error message, or null when valid. */
    private function validateLicenseNumber(string $license): ?string
    {
        $needle = $this->licenseNeedle($license);
        if (strlen($needle) < 5 || strlen($needle) > 25) {
            return 'License number must contain 5-25 letters and numbers.';
        }
        if (!preg_match('/[0-9]/', $needle)) {
            return 'License number must include at least one digit.';
        }
        if (!preg_match('/^[A-Za-z0-9 .-]+$/', $license)) {
            return 'License number contains characters that are not allowed. Use letters, numbers, spaces, periods, or hyphens.';
        }
        return null;
    }

    /** Validate an optional contact number and return an error message, or null. */
    private function validateContactNumber(string $contact): ?string
    {
        if ($contact === '') {
            return null;
        }
        if (!preg_match('/^[0-9+()\-\s.]+$/', $contact)) {
            return 'Contact number may only contain digits, spaces, +, -, or parentheses.';
        }
        $digits = preg_replace('/\D/', '', $contact) ?? '';
        if (strlen($digits) < 7 || strlen($digits) > 15) {
            return 'Contact number must contain between 7 and 15 digits.';
        }
        return null;
    }

    public function plateNumberExists($plate, $excludeId = 0)
    {
        $needle = $this->plateNeedle((string)$plate);
        if ($needle === '') {
            return false;
        }
        $sql = "SELECT id FROM vehicle_fleet
                WHERE REPLACE(UPPER(plate_number), ' ', '') = ?";
        if ((int)$excludeId > 0) {
            $sql .= " AND id <> ?";
        }
        $sql .= " LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return false;
        }
        if ((int)$excludeId > 0) {
            $exclude = (int)$excludeId;
            $stmt->bind_param("si", $needle, $exclude);
        } else {
            $stmt->bind_param("s", $needle);
        }
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function licenseNumberExists($license, $excludeId = 0)
    {
        $needle = $this->licenseNeedle((string)$license);
        if ($needle === '') {
            return false;
        }
        $sql = "SELECT id FROM drivers
                WHERE REPLACE(UPPER(license_number), ' ', '') = ?";
        if ((int)$excludeId > 0) {
            $sql .= " AND id <> ?";
        }
        $sql .= " LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return false;
        }
        if ((int)$excludeId > 0) {
            $exclude = (int)$excludeId;
            $stmt->bind_param("si", $needle, $exclude);
        } else {
            $stmt->bind_param("s", $needle);
        }
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function getDrivers($activeOnly = false)
    {
        $sql = "SELECT * FROM drivers";
        if ($activeOnly) {
            $sql .= " WHERE status = 'active'";
        }
        $sql .= " ORDER BY full_name";
        $result = $this->conn->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function getDriverById($driverId)
    {
        $driverId = (int)$driverId;
        if ($driverId <= 0) {
            return null;
        }
        $stmt = $this->conn->prepare("SELECT * FROM drivers WHERE id = ?");
        $stmt->bind_param("i", $driverId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Create or update a driver.
     *
     * Returns a structured result so the UI can put the error next to the field
     * that caused it instead of showing a generic failure.
     */
    public function saveDriver($data)
    {
        $id = (int)($data['id'] ?? 0);
        $name = trim((string)($data['full_name'] ?? ''));
        $license = trim((string)($data['license_number'] ?? ''));
        $contact = trim((string)($data['contact_number'] ?? ''));
        $status = (($data['status'] ?? 'active') === 'inactive') ? 'inactive' : 'active';

        if ($name === '') {
            return ['success' => false, 'error' => 'Driver name is required.', 'field' => 'full_name'];
        }
        if ($nameError = $this->validateDriverName($name)) {
            return ['success' => false, 'error' => $nameError, 'field' => 'full_name'];
        }
        if ($license === '') {
            return ['success' => false, 'error' => 'Driver license number is required.', 'field' => 'license_number'];
        }
        if ($licenseError = $this->validateLicenseNumber($license)) {
            return ['success' => false, 'error' => $licenseError, 'field' => 'license_number'];
        }
        if ($contactError = $this->validateContactNumber($contact)) {
            return ['success' => false, 'error' => $contactError, 'field' => 'contact_number'];
        }
        if ($this->licenseNumberExists($license, $id)) {
            return ['success' => false, 'error' => 'That license number is already registered to another driver.', 'field' => 'license_number'];
        }

        if ($id > 0) {
            $stmt = $this->conn->prepare("UPDATE drivers
                                          SET full_name = ?, license_number = ?, contact_number = ?, status = ?
                                          WHERE id = ?");
            $stmt->bind_param("ssssi", $name, $license, $contact, $status, $id);
            if (!$stmt->execute()) {
                $error = $stmt->error;
                $stmt->close();
                return ['success' => false, 'error' => 'Could not update the driver: ' . $error];
            }
            $stmt->close();
        } else {
            $createdBy = $_SESSION['email'] ?? '';
            $stmt = $this->conn->prepare("INSERT INTO drivers
                                          (full_name, license_number, contact_number, status, created_by)
                                          VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $license, $contact, $status, $createdBy);
            if (!$stmt->execute()) {
                $error = $stmt->error;
                $stmt->close();
                return ['success' => false, 'error' => 'Could not add the driver: ' . $error];
            }
            $id = (int)$stmt->insert_id;
            $stmt->close();
        }

        return [
            'success' => true,
            'id' => $id,
            'driver' => $this->getDriverById($id),
            'message' => 'Driver saved successfully.',
        ];
    }

    /**
     * Remove a driver. Any vehicle they were assigned to is unassigned in the
     * same transaction, so no row is ever left pointing at a missing driver.
     */
    public function deleteDriver($driverId)
    {
        $driverId = (int)$driverId;
        if ($driverId <= 0) {
            return false;
        }

        $this->conn->begin_transaction();
        try {
            $stmt = $this->conn->prepare("UPDATE vehicle_fleet
                                          SET driver_id = NULL, updated_at = NOW()
                                          WHERE driver_id = ?");
            $stmt->bind_param("i", $driverId);
            $stmt->execute();
            $stmt->close();

            $stmt = $this->conn->prepare("DELETE FROM drivers WHERE id = ?");
            $stmt->bind_param("i", $driverId);
            $stmt->execute();
            $deleted = $stmt->affected_rows > 0;
            $stmt->close();

            $this->conn->commit();
            return $deleted;
        } catch (Throwable $e) {
            $this->conn->rollback();
            error_log('deleteDriver failed: ' . $e->getMessage());
            return false;
        }
    }

    private function clearVehicleDriver($vehicleId)
    {
        $vehicleId = (int)$vehicleId;
        $stmt = $this->conn->prepare("UPDATE vehicle_fleet
                                      SET driver_id = NULL, updated_at = NOW()
                                      WHERE id = ?");
        $stmt->bind_param("i", $vehicleId);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Attach $driverId to $vehicleId.
     *
     * If the driver currently belongs to another vehicle, this is a move: the old
     * vehicle is cleared first so the UNIQUE(driver_id) index always holds and the
     * driver is never reported as driving two vehicles.
     */
    private function linkDriverToVehicle($vehicleId, $driverId)
    {
        $vehicleId = (int)$vehicleId;
        $driverId = (int)$driverId;

        $stmt = $this->conn->prepare("UPDATE vehicle_fleet
                                      SET driver_id = NULL, updated_at = NOW()
                                      WHERE driver_id = ? AND id <> ?");
        $stmt->bind_param("ii", $driverId, $vehicleId);
        $stmt->execute();
        $stmt->close();

        $stmt = $this->conn->prepare("UPDATE vehicle_fleet
                                      SET driver_id = ?, updated_at = NOW()
                                      WHERE id = ?");
        $stmt->bind_param("ii", $driverId, $vehicleId);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Add or edit a fleet vehicle and, optionally, its driver.
     *
     * $data['driver_mode'] is one of:
     *   none     - save the vehicle without a driver
     *   existing - attach $data['driver_id']
     *   new      - create a driver from driver_full_name / driver_license / driver_contact
     */
    public function saveFleetVehicle($data)
    {
        $id = (int)($data['id'] ?? 0);
        $plate = trim((string)($data['plate_number'] ?? ''));
        $type = trim((string)($data['vehicle_type'] ?? ''));
        $capacity = (int)($data['capacity'] ?? 0);
        $model = trim((string)($data['model'] ?? ''));
        $vehicleName = trim((string)($data['vehicle_name'] ?? ''));
        $year = (int)($data['year_manufactured'] ?? 0);
        $fuel = trim((string)($data['fuel_type'] ?? ''));
        $status = (string)($data['status'] ?? 'available');
        $driverMode = (string)($data['driver_mode'] ?? 'none');

        if (!in_array($status, ['available', 'maintenance', 'retired'], true)) {
            $status = 'available';
        }

        if ($plate === '') {
            return ['success' => false, 'error' => 'Plate number is required.', 'field' => 'plate_number'];
        }
        if ($plateError = $this->validatePlateNumber($plate)) {
            return ['success' => false, 'error' => $plateError, 'field' => 'plate_number'];
        }
        if ($type === '') {
            return ['success' => false, 'error' => 'Vehicle type is required.', 'field' => 'vehicle_type'];
        }
        if ($capacity < 1 || $capacity > 80) {
            return ['success' => false, 'error' => 'Capacity must be between 1 and 80 passengers.', 'field' => 'capacity'];
        }
        if ($year !== 0 && ($year < 1900 || $year > (int)date('Y') + 1)) {
            return ['success' => false, 'error' => 'Year manufactured must be a valid year up to next year.', 'field' => 'year_manufactured'];
        }
        if ($this->plateNumberExists($plate, $id)) {
            return ['success' => false, 'error' => 'That plate number is already registered in the fleet.', 'field' => 'plate_number'];
        }

        $yearValue = $year > 0 ? $year : null;
        $driverId = null;

        $this->conn->begin_transaction();
        try {
            if ($driverMode === 'new') {
                $driverResult = $this->saveDriver([
                    'full_name' => $data['driver_full_name'] ?? '',
                    'license_number' => $data['driver_license'] ?? '',
                    'contact_number' => $data['driver_contact'] ?? '',
                    'status' => 'active',
                ]);
                if (empty($driverResult['success'])) {
                    $this->conn->rollback();
                    return $driverResult;
                }
                $driverId = (int)$driverResult['id'];
            } elseif ($driverMode === 'existing') {
                $driverId = (int)($data['driver_id'] ?? 0);
                if ($driverId <= 0) {
                    $this->conn->rollback();
                    return ['success' => false, 'error' => 'Select a driver or choose "No driver".', 'field' => 'driver_id'];
                }
                if (!$this->getDriverById($driverId)) {
                    $this->conn->rollback();
                    return ['success' => false, 'error' => 'The selected driver no longer exists.', 'field' => 'driver_id'];
                }
            }

            if ($id > 0) {
                $stmt = $this->conn->prepare("UPDATE vehicle_fleet
                                              SET vehicle_name = ?, vehicle_type = ?, plate_number = ?,
                                                  capacity = ?, model = ?, year_manufactured = ?,
                                                  fuel_type = ?, status = ?
                                              WHERE id = ?");
                $stmt->bind_param("sssisissi", $vehicleName, $type, $plate, $capacity, $model, $yearValue, $fuel, $status, $id);
            } else {
                $createdBy = $_SESSION['email'] ?? '';
                $stmt = $this->conn->prepare("INSERT INTO vehicle_fleet
                                              (vehicle_name, vehicle_type, plate_number, capacity, model,
                                               year_manufactured, fuel_type, status, created_by)
                                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssisisss", $vehicleName, $type, $plate, $capacity, $model, $yearValue, $fuel, $status, $createdBy);
            }

            if (!$stmt->execute()) {
                throw new RuntimeException($stmt->error);
            }
            $vehicleId = $id > 0 ? $id : (int)$this->conn->insert_id;
            $stmt->close();

            if ($driverMode === 'none') {
                $this->clearVehicleDriver($vehicleId);
            } else {
                $this->linkDriverToVehicle($vehicleId, $driverId);
            }

            $this->conn->commit();
            return [
                'success' => true,
                'id' => $vehicleId,
                'message' => $id > 0 ? 'Vehicle updated successfully.' : 'Vehicle added to the fleet.',
            ];
        } catch (Throwable $e) {
            $this->conn->rollback();
            $message = $e->getMessage();
            if (stripos($message, 'Duplicate') !== false) {
                return ['success' => false, 'error' => 'A record with that plate number or license number already exists.'];
            }
            // Surface the database guard messages (plate/capacity validation)
            // instead of hiding a useful reason behind a generic failure.
            if (stripos($message, 'Plate number') !== false
                || stripos($message, 'capacity') !== false
                || stripos($message, 'does not exist') !== false) {
                return ['success' => false, 'error' => $message];
            }
            error_log('saveFleetVehicle failed: ' . $message);
            return ['success' => false, 'error' => 'Could not save the vehicle. Please try again.'];
        }
    }

    // Backwards-compatible wrapper used by older callers.
    public function addVehicleToFleet($vehicleData)
    {
        $result = $this->saveFleetVehicle($vehicleData);
        return !empty($result['success']) ? (int)$result['id'] : false;
    }

    /**
     * Assign (or unassign with $driverId = 0) a driver to one vehicle.
     */
    public function assignDriverToVehicle($vehicleId, $driverId)
    {
        $vehicleId = (int)$vehicleId;
        $driverId = (int)$driverId;

        $stmt = $this->conn->prepare("SELECT id FROM vehicle_fleet WHERE id = ?");
        $stmt->bind_param("i", $vehicleId);
        $stmt->execute();
        $vehicle = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$vehicle) {
            return ['success' => false, 'error' => 'Vehicle not found.'];
        }

        if ($driverId > 0 && !$this->getDriverById($driverId)) {
            return ['success' => false, 'error' => 'Driver not found.'];
        }

        $this->conn->begin_transaction();
        try {
            if ($driverId > 0) {
                $this->linkDriverToVehicle($vehicleId, $driverId);
            } else {
                $this->clearVehicleDriver($vehicleId);
            }
            $this->conn->commit();
            return ['success' => true, 'message' => $driverId > 0 ? 'Driver assigned.' : 'Driver unassigned.'];
        } catch (Throwable $e) {
            $this->conn->rollback();
            error_log('assignDriverToVehicle failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Could not assign the driver. Please try again.'];
        }
    }

    // Get all vehicles from fleet, with the assigned driver joined in
    public function getVehicleFleet() {
        $sql = "SELECT vf.*,
                       d.full_name AS driver_name,
                       d.license_number AS driver_license,
                       d.contact_number AS driver_contact,
                       d.status AS driver_status
                FROM vehicle_fleet vf
                LEFT JOIN drivers d ON d.id = vf.driver_id
                ORDER BY vf.plate_number";
        $result = $this->conn->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    // Get available vehicles for dropdown
    public function getAvailableVehicles() {
        $sql = "SELECT id, plate_number, vehicle_type, capacity, model
                FROM vehicle_fleet
                WHERE status = 'available'
                ORDER BY plate_number";
        $result = $this->conn->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    // Update vehicle status
    public function updateVehicleStatus($vehicleId, $status) {
        $vehicleId = (int)$vehicleId;
        $status = (string)$status;

        if ($vehicleId <= 0) {
            return ['success' => false, 'error' => 'Vehicle not found.'];
        }
        if (!in_array($status, ['available', 'maintenance', 'retired'], true)) {
            return ['success' => false, 'error' => 'Invalid vehicle status.'];
        }

        $check = $this->conn->prepare("SELECT id FROM vehicle_fleet WHERE id = ?");
        $check->bind_param("i", $vehicleId);
        $check->execute();
        $vehicle = $check->get_result()->fetch_assoc();
        $check->close();
        if (!$vehicle) {
            return ['success' => false, 'error' => 'Vehicle not found.'];
        }

        $sql = "UPDATE vehicle_fleet
                SET status = ?,
                    last_maintenance = CASE WHEN ? = 'maintenance' THEN CURDATE() ELSE last_maintenance END,
                    updated_at = NOW()
                WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return ['success' => false, 'error' => 'Could not update the vehicle status.'];
        }
        $stmt->bind_param("ssi", $status, $status, $vehicleId);
        $success = $stmt->execute();
        $error = $stmt->error;
        $stmt->close();

        if (!$success) {
            error_log('updateVehicleStatus failed: ' . $error);
            return ['success' => false, 'error' => 'Could not update the vehicle status.'];
        }

        return [
            'success' => true,
            'status' => $status,
            'message' => $status === 'maintenance'
                ? 'Vehicle moved to maintenance. It is no longer offered to faculty requests.'
                : 'Vehicle status updated.',
        ];
    }

    /**
     * Deactivate (retire) a vehicle and release its driver in one step, so a
     * driver is not stuck on a vehicle that is no longer in service.
     */
    public function deactivateVehicle($vehicleId) {
        $vehicleId = (int)$vehicleId;
        $this->conn->begin_transaction();
        try {
            $stmt = $this->conn->prepare("UPDATE vehicle_fleet
                                          SET status = 'retired', driver_id = NULL, updated_at = NOW()
                                          WHERE id = ?");
            $stmt->bind_param("i", $vehicleId);
            $stmt->execute();
            $success = $stmt->affected_rows > 0;
            $stmt->close();
            $this->conn->commit();
            return $success;
        } catch (Throwable $e) {
            $this->conn->rollback();
            error_log('deactivateVehicle failed: ' . $e->getMessage());
            return false;
        }
    }

    // Delete vehicle from fleet
    public function deleteVehicleFromFleet($vehicleId) {
        $sql = "DELETE FROM vehicle_fleet WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $vehicleId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    
    // Add a new vehicle request
    /**
     * Allocate the next Trip Ticket number for a travel year.
     *
     * Numbers restart at 1 every January, so the first request of a new year is 001.
     * Returns [ticket_no, ticket_year] or null if the travel date has no year.
     */
    private function nextTicketNo($travelDate) {
        $ts = strtotime($travelDate);
        if ($ts === false) {
            return null;
        }
        $year = (int)date('Y', $ts);

        $r = $this->conn->query("SELECT COALESCE(MAX(ticket_no), 0) AS mx
                                 FROM vehicle_requests
                                 WHERE ticket_year = $year");
        if (!$r) {
            return null;
        }
        return [(int)$r->fetch_assoc()['mx'] + 1, $year];
    }

    public function addRequest($requestData) {
        // Debug: Log incoming data
        error_log("Add request called with data: " . print_r($requestData, true));

        $userEmail = $_SESSION['email'] ?? '';

        // Normalize vehicle travel dates to a calendar date. The column is DATE,
        // so accepting a datetime here would otherwise rely on implicit truncation.
        $travelDateRaw = trim((string)($requestData['travelDate'] ?? ''));
        $travelDatePart = preg_split('/[T ]/', $travelDateRaw)[0];
        $parsedTravelDate = DateTimeImmutable::createFromFormat('!Y-m-d', $travelDatePart);
        if (!$parsedTravelDate || $parsedTravelDate->format('Y-m-d') !== $travelDatePart) {
            return ['error' => 'Invalid travel date'];
        }
        $travelDate = $parsedTravelDate->format('Y-m-d');

        // Retry once: two officers submitting in the same instant can otherwise
        // both read the same MAX and collide on the uniq_ticket key.
        $attempts = 0;
        $saved = false;
        $insertId = 0;

        while ($attempts < 2 && !$saved) {
            $attempts++;
            $ticket = $this->nextTicketNo($travelDate);
            $ticketNo = $ticket ? $ticket[0] : null;
            $ticketYear = $ticket ? $ticket[1] : null;

            $sql = "INSERT INTO vehicle_requests
                    (ticket_no, ticket_year, travel_date, destination, purpose, passengers, requested_by, user_email, request_date)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                error_log("Prepare failed: " . $this->conn->error);
                return ['error' => 'Failed to prepare statement'];
            }

            $stmt->bind_param("iisssssss",
                $ticketNo,
                $ticketYear,
                $travelDate,
                $requestData['destination'],
                $requestData['purpose'],
                $requestData['passengers'],
                $requestData['requestedBy'],
                $userEmail,
                $requestData['requestDate']
            );

            if ($stmt->execute()) {
                $saved = true;
                $insertId = $this->conn->insert_id;
                error_log("Request inserted successfully with ID: $insertId, ticket: $ticketNo/$ticketYear");
                $stmt->close();
            } else {
                // 1062 = duplicate key, i.e. we lost the race. Loop and try again.
                $err = $this->conn->error;
                $stmt->close();
                if (strpos($err, '1062') === false) {
                    error_log("Insert failed: " . $err);
                    return ['error' => 'Failed to add request'];
                }
                error_log("Ticket collision on attempt $attempts, retrying");
            }
        }

        if (!$saved) {
            return ['error' => 'Could not allocate a trip ticket number, please retry'];
        }

        // Create notification for Planning Officers
        $title = "New Vehicle Request";
        $message = "A new vehicle request has been submitted by {$requestData['requestedBy']} for {$requestData['destination']}.";
        $this->createNotification($title, $message, 'vehicle_request', 'planning_officer', $requestData['requestedBy'], $insertId);

        return [
            'id' => $insertId,
            'status' => 'pending',
            'message' => 'Request submitted successfully'
        ];
    }
    
    // Get all requests - filtered by logged-in user for Faculty
    public function getAllRequests() {
        // Check what table is being requested
        $table = isset($_GET['table']) && $_GET['table'] === 'requested_items' ? 'requested_items' : 'vehicle_requests';
        
        // Get current user info from session (kept for logging/debug purposes)
        $currentUser = '';
        $userRole = '';
        
        if (isset($_SESSION['firstName']) && isset($_SESSION['lastName'])) {
            $currentUser = trim($_SESSION['firstName'] . ' ' . $_SESSION['lastName']);
            $userRole = $_SESSION['role'] ?? '';
        } elseif (isset($_SESSION['email'])) {
            $currentUser = trim($_SESSION['email']);
            $userRole = $_SESSION['role'] ?? '';
        }
        
        // Debug: Add user info to response temporarily
        $debug_info = [
            'current_user' => $currentUser,
            'user_role' => $userRole,
            'table' => $table,
            'session_keys' => array_keys($_SESSION),
            'session_email' => $_SESSION['email'] ?? 'not set',
            'session_firstName' => $_SESSION['firstName'] ?? 'not set',
            'session_lastName' => $_SESSION['lastName'] ?? 'not set',
            'session_fullname' => (isset($_SESSION['firstName']) && isset($_SESSION['lastName'])) ? $_SESSION['firstName'] . ' ' . $_SESSION['lastName'] : 'not available'
        ];
        
        // Filter by user for faculty; planning officer sees all
        $isFaculty = ($userRole === 'faculty');
        
        if ($table === 'requested_items') {
            if ($isFaculty && !empty($currentUser)) {
                // Legacy rows created before user_email was captured store NULL, '',
                // or '0'; fall back to the submitted name so old requests stay visible.
                $sql = "SELECT id, names, items, quantity, reason, DATE_FORMAT(when_needed, '%Y-%m-%d') as when_needed, action, DATE_FORMAT(request_date, '%Y-%m-%d') as request_date 
                         FROM requested_items
                         WHERE (user_email = ?
                                OR ((user_email IS NULL OR user_email = '' OR user_email = '0')
                                    AND LOWER(TRIM(names)) = LOWER(?)))
                         ORDER BY id DESC";
                $stmt = $this->conn->prepare($sql);
                $email = $_SESSION['email'] ?? '';
                $stmt->bind_param("ss", $email, $currentUser);
                $stmt->execute();
                $result = $stmt->get_result();
                $stmt->close();
            } else {
                $sql = "SELECT id, names, items, quantity, reason, DATE_FORMAT(when_needed, '%Y-%m-%d') as when_needed, action, DATE_FORMAT(request_date, '%Y-%m-%d') as request_date 
                         FROM requested_items ORDER BY id DESC";
                $result = $this->conn->query($sql);
            }
        } else {
            if ($isFaculty && !empty($currentUser)) {
                // Legacy rows created before user_email was captured store NULL, '',
                // or '0'; fall back to the submitted name so old requests stay visible.
                $sql = "SELECT vr.id, vr.ticket_no, vr.ticket_year, vr.destination, vr.purpose, vr.passengers,
                               vr.vehicle_id, vf.plate_number AS vehicle_plate,
                               vr.requested_by, vr.travel_date, vr.attachment_name,
                               DATE_FORMAT(vr.request_date, '%Y-%m-%d') as request_date, vr.status, vr.notes
                         FROM vehicle_requests vr
                         LEFT JOIN vehicle_fleet vf ON vf.id = vr.vehicle_id
                         WHERE (vr.user_email = ?
                                OR ((vr.user_email IS NULL OR vr.user_email = '' OR vr.user_email = '0')
                                    AND LOWER(TRIM(vr.requested_by)) = LOWER(?)))
                         ORDER BY vr.id DESC";
                $stmt = $this->conn->prepare($sql);
                $email = $_SESSION['email'] ?? '';
                $stmt->bind_param("ss", $email, $currentUser);
                $stmt->execute();
                $result = $stmt->get_result();
                $stmt->close();
            } else {
                $sql = "SELECT vr.id, vr.ticket_no, vr.ticket_year, vr.destination, vr.purpose, vr.passengers,
                               vr.vehicle_id, vf.plate_number AS vehicle_plate,
                               vr.requested_by, vr.travel_date, vr.attachment_name,
                               DATE_FORMAT(vr.request_date, '%Y-%m-%d') as request_date, vr.status, vr.notes,
                        (SELECT CONCAT(u.firstName, ' ', u.lastName) FROM users u 
                         WHERE u.email = vr.user_email
                            OR ((vr.user_email IS NULL OR vr.user_email = '') AND LOWER(CONCAT(u.firstName, ' ', u.lastName)) = LOWER(vr.requested_by))) AS faculty_name 
                         FROM vehicle_requests vr
                         LEFT JOIN vehicle_fleet vf ON vf.id = vr.vehicle_id
                         ORDER BY vr.id DESC";
                $result = $this->conn->query($sql);
            }
        }
        
        $requests = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $requests[] = $row;
            }
        }
        
        // Add debug info to response temporarily
        $debug_info['request_count'] = count($requests);
        $debug_info['requests_found'] = $requests;
        
        return array_merge(['debug' => $debug_info], ['requests' => $requests]);
    }
    
    // Get requests by status
    public function getRequestsByStatus($status) {
        // Explicit columns: attachment_data is a LONGBLOB and would make the caller's
        // json_encode() return false, emitting an empty response body.
        $sql = "SELECT id, ticket_no, ticket_year, travel_date, destination, purpose,
                       passengers, requested_by, user_email, attachment_name,
                       request_date, status, reviewed_at, notes, created_at
                FROM vehicle_requests WHERE status = ? ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $status);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $requests = [];
        while ($row = $result->fetch_assoc()) {
            $requests[] = $row;
        }
        
        return $requests;
    }
    
    /**
     * Save the driver the officer picked on the travel order form.
     *
     * Called when they approve/print, so the approved-request summary can report
     * who drove each trip. An empty value clears the assignment.
     */
    public function updateRequestDriver($requestId, $driver) {
        $id = (int)$requestId;
        if ($id <= 0) {
            return ['error' => 'Invalid request id'];
        }

        // bind_param() takes its arguments by reference, so the value must live in
        // a variable - an inline expression here is a fatal error.
        $driverValue = trim((string)$driver);
        if ($driverValue === '') {
            $driverValue = null;
        }

        $sql = "UPDATE vehicle_requests SET driver = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return ['error' => 'Failed to prepare statement'];
        }
        $stmt->bind_param("si", $driverValue, $id);
        $success = $stmt->execute();
        $error = $stmt->error;
        $stmt->close();

        if (!$success) {
            return ['error' => 'Failed to save driver: ' . $error];
        }
        return ['success' => true, 'message' => 'Driver saved', 'driver' => $driverValue];
    }

    /**
     * Save the fleet vehicle the Planning Officer dispatched on the travel order.
     *
     * The faculty request no longer names a vehicle; only this officer-side
     * assignment writes vehicle_requests.vehicle_id. An empty plate clears it.
     */
    public function updateRequestVehicle($requestId, $vehicle)
    {
        $id = (int)$requestId;
        if ($id <= 0) {
            return ['error' => 'Invalid request id'];
        }

        $vehicleValue = trim((string)$vehicle);
        $vehicleId = null;

        if ($vehicleValue !== '') {
            $lookup = $this->conn->prepare("SELECT id FROM vehicle_fleet WHERE plate_number = ? LIMIT 1");
            if (!$lookup) {
                return ['error' => 'Failed to prepare statement'];
            }
            $lookup->bind_param("s", $vehicleValue);
            $lookup->execute();
            $vehicleRow = $lookup->get_result()->fetch_assoc();
            $lookup->close();

            if (!$vehicleRow) {
                return ['error' => 'Selected vehicle was not found in the fleet.'];
            }
            $vehicleId = (int)$vehicleRow['id'];
        }

        $sql = "UPDATE vehicle_requests SET vehicle_id = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return ['error' => 'Failed to prepare statement'];
        }
        $stmt->bind_param("ii", $vehicleId, $id);
        $success = $stmt->execute();
        $error = $stmt->error;
        $stmt->close();

        if (!$success) {
            return ['error' => 'Failed to save vehicle: ' . $error];
        }

        return [
            'success' => true,
            'message' => 'Vehicle saved',
            'vehicle' => $vehicleValue !== '' ? $vehicleValue : null,
            'vehicle_id' => $vehicleId,
        ];
    }

    // Update request status
    public function updateRequestStatus($requestId, $status, $notes = '') {
        $validStatuses = ['pending', 'approved', 'declined'];
        if (!in_array($status, $validStatuses, true)) {
            return ['error' => 'Invalid status: must be one of ' . implode(', ', $validStatuses)];
        }
        
        // Table can come from GET or POST
        $table = (($_GET['table'] ?? $_POST['table'] ?? '') === 'requested_items')
            ? 'requested_items'
            : 'vehicle_requests';
        
        $requesterName = '';
        if ($table === 'requested_items') {
            // Stock is reserved when the request is submitted. Read the previous
            // state so stock is only restored/re-deducted once per transition.
            $prevSql = "SELECT items, quantity, action FROM requested_items WHERE id = ?";
            $prevStmt = $this->conn->prepare($prevSql);
            $prevStmt->bind_param("i", $requestId);
            $prevStmt->execute();
            $prevRes = $prevStmt->get_result();
            $prevRow = $prevRes->fetch_assoc();
            $prevStmt->close();

            if ($prevRow) {
                $prevStatus = strtolower($prevRow['action'] ?? 'pending');
                $stockItem = $prevRow['items'] ?? '';
                $stockQty = (int)($prevRow['quantity'] ?? 0);

                if ($stockItem !== '' && $stockQty > 0) {
                    if ($status === 'declined' && $prevStatus !== 'declined') {
                        // Give the reserved stock back to inventory
                        $restoreSql = "UPDATE inventory_management SET quantity = quantity + ? WHERE item_name = ?";
                        $restoreStmt = $this->conn->prepare($restoreSql);
                        $restoreStmt->bind_param("is", $stockQty, $stockItem);
                        $restoreStmt->execute();
                        $restoreStmt->close();
                    } elseif ($status === 'approved' && $prevStatus === 'declined') {
                        // Re-reserve stock when a declined request is approved again
                        $checkSql = "SELECT quantity FROM inventory_management WHERE item_name = ?";
                        $checkStmt = $this->conn->prepare($checkSql);
                        $checkStmt->bind_param("s", $stockItem);
                        $checkStmt->execute();
                        $checkRes = $checkStmt->get_result();
                        $checkRow = $checkRes->fetch_assoc();
                        $checkStmt->close();

                        if (!$checkRow || (int)$checkRow['quantity'] < $stockQty) {
                            return ['error' => 'Not enough stock available to approve this request.'];
                        }

                        $deductSql = "UPDATE inventory_management SET quantity = quantity - ? WHERE item_name = ?";
                        $deductStmt = $this->conn->prepare($deductSql);
                        $deductStmt->bind_param("is", $stockQty, $stockItem);
                        $deductStmt->execute();
                        $deductStmt->close();
                    }
                }
            }

            $sql = "UPDATE requested_items SET action = ?, status_updated_date = NOW(), updated_by = 'Planning Officer' WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("si", $status, $requestId);
            
            // Get requester name for the notification
            $nSql = "SELECT names FROM requested_items WHERE id = ?";
            $nStmt = $this->conn->prepare($nSql);
            $nStmt->bind_param("i", $requestId);
            $nStmt->execute();
            $nRes = $nStmt->get_result();
            if ($row = $nRes->fetch_assoc()) {
                $requesterName = $row['names'] ?? '';
            }
            $nStmt->close();
        } else {
            $sql = "UPDATE vehicle_requests SET status = ?, reviewed_at = NOW(), notes = ? WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ssi", $status, $notes, $requestId);
            
            // Get requester name for the notification
            $nSql = "SELECT requested_by FROM vehicle_requests WHERE id = ?";
            $nStmt = $this->conn->prepare($nSql);
            $nStmt->bind_param("i", $requestId);
            $nStmt->execute();
            $nRes = $nStmt->get_result();
            if ($row = $nRes->fetch_assoc()) {
                $requesterName = $row['requested_by'] ?? '';
            }
            $nStmt->close();
        }
        
        if ($stmt->execute()) {
            // Notify faculty when their request is approved or declined
            if (in_array($status, ['approved', 'declined'], true)) {
                $statusLabel = ucfirst($status);
                $type = ($table === 'requested_items') ? 'item_request' : 'vehicle_request';
                $title = "Request $statusLabel";
                $itemOrVehicle = ($table === 'requested_items') ? 'item request' : 'vehicle request';
                $message = "Your $itemOrVehicle has been $status.";
                if ($requesterName !== '') {
                    $message = "$requesterName's $itemOrVehicle has been $status.";
                }
                // Get the requester's email to send notification to the right person
                $recipientEmail = null;
                if ($table === 'requested_items') {
                    $eSql = "SELECT user_email FROM requested_items WHERE id = ?";
                } else {
                    $eSql = "SELECT user_email FROM vehicle_requests WHERE id = ?";
                }
                $eStmt = $this->conn->prepare($eSql);
                $eStmt->bind_param("i", $requestId);
                $eStmt->execute();
                $eRes = $eStmt->get_result();
                if ($eRow = $eRes->fetch_assoc()) {
                    $recipientEmail = $eRow['user_email'] ?? null;
                }
                $eStmt->close();
                $this->createNotification($title, $message, $type, 'faculty', $requesterName, $requestId, $recipientEmail);
            }
            return ['success' => true, 'message' => "Request $status successfully"];
        } else {
            return ['error' => 'Failed to update request'];
        }
    }
    
    // Delete a request
    public function deleteRequest($requestId) {
        $sql = "DELETE FROM vehicle_requests WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $requestId);
        return $stmt->execute();
    }
    
    // Get requests by specific date
    public function getRequestsByDate($date) {
        if (empty($date)) {
            return [];
        }
        
        // Explicit columns: attachment_data is a LONGBLOB and would make the caller's
        // json_encode() return false, emitting an empty response body.
        // Ordering by travel_time was removed: no such column exists, so prepare()
        // failed and this endpoint returned a fatal error.
        $sql = "SELECT id, ticket_no, ticket_year, travel_date, destination, purpose,
                       passengers, requested_by, user_email, attachment_name,
                       request_date, status, reviewed_at, notes, created_at
                FROM vehicle_requests WHERE DATE(travel_date) = ? ORDER BY travel_date";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $requests = [];
        while ($row = $result->fetch_assoc()) {
            $requests[] = $row;
        }
        
        return $requests;
    }
    
    // Get request statistics
    public function getStatistics() {
        $stats = [];
        
        $statuses = ['pending', 'approved', 'declined'];
        foreach ($statuses as $status) {
            $sql = "SELECT COUNT(*) as count FROM vehicle_requests WHERE status = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("s", $status);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stats[$status] = $row['count'];
        }
        
        return $stats;
    }
}

// API endpoints for AJAX calls
header('Content-Type: application/json');

$database = new DatabaseRequestSystem();

// Handle both GET and POST actions
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Fleet writes are officer-only. The fleet page is already role-gated, but the
// API can be called directly, so enforce it here as well.
$planningOnlyActions = [
    'save_fleet_vehicle', 'add_vehicle_to_fleet',
    'save_driver', 'delete_driver', 'assign_driver',
    'deactivate_vehicle', 'update_vehicle_status', 'delete_vehicle_from_fleet',
];
if (in_array($action, $planningOnlyActions, true) && trim($_SESSION['role'] ?? '') !== 'planning_officer') {
    echo json_encode(['success' => false, 'error' => 'Not authorised to manage the fleet']);
    exit;
}

switch ($action) {
    case 'add':
        $table = $_POST['table'] ?? '';
        error_log("API add called with table: " . $table);
        error_log("POST data: " . print_r($_POST, true));
        
        if ($table === 'vehicle_requests') {
            $data = [
                'travelDate' => $_POST['travelDate'] ?? '',
                'destination' => $_POST['destination'] ?? '',
                'purpose' => $_POST['purpose'] ?? '',
                'passengers' => $_POST['passengers'] ?? '',
                'requestedBy' => $_POST['requestedBy'] ?? '',
                'requestDate' => $_POST['requestDate'] ?? date('Y-m-d H:i:s')
            ];
            error_log("Vehicle request data: " . print_r($data, true));
            $result = $database->addRequest($data);
        } elseif ($table === 'requested_items') {
            $data = [
                'names' => $_SESSION['firstName'] . ' ' . $_SESSION['lastName'] ?? $_SESSION['email'],
                'items' => $_POST['items'] ?? '',
                'quantity' => $_POST['quantity'] ?? 0,
                'reason' => $_POST['reason'] ?? '',
                'when_needed' => $_POST['when_needed'] ?? ''
            ];
            $result = $database->addRequest($data);
            
            // Create notification for Planning Officers when item request is submitted
            if ($result && isset($result['id'])) {
                $title = "New Item Request";
                $message = "A new item request has been submitted by {$data['names']} for {$data['items']}.";
                $database->createNotification($title, $message, 'item_request', 'planning_officer', $data['names'], $result['id']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid table specified.']);
            break;
        }
        echo json_encode($result);
        break;
        
    case 'get_notifications':
        $role = $_SESSION['role'] ?? '';
        echo json_encode($database->getNotifications($role));
        break;
        
    case 'get_unread_count':
        $role = $_SESSION['role'] ?? '';
        echo json_encode(['unread_count' => $database->getUnreadCount($role)]);
        break;
        
    case 'mark_notification_read':
        $notificationId = $_POST['notification_id'] ?? 0;
        echo json_encode(['success' => $database->markNotificationAsRead($notificationId)]);
        break;
        
    case 'clear_all_notifications':
        $role = $_SESSION['role'] ?? 'faculty';
        echo json_encode(['success' => $database->clearAllNotifications($role)]);
        break;

    case 'mark_all_notifications_read':
        $role = $_SESSION['role'] ?? 'planning_officer';
        $success = $database->clearAllNotifications($role);
        echo json_encode(['success' => true]);
        break;
        
    case 'save_fleet_vehicle':
    case 'add_vehicle_to_fleet': // legacy alias kept for older callers
        $vehicleData = [
            'id' => $_POST['id'] ?? 0,
            'vehicle_name' => $_POST['vehicle_name'] ?? '',
            'vehicle_type' => $_POST['vehicle_type'] ?? '',
            'plate_number' => $_POST['plate_number'] ?? '',
            'capacity' => $_POST['capacity'] ?? 1,
            'model' => $_POST['model'] ?? '',
            'year_manufactured' => $_POST['year_manufactured'] ?? null,
            'fuel_type' => $_POST['fuel_type'] ?? '',
            'status' => $_POST['status'] ?? 'available',
            'driver_mode' => $_POST['driver_mode'] ?? 'none',
            'driver_id' => $_POST['driver_id'] ?? 0,
            'driver_full_name' => $_POST['driver_full_name'] ?? '',
            'driver_license' => $_POST['driver_license'] ?? '',
            'driver_contact' => $_POST['driver_contact'] ?? '',
        ];
        echo json_encode($database->saveFleetVehicle($vehicleData));
        break;

    case 'get_vehicle_fleet':
        $vehicles = $database->getVehicleFleet();
        echo json_encode(['success' => true, 'vehicles' => $vehicles]);
        break;

    case 'get_drivers':
        $drivers = $database->getDrivers(!empty($_GET['active']));
        echo json_encode(['success' => true, 'drivers' => $drivers]);
        break;

    case 'save_driver':
        echo json_encode($database->saveDriver([
            'id' => $_POST['id'] ?? 0,
            'full_name' => $_POST['full_name'] ?? '',
            'license_number' => $_POST['license_number'] ?? '',
            'contact_number' => $_POST['contact_number'] ?? '',
            'status' => $_POST['status'] ?? 'active',
        ]));
        break;

    case 'delete_driver':
        echo json_encode(['success' => $database->deleteDriver($_POST['driver_id'] ?? 0)]);
        break;

    case 'assign_driver':
        echo json_encode($database->assignDriverToVehicle(
            $_POST['vehicle_id'] ?? 0,
            $_POST['driver_id'] ?? 0
        ));
        break;

    case 'deactivate_vehicle':
        echo json_encode(['success' => $database->deactivateVehicle($_POST['vehicle_id'] ?? 0)]);
        break;

    case 'update_vehicle_status':
        $vehicleId = $_POST['vehicle_id'] ?? 0;
        $status = $_POST['status'] ?? 'available';
        echo json_encode($database->updateVehicleStatus($vehicleId, $status));
        break;
        
    case 'delete_vehicle_from_fleet':
        $vehicleId = $_POST['vehicle_id'] ?? 0;
        echo json_encode(['success' => $database->deleteVehicleFromFleet($vehicleId)]);
        break;
        
    case 'get_available_vehicles':
        $vehicles = $database->getAvailableVehicles();
        echo json_encode(['success' => true, 'vehicles' => $vehicles]);
        break;
        
    case 'add_request':
        $data = json_decode(file_get_contents('php://input'), true);
        error_log("API add_request called with: " . print_r($data, true));
        if (!$data) {
            echo json_encode(['error' => 'Invalid JSON data received']);
            break;
        }
        $result = $database->addRequest($data);
        error_log("API add_request result: " . print_r($result, true));
        echo json_encode($result);
        break;
        
    case 'get_all_requests':
    case 'get_requests': // Handle both names
        try {
            $requests = $database->getAllRequests();
            header('Content-Type: application/json');
            echo json_encode($requests);
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Failed to get requests: ' . $e->getMessage()]);
        }
        break;
        
    case 'get_pending_requests':
        echo json_encode($database->getRequestsByStatus('pending'));
        break;
        
    case 'update_status':
        $requestId = $_POST['id'] ?? $_POST['request_id'] ?? 0;
        $status = $_POST['status'] ?? '';
        $notes = $_POST['notes'] ?? '';
        error_log("Updating status for ID: $requestId, Status: $status, Notes: $notes");
        error_log("POST data: " . print_r($_POST, true));
        error_log("GET data: " . print_r($_GET, true));
        
        $result = $database->updateRequestStatus($requestId, $status, $notes);
        error_log("Update result: " . print_r($result, true));
        
        header('Content-Type: application/json');
        echo json_encode($result);
        break;
        
    case 'set_driver':
        // Only officers decide who drives, so the write is refused for any other role
        // instead of relying on the page to hide the control.
        if (trim($_SESSION['role'] ?? '') !== 'planning_officer') {
            echo json_encode(['error' => 'Not authorised to assign a driver']);
            break;
        }
        $requestId = $_POST['id'] ?? $_POST['request_id'] ?? 0;
        $driver = $_POST['driver'] ?? '';
        echo json_encode($database->updateRequestDriver($requestId, $driver));
        break;

    case 'set_vehicle':
        // Vehicle dispatch is an officer-only decision made on the Travel Order.
        if (trim($_SESSION['role'] ?? '') !== 'planning_officer') {
            echo json_encode(['error' => 'Not authorised to assign a vehicle']);
            break;
        }
        $requestId = $_POST['id'] ?? $_POST['request_id'] ?? 0;
        $vehicle = $_POST['vehicle'] ?? '';
        echo json_encode($database->updateRequestVehicle($requestId, $vehicle));
        break;

    case 'get_statistics':
        echo json_encode($database->getStatistics());
        break;
        
    case 'get_requests_by_date':
        $date = $_GET['date'] ?? '';
        echo json_encode($database->getRequestsByDate($date));
        break;
        
    case 'delete_request':
        $requestId = $_POST['request_id'] ?? 0;
        echo json_encode(['success' => $database->deleteRequest($requestId)]);
        break;
        
    case 'cancel':
        $requestId = $_POST['id'] ?? 0;
        $table = $_POST['table'] ?? '';
        
        if ($table === 'requested_items' || $table === 'vehicle_requests') {
            // Attachments are stored in the row's LONGBLOB column, so deleting the row
            // below releases the bytes too - there is no separate file to remove.

            // Cancelling an item request releases the stock reserved at submission.
            if ($table === 'requested_items') {
                $lookup = $conn->prepare("SELECT items, quantity, action FROM requested_items WHERE id = ?");
                $lookup->bind_param("i", $requestId);
                $lookup->execute();
                $lookupRes = $lookup->get_result();
                $lookupRow = $lookupRes->fetch_assoc();
                $lookup->close();

                if ($lookupRow && strtolower($lookupRow['action'] ?? '') !== 'declined') {
                    $restoreQty = (int)$lookupRow['quantity'];
                    $restoreItem = $lookupRow['items'];
                    $restoreStmt = $conn->prepare("UPDATE inventory_management SET quantity = quantity + ? WHERE item_name = ?");
                    $restoreStmt->bind_param("is", $restoreQty, $restoreItem);
                    $restoreStmt->execute();
                    $restoreStmt->close();
                }
            }

            // Delete the request entirely so cancelled items don't clutter the list
            $sql = "DELETE FROM $table WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $requestId);
            $success = $stmt->execute();
            $affected = $stmt->affected_rows;
            
            // Also remove any notification linked to this request
            if ($success) {
                $del = $conn->prepare("DELETE FROM notifications WHERE related_id = ?");
                $del->bind_param("i", $requestId);
                $del->execute();
                $del->close();
                echo json_encode(['success' => true, 'message' => 'Request cancelled and removed']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to cancel request']);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid table for cancel action']);
        }
        break;
        
    case 'get_pending_counts':
        $vSeen = isset($_GET['vehicle_seen']) ? $_GET['vehicle_seen'] : null;
        $iSeen = isset($_GET['items_seen']) ? $_GET['items_seen'] : null;

        $vWhere = "WHERE status = 'pending'";
        if ($vSeen) {
            $vSeenEsc = $conn->real_escape_string($vSeen);
            $vWhere .= " AND created_at > '$vSeenEsc'";
        }
        $vResult = $conn->query("SELECT COUNT(*) as cnt FROM vehicle_requests $vWhere");
        $vRow = $vResult ? $vResult->fetch_assoc() : ['cnt' => 0];

        $iWhere = "WHERE action = 'pending'";
        if ($iSeen) {
            $iSeenEsc = $conn->real_escape_string($iSeen);
            $iWhere .= " AND request_date > '$iSeenEsc'";
        }
        $iResult = $conn->query("SELECT COUNT(*) as cnt FROM requested_items $iWhere");
        $iRow = $iResult ? $iResult->fetch_assoc() : ['cnt' => 0];

        echo json_encode(['vehicle_pending' => (int)$vRow['cnt'], 'items_pending' => (int)$iRow['cnt']]);
        break;

    case 'set_badge_seen':
        $section = isset($_POST['section']) ? $_POST['section'] : null;
        if ($section && in_array($section, ['vehicle', 'items'])) {
            $now = date('Y-m-d H:i:s');
            $stmt = $conn->prepare("INSERT INTO badge_seen (section, seen_at) VALUES (?, ?) ON DUPLICATE KEY UPDATE seen_at = VALUES(seen_at)");
            $stmt->bind_param("ss", $section, $now);
            $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => true, 'seen_at' => $now]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid section']);
        }
        break;

    case 'get_badge_seen':
        $section = isset($_GET['section']) ? $_GET['section'] : null;
        if ($section && in_array($section, ['vehicle', 'items'])) {
            $stmt = $conn->prepare("SELECT seen_at FROM badge_seen WHERE section = ?");
            $stmt->bind_param("s", $section);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            echo json_encode(['seen_at' => $row ? $row['seen_at'] : null]);
        } else {
            echo json_encode(['seen_at' => null]);
        }
        break;
        
    default:
        error_log("Invalid action: " . $action);
        echo json_encode(['error' => 'Invalid action: ' . $action]);
}
?>
