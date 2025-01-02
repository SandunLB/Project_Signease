<?php
include 'config.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['document_id'])) {
    $document_id = $_POST['document_id'];
    $user_id = $_SESSION['user_id']; // Assuming the user ID is stored in the session

    // Update the document status to 'pending'
    $sql = "UPDATE documents SET status = 'pending' WHERE id = ? AND recipient_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $document_id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}