<?php
include 'config.php';
include 'session.php';

// Fetch user details
$stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
?>

<div id="sidebar" class="fixed top-0 left-0 z-40 w-64 h-screen transition-transform -translate-x-full sm:translate-x-0">
    <div class="h-full px-3 py-4 overflow-y-auto bg-gray-50 dark:bg-gray-800">
        <div class="flex items-center justify-between mb-5 pb-3 border-b border-gray-200 dark:border-gray-700">
            <span class="text-xl font-semibold text-gray-800 dark:text-white">SignEase</span>
            <button id="sidebarToggle" class="text-gray-500 focus:outline-none sm:hidden">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <ul class="space-y-2 font-medium">
            <li>
                <a href="Upload_Document.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                    <i class="fas fa-upload w-5 h-5 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"></i>
                    <span class="ml-3">Upload Management</span>
                </a>
            </li>
            <li>
                <a href="e-signpage.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                    <i class="fas fa-signature w-5 h-5 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"></i>
                    <span class="ml-3">E-Sign</span>
                </a>
            </li>
            <li>
                <a href="edit_user.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                    <i class="fas fa-user w-5 h-5 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"></i>
                    <span class="ml-3">User</span>
                </a>
            </li>
            <li>
                <a href="activity_log.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                    <i class="fas fa-history w-5 h-5 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"></i>
                    <span class="ml-3">Activity Log</span>
                </a>
            </li>
            <li>
                <a href="support.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                    <i class="fas fa-question-circle w-5 h-5 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"></i>
                    <span class="ml-3">Support</span>
                </a>
            </li>
            <li>
                <a href="message.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                    <i class="fas fa-envelope w-5 h-5 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"></i>
                    <span class="ml-3">Messages</span>
                </a>
            </li>
            <li>
                <a href="feedbacks.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                    <i class="fas fa-comment-alt w-5 h-5 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"></i>
                    <span class="ml-3">Feedback</span>
                </a>
            </li>
            <li>
            <a href="recipient_documents.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                <i class="fas fa-file-signature w-5 h-5 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"></i>
                <span class="ml-3">Documents to Sign</span>
            </a>
            </li>
        </ul>
        <div class="pt-4 mt-4 space-y-2 font-medium border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center p-2 text-gray-900 dark:text-white">
                <div>
                    <p class="text-sm font-semibold"><?php echo htmlspecialchars($user['name']); ?></p>
                    <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($user['email']); ?></p>
                </div>
            </div>
            <a href="logout.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                <i class="fas fa-sign-out-alt w-5 h-5 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"></i>
                <span class="ml-3">Logout</span>
            </a>
        </div>
    </div>
</div>

<script>
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('-translate-x-full');
    });
</script>