<?php
include 'config.php';
include 'session.php';

// Fetch user details including role
$stmt = $conn->prepare("SELECT name, email, role FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

function getDocumentsToSignCount($userId) {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) FROM documents WHERE recipient_id = ? AND status = 'sent'");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_row()[0];
    $stmt->close();
    return $count;
}

function getUnreadMessagesCount($userId) {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND `read` = 0");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_row()[0];
    $stmt->close();
    return $count;
}

function getPendingUsersCount() {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE status = 'pending'");
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_row()[0];
    $stmt->close();
    return $count;
}

// Fetch notification counts
$documentsToSignCount = getDocumentsToSignCount($_SESSION['user_id']);
$unreadMessagesCount = getUnreadMessagesCount($_SESSION['user_id']);
$pendingUsersCount = $user['role'] === 'admin' ? getPendingUsersCount() : 0;

// Define shared menu items
$sharedMenuItems = [
    ['url' => 'dashboard.php', 'icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard'],
    ['url' => '#', 'icon' => 'fas fa-file-signature', 'text' => 'eSign', 'submenu' => [
        ['url' => 'upload_documents.php', 'text' => 'Document Upload'],
        ['url' => 'documents_to_sign.php', 'text' => 'Documents to Sign', 'badge' => $documentsToSignCount],
    ]],
    ['url' => 'activity_log.php', 'icon' => 'fas fa-history', 'text' => 'Activity Log'],
    ['url' => 'insights_hub.php', 'icon' => 'fas fa-chart-line', 'text' => 'Insight Hub'],
    ['url' => 'chat.php', 'icon' => 'fas fa-comments', 'text' => 'Live Chat', 'badge' => $unreadMessagesCount],
];

// Define admin-specific menu items
$adminMenuItems = [
    ['url' => 'document_management.php', 'icon' => 'fas fa-folder-open', 'text' => 'Document Management'],
    ['url' => 'user_management.php', 'icon' => 'fas fa-users-cog', 'text' => 'User Management', 'badge' => $pendingUsersCount],
    ['url' => 'reports.php', 'icon' => 'fas fa-chart-bar', 'text' => 'Reports'],
];

// Combine menu items based on user role
$menuItems = $sharedMenuItems;
if ($user['role'] === 'admin') {
    $menuItems = array_merge($menuItems, $adminMenuItems);
}

