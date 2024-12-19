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

// Handle user addition/update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_user'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $nic = $_POST['nic'];
    $position = $_POST['position'];
    $faculty = $_POST['faculty'];
    $mobile = $_POST['mobile'];
    $employee_number = $_POST['employee_number'];
    $role = $_POST['role'];
    $status = $_POST['status'];

    if (isset($_POST['user_id'])) {
        // Update existing user
        $user_id = $_POST['user_id'];
        $sql = "UPDATE users SET name = ?, email = ?, nic = ?, position = ?, faculty = ?, mobile = ?, employee_number = ?, role = ?, status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssssi", $name, $email, $nic, $position, $faculty, $mobile, $employee_number, $role, $status, $user_id);
    } else {
        // Add new user
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (name, email, password, nic, position, faculty, mobile, employee_number, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssss", $name, $email, $password, $nic, $position, $faculty, $mobile, $employee_number, $role, $status);
    }
    
    $stmt->execute();
    $stmt->close();
}

// Fetch all users
$sql = "SELECT id, name, email, nic, position, faculty, mobile, employee_number, role, status FROM users ORDER BY status DESC, name ASC";
$result = $conn->query($sql);
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
            
            <!-- Add/Edit User Form -->
            <div class="mb-8 bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
                <h2 class="text-2xl font-semibold mb-4 text-gray-800 dark:text-white">Add/Edit User</h2>
                <form action="" method="POST">
                    <div class="grid grid-cols-2 gap-4">
                        <input type="hidden" name="user_id" id="user_id">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                            <input type="text" id="name" name="name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                            <input type="email" id="email" name="email" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        </div>
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Password (for new users)</label>
                            <input type="password" id="password" name="password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        </div>
                        <div>
                            <label for="nic" class="block text-sm font-medium text-gray-700 dark:text-gray-300">NIC</label>
                            <input type="text" id="nic" name="nic" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        </div>
                        <div>
                            <label for="position" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Position</label>
                            <input type="text" id="position" name="position" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        </div>
                        <div>
                            <label for="faculty" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Faculty</label>
                            <input type="text" id="faculty" name="faculty" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        </div>
                        <div>
                            <label for="mobile" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Mobile</label>
                            <input type="text" id="mobile" name="mobile" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        </div>
                        <div>
                            <label for="employee_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Employee Number</label>
                            <input type="text" id="employee_number" name="employee_number" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        </div>
                        <div>
                            <label for="role" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Role</label>
                            <select id="role" name="role" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                            <select id="status" name="status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" name="submit_user" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            Submit
                        </button>
                    </div>
                </form>
            </div>

            <!-- User List -->
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
                                <td class="py-4 px-6"><?php echo htmlspecialchars($row['status']); ?></td>
                                <td class="py-4 px-6">
                                    <?php if ($row['status'] === 'pending'): ?>
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
                                    <?php endif; ?>
                                    <button onclick="editUser(<?php echo htmlspecialchars(json_encode($row)); ?>)" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                                        Edit
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
        function editUser(user) {
            document.getElementById('user_id').value = user.id;
            document.getElementById('name').value = user.name;
            document.getElementById('email').value = user.email;
            document.getElementById('nic').value = user.nic;
            document.getElementById('position').value = user.position;
            document.getElementById('faculty').value = user.faculty;
            document.getElementById('mobile').value = user.mobile;
            document.getElementById('employee_number').value = user.employee_number;
            document.getElementById('role').value = user.role;
            document.getElementById('status').value = user.status;
        }
    </script>
    <script src="theme.js"></script>
</body>
</html>