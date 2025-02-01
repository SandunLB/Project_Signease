<?php
include 'config.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['document_id'])) {
    $document_id = $_POST['document_id'];
    $user_id = $_SESSION['user_id'];

    try {
        // Start transaction
        $conn->begin_transaction();

        // First, get the document details including the latest signed version
        $sql = "SELECT recipient_id, recipient_id_2, recipient_id_3, current_recipient, 
                total_recipients, status, file_path, signed_file_path 
                FROM documents 
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $document_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $document = $result->fetch_assoc();

        if (!$document) {
            throw new Exception("Document not found");
        }

        // Verify it's this user's turn to sign
        $is_valid_recipient = false;
        if ($document['current_recipient'] == 1 && $document['recipient_id'] == $user_id) {
            $is_valid_recipient = true;
        } else if ($document['current_recipient'] == 2 && $document['recipient_id_2'] == $user_id) {
            $is_valid_recipient = true;
        } else if ($document['current_recipient'] == 3 && $document['recipient_id_3'] == $user_id) {
            $is_valid_recipient = true;
        }

        if (!$is_valid_recipient) {
            throw new Exception("Not authorized to sign at this time");
        }

        // Determine which version of the document to use for signing
        $document_to_sign = !empty($document['signed_file_path']) ? 
                           $document['signed_file_path'] : 
                           $document['file_path'];

        // Store the document path in session for the signing page to use
        $_SESSION['document_to_sign'] = $document_to_sign;

        // Update the document status to 'pending' for the current recipient
        $sql = "UPDATE documents SET status = 'pending' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $document_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update document status");
        }

        // Commit transaction
        $conn->commit();
        echo json_encode([
            'success' => true,
            'documentPath' => $document_to_sign
        ]);

    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        error_log("Error in update_document_status.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}

$conn->close();
?>