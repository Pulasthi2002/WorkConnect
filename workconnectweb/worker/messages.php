<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

SessionManager::requireLogin();

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Get conversation ID if specified
$conversation_with = intval($_GET['with'] ?? 0);
$selected_conversation = null;

// Get all conversations for current user
$conversations = [];
try {
    $stmt = $conn->prepare("
        SELECT DISTINCT
            CASE 
                WHEN m.sender_id = ? THEN m.receiver_id 
                ELSE m.sender_id 
            END as other_user_id,
            u.name as other_user_name,
            u.role as other_user_role,
            MAX(m.sent_at) as last_message_time,
            (SELECT message FROM messages m2 
             WHERE (m2.sender_id = ? AND m2.receiver_id = other_user_id) 
                OR (m2.sender_id = other_user_id AND m2.receiver_id = ?)
             ORDER BY m2.sent_at DESC LIMIT 1) as last_message,
            COUNT(CASE WHEN m.receiver_id = ? AND m.is_read = 0 THEN 1 END) as unread_count
        FROM messages m
        INNER JOIN users u ON u.id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END
        WHERE m.sender_id = ? OR m.receiver_id = ?
        GROUP BY other_user_id, u.name, u.role
        ORDER BY last_message_time DESC
    ");
    $stmt->bind_param("iiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // If conversation_with is specified, get that user's info
    if ($conversation_with > 0) {
        $stmt = $conn->prepare("SELECT id, name, role FROM users WHERE id = ? AND status = 'active'");
        $stmt->bind_param("i", $conversation_with);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $selected_conversation = $result->fetch_assoc();
        }
    }
} catch (Exception $e) {
    error_log("Messages error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .messages-container { height: calc(100vh - 120px); }
        .conversations-list { 
            height: 100%; 
            overflow-y: auto; 
            background: #f8f9fa; 
            border-right: 1px solid #dee2e6;
        }
        .conversation-item {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .conversation-item:hover, .conversation-item.active {
            background: white;
            border-left: 4px solid var(--primary);
        }
        .chat-area {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .chat-header {
            background: white;
            border-bottom: 1px solid #dee2e6;
            padding: 20px;
        }
        .messages-area {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #f8f9fa;
        }
        .message {
            margin-bottom: 15px;
            max-width: 70%;
        }
        .message.sent {
            margin-left: auto;
            text-align: right;
        }
        .message-bubble {
            padding: 12px 16px;
            border-radius: 18px;
            word-wrap: break-word;
        }
        .message.sent .message-bubble {
            background: var(--primary);
            color: white;
            border-bottom-right-radius: 4px;
        }
        .message.received .message-bubble {
            background: white;
            border: 1px solid #e9ecef;
            border-bottom-left-radius: 4px;
        }
        .message-time {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 5px;
        }
        .message-input {
            background: white;
            border-top: 1px solid #dee2e6;
            padding: 20px;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .unread-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-<?php echo $user_role === 'worker' ? 'success' : 'primary'; ?>">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-tools me-2"></i><?php echo APP_NAME; ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid p-0">
        <div class="row g-0 messages-container">
            <!-- Conversations List -->
            <div class="col-md-4">
                <div class="conversations-list">
                    <div class="p-3 border-bottom bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-comments me-2"></i>Messages
                        </h5>
                    </div>
                    
                    <?php if (empty($conversations)): ?>
                        <div class="text-center p-4">
                            <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                            <h6 class="text-muted">No conversations yet</h6>
                            <p class="text-muted small">Messages will appear here when you start chatting</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($conversations as $conv): ?>
                            <div class="conversation-item position-relative <?php echo ($conversation_with == $conv['other_user_id']) ? 'active' : ''; ?>" 
                                 onclick="openConversation(<?php echo $conv['other_user_id']; ?>)">
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar me-3">
                                        <?php echo strtoupper(substr($conv['other_user_name'], 0, 2)); ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($conv['other_user_name']); ?></h6>
                                                <span class="badge bg-<?php echo $conv['other_user_role'] === 'worker' ? 'success' : 'primary'; ?> badge-sm">
                                                    <?php echo ucfirst($conv['other_user_role']); ?>
                                                </span>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo Functions::timeAgo($conv['last_message_time']); ?>
                                            </small>
                                        </div>
                                        <p class="mb-0 text-muted small mt-1">
                                            <?php echo Functions::truncateText($conv['last_message'], 40); ?>
                                        </p>
                                    </div>
                                </div>
                                <?php if ($conv['unread_count'] > 0): ?>
                                    <div class="unread-badge"><?php echo $conv['unread_count']; ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Chat Area -->
            <div class="col-md-8">
                <?php if ($selected_conversation): ?>
                    <div class="chat-area">
                        <!-- Chat Header -->
                        <div class="chat-header">
                            <div class="d-flex align-items-center">
                                <div class="user-avatar me-3">
                                    <?php echo strtoupper(substr($selected_conversation['name'], 0, 2)); ?>
                                </div>
                                <div>
                                    <h6 class="mb-0"><?php echo htmlspecialchars($selected_conversation['name']); ?></h6>
                                    <small class="text-muted">
                                        <i class="fas fa-circle text-success me-1" style="font-size: 0.5rem;"></i>
                                        <?php echo ucfirst($selected_conversation['role']); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Messages Area -->
                        <div class="messages-area" id="messagesArea">
                            <div class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading messages...</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Message Input -->
                        <div class="message-input">
                            <form id="messageForm">
                                <div class="input-group">
                                    <input type="text" class="form-control" id="messageInput" 
                                           placeholder="Type your message..." maxlength="500">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="d-flex align-items-center justify-content-center h-100">
                        <div class="text-center">
                            <i class="fas fa-comments fa-4x text-muted mb-4"></i>
                            <h4 class="text-muted">Select a conversation</h4>
                            <p class="text-muted">Choose a conversation to start messaging</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    <script>
    const currentUserId = <?php echo $user_id; ?>;
    const conversationWith = <?php echo $conversation_with; ?>;
    let messageInterval;

    $(document).ready(function() {
        if (conversationWith > 0) {
            loadMessages();
            // Auto-refresh messages every 3 seconds
            messageInterval = setInterval(loadMessages, 3000);
        }

        // Message form submission
        $('#messageForm').on('submit', function(e) {
            e.preventDefault();
            sendMessage();
        });

        // Auto-scroll to bottom when loading messages
        scrollToBottom();
    });

    function loadMessages() {
        if (conversationWith <= 0) return;

        makeAjaxRequest(
            '../processes/get_messages.php',
            {
                other_user_id: conversationWith,
                csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
            },
            function(response) {
                $('#messagesArea').html(response.messages_html);
                scrollToBottom();
                markMessagesAsRead();
            },
            function(error) {
                console.error('Error loading messages:', error);
            }
        );
    }

    function sendMessage() {
        const message = $('#messageInput').val().trim();
        if (!message || conversationWith <= 0) return;

        const $button = $('#messageForm button[type="submit"]');
        const originalHtml = $button.html();
        $button.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);

        makeAjaxRequest(
            '../processes/send_message.php',
            {
                receiver_id: conversationWith,
                message: message,
                csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
            },
            function(response) {
                $('#messageInput').val('');
                loadMessages();
            },
            function(error) {
                showAlert('danger', error);
            }
        ).always(function() {
            $button.html(originalHtml).prop('disabled', false);
        });
    }

    function markMessagesAsRead() {
        if (conversationWith <= 0) return;

        makeAjaxRequest(
            '../processes/mark_messages_read.php',
            {
                sender_id: conversationWith,
                csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
            },
            function(response) {
                // Update unread badge
                $(`.conversation-item[data-user-id="${conversationWith}"] .unread-badge`).remove();
            }
        );
    }

    function openConversation(userId) {
        window.location.href = `messages.php?with=${userId}`;
    }

    function scrollToBottom() {
        const messagesArea = document.getElementById('messagesArea');
        if (messagesArea) {
            messagesArea.scrollTop = messagesArea.scrollHeight;
        }
    }

    // Cleanup on page unload
    $(window).on('beforeunload', function() {
        if (messageInterval) {
            clearInterval(messageInterval);
        }
    });
    </script>
</body>
</html>
