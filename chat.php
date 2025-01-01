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

// Function to get user conversations
function getUserConversations($conn, $userId) {
    $stmt = $conn->prepare("
        SELECT c.id, u.email, u.name,
            (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.receiver_id = ? AND m.read = 0) as unread_count
        FROM conversations c
        JOIN users u ON (c.user1_id = u.id OR c.user2_id = u.id)
        WHERE (c.user1_id = ? OR c.user2_id = ?) AND u.id != ?
        ORDER BY c.last_message_at DESC
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

// Handle new message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'send_message') {
        $receiverEmail = $_POST['receiver_email'];
        $message = $_POST['message'];

        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
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

            header("Location: chat.php?conversation_id=" . $conversationId);
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - SignEase</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * {
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }
    </style>
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
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900">
    <div class="p-4 sm:ml-64">
        <div class="p-4 border-2 border-gray-200 border-dashed rounded-lg dark:border-gray-700">
            <h1 class="text-3xl font-bold mb-6 text-gray-800 dark:text-white">Chat</h1>
            
            <div class="overflow-hidden relative shadow-md sm:rounded-lg">
                <div class="flex h-[calc(100vh-200px)]">
                    <!-- Conversation List -->
                    <div class="w-1/3 border-r border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                            <h2 class="text-xl font-semibold text-gray-800 dark:text-white">Conversations</h2>
                        </div>
                        <div class="overflow-y-auto h-full">
                            <?php foreach ($conversations as $conversation): ?>
                                <a href="?conversation_id=<?= $conversation['id'] ?>" class="block p-4 hover:bg-gray-100 dark:hover:bg-gray-700 <?= $activeConversationId == $conversation['id'] ? 'bg-gray-200 dark:bg-gray-600' : '' ?>">
                                    <div class="flex justify-between items-center">
                                        <span class="font-semibold text-gray-800 dark:text-white"><?= htmlspecialchars($conversation['name'] ?: $conversation['email']) ?></span>
                                        <?php if ($conversation['unread_count'] > 0): ?>
                                            <span class="bg-green-500 text-white dark:bg-green-600 dark:text-gray-100 rounded-full px-2 py-1 text-xs"><?= $conversation['unread_count'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Chat Area -->
                    <div class="w-2/3 flex flex-col bg-white dark:bg-gray-800">
                        <?php if ($activeConversationId): ?>
                            <!-- Chat Header -->
                            <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
                                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">
                                    <?= htmlspecialchars($conversations[array_search($activeConversationId, array_column($conversations, 'id'))]['name'] ?: $conversations[array_search($activeConversationId, array_column($conversations, 'id'))]['email']) ?>
                                </h2>
                            </div>

                            <!-- Messages -->
                            <div class="flex-1 overflow-y-auto p-4" id="chat-messages">
                                <?php foreach ($messages as $message): ?>
                                    <div class="mb-4 <?= $message['sender_id'] == $_SESSION['user_id'] ? 'text-right' : 'text-left' ?>" id="message-<?= $message['id'] ?>">
                                        <div class="inline-block p-2 rounded-lg <?= $message['sender_id'] == $_SESSION['user_id'] ? 'bg-blue-500 text-white dark:bg-blue-600' : 'bg-gray-300 text-gray-800 dark:bg-gray-600 dark:text-white' ?> relative group">
                                            <span class="message-text"><?= htmlspecialchars($message['message']) ?></span>
                                            <div class="text-xs mt-1 text-gray-500 dark:text-gray-400">
                                                <?= date('M d, Y H:i', strtotime($message['created_at'])) ?>
                                            </div>
                                            <?php if ($message['sender_id'] == $_SESSION['user_id']): ?>
                                                <div class="hidden group-hover:block absolute top-0 right-0 -mt-2 -mr-2">
                                                    <button onclick="editMessage(<?= $message['id'] ?>)" class="bg-yellow-500 text-white dark:bg-yellow-600 dark:text-gray-100 rounded-full p-1 text-xs mr-1">Edit</button>
                                                    <button onclick="deleteMessage(<?= $message['id'] ?>)" class="bg-red-500 text-white dark:bg-red-600 dark:text-gray-100 rounded-full p-1 text-xs">Delete</button>
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
                                    <input type="hidden" name="receiver_email" value="<?= htmlspecialchars($conversations[array_search($activeConversationId, array_column($conversations, 'id'))]['email']) ?>">
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

    $(document).ready(function() {
        $('#message-form').on('submit', function(e) {
            e.preventDefault();
            $.post('chat.php', $(this).serialize(), function(response) {
                location.reload();
            });
        });

        $('#new-conversation-form').on('submit', function(e) {
            e.preventDefault();
            $.post('chat.php', $(this).serialize(), function(response) {
                location.reload();
            });
        });

        // Auto-scroll to bottom of chat
        const chatMessages = document.getElementById('chat-messages');
        chatMessages.scrollTop = chatMessages.scrollHeight;
    });
    </script>
    <script src="theme.js"></script>
</body>
</html>