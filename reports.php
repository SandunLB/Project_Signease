<?php
// Start output buffering
ob_start();

include 'sidebar.php';
// sidebar.php includes config.php and session.php

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login_register.php");
    exit();
}

// Function to sanitize input
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Handle form submission
$start_date = isset($_POST['start_date']) ? sanitize_input($_POST['start_date']) : '';
$end_date = isset($_POST['end_date']) ? sanitize_input($_POST['end_date']) : '';
$keyword = isset($_POST['keyword']) ? sanitize_input($_POST['keyword']) : '';
$status = isset($_POST['status']) ? sanitize_input($_POST['status']) : '';
$selected_docs = isset($_POST['selected_docs']) ? $_POST['selected_docs'] : [];

// Base query
$query = "SELECT d.id, d.file_path, d.upload_date, d.signed_at, d.status, 
                 sender.name AS sender_name, recipient.name AS recipient_name 
          FROM documents d
          JOIN users sender ON d.sender_id = sender.id
          JOIN users recipient ON d.recipient_id = recipient.id
          WHERE 1=1";

$params = [];
$types = "";

// Add date range filter
if ($start_date && $end_date) {
    $query .= " AND (d.upload_date BETWEEN ? AND ?)";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= "ss";
}

// Add keyword filter
if ($keyword) {
    $query .= " AND (d.file_path LIKE ? OR sender.name LIKE ? OR recipient.name LIKE ?)";
    $keyword_param = "%$keyword%";
    $params[] = $keyword_param;
    $params[] = $keyword_param;
    $params[] = $keyword_param;
    $types .= "sss";
}

// Add status filter
if ($status) {
    $query .= " AND d.status = ?";
    $params[] = $status;
    $types .= "s";
}

$query .= " ORDER BY d.upload_date DESC";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$documents = $result->fetch_all(MYSQLI_ASSOC);

// Handle PDF generation and download
if (isset($_POST['generate_pdf'])) {
    // Clean (erase) the output buffer and turn off output buffering
    ob_end_clean();

    require_once('fpdf/fpdf.php');

    class PDF extends FPDF {
        function Header() {
            $this->Image('./imgs/logo3.png', 10, 8, 15); 
            // Set font: Arial bold 15
            $this->SetFont('Arial', 'B', 15);
            // Move to the right for text alignment
            $this->Cell(25); 
            // Add title, centered
            $this->Cell(130, 10, 'SignEase Document Report', 0, 0, 'C'); 
            // Line break
            $this->Ln(20);
        }
        

        function Footer() {
            // Position at 1.5 cm from bottom
            $this->SetY(-15);
            // Arial italic 8
            $this->SetFont('Arial', 'I', 8);
            // Page number
            $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }

        function ChapterTitle($num, $label) {
            // Arial 12
            $this->SetFont('Arial', '', 12);
            // Background color
            $this->SetFillColor(200, 220, 255);
            // Title
            $this->Cell(0, 6, "Chapter $num : $label", 0, 1, 'L', true);
            // Line break
            $this->Ln(4);
        }

        function ChapterBody($file) {
            // Read text file
            $txt = file_get_contents($file);
            // Times 12
            $this->SetFont('Times', '', 12);
            // Output justified text
            $this->MultiCell(0, 5, $txt);
            // Line break
            $this->Ln();
        }
    }

    $pdf = new PDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 10);

    // Add report details
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 10, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'R', true);
    

    // Add table headers
    $pdf->SetFillColor(52, 152, 219); // Blue header
    $pdf->SetTextColor(255);
    $pdf->SetDrawColor(52, 152, 219);
    $pdf->SetLineWidth(.3);
    $pdf->SetFont('', 'B');

    $pdf->Cell(15, 7, 'ID', 1, 0, 'C', true);
    $pdf->Cell(40, 7, 'Document', 1, 0, 'C', true);
    $pdf->Cell(30, 7, 'Sender', 1, 0, 'C', true);
    $pdf->Cell(30, 7, 'Recipient', 1, 0, 'C', true);
    $pdf->Cell(25, 7, 'Upload Date', 1, 0, 'C', true);
    $pdf->Cell(25, 7, 'Signed Date', 1, 0, 'C', true);
    $pdf->Cell(25, 7, 'Status', 1, 0, 'C', true);
    $pdf->Ln();

    // Reset colors and font
    $pdf->SetFillColor(224, 235, 255);
    $pdf->SetTextColor(0);
    $pdf->SetFont('');

    // Data
    $fill = false;
    foreach ($documents as $doc) {
        if (empty($selected_docs) || in_array($doc['id'], $selected_docs)) {
            $pdf->Cell(15, 6, $doc['id'], 'LR', 0, 'L', $fill);
            $pdf->Cell(40, 6, (strlen(basename($doc['file_path'])) > 15 ? substr(basename($doc['file_path']), 0, 15) . '...' . '.pdf' : basename($doc['file_path'])), 'LR', 0, 'L', $fill);
            $pdf->Cell(30, 6, $doc['sender_name'], 'LR', 0, 'L', $fill);
            $pdf->Cell(30, 6, $doc['recipient_name'], 'LR', 0, 'L', $fill);
            $pdf->Cell(25, 6, date('y-m-d H:i', strtotime($doc['upload_date'])), 'LR', 0, 'L', $fill);
            $pdf->Cell(25, 6, $doc['signed_at'] ? date('y-m-d H:i', strtotime($doc['signed_at'])) : 'N/A', 'LR', 0, 'L', $fill);
            $pdf->Cell(25, 6, $doc['status'], 'LR', 0, 'L', $fill);
            $pdf->Ln();
            $fill = !$fill;
        }
    }
    $pdf->Cell(190, 0, '', 'T');

    $pdf->Output('D', 'document_report.pdf');
    exit;
}

