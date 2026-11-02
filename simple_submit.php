<?php
session_start();
include 'connect.php';

header('Content-Type: application/json');

// Create table if it doesn't exist
$createTableSQL = "CREATE TABLE IF NOT EXISTS vehicle_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    travel_date DATE NOT NULL,
    destination VARCHAR(255) NOT NULL,
    purpose TEXT NOT NULL,
    passengers VARCHAR(50) NOT NULL,
    requested_by VARCHAR(100) NOT NULL,
    attachment_name VARCHAR(255) NULL,
    attachment_data LONGBLOB NULL,
    request_date DATETIME NOT NULL,
    status ENUM('pending', 'approved', 'declined') DEFAULT 'pending',
    reviewed_at DATETIME NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (!$conn->query($createTableSQL)) {
    echo json_encode(['error' => 'Error creating table: ' . $conn->error]);
    exit;
}

// Migrate older installs that used the 'rejected' status name to 'declined'
$conn->query("UPDATE vehicle_requests SET status = 'declined' WHERE status = 'rejected'");
$statusCol = $conn->query("SHOW COLUMNS FROM vehicle_requests LIKE 'status'");
if ($statusCol && ($statusRow = $statusCol->fetch_assoc()) && strpos($statusRow['Type'], "'rejected'") !== false) {
    $conn->query("ALTER TABLE vehicle_requests MODIFY status ENUM('pending', 'approved', 'declined') DEFAULT 'pending'");
}

// Ensure the attachment columns exist (for existing tables)
$checkCol = $conn->query("SHOW COLUMNS FROM vehicle_requests LIKE 'attachment_name'");
if ($checkCol && $checkCol->num_rows === 0) {
    $conn->query("ALTER TABLE vehicle_requests ADD COLUMN attachment_name VARCHAR(255) NULL AFTER requested_by");
}
$checkBlob = $conn->query("SHOW COLUMNS FROM vehicle_requests LIKE 'attachment_data'");
if ($checkBlob && $checkBlob->num_rows === 0) {
    $conn->query("ALTER TABLE vehicle_requests ADD COLUMN attachment_data LONGBLOB NULL AFTER attachment_name");
}

// Get POST data
$travelDateRaw = trim((string)($_POST['travelDate'] ?? ''));

// Normalize to a calendar date. Accepts legacy datetime submissions but never
// stores a time, so the DATE column and ticket-year lookup stay timezone-free.
$travelDate = '';
$travelDatePart = preg_split('/[T ]/', $travelDateRaw)[0];
$parsedTravelDate = DateTimeImmutable::createFromFormat('!Y-m-d', $travelDatePart);
if ($parsedTravelDate && $parsedTravelDate->format('Y-m-d') === $travelDatePart) {
    $travelDate = $parsedTravelDate->format('Y-m-d');
}

$destination = $_POST['destination'] ?? '';
$purpose = $_POST['purpose'] ?? '';
$passengers = $_POST['passengers'] ?? '';
$requestedBy = trim($_POST['requestedBy'] ?? '');

// Debug: Log received data
error_log("Received POST data: " . print_r($_POST, true));

// Debug: Check connection
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// Validate required fields
if (!$travelDate || !$destination || !$purpose || !$passengers || !$requestedBy) {
    $missing = [];
    if (!$travelDate) $missing[] = 'travelDate (valid date required)';
    if (!$destination) $missing[] = 'destination';
    if (!$purpose) $missing[] = 'purpose';
    if (!$passengers) $missing[] = 'passengers';
    if (!$requestedBy) $missing[] = 'requestedBy';
    
    echo json_encode(['error' => 'Missing required fields: ' . implode(', ', $missing)]);
    exit;
}

// Handle file upload - the file bytes are stored in the database (LONGBLOB), so the
// attachment travels with the request row and no shared upload folder is involved.
$attachmentName = '';
$attachmentData = null;
if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $allowedExt = ['pdf', 'jpg', 'jpeg', 'png', 'gif'];
    $fileExt = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileExt, $allowedExt, true)) {
        echo json_encode(['error' => 'Invalid file type. Only PDF, JPG, JPEG, PNG, GIF allowed.']);
        exit;
    }
    
    $tmp = $_FILES['attachment']['tmp_name'];
    if (!is_uploaded_file($tmp)) {
        echo json_encode(['error' => 'Failed to save uploaded file.']);
        exit;
    }
    
    // Build a friendly filename: "Name - authority to travel.ext"
    $cleanName = trim(preg_replace('/[^a-zA-Z0-9 _-]/', '_', $requestedBy));
    $cleanName = preg_replace('/\s+/', ' ', $cleanName);
    if ($cleanName === '') {
        $cleanName = 'Faculty';
    }
    $attachmentName = $cleanName . ' - authority to travel.' . $fileExt;
    
    $bytes = file_get_contents($tmp);
    if ($bytes === false || $bytes === '') {
        echo json_encode(['error' => 'Failed to read uploaded file.']);
        exit;
    }
    $attachmentData = $bytes;
}

