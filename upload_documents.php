<?php
include 'sidebar.php';
include 'mail_config.php';
// Note: sidebar.php already includes config.php and session.php

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login_register.php");
    exit();
}

$upload_message = '';

function getGoogleDriveFileId($url) {
    $patterns = array(
        '/\/file\/d\/([a-zA-Z0-9_-]+)/',
        '/id=([a-zA-Z0-9_-]+)/',
        '/folders\/([a-zA-Z0-9_-]+)/'
    );

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
    }
    return null;
}

function getUniqueFilename($target_dir, $filename) {
    $original_name = pathinfo($filename, PATHINFO_FILENAME);
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    $counter = 1;
    
    // Keep checking until we find a unique filename
    while (file_exists($target_dir . $filename)) {
        $filename = $original_name . " ({$counter})." . $extension;
        $counter++;
    }
    
    return $filename;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $sender_id = $_SESSION['user_id'];
    $recipient_id = $_POST['recipient'];
    $drive_link = $_POST['drive_link'];
    $requirements = isset($_POST['requirements']) ? implode(", ", $_POST['requirements']) : "";
    $description = $_POST['description'];
    $status = 'sent'; // Initial status when document is uploaded
    
    // Add this line to get the due date
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : date('Y-m-d', strtotime('+1 week'));

    $target_dir = "uploads/";
    $uploadOk = 1;

    // Handle file upload
    if (isset($_FILES["document"]) && $_FILES["document"]["error"] == 0) {
        $original_filename = basename($_FILES["document"]["name"]);
        $filename = getUniqueFilename($target_dir, $original_filename);
        $target_file = $target_dir . $filename;
        $fileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Check file size
        if ($_FILES["document"]["size"] > 5000000) {
            $upload_message = "Error: Your file is too large (max 5MB).";
            $uploadOk = 0;
        }

        // Check file type
        $allowed_extensions = array("pdf", "doc", "docx", "txt");
        if (!in_array($fileType, $allowed_extensions)) {
            $upload_message = "Error: Only PDF, DOC, DOCX & TXT files are allowed.";
            $uploadOk = 0;
        }

        if ($uploadOk == 1 && !move_uploaded_file($_FILES["document"]["tmp_name"], $target_file)) {
            $upload_message = "Error: There was an error uploading your file.";
            $uploadOk = 0;
        }
    } elseif (!empty($drive_link)) {
        $file_id = getGoogleDriveFileId($drive_link);
        
        if ($file_id) {
            $download_url = "https://drive.google.com/uc?export=download&id=" . $file_id;
            $original_filename = uniqid('gdrive_') . '.pdf';
            $filename = getUniqueFilename($target_dir, $original_filename);
            $target_file = $target_dir . $filename;

            $ch = curl_init($download_url);
            $fp = fopen($target_file, 'wb');
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            if (curl_exec($ch) === false) {
                $upload_message = "Error: Unable to access the Google Drive file. Please check if it's publicly accessible.";
                $uploadOk = 0;
            }
            
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($http_code == 404) {
                $upload_message = "Error: The Google Drive file was not found. Please check the link.";
                $uploadOk = 0;
            } elseif ($http_code != 200) {
                $upload_message = "Error: Unable to download the file. HTTP status code: " . $http_code;
                $uploadOk = 0;
            }
            
            curl_close($ch);
            fclose($fp);

            if (!file_exists($target_file) || filesize($target_file) == 0) {
                $upload_message = "Error: Failed to download file from Google Drive. Please check if the file is accessible.";
                $uploadOk = 0;
                @unlink($target_file);
            }
        } else {
            $upload_message = "Error: Invalid Google Drive link format.";
            $uploadOk = 0;
        }
    } else {
        $upload_message = "Error: No file was uploaded or provided via Google Drive link.";
        $uploadOk = 0;
    }

    if ($uploadOk == 1) {
        // Get sender's name for the email
        $sender_sql = "SELECT name FROM users WHERE id = ?";
        $sender_stmt = $conn->prepare($sender_sql);
        $sender_stmt->bind_param("i", $sender_id);
        $sender_stmt->execute();
        $sender_result = $sender_stmt->get_result();
        $sender_data = $sender_result->fetch_assoc();
        $sender_name = $sender_data['name'];

        // Update the SQL query to include the due_date column
        $sql = "INSERT INTO documents (sender_id, recipient_id, file_path, drive_link, requirements, description, status, due_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iissssss", $sender_id, $recipient_id, $target_file, $drive_link, $requirements, $description, $status, $due_date);

        if ($stmt->execute()) {
            try {
                // Get recipient's email
                $recipient_sql = "SELECT name, email FROM users WHERE id = ?";
                $recipient_stmt = $conn->prepare($recipient_sql);
                $recipient_stmt->bind_param("i", $recipient_id);
                $recipient_stmt->execute();
                $recipient_result = $recipient_stmt->get_result();
                $recipient_data = $recipient_result->fetch_assoc();

                // Send email notification
                $mail = configureMailer();
                $mail->addAddress($recipient_data['email'], $recipient_data['name']);
                $mail->Subject = 'New Document for Signing - SignEase';
                $mail->Body = "
                    <div style='font-family: Arial, sans-serif; padding: 20px; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 5px;'>
                        <h2 style='color: #2563eb; margin-bottom: 20px;'>New Document Ready for Signing</h2>
                        
                        <p>Hello {$recipient_data['name']},</p>
                        
                        <p>A new document has been sent to you by {$sender_name} for signing through SignEase.</p>
                        
                        <div style='background-color: #f8fafc; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                            <h3 style='color: #374151; margin-bottom: 10px;'>Document Details:</h3>
                            <ul style='list-style: none; padding: 0; margin: 0;'>
                                <li style='margin-bottom: 8px;'><strong>Sender:</strong> {$sender_name}</li>
                                <li style='margin-bottom: 8px;'><strong>Requirements:</strong> {$requirements}</li>
                                <li style='margin-bottom: 8px;'><strong>Due Date:</strong> {$due_date}</li>
                                <li style='margin-bottom: 8px;'><strong>Description:</strong> {$description}</li>
                            </ul>
                        </div>

                        <p>Please log in to your SignEase account to view and sign the document.</p>
                        
                        <div style='margin-top: 20px; padding: 15px; background-color: #ffedd5; border-radius: 5px;'>
                            <p style='margin: 0; color: #9a3412;'>Note: This document needs to be signed by {$due_date}.</p>
                        </div>
                        
                        <p style='margin-top: 20px;'>Thank you for using SignEase!</p>
                        
                        <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #6b7280;'>
                            <p>This is an automated message, please do not reply to this email.</p>
                        </div>
                    </div>";

                $mail->send();
                $upload_message = "Success: The file has been uploaded and recipient has been notified by email.";
            } catch (Exception $e) {
                error_log("Email sending failed: {$mail->ErrorInfo}");
                $upload_message = "Success: The file has been uploaded, but email notification failed.";
            }
        } else {
            $upload_message = "Error: There was an error saving to the database.";
        }
        $stmt->close();
    }
}

