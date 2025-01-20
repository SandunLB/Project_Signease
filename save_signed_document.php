<?php
include 'config.php';
include 'session.php';
include 'mail_config.php';

// Set timezone to Sri Lanka
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

    // Get the document ID and sender information
    $sql = "SELECT d.id, d.file_path, u.email as sender_email, u.name as sender_name, d.requirements 
            FROM documents d 
            JOIN users u ON d.sender_id = u.id 
            WHERE d.signed_file_path = ? 
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $filepath);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to retrieve document information');
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

    // Send email notification
    try {
        $mail = configureMailer();
        $mail->addAddress($document['sender_email'], $document['sender_name']);
        $mail->Subject = 'Document Signed Notification - SignEase';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; padding: 20px; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 5px;'>
                <h2 style='color: #2563eb; margin-bottom: 20px;'>Document Signing Notification</h2>
                
                <p>Hello {$document['sender_name']},</p>
                
                <p>Your document has been successfully signed by the recipient.</p>
                
                <div style='background-color: #f8fafc; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <h3 style='color: #374151; margin-bottom: 10px;'>Document Details:</h3>
                    <ul style='list-style: none; padding: 0; margin: 0;'>
                        <li style='margin-bottom: 8px;'><strong>Document ID:</strong> {$document['id']}</li>
                        <li style='margin-bottom: 8px;'><strong>Original File:</strong> " . basename($document['file_path']) . "</li>
                        <li style='margin-bottom: 8px;'><strong>Requirements:</strong> {$document['requirements']}</li>
                        <li style='margin-bottom: 8px;'><strong>Signed Date:</strong> {$current_time}</li>
                    </ul>
                </div>

                <p>You can view and download the signed document from your SignEase dashboard.</p>
                
                <p style='margin-top: 20px;'>Thank you for using SignEase!</p>
                
                <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #6b7280;'>
                    <p>This is an automated message, please do not reply to this email.</p>
                </div>
            </div>";

        $mail->send();
        echo json_encode([
            'success' => true, 
            'message' => 'Document signed and notification sent successfully'
        ]);
    } catch (Exception $e) {
        error_log("Email sending failed: {$mail->ErrorInfo}");
        echo json_encode([
            'success' => true,
            'message' => 'Document signed successfully but notification email failed'
        ]);
    }

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$stmt->close();
$conn->close();
?>