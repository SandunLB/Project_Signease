<?php
include 'admin_sidebar.php';
// Note: admin_sidebar.php already includes config.php and session.php

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login_register.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch current admin data
$stmt = $conn->prepare("SELECT username, email, name FROM users WHERE id = ? AND role = 'admin'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

// Function to validate password complexity
function isPasswordValid($password) {
    // At least 8 characters long, one capital letter, and one symbol
    return (strlen($password) >= 8 && 
            preg_match('/[A-Z]/', $password) && 
            preg_match('/[^A-Za-z0-9]/', $password));
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $name = $_POST['name'];
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate current password
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? AND role = 'admin'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin_data = $result->fetch_assoc();
    $stmt->close();

    if (password_verify($current_password, $admin_data['password'])) {
        // Update admin data
        $update_stmt = $conn->prepare("UPDATE users SET username = ?, name = ? WHERE id = ? AND role = 'admin'");
        $update_stmt->bind_param("ssi", $username, $name, $user_id);
        $update_stmt->execute();
        $update_stmt->close();

        // Update password if provided
        if (!empty($new_password)) {
            if ($new_password === $confirm_password) {
                if (isPasswordValid($new_password)) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $pass_update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'admin'");
                    $pass_update_stmt->bind_param("si", $hashed_password, $user_id);
                    $pass_update_stmt->execute();
                    $pass_update_stmt->close();
                    $success_message = "Admin profile and password updated successfully!";
                } else {
                    $error_message = "New password must be at least 8 characters long, include one capital letter and one symbol.";
                }
            } else {
                $error_message = "New password and confirmation do not match.";
            }
        } else {
            $success_message = "Admin profile updated successfully!";
        }
    } else {
        $error_message = "Current password is incorrect.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Admin Profile - SignEase</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 dark:bg-gray-900">
    <div class="p-4 sm:ml-64">
        <div class="p-4 border-2 border-gray-200 border-dashed rounded-lg dark:border-gray-700">
            <h1 class="text-3xl font-bold mb-6 text-gray-800 dark:text-white">Edit Admin Profile</h1>
            
            <?php if (isset($success_message)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                    <p><?php echo $success_message; ?></p>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                    <p><?php echo $error_message; ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" class="bg-white dark:bg-gray-800 shadow-md rounded px-8 pt-6 pb-8 mb-4">
                <div class="mb-4">
                    <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2" for="username">
                        Username
                    </label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="username" type="text" name="username" value="<?php echo htmlspecialchars($admin['username']); ?>" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2" for="name">
                        Name
                    </label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="name" type="text" name="name" value="<?php echo htmlspecialchars($admin['name']); ?>" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2" for="email">
                        Email (Cannot be changed)
                    </label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="email" type="email" value="<?php echo htmlspecialchars($admin['email']); ?>" disabled>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2" for="current_password">
                        Current Password
                    </label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="current_password" type="password" name="current_password" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2" for="new_password">
                        New Password (Leave blank to keep current password)
                    </label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="new_password" type="password" name="new_password">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Must be at least 8 characters long, include one capital letter and one symbol.</p>
                </div>
                <div class="mb-6">
                    <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2" for="confirm_password">
                        Confirm New Password
                    </label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="confirm_password" type="password" name="confirm_password">
                </div>
                <div class="flex items-center justify-between">
                    <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" type="submit">
                        Update Admin Profile
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script src="theme.js"></script>
</body>
</html>