// Get list of potential recipients
$sql = "SELECT id, name, email FROM users WHERE status = 'approved' AND id != ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

$recipient_options = "";
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $recipient_options .= "<option value='" . $row['id'] . "'>" . $row['name'] . " (" . $row['email'] . ")</option>";
    }
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Upload - SignEase</title>
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
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900" x-data="{ uploadMethod: 'local', isLoading: false, message: '<?php echo addslashes($upload_message); ?>' }">
    <div class="p-4 sm:ml-64">
        <div class="p-4 border-2 border-gray-200 border-dashed rounded-lg dark:border-gray-700">
        <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Document Upload</h1>
                <a href="doc_creator/index.html" 
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md">
                    <i class="fas fa-plus mr-2"></i>
                    Create New Document
                </a>
            </div>
            
            <div x-show="message" x-cloak
                 x-bind:class="{'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100': message.includes('Success'), 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100': message.includes('Error')}"
                 class="mb-6 p-4 rounded-md text-sm font-medium">
                <p x-text="message"></p>
            </div>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data" class="space-y-6" @submit="isLoading = true">
                <div>
                    <label for="upload_method" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Upload Method</label>
                    <select id="upload_method" name="upload_method" x-model="uploadMethod"
                            class="block w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm text-gray-900 dark:text-white">
                        <option value="local">Local File</option>
                        <option value="drive">Google Drive Link</option>
                    </select>
                </div>

                <div x-show="uploadMethod === 'local'">
                    <label for="document" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Upload Document</label>
                    <input type="file" id="document" name="document" x-bind:required="uploadMethod === 'local'"
                           class="block w-full text-sm text-gray-500 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-900 dark:file:text-indigo-200 dark:hover:file:bg-indigo-800">
                </div>

                <div x-show="uploadMethod === 'drive'">
                    <label for="drive_link" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
                        Google Drive Link
                        <button type="button" @click="alert('Please make sure your Google Drive file is publicly accessible:\n\n1. Right-click on the file in Google Drive\n2. Click \'Share\'\n3. Click \'Change to anyone with the link\'\n4. Copy the link and paste it here')"
                                class="ml-2 text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 text-xs underline">
                            (Help)
                        </button>
                    </label>
                    <input type="url" id="drive_link" name="drive_link" x-bind:required="uploadMethod === 'drive'"
                           class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white sm:text-sm">
                </div>

                <div>
                    <label for="recipient" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Select Recipient</label>
                    <select id="recipient" name="recipient" required
                            class="block w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm text-gray-900 dark:text-white">
                        <option value="">Choose a recipient</option>
                        <?php echo $recipient_options; ?>
                    </select>
                </div>

                <div>
                    <p class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-4">Select Requirements</p>
                    <div class="flex flex-wrap gap-8 items-center">
                        <?php
                        $requirements = ['signature' => 'Signature', 'stamp' => 'Stamp', 'date_time' => 'Date & Time', 'text' => 'Text'];
                        foreach ($requirements as $value => $label) {
                            echo "<label class='inline-flex items-center group cursor-pointer'>
                                    <div class='relative'>
                                        <input type='checkbox' 
                                            name='requirements[]' 
                                            value='$value' 
                                            class='peer sr-only'>
                                        <div class='w-5 h-5 bg-white border-2 border-gray-300 rounded-md transition-all duration-300 
                                                peer-checked:bg-indigo-600 peer-checked:border-indigo-600 
                                                hover:border-indigo-500 dark:bg-gray-700 dark:border-gray-600
                                                dark:peer-checked:bg-indigo-500 dark:peer-checked:border-indigo-500 
                                                dark:hover:border-indigo-400'>
                                            <svg class='w-3 h-3 absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 opacity-0 text-white 
                                                    transition-opacity duration-300 peer-checked:opacity-100' 
                                                fill='none' 
                                                viewBox='0 0 24 24' 
                                                stroke='currentColor' 
                                                stroke-width='3'>
                                                <path d='M5 13l4 4L19 7'/>
                                            </svg>
                                        </div>
                                    </div>
                                    <span class='ml-3 text-sm text-gray-700 dark:text-gray-200 transition-colors duration-200 
                                            group-hover:text-indigo-600 dark:group-hover:text-indigo-400'>$label</span>
                                </label>";
                        }
                        ?>
                    </div>
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Additional Description</label>
                    <textarea id="description" name="description" rows="3"
                              class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white sm:text-sm"
                              placeholder="Enter any additional instructions or information here..."></textarea>
                </div>

                <div>
                    <label for="due_date" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Due Date (Optional)</label>
                    <input type="date" id="due_date" name="due_date"
                           class="block w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm text-gray-900 dark:text-white">
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">If not selected, due date will be set to 1 week from today.</p>
                </div>

                <div>
                    <button type="submit"
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-600"
                            x-bind:disabled="isLoading">
                        <svg x-show="isLoading" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span x-text="isLoading ? 'Uploading...' : 'Upload Document'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script src="theme.js"></script>
</body>
</html>