// Allocate the Trip Ticket here, at insert time.
// ensureTicketNumbering() only runs when connect.php is loaded, which happens BEFORE
// this INSERT, so a brand-new row used to be left with ticket_no = NULL until some
// later page load backfilled it. Worse, that backfill restarted numbering at 1 for
// each travel year and collided with the uniq_ticket (ticket_year, ticket_no) index,
// so it failed silently and the row stayed NULL permanently. Take the next free
// number in the request's travel year up front instead.
$ticketYear = (int)substr($travelDate, 0, 4);
$nextTicketSql = "SELECT COALESCE(MAX(ticket_no), 0) + 1 AS n FROM vehicle_requests WHERE ticket_year = ?";
$nextStmt = $conn->prepare($nextTicketSql);
$nextStmt->bind_param("i", $ticketYear);
$nextStmt->execute();
$ticketNo = (int)($nextStmt->get_result()->fetch_assoc()['n'] ?? 1);
$nextStmt->close();

// Insert the row together with the attachment bytes. The blob is bound as a plain
// string ("s"), not "b": the "b" type silently stores an empty value on this
// PHP/mysqli build, while "s" is byte-exact because the target column is LONGBLOB
// and so no character-set conversion is applied. Verified with an md5 round-trip.
$userEmail = $_SESSION['email'] ?? '';
$sql = "INSERT INTO vehicle_requests (travel_date, destination, purpose, passengers, requested_by, attachment_name, attachment_data, user_email, ticket_no, ticket_year, request_date) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

// Two officers can submit in the same second and pick the same number, so retry on
// a duplicate-key clash by taking the next free number.
$inserted = false;
$lastError = '';
for ($attempt = 0; $attempt < 5 && !$inserted; $attempt++) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssii", $travelDate, $destination, $purpose, $passengers, $requestedBy, $attachmentName, $attachmentData, $userEmail, $ticketNo, $ticketYear);
    if ($stmt->execute()) {
        $inserted = true;
    } else {
        $lastError = $stmt->error;
        $stmt->close();
        // A database guard rejected the row (for example the vehicle entered
        // maintenance a moment ago). Retrying cannot help, so surface it now.
        if (stripos($lastError, 'not available') !== false || stripos($lastError, 'does not exist') !== false) {
            echo json_encode(['error' => 'The selected vehicle is no longer available. Please choose another vehicle.']);
            exit;
        }
        $bump = $conn->prepare($nextTicketSql);
        $bump->bind_param("i", $ticketYear);
        $bump->execute();
        $ticketNo = (int)($bump->get_result()->fetch_assoc()['n'] ?? ($ticketNo + 1));
        $bump->close();
    }
}
$stmt->close();

if (!$inserted) {
    echo json_encode(['error' => 'Failed to save request: ' . $lastError]);
    exit;
}

$insertId = $conn->insert_id;

// Create notification for Planning Officers
$notifSql = "INSERT INTO notifications (title, message, type, recipient_role, sender_name, related_id) VALUES (?, ?, ?, ?, ?, ?)";
$notifStmt = $conn->prepare($notifSql);
$title = "New Vehicle Request";
$message = "A new vehicle request has been submitted by {$requestedBy} for {$destination}.";
$notifType = 'vehicle_request';
$notifRole = 'planning_officer';
$notifStmt->bind_param("sssssi", $title, $message, $notifType, $notifRole, $requestedBy, $insertId);
$notifStmt->execute();
$notifStmt->close();

echo json_encode([
    'success' => 'Request submitted successfully',
    'id' => $insertId,
    'ticket_no' => $ticketNo,
    'ticket_year' => $ticketYear,
    'message' => 'Your request has been saved to the database'
]);
?>