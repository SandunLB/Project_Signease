<?php
// verify_document.php
include 'config.php';

header('Content-Type: application/json');

if (!isset($_FILES['pdf']) || !isset($_POST['hash'])) {
    echo json_encode([
        'valid' => false,
        'message' => 'Missing required data'
    ]);
    exit();
}

try {
    $uploadedHash = $_POST['hash'];
    
    // Enhanced query to fetch more details
    $sql = "SELECT 
            dm.*,
            d.upload_date,
            d.signed_at,
            d.requirements,
            d.description,
            d.due_date,
            d.status,
            sender.name as sender_name,
            sender.position as sender_position,
            sender.faculty as sender_faculty,
            recipient.name as recipient_name,
            recipient.position as recipient_position,
            recipient.faculty as recipient_faculty
            FROM document_metadata dm 
            JOIN documents d ON dm.document_id = d.id 
            JOIN users sender ON d.sender_id = sender.id 
            JOIN users recipient ON d.recipient_id = recipient.id
            WHERE dm.hash = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $uploadedHash);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'valid' => false,
            'message' => 'Document not found in our records'
        ]);
        exit();
    }
    
    $metadata = $result->fetch_assoc();
    
    echo json_encode([
        'valid' => true,
        'message' => 'Document is authentic',
        'metadata' => [
            'document' => [
                'original_filename' => $metadata['original_filename'],
                'upload_date' => $metadata['upload_date'],
                'signed_at' => $metadata['signed_at'],
                'status' => $metadata['status'],
                'due_date' => $metadata['due_date'],
                'requirements' => $metadata['requirements'],
                'description' => $metadata['description']
            ],
            'hash' => $metadata['hash'],
            'sender' => [
                'name' => $metadata['sender_name'],
                'position' => $metadata['sender_position'],
                'faculty' => $metadata['sender_faculty']
            ],
            'recipient' => [
                'name' => $metadata['recipient_name'],
                'position' => $metadata['recipient_position'],
                'faculty' => $metadata['recipient_faculty']
            ]
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'valid' => false,
        'message' => 'Error validating document: ' . $e->getMessage()
    ]);
}

$stmt->close();
$conn->close();
?>