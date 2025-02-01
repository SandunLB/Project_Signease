<?php
include 'sidebar.php';
// sidebar.php includes config.php and session.php

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login_register.php");
    exit();
}

// Fetch documents
function fetchDocuments($status = null, $search = '') {
    global $conn;
    $sql = "SELECT d.*, 
                   sender.name AS sender_name,
                   r1.name AS recipient1_name, r1.email AS recipient1_email,
                   r2.name AS recipient2_name, r2.email AS recipient2_email,
                   r3.name AS recipient3_name, r3.email AS recipient3_email
            FROM documents d
            JOIN users sender ON d.sender_id = sender.id
            JOIN users r1 ON d.recipient_id = r1.id
            LEFT JOIN users r2 ON d.recipient_id_2 = r2.id
            LEFT JOIN users r3 ON d.recipient_id_3 = r3.id
            WHERE 1=1";
    
    $params = [];
    $types = '';

    if ($status) {
        $sql .= " AND d.status = ?";
        $params[] = $status;
        $types .= 's';
    }

    if ($search) {
        $sql .= " AND (sender.name LIKE ? OR 
                      r1.name LIKE ? OR r1.email LIKE ? OR
                      r2.name LIKE ? OR r2.email LIKE ? OR
                      r3.name LIKE ? OR r3.email LIKE ? OR
                      d.description LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, array_fill(0, 8, $searchParam));
        $types .= str_repeat('s', 8);
    }

    $sql .= " ORDER BY d.upload_date DESC";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

$search = isset($_GET['search']) ? $_GET['search'] : '';
$currentTab = isset($_GET['tab']) ? $_GET['tab'] : 'all';

