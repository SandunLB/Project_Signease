<?php
session_start();
include 'config.php';

$message = '';
$message_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['new_password'])) {
    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Password validation rules
    if ($password !== $confirm_password) {
        $message = 'Passwords do not match!';
        $message_type = 'error';
    } elseif (strlen($password) < 8 || 
              !preg_match('/[A-Z]/', $password) || 
              !preg_match('/[a-z]/', $password) || 
              !preg_match('/[0-9]/', $password) || 
              !preg_match('/[\W_]/', $password)) {
        $message = 'Password must be at least 8 characters long and include one uppercase letter, one lowercase letter, one number, and one special character.';
        $message_type = 'error';
    } else {
        // Check if the token is valid and not expired
        $stmt = $conn->prepare("SELECT email FROM password_resets WHERE token = ? AND expires > NOW()");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $row = $result->fetch_assoc();
            $email = $row['email'];

            // Update the user's password
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->bind_param("ss", $hashed_password, $email);
            $stmt->execute();
            $stmt->close();

            // Delete the token
            $stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $stmt->close();

            $message = 'Your password has been reset successfully.';
            $message_type = 'success';
        } else {
            $message = 'Invalid or expired token.';
            $message_type = 'error';
        }
    }
} elseif (isset($_GET['token'])) {
    $token = $_GET['token'];
} else {
    header("Location: http://localhost/project_signease/login_register.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - SignEase</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
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
</head>
<body class="min-h-screen flex items-center justify-center p-6 bg-gray-100 dark:bg-gray-900 transition-colors duration-300">
    <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl overflow-hidden w-full max-w-md">
        <div class="p-8">
            <h2 class="text-2xl font-semibold mb-6 relative dark:text-white">Reset Password</h2>
            <?php if (!empty($message)): ?>
                <div class="p-4 rounded-md shadow-md z-50 
                    <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700 border-green-400' : 
                        ($message_type === 'error' ? 'bg-red-100 text-red-700 border-red-400' : 
                        'bg-yellow-100 text-yellow-700 border-yellow-400'); ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            <form action="reset_password.php" method="post" class="space-y-4">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <div class="relative">
                    <i class="fas fa-lock absolute left-3 top-1/2 transform -translate-y-1/2 text-primary dark:text-white"></i>
                    <input type="password" name="password" placeholder="New Password" required class="w-full p-2 pl-10 border-b-2 border-gray-300 focus:border-primary outline-none dark:bg-gray-700 dark:text-white dark:border-gray-600">
                </div>
                <div class="relative">
                    <i class="fas fa-lock absolute left-3 top-1/2 transform -translate-y-1/2 text-primary dark:text-white"></i>
                    <input type="password" name="confirm_password" placeholder="Confirm New Password" required class="w-full p-2 pl-10 border-b-2 border-gray-300 focus:border-primary outline-none dark:bg-gray-700 dark:text-white dark:border-gray-600">
                </div>
                <button type="submit" name="new_password" class="w-full bg-primary text-white p-2 rounded-md hover:bg-secondary transition duration-300 dark:bg-primary dark:hover:bg-secondary">Reset Password</button>
            </form>
        </div>
    </div>
</body>
</html>