<?php
include 'admin_sidebar.php';
// admin_sidebar.php includes config.php and session.php

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login_register.php");
    exit();
}

// Function to add a new FAQ
function addFAQ($question, $answer) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO faq (question, answer) VALUES (?, ?)");
    $stmt->bind_param("ss", $question, $answer);
    return $stmt->execute();
}

// Function to get all FAQs
function getFAQs() {
    global $conn;
    $result = $conn->query("SELECT * FROM faq ORDER BY id DESC");
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to get all feedback
function getFeedback() {
    global $conn;
    $result = $conn->query("SELECT f.*, u.name as user_name FROM feedback f LEFT JOIN users u ON f.user_id = u.id ORDER BY f.created_at DESC");
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Handle form submission for adding FAQ
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_faq'])) {
    $question = $_POST['question'];
    $answer = $_POST['answer'];
    if (addFAQ($question, $answer)) {
        $success_message = "FAQ added successfully!";
    } else {
        $error_message = "Error adding FAQ. Please try again.";
    }
}

// Fetch FAQs and Feedback
$faqs = getFAQs();
$feedbacks = getFeedback();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insights Hub - SignEase</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 dark:bg-gray-900">
    <div class="p-4 sm:ml-64">
        <div class="p-4 border-2 border-gray-200 border-dashed rounded-lg dark:border-gray-700">
            <h1 class="text-3xl font-bold mb-6 text-gray-800 dark:text-white">Insights Hub</h1>
            
            <!-- Tabs -->
            <div class="mb-4">
                <ul class="flex flex-wrap text-sm font-medium text-center text-gray-500 border-b border-gray-200 dark:border-gray-700 dark:text-gray-400">
                    <li class="mr-2">
                        <a href="#" class="inline-block p-4 text-blue-600 bg-gray-100 rounded-t-lg active dark:bg-gray-800 dark:text-blue-500" onclick="showTab('add-faq')">Add FAQ</a>
                    </li>
                    <li class="mr-2">
                        <a href="#" class="inline-block p-4 rounded-t-lg hover:text-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 dark:hover:text-gray-300" onclick="showTab('view-faqs')">View FAQs</a>
                    </li>
                    <li class="mr-2">
                        <a href="#" class="inline-block p-4 rounded-t-lg hover:text-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 dark:hover:text-gray-300" onclick="showTab('view-feedback')">View Feedback</a>
                    </li>
                </ul>
            </div>

            <!-- Add FAQ Section -->
            <div id="add-faq" class="tab-content">
                <h2 class="text-2xl font-bold mb-4 text-gray-800 dark:text-white">Add New FAQ</h2>
                <?php if (isset($success_message)): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline"><?php echo $success_message; ?></span>
                    </div>
                <?php endif; ?>
                <?php if (isset($error_message)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline"><?php echo $error_message; ?></span>
                    </div>
                <?php endif; ?>
                <form action="" method="POST" class="space-y-4">
                    <div>
                        <label for="question" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Question</label>
                        <input type="text" id="question" name="question" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" required>
                    </div>
                    <div>
                        <label for="answer" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Answer</label>
                        <textarea id="answer" name="answer" rows="4" class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" required></textarea>
                    </div>
                    <button type="submit" name="add_faq" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">Add FAQ</button>
                </form>
            </div>

            <!-- View FAQs Section -->
            <div id="view-faqs" class="tab-content hidden">
                <h2 class="text-2xl font-bold mb-4 text-gray-800 dark:text-white">View FAQs</h2>
                <div class="space-y-4">
                    <?php foreach ($faqs as $faq): ?>
                        <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2"><?php echo htmlspecialchars($faq['question']); ?></h3>
                            <p class="text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($faq['answer']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- View Feedback Section -->
            <div id="view-feedback" class="tab-content hidden">
                <h2 class="text-2xl font-bold mb-4 text-gray-800 dark:text-white">View Feedback</h2>
                <div class="overflow-x-auto relative shadow-md sm:rounded-lg">
                    <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                            <tr>
                                <th scope="col" class="py-3 px-6">Name</th>
                                <th scope="col" class="py-3 px-6">Email</th>
                                <th scope="col" class="py-3 px-6">Subject</th>
                                <th scope="col" class="py-3 px-6">Message</th>
                                <th scope="col" class="py-3 px-6">Date</th>
                                <th scope="col" class="py-3 px-6">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feedbacks as $feedback): ?>
                                <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                    <td class="py-4 px-6"><?php echo htmlspecialchars($feedback['name']); ?></td>
                                    <td class="py-4 px-6"><?php echo htmlspecialchars($feedback['email']); ?></td>
                                    <td class="py-4 px-6"><?php echo htmlspecialchars($feedback['subject']); ?></td>
                                    <td class="py-4 px-6"><?php echo htmlspecialchars(substr($feedback['message'], 0, 50)) . '...'; ?></td>
                                    <td class="py-4 px-6"><?php echo htmlspecialchars($feedback['created_at']); ?></td>
                                    <td class="py-4 px-6">
                                        <button onclick="viewFeedbackDetails(<?php echo htmlspecialchars(json_encode($feedback)); ?>)" class="font-medium text-blue-600 dark:text-blue-500 hover:underline">View Details</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Feedback Details Modal -->
    <div id="feedbackModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
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
                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-800 dark:text-white dark:border-gray-600 dark:hover:bg-gray-700" onclick="closeFeedbackModal()">
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

        function viewFeedbackDetails(feedback) {
            document.getElementById('feedbackName').textContent = `Name: ${feedback.name}`;
            document.getElementById('feedbackEmail').textContent = `Email: ${feedback.email}`;
            document.getElementById('feedbackSubject').textContent = `Subject: ${feedback.subject}`;
            document.getElementById('feedbackMessage').textContent = `Message: ${feedback.message}`;
            document.getElementById('feedbackDate').textContent = `Date: ${feedback.created_at}`;
            document.getElementById('feedbackAttachment').textContent = feedback.attachment ? `Attachment: ${feedback.attachment}` : 'No attachment';
            document.getElementById('feedbackModal').classList.remove('hidden');
        }

        function closeFeedbackModal() {
            document.getElementById('feedbackModal').classList.add('hidden');
        }
    </script>
    <script src="theme.js"></script>
</body>
</html>