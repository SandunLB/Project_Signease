<?php
include 'sidebar.php';
// Note: sidebar.php already includes config.php and session.php

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch user details
$stmt = $conn->prepare("SELECT id, name, role, email, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Function to get total count
function getTotalCount($conn, $table, $condition = '') {
    $sql = "SELECT COUNT(*) as count FROM $table $condition";
    $result = $conn->query($sql);
    return $result->fetch_assoc()['count'];
}

// Function to get monthly data
function getMonthlyData($conn, $table, $dateColumn, $condition = '') {
    $sql = "SELECT DATE_FORMAT($dateColumn, '%Y-%m') as month, COUNT(*) as count 
            FROM $table 
            $condition
            GROUP BY DATE_FORMAT($dateColumn, '%Y-%m') 
            ORDER BY month DESC 
            LIMIT 12";
    $result = $conn->query($sql);
    return array_reverse($result->fetch_all(MYSQLI_ASSOC));
}

// Get user-specific data
$userDocuments = getTotalCount($conn, 'documents', "WHERE sender_id = {$user['id']}");
$userSignedDocuments = getTotalCount($conn, 'documents', "WHERE sender_id = {$user['id']} AND status = 'signed'");
$userPendingDocuments = getTotalCount($conn, 'documents', "WHERE sender_id = {$user['id']} AND status = 'pending'");
$userMessages = getTotalCount($conn, 'messages', "WHERE sender_id = {$user['id']} OR receiver_id = {$user['id']}");

$userMonthlyDocuments = getMonthlyData($conn, 'documents', 'upload_date', "WHERE sender_id = {$user['id']}");
$userMonthlySignedDocuments = getMonthlyData($conn, 'documents', 'signed_at', "WHERE sender_id = {$user['id']} AND status = 'signed'");
$userMonthlyMessages = getMonthlyData($conn, 'messages', 'created_at', "WHERE sender_id = {$user['id']} OR receiver_id = {$user['id']}");

// Get document types for the user
$stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM documents WHERE sender_id = ? GROUP BY status");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$userDocumentTypes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get admin-specific data
if ($user['role'] === 'admin') {
    $totalUsers = getTotalCount($conn, 'users');
    $totalDocuments = getTotalCount($conn, 'documents');
    $totalSignedDocuments = getTotalCount($conn, 'documents', "WHERE status = 'signed'");
    $totalPendingDocuments = getTotalCount($conn, 'documents', "WHERE status = 'pending'");
    $totalMessages = getTotalCount($conn, 'messages');

    $monthlyUsers = getMonthlyData($conn, 'users', 'created_at');
    $monthlyDocuments = getMonthlyData($conn, 'documents', 'upload_date');
    $monthlySignedDocuments = getMonthlyData($conn, 'documents', 'signed_at', "WHERE status = 'signed'");
    $monthlyMessages = getMonthlyData($conn, 'messages', 'created_at');

    // Get top users
    $stmt = $conn->prepare("SELECT u.name, COUNT(d.id) as document_count 
                            FROM users u 
                            LEFT JOIN documents d ON u.id = d.sender_id 
                            GROUP BY u.id 
                            ORDER BY document_count DESC 
                            LIMIT 5");
    $stmt->execute();
    $topUsers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Get document types for all users
    $stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM documents GROUP BY status");
    $stmt->execute();
    $allDocumentTypes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Get user activity trends
    $stmt = $conn->prepare("SELECT 
                                u.id,
                                u.name,
                                COUNT(DISTINCT d.id) as document_count,
                                COUNT(DISTINCT m.id) as message_count,
                                MAX(GREATEST(IFNULL(d.upload_date, '1970-01-01'), IFNULL(m.created_at, '1970-01-01'))) as last_activity
                            FROM 
                                users u
                                LEFT JOIN documents d ON u.id = d.sender_id
                                LEFT JOIN messages m ON u.id = m.sender_id OR u.id = m.receiver_id
                            GROUP BY 
                                u.id
                            ORDER BY 
                                last_activity DESC
                            LIMIT 10");
    $stmt->execute();
    $userActivityTrends = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Get recent activities (for both user and admin)
$activityCondition = $user['role'] === 'admin' ? '' : "WHERE d.sender_id = {$user['id']}";
$stmt = $conn->prepare("SELECT u.name, d.file_path, d.status, d.upload_date
                        FROM documents d 
                        JOIN users u ON d.sender_id = u.id 
                        $activityCondition
                        ORDER BY d.upload_date DESC 
                        LIMIT 10");
$stmt->execute();
$recentActivities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get recent messages
$messageCondition = $user['role'] === 'admin' ? '' : "WHERE m.sender_id = {$user['id']} OR m.receiver_id = {$user['id']}";
$stmt = $conn->prepare("SELECT m.id, m.sender_id, s.name as sender_name, m.receiver_id, r.name as receiver_name, m.message, m.created_at
                        FROM messages m
                        JOIN users s ON m.sender_id = s.id
                        JOIN users r ON m.receiver_id = r.id
                        $messageCondition
                        ORDER BY m.created_at DESC
                        LIMIT 10");
$stmt->execute();
$recentMessages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SignEase</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        .animate__animated { animation-duration: 0.5s; }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-gray-200">
    <div class="p-4 sm:ml-64">
        <div class="p-4 border-2 border-gray-200 border-dashed rounded-lg dark:border-gray-700">
            <h1 class="text-3xl font-semibold mb-6 animate__animated animate__fadeIn">Welcome, <?php echo htmlspecialchars($user['name']); ?>!</h1>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <?php if ($user['role'] === 'admin'): ?>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md animate__animated animate__fadeInUp">
                    <h3 class="text-xl font-semibold mb-2">Total Users</h3>
                    <p class="text-3xl font-bold"><?php echo $totalUsers; ?></p>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
                    <h3 class="text-xl font-semibold mb-2">Total Documents</h3>
                    <p class="text-3xl font-bold"><?php echo $totalDocuments; ?></p>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
                    <h3 class="text-xl font-semibold mb-2">Signed Documents</h3>
                    <p class="text-3xl font-bold"><?php echo $totalSignedDocuments; ?></p>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md animate__animated animate__fadeInUp" style="animation-delay: 0.3s;">
                    <h3 class="text-xl font-semibold mb-2">Total Messages</h3>
                    <p class="text-3xl font-bold"><?php echo $totalMessages; ?></p>
                </div>
                <?php else: ?>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md animate__animated animate__fadeInUp">
                    <h3 class="text-xl font-semibold mb-2">Your Documents</h3>
                    <p class="text-3xl font-bold"><?php echo $userDocuments; ?></p>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
                    <h3 class="text-xl font-semibold mb-2">Your Signed Documents</h3>
                    <p class="text-3xl font-bold"><?php echo $userSignedDocuments; ?></p>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
                    <h3 class="text-xl font-semibold mb-2">Your Pending Documents</h3>
                    <p class="text-3xl font-bold"><?php echo $userPendingDocuments; ?></p>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md animate__animated animate__fadeInUp" style="animation-delay: 0.3s;">
                    <h3 class="text-xl font-semibold mb-2">Your Messages</h3>
                    <p class="text-3xl font-bold"><?php echo $userMessages; ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <?php if ($user['role'] === 'admin'): ?>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md animate__animated animate__fadeIn">
                    <h3 class="text-xl font-semibold mb-4">Monthly User Registrations</h3>
                    <canvas id="userChart"></canvas>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md animate__animated animate__fadeIn" style="animation-delay: 0.1s;">
                    <h3 class="text-xl font-semibold mb-4">Monthly Document Activity</h3>
                    <canvas id="documentChart"></canvas>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md animate__animated animate__fadeIn" style="animation-delay: 0.2s;">
                    <h3 class="text-xl font-semibold mb-4">Document Status Distribution</h3>
                    <canvas id="documentTypesChart"></canvas>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md animate__animated animate__fadeIn" style="animation-delay: 0.3s;">
                    <h3 class="text-xl font-semibold mb-4">Monthly Message Activity</h3>
                    <canvas id="messageChart"></canvas>
                </div>
                <?php else: ?>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md animate__animated animate__fadeIn">
                    <h3 class="text-xl font-semibold mb-4">Your Monthly Document Activity</h3>
                    <canvas id="userDocumentChart"></canvas>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md animate__animated animate__fadeIn" style="animation-delay: 0.1s;">
                    <h3 class="text-xl font-semibold mb-4">Your Document Status Distribution</h3>
                    <canvas id="userDocumentStatusChart"></canvas>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md animate__animated animate__fadeIn" style="animation-delay: 0.2s;">
                    <h3 class="text-xl font-semibold mb-4">Your Monthly Message Activity</h3>
                    <canvas id="userMessageChart"></canvas>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($user['role'] === 'admin'): ?>
            <!-- Admin-only sections -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md animate__animated animate__fadeIn">
                    <h3 class="text-xl font-semibold mb-4">Top Users by Document Count</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white dark:bg-gray-800">
                            <thead class="bg-gray-100 dark:bg-gray-700">
                                <tr>
                                    <th class="py-2 px-4 text-left">User</th>
                                    <th class="py-2 px-4 text-left">Document Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topUsers as $topUser): ?>
                                <tr class="border-b dark:border-gray-700">
                                    <td class="py-2 px-4"><?php echo htmlspecialchars($topUser['name']); ?></td>
                                    <td class="py-2 px-4"><?php echo $topUser['document_count']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md animate__animated animate__fadeIn" style="animation-delay: 0.1s;">
                    <h3 class="text-xl font-semibold mb-4">User Activity Trends</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white dark:bg-gray-800">
                            <thead class="bg-gray-100 dark:bg-gray-700">
                                <tr>
                                    <th class="py-2 px-4 text-left">User</th>
                                    <th class="py-2 px-4 text-left">Documents</th>
                                    <th class="py-2 px-4 text-left">Messages</th>
                                    <th class="py-2 px-4 text-left">Last Activity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($userActivityTrends as $activity): ?>
                                <tr class="border-b dark:border-gray-700">
                                    <td class="py-2 px-4"><?php echo htmlspecialchars($activity['name']); ?></td>
                                    <td class="py-2 px-4"><?php echo $activity['document_count']; ?></td>
                                    <td class="py-2 px-4"><?php echo $activity['message_count']; ?></td>
                                    <td class="py-2 px-4"><?php echo date('Y-m-d H:i', strtotime($activity['last_activity'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Activities -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md mb-8 animate__animated animate__fadeIn">
                <h3 class="text-xl font-semibold mb-4">Recent Activities</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white dark:bg-gray-800">
                        <thead class="bg-gray-100 dark:bg-gray-700">
                            <tr>
                                <th class="py-2 px-4 text-left">User</th>
                                <th class="py-2 px-4 text-left">Document</th>
                                <th class="py-2 px-4 text-left">Status</th>
                                <th class="py-2 px-4 text-left">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentActivities as $activity): ?>
                            <tr class="border-b dark:border-gray-700">
                                <td class="py-2 px-4"><?php echo htmlspecialchars($activity['name']); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars(basename($activity['file_path'])); ?></td>
                                <td class="py-2 px-4"><?php echo ucfirst($activity['status']); ?></td>
                                <td class="py-2 px-4"><?php echo date('Y-m-d H:i', strtotime($activity['upload_date'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Messages -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md animate__animated animate__fadeIn" style="animation-delay: 0.1s;">
                <h3 class="text-xl font-semibold mb-4">Recent Messages</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white dark:bg-gray-800">
                        <thead class="bg-gray-100 dark:bg-gray-700">
                            <tr>
                                <th class="py-2 px-4 text-left">Sender</th>
                                <th class="py-2 px-4 text-left">Receiver</th>
                                <th class="py-2 px-4 text-left">Message</th>
                                <th class="py-2 px-4 text-left">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentMessages as $message): ?>
                            <tr class="border-b dark:border-gray-700">
                                <td class="py-2 px-4"><?php echo htmlspecialchars($message['sender_name']); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($message['receiver_name']); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars(substr($message['message'], 0, 50)) . (strlen($message['message']) > 50 ? '...' : ''); ?></td>
                                <td class="py-2 px-4"><?php echo date('Y-m-d H:i', strtotime($message['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
    function getChartConfig(isDarkMode) {
        return {
            color: isDarkMode ? '#fff' : '#666',
            borderColor: isDarkMode ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)',
            grid: {
                color: isDarkMode ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)'
            }
        };
    }

    function updateChartTheme(chart, isDarkMode) {
        const config = getChartConfig(isDarkMode);
        chart.options.scales.x.ticks.color = config.color;
        chart.options.scales.y.ticks.color = config.color;
        chart.options.scales.x.grid.color = config.grid.color;
        chart.options.scales.y.grid.color = config.grid.color;
        chart.options.plugins.legend.labels.color = config.color;
        chart.update();
    }

    const isDarkMode = document.documentElement.classList.contains('dark');
    const chartConfig = getChartConfig(isDarkMode);

    <?php if ($user['role'] === 'admin'): ?>
    // Admin Charts
    var userCtx = document.getElementById('userChart').getContext('2d');
    var userChart = new Chart(userCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($monthlyUsers, 'month')); ?>,
            datasets: [{
                label: 'New Users',
                data: <?php echo json_encode(array_column($monthlyUsers, 'count')); ?>,
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1,
                fill: false
            }]
        },
        options: {
            responsive: true,
            scales: {
                x: {
                    ticks: { color: chartConfig.color },
                    grid: { color: chartConfig.grid.color }
                },
                y: {
                    beginAtZero: true,
                    ticks: { color: chartConfig.color },
                    grid: { color: chartConfig.grid.color }
                }
            },
            plugins: {
                legend: {
                    labels: { color: chartConfig.color }
                }
            },
            animation: {
                duration: 2000,
                easing: 'easeOutQuart'
            }
        }
    });

    var docCtx = document.getElementById('documentChart').getContext('2d');
    var docChart = new Chart(docCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($monthlyDocuments, 'month')); ?>,
            datasets: [{
                label: 'Uploaded Documents',
                data: <?php echo json_encode(array_column($monthlyDocuments, 'count')); ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgb(54, 162, 235)',
                borderWidth: 1
            },
            {
                label: 'Signed Documents',
                data: <?php echo json_encode(array_column($monthlySignedDocuments, 'count')); ?>,
                backgroundColor: 'rgba(75, 192, 192, 0.5)',
                borderColor: 'rgb(75, 192, 192)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                x: {
                    ticks: { color: chartConfig.color },
                    grid: { color: chartConfig.grid.color }
                },
                y: {
                    beginAtZero: true,
                    ticks: { color: chartConfig.color },
                    grid: { color: chartConfig.grid.color }
                }
            },
            plugins: {
                legend: {
                    labels: { color: chartConfig.color }
                }
            },
            animation: {
                duration: 2000,
                easing: 'easeOutQuart'
            }
        }
    });

    var docTypesCtx = document.getElementById('documentTypesChart').getContext('2d');
    var docTypesChart = new Chart(docTypesCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($allDocumentTypes, 'status')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($allDocumentTypes, 'count')); ?>,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.8)',
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(255, 206, 86, 0.8)',
                    'rgba(75, 192, 192, 0.8)',
                    'rgba(153, 102, 255, 0.8)'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right',
                    labels: { color: chartConfig.color }
                },
                title: {
                    display: true,
                    text: 'Document Status Distribution',
                    color: chartConfig.color
                }
            },
            animation: {
                duration: 2000,
                easing: 'easeOutQuart'
            }
        }
    });

    var messageCtx = document.getElementById('messageChart').getContext('2d');
    var messageChart = new Chart(messageCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($monthlyMessages, 'month')); ?>,
            datasets: [{
                label: 'Messages',
                data: <?php echo json_encode(array_column($monthlyMessages, 'count')); ?>,
                borderColor: 'rgb(255, 159, 64)',
                tension: 0.1,
                fill: false
            }]
        },
        options: {
            responsive: true,
            scales: {
                x: {
                    ticks: { color: chartConfig.color },
                    grid: { color: chartConfig.grid.color }
                },
                y: {
                    beginAtZero: true,
                    ticks: { color: chartConfig.color },
                    grid: { color: chartConfig.grid.color }
                }
            },
            plugins: {
                legend: {
                    labels: { color: chartConfig.color }
                }
            },
            animation: {
                duration: 2000,
                easing: 'easeOutQuart'
            }
        }
    });
    <?php else: ?>
    // User Charts
    var userDocCtx = document.getElementById('userDocumentChart').getContext('2d');
    var userDocChart = new Chart(userDocCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($userMonthlyDocuments, 'month')); ?>,
            datasets: [{
                label: 'Your Uploaded Documents',
                data: <?php echo json_encode(array_column($userMonthlyDocuments, 'count')); ?>,
                borderColor: 'rgb(54, 162, 235)',
                tension: 0.1,
                fill: false
            },
            {
                label: 'Your Signed Documents',
                data: <?php echo json_encode(array_column($userMonthlySignedDocuments, 'count')); ?>,
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1,
                fill: false
            }]
        },
        options: {
            responsive: true,
            scales: {
                x: {
                    ticks: { color: chartConfig.color },
                    grid: { color: chartConfig.grid.color }
                },
                y: {
                    beginAtZero: true,
                    ticks: { color: chartConfig.color },
                    grid: { color: chartConfig.grid.color }
                }
            },
            plugins: {
                legend: {
                    labels: { color: chartConfig.color }
                }
            },
            animation: {
                duration: 2000,
                easing: 'easeOutQuart'
            }
        }
    });

    var statusCtx = document.getElementById('userDocumentStatusChart').getContext('2d');
    var statusChart = new Chart(statusCtx, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode(array_column($userDocumentTypes, 'status')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($userDocumentTypes, 'count')); ?>,
                backgroundColor: [
                    'rgba(75, 192, 192, 0.8)',
                    'rgba(255, 206, 86, 0.8)',
                    'rgba(153, 102, 255, 0.8)'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right',
                    labels: { color: chartConfig.color }
                },
                title: {
                    display: true,
                    text: 'Your Document Status Distribution',
                    color: chartConfig.color
                }
            },
            animation: {
                duration: 2000,
                easing: 'easeOutQuart'
            }
        }
    });

    var userMessageCtx = document.getElementById('userMessageChart').getContext('2d');
    var userMessageChart = new Chart(userMessageCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($userMonthlyMessages, 'month')); ?>,
            datasets: [{
                label: 'Your Messages',
                data: <?php echo json_encode(array_column($userMonthlyMessages, 'count')); ?>,
                borderColor: 'rgb(255, 159, 64)',
                tension: 0.1,
                fill: false
            }]
        },
        options: {
            responsive: true,
            scales: {
                x: {
                    ticks: { color: chartConfig.color },
                    grid: { color: chartConfig.grid.color }
                },
                y: {
                    beginAtZero: true,
                    ticks: { color: chartConfig.color },
                    grid: { color: chartConfig.grid.color }
                }
            },
            plugins: {
                legend: {
                    labels: { color: chartConfig.color }
                }
            },
            animation: {
                duration: 2000,
                easing: 'easeOutQuart'
            }
        }
    });
    <?php endif; ?>

    // Add scroll animation to all animate__animated elements
    const animatedElements = document.querySelectorAll('.animate__animated');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate__fadeIn');
            }
        });
    }, { threshold: 0.1 });

    animatedElements.forEach(element => {
        observer.observe(element);
    });

    // Function to update chart themes
    function updateChartsTheme(isDark) {
        const charts = [
            <?php if ($user['role'] === 'admin'): ?>
            userChart, docChart, docTypesChart, messageChart
            <?php else: ?>
            userDocChart, statusChart, userMessageChart
            <?php endif; ?>
        ];
        charts.forEach(chart => updateChartTheme(chart, isDark));
    }

    // Listen for theme changes
    window.addEventListener('themeChanged', function(e) {
        updateChartsTheme(e.detail.isDarkMode);
    });
    </script>
    <script src="theme.js"></script>
</body>
</html>