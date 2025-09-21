<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

SessionManager::requireRole(['customer']);

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get conversation partner ID if specified
$with_user_id = intval($_GET['with'] ?? 0);

// Get conversations
$conversations = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            m.sender_id, m.receiver_id,
            CASE 
                WHEN m.sender_id = ? THEN m.receiver_id 
                ELSE m.sender_id 
            END as partner_id,
            CASE 
                WHEN m.sender_id = ? THEN receiver.name 
                ELSE sender.name 
            END as partner_name,
            m.message, m.sent_at, m.is_read,
            COUNT(CASE WHEN m.receiver_id = ? AND m.is_read = 0 THEN 1 END) as unread_count
        FROM messages m
        INNER JOIN users sender ON m.sender_id = sender.id
        INNER JOIN users receiver ON m.receiver_id = receiver.id
        WHERE (m.sender_id = ? OR m.receiver_id = ?)
        GROUP BY partner_id
        ORDER BY m.sent_at DESC
    ");
    $stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Conversations error: " . $e->getMessage());
}

// Get messages for selected conversation
$messages = [];
$partner_info = null;
if ($with_user_id > 0) {
    try {
        // Get partner info
        $stmt = $conn->prepare("SELECT name, profile_image FROM users WHERE id = ? AND role = 'worker'");
        $stmt->bind_param("i", $with_user_id);
        $stmt->execute();
        $partner_info = $stmt->get_result()->fetch_assoc();
        
        if ($partner_info) {
            // Get messages
            $stmt = $conn->prepare("
                SELECT m.*, u.name as sender_name 
                FROM messages m
                INNER JOIN users u ON m.sender_id = u.id
                WHERE (m.sender_id = ? AND m.receiver_id = ?) 
                   OR (m.sender_id = ? AND m.receiver_id = ?)
                ORDER BY m.sent_at ASC
            ");
            $stmt->bind_param("iiii", $user_id, $with_user_id, $with_user_id, $user_id);
            $stmt->execute();
            $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Mark messages as read
            $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?");
            $stmt->bind_param("ii", $with_user_id, $user_id);
            $stmt->execute();
        }
    } catch (Exception $e) {
        error_log("Messages error: " . $e->getMessage());
    }
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
        .messages-container { height: 400px; overflow-y: auto; }
        .message-bubble { max-width: 70%; padding: 10px 15px; margin-bottom: 10px; border-radius: 15px; }
        .message-sent { background: #007bff; color: white; margin-left: auto; }
        .message-received { background: #f8f9fa; color: #333; }
        .conversation-item { cursor: pointer; transition: all 0.3s; }
        .conversation-item:hover { background: #f8f9fa; }
        .conversation-item.active { background: #e3f2fd; border-left: 3px solid #007bff; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
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

    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Conversations Sidebar -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-comments me-2"></i>Conversations</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($conversations)): ?>
                            <?php foreach ($conversations as $conv): ?>
                                <div class="conversation-item p-3 border-bottom <?php echo $conv['partner_id'] == $with_user_id ? 'active' : ''; ?>"
                                     onclick="window.location.href='messages.php?with=<?php echo $conv['partner_id']; ?>'">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($conv['partner_name']); ?></h6>
                                            <p class="mb-1 small text-muted">
                                                <?php echo Functions::truncateText($conv['message'], 50); ?>
                                            </p>
                                            <small class="text-muted"><?php echo Functions::timeAgo($conv['sent_at']); ?></small>
                                        </div>
                                        <?php if ($conv['unread_count'] > 0): ?>
                                            <span class="badge bg-primary"><?php echo $conv['unread_count']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No conversations yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Messages Area -->
            <div class="col-md-8">
                <div class="card">
                    <?php if ($partner_info): ?>
                        <div class="card-header">
                            <div class="d-flex align-items-center">
                                <?php if ($partner_info['profile_image']): ?>
                                    <img src="../<?php echo htmlspecialchars($partner_info['profile_image']); ?>" 
                                         class="rounded-circle me-3" width="40" height="40" style="object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" 
                                         style="width: 40px; height: 40px; font-weight: bold;">
                                        <?php echo strtoupper(substr($partner_info['name'], 0, 2)); ?>
                                    </div>
                                <?php endif; ?>
                                <h5 class="mb-0"><?php echo htmlspecialchars($partner_info['name']); ?></h5>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="messages-container" id="messagesContainer">
                                <?php if (!empty($messages)): ?>
                                    <?php foreach ($messages as $message): ?>
                                        <div class="d-flex <?php echo $message['sender_id'] == $user_id ? 'justify-content-end' : 'justify-content-start'; ?>">
                                            <div class="message-bubble <?php echo $message['sender_id'] == $user_id ? 'message-sent' : 'message-received'; ?>">
                                                <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                                <div class="mt-1">
                                                    <small class="opacity-75">
                                                        <?php echo Functions::timeAgo($message['sent_at']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-comment fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Start the conversation by sending a message</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <form id="messageForm" class="mt-3">
                                <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                                <input type="hidden" name="receiver_id" value="<?php echo $with_user_id; ?>">
                                <div class="input-group">
                                    <input type="text" class="form-control" name="message" 
                                           placeholder="Type your message..." required maxlength="500">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="card-body text-center py-5">
                            <i class="fas fa-comments fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">Select a conversation</h5>
                            <p class="text-muted">Choose a conversation from the left to start messaging</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    <script>
    $('#messageForm').on('submit', function(e) {
        e.preventDefault();
        
        const messageText = $('input[name="message"]').val().trim();
        if (!messageText) return;
        
        makeAjaxRequest(
            '../processes/send_message.php',
            $(this).serialize(),
            function(response) {
                // Add message to UI
                const messageHtml = `
                    <div class="d-flex justify-content-end">
                        <div class="message-bubble message-sent">
                            ${messageText}
                            <div class="mt-1">
                                <small class="opacity-75">just now</small>
                            </div>
                        </div>
                    </div>
                `;
                $('#messagesContainer').append(messageHtml);
                $('#messagesContainer').scrollTop($('#messagesContainer')[0].scrollHeight);
                $('input[name="message"]').val('');
            },
            function(error) {
                showAlert('danger', error);
            }
        );
    });

    // Auto-scroll to bottom
    $('#messagesContainer').scrollTop($('#messagesContainer')[0].scrollHeight);
    </script>
</body>
</html>
