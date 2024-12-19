<?php
include 'admin_sidebar.php';
// admin_sidebar.php includes config.php and session.php

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login_register.php");
    exit();
}

// Fetch documents
function fetchDocuments($status = null) {
    global $conn;
    $sql = "SELECT d.*, sender.name AS sender_name, recipient.name AS recipient_name 
            FROM documents d
            JOIN users sender ON d.sender_id = sender.id
            JOIN users recipient ON d.recipient_id = recipient.id";
    if ($status) {
        $sql .= " WHERE d.status = ?";
    }
    $sql .= " ORDER BY d.upload_date DESC";
    
    $stmt = $conn->prepare($sql);
    if ($status) {
        $stmt->bind_param("s", $status);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

$allDocuments = fetchDocuments();
$sentDocuments = fetchDocuments('sent');
$pendingDocuments = fetchDocuments('pending');
$signedDocuments = fetchDocuments('signed');
$completedDocuments = fetchDocuments('completed');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Management - SignEase</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 dark:bg-gray-900">
    <div class="p-4 sm:ml-64">
        <div class="p-4 border-2 border-gray-200 border-dashed rounded-lg dark:border-gray-700">
            <h1 class="text-3xl font-bold mb-6 text-gray-800 dark:text-white">Document Management</h1>
            
            <!-- Tabs -->
            <div class="mb-4">
                <ul class="flex flex-wrap text-sm font-medium text-center text-gray-500 border-b border-gray-200 dark:border-gray-700 dark:text-gray-400">
                    <li class="mr-2">
                        <a href="#" class="inline-block p-4 text-blue-600 bg-gray-100 rounded-t-lg active dark:bg-gray-800 dark:text-blue-500" onclick="showTab('all-documents')">All Documents</a>
                    </li>
                    <li class="mr-2">
                        <a href="#" class="inline-block p-4 rounded-t-lg hover:text-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 dark:hover:text-gray-300" onclick="showTab('sent-documents')">Sent</a>
                    </li>
                    <li class="mr-2">
                        <a href="#" class="inline-block p-4 rounded-t-lg hover:text-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 dark:hover:text-gray-300" onclick="showTab('pending-documents')">Pending</a>
                    </li>
                    <li class="mr-2">
                        <a href="#" class="inline-block p-4 rounded-t-lg hover:text-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 dark:hover:text-gray-300" onclick="showTab('signed-documents')">Signed</a>
                    </li>
                    <li class="mr-2">
                        <a href="#" class="inline-block p-4 rounded-t-lg hover:text-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 dark:hover:text-gray-300" onclick="showTab('completed-documents')">Completed</a>
                    </li>
                </ul>
            </div>

            <!-- Document Tables -->
            <?php
            $tabIds = ['all-documents', 'sent-documents', 'pending-documents', 'signed-documents', 'completed-documents'];
            $documentSets = [$allDocuments, $sentDocuments, $pendingDocuments, $signedDocuments, $completedDocuments];

            foreach ($tabIds as $index => $tabId) {
                $documents = $documentSets[$index];
            ?>
            <div id="<?php echo $tabId; ?>" class="tab-content <?php echo $index === 0 ? '' : 'hidden'; ?>">
                <div class="overflow-x-auto relative shadow-md sm:rounded-lg">
                    <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                            <tr>
                                <th scope="col" class="py-3 px-6">ID</th>
                                <th scope="col" class="py-3 px-6">Sender</th>
                                <th scope="col" class="py-3 px-6">Recipient</th>
                                <th scope="col" class="py-3 px-6">Upload Date</th>
                                <th scope="col" class="py-3 px-6">Status</th>
                                <th scope="col" class="py-3 px-6">Original Document</th>
                                <th scope="col" class="py-3 px-6">Signed Document</th>
                                <th scope="col" class="py-3 px-6">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $document): ?>
                            <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                <td class="py-4 px-6"><?php echo htmlspecialchars($document['id']); ?></td>
                                <td class="py-4 px-6"><?php echo htmlspecialchars($document['sender_name']); ?></td>
                                <td class="py-4 px-6"><?php echo htmlspecialchars($document['recipient_name']); ?></td>
                                <td class="py-4 px-6"><?php echo htmlspecialchars($document['upload_date']); ?></td>
                                <td class="py-4 px-6">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php
                                        switch($document['status']) {
                                            case 'sent':
                                                echo 'bg-blue-100 text-blue-800';
                                                break;
                                            case 'pending':
                                                echo 'bg-yellow-100 text-yellow-800';
                                                break;
                                            case 'signed':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            case 'completed':
                                                echo 'bg-purple-100 text-purple-800';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?php echo ucfirst(htmlspecialchars($document['status'])); ?>
                                    </span>
                                </td>
                                <td class="py-4 px-6">
                                    <?php if ($document['file_path']): ?>
                                        <a href="<?php echo htmlspecialchars($document['file_path']); ?>" target="_blank" class="text-blue-600 dark:text-blue-500 hover:underline">View</a>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-6">
                                    <?php if ($document['signed_file_path']): ?>
                                        <a href="<?php echo htmlspecialchars($document['signed_file_path']); ?>" target="_blank" class="text-blue-600 dark:text-blue-500 hover:underline">View</a>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-6">
                                    <button data-action="view-details" data-document='<?php echo htmlspecialchars(json_encode($document), ENT_QUOTES, 'UTF-8'); ?>' class="text-blue-600 dark:text-blue-500 hover:underline">
                                        View Details
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>

    <!-- Document Details Modal -->
    <div id="documentModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-title">
                        Document Details
                    </h3>
                    <div class="mt-2">
                        <p id="documentId" class="text-sm text-gray-500 dark:text-gray-400"></p>
                        <p id="documentSender" class="text-sm text-gray-500 dark:text-gray-400"></p>
                        <p id="documentRecipient" class="text-sm text-gray-500 dark:text-gray-400"></p>
                        <p id="documentUploadDate" class="text-sm text-gray-500 dark:text-gray-400"></p>
                        <p id="documentStatus" class="text-sm text-gray-500 dark:text-gray-400"></p>
                        <p id="documentRequirements" class="text-sm text-gray-500 dark:text-gray-400"></p>
                        <p id="documentDescription" class="text-sm text-gray-500 dark:text-gray-400"></p>
                        <p id="documentFilePath" class="text-sm text-gray-500 dark:text-gray-400"></p>
                        <p id="documentSignedFilePath" class="text-sm text-gray-500 dark:text-gray-400"></p>
                        <p id="documentDriveLink" class="text-sm text-gray-500 dark:text-gray-400"></p>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-800 dark:text-white dark:border-gray-600 dark:hover:bg-gray-700" onclick="closeDocumentModal()">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });
            document.getElementById(tabId).classList.remove('hidden');

            document.querySelectorAll('.flex.flex-wrap a').forEach(link => {
                link.classList.remove('text-blue-600', 'bg-gray-100', 'dark:bg-gray-800', 'dark:text-blue-500');
                link.classList.add('hover:text-gray-600', 'hover:bg-gray-50', 'dark:hover:bg-gray-800', 'dark:hover:text-gray-300');
            });
            document.querySelector(`a[onclick="showTab('${tabId}')"]`).classList.add('text-blue-600', 'bg-gray-100', 'dark:bg-gray-800', 'dark:text-blue-500');
            document.querySelector(`a[onclick="showTab('${tabId}')"]`).classList.remove('hover:text-gray-600', 'hover:bg-gray-50', 'dark:hover:bg-gray-800', 'dark:hover:text-gray-300');
        }

        function openDocumentModal(docData) {
            document.getElementById('documentId').textContent = `ID: ${docData.id}`;
            document.getElementById('documentSender').textContent = `Sender: ${docData.sender_name}`;
            document.getElementById('documentRecipient').textContent = `Recipient: ${docData.recipient_name}`;
            document.getElementById('documentUploadDate').textContent = `Upload Date: ${docData.upload_date}`;
            document.getElementById('documentStatus').textContent = `Status: ${docData.status}`;
            document.getElementById('documentRequirements').textContent = `Requirements: ${docData.requirements}`;
            document.getElementById('documentDescription').textContent = `Description: ${docData.description || 'N/A'}`;
            document.getElementById('documentFilePath').textContent = `File Path: ${docData.file_path}`;
            document.getElementById('documentSignedFilePath').textContent = `Signed File Path: ${docData.signed_file_path || 'N/A'}`;
            document.getElementById('documentDriveLink').textContent = `Drive Link: ${docData.drive_link || 'N/A'}`;
            document.getElementById('documentModal').classList.remove('hidden');
        }

        function closeDocumentModal() {
            document.getElementById('documentModal').classList.add('hidden');
        }

        // Add event listeners after the DOM is fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            const viewDetailsButtons = document.querySelectorAll('[data-action="view-details"]');
            viewDetailsButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const docData = JSON.parse(this.getAttribute('data-document'));
                    openDocumentModal(docData);
                });
            });
        });
    </script>
    <script src="theme.js"></script>
</body>
</html>

