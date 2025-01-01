<?php
include 'sidebar.php';
// Note: sidebar.php already includes config.php and session.php

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login_register.php");
    exit();
}

// Fetch user details including role
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$is_admin = ($user['role'] === 'admin');

// Function to get all FAQs
function getFAQs() {
    global $conn;
    $result = $conn->query("SELECT * FROM faq ORDER BY id DESC");
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to get all feedback (admin only)
function getFeedback() {
    global $conn;
    $result = $conn->query("SELECT f.*, u.name as user_name, u.email FROM feedback f LEFT JOIN users u ON f.user_id = u.id ORDER BY f.created_at DESC");
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Admin functions
if ($is_admin) {
    // Function to add a new FAQ
    function addFAQ($question, $answer) {
        global $conn;
        $stmt = $conn->prepare("INSERT INTO faq (question, answer) VALUES (?, ?)");
        $stmt->bind_param("ss", $question, $answer);
        return $stmt->execute();
    }

    // Function to update an existing FAQ
    function updateFAQ($id, $question, $answer) {
        global $conn;
        $stmt = $conn->prepare("UPDATE faq SET question = ?, answer = ? WHERE id = ?");
        $stmt->bind_param("ssi", $question, $answer, $id);
        return $stmt->execute();
    }

    // Function to delete an FAQ
    function deleteFAQ($id) {
        global $conn;
        $stmt = $conn->prepare("DELETE FROM faq WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    // Handle form submission for adding/editing FAQ
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['add_faq'])) {
            $question = $_POST['question'];
            $answer = $_POST['answer'];
            if (addFAQ($question, $answer)) {
                $success_message = "FAQ added successfully!";
            } else {
                $error_message = "Error adding FAQ. Please try again.";
            }
        } elseif (isset($_POST['edit_faq'])) {
            $id = $_POST['faq_id'];
            $question = $_POST['question'];
            $answer = $_POST['answer'];
            if (updateFAQ($id, $question, $answer)) {
                $success_message = "FAQ updated successfully!";
            } else {
                $error_message = "Error updating FAQ. Please try again.";
            }
        } elseif (isset($_POST['delete_faq'])) {
            $id = $_POST['faq_id'];
            if (deleteFAQ($id)) {
                $success_message = "FAQ deleted successfully!";
            } else {
                $error_message = "Error deleting FAQ. Please try again.";
            }
        }
    }
}

// User function to submit feedback
if (!$is_admin) {
    function submitFeedback($user_id, $subject, $message, $attachment = null) {
        global $conn;
        $stmt = $conn->prepare("INSERT INTO feedback (user_id, subject, message, attachment) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $subject, $message, $attachment);
        return $stmt->execute();
    }

    // Handle feedback submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_feedback'])) {
        $user_id = $_SESSION['user_id'];
        $subject = $_POST['subject'];
        $message = $_POST['message'];
        $attachment = null;

        // Handle file upload
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            $allowed = array('jpg', 'jpeg', 'png', 'gif');
            $filename = $_FILES['attachment']['name'];
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            if (in_array(strtolower($ext), $allowed) && $_FILES['attachment']['size'] <= 5 * 1024 * 1024) {
                $upload_dir = './uploads/feedback_attachments/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $upload_file = $upload_dir . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_file)) {
                    $attachment = $upload_file;
                }
            }
        }

        if (submitFeedback($user_id, $subject, $message, $attachment)) {
            $success_message = "Feedback submitted successfully!";
        } else {
            $error_message = "Error submitting feedback. Please try again.";
        }
    }
}

$faqs = getFAQs();
if ($is_admin) {
    $feedbacks = getFeedback();
}

