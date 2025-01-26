<?php
session_start();
include 'config.php';
include 'mail_config.php';

$message = '';
$message_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset_password'])) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Invalid email format!';
        $message_type = 'error';
    } else {
        // Check if the email exists in the database
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            $user_id = $user['id'];

            // Generate a unique token
            $token = bin2hex(random_bytes(50));
            $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

            // Store the token in the database
            $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $email, $token, $expires);
            $stmt->execute();
            $stmt->close();

            // Send the password reset email
            $mail = configureMailer();
            $mail->addAddress($email);
            $mail->Subject = 'Password Reset Request';
            $reset_link = "http://localhost/project_signease/reset_password.php?token=$token";
            $mail->Body = "Click the following link to reset your password: <a href='$reset_link'>Reset Password</a>";

            if ($mail->send()) {
                $message = 'A password reset link has been sent to your email.';
                $message_type = 'success';
            } else {
                $message = 'Failed to send the password reset email.';
                $message_type = 'error';
            }
        } else {
            $message = 'No user found with this email.';
            $message_type = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - SignEase</title>
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
            <h2 class="text-2xl font-semibold mb-6 relative dark:text-white">Forgot Password</h2>
            <?php if (!empty($message)): ?>
                <div class="p-4 rounded-md shadow-md z-50 
                    <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700 border-green-400' : 
                        ($message_type === 'error' ? 'bg-red-100 text-red-700 border-red-400' : 
                        'bg-yellow-100 text-yellow-700 border-yellow-400'); ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            <form action="forget_password.php" method="post" class="space-y-4">
                <div class="relative">
                    <i class="fas fa-envelope absolute left-3 top-1/2 transform -translate-y-1/2 text-primary dark:text-white"></i>
                    <input type="email" name="email" placeholder="Input email" required class="w-full p-2 pl-10 border-b-2 border-gray-300 focus:border-primary outline-none dark:bg-gray-700 dark:text-white dark:border-gray-600">
                </div>
                <button type="submit" name="reset_password" class="w-full bg-primary text-white p-2 rounded-md hover:bg-secondary transition duration-300 dark:bg-primary dark:hover:bg-secondary">Reset Password</button>
            </form>
            <p class="mt-4 text-sm dark:text-white">Remember your password? <a href="http://localhost/project_signease/login_register.php" class="text-primary dark:text-white hover:underline">Login now</a></p>
        </div>
    </div>
</body>
</html>