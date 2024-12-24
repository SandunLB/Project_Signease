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

// Define shared menu items
$sharedMenuItems = [
    ['url' => 'dashboard.php', 'icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard'],
    ['url' => 'esign.php', 'icon' => 'fas fa-file-signature', 'text' => 'eSign', 'submenu' => [
        ['url' => 'upload_documents.php', 'text' => 'Upload Documents'],
        ['url' => 'documents_to_sign.php', 'text' => 'Documents to Sign'],
    ]],
    ['url' => 'insights_hub.php', 'icon' => 'fas fa-chart-line', 'text' => 'Insight Hub'],
    ['url' => 'activity_log.php', 'icon' => 'fas fa-history', 'text' => 'Activity Log'],
    ['url' => 'edit_profile.php', 'icon' => 'fas fa-user-edit', 'text' => 'Edit Profile'],
    ['url' => 'chat.php', 'icon' => 'fas fa-comments', 'text' => 'Live Chat'],
];

// Define admin-specific menu items
$adminMenuItems = [
    ['url' => 'document_management.php', 'icon' => 'fas fa-folder-open', 'text' => 'Document Management'],
    ['url' => 'user_management.php', 'icon' => 'fas fa-users-cog', 'text' => 'User Management'],
    ['url' => 'reports.php', 'icon' => 'fas fa-chart-bar', 'text' => 'Reports'],
];

// Combine menu items based on user role
$menuItems = $sharedMenuItems;
if ($user['role'] === 'admin') {
    $menuItems = array_merge($adminMenuItems, $menuItems);
}

function renderMenuItem($item, $isAdmin) {
    $submenuHtml = '';
    if (isset($item['submenu'])) {
        $submenuHtml .= '<ul class="ml-4 mt-2 space-y-2 hidden">';
        foreach ($item['submenu'] as $subitem) {
            $submenuHtml .= '<li><a href="' . $subitem['url'] . '" class="flex items-center p-2 text-gray-600 rounded-lg dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 group">' . $subitem['text'] . '</a></li>';
        }
        $submenuHtml .= '</ul>';
    }

    $adminClass = $isAdmin ? 'border-l-4 border-indigo-500 pl-3' : '';
    $iconClass = $isAdmin ? 'text-indigo-500' : 'text-gray-500';

    return '
    <li>
        <a href="' . $item['url'] . '" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group ' . $adminClass . '">
            <i class="' . $item['icon'] . ' w-5 h-5 ' . $iconClass . ' transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"></i>
            <span class="ml-3">' . $item['text'] . '</span>
            ' . (isset($item['submenu']) ? '<i class="fas fa-chevron-down ml-auto"></i>' : '') . '
        </a>
        ' . $submenuHtml . '
    </li>';
}
?>

<div id="sidebar" class="fixed top-0 left-0 z-40 w-64 h-screen transition-transform -translate-x-full sm:translate-x-0">
    <div class="h-full px-3 py-4 overflow-y-auto bg-gray-50 dark:bg-gray-800">
        <div class="flex flex-col items-center justify-center mb-5 pb-3 border-b border-gray-200 dark:border-gray-700">
            <?php if ($user['role'] === 'admin'): ?>
                <div class="w-16 h-16 bg-indigo-500 rounded-full flex items-center justify-center mb-2">
                    <i class="fas fa-user-shield text-3xl text-white"></i>
                </div>
                <span class="text-xl font-bold text-indigo-500 dark:text-indigo-400">SignEase Admin</span>
            <?php else: ?>
                <span class="text-xl font-semibold text-gray-800 dark:text-white">SignEase</span>
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
                    <i id="themeIcon" class="fas fa-sun w-5 h-5 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"></i>
                    <span class="ml-3">Toggle Theme</span>
                </button>
            </li>
        </ul>
        <div class="pt-4 mt-4 space-y-2 font-medium border-t border-gray-200 dark:border-gray-700">
            <div id="userProfileTrigger" class="flex items-center p-2 text-gray-900 dark:text-white cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 rounded-full bg-indigo-500 flex items-center justify-center">
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
                    <i class="fas fa-user-edit w-5 h-5 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"></i>
                    <span class="ml-3">Edit Profile</span>
                </a>
            </div>
            <a href="logout.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                <i class="fas fa-sign-out-alt w-5 h-5 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"></i>
                <span class="ml-3">Logout</span>
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const userProfileTrigger = document.getElementById('userProfileTrigger');
    const userProfileMenu = document.getElementById('userProfileMenu');

    userProfileTrigger.addEventListener('click', function() {
        userProfileMenu.classList.toggle('hidden');
    });

    // Close the menu when clicking outside
    document.addEventListener('click', function(event) {
        if (!userProfileTrigger.contains(event.target) && !userProfileMenu.contains(event.target)) {
            userProfileMenu.classList.add('hidden');
        }
    });

    // Toggle submenu visibility
    document.querySelectorAll('a:has(.fa-chevron-down)').forEach(function(el) {
        el.addEventListener('click', function(e) {
            e.preventDefault();
            this.nextElementSibling.classList.toggle('hidden');
        });
    });

    // Theme toggle functionality
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');
    const htmlElement = document.documentElement;

    themeToggle.addEventListener('click', function() {
        htmlElement.classList.toggle('dark');
        if (htmlElement.classList.contains('dark')) {
            themeIcon.classList.remove('fa-sun');
            themeIcon.classList.add('fa-moon');
        } else {
            themeIcon.classList.remove('fa-moon');
            themeIcon.classList.add('fa-sun');
        }
    });
});
</script>