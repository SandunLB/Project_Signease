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

    // Get current timestamp
    $current_time = date('Y-m-d H:i:s');
    
    // Get document information and validate current signer
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT d.*, 
                   s.name as sender_name, s.email as sender_email,
                   r1.email as recipient1_email, r1.name as recipient1_name,
                   r2.email as recipient2_email, r2.name as recipient2_name,
                   r3.email as recipient3_email, r3.name as recipient3_name
            FROM documents d
            JOIN users s ON d.sender_id = s.id
            JOIN users r1 ON d.recipient_id = r1.id
            LEFT JOIN users r2 ON d.recipient_id_2 = r2.id
            LEFT JOIN users r3 ON d.recipient_id_3 = r3.id
            WHERE (d.recipient_id = ? OR d.recipient_id_2 = ? OR d.recipient_id_3 = ?)
            AND d.status = 'pending'
            ORDER BY d.id DESC LIMIT 1";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $document = $result->fetch_assoc();

    if (!$document) {
        throw new Exception('Document not found or not pending signature');
    }

    // Verify it's this user's turn
    if (($document['current_recipient'] == 1 && $document['recipient_id'] != $user_id) ||
        ($document['current_recipient'] == 2 && $document['recipient_id_2'] != $user_id) ||
        ($document['current_recipient'] == 3 && $document['recipient_id_3'] != $user_id)) {
        throw new Exception('Not authorized to sign at this time');
    }

    // Determine new status and next recipient
    $new_status = 'partially_signed';
    $next_recipient = $document['current_recipient'] + 1;
    
    // If this was the last recipient, mark as completed
    if ($next_recipient > $document['total_recipients']) {
        $new_status = 'completed';
        $next_recipient = $document['current_recipient']; // Keep current value
    }

    // Update document status
    $sql = "UPDATE documents 
            SET status = ?, 
                current_recipient = ?, 
                signed_file_path = ?, 
                signed_at = ? 
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sissi", $new_status, $next_recipient, $filepath, $current_time, $document['id']);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update document status');
    }

    // Store metadata
    $sql = "INSERT INTO document_metadata (document_id, hash, author, timestamp, original_filename) 
            VALUES (?, ?, ?, ?, ?)";
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

    // Send appropriate notifications
    try {
        $mail = configureMailer();

        if ($new_status === 'completed') {
            // Send completion notification to sender
            $mail->addAddress($document['sender_email'], $document['sender_name']);
            $mail->Subject = 'Document Fully Signed - SignEase';
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; padding: 20px; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 5px;'>
                    <h2 style='color: #2563eb; margin-bottom: 20px;'>Document Fully Signed</h2>
                    
                    <p>Hello {$document['sender_name']},</p>
                    
                    <p>Your document has been signed by all recipients.</p>
                    
                    <div style='background-color: #f8fafc; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                        <h3 style='color: #374151; margin-bottom: 10px;'>Document Details:</h3>
                        <ul style='list-style: none; padding: 0; margin: 0;'>
                            <li style='margin-bottom: 8px;'><strong>Document ID:</strong> {$document['id']}</li>
                            <li style='margin-bottom: 8px;'><strong>Original File:</strong> " . basename($document['file_path']) . "</li>
                            <li style='margin-bottom: 8px;'><strong>Final Signing Date:</strong> {$current_time}</li>
                        </ul>
                    </div>

                    <p>You can view and download the signed document from your SignEase dashboard.</p>
                    
                    <p style='margin-top: 20px;'>Thank you for using SignEase!</p>
                </div>";
        } else {
            // Send notification to next recipient
            $next_recipient_email = $document['recipient' . $next_recipient . '_email'];
            $next_recipient_name = $document['recipient' . $next_recipient . '_name'];
            
            $mail->addAddress($next_recipient_email, $next_recipient_name);
            $mail->Subject = 'Document Ready for Signing - SignEase';
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; padding: 20px; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 5px;'>
                    <h2 style='color: #2563eb; margin-bottom: 20px;'>Document Ready for Your Signature</h2>
                    
                    <p>Hello {$next_recipient_name},</p>
                    
                    <p>A document is ready for your signature.</p>
                    
                    <div style='background-color: #f8fafc; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                        <h3 style='color: #374151; margin-bottom: 10px;'>Document Details:</h3>
                        <ul style='list-style: none; padding: 0; margin: 0;'>
                            <li style='margin-bottom: 8px;'><strong>Sender:</strong> {$document['sender_name']}</li>
                            <li style='margin-bottom: 8px;'><strong>You are recipient:</strong> {$next_recipient} of {$document['total_recipients']}</li>
                            <li style='margin-bottom: 8px;'><strong>Due Date:</strong> {$document['due_date']}</li>
                        </ul>
                    </div>

                    <p>Please log in to your SignEase account to review and sign the document.</p>
                    
                    <p style='margin-top: 20px;'>Thank you for using SignEase!</p>
                </div>";
        }

        $mail->send();
        
    } catch (Exception $e) {
        // Log email error but don't fail the transaction
        error_log("Failed to send email notification: " . $e->getMessage());
    }

    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Document signed successfully'
    ]);

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>