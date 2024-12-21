<?php
include 'sidebar.php';
// Note: sidebar.php already includes config.php and session.php

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login_register.php");
    exit();
}

// Function to get all FAQs
function getFAQs() {
    global $conn;
    $result = $conn->query("SELECT * FROM faq ORDER BY id DESC");
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to submit feedback
function submitFeedback($user_id, $subject, $message, $attachment = null) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO feedback (user_id, subject, message, attachment) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $subject, $message, $attachment);
    return $stmt->execute();
}

$faqs = getFAQs();

// Fetch user details
$stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQs & Feedback - SignEase</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
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
            <h1 class="text-3xl font-bold mb-6 text-gray-800 dark:text-white">FAQs & Feedback</h1>
            
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
                        <a href="#" class="inline-block p-4 rounded-t-lg hover:text-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 dark:hover:text-gray-300" data-tab="feedback-section">Submit Feedback</a>
                    </li>
                </ul>
            </div>

            <!-- FAQ Section -->
            <div id="faq-section" class="tab-content active">
                <h2 class="text-2xl font-bold mb-4 text-gray-800 dark:text-white">Frequently Asked Questions</h2>
                <div class="mb-4">
                    <input type="text" id="faq-search" placeholder="Search FAQs..." class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
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
            </div>

            <!-- Feedback Section -->
            <div id="feedback-section" class="tab-content">
                <h2 class="text-2xl font-bold mb-4 text-gray-800 dark:text-white">Submit Feedback</h2>
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
                        <button type="submit" name="submit_feedback" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300 ease-in-out transform hover:scale-105">
                            Submit Feedback
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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

            <?php if (isset($success_message)): ?>
                showNotification("<?php echo $success_message; ?>", 'success');
            <?php elseif (isset($error_message)): ?>
                showNotification("<?php echo $error_message; ?>", 'error');
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
    </script>
    <script src="theme.js"></script>
</body>
</html>