// Fetch user details for feedback form (if not admin)
if (!$is_admin) {
    $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_admin ? 'Insights Hub - Admin' : 'FAQs & Feedback'; ?> - SignEase</title>
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .fade-in {
            animation: fadeIn 0.5s;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .notification {
            transition: opacity 0.5s, transform 0.5s;
        }
        .notification.show {
            opacity: 1;
            transform: translateY(0);
        }
        .notification.hide {
            opacity: 0;
            transform: translateY(-100%);
        }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900">
    <div class="p-4 sm:ml-64">
        <div class="p-4 border-2 border-gray-200 border-dashed rounded-lg dark:border-gray-700">
            <h1 class="text-3xl font-bold mb-6 text-gray-800 dark:text-white">
                <?php echo $is_admin ? 'Insights Hub' : 'FAQs & Feedback'; ?>
            </h1>
            
            <!-- Notification -->
            <div id="notification" class="fixed top-4 right-4 max-w-sm w-full bg-white dark:bg-gray-800 shadow-lg rounded-lg pointer-events-auto ring-1 ring-black ring-opacity-5 overflow-hidden notification hide">
                <div class="p-4">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-3 w-0 flex-1 pt-0.5">
                            <p id="notificationMessage" class="text-sm font-medium text-gray-900 dark:text-white"></p>
                        </div>
                        <div class="ml-4 flex-shrink-0 flex">
                            <button onclick="hideNotification()" class="bg-white dark:bg-gray-800 rounded-md inline-flex text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <span class="sr-only">Close</span>
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="mb-4">
                <ul class="flex flex-wrap text-sm font-medium text-center text-gray-500 border-b border-gray-200 dark:border-gray-700 dark:text-gray-400">
                    <li class="mr-2">
                        <a href="#" class="inline-block p-4 text-blue-600 bg-gray-100 rounded-t-lg active dark:bg-gray-800 dark:text-blue-500" data-tab="faq-section">FAQs</a>
                    </li>
                    <li class="mr-2">
                        <a href="#" class="inline-block p-4 rounded-t-lg hover:text-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 dark:hover:text-gray-300" data-tab="<?php echo $is_admin ? 'feedback-section' : 'submit-feedback-section'; ?>">
                            <?php echo $is_admin ? 'Feedback' : 'Submit Feedback'; ?>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- FAQ Section -->
            <div id="faq-section" class="tab-content active fade-in">
                <h2 class="text-2xl font-bold mb-4 text-gray-800 dark:text-white">
                    <?php echo $is_admin ? 'FAQs' : 'Frequently Asked Questions'; ?>
                </h2>
                <?php if ($is_admin): ?>
                    <?php if (empty($faqs)): ?>
                        <div class="text-center py-8">
                            <p class="text-gray-600 dark:text-gray-400 mb-4">No FAQs available. Add your first FAQ!</p>
                            <button onclick="openFAQModal()" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded transition duration-300 ease-in-out transform hover:scale-105 dark:bg-blue-600 dark:hover:bg-blue-700">
                                <i class="fas fa-plus mr-2"></i>Add FAQ
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="mb-4 text-right">
                            <button onclick="openFAQModal()" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded transition duration-300 ease-in-out transform hover:scale-105 dark:bg-blue-600 dark:hover:bg-blue-700">
                                <i class="fas fa-plus mr-2"></i>Add FAQ
                            </button>
                        </div>
                        <div class="space-y-4">
                            <?php foreach ($faqs as $faq): ?>
                                <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-4 transition duration-300 ease-in-out hover:shadow-lg">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2"><?php echo htmlspecialchars($faq['question']); ?></h3>
                                    <p class="text-gray-700 dark:text-gray-300 mb-4"><?php echo htmlspecialchars($faq['answer']); ?></p>
                                    <div class="flex justify-end space-x-2">
                                        <button onclick="openFAQModal(<?php echo htmlspecialchars(json_encode($faq)); ?>)" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200 transition duration-300 ease-in-out">
                                            <i class="fas fa-edit mr-1"></i>Edit
                                        </button>
                                        <button onclick="deleteFAQ(<?php echo $faq['id']; ?>)" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-200 transition duration-300 ease-in-out">
                                            <i class="fas fa-trash-alt mr-1"></i>Delete
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="mb-4">
                        <input type="text" id="faq-search" placeholder="Search FAQs..." class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:placeholder-gray-400">
                    </div>
                    <?php if (empty($faqs)): ?>
                        <p class="text-gray-600 dark:text-gray-400">No FAQs available at the moment.</p>
                    <?php else: ?>
                        <div id="faq-list" class="space-y-4">
                            <?php foreach ($faqs as $faq): ?>
                                <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-4">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2"><?php echo htmlspecialchars($faq['question']); ?></h3>
                                    <p class="text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($faq['answer']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Feedback Section (Admin) / Submit Feedback Section (User) -->
            <div id="<?php echo $is_admin ? 'feedback-section' : 'submit-feedback-section'; ?>" class="tab-content fade-in">
                <h2 class="text-2xl font-bold mb-4 text-gray-800 dark:text-white">
                    <?php echo $is_admin ? 'Feedback' : 'Submit Feedback'; ?>
                </h2>
                <?php if ($is_admin): ?>
                    <div class="overflow-x-auto relative shadow-md sm:rounded-lg">
                        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="py-3 px-6">Name</th>
                                    <th scope="col" class="py-3 px-6">Email</th>
                                    <th scope="col" class="py-3 px-6">Subject</th>
                                    <th scope="col" class="py-3 px-6">Message</th>
                                    <th scope="col" class="py-3 px-6">Date</th>
                                    <th scope="col" class="py-3 px-6">Attachment</th>
                                    <th scope="col" class="py-3 px-6">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($feedbacks as $feedback): ?>
                                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition duration-300 ease-in-out">
                                        <td class="py-4 px-6"><?php echo htmlspecialchars($feedback['user_name'] ?? 'N/A'); ?></td>
                                        <td class="py-4 px-6"><?php echo htmlspecialchars($feedback['email'] ?? 'N/A'); ?></td>
                                        <td class="py-4 px-6"><?php echo htmlspecialchars($feedback['subject']); ?></td>
                                        <td class="py-4 px-6"><?php echo htmlspecialchars(substr($feedback['message'], 0, 50)) . '...'; ?></td>
                                        <td class="py-4 px-6"><?php echo htmlspecialchars($feedback['created_at']); ?></td>
                                        <td class="py-4 px-6">
                                            <?php if ($feedback['attachment']): ?>
                                                <a href="<?php echo htmlspecialchars($feedback['attachment']); ?>" target="_blank" class="text-blue-600 hover:underline dark:text-blue-400">View</a>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-6">
                                            <button onclick="viewFeedbackDetails(<?php echo htmlspecialchars(json_encode($feedback)); ?>)" class="font-medium text-blue-600 dark:text-blue-500 hover:underline transition duration-300 ease-in-out">View Details</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <form method="POST" enctype="multipart/form-data" class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-6">
                        <div class="mb-4">
                            <label for="name" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Name</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" readonly class="bg-gray-100 shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 leading-tight focus:outline-none focus:shadow-outline dark:bg-gray-700 dark:border-gray-600 cursor-not-allowed">
                        </div>
                        <div class="mb-4">
                            <label for="email" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly class="bg-gray-100 shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 leading-tight focus:outline-none focus:shadow-outline dark:bg-gray-700 dark:border-gray-600 cursor-not-allowed">
                        </div>
                        <div class="mb-4">
                            <label for="subject" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Subject</label>
                            <input type="text" id="subject" name="subject" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 leading-tight focus:outline-none focus:shadow-outline dark:bg-gray-700 dark:border-gray-600">
                        </div>
                        <div class="mb-4">
                            <label for="message" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Message</label>
                            <textarea id="message" name="message" required rows="4" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 leading-tight focus:outline-none focus:shadow-outline dark:bg-gray-700 dark:border-gray-600"></textarea>
                        </div>
                        <div class="mb-6">
                            <label for="attachment" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Attachment (Optional, max 5MB)</label>
                            <input type="file" id="attachment" name="attachment" accept="image/*" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 leading-tight focus:outline-none focus:shadow-outline dark:bg-gray-700 dark:border-gray-600">
                        </div>
                        <div class="flex items-center justify-between">
                            <button type="submit" name="submit_feedback" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300 ease-in-out transform hover:scale-105 dark:bg-blue-600 dark:hover:bg-blue-800">
                                Submit Feedback
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($is_admin): ?>
    <!-- FAQ Modal -->
    <div id="faqModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form id="faqForm" method="POST" class="w-full">
                    <div class="px-4 pt-5 pb-4 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4" id="modal-title">
                            Add/Edit FAQ
                        </h3>
                        <div class="mt-2 space-y-4">
                            <input type="hidden" id="faqId" name="faq_id">
                            <div>
                                <label for="question" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Question</label>
                                <input type="text" id="question" name="question" class="mt-1 block w-full rounded-md bg-gray-100 dark:bg-gray-700 border-transparent focus:border-blue-500 focus:bg-white dark:focus:bg-gray-900 focus:ring-0 text-gray-900 dark:text-white transition duration-300 ease-in-out" required>
                            </div>
                            <div>
                                <label for="answer" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Answer</label>
                                <textarea id="answer" name="answer" rows="4" class="mt-1 block w-full rounded-md bg-gray-100 dark:bg-gray-700 border-transparent focus:border-blue-500 focus:bg-white dark:focus:bg-gray-900 focus:ring-0 text-gray-900 dark:text-white transition duration-300 ease-in-out" required></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" name="add_faq" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm transition duration-300 ease-in-out transform hover:scale-105 dark:bg-blue-500 dark:hover:bg-blue-600">
                            Save
                        </button>
                        <button type="button" onclick="closeFAQModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-600 dark:text-white dark:border-gray-500 dark:hover:bg-gray-700 transition duration-300 ease-in-out transform hover:scale-105">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Feedback Details Modal -->
    <div id="feedbackModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-title">
                        Feedback Details
                    </h3>
                    <div class="mt-2">
                        <p id="feedbackName" class="text-sm text-gray-500 dark:text-gray-400"></p>
                        <p id="feedbackEmail" class="text-sm text-gray-500 dark:text-gray-400"></p>
                        <p id="feedbackSubject" class="text-sm text-gray-500 dark:text-gray-400"></p>
                        <p id="feedbackMessage" class="text-sm text-gray-500 dark:text-gray-400"></p>
                        <p id="feedbackDate" class="text-sm text-gray-500 dark:text-gray-400"></p>
                        <p id="feedbackAttachment" class="text-sm text-gray-500 dark:text-gray-400"></p>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-800 dark:text-white dark:border-gray-600 dark:hover:bg-gray-700 transition duration-300 ease-in-out transform hover:scale-105" onclick="closeFeedbackModal()">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('[data-tab]');
            tabs.forEach(tab => {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    const tabId = this.getAttribute('data-tab');
                    showTab(tabId);
                });
            });

            <?php if (isset($success_message)): ?>
                showNotification("<?php echo $success_message; ?>", 'success');
            <?php elseif (isset($error_message)): ?>
                showNotification("<?php echo $error_message; ?>", 'error');
            <?php endif; ?>

            <?php if (!$is_admin): ?>
            // FAQ Search functionality
            const faqSearch = document.getElementById('faq-search');
            const faqList = document.getElementById('faq-list');
            const faqs = faqList.querySelectorAll('div');

            faqSearch.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                faqs.forEach(faq => {
                    const question = faq.querySelector('h3').textContent.toLowerCase();
                    const answer = faq.querySelector('p').textContent.toLowerCase();
                    if (question.includes(searchTerm) || answer.includes(searchTerm)) {
                        faq.style.display = 'block';
                    } else {
                        faq.style.display = 'none';
                    }
                });
            });
            <?php endif; ?>
        });

        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.getElementById(tabId).classList.add('active');

            document.querySelectorAll('[data-tab]').forEach(link => {
                link.classList.remove('text-blue-600', 'bg-gray-100', 'dark:bg-gray-800', 'dark:text-blue-500');
                link.classList.add('hover:text-gray-600', 'hover:bg-gray-50', 'dark:hover:bg-gray-800', 'dark:hover:text-gray-300');
            });
            document.querySelector(`[data-tab="${tabId}"]`).classList.add('text-blue-600', 'bg-gray-100', 'dark:bg-gray-800', 'dark:text-blue-500');
            document.querySelector(`[data-tab="${tabId}"]`).classList.remove('hover:text-gray-600', 'hover:bg-gray-50', 'dark:hover:bg-gray-800', 'dark:hover:text-gray-300');
        }

        function showNotification(message, type) {
            const notification = document.getElementById('notification');
            const notificationMessage = document.getElementById('notificationMessage');
            notificationMessage.textContent = message;
            notification.classList.remove('hide');
            notification.classList.add('show');

            if (type === 'error') {
                notification.querySelector('svg').classList.remove('text-green-400');
                notification.querySelector('svg').classList.add('text-red-400');
            } else {
                notification.querySelector('svg').classList.remove('text-red-400');
                notification.querySelector('svg').classList.add('text-green-400');
            }

            setTimeout(() => {
                hideNotification();
            }, 5000);
        }

        function hideNotification() {
            const notification = document.getElementById('notification');
            notification.classList.remove('show');
            notification.classList.add('hide');
        }

        <?php if ($is_admin): ?>
        function openFAQModal(faq = null) {
            const modal = document.getElementById('faqModal');
            const form = document.getElementById('faqForm');
            const titleElement = modal.querySelector('#modal-title');
            const questionInput = document.getElementById('question');
            const answerInput = document.getElementById('answer');
            const faqIdInput = document.getElementById('faqId');

            if (faq) {
                titleElement.textContent = 'Edit FAQ';
                questionInput.value = faq.question;
                answerInput.value = faq.answer;
                faqIdInput.value = faq.id;
                form.querySelector('button[type="submit"]').name = 'edit_faq';
            } else {
                titleElement.textContent = 'Add FAQ';
                form.reset();
                faqIdInput.value = '';
                form.querySelector('button[type="submit"]').name = 'add_faq';
            }

            modal.classList.remove('hidden');
        }

        function closeFAQModal() {
            document.getElementById('faqModal').classList.add('hidden');
        }

        function deleteFAQ(id) {
            if (confirm('Are you sure you want to delete this FAQ?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="delete_faq" value="1">
                    <input type="hidden" name="faq_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function viewFeedbackDetails(feedback) {
            document.getElementById('feedbackName').textContent = `Name: ${feedback.user_name || 'N/A'}`;
            document.getElementById('feedbackEmail').textContent = `Email: ${feedback.email || 'N/A'}`;
            document.getElementById('feedbackSubject').textContent = `Subject: ${feedback.subject}`;
            document.getElementById('feedbackMessage').textContent = `Message: ${feedback.message}`;
            document.getElementById('feedbackDate').textContent = `Date: ${feedback.created_at}`;
            document.getElementById('feedbackAttachment').innerHTML = feedback.attachment ? 
                `Attachment: <a href="${feedback.attachment}" target="_blank" class="text-blue-600 hover:underline dark:text-blue-400">View Attachment</a>` : 
                'No attachment';
            document.getElementById('feedbackModal').classList.remove('hidden');
        }

        function closeFeedbackModal() {
            document.getElementById('feedbackModal').classList.add('hidden');
        }
        <?php endif; ?>
    </script>
    <script src="theme.js"></script>
</body>
</html>