// If we're not generating a PDF, we can output the buffered content
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - SignEase</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        * {
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .animate-fadeIn {
            animation: fadeIn 0.5s ease-out;
        }
        @keyframes slideDown {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .animate-slideDown {
            animation: slideDown 0.5s ease-out;
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
        <div class="p-4 border-2 border-gray-200 border-dashed rounded-lg dark:border-gray-700 animate-fadeIn">
            <h1 class="text-3xl font-bold mb-6 text-gray-800 dark:text-white">Reports</h1>
            
                <!-- Filter Form -->
                <form method="post" class="mb-6 bg-white dark:bg-gray-800 p-5 rounded-lg shadow-lg animate-slideDown">
                    <div class="mb-3">
                        <h2 class="text-lg font-bold text-gray-800 dark:text-white mb-2">Filter Documents</h2>
                        <div class="w-full h-0.5 bg-blue-500 mb-4"></div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="space-y-1">
                            <label for="start_date" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                <i class="fas fa-calendar-alt mr-1"></i>Start Date
                            </label>
                            <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>" 
                                class="mt-1 block w-full p-2 rounded-md border border-gray-300 shadow-sm 
                                focus:border-blue-500 focus:ring focus:ring-blue-200 
                                dark:bg-gray-700 dark:border-gray-600 dark:text-white 
                                text-sm transition duration-300">
                        </div>
                        
                        <div class="space-y-1">
                            <label for="end_date" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                <i class="fas fa-calendar-alt mr-1"></i>End Date
                            </label>
                            <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>" 
                                class="mt-1 block w-full p-2 rounded-md border border-gray-300 shadow-sm 
                                focus:border-blue-500 focus:ring focus:ring-blue-200 
                                dark:bg-gray-700 dark:border-gray-600 dark:text-white 
                                text-sm transition duration-300">
                        </div>
                        
                        <div class="space-y-1">
                            <label for="keyword" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                <i class="fas fa-search mr-1"></i>Keyword
                            </label>
                            <input type="text" id="keyword" name="keyword" value="<?php echo $keyword; ?>" 
                                placeholder="Search documents..." 
                                class="mt-1 block w-full p-2 rounded-md border border-gray-300 shadow-sm 
                                focus:border-blue-500 focus:ring focus:ring-blue-200 
                                dark:bg-gray-700 dark:border-gray-600 dark:text-white 
                                text-sm transition duration-300">
                        </div>
                        
                        <div class="space-y-1">
                            <label for="status" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                <i class="fas fa-filter mr-1"></i>Status
                            </label>
                            <select id="status" name="status" 
                                class="mt-1 block w-full p-2 rounded-md border border-gray-300 shadow-sm 
                                focus:border-blue-500 focus:ring focus:ring-blue-200 
                                dark:bg-gray-700 dark:border-gray-600 dark:text-white 
                                text-sm transition duration-300">
                                <option value="">All Statuses</option>
                                <option value="sent" <?php echo $status === 'sent' ? 'selected' : ''; ?>>Sent</option>
                                <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="signed" <?php echo $status === 'signed' ? 'selected' : ''; ?>>Signed</option>
                                <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-4 flex justify-end">
                        <button type="submit" 
                            class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-md 
                            transition duration-300 ease-in-out focus:outline-none focus:ring-2 
                            focus:ring-blue-500 focus:ring-opacity-50 text-sm">
                            <i class="fas fa-filter mr-1"></i> Apply Filters
                        </button>
                    </div>
                </form>

            <!-- Documents Table -->
            <form method="post" id="reportForm">
                <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
                <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
                <input type="hidden" name="keyword" value="<?php echo $keyword; ?>">
                <input type="hidden" name="status" value="<?php echo $status; ?>">
                
                <div class="overflow-x-auto relative shadow-md sm:rounded-lg animate-fadeIn">
                    <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                            <tr>
                                <th scope="col" class="p-4">
                                    <div class="flex items-center">
                                        <input id="checkbox-all" type="checkbox" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                        <label for="checkbox-all" class="sr-only">checkbox</label>
                                    </div>
                                </th>
                                <th scope="col" class="py-3 px-6">ID</th>
                                <th scope="col" class="py-3 px-6">Document Name</th>
                                <th scope="col" class="py-3 px-6">Sender</th>
                                <th scope="col" class="py-3 px-6">Recipient</th>
                                <th scope="col" class="py-3 px-6">Upload Date</th>
                                <th scope="col" class="py-3 px-6">Signed Date</th>
                                <th scope="col" class="py-3 px-6">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $index => $document): ?>
                            <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition duration-150 ease-in-out" style="animation-delay: <?php echo $index * 0.05; ?>s;">
                                <td class="p-4 w-4">
                                    <div class="flex items-center">
                                        <input id="checkbox-table-<?php echo $document['id']; ?>" type="checkbox" name="selected_docs[]" value="<?php echo $document['id']; ?>" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                        <label for="checkbox-table-<?php echo $document['id']; ?>" class="sr-only">checkbox</label>
                                    </div>
                                </td>
                                <td class="py-4 px-6"><?php echo htmlspecialchars($document['id']); ?></td>
                                <td class="py-4 px-6"><?php echo htmlspecialchars(basename($document['file_path'])); ?></td>
                                <td class="py-4 px-6"><?php echo htmlspecialchars($document['sender_name']); ?></td>
                                <td class="py-4 px-6"><?php echo htmlspecialchars($document['recipient_name']); ?></td>
                                <td class="py-4 px-6"><?php echo date('y-m-d H:i', strtotime($document['upload_date'])); ?></td>
                                <td class="py-4 px-6"><?php echo $document['signed_at'] ? date('y-m-d H:i', strtotime($document['signed_at'])) : 'N/A'; ?></td>
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
                                            case 'signed':
                                                echo 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
                                                break;
                                            case 'completed':
                                                echo 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
                                        }
                                        ?>">
                                        <?php echo ucfirst(htmlspecialchars($document['status'])); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-6 flex justify-end">
                    <button type="submit" name="generate_pdf" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg transition duration-300 ease-in-out transform hover:-translate-y-1 hover:scale-105 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50">
                        <i class="fas fa-file-pdf mr-2"></i> Generate PDF Report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('checkbox-all').addEventListener('change', function() {
            var checkboxes = document.getElementsByName('selected_docs[]');
            for (var checkbox of checkboxes) {
                checkbox.checked = this.checked;
            }
        });

        // Add staggered animation to table rows
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach((row, index) => {
                row.style.animationDelay = `${index * 0.05}s`;
                row.classList.add('animate-fadeIn');
            });
        });
    </script>
    <script src="theme.js"></script>
</body>
</html>