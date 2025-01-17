<?php
include 'config.php';
include 'session.php';

// Set timezone to Sri Lanka (assuming you're in Sri Lanka)
date_default_timezone_set('Asia/Colombo');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Check if a file and metadata were uploaded
if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK || !isset($_POST['metadata'])) {
    echo json_encode(['success' => false, 'message' => 'Missing file or metadata']);
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Create uploads directory if it doesn't exist
    $uploadsDir = 'uploads/';
    if (!file_exists($uploadsDir)) {
        mkdir($uploadsDir, 0777, true);
    }

    // Generate a unique filename
    $filename = uniqid() . '_signed.pdf';
    $filepath = $uploadsDir . $filename;

    // Move the uploaded file
    if (!move_uploaded_file($_FILES['pdf']['tmp_name'], $filepath)) {
        throw new Exception('Failed to save the file');
    }

    // Decode metadata
    $metadata = json_decode($_POST['metadata'], true);
    if (!$metadata) {
        throw new Exception('Invalid metadata format');
    }

    // Get current timestamp in correct timezone
    $current_time = date('Y-m-d H:i:s');
    
    // Update the documents table with signed_at timestamp
    $user_id = $_SESSION['user_id'];
    $sql = "UPDATE documents SET status = 'signed', signed_file_path = ?, signed_at = ? WHERE recipient_id = ? AND (status = 'sent' OR status = 'pending') LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $filepath, $current_time, $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update document status');
    }

    // Get the document ID
    $sql = "SELECT id FROM documents WHERE signed_file_path = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $filepath);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to retrieve document ID');
    }
    
    $result = $stmt->get_result();
    $document = $result->fetch_assoc();
    
    if (!$document) {
        throw new Exception('Document not found');
    }

    // Store metadata
    $sql = "INSERT INTO document_metadata (document_id, hash, author, timestamp, original_filename) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issss", 
        $document['id'],
        $metadata['hash'],
        $metadata['author'],
        $metadata['timestamp'],
        $metadata['originalFilename']
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to store document metadata');
    }

    // Commit transaction
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Document signed and metadata stored successfully']);

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$stmt->close();
$conn->close();
?>