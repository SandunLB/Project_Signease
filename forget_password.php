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
            $mail->Subject = 'Password Reset Request - SignEase';

            // HTML Email Template
            $reset_link = "http://localhost/project_signease/reset_password.php?token=$token";
            $mail->Body = "
                <html>
                <head>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            background-color: #f4f4f4;
                            color: #333;
                            margin: 0;
                            padding: 0;
                        }
                        .email-container {
                            max-width: 600px;
                            margin: 20px auto;
                            background-color: #fff;
                            border-radius: 8px;
                            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                            overflow: hidden;
                        }
                        .email-header {
                            background-color: #8B0000;
                            color: #fff;
                            text-align: center;
                            padding: 20px;
                        }
                        .email-header h1 {
                            margin: 0;
                            font-size: 24px;
                        }
                        .email-body {
                            padding: 20px;
                        }
                        .email-body p {
                            font-size: 16px;
                            line-height: 1.6;
                        }
                        .email-body a {
                            display: inline-block;
                            margin: 20px 0;
                            padding: 10px 20px;
                            background-color: #8B0000;
                            color: #fff;
                            text-decoration: none;
                            border-radius: 5px;
                        }
                        .email-footer {
                            background-color: #f4f4f4;
                            text-align: center;
                            padding: 10px;
                            font-size: 14px;
                            color: #666;
                        }
                    </style>
                </head>
                <body>
                    <div class='email-container'>
                        <div class='email-header'>
                            <h1>Password Reset Request</h1>
                        </div>
                        <div class='email-body'>
                            <p>Hello,</p>
                            <p>We received a request to reset your password for your SignEase account. Click the button below to reset your password:</p>
                            <a href='$reset_link'>Reset Password</a>
                            <p>If you did not request a password reset, please ignore this email or contact support if you have any questions.</p>
                            <p>This link will expire in 1 hour.</p>
                        </div>
                        <div class='email-footer'>
                            <p>Thank you,<br>The SignEase Team</p>
                        </div>
                    </div>
                </body>
                </html>
            ";

            // Set email content type to HTML
            $mail->isHTML(true);

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

    // Return a JSON response for AJAX
    echo json_encode([
        'message' => $message,
        'message_type' => $message_type
    ]);
    exit();
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
    <style>
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #8B0000;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6 bg-gray-100 dark:bg-gray-900 transition-colors duration-300">
    <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl overflow-hidden w-full max-w-md">
        <div class="p-8">
            <h2 class="text-2xl font-semibold mb-6 relative dark:text-white">Forgot Password</h2>
            <div id="message-container" class="hidden p-4 rounded-md shadow-md z-50 mb-4"></div>
            <form id="reset-form" class="space-y-4">
                <div class="relative">
                    <i class="fas fa-envelope absolute left-3 top-1/2 transform -translate-y-1/2 text-primary dark:text-white"></i>
                    <input type="email" name="email" id="email" placeholder="Input email" required class="w-full p-2 pl-10 border-b-2 border-gray-300 focus:border-primary outline-none dark:bg-gray-700 dark:text-white dark:border-gray-600">
                </div>
                <button type="submit" id="reset-button" class="w-full bg-primary text-white p-2 rounded-md hover:bg-secondary transition duration-300 dark:bg-primary dark:hover:bg-secondary flex items-center justify-center">
                    <span id="button-text">Reset Password</span>
                    <div id="spinner" class="spinner hidden ml-2"></div>
                </button>
            </form>
            <p class="mt-4 text-sm dark:text-white">Remember your password? <a href="http://localhost/project_signease/login_register.php" class="text-primary dark:text-white hover:underline">Login now</a></p>
        </div>
    </div>

    <script>
        document.getElementById('reset-form').addEventListener('submit', async function (event) {
            event.preventDefault(); // Prevent the default form submission

            const button = document.getElementById('reset-button');
            const buttonText = document.getElementById('button-text');
            const spinner = document.getElementById('spinner');
            const messageContainer = document.getElementById('message-container');

            // Disable the button and show the spinner
            button.disabled = true;
            buttonText.textContent = 'Processing...';
            spinner.classList.remove('hidden');

            // Get the form data
            const formData = new FormData(this);
            formData.append('reset_password', 'true');

            try {
                // Send the form data via AJAX
                const response = await fetch('forget_password.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                // Display the message
                messageContainer.textContent = result.message;
                messageContainer.classList.remove('hidden');
                messageContainer.className = `p-4 rounded-md shadow-md z-50 ${
                    result.message_type === 'success' ? 'bg-green-100 text-green-700 border-green-400' :
                    result.message_type === 'error' ? 'bg-red-100 text-red-700 border-red-400' :
                    'bg-yellow-100 text-yellow-700 border-yellow-400'
                }`;
            } catch (error) {
                console.error('Error:', error);
                messageContainer.textContent = 'An error occurred. Please try again.';
                messageContainer.classList.remove('hidden');
                messageContainer.className = 'p-4 rounded-md shadow-md z-50 bg-red-100 text-red-700 border-red-400';
            } finally {
                // Re-enable the button and hide the spinner
                button.disabled = false;
                buttonText.textContent = 'Reset Password';
                spinner.classList.add('hidden');
            }
        });
    </script>
</body>
</html>