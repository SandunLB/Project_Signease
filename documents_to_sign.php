<?php
include 'sidebar.php';
// Note: sidebar.php already includes config.php and session.php

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login_register.php");
    exit();
}

// Fetch documents for the current user
$user_id = $_SESSION['user_id'];
$sql = "SELECT d.id, d.file_path, d.signed_file_path, d.drive_link, d.description, d.status, 
               d.upload_date, d.due_date, d.current_recipient, d.total_recipients,
               sender.name AS sender_name, sender.email AS sender_email,
               d.recipient_id, d.recipient_id_2, d.recipient_id_3
        FROM documents d
        JOIN users sender ON d.sender_id = sender.id
        WHERE d.recipient_id = ? OR d.recipient_id_2 = ? OR d.recipient_id_3 = ?
        ORDER BY d.id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recipient Documents - SignEase</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#8B0000',
                        secondary: '#FFA500',
                    }
                }
            }
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 dark:bg-gray-900 transition-colors duration-300">
    <div class="p-4 sm:ml-64">
        <div class="p-4 border-2 border-gray-200 border-dashed rounded-lg dark:border-gray-700">
            <h1 class="text-3xl font-bold mb-6 text-gray-800 dark:text-white">Receive for eSign</h1>
            
            <?php if ($result->num_rows > 0): ?>
                <div class="overflow-x-auto relative shadow-md sm:rounded-lg">
                    <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                            <tr>
                                <th scope="col" class="py-3 px-6">Sender</th>
                                <th scope="col" class="py-3 px-6">Document</th>
                                <th scope="col" class="py-3 px-6">Upload Date & Time</th>
                                <th scope="col" class="py-3 px-6">Due Date</th>
                                <th scope="col" class="py-3 px-6">Description</th>
                                <th scope="col" class="py-3 px-6">Status</th>
                                <th scope="col" class="py-3 px-6">Your Position</th>
                                <th scope="col" class="py-3 px-6">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): 
                                // Determine recipient position
                                $recipient_position = 0;
                                if ($row['recipient_id'] == $user_id) $recipient_position = 1;
                                else if ($row['recipient_id_2'] == $user_id) $recipient_position = 2;
                                else if ($row['recipient_id_3'] == $user_id) $recipient_position = 3;

                                // Determine if it's this user's turn to sign
                                $is_my_turn = ($recipient_position == $row['current_recipient']);

                                // Determine if this recipient has already signed
                                $has_signed = false;
                                if ($recipient_position < $row['current_recipient']) {
                                    $has_signed = true;
                                } elseif ($row['status'] == 'completed') {
                                    $has_signed = true;
                                }

                                // Get the latest version of the document to display
                                $document_to_show = !empty($row['signed_file_path']) ? $row['signed_file_path'] : $row['file_path'];
                            ?>
                                <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                    <td class="py-4 px-6">
                                        <?php echo htmlspecialchars($row['sender_name']); ?><br>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            <?php echo htmlspecialchars($row['sender_email']); ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-6">
                                        <a href="<?php echo htmlspecialchars($document_to_show); ?>" 
                                           target="_blank" 
                                           class="text-blue-600 dark:text-blue-400 hover:underline">
                                            <?php echo htmlspecialchars(basename($document_to_show)); ?>
                                        </a>
                                    </td>
                                    <td class="py-4 px-6"><?php echo htmlspecialchars($row['upload_date']); ?></td>
                                    <td class="py-4 px-6"><?php echo htmlspecialchars($row['due_date'] ?? 'N/A'); ?></td>
                                    <td class="py-4 px-6"><?php echo htmlspecialchars($row['description']); ?></td>
                                    <td class="py-4 px-6">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php
                                            switch ($row['status']) {
                                                case 'sent':
                                                    echo 'bg-blue-500 text-white';
                                                    break;
                                                case 'pending':
                                                    echo 'bg-yellow-500 text-white';
                                                    break;
                                                case 'partially_signed':
                                                    echo 'bg-orange-500 text-white';
                                                    break;
                                                case 'completed':
                                                    echo 'bg-green-500 text-white';
                                                    break;
                                                default:
                                                    echo 'bg-gray-300 text-gray-700';
                                            }
                                        ?>">
                                            <?php 
                                            echo ucfirst(str_replace('_', ' ', $row['status']));
                                            if ($row['status'] == 'partially_signed') {
                                                echo " (" . ($row['current_recipient'] - 1) . "/{$row['total_recipients']})";
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-6">
                                        <?php echo "Recipient {$recipient_position} of {$row['total_recipients']}"; ?>
                                    </td>
                                    <td class="py-4 px-6">
                                        <?php if (!$has_signed && $is_my_turn): ?>
                                            <a href="#" 
                                               onclick="handleSignClick(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($document_to_show); ?>')" 
                                               class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                                                Sign
                                            </a>
                                        <?php elseif ($has_signed): ?>
                                            <span class="text-green-600 dark:text-green-400">
                                                <i class="fas fa-check-circle mr-1"></i> Signed
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-500 dark:text-gray-400">
                                                Awaiting Previous Signature
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-600 dark:text-gray-400">No documents to sign at the moment.</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function handleSignClick(documentId, documentPath) {
        if (confirm("Are you sure you want to sign this document?")) {
            fetch('update_document_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'document_id=' + documentId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Use the document path from the response
                    sessionStorage.setItem('documentToSign', data.documentPath);
                    
                    // Create URL to the document editor page
                    const signerUrl = new URL(window.location.pathname, window.location.origin);
                    const editorPath = signerUrl.pathname.replace('documents_to_sign.php', 'doc_editor/index.html');
                    const finalUrl = new URL(editorPath, window.location.origin);
                    
                    // Navigate to the signer page
                    window.location.href = finalUrl.toString();
                } else {
                    alert('Failed to update document status: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the document status');
            });
        }
    }
    </script>
    <script src="theme.js"></script>
</body>
</html>