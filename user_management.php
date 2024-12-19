<?php
include 'admin_sidebar.php';
// admin_sidebar.php includes config.php and session.php

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

// Fetch all users
$sql = "SELECT id, name, email, nic, position, faculty, mobile, employee_number, role, status FROM users ORDER BY status DESC, name ASC";
$result = $conn->query($sql);

// Fetch pending users
$sql_pending = "SELECT id, name, email, nic, position, faculty, mobile, employee_number, role FROM users WHERE status = 'pending' ORDER BY name ASC";
$result_pending = $conn->query($sql_pending);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - SignEase</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 dark:bg-gray-900">
    <div class="p-4 sm:ml-64">
        <div class="p-4 border-2 border-gray-200 border-dashed rounded-lg dark:border-gray-700">
            <h1 class="text-3xl font-bold mb-6 text-gray-800 dark:text-white">User Management</h1>
            
            <!-- Tabs -->
            <div class="mb-4">
                <ul class="flex flex-wrap text-sm font-medium text-center text-gray-500 border-b border-gray-200 dark:border-gray-700 dark:text-gray-400">
                    <li class="mr-2">
                        <a href="#" class="inline-block p-4 text-blue-600 bg-gray-100 rounded-t-lg active dark:bg-gray-800 dark:text-blue-500" onclick="showTab('all-users')">All Users</a>
                    </li>
                    <li class="mr-2">
                        <a href="#" class="inline-block p-4 rounded-t-lg hover:text-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 dark:hover:text-gray-300" onclick="showTab('pending-approvals')">Pending Approvals</a>
                    </li>
                </ul>
            </div>

            <!-- All Users Tab -->
            <div id="all-users" class="tab-content">
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
                                            <?php echo $row['status'] === 'approved' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
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
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pending Approvals Tab -->
            <div id="pending-approvals" class="tab-content hidden">
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
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
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
                                <button type="submit" name="update_user" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
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

