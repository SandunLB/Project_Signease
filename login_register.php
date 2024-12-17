<?php
session_start();
include 'config.php';

$message = '';
$message_type = '';

// Function to sanitize input
function sanitize_input($input) {
    return htmlspecialchars(trim($input));
}

// Login validation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Invalid email format!';
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("SELECT id, password, role, status FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                if ($user['status'] === 'approved') {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];

                    $log_stmt = $conn->prepare("INSERT INTO login_details (user_id, login_time) VALUES (?, NOW())");
                    $log_stmt->bind_param("i", $user['id']);
                    $log_stmt->execute();
                    $log_stmt->close();

                    header("Location: " . ($user['role'] == 'admin' ? 'admin-dashboard.php' : 'dashboard.php'));
                    exit();
                } else {
                    $message = 'Your account is pending approval. Please wait for admin confirmation.';
                    $message_type = 'warning';
                }
            } else {
                $message = 'Invalid password.';
                $message_type = 'error';
            }
        } else {
            $message = 'No user found with this email.';
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// Registration validation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $name = sanitize_input($_POST['name']);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $nic = sanitize_input($_POST['nic']);
    $position = sanitize_input($_POST['position']);
    $faculty = sanitize_input($_POST['faculty']);
    $mobile = sanitize_input($_POST['mobile']);
    $employee_number = sanitize_input($_POST['employee_number']);
    $role = sanitize_input($_POST['role']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Invalid email format!';
        $message_type = 'error';
    } elseif ($password !== $confirm_password) {
        $message = 'Passwords do not match!';
        $message_type = 'error';
    } elseif (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[\W_]/', $password)) {
        $message = 'Password must be at least 8 characters long and include one uppercase letter, one lowercase letter, one number, and one special character';
        $message_type = 'error';
    } else {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        $conn->begin_transaction();

        try {
            $stmtCheck = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmtCheck->bind_param("s", $email);
            $stmtCheck->execute();
            $stmtCheck->store_result();
            
            if ($stmtCheck->num_rows > 0) {
                throw new Exception("Email already exists. Please use a different email.");
            }

            $stmt1 = $conn->prepare("INSERT INTO users (username, email, password, nic, position, faculty, mobile, employee_number, name, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            $stmt1->bind_param("ssssssssss", $name, $email, $hashed_password, $nic, $position, $faculty, $mobile, $employee_number, $name, $role);
            $stmt1->execute();
            $user_id = $stmt1->insert_id;

            if (!$user_id) {
                throw new Exception("An error occurred. Please try again later.");
            }

            $conn->commit();

            $message = 'Registration successful! Your account is pending approval.';
            $message_type = 'success';
        } catch (Exception $e) {
            $conn->rollback();
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'error';
        }

        if (isset($stmt1)) $stmt1->close();
        if (isset($stmtCheck)) $stmtCheck->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login / Register - SignEase</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <style>
        .flip-container {
            perspective: 1000px;
            width: 100%;
            height: 800px; /* Increased height */
            max-width: 1200px; /* Increased max-width */
        }
        .flipper {
            transition: 0.6s;
            transform-style: preserve-3d;
            position: relative;
            width: 100%;
            height: 100%;
        }
        .front, .back {
            backface-visibility: hidden;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow-y: auto;
        }
        .back {
            transform: rotateY(180deg);
        }
        .flip .flipper {
            transform: rotateY(180deg);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6 bg-gray-100">
    <?php if (!empty($message)): ?>
        <div class="fixed top-5 left-1/2 transform -translate-x-1/2 p-4 rounded-md shadow-md z-50 
            <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700 border-green-400' : 
                ($message_type === 'error' ? 'bg-red-100 text-red-700 border-red-400' : 
                'bg-yellow-100 text-yellow-700 border-yellow-400'); ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="flip-container bg-white rounded-3xl shadow-2xl overflow-hidden w-full">
        <div class="flipper">
            <div class="front w-full h-full bg-white">
                <div class="flex flex-col md:flex-row h-full">
                    <div class="w-full md:w-1/2 p-8 overflow-y-auto">
                        <img src="imgs/logo.jpg" alt="Logo" class="w-64 mb-8">
                        <h2 class="text-2xl font-semibold mb-6 relative after:content-[''] after:absolute after:left-0 after:bottom-0 after:h-1 after:w-8 after:bg-red-900">Login</h2>
                        <form action="login_register.php" method="post" class="space-y-4">
                            <div class="relative">
                                <i class="fas fa-envelope absolute left-3 top-1/2 transform -translate-y-1/2 text-red-900"></i>
                                <input type="email" name="email" placeholder="Input email" required class="w-full p-2 pl-10 border-b-2 border-gray-300 focus:border-red-900 outline-none">
                            </div>
                            <div class="relative">
                                <i class="fas fa-lock absolute left-3 top-1/2 transform -translate-y-1/2 text-red-900"></i>
                                <input type="password" name="password" placeholder="Input password" required class="w-full p-2 pl-10 border-b-2 border-gray-300 focus:border-red-900 outline-none">
                            </div>
                            <div class="text-sm">
                                <a href="forget_password.html" class="text-red-900 hover:underline">Forgot password?</a>
                            </div>
                            <button type="submit" name="login" class="w-full bg-red-900 text-white p-2 rounded-md hover:bg-yellow-500 transition duration-300">Login</button>
                        </form>
                        <p class="mt-4 text-sm">Don't have an account? <a href="#" class="text-red-900 hover:underline" id="show-signup">Signup now</a></p>
                    </div>
                    <div class="hidden md:block w-1/2 relative">
                        <img src="imgs/pic 4.jpg" alt="Cover" class="absolute inset-0 w-full h-full object-cover">
                        <div class="absolute inset-0 bg-black bg-opacity-50 flex flex-col items-center justify-center text-white p-8">
                            <h3 class="text-2xl font-semibold mb-2">Secure Your Documents</h3>
                            <p class="text-center">Digitally sign contracts, agreements, and more with ease.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="back w-full h-full bg-white">
                <div class="flex flex-col md:flex-row h-full">
                    <div class="w-full md:w-1/2 p-8 overflow-y-auto">
                        <h2 class="text-2xl font-semibold mb-6 relative after:content-[''] after:absolute after:left-0 after:bottom-0 after:h-1 after:w-8 after:bg-red-900">Signup</h2>
                        <form action="login_register.php" method="post" class="space-y-4">
                            <div class="relative">
                                <i class="fas fa-user absolute left-3 top-1/2 transform -translate-y-1/2 text-red-900"></i>
                                <input type="text" name="name" placeholder="Your name" required class="w-full p-2 pl-10 border-b-2 border-gray-300 focus:border-red-900 outline-none">
                            </div>
                            <div class="relative">
                                <i class="fas fa-id-card absolute left-3 top-1/2 transform -translate-y-1/2 text-red-900"></i>
                                <input type="text" name="nic" placeholder="Your NIC" required class="w-full p-2 pl-10 border-b-2 border-gray-300 focus:border-red-900 outline-none">
                            </div>
                            <div class="relative">
                                <i class="fas fa-briefcase absolute left-3 top-1/2 transform -translate-y-1/2 text-red-900"></i>
                                <select name="position" id="position" class="w-full p-2 pl-10 border-b-2 border-gray-300 focus:border-red-900 outline-none" required>
                                    <option value="" disabled selected>Select position</option>
                                    <option value="vc">VC</option>
                                    <option value="dvc">DVC</option>
                                    <option value="dean">Dean</option>
                                    <option value="directors">Directors</option>
                                    <option value="registrar/AR">Registrar / Assistant Registrar</option>
                                    <option value="hod">Head of Department(HOD)</option>
                                    <option value="lec/AL">Lecturer / Assistant Lecturer</option>
                                    <option value="B/AB">Bursar / Assistant Bursar</option>
                                    <option value="MA">Management Assistant</option>
                                    <option value="cmo">Chief Medical Officer(CMO)</option>
                                </select>
                            </div>
                            <div class="relative">
                                <i class="fas fa-graduation-cap absolute left-3 top-1/2 transform -translate-y-1/2 text-red-900"></i>
                                <select name="faculty" id="faculty" class="w-full p-2 pl-10 border-b-2 border-gray-300 focus:border-red-900 outline-none" required>
                                    <option value="" disabled selected>Select faculty</option>
                                    <option value="agriculture">Faculty of Agriculture</option>
                                    <option value="allied_health">Faculty of Allied Health Sciences</option>
                                    <option value="arts">Faculty of Arts</option>
                                    <option value="dental">Faculty of Dental Sciences</option>
                                    <option value="engineering">Faculty of Engineering</option>
                                    <option value="management">Faculty of Management</option>
                                    <option value="medicine">Faculty of Medicine</option>
                                    <option value="science">Faculty of Sciences</option>
                                    <option value="veterinary">Faculty of Veterinary Medicine & Animal Science</option>
                                    <option value="pgia">Postgraduate Institute of Agriculture (PGIA)</option>
                                    <option value="pgihs">Postgraduate Institute of Humanities and Social Sciences (PGIHS)</option>
                                    <option value="pgims">Postgraduate Institute of Medical Sciences (PGIMS)</option>
                                    <option value="pgis">Postgraduate Institute of Science (PGIS)</option>
                                </select>
                            </div>
                            <div class="relative">
                                <i class="fas fa-phone absolute left-3 top-1/2 transform -translate-y-1/2 text-red-900"></i>
                                <input type="text" name="mobile" placeholder="Mobile" required class="w-full p-2 pl-10 border-b-2 border-gray-300 focus:border-red-900 outline-none">
                            </div>
                            <div class="relative">
                                <i class="fas fa-id-badge absolute left-3 top-1/2 transform -translate-y-1/2 text-red-900"></i>
                                <input type="text" name="employee_number" placeholder="Your employment number" required class="w-full p-2 pl-10 border-b-2 border-gray-300 focus:border-red-900 outline-none">
                            </div>
                            <div class="relative">
                                <i class="fas fa-user-tag absolute left-3 top-1/2 transform -translate-y-1/2 text-red-900"></i>
                                <select name="role" id="role" class="w-full p-2 pl-10 border-b-2 border-gray-300 focus:border-red-900 outline-none" required>
                                    <option value="" disabled selected>Select Role</option>
                                    <option value="user">User</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div class="relative">
                                <i class="fas fa-envelope absolute left-3 top-1/2 transform -translate-y-1/2 text-red-900"></i>
                                <input type="email" name="email" placeholder="Email" required class="w-full p-2 pl-10 border-b-2 border-gray-300 focus:border-red-900 outline-none">
                            </div>
                            <div class="relative">
                                <i class="fas fa-lock absolute left-3 top-1/2 transform -translate-y-1/2 text-red-900"></i>
                                <input type="password" name="password" placeholder="Password" required class="w-full p-2 pl-10 border-b-2 border-gray-300 focus:border-red-900 outline-none">
                            </div>
                            <div class="relative">
                                <i class="fas fa-lock absolute left-3 top-1/2 transform -translate-y-1/2 text-red-900"></i>
                                <input type="password" name="confirm_password" placeholder="Confirm password" required class="w-full p-2 pl-10 border-b-2 border-gray-300 focus:border-red-900 outline-none">
                            </div>
                            <button type="submit" name="register" class="w-full bg-red-900 text-white p-2 rounded-md hover:bg-yellow-500 transition duration-300">Register</button>
                        </form>
                        <p class="mt-4 text-sm">Already have an account? <a href="#" class="text-red-900 hover:underline" id="show-login">Login now</a></p>
                    </div>
                    <div class="hidden md:block w-1/2 relative">
                        <img src="imgs/h1.jpg" alt="Cover" class="absolute inset-0 w-full h-full object-cover">
                        <div class="absolute inset-0 bg-black bg-opacity-50 flex flex-col items-center justify-center text-white p-8">
                            <h3 class="text-2xl font-semibold mb-2">Sign Ease</h3>
                            <img src="imgs/logo3.png" alt="Logo" class="w-32 mb-4">
                            <p class="text-center">SignEase is a leading electronic signature platform that enables individuals and businesses to sign, send, and manage documents digitally. It provides a secure, convenient, and legally binding way to complete agreements and approvals online, eliminating the need for physical paperwork and manual processes.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('show-signup').addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelector('.flip-container').classList.add('flip');
        });

        document.getElementById('show-login').addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelector('.flip-container').classList.remove('flip');
        });
    </script>
</body>
</html>