function renderMenuItem($item, $isAdmin) {
    // Get current page URL for comparison
    $currentPage = basename($_SERVER['PHP_SELF']);
    $isActive = basename($item['url']) === $currentPage;
    
    $submenuHtml = '';
    if (isset($item['submenu'])) {
        $submenuHtml .= '<ul class="ml-4 mt-2 space-y-2 hidden submenu">';
        foreach ($item['submenu'] as $subitem) {
            $isSubActive = basename($subitem['url']) === $currentPage;
            $activeSubClass = $isSubActive ? 'bg-primary/10 dark:bg-primary-dark/20' : '';
            $badgeHtml = isset($subitem['badge']) && $subitem['badge'] > 0 ? '<span class="inline-flex items-center justify-center w-5 h-5 ml-2 text-xs font-semibold text-white bg-red-500 rounded-full">' . $subitem['badge'] . '</span>' : '';
            $submenuHtml .= '<li><a href="' . $subitem['url'] . '" class="flex items-center p-2 text-gray-600 rounded-lg dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 group ' . $activeSubClass . '">' . $subitem['text'] . $badgeHtml . '</a></li>';
        }
        $submenuHtml .= '</ul>';
    }

    // Add active state classes
    $adminClass = $isAdmin ? 'border-l-4 border-primary dark:border-primary-dark pl-3' : '';
    $dropdownClass = isset($item['submenu']) ? 'dropdown-toggle' : '';
    $activeClass = $isActive ? 'bg-primary/10 dark:bg-primary-dark/20 text-primary dark:text-primary-dark' : '';

    $badgeHtml = isset($item['badge']) && $item['badge'] > 0 ? '<span class="inline-flex items-center justify-center w-5 h-5 ml-2 text-xs font-semibold text-white bg-red-500 rounded-full">' . $item['badge'] . '</span>' : '';

    return '
    <li>
        <a href="' . $item['url'] . '" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group ' . $adminClass . ' ' . $dropdownClass . ' ' . $activeClass . '">
            <i class="' . $item['icon'] . ' w-5 h-5 text-gray-500 dark:text-gray-400 group-hover:text-primary dark:group-hover:text-primary-dark transition duration-75 ' . ($isActive ? 'text-primary dark:text-primary-dark' : '') . '"></i>
            <span class="ml-3">' . $item['text'] . '</span>
            ' . $badgeHtml . '
            ' . (isset($item['submenu']) ? '<i class="fas fa-chevron-down ml-auto text-gray-500 dark:text-gray-400 group-hover:text-primary dark:group-hover:text-primary-dark transition-transform duration-200"></i>' : '') . '
        </a>
        ' . $submenuHtml . '
    </li>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SignEase</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            DEFAULT: '#880404',
                            dark: '#a61b1b',    // Lighter shade for dark mode
                            light: '#880404',    // Original shade for light mode
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script src="theme.js"></script>
</head>
<body>
    <div id="sidebar" class="fixed top-0 left-0 z-40 w-64 h-screen transition-transform -translate-x-full sm:translate-x-0">
        <div class="h-full px-3 py-4 overflow-y-auto bg-gray-50 dark:bg-gray-800">
            <div class="flex flex-col items-center justify-center mb-5 pb-3 border-b border-gray-200 dark:border-gray-700">
                <?php if ($user['role'] === 'admin'): ?>
                    <div class="w-16 h-16 bg-primary dark:bg-primary-dark rounded-full flex items-center justify-center mb-2">
                        <i class="fas fa-user-shield text-3xl text-white"></i>
                    </div>
                    <span class="text-xl font-bold text-primary dark:text-primary-dark">SignEase Admin</span>
                <?php else: ?>
                    <span class="text-xl font-semibold text-primary dark:text-primary-dark">SignEase</span>
                <?php endif; ?>
                <button id="sidebarToggle" class="mt-2 text-gray-500 focus:outline-none sm:hidden">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            <ul class="space-y-2 font-medium">
                <?php
                foreach ($menuItems as $item) {
                    echo renderMenuItem($item, $user['role'] === 'admin');
                }
                ?>
                <li>
                    <button id="themeToggle" class="flex items-center w-full p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                        <i id="themeIcon" class="fas fa-sun w-5 h-5 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-primary dark:group-hover:text-primary-dark"></i>
                        <span class="ml-3">Toggle Theme</span>
                    </button>
                </li>
            </ul>
            <div class="pt-4 mt-4 space-y-2 font-medium border-t border-gray-200 dark:border-gray-700">
                <div id="userProfileTrigger" class="flex items-center p-2 text-gray-900 dark:text-white cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 rounded-full bg-primary dark:bg-primary-dark flex items-center justify-center">
                            <span class="text-xl font-semibold text-white"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></span>
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-semibold"><?php echo htmlspecialchars($user['name']); ?></p>
                        <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                </div>
                <div id="userProfileMenu" class="hidden ml-2">
                    <a href="edit_profile.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                        <i class="fas fa-user-edit w-5 h-5 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-primary dark:group-hover:text-primary-dark"></i>
                        <span class="ml-3">Edit Profile</span>
                    </a>
                </div>
                <a href="logout.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                    <i class="fas fa-sign-out-alt w-5 h-5 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-primary dark:group-hover:text-primary-dark"></i>
                    <span class="ml-3">Logout</span>
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const userProfileTrigger = document.getElementById('userProfileTrigger');
            const userProfileMenu = document.getElementById('userProfileMenu');
            const themeToggle = document.getElementById('themeToggle');
            const themeIcon = document.getElementById('themeIcon');
            const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

            userProfileTrigger.addEventListener('click', function() {
                userProfileMenu.classList.toggle('hidden');
            });

            dropdownToggles.forEach(toggle => {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    const submenu = this.nextElementSibling;
                    submenu.classList.toggle('hidden');
                    const chevron = this.querySelector('.fa-chevron-down');
                    chevron.classList.toggle('rotate-180');
                });
            });

            function updateThemeIcon(theme) {
                themeIcon.className = theme === 'dark'
                    ? 'fas fa-moon w-5 h-5 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-primary dark:group-hover:text-primary-dark'
                    : 'fas fa-sun w-5 h-5 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-primary dark:group-hover:text-primary-dark';
            }

            themeToggle.addEventListener('click', function() {
                window.themeUtils.toggleTheme();
            });

            window.addEventListener('themeChanged', (event) => {
                updateThemeIcon(event.detail);
            });

            window.themeUtils.initTheme();
            updateThemeIcon(localStorage.getItem('theme'));
        });
    </script>
</body>
</html>