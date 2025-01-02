<?php
include 'config.php';
include 'session.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Check if a file was uploaded
if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit();
}

// Create uploads directory if it doesn't exist
$uploadsDir = 'uploads/';
if (!file_exists($uploadsDir)) {
    mkdir($uploadsDir, 0777, true);
}

// Generate a unique filename
$filename = uniqid() . '_signed.pdf';
$filepath = $uploadsDir . $filename;

// Move the uploaded file to the uploads directory
if (!move_uploaded_file($_FILES['pdf']['tmp_name'], $filepath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save the file']);
    exit();
}

// Update the database
$user_id = $_SESSION['user_id'];
$sql = "UPDATE documents SET status = 'signed', signed_file_path = ? WHERE recipient_id = ? AND (status = 'sent' OR status = 'pending') LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $filepath, $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Document signed and saved successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update the database']);
}

$stmt->close();
$conn->close();