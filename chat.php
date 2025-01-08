<?php
include 'sidebar.php';
// Note: sidebar.php already includes config.php and session.php, so we don't need to include them again

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login_register.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Function to get or create a conversation
function getOrCreateConversation($conn, $user1Id, $user2Id) {
    $stmt = $conn->prepare("SELECT id FROM conversations WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?) LIMIT 1");
    $stmt->bind_param("iiii", $user1Id, $user2Id, $user2Id, $user1Id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['id'];
    }

    $stmt = $conn->prepare("INSERT INTO conversations (user1_id, user2_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user1Id, $user2Id);
    $stmt->execute();
    return $conn->insert_id;
}

function getUserConversations($conn, $userId) {
    $stmt = $conn->prepare("
        SELECT c.id, u.email, u.name,
            (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.receiver_id = ? AND m.read = 0) as unread_count,
            (SELECT message FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
            (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_time
        FROM conversations c
        JOIN users u ON (c.user1_id = u.id OR c.user2_id = u.id)
        WHERE (c.user1_id = ? OR c.user2_id = ?) AND u.id != ?
        ORDER BY CASE 
            WHEN (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) IS NULL THEN 1 
            ELSE 0 
        END, 
        (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) DESC
    ");
    $stmt->bind_param("iiii", $userId, $userId, $userId, $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Function to get messages for a conversation
function getMessages($conn, $conversationId) {
    $stmt = $conn->prepare("SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at ASC");
    $stmt->bind_param("i", $conversationId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Function to get user details
function getUserDetails($conn, $userId) {
    $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Function to delete a conversation
function deleteConversation($conn, $conversationId, $userId) {
    // First, check if the user is part of the conversation
    $stmt = $conn->prepare("SELECT * FROM conversations WHERE id = ? AND (user1_id = ? OR user2_id = ?)");
    $stmt->bind_param("iii", $conversationId, $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false; // User is not part of this conversation
    }

    // Delete all messages in the conversation
    $stmt = $conn->prepare("DELETE FROM messages WHERE conversation_id = ?");
    $stmt->bind_param("i", $conversationId);
    $stmt->execute();

    // Delete the conversation itself
    $stmt = $conn->prepare("DELETE FROM conversations WHERE id = ?");
    $stmt->bind_param("i", $conversationId);
    $stmt->execute();

    return true;
}

// Handle new message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'send_message') {
        $receiverEmail = $_POST['receiver_email'];
        $message = $_POST['message'];

        $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->bind_param("s", $receiverEmail);
        $stmt->execute();
        $result = $stmt->get_result();
        $receiver = $result->fetch_assoc();

        if ($receiver) {
            $conversationId = getOrCreateConversation($conn, $_SESSION['user_id'], $receiver['id']);
            $stmt = $conn->prepare("INSERT INTO messages (conversation_id, sender_id, receiver_id, message) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiis", $conversationId, $_SESSION['user_id'], $receiver['id'], $message);
            $stmt->execute();

            $stmt = $conn->prepare("UPDATE conversations SET last_message_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $conversationId);
            $stmt->execute();

            $newMessageId = $conn->insert_id;
            $newMessageTime = date('Y-m-d H:i:s');

            echo json_encode([
                'success' => true, 
                'conversation_id' => $conversationId,
                'receiver_name' => $receiver['name'],
                'receiver_email' => $receiverEmail,
                'message_id' => $newMessageId,
                'message' => $message,
                'created_at' => $newMessageTime
            ]);
            exit();
        }
    } elseif ($_POST['action'] === 'edit_message') {
        $messageId = $_POST['message_id'];
        $newMessage = $_POST['new_message'];

        $stmt = $conn->prepare("UPDATE messages SET message = ? WHERE id = ? AND sender_id = ?");
        $stmt->bind_param("sii", $newMessage, $messageId, $_SESSION['user_id']);
        $stmt->execute();

        echo json_encode(['success' => true]);
        exit();
    } elseif ($_POST['action'] === 'delete_message') {
        $messageId = $_POST['message_id'];

        $stmt = $conn->prepare("DELETE FROM messages WHERE id = ? AND sender_id = ?");
        $stmt->bind_param("ii", $messageId, $_SESSION['user_id']);
        $stmt->execute();

        echo json_encode(['success' => true]);
        exit();
    } elseif ($_POST['action'] === 'delete_conversation') {
        $conversationId = $_POST['conversation_id'];
        $success = deleteConversation($conn, $conversationId, $_SESSION['user_id']);
        echo json_encode(['success' => $success]);
        exit();
    }
}

$conversations = getUserConversations($conn, $_SESSION['user_id']);
$activeConversationId = $_GET['conversation_id'] ?? null;
$messages = $activeConversationId ? getMessages($conn, $activeConversationId) : [];
$userDetails = getUserDetails($conn, $_SESSION['user_id']);

// Mark messages as read
if ($activeConversationId) {
    $stmt = $conn->prepare("UPDATE messages SET `read` = 1 WHERE conversation_id = ? AND receiver_id = ?");
    $stmt->bind_param("ii", $activeConversationId, $_SESSION['user_id']);
    $stmt->execute();
}

// Get active conversation details
$activeConversation = null;
if ($activeConversationId) {
    $activeConversation = array_filter($conversations, function($conv) use ($activeConversationId) {
        return $conv['id'] == $activeConversationId;
    });
    $activeConversation = reset($activeConversation); // Get the first (and only) element
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - SignEase</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            DEFAULT: '#880404',
                            dark: '#a61b1b',
                            light: '#880404',
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-gray-100 dark:bg-gray-900">
    <div class="p-4 sm:ml-64">
        <div class="p-4 border-2 border-gray-200 border-dashed rounded-lg dark:border-gray-700">
            <h1 class="text-3xl font-bold mb-6 text-gray-800 dark:text-white">Chat</h1>
            
            <div class="overflow-hidden relative shadow-md sm:rounded-lg">
                <div class="flex h-[calc(100vh-200px)]">
                    <!-- Conversation List -->
                    <div class="w-1/3 border-r border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
    <!-- Header -->
    <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-gray-50 to-white dark:from-gray-800 dark:to-gray-700">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white flex items-center">
                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z" />
                </svg>
                Conversations
            </h2>
            <div class="flex items-center space-x-2">
                <span class="text-sm text-gray-500 dark:text-gray-400"><?= count($conversations) ?> chats</span>
            </div>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="p-3 border-b border-gray-200 dark:border-gray-700">
        <div class="relative">
            <input type="text" 
                   placeholder="Search conversations..." 
                   class="w-full pl-10 pr-4 py-2 border rounded-lg text-sm bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-600"
                   id="conversation-search">
            <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
        </div>
    </div>

    <!-- Conversations List -->
    <div class="overflow-y-auto h-full" id="conversation-list">
        <?php foreach ($conversations as $conversation): ?>
            <a href="?conversation_id=<?= $conversation['id'] ?>" 
               class="block hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150 <?= $activeConversationId == $conversation['id'] ? 'bg-blue-50 dark:bg-gray-600 border-l-4 border-blue-500 dark:border-blue-400' : '' ?>">
                <div class="p-4">
                    <div class="flex justify-between items-center mb-2">
                        <div class="flex items-center space-x-3">
                            <!-- User Avatar -->
                            <div class="relative">
                                <div class="w-10 h-10 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center">
                                    <svg class="w-6 h-6 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </div>
                                <?php if ($conversation['unread_count'] > 0): ?>
                                    <div class="absolute -top-1 -right-1">
                                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-500 text-xs text-white">
                                            <?= $conversation['unread_count'] ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- User Info -->
                            <div class="flex-1 min-w-0">
                                <h3 class="font-semibold text-gray-900 dark:text-white truncate">
                                    <?= htmlspecialchars($conversation['name'] ?? $conversation['email'] ?? 'Unknown User') ?>
                                </h3>
                            </div>
                        </div>

                        <!-- Timestamp -->
                        <div class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                            <?php if ($conversation['last_message_time']): ?>
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <?= date('M d, H:i', strtotime($conversation['last_message_time'])) ?>
                                </div>
                            <?php else: ?>
                                <span class="italic">No messages</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Last Message -->
                    <div class="mt-1">
                        <p class="text-sm text-gray-600 dark:text-gray-400 truncate flex items-center">
                            <?php if ($conversation['last_message']): ?>
                                <svg class="w-4 h-4 mr-1 <?= $conversation['unread_count'] > 0 ? 'text-blue-500' : 'text-gray-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                                <?= htmlspecialchars($conversation['last_message']) ?>
                            <?php else: ?>
                                <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                                Start a conversation
                            <?php endif; ?>
                            </p>
                            </div>
                            </div>
                            </a>
                            <?php endforeach; ?>
                            </div>
                            </div>

                    <!-- Chat Area -->
                    <div class="w-2/3 flex flex-col bg-white dark:bg-gray-800">
                        <?php if ($activeConversationId && $activeConversation): ?>
                            <!-- Chat Header -->
                            <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700 flex justify-between items-center">
                                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">
                                    <?= htmlspecialchars($activeConversation['name'] ?: $activeConversation['email']) ?>
                                </h2>
                                <div class="relative">
                                    <button onclick="toggleMenu()" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                                        </svg>
                                    </button>
                                    <div id="chatMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-10 dark:bg-gray-700">
                                        <div class="py-1">
                                            <a href="#" onclick="deleteConversation(<?= $activeConversationId ?>)" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-600">Delete Chat</a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Messages -->
                            <div class="flex-1 overflow-y-auto p-4" id="chat-messages">
                                <?php foreach ($messages as $message): ?>
                                    <div class="mb-4 <?= $message['sender_id'] == $_SESSION['user_id'] ? 'text-right' : 'text-left' ?>" id="message-<?= $message['id'] ?>">
                                        <div class="inline-block p-2 rounded-lg <?= $message['sender_id'] == $_SESSION['user_id'] ? 'bg-blue-500 text-white dark:bg-blue-600' : 'bg-gray-300 text-gray-800 dark:bg-gray-600 dark:text-white' ?> relative group">
                                            <span class="message-text"><?= htmlspecialchars($message['message']) ?></span>
                                            <div class="text-xs mt-1 text-gray-200 dark:text-gray-200">
                                                <?= date('M d, Y H:i', strtotime($message['created_at'])) ?>
                                            </div>
                                            <?php if ($message['sender_id'] == $_SESSION['user_id']): ?>
                                                <div class="hidden group-hover:block absolute top-0 right-0 -mt-2 -mr-2">
                                                    <button onclick="editMessage(<?= $message['id'] ?>)" class="text-yellow-500 hover:text-yellow-600 dark:text-yellow-400 dark:hover:text-yellow-300 mr-2">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button onclick="deleteMessage(<?= $message['id'] ?>)" class="text-red-500 hover:text-red-600 dark:text-red-400 dark:hover:text-red-300">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Message Input -->
                            <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                                <form id="message-form" class="flex">
                                    <input type="hidden" name="action" value="send_message">
                                    <input type="hidden" name="receiver_email" value="<?= htmlspecialchars($activeConversation['email']) ?>">
                                    <input type="text" name="message" placeholder="Type a message" class="flex-1 border border-gray-300 dark:border-gray-600 rounded-l-lg p-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white" required>
                                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 dark:bg-blue-600 dark:hover:bg-blue-700 text-white px-4 py-2 rounded-r-lg">Send</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="flex-1 flex items-center justify-center">
                                <p class="text-gray-500 dark:text-gray-400">Select a conversation to start chatting</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- New Conversation Form -->
    <div class="fixed bottom-4 right-4">
        <button onclick="document.getElementById('newConversationModal').classList.remove('hidden')" class="bg-green-500 hover:bg-green-600 dark:bg-green-600 dark:hover:bg-green-700 text-white rounded-full p-4 shadow-lg">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
        </button>
    </div>

    <div id="newConversationModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3 text-center">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Start New Conversation</h3>
                <div class="mt-2 px-7 py-3">
                    <form id="new-conversation-form">
                        <input type="hidden" name="action" value="send_message">
                        <input type="email" name="receiver_email" placeholder="Enter email address" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white" required>
                        <input type="text" name="message" placeholder="Type a message" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg mt-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white" required>
                        <button type="submit" class="w-full mt-2 px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 dark:bg-blue-600 dark:hover:bg-blue-700">Start Chat</button>
                    </form>
                </div>
                <div class="items-center px-4 py-3">
                    <button onclick="document.getElementById('newConversationModal').classList.add('hidden')" class="px-4 py-2 bg-gray-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-300 dark:bg-gray-600 dark:hover:bg-gray-500 dark:focus:ring-gray-700">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
// Add loading state elements
const loadingOverlay = `
<div id="loadingOverlay" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 rounded-lg p-4 flex items-center space-x-3">
        <svg class="animate-spin h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span class="text-gray-900 dark:text-white">Creating conversation...</span>
    </div>
</div>`;

// Add loading overlay to body
document.body.insertAdjacentHTML('beforeend', loadingOverlay);

// Function to show/hide loading
function toggleLoading(show) {
    const overlay = document.getElementById('loadingOverlay');
    if (show) {
        overlay.classList.remove('hidden');
    } else {
        overlay.classList.add('hidden');
    }
}

// Function to create a new conversation element
function createConversationElement(data) {
    return `
        <a href="?conversation_id=${data.conversation_id}" 
           class="block hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150 bg-blue-50 dark:bg-gray-600 border-l-4 border-blue-500 dark:border-blue-400">
            <div class="p-4">
                <div class="flex justify-between items-center mb-2">
                    <div class="flex items-center space-x-3">
                        <div class="relative">
                            <div class="w-10 h-10 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center">
                                <svg class="w-6 h-6 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-semibold text-gray-900 dark:text-white truncate">
                                ${data.receiver_name || data.receiver_email}
                            </h3>
                        </div>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Just now
                        </div>
                    </div>
                </div>
                <div class="mt-1">
                    <p class="text-sm text-gray-600 dark:text-gray-400 truncate flex items-center">
                        <svg class="w-4 h-4 mr-1 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                        ${data.message}
                    </p>
                </div>
            </div>
        </a>
    `;
}

// Add search functionality
document.getElementById('conversation-search').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const conversations = document.querySelectorAll('#conversation-list a');
    
    conversations.forEach(conv => {
        const name = conv.querySelector('h3').textContent.toLowerCase();
        const message = conv.querySelector('p').textContent.toLowerCase();
        
        if (name.includes(searchTerm) || message.includes(searchTerm)) {
            conv.style.display = '';
        } else {
            conv.style.display = 'none';
        }
    });
});

function editMessage(messageId) {
    const messageElement = document.querySelector(`#message-${messageId} .message-text`);
    const currentMessage = messageElement.textContent;
    const newMessage = prompt("Edit your message:", currentMessage);
    
    if (newMessage !== null && newMessage !== currentMessage) {
        $.post('chat.php', {
            action: 'edit_message',
            message_id: messageId,
            new_message: newMessage
        }, function(response) {
            if (response.success) {
                messageElement.textContent = newMessage;
            }
        }, 'json');
    }
}

function deleteMessage(messageId) {
    if (confirm("Are you sure you want to delete this message?")) {
        $.post('chat.php', {
            action: 'delete_message',
            message_id: messageId
        }, function(response) {
            if (response.success) {
                $(`#message-${messageId}`).remove();
            }
        }, 'json');
    }
}

function deleteConversation(conversationId) {
    if (confirm("Are you sure you want to delete this entire conversation? This action cannot be undone.")) {
        // Show loading overlay with delete message
        const overlay = document.getElementById('loadingOverlay');
        const loadingText = overlay.querySelector('span');
        loadingText.textContent = 'Deleting conversation...';
        toggleLoading(true);

        $.post('chat.php', {
            action: 'delete_conversation',
            conversation_id: conversationId
        })
        .done(function(response) {
            try {
                if (typeof response === 'string') {
                    response = JSON.parse(response);
                }
                
                if (response.success) {
                    window.location.href = 'chat.php';
                } else {
                    toggleLoading(false);
                    alert("Failed to delete the conversation. Please try again.");
                }
            } catch (e) {
                // If we can't parse the response but got a 200 status,
                // assume success and redirect
                window.location.href = 'chat.php';
            }
        })
        .fail(function(jqXHR) {
            // Only show error if it's a real error status
            if (jqXHR.status !== 200) {
                toggleLoading(false);
                alert("An error occurred while deleting the conversation. Please try again.");
            } else {
                // If status is 200 but fail triggered, assume success
                window.location.href = 'chat.php';
            }
        });
    }
}

function toggleMenu() {
    const menu = document.getElementById('chatMenu');
    menu.classList.toggle('hidden');
}

// Close the menu when clicking outside
window.onclick = function(event) {
    if (!event.target.matches('.dots-menu')) {
        var dropdowns = document.getElementsByClassName("dropdown-content");
        for (var i = 0; i < dropdowns.length; i++) {
            var openDropdown = dropdowns[i];
            if (!openDropdown.classList.contains('hidden')) {
                openDropdown.classList.add('hidden');
            }
        }
    }
}

function updateChatArea(conversationId, receiverName, receiverEmail) {
    toggleLoading(true);
    
    $.get('chat.php?conversation_id=' + conversationId, function(data) {
        const $data = $(data);
        $('#chat-messages').html($data.find('#chat-messages').html());
        $('.w-2/3.flex.flex-col').html($data.find('.w-2/3.flex.flex-col').html());
        
        // Update the conversation list to show the new conversation
        $('#conversation-list').html($data.find('#conversation-list').html());
        
        // Scroll to the bottom of the chat
        const chatMessages = document.getElementById('chat-messages');
        chatMessages.scrollTop = chatMessages.scrollHeight;

        // Update the URL without refreshing the page
        history.pushState(null, '', 'chat.php?conversation_id=' + conversationId);
    })
    .always(function() {
        toggleLoading(false);
    });
}

function addMessageToChat(message) {
    const chatMessages = document.getElementById('chat-messages');
    const newMessageElement = document.createElement('div');
    newMessageElement.className = 'mb-4 text-right';
    newMessageElement.id = `message-${message.message_id}`;
    
    newMessageElement.innerHTML = `
        <div class="inline-block p-2 rounded-lg bg-blue-500 text-white dark:bg-blue-600 relative group animate-fade-in">
            <span class="message-text">${message.message}</span>
            <div class="text-xs mt-1 text-blue-200 dark:text-blue-300">
                ${message.created_at}
            </div>
            <div class="hidden group-hover:block absolute top-0 right-0 -mt-2 -mr-2">
                <button onclick="editMessage(${message.message_id})" class="text-yellow-500 hover:text-yellow-600 dark:text-yellow-400 dark:hover:text-yellow-300 mr-2">
                    <i class="fas fa-edit"></i>
                </button>
                <button onclick="deleteMessage(${message.message_id})" class="text-red-500 hover:text-red-600 dark:text-red-400 dark:hover:text-red-300">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `;
    chatMessages.appendChild(newMessageElement);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

const style = document.createElement('style');
style.textContent = `
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in {
        animation: fadeIn 0.3s ease-out forwards;
    }
`;
document.head.appendChild(style);

$(document).ready(function() {
    $('#message-form').on('submit', function(e) {
    e.preventDefault();
    const form = $(this);
    const formData = form.serialize();
    const messageText = form.find('input[name="message"]').val();
    
    // Format current time immediately in the desired format
    const now = new Date();
    const formattedTime = now.toLocaleString('en-US', { 
        month: 'short',
        day: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false
    });

    // Create temporary message element with a temporary ID
    const tempMessageId = 'temp-' + Date.now();
    const tempMessage = {
        message_id: tempMessageId,
        message: messageText,
        created_at: formattedTime // Use formatted time directly
    };

    // Add message to chat immediately
    addMessageToChat(tempMessage);
    form[0].reset();

    // Rest of your AJAX code remains the same
    $.post('chat.php', formData)
        .done(function(response) {
            if (typeof response === 'string') {
                try {
                    response = JSON.parse(response);
                } catch (e) {
                    if (response.includes('message-')) {
                        return;
                    }
                }
            }

            if (response.success) {
                $(`#message-${tempMessageId}`).attr('id', `message-${response.message_id}`);
            } else {
                if (response.error) {
                    $(`#message-${tempMessageId}`).remove();
                    alert('Failed to send message. Please try again.');
                }
            }
        })
        .fail(function(jqXHR) {
            if (jqXHR.status !== 200) {
                $(`#message-${tempMessageId}`).remove();
                alert('Failed to send message. Please try again.');
            }
        });
});

    $('#new-conversation-form').on('submit', function(e) {
    e.preventDefault();
    const formData = $(this).serialize();
    
    // Show loading overlay
    toggleLoading(true);
    
    // Add timeout to prevent infinite loading
    let timeoutId = setTimeout(() => {
        toggleLoading(false);
        location.reload(); // Force reload if taking too long
    }, 5000); // 5 seconds timeout
    
    $.post('chat.php', formData)
        .done(function(response) {
            clearTimeout(timeoutId); // Clear timeout on success
            
            // Parse the response if it's a string
            if (typeof response === 'string') {
                try {
                    response = JSON.parse(response);
                } catch (e) {
                    // If response can't be parsed as JSON, check if it contains HTML
                    if (response.includes('conversation_id')) {
                        // Force reload for first conversation
                        location.reload();
                        return;
                    }
                    throw new Error('Invalid response format');
                }
            }

            if (response.success) {
                try {
                    // Create and prepend the new conversation to the list
                    const newConversationHtml = createConversationElement(response);
                    const conversationList = document.getElementById('conversation-list');
                    
                    if (!conversationList) {
                        // If conversation list doesn't exist yet (first chat)
                        location.reload();
                        return;
                    }
                    
                    // Add the new conversation at the top
                    conversationList.insertAdjacentHTML('afterbegin', newConversationHtml);
                    
                    // Update chat area to show the new conversation
                    updateChatArea(response.conversation_id, response.receiver_name, response.receiver_email);
                    
                    // Close the modal and reset form
                    document.getElementById('newConversationModal').classList.add('hidden');
                    $('#new-conversation-form')[0].reset();
                    
                    // Update conversation count
                    const countElement = document.querySelector('.text-sm.text-gray-500');
                    if (countElement) {
                        const currentCount = parseInt(countElement.textContent) || 0;
                        countElement.textContent = `${currentCount + 1} chats`;
                    }
                } catch (error) {
                    // If any error occurs during DOM manipulation, force reload
                    location.reload();
                }
            }
        })
        .fail(function(jqXHR, textStatus, errorThrown) {
            clearTimeout(timeoutId); // Clear timeout on fail
            // Only show error if it's a real error, not a successful HTML response
            if (jqXHR.status !== 200) {
                alert('Failed to create conversation. Please try again.');
            } else {
                // If status is 200 but we're here, reload the page
                location.reload();
            }
        })
        .always(function() {
            clearTimeout(timeoutId); // Clear timeout just in case
            toggleLoading(false);
        });
});

    // Auto-scroll to bottom of chat
    const chatMessages = document.getElementById('chat-messages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    // Periodically check for new messages
    setInterval(function() {
        if (window.location.search.includes('conversation_id')) {
            const conversationId = new URLSearchParams(window.location.search).get('conversation_id');
            $.get('chat.php?conversation_id=' + conversationId, function(data) {
                const $data = $(data);
                const newMessages = $data.find('#chat-messages').html();
                if (newMessages !== $('#chat-messages').html()) {
                    $('#chat-messages').html(newMessages);
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }
            });
        }
        
        // Update conversation list
        $.get('chat.php', function(data) {
            const $data = $(data);
            const newConversationList = $data.find('#conversation-list').html();
            if (newConversationList !== $('#conversation-list').html()) {
                $('#conversation-list').html(newConversationList);
            }
        });
    }, 5000);
});
</script>
    <script src="theme.js"></script>
</body>
</html>