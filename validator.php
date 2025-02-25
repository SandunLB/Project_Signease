<?php
ob_start();
include 'sidebar.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login_register.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle PDF generation
if (isset($_POST['generate_pdf'])) {
    // Clean (erase) the output buffer and turn off output buffering
    ob_end_clean();

    require_once('fpdf/fpdf.php');

    class PDF extends FPDF {
        function Header() {
            $this->Image('./imgs/logo3.png', 10, 8, 15); 
            $this->SetFont('Arial', 'B', 15);
            $this->Cell(25);
            $this->Cell(130, 10, 'SignEase Document Validation Report', 0, 0, 'C');
            $this->Ln(20);
        }

        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
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

    // Add validation results
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Validation Results', 0, 1);
    $pdf->SetFont('Arial', '', 10);

    $pdf->Cell(40, 10, 'Document Name:', 0, 0);
    $pdf->Cell(0, 10, $_POST['doc_filename'], 0, 1);
    $pdf->Cell(40, 10, 'Status:', 0, 0);
    $pdf->Cell(0, 10, $_POST['doc_status'], 0, 1);
    $pdf->Cell(40, 10, 'Upload Date:', 0, 0);
    $pdf->Cell(0, 10, $_POST['doc_upload_date'], 0, 1);
    $pdf->Cell(40, 10, 'Signed Date:', 0, 0);
    $pdf->Cell(0, 10, $_POST['doc_signed_date'], 0, 1);
    $pdf->Cell(40, 10, 'Due Date:', 0, 0);
    $pdf->Cell(0, 10, $_POST['doc_due_date'], 0, 1);
    $pdf->Cell(40, 10, 'Requirements:', 0, 0);
    $pdf->Cell(0, 10, $_POST['doc_requirements'], 0, 1);

    $pdf->Ln(10);

    // Add sender information
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Sender Information', 0, 1);
    $pdf->SetFont('Arial', '', 10);

    $pdf->Cell(40, 10, 'Name:', 0, 0);
    $pdf->Cell(0, 10, $_POST['sender_name'], 0, 1);
    $pdf->Cell(40, 10, 'Position:', 0, 0);
    $pdf->Cell(0, 10, $_POST['sender_position'], 0, 1);
    $pdf->Cell(40, 10, 'Faculty:', 0, 0);
    $pdf->Cell(0, 10, $_POST['sender_faculty'], 0, 1);

    $pdf->Ln(10);

    // Add recipient information
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Recipient Information', 0, 1);
    $pdf->SetFont('Arial', '', 10);

    $pdf->Cell(40, 10, 'Name:', 0, 0);
    $pdf->Cell(0, 10, $_POST['recipient_name'], 0, 1);
    $pdf->Cell(40, 10, 'Position:', 0, 0);
    $pdf->Cell(0, 10, $_POST['recipient_position'], 0, 1);
    $pdf->Cell(40, 10, 'Faculty:', 0, 0);
    $pdf->Cell(0, 10, $_POST['recipient_faculty'], 0, 1);

    $pdf->Output('D', 'validation_report.pdf');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Verification - SignEase</title>
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
                        // Add any custom colors here
                    }
                }
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        [x-cloak] { display: none !important; }

        @keyframes slide-up {
            0% { transform: translateY(20px); opacity: 0; }
            100% { transform: translateY(0); opacity: 1; }
        }

        @keyframes fade-in {
            0% { opacity: 0; }
            100% { opacity: 1; }
        }

        .animate-slide-up {
            animation: slide-up 0.5s ease-out forwards;
        }

        .animate-fade-in {
            animation: fade-in 0.3s ease-out forwards;
        }

        .upload-zone {
            transition: all 0.3s ease;
            border: 2px dashed #93C5FD;
            border-radius: 0.75rem;
        }

        .upload-zone:hover {
            transform: translateY(-2px);
            border-color: #3B82F6;
            background-color: rgba(59, 130, 246, 0.05);
        }

        .result-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .valid-animation {
            animation: valid-pulse 0.5s ease-out;
        }

        .invalid-animation {
            animation: invalid-pulse 0.5s ease-out;
        }

        @keyframes valid-pulse {
            0% { background-color: rgba(34, 197, 94, 0); }
            50% { background-color: rgba(34, 197, 94, 0.1); }
            100% { background-color: rgba(34, 197, 94, 0); }
        }

        @keyframes invalid-pulse {
            0% { background-color: rgba(239, 68, 68, 0); }
            50% { background-color: rgba(239, 68, 68, 0.1); }
            100% { background-color: rgba(239, 68, 68, 0); }
        }
    </style>
    <script type="module" crossorigin src="./doc_editor/js/validator-wxUbiz82.js"></script>
    <link rel="modulepreload" crossorigin href="./doc_editor/js/index-BhysBmqz.js">
    <link rel="stylesheet" crossorigin href="./doc_editor/assets/index-BBjXrYJ7.css">