$allDocuments = fetchDocuments(null, $search);
$sentDocuments = fetchDocuments('sent', $search);
$pendingDocuments = fetchDocuments('pending', $search);
$partiallySignedDocuments = fetchDocuments('partially_signed', $search);
$completedDocuments = fetchDocuments('completed', $search);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Management - SignEase</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        * {
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }
    </style>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {"50":"#eff6ff","100":"#dbeafe","200":"#bfdbfe","300":"#93c5fd","400":"#60a5fa","500":"#3b82f6","600":"#2563eb","700":"#1d4ed8","800":"#1e40af","900":"#1e3a8a","950":"#172554"}
                    }
                },
                fontFamily: {
                    'body': [
                        'Inter', 
                        'ui-sans-serif', 
                        'system-ui', 
                        '-apple-system', 
                        'system-ui', 
                        'Segoe UI', 
                        'Roboto', 
                        'Helvetica Neue', 
                        'Arial', 
                        'Noto Sans', 
                        'sans-serif', 
                        'Apple Color Emoji', 
                        'Segoe UI Emoji', 
                        'Segoe UI Symbol', 
                        'Noto Color Emoji'
                    ],
                    'sans': [
                        'Inter', 
                        'ui-sans-serif', 
                        'system-ui', 
                        '-apple-system', 
                        'system-ui', 
                        'Segoe UI', 
                        'Roboto', 
                        'Helvetica Neue', 
                        'Arial', 
                        'Noto Sans', 
                        'sans-serif', 
                        'Apple Color Emoji', 
                        'Segoe UI Emoji', 
                        'Segoe UI Symbol', 
                        'Noto Color Emoji'
                    ]
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <div class="p-4 sm:ml-64">
        <div class="p-4 border-2 border-gray-200 border-dashed rounded-lg dark:border-gray-700 mt-14">
            <h1 class="text-3xl font-bold mb-6 text-gray-800 dark:text-white">Document Management</h1>
            
            <!-- Tabs and Search Bar -->
            <div class="flex items-center justify-between mb-4 bg-white dark:bg-gray-800 rounded-lg shadow">
                <ul class="flex flex-wrap text-sm font-medium text-center text-gray-500 dark:text-gray-400">
                    <li class="mr-2">
                        <a href="?tab=all<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="inline-block p-4 <?php echo $currentTab === 'all' ? 'text-blue-600 bg-gray-100 rounded-tl-lg active dark:bg-gray-800 dark:text-blue-500' : 'hover:text-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 dark:hover:text-gray-300'; ?>">All Documents</a>
                    </li>
                    <li class="mr-2">
                        <a href="?tab=sent<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="inline-block p-4 <?php echo $currentTab === 'sent' ? 'text-blue-600 bg-gray-100 active dark:bg-gray-800 dark:text-blue-500' : 'hover:text-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 dark:hover:text-gray-300'; ?>">Sent</a>
                    </li>
                    <li class="mr-2">
                        <a href="?tab=pending<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="inline-block p-4 <?php echo $currentTab === 'pending' ? 'text-blue-600 bg-gray-100 active dark:bg-gray-800 dark:text-blue-500' : 'hover:text-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 dark:hover:text-gray-300'; ?>">Pending</a>
                    </li>
                    <li class="mr-2">
                        <a href="?tab=partially_signed<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="inline-block p-4 <?php echo $currentTab === 'partially_signed' ? 'text-blue-600 bg-gray-100 active dark:bg-gray-800 dark:text-blue-500' : 'hover:text-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 dark:hover:text-gray-300'; ?>">Partially Signed</a>
                    </li>
                    <li class="mr-2">
                        <a href="?tab=completed<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="inline-block p-4 <?php echo $currentTab === 'completed' ? 'text-blue-600 bg-gray-100 active dark:bg-gray-800 dark:text-blue-500' : 'hover:text-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 dark:hover:text-gray-300'; ?>">Completed</a>
                    </li>
                </ul>
                <form action="" method="GET" class="flex items-center p-2 relative group">
                    <input type="hidden" name="tab" value="<?php echo $currentTab; ?>">
                    <input 
                        type="text" 
                        name="search" 
                        placeholder="Search documents..." 
                        value="<?php echo htmlspecialchars($search); ?>" 
                        class="w-10 p-2 border rounded-full transition-all duration-300 focus:w-64 group-hover:w-64 dark:bg-gray-700 dark:text-white opacity-0 focus:opacity-100 group-hover:opacity-100 outline-none border-blue-500"
                    >
                    <button type="submit" class="absolute right-2 bg-blue-500 text-white p-2 rounded-full hover:bg-blue-600 h-10 w-10 flex items-center justify-center">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>

            <!-- Document Table -->
            <div class="overflow-x-auto relative shadow-md sm:rounded-lg">
                <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                        <tr>
                            <th scope="col" class="py-3 px-6">ID</th>
                            <th scope="col" class="py-3 px-6">Sender</th>
                            <th scope="col" class="py-3 px-6">Recipients</th>
                            <th scope="col" class="py-3 px-6">Upload Date</th>
                            <th scope="col" class="py-3 px-6">Due Date</th>
                            <th scope="col" class="py-3 px-6">Status</th>
                            <th scope="col" class="py-3 px-6">Document</th>
                            <th scope="col" class="py-3 px-6">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $documents = [];
                        switch ($currentTab) {
                            case 'sent':
                                $documents = $sentDocuments;
                                break;
                            case 'pending':
                                $documents = $pendingDocuments;
                                break;
                            case 'partially_signed':
                                $documents = $partiallySignedDocuments;
                                break;
                            case 'completed':
                                $documents = $completedDocuments;
                                break;
                            default:
                                $documents = $allDocuments;
                        }

                        if (empty($documents)): 
                        ?>
                        <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                            <td colspan="8" class="py-4 px-6 text-center">No documents found</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($documents as $document): ?>
                        <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                            <td class="py-4 px-6"><?php echo htmlspecialchars($document['id']); ?></td>
                            <td class="py-4 px-6"><?php echo htmlspecialchars($document['sender_name']); ?></td>
                            <td class="py-4 px-6">
                                <div class="space-y-2">
                                    <?php for ($i = 1; $i <= $document['total_recipients']; $i++): ?>
                                        <?php if (!empty($document["recipient{$i}_name"])): ?>
                                            <div class="<?php echo ($i == $document['current_recipient']) ? 'font-bold' : ''; ?>">
                                                <span class="font-medium">
                                                    <?php echo "Recipient $i: "; ?>
                                                    <?php if ($document['status'] === 'completed'): ?>
                                                        <i class="fas fa-check-circle text-green-500"></i>
                                                    <?php elseif ($i < $document['current_recipient']): ?>
                                                        <i class="fas fa-check-circle text-green-500"></i>
                                                    <?php elseif ($i == $document['current_recipient'] && $document['status'] !== 'completed'): ?>
                                                        <i class="fas fa-clock text-yellow-500"></i>
                                                    <?php endif; ?>
                                                </span>
                                                <?php echo htmlspecialchars($document["recipient{$i}_name"]); ?>
                                                <br>
                                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($document["recipient{$i}_email"]); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                            </td>
                            <td class="py-4 px-6"><?php echo htmlspecialchars($document['upload_date']); ?></td>
                            <td class="py-4 px-6"><?php echo htmlspecialchars($document['due_date'] ?? 'N/A'); ?></td>
                            <td class="py-4 px-6">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php
                                    switch($document['status']) {
                                        case 'sent':
                                            echo 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
                                            break;
                                        case 'pending':
                                            echo 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
                                            break;
                                        case 'partially_signed':
                                            echo 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300';
                                            break;
                                        case 'completed':
                                            echo 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
                                            break;
                                        default:
                                            echo 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
                                    }
                                    ?>">
                                    <?php 
                                    echo ucfirst(str_replace('_', ' ', $document['status']));
                                    if ($document['status'] == 'partially_signed') {
                                        echo " (" . ($document['current_recipient'] - 1) . "/{$document['total_recipients']})";
                                    }
                                    ?>
                                </span>
                            </td>
                            <td class="py-4 px-6">
                                <?php
                                if ($document['status'] === 'completed' || $document['status'] === 'partially_signed') {
                                    $doc_link = $document['signed_file_path'];
                                    $doc_name = "View Latest Signed Version";
                                } else {
                                    $doc_link = $document['file_path'];
                                    $doc_name = "View Original";
                                }
                                ?>
                                <a href="<?php echo htmlspecialchars($doc_link); ?>" 
                                   target="_blank" 
                                   class="text-blue-600 dark:text-blue-500 hover:underline">
                                    <?php echo $doc_name; ?>
                                </a>
                            </td>
                            <td class="py-4 px-6">
                                <button data-action="view-details" 
                                        data-document='<?php echo htmlspecialchars(json_encode($document), ENT_QUOTES, 'UTF-8'); ?>' 
                                        class="text-blue-600 dark:text-blue-500 hover:underline">
                                    View Details
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Document Details Modal -->
    <div id="documentModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity dark:bg-gray-900 dark:bg-opacity-75" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full dark:bg-gray-800">
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-title">
                        Document Details
                    </h3>
                    <div class="mt-4 space-y-3" id="modal-content">
                        <p id="documentId" class="text-sm text-gray-500 dark:text-gray-400"></p>
                        <p id="documentSender" class="text-sm text-gray-500 dark:text-gray-400"></p>
                        <div id="documentRecipients" class="text-sm text-gray-500 dark:text-gray-400"></div>
                        <p id="documentUploadDate" class="text-sm text-gray-500 dark:text-gray-400"></p>
                        <p id="documentDueDate" class="text-sm text-gray-500 dark:text-gray-400"></p>
                        <p id="documentStatus" class="text-sm text-gray-500 dark:text-gray-400"></p>
                        <p id="documentRequirements" class="text-sm text-gray-500 dark:text-gray-400"></p>
                        <p id="documentDescription" class="text-sm text-gray-500 dark:text-gray-400"></p>
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
        function openDocumentModal(docData) {
            document.getElementById('documentId').textContent = `Document ID: ${docData.id}`;
            document.getElementById('documentSender').textContent = `Sender: ${docData.sender_name}`;
            
            // Display recipients
            let recipientsHtml = '<strong>Recipients:</strong><br>';
            for (let i = 1; i <= docData.total_recipients; i++) {
                const recipientName = docData[`recipient${i}_name`];
                const recipientEmail = docData[`recipient${i}_email`];
                if (recipientName) {
                    let status = '';
                    if (docData.status === 'completed') {
                        status = '<i class="fas fa-check-circle text-green-500"></i>';
                    } else if (i < docData.current_recipient) {
                        status = '<i class="fas fa-check-circle text-green-500"></i>';
                    } else if (i === docData.current_recipient) {
                        status = '<i class="fas fa-clock text-yellow-500"></i>';
                    }
                    recipientsHtml += `Recipient ${i}: ${status} ${recipientName} (${recipientEmail})<br>`;
                }
            }
            document.getElementById('documentRecipients').innerHTML = recipientsHtml;
            
            document.getElementById('documentUploadDate').textContent = `Upload Date: ${docData.upload_date}`;
            document.getElementById('documentDueDate').textContent = `Due Date: ${docData.due_date || 'N/A'}`;
            
            let statusText = docData.status.replace(/_/g, ' ');
            if (docData.status === 'partially_signed') {
                statusText += ` (${docData.current_recipient - 1}/${docData.total_recipients})`;
            }
            document.getElementById('documentStatus').textContent = `Status: ${statusText}`;
            
            document.getElementById('documentRequirements').textContent = `Requirements: ${docData.requirements || 'N/A'}`;
            document.getElementById('documentDescription').textContent = `Description: ${docData.description || 'N/A'}`;
            
            document.getElementById('documentModal').classList.remove('hidden');
        }

        function closeDocumentModal() {
            document.getElementById('documentModal').classList.add('hidden');
        }

        // Add event listeners
        document.addEventListener('DOMContentLoaded', function() {
            const viewDetailsButtons = document.querySelectorAll('[data-action="view-details"]');
            viewDetailsButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const docData = JSON.parse(this.getAttribute('data-document'));
                    openDocumentModal(docData);
                });
            });

            // Close modal on outside click
            document.getElementById('documentModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeDocumentModal();
                }
            });

            // Close modal on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeDocumentModal();
                }
            });
        });
    </script>
    <script src="theme.js"></script>
</body>
</html>