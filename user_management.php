<?php
include 'sidebar.php';
// sidebar.php includes config.php and session.php

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login_register.php");
    exit();
}

// Handle user approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    $action = $_POST['action'];
    
    if ($action == 'approve') {
        $sql = "UPDATE users SET status = 'approved' WHERE id = ?";
    } elseif ($action == 'reject') {
        $sql = "DELETE FROM users WHERE id = ?";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

// Handle user update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user'])) {
    $user_id = $_POST['user_id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $nic = $_POST['nic'];
    $position = $_POST['position'];
    $faculty = $_POST['faculty'];
    $mobile = $_POST['mobile'];
    $employee_number = $_POST['employee_number'];
    $role = $_POST['role'];
    $status = $_POST['status'];

    $sql = "UPDATE users SET name = ?, email = ?, nic = ?, position = ?, faculty = ?, mobile = ?, employee_number = ?, role = ?, status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssssi", $name, $email, $nic, $position, $faculty, $mobile, $employee_number, $role, $status, $user_id);
    $stmt->execute();
    $stmt->close();
}

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$currentTab = isset($_GET['tab']) ? $_GET['tab'] : 'all-users';

// Fetch all users with search
$sql = "SELECT id, name, email, nic, position, faculty, mobile, employee_number, role, status FROM users 
        WHERE (name LIKE ? OR email LIKE ? OR nic LIKE ? OR position LIKE ? OR faculty LIKE ?)
        ORDER BY status DESC, name ASC";
$stmt = $conn->prepare($sql);
$searchParam = "%$search%";
$stmt->bind_param("sssss", $searchParam, $searchParam, $searchParam, $searchParam, $searchParam);
$stmt->execute();
$result = $stmt->get_result();

// Fetch pending users with search
$sql_pending = "SELECT id, name, email, nic, position, faculty, mobile, employee_number, role FROM users 
                WHERE status = 'pending' AND (name LIKE ? OR email LIKE ? OR nic LIKE ? OR position LIKE ? OR faculty LIKE ?)
                ORDER BY name ASC";
