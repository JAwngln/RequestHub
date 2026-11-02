<?php
include 'connect.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Optional: allow session-based viewing if you want to restrict access
// session_start();
// if (!isset($_SESSION['email'])) {
//     header("Location: auth/login.php");
//     exit();
// }

if ($id <= 0) {
    http_response_code(400);
    echo "Invalid request.";
    exit;
}

// Fetch the attachment for this request
$stmt = $conn->prepare("SELECT attachment_name, attachment_data FROM vehicle_requests WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->bind_result($fileName, $fileData);
$stmt->fetch();

if ($fileData === null) {
    $stmt->close();
    http_response_code(404);
    echo "No attachment found for this request.";
    exit;
}

// Determine content type from the stored filename's extension
$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$mimeMap = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
];
$contentType = $mimeMap[$ext] ?? 'application/octet-stream';

$stmt->close();

// Strip anything that could break out of the header value. Filenames are built
// server side, but this endpoint is reachable with any id, so it is hardened.
$safeName = preg_replace('/[^A-Za-z0-9._ -]/', '_', (string)$fileName);
$safeName = str_replace(['"', "\r", "\n"], '', $safeName);
if ($safeName === '') {
    $safeName = 'attachment';
}

// Serve the file
header('Content-Type: ' . $contentType);
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="' . $safeName . '"');
header('Content-Length: ' . strlen($fileData));
echo $fileData;
exit;