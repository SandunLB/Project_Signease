<?php
ob_start();
include 'sidebar.php';
// Note: sidebar.php already includes config.php and session.php

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login_register.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Handle document download
if (isset($_GET['download_document'])) {
    $document_id = $_GET['download_document'];
    
    // Fetch the file path
    $file_sql = "SELECT signed_file_path FROM documents WHERE id = ? AND (status = 'completed' OR status = 'partially_signed')";
    $file_stmt = $conn->prepare($file_sql);
    $file_stmt->bind_param("i", $document_id);
    $file_stmt->execute();
    $file_result = $file_stmt->get_result();
    $file_row = $file_result->fetch_assoc();
    
    if ($file_row && !empty($file_row['signed_file_path'])) {
        $file_path = $file_row['signed_file_path'];
        if (file_exists($file_path)) {
            ob_clean();
            header("Content-Type: application/pdf");
            header("Content-Disposition: attachment; filename=\"" . basename($file_path) . "\"");
            header("Content-Length: " . filesize($file_path));
            readfile($file_path);
            exit;
        }
    }
    echo "File not found.";
}

// Fetch sent documents with search
$sent_sql = "SELECT d.id, d.file_path, d.signed_file_path, d.drive_link, d.requirements, 
                    d.description, d.status, d.upload_date, d.due_date, d.current_recipient, 
                    d.total_recipients,
                    r1.name AS recipient1_name, r1.email AS recipient1_email,
                    r2.name AS recipient2_name, r2.email AS recipient2_email,
                    r3.name AS recipient3_name, r3.email AS recipient3_email
             FROM documents d
             JOIN users r1 ON d.recipient_id = r1.id
             LEFT JOIN users r2 ON d.recipient_id_2 = r2.id
             LEFT JOIN users r3 ON d.recipient_id_3 = r3.id
             WHERE d.sender_id = ? 
             AND (r1.name LIKE ? OR r1.email LIKE ? OR 
                  r2.name LIKE ? OR r2.email LIKE ? OR 
                  r3.name LIKE ? OR r3.email LIKE ? OR 
                  d.status LIKE ?)
             ORDER BY d.upload_date DESC";

$sent_stmt = $conn->prepare($sent_sql);
$searchParam = "%$search%";
$sent_stmt->bind_param("isssssss", $user_id, $searchParam, $searchParam, $searchParam, 
                      $searchParam, $searchParam, $searchParam, $searchParam);
$sent_stmt->execute();
$sent_result = $sent_stmt->get_result();

