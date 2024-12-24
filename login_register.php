<?php
session_start();
include 'config.php';

$message = '';
$message_type = '';

// Function to sanitize input
function sanitize_input($input) {
  return htmlspecialchars(trim($input));
}

// Initialize variables to store form data
$login_email = '';
$register_data = [
  'name' => '',
  'email' => '',
  'nic' => '',
  'position' => '',
  'faculty' => '',
  'mobile' => '',
  'employee_number' => '',
  'role' => ''
];

// Login validation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
  $login_email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
  $password = $_POST['password'];

  if (!filter_var($login_email, FILTER_VALIDATE_EMAIL)) {
      $message = 'Invalid email format!';
      $message_type = 'error';
  } else {
      $stmt = $conn->prepare("SELECT id, password, role, status FROM users WHERE email = ?");
      $stmt->bind_param("s", $login_email);
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

                  header("Location: " . ($user['role'] == 'admin' ? 'dashboard.php' : 'dashboard.php'));
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
  $register_data = [
      'name' => sanitize_input($_POST['name']),
      'email' => filter_var($_POST['email'], FILTER_SANITIZE_EMAIL),
      'password' => $_POST['password'],
      'confirm_password' => $_POST['confirm_password'],
      'nic' => sanitize_input($_POST['nic']),
      'position' => sanitize_input($_POST['position']),
      'faculty' => sanitize_input($_POST['faculty']),
      'mobile' => sanitize_input($_POST['mobile']),
      'employee_number' => sanitize_input($_POST['employee_number']),
      'role' => sanitize_input($_POST['role'])
  ];

  if (!filter_var($register_data['email'], FILTER_VALIDATE_EMAIL)) {
      $message = 'Invalid email format!';
      $message_type = 'error';
  } elseif ($register_data['password'] !== $register_data['confirm_password']) {
      $message = 'Passwords do not match!';
      $message_type = 'error';
  } elseif (strlen($register_data['password']) < 8 || !preg_match('/[A-Z]/', $register_data['password']) || !preg_match('/[a-z]/', $register_data['password']) || !preg_match('/[0-9]/', $register_data['password']) || !preg_match('/[\W_]/', $register_data['password'])) {
      $message = 'Password must be at least 8 characters long and include one uppercase letter, one lowercase letter, one number, and one special character';
      $message_type = 'error';
  } else {
      $hashed_password = password_hash($register_data['password'], PASSWORD_BCRYPT);
      $status = 'pending'; // Added status variable

      $conn->begin_transaction();

      try {
          $stmtCheck = $conn->prepare("SELECT id FROM users WHERE email = ?");
          $stmtCheck->bind_param("s", $register_data['email']);
          $stmtCheck->execute();
          $stmtCheck->store_result();
          
          if ($stmtCheck->num_rows > 0) {
              throw new Exception("Email already exists. Please use a different email.");
          }

          $stmt1 = $conn->prepare("INSERT INTO users (username, email, password, nic, position, faculty, mobile, employee_number, name, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
          $stmt1->bind_param("sssssssssss", $register_data['name'], $register_data['email'], $hashed_password, $register_data['nic'], $register_data['position'], $register_data['faculty'], $register_data['mobile'], $register_data['employee_number'], $register_data['name'], $register_data['role'], $status); // Updated bind_param
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
      .flip-container {
          perspective: 1000px;
          width: 100%;
          height: 800px;
          max-width: 1200px;
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
      @keyframes fadeOut {
          from { opacity: 1; }
          to { opacity: 0; }
      }
      .fade-out {
          animation: fadeOut 0.5s ease-out forwards;
      }
      @keyframes spin {
          from { transform: rotate(0deg); }
          to { transform: rotate(360deg); }
      }
      .animate-spin {
          animation: spin 0.3s linear;
      }
  </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6 bg-gray-100 dark:bg-gray-900 transition-colors duration-300">
  <button id="theme-toggle" class="fixed top-4 left-4 z-50 w-10 h-10 rounded-full bg-gray-200 dark:bg-gray-800 flex items-center justify-center transition-all duration-300 shadow-lg">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-6 h-6 text-yellow-500 hidden dark:block">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
      </svg>
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-6 h-6 text-gray-800 dark:hidden">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
      </svg>
  </button>
  <?php if (!empty($message)): ?>
      <div id="notification" class="fixed top-5 left-1/2 transform -translate-x-1/2 p-4 rounded-md shadow-md z-50 
          <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700 border-green-400' : 
              ($message_type === 'error' ? 'bg-red-100 text-red-700 border-red-400' : 
              'bg-yellow-100 text-yellow-700 border-yellow-400'); ?>">
          <?php echo $message; ?>
      </div>
  <?php endif; ?>

  <div class="flip-container bg-white dark:bg-gray-800 rounded-3xl shadow-2xl overflow-hidden w-full">
      <div class="flipper">
          <div class="front w-full h-full bg-white dark:bg-gray-800">
              <div class="flex flex-col md:flex-row h-full">
                  <div class="w-full md:w-1/2 p-8 overflow-y-auto">
                      <img src="imgs/logo.png" alt="Logo" class="w-64 mb-8">
                      <h2 class="text-2xl font-semibold mb-6 relative dark:text-white">Login</h2>
                      <form action="login_register.php" method="post" class="space-y-4">
                          <div class="relative">
                              <i class="fas fa-envelope absolute left-3 top-1/2 transform -translate-y-1/2 text-primary dark:text-white"></i>
                              <input type="email" name="email" placeholder="Input email" required class="w-full p-2 pl-10 border-b-2 border-gray-300 focus:border-primary outline-none dark:bg-gray-700 dark:text-white dark:border-gray-600" value="<?php echo $login_email; ?>">
                          </div>
                          <div class="relative">
                              <i class="fas fa-lock absolute left-3 top-1/2 transform -translate-y-1/2 text-primary dark:text-white"></i>
                              <input type="password" name="password" placeholder="Input password" required class="w-full p-2 pl-10 border-b-2 border-gray-300 focus:border-primary outline-none dark:bg-gray-700 dark:text-white dark:border-gray-600">
                          </div>
                          <div class="text-sm dark:text-white">
                              <a href="forget_password.html" class="text-primary dark:text-white hover:underline">Forgot password?</a>
                          </div>
                          <button type="submit" name="login" class="w-full bg-primary text-white p-2 rounded-md hover:bg-secondary transition duration-300 dark:bg-primary dark:hover:bg-secondary">Login</button>
                      </form>
                      <p class="mt-4 text-sm dark:text-white">Don't have an account? <a href="#" class="text-primary dark:text-white hover:underline" id="show-signup">Signup now</a></p>
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
          <div class="back w-full h-full bg-white dark:bg-gray-800">
              <div class="flex flex-col md:flex-row h-full">
                  <div class="w-full md:w-1/2 p-8 overflow-y-auto">
                      <h2 class="text-2xl font-semibold mb-6 relative dark:text-white">Signup</h2>
                      <form action="login_register.php" method="post" class="space-y-4">
                          <div class="relative">
                              <i class="fas fa-user absolute left-3 top-1/2 transform -translate-y-1/2 text-primary dark:text-white"></i>
                              <input type="text" name="name" placeholder="Your name" required class="w-full p-2 pl-10 border-b-2 border-gray-300 focus:border-primary outline-none dark:bg-gray-700 dark:text-white dark:border-gray-600" value="<?php echo $register_data['name']; ?>">
                          </div>
                          <div class="relative">
                              <i class="fas fa-id-card absolute left-3 top-1/2 transform -translate-y-1/2 text-primary dark:text-white"></i>
                              <input type="text" name="nic" placeholder="Your NIC" required class="w-full p-2 pl-10 border-b-2 border-gray-300 focus:border-primary outline-none dark:bg-gray-700 dark:text-white dark:border-gray-600" value="<?php echo $register_data['nic']; ?>">
                          </div>
                          <div class="relative">
                              <i class="fas fa-briefcase absolute left-3 top-1/2 transform -translate-y-1/2 text-primary dark:text-white"></i>
                              <select name="position" id="position" class="w-full p-2 pl-10 border-b-2 border-gray-300 focus:border-primary outline-none dark:bg-gray-700 dark:text-white dark:border-gray-600" required>
                                  <option value="" disabled <?php echo empty($register_data['position']) ? 'selected' : ''; ?>>Select position</option>
                                  <option value="vc" <?php echo $register_data['position'] === 'vc' ? 'selected' : ''; ?>>VC</option>
                                  <option value="dvc" <?php echo $register_data['position'] === 'dvc' ? 'selected' : ''; ?>>DVC</option>
                                  <option value="dean" <?php echo $register_data['position'] === 'dean' ? 'selected' : ''; ?>>Dean</option>
                                  <option value="directors" <?php echo $register_data['position'] === 'directors' ? 'selected' : ''; ?>>Directors</option>
                                  <option value="registrar/AR" <?php echo $register_data['position'] === 'registrar/AR' ? 'selected' : ''; ?>>Registrar / Assistant Registrar</option>
                                  <option value="hod" <?php echo $register_data['position'] === 'hod' ? 'selected' : ''; ?>>Head of Department(HOD)</option>
                                  <option value="lec/AL" <?php echo $register_data['position'] === 'lec/AL' ? 'selected' : ''; ?>>Lecturer / Assistant Lecturer</option>
                                  <option value="B/AB" <?php echo $register_data['position'] === 'B/AB' ? 'selected' : ''; ?>>Bursar / Assistant Bursar</option>
                                  <option value="MA" <?php echo $register_data['position'] === 'MA' ? 'selected' : ''; ?>>Management Assistant</option>
                                  <option value="cmo" <?php echo $register_data['position'] === 'cmo' ? 'selected' : ''; ?>>Chief Medical Officer(CMO)</option>
                              </select>
                          </div>
                          <div class="relative">
                              <i class="fas fa-graduation-cap absolute left-3 top-1/2 transform -translate-y-1/2 text-primary dark:text-white"></i>
                              <select name="faculty" id="faculty" class="w-full p-2 pl-10 border-b-2 border-gray-300 focus:border-primary outline-none dark:bg-gray-700 dark:text-white dark:border-gray-600" required>
                                  <option value="" disabled <?php echo empty($register_data['faculty']) ? 'selected' : ''; ?>>Select faculty</option>
                                  <option value="agriculture" <?php echo $register_data['faculty'] === 'agriculture' ? 'selected' : ''; ?>>Faculty of Agriculture</option>
                                  <option value="allied_health" <?php echo $register_data['faculty'] === 'allied_health' ? 'selected' : ''; ?>>Faculty of Allied Health Sciences</option>
                                  <option value="arts" <?php echo $register_data['faculty'] === 'arts' ? 'selected' : ''; ?>>Faculty of Arts</option>
                                  <option value="dental" <?php echo $register_data['faculty'] === 'dental' ? 'selected' : ''; ?>>Faculty of Dental Sciences</option>
                                  <option value="engineering" <?php echo $register_data['faculty'] === 'engineering' ? 'selected' : ''; ?>>Faculty of Engineering</option>
                                  <option value="management" <?php echo $register_data['faculty'] === 'management' ? 'selected' : ''; ?>>Faculty of Management</option>
                                  <option value="medicine" <?php echo $register_data['faculty'] === 'medicine' ? 'selected' : ''; ?>>Faculty of Medicine</option>
                                  <option value="science" <?php echo $register_data['faculty'] === 'science' ? 'selected' : ''; ?>>Faculty of Sciences</option>
                                  <option value="veterinary" <?php echo $register_data['faculty'] === 'veterinary' ? 'selected' : ''; ?>>Faculty of Veterinary Medicine & Animal Science</option>
                                  <option value="pgia" <?php echo $register_data['faculty'] === 'pgia' ? 'selected' : ''; ?>>Postgraduate Institute of Agriculture (PGIA)</option>
                                  <option value="pgihs" <?php echo $register_data['faculty'] === 'pgihs' ? 'selected' : ''; ?>>Postgraduate Institute of Humanities and Social Sciences (PGIHS)</option>
                                  <option value="pgims" <?php echo $register_data['faculty'] === 'pgims' ? 'selected' : ''; ?>>Postgraduate Institute of Medical Sciences (PGIMS)</option>
                                  <option value="pgis" <?php echo $register_data['faculty'] === 'pgis' ? 'selected' : ''; ?>>Postgraduate Institute of Science (PGIS)</option>
                              </select>
                          </div>
                          <div class="relative">
                              <i class="fas fa-phone absolute left-3 top-1/2 transform -translate-y-1/2 text-primary dark:text-white"></i>
                              <input type="text" name="mobile" placeholder="Mobile" required class="w-full p-2 pl-10 border-b-2 border-gray-300 focus:border-primary outline-none dark:bg-gray-700 dark:text-white dark:border-gray-600" value="<?php echo $register_data['mobile']; ?>">
                          </div>
                          <div class="relative">
                              <i class="fas fa-id-badge absolute left-3 top-1/2 transform -translate-y-1/2 text-primary dark:text-white"></i>
                              <input type="text" name="employee_number" placeholder="Your employment number" required class="w-full p-2 pl-10 border-b-2 border-gray-300 focus:border-primary outline-none dark:bg-gray-700 dark:text-white dark:border-gray-600" value="<?php echo $register_data['employee_number']; ?>">
                          </div>
                          <div class="relative">
                              <i class="fas fa-user-tag absolute left-3 top-1/2 transform -translate-y-1/2 text-primary dark:text-white"></i>
                              <select name="role" id="role" class="w-full p-2 pl-10 border-b-2 border-gray-300 focus:border-primary outline-none dark:bg-gray-700 dark:text-white dark:border-gray-600" required>
                                  <option value="" disabled <?php echo empty($register_data['role']) ? 'selected' : ''; ?>>Select Role</option>
                                  <option value="user" <?php echo $register_data['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                  <option value="admin" <?php echo $register_data['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                              </select>
                          </div>
                          <div class="relative">
                              <i class="fas fa-envelope absolute left-3 top-1/2 transform -translate-y-1/2 text-primary dark:text-white"></i>
                              <input type="email" name="email" placeholder="Email" required class="w-full p-2 pl-10 border-b-2 border-gray-300 focus:border-primary outline-none dark:bg-gray-700 dark:text-white dark:border-gray-600" value="<?php echo $register_data['email']; ?>">
                          </div>
                          <div class="relative">
                              <i class="fas fa-lock absolute left-3 top-1/2 transform -translate-y-1/2 text-primary dark:text-white"></i>
                              <input type="password" name="password" placeholder="Password" required class="w-full p-2 pl-10 border-b-2 border-gray-300 focus:border-primary outline-none dark:bg-gray-700 dark:text-white dark:border-gray-600">
                          </div>
                          <div class="relative">
                              <i class="fas fa-lock absolute left-3 top-1/2 transform -translate-y-1/2 text-primary dark:text-white"></i>
                              <input type="password" name="confirm_password" placeholder="Confirm password" required class="w-full p-2 pl-10 border-b-2 border-gray-300 focus:border-primary outline-none dark:bg-gray-700 dark:text-white dark:border-gray-600">
                          </div>
                          <button type="submit" name="register" class="w-full bg-primary text-white p-2 rounded-md hover:bg-secondary transition duration-300 dark:bg-primary dark:hover:bg-secondary">Register</button>
                      </form>
                      <p class="mt-4 text-sm dark:text-white">Already have an account? <a href="#" class="text-primary dark:text-white hover:underline" id="show-login">Login now</a></p>
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

      // New notification hide functionality
      document.addEventListener('DOMContentLoaded', function() {
          var notification = document.getElementById('notification');
          if (notification) {
              setTimeout(function() {
                  notification.classList.add('fade-out');
                  setTimeout(function() {
                      notification.style.display = 'none';
                  }, 500);
              }, 5000);
          }
      });

      // Theme toggle functionality
      const themeToggle = document.getElementById('theme-toggle');
      const html = document.documentElement;

      // Check for saved theme preference or use system preference
      if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
          html.classList.add('dark');
      } else {
          html.classList.remove('dark');
      }

      // Toggle theme
      themeToggle.addEventListener('click', () => {
          html.classList.toggle('dark');
          if (html.classList.contains('dark')) {
              localStorage.theme = 'dark';
          } else {
              localStorage.theme = 'light';
          }
      });

      // Add animation to the toggle button
      themeToggle.addEventListener('click', () => {
          themeToggle.classList.add('animate-spin');
          setTimeout(() => {
              themeToggle.classList.remove('animate-spin');
          }, 300);
      });
  </script>
</body>
</html>