$stmt_pending = $conn->prepare($sql_pending);
$stmt_pending->bind_param("sssss", $searchParam, $searchParam, $searchParam, $searchParam, $searchParam);
$stmt_pending->execute();
$result_pending = $stmt_pending->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - SignEase</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
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
        <div class="p-4 border-2 border-gray-200 border-dashed rounded-lg dark:border-gray-700 mt-14">
            <h1 class="text-3xl font-bold mb-6 text-gray-800 dark:text-white">User Management</h1>
            
            <!-- Tabs and Search Bar -->
            <div class="flex items-center justify-between mb-4 bg-white dark:bg-gray-800 rounded-lg shadow">
                <ul class="flex flex-wrap text-sm font-medium text-center text-gray-500 dark:text-gray-400">
                    <li class="mr-2">
                        <a href="?tab=all-users<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="inline-block p-4 <?php echo $currentTab === 'all-users' ? 'text-blue-600 bg-gray-100 rounded-tl-lg active dark:bg-gray-800 dark:text-blue-500' : 'hover:text-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 dark:hover:text-gray-300'; ?>">All Users</a>
                    </li>
                    <li class="mr-2">
                        <a href="?tab=pending-approvals<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="inline-block p-4 <?php echo $currentTab === 'pending-approvals' ? 'text-blue-600 bg-gray-100 active dark:bg-gray-800 dark:text-blue-500' : 'hover:text-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 dark:hover:text-gray-300'; ?>">Pending Approvals</a>
                    </li>
                </ul>
                <form action="" method="GET" class="flex items-center p-2 relative group">
                    <input type="hidden" name="tab" value="<?php echo $currentTab; ?>">
                    <input 
                        type="text" 
                        name="search" 
                        placeholder="Search documents..." 
                        value="<?php echo htmlspecialchars($search); ?>" 
                        class="w-10 p-2 border rounded-full transition-all duration-300 focus:w-64 group-hover:w-64 dark:bg-gray-700 dark:text-white opacity-0 focus:opacity-100 group-hover:opacity-100 outline-none border-blue-500"
                    >
                    <button type="submit" class="absolute right-2 bg-blue-500 text-white p-2 rounded-full hover:bg-blue-600 h-10 w-10 flex items-center justify-center">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>

            <!-- All Users Tab -->
            <div id="all-users" class="tab-content <?php echo $currentTab === 'all-users' ? '' : 'hidden'; ?>">
                <div class="overflow-x-auto relative shadow-md sm:rounded-lg">
                    <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                            <tr>
                                <th scope="col" class="py-3 px-6">Name</th>
                                <th scope="col" class="py-3 px-6">Email</th>
                                <th scope="col" class="py-3 px-6">NIC</th>
                                <th scope="col" class="py-3 px-6">Position</th>
                                <th scope="col" class="py-3 px-6">Faculty</th>
                                <th scope="col" class="py-3 px-6">Mobile</th>
                                <th scope="col" class="py-3 px-6">Employee Number</th>
                                <th scope="col" class="py-3 px-6">Role</th>
                                <th scope="col" class="py-3 px-6">Status</th>
                                <th scope="col" class="py-3 px-6">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                        <td class="py-4 px-6"><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td class="py-4 px-6"><?php echo htmlspecialchars($row['email']); ?></td>
                                        <td class="py-4 px-6"><?php echo htmlspecialchars($row['nic']); ?></td>
                                        <td class="py-4 px-6"><?php echo htmlspecialchars($row['position']); ?></td>
                                        <td class="py-4 px-6"><?php echo htmlspecialchars($row['faculty']); ?></td>
                                        <td class="py-4 px-6"><?php echo htmlspecialchars($row['mobile']); ?></td>
                                        <td class="py-4 px-6"><?php echo htmlspecialchars($row['employee_number']); ?></td>
                                        <td class="py-4 px-6"><?php echo htmlspecialchars($row['role']); ?></td>
                                        <td class="py-4 px-6">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?php echo $row['status'] === 'approved' ? 'bg-green-100 text-green-800 dark:bg-green-200 dark:text-green-900' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-200 dark:text-yellow-900'; ?>">
                                                <?php echo ucfirst(htmlspecialchars($row['status'])); ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-6">
                                            <button onclick="openUpdateModal(<?php echo htmlspecialchars(json_encode($row)); ?>)" class="text-blue-600 dark:text-blue-500 hover:underline">
                                                Edit
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                                    <td colspan="10" class="py-4 px-6 text-center">No users found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pending Approvals Tab -->
            <div id="pending-approvals" class="tab-content <?php echo $currentTab === 'pending-approvals' ? '' : 'hidden'; ?>">
                <div class="overflow-x-auto relative shadow-md sm:rounded-lg">
                    <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                            <tr>
                                <th scope="col" class="py-3 px-6">Name</th>
                                <th scope="col" class="py-3 px-6">Email</th>
                                <th scope="col" class="py-3 px-6">NIC</th>
                                <th scope="col" class="py-3 px-6">Position</th>
                                <th scope="col" class="py-3 px-6">Faculty</th>
                                <th scope="col" class="py-3 px-6">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_pending->num_rows > 0): ?>
                                <?php while ($row = $result_pending->fetch_assoc()): ?>
                                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                        <td class="py-4 px-6"><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td class="py-4 px-6"><?php echo htmlspecialchars($row['email']); ?></td>
                                        <td class="py-4 px-6"><?php echo htmlspecialchars($row['nic']); ?></td>
                                        <td class="py-4 px-6"><?php echo htmlspecialchars($row['position']); ?></td>
                                        <td class="py-4 px-6"><?php echo htmlspecialchars($row['faculty']); ?></td>
                                        <td class="py-4 px-6">
                                            <form method="POST" class="inline-block mr-2">
                                                <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="text-white bg-green-700 hover:bg-green-800 focus:ring-4 focus:ring-green-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-green-600 dark:hover:bg-green-700 dark:focus:ring-green-800">
                                                    Approve
                                                </button>
                                            </form>
                                            <form method="POST" class="inline-block">
                                                <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="text-white bg-red-700 hover:bg-red-800 focus:ring-4 focus:ring-red-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-red-600 dark:hover:bg-red-700 dark:focus:ring-red-800">
                                                    Reject
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                                    <td colspan="6" class="py-4 px-6 text-center">No pending approvals found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Update User Modal -->
    <div id="updateModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full dark:bg-gray-800">
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-title">
                        Update User
                    </h3>
                    <div class="mt-2">
                        <form id="updateForm" method="POST">
                            <input type="hidden" id="update_user_id" name="user_id">
                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <label for="update_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                                    <input type="text" id="update_name" name="name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                </div>
                                <div>
                                    <label for="update_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                                    <input type="email" id="update_email" name="email" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                </div>
                                <div>
                                    <label for="update_nic" class="block text-sm font-medium text-gray-700 dark:text-gray-300">NIC</label>
                                    <input type="text" id="update_nic" name="nic" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                </div>
                                <div>
                                    <label for="update_position" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Position</label>
                                    <input type="text" id="update_position" name="position" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                </div>
                                <div>
                                    <label for="update_faculty" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Faculty</label>
                                    <input type="text" id="update_faculty" name="faculty" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                </div>
                                <div>
                                    <label for="update_mobile" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Mobile</label>
                                    <input type="text" id="update_mobile" name="mobile" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                </div>
                                <div>
                                    <label for="update_employee_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Employee Number</label>
                                    <input type="text" id="update_employee_number" name="employee_number" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                </div>
                                <div>
                                    <label for="update_role" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Role</label>
                                    <select id="update_role" name="role" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                        <option value="user">User</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="update_status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                                    <select id="update_status" name="status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                        <option value="pending">Pending</option>
                                        <option value="approved">Approved</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-4">
                                <button type="submit" name="update_user" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-blue-500 dark:hover:bg-blue-600">
                                    Update User
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-800 dark:text-white dark:border-gray-600 dark:hover:bg-gray-700" onclick="closeUpdateModal()">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabId) {
            // This function is no longer needed as we're using server-side tab switching
        }

        function openUpdateModal(user) {
            document.getElementById('update_user_id').value = user.id;
            document.getElementById('update_name').value = user.name;
            document.getElementById('update_email').value = user.email;
            document.getElementById('update_nic').value = user.nic;
            document.getElementById('update_position').value = user.position;
            document.getElementById('update_faculty').value = user.faculty;
            document.getElementById('update_mobile').value = user.mobile;
            document.getElementById('update_employee_number').value = user.employee_number;
            document.getElementById('update_role').value = user.role;
            document.getElementById('update_status').value = user.status;
            document.getElementById('updateModal').classList.remove('hidden');
        }

        function closeUpdateModal() {
            document.getElementById('updateModal').classList.add('hidden');
        }
    </script>
    <script src="theme.js"></script>
</body>
</html>