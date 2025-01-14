<?php
include 'sidebar.php';
// Note: sidebar.php already includes config.php and session.php, so we don't need to include them again

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login_register.php");
    exit();
}

// Fetch documents for the current user
$user_id = $_SESSION['user_id'];
$sql = "SELECT d.id, d.file_path, d.signed_file_path, d.drive_link, d.requirements, d.description, d.status, 
               d.upload_date, d.due_date,
               u.id AS sender_id, u.name AS sender_name, u.email AS sender_email
        FROM documents d
        JOIN users u ON d.sender_id = u.id
        WHERE d.recipient_id = ?
        ORDER BY d.id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
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
            <h1 class="text-3xl font-bold mb-6 text-gray-800 dark:text-white">Documents to Sign</h1>
            
            <?php if ($result->num_rows > 0): ?>
                <div class="overflow-x-auto relative shadow-md sm:rounded-lg">
                    <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                            <tr>
                                <th scope="col" class="py-3 px-6">Sender</th>
                                <th scope="col" class="py-3 px-6">Document</th>
                                <th scope="col" class="py-3 px-6">Upload Date</th>
                                <th scope="col" class="py-3 px-6">Due Date</th>
                                <th scope="col" class="py-3 px-6">Requirements</th>
                                <th scope="col" class="py-3 px-6">Description</th>
                                <th scope="col" class="py-3 px-6">Status</th>
                                <th scope="col" class="py-3 px-6">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                    <td class="py-4 px-6">
                                        <?php echo htmlspecialchars($row['sender_name']); ?><br>
                                        <span class="text-xs text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($row['sender_email']); ?></span>
                                    </td>
                                    <td class="py-4 px-6">
                                    <?php if ($row['status'] === 'signed' || $row['status'] === 'completed' && !empty($row['signed_file_path'])): ?>
                                            <a href="<?php echo htmlspecialchars($row['signed_file_path']); ?>" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline">View Signed Document</a>
                                        <?php elseif (!empty($row['drive_link'])): ?>
                                            <a href="<?php echo htmlspecialchars($row['drive_link']); ?>" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline">View on Google Drive</a>
                                        <?php else: ?>
                                            <?php $file_name = basename($row['file_path']); ?>
                                            <a href="<?php echo htmlspecialchars($row['file_path']); ?>" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline"><?php echo htmlspecialchars($file_name); ?></a>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-6"><?php echo htmlspecialchars($row['upload_date']); ?></td>
                                    <td class="py-4 px-6"><?php echo htmlspecialchars($row['due_date'] ?? 'N/A'); ?></td>
                                    <td class="py-4 px-6"><?php echo htmlspecialchars($row['requirements']); ?></td>
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
                                                case 'signed':
                                                    echo 'bg-green-500 text-white';
                                                    break;
                                                case 'completed':
                                                    echo 'bg-purple-500 text-white';
                                                    break;
                                                default:
                                                    echo 'bg-gray-300 text-gray-700';
                                            }
                                            ?>">
                                            <?php echo ucfirst(htmlspecialchars($row['status'])); ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-6">
                                    <?php if ($row['status'] !== 'signed' && $row['status'] !== 'completed'): ?>
                                            <a href="#" 
                                            onclick="handleSignClick(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['file_path']); ?>')" 
                                            class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                                                Sign
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-500 dark:text-gray-400">Signed</span>
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
                // Send a POST request to update the document status
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
                        // If status update is successful, proceed to the PDF editor
                        sessionStorage.setItem('documentToSign', documentPath);
                        
                        // Create URL to the document editor page
                        const signerUrl = new URL(window.location.pathname, window.location.origin);
                        const editorPath = signerUrl.pathname.replace('documents_to_sign.php', 'doc_editor/index.html');
                        const finalUrl = new URL(editorPath, window.location.origin);
                        
                        // Navigate to the signer page
                        window.location.href = finalUrl.toString();
                    } else {
                        alert('Failed to update document status');
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

