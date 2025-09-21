<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

SessionManager::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid security token']);
    exit;
}

$user_id = $_SESSION['user_id'];
$other_user_id = intval($_POST['other_user_id'] ?? 0);

if ($other_user_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user ID']);
    exit;
}

try {
    // Get messages between these two users
    $stmt = $conn->prepare("
        SELECT m.*, u.name as sender_name
        FROM messages m
        INNER JOIN users u ON m.sender_id = u.id
        WHERE (m.sender_id = ? AND m.receiver_id = ?) 
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.sent_at ASC
    ");
    $stmt->bind_param("iiii", $user_id, $other_user_id, $other_user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $messages_html = '';
    
    if ($result->num_rows === 0) {
        $messages_html = '<div class="text-center text-muted py-4">
                            <i class="fas fa-comments fa-2x mb-2"></i>
                            <p>No messages yet. Start the conversation!</p>
                          </div>';
    } else {
        while ($message = $result->fetch_assoc()) {
            $is_sender = $message['sender_id'] == $user_id;
            $message_class = $is_sender ? 'sent' : 'received';
            
            $messages_html .= '<div class="message ' . $message_class . '">';
            $messages_html .= '  <div class="message-bubble">';
            $messages_html .= '    ' . htmlspecialchars($message['message']);
            $messages_html .= '  </div>';
            $messages_html .= '  <div class="message-time">';
            $messages_html .= '    ' . Functions::timeAgo($message['sent_at']);
            $messages_html .= '  </div>';
            $messages_html .= '</div>';
        }
    }

    echo json_encode([
        'status' => 'success',
        'messages_html' => $messages_html,
        'message_count' => $result->num_rows
    ]);

} catch (Exception $e) {
    error_log("Get messages error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to load messages']);
}

$conn->close();
?>