function getStatusClass($status) {
    switch ($status) {
        case 'sent':
            return 'bg-blue-500 text-white';
        case 'pending':
            return 'bg-yellow-500 text-white';
        case 'partially_signed':
            return 'bg-orange-500 text-white';
        case 'completed':
            return 'bg-green-500 text-white';
        default:
            return 'bg-gray-300 text-gray-700';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - SignEase</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
                        primary: '#8B0000',
                        secondary: '#FFA500',
                    }
                }
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900">
    <div class="p-4 sm:ml-64">
        <div class="p-4 border-2 border-gray-200 border-dashed rounded-lg dark:border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Activity Log</h1>
                
                <form action="" method="GET" class="flex items-center">
                    <div class="relative">
                        <input 
                            type="text" 
                            name="search" 
                            placeholder="Search by recipient or status..." 
                            value="<?php echo htmlspecialchars($search); ?>"
                            class="bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg py-2 px-4 pr-10 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:text-white w-64"
                        >
                        <button type="submit" class="absolute right-3 top-2.5">
                            <i class="fas fa-search text-gray-400 dark:text-gray-300"></i>
                        </button>
                    </div>
                    <?php if(!empty($search)): ?>
                        <a href="activity_log.php" class="ml-2 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="overflow-x-auto relative shadow-md sm:rounded-lg">
                <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                        <tr>
                            <th scope="col" class="py-3 px-6">Recipients</th>
                            <th scope="col" class="py-3 px-6">Document</th>
                            <th scope="col" class="py-3 px-6">Upload Date</th>
                            <th scope="col" class="py-3 px-6">Due Date</th>
                            <th scope="col" class="py-3 px-6">Status</th>
                            <th scope="col" class="py-3 px-6">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($sent_result->num_rows > 0): ?>
                            <?php while ($row = $sent_result->fetch_assoc()): ?>
                                <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 cursor-pointer open-modal" 
                                    data-id="<?php echo $row['id']; ?>"
                                    data-requirements="<?php echo htmlspecialchars($row['requirements']); ?>"
                                    data-description="<?php echo htmlspecialchars($row['description']); ?>"
                                    data-status="<?php echo htmlspecialchars($row['status']); ?>"
                                    data-upload-date="<?php echo htmlspecialchars($row['upload_date']); ?>"
                                    data-due-date="<?php echo htmlspecialchars($row['due_date']); ?>"
                                    data-current="<?php echo $row['current_recipient']; ?>"
                                    data-total="<?php echo $row['total_recipients']; ?>">
                                    <td class="py-4 px-6">
                                        <div class="space-y-2">
                                        <?php for ($i = 1; $i <= $row['total_recipients']; $i++): ?>
                                            <?php if (!empty($row["recipient{$i}_name"])): ?>
                                                <div class="<?php echo ($i == $row['current_recipient']) ? 'font-bold' : ''; ?>">
                                                    <span class="font-medium">
                                                        <?php echo "Recipient $i: "; ?>
                                                        <?php if ($row['status'] === 'completed'): ?>
                                                            <!-- If document is completed, show check mark for all recipients -->
                                                            <i class="fas fa-check-circle text-green-500"></i>
                                                        <?php elseif ($i < $row['current_recipient']): ?>
                                                            <!-- For recipients who have already signed -->
                                                            <i class="fas fa-check-circle text-green-500"></i>
                                                        <?php elseif ($i == $row['current_recipient'] && $row['status'] !== 'completed'): ?>
                                                            <!-- For current recipient if document isn't completed -->
                                                            <i class="fas fa-clock text-yellow-500"></i>
                                                        <?php endif; ?>
                                                    </span>
                                                    <?php echo htmlspecialchars($row["recipient{$i}_name"]); ?><br>
                                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                                        <?php echo htmlspecialchars($row["recipient{$i}_email"]); ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        </div>
                                    </td>
                                    <td class="py-4 px-6">
                                        <?php
                                        if ($row['status'] === 'completed' || $row['status'] === 'partially_signed') {
                                            $doc_link = $row['signed_file_path'];
                                            $doc_name = "View Latest Signed Version";
                                        } elseif (!empty($row['drive_link'])) {
                                            $doc_link = $row['drive_link'];
                                            $doc_name = "View on Google Drive";
                                        } else {
                                            $doc_link = $row['file_path'];
                                            $doc_name = basename($row['file_path']);
                                        }
                                        ?>
                                        <a href="<?php echo htmlspecialchars($doc_link); ?>" 
                                           target="_blank" 
                                           class="text-blue-600 dark:text-blue-400 hover:underline" 
                                           onclick="event.stopPropagation();">
                                            <?php echo htmlspecialchars($doc_name); ?>
                                        </a>
                                    </td>
                                    <td class="py-4 px-6"><?php echo htmlspecialchars($row['upload_date']); ?></td>
                                    <td class="py-4 px-6"><?php echo htmlspecialchars($row['due_date'] ?? 'N/A'); ?></td>
                                    <td class="py-4 px-6">
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo getStatusClass($row['status']); ?>">
                                            <?php 
                                            echo ucfirst(str_replace('_', ' ', $row['status']));
                                            if ($row['status'] == 'partially_signed') {
                                                echo " (" . ($row['current_recipient'] - 1) . "/{$row['total_recipients']})";
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-6">
                                        <?php if ($row['status'] === 'completed' || $row['status'] === 'partially_signed'): ?>
                                            <form method="get" action="">
                                                <input type="hidden" name="download_document" value="<?php echo $row['id']; ?>">
                                                <button type="submit" 
                                                        class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded" 
                                                        onclick="event.stopPropagation();">
                                                    <?php echo $row['status'] === 'completed' ? 'Download Final' : 'Download Latest'; ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr class="bg-white dark:bg-gray-800">
                                <td colspan="6" class="py-4 px-6 text-center">
                                    <?php if(!empty($search)): ?>
                                        No documents found matching your search.
                                    <?php else: ?>
                                        No documents found.
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div id="documentModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity dark:bg-gray-900 dark:bg-opacity-75" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full dark:bg-gray-800">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 dark:bg-gray-800">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-title">
                                Document Details
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 dark:text-gray-400" id="modal-content"></p>
                            </div>
                            <div class="mt-4">
                                <div class="flex items-center justify-between w-full" id="status-flow">
                                    <!-- Status indicators will be inserted here by JavaScript -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse dark:bg-gray-700">
                    <button type="button" class="close-modal mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-600 dark:text-white dark:border-gray-500 dark:hover:bg-gray-700">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', (event) => {
            const modal = document.getElementById('documentModal');
            const modalContent = document.getElementById('modal-content');
            const statusFlow = document.getElementById('status-flow');
            const openModalRows = document.querySelectorAll('.open-modal');
            const closeModalButton = document.querySelector('.close-modal');

            const statuses = ['sent', 'pending', 'partially_signed', 'completed'];
            const statusColors = {
                'sent': 'bg-blue-500',
                'pending': 'bg-yellow-500',
                'partially_signed': 'bg-orange-500',
                'completed': 'bg-green-500'
            };

            openModalRows.forEach(row => {
                row.addEventListener('click', function(e) {
                    if (e.target.tagName.toLowerCase() === 'a' || e.target.tagName.toLowerCase() === 'button') return;
                    
                    const current = this.getAttribute('data-current');
                    const total = this.getAttribute('data-total');
                    const status = this.getAttribute('data-status');
                    
                    const docDetails = {
                        requirements: this.getAttribute('data-requirements'),
                        description: this.getAttribute('data-description'),
                        status: status,
                        uploadDate: this.getAttribute('data-upload-date'),
                        dueDate: this.getAttribute('data-due-date'),
                        current: current,
                        total: total
                    };

                    modalContent.innerHTML = `
                        <div class="space-y-3">
                            <p class="dark:text-gray-300"><strong>Requirements:</strong> ${docDetails.requirements}</p>
                            <p class="dark:text-gray-300"><strong>Description:</strong> ${docDetails.description}</p>
                            <p class="dark:text-gray-300"><strong>Upload Date:</strong> ${docDetails.uploadDate}</p>
                            <p class="dark:text-gray-300"><strong>Due Date:</strong> ${docDetails.dueDate || 'N/A'}</p>
                            <p class="dark:text-gray-300">
                                <strong>Signing Progress:</strong> 
                                ${status === 'completed' ? 'All recipients have signed' : 
                                  status === 'partially_signed' ? `${current - 1} of ${total} recipients have signed` :
                                  status === 'pending' ? `Awaiting signature from recipient ${current}` :
                                  'Sent for signing'}
                            </p>
                        </div>`;
                    
                    updateStatusFlow(docDetails.status);
                    modal.classList.remove('hidden');
                });
            });

            closeModalButton.addEventListener('click', function() {
                modal.classList.add('hidden');
            });

            // Close modal on click outside
            window.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.classList.add('hidden');
                }
            });

            // Close modal on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                    modal.classList.add('hidden');
                }
            });

            function updateStatusFlow(currentStatus) {
                statusFlow.innerHTML = '';
                statuses.forEach((status, index) => {
                    const statusElement = document.createElement('div');
                    const isPassed = getStatusOrder(currentStatus) >= getStatusOrder(status);
                    
                    statusElement.className = `w-auto px-2 py-1 rounded-full flex items-center justify-center text-white text-xs font-bold ${
                        isPassed ? statusColors[status] : 'bg-gray-300 dark:bg-gray-600'
                    }`;
                    
                    statusElement.textContent = status.split('_').map(word => 
                        word.charAt(0).toUpperCase() + word.slice(1)
                    ).join(' ');
                    
                    statusFlow.appendChild(statusElement);

                    if (index < statuses.length - 1) {
                        const lineElement = document.createElement('div');
                        lineElement.className = `flex-grow h-1 ${
                            isPassed ? 'bg-green-500' : 'bg-gray-300 dark:bg-gray-600'
                        }`;
                        statusFlow.appendChild(lineElement);
                    }
                });
            }

            function getStatusOrder(status) {
                return statuses.indexOf(status);
            }
        });
    </script>
    <script src="theme.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>