</head>
<body class="bg-gray-100 dark:bg-gray-900">
    <div class="p-4 sm:ml-64">
        <div class="p-4 border-2 border-gray-200 border-dashed rounded-lg dark:border-gray-700">
            <div class="flex items-center mb-6">
                <i class="fas fa-shield-alt text-3xl text-blue-600 dark:text-blue-400 mr-4"></i>
                <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Verify Document</h1>
            </div>
            
            <div class="bg-white dark:bg-gray-800 shadow-lg rounded-xl p-6 animate-fade-in">
                <div class="bg-blue-50 dark:bg-blue-900/30 border-l-4 border-blue-500 p-4 mb-6 rounded-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-blue-600 dark:text-blue-400"></i>
                        </div>
                        <p class="ml-3 text-gray-600 dark:text-gray-400">
                            Upload a signed PDF document to verify its authenticity and view its metadata.
                            Maximum file size: 10MB.
                        </p>
                    </div>
                </div>
                
                <div class="mb-6">
                    <input type="file" id="validation-upload" accept="application/pdf" 
                        class="block w-full text-sm text-gray-500 dark:text-gray-400
                                file:mr-4 file:py-2 file:px-4 
                                file:rounded-full file:border-0 file:text-sm file:font-semibold 
                                file:bg-blue-50 file:text-blue-700 
                                dark:file:bg-blue-900 dark:file:text-blue-200
                                hover:file:bg-blue-100 dark:hover:file:bg-blue-800 cursor-pointer">
                </div>
                
                <button id="validate-btn" 
                        class="w-full px-6 py-3 bg-blue-600 text-white rounded-xl
                               hover:bg-blue-700 active:bg-blue-800 
                               transition-all duration-200 transform hover:-translate-y-1
                               font-semibold shadow-md hover:shadow-lg
                               disabled:opacity-50 disabled:cursor-not-allowed
                               dark:bg-blue-500 dark:hover:bg-blue-600
                               flex items-center justify-center gap-2">
                    <i class="fas fa-check-circle"></i>
                    Verify Document
                </button>
                
                <!-- Results Card -->
                <div id="result-card" class="mt-8 p-6 bg-white dark:bg-gray-800 rounded-xl shadow-lg hidden animate-slide-up">
                    <br>
                    <div class="flex items-center mb-6">
                        <span id="status-icon" class="text-4xl mr-4"></span>
                        <h2 id="result-title" class="text-2xl font-bold text-gray-800 dark:text-white"></h2>
                    </div>
                    
                    <!-- Document Details -->
                    <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-4">
                        <div class="flex items-center mb-4">
                            <i class="fas fa-file-alt text-blue-500 dark:text-blue-400 mr-3"></i>
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Document Details</h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                <p class="text-sm text-gray-500 dark:text-gray-400">File Name</p>
                                <p id="doc-filename" class="font-medium text-gray-800 dark:text-white mt-1"></p>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                <p class="text-sm text-gray-500 dark:text-gray-400">Status</p>
                                <p id="doc-status" class="font-medium text-gray-800 dark:text-white mt-1"></p>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                <p class="text-sm text-gray-500 dark:text-gray-400">Upload Date</p>
                                <p id="doc-upload-date" class="font-medium text-gray-800 dark:text-white mt-1"></p>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                <p class="text-sm text-gray-500 dark:text-gray-400">Signed Date</p>
                                <p id="doc-signed-date" class="font-medium text-gray-800 dark:text-white mt-1"></p>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                <p class="text-sm text-gray-500 dark:text-gray-400">Due Date</p>
                                <p id="doc-due-date" class="font-medium text-gray-800 dark:text-white mt-1"></p>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                <p class="text-sm text-gray-500 dark:text-gray-400">Requirements</p>
                                <p id="doc-requirements" class="font-medium text-gray-800 dark:text-white mt-1"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Sender Information -->
                    <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-4">
                        <div class="flex items-center mb-4">
                            <i class="fas fa-user text-blue-500 dark:text-blue-400 mr-3"></i>
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Sender Information</h3>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg space-y-3">
                            <p><span class="text-gray-500 dark:text-gray-400">Name:</span> <span id="sender-name" class="font-medium text-gray-800 dark:text-white ml-2"></span></p>
                            <p><span class="text-gray-500 dark:text-gray-400">Position:</span> <span id="sender-position" class="font-medium text-gray-800 dark:text-white ml-2"></span></p>
                            <p><span class="text-gray-500 dark:text-gray-400">Faculty:</span> <span id="sender-faculty" class="font-medium text-gray-800 dark:text-white ml-2"></span></p>
                        </div>
                    </div>

                    <!-- Recipient Information -->
                    <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-4">
                        <div class="flex items-center mb-4">
                            <i class="fas fa-user-check text-blue-500 dark:text-blue-400 mr-3"></i>
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Recipient Information</h3>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg space-y-3">
                            <p><span class="text-gray-500 dark:text-gray-400">Name:</span> <span id="recipient-name" class="font-medium text-gray-800 dark:text-white ml-2"></span></p>
                            <p><span class="text-gray-500 dark:text-gray-400">Position:</span> <span id="recipient-position" class="font-medium text-gray-800 dark:text-white ml-2"></span></p>
                            <p><span class="text-gray-500 dark:text-gray-400">Faculty:</span> <span id="recipient-faculty" class="font-medium text-gray-800 dark:text-white ml-2"></span></p>
                        </div>
                    </div>

                    <!-- PDF Generation Button -->
                    <div class="mt-6 flex justify-end">
                        <button id="generate-pdf-btn" 
                                class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg 
                                       transition duration-300 ease-in-out transform hover:-translate-y-1 hover:scale-105 
                                       focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50">
                            <i class="fas fa-file-pdf mr-2"></i> Download PDF Report
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white dark:bg-gray-800 p-8 rounded-xl shadow-xl animate-fade-in">
            <div class="w-16 h-16 border-4 border-blue-600 dark:border-blue-400 border-t-transparent rounded-full mx-auto animate-spin"></div>
            <p class="mt-4 text-gray-700 dark:text-gray-300 text-center font-medium">Validating document...</p>
        </div>
    </div>

    <script src="theme.js"></script>
    <script>
        document.getElementById('generate-pdf-btn').addEventListener('click', function() {
            // Gather all the data from the result card
            const docFilename = document.getElementById('doc-filename').innerText;
            const docStatus = document.getElementById('doc-status').innerText;
            const docUploadDate = document.getElementById('doc-upload-date').innerText;
            const docSignedDate = document.getElementById('doc-signed-date').innerText;
            const docDueDate = document.getElementById('doc-due-date').innerText;
            const docRequirements = document.getElementById('doc-requirements').innerText;
            const senderName = document.getElementById('sender-name').innerText;
            const senderPosition = document.getElementById('sender-position').innerText;
            const senderFaculty = document.getElementById('sender-faculty').innerText;
            const recipientName = document.getElementById('recipient-name').innerText;
            const recipientPosition = document.getElementById('recipient-position').innerText;
            const recipientFaculty = document.getElementById('recipient-faculty').innerText;

            // Create a form and submit it to generate the PDF
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';

            const addHiddenField = (name, value) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            };

            addHiddenField('generate_pdf', 'true');
            addHiddenField('doc_filename', docFilename);
            addHiddenField('doc_status', docStatus);
            addHiddenField('doc_upload_date', docUploadDate);
            addHiddenField('doc_signed_date', docSignedDate);
            addHiddenField('doc_due_date', docDueDate);
            addHiddenField('doc_requirements', docRequirements);
            addHiddenField('sender_name', senderName);
            addHiddenField('sender_position', senderPosition);
            addHiddenField('sender_faculty', senderFaculty);
            addHiddenField('recipient_name', recipientName);
            addHiddenField('recipient_position', recipientPosition);
            addHiddenField('recipient_faculty', recipientFaculty);

            document.body.appendChild(form);
            form.submit();
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>