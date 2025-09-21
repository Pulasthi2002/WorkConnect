<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';

header('Content-Type: application/json');

SessionManager::requireLogin();

// Rate limiting
if (!Security::rateLimitCheck('send_message', 30, 60)) {
    echo json_encode(['status' => 'error', 'message' => 'Too many messages. Please wait before sending more.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid security token']);
    exit;
}

$user_id = $_SESSION['user_id'];
$receiver_id = intval($_POST['receiver_id'] ?? 0);
$message = Security::sanitizeInput($_POST['message'] ?? '');

// Validation
if ($receiver_id <= 0 || $receiver_id === $user_id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid receiver']);
    exit;
}

if (empty($message) || strlen($message) > 500) {
    echo json_encode(['status' => 'error', 'message' => 'Message must be between 1 and 500 characters']);
    exit;
}

try {
    // Verify receiver exists and is active
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND status = 'active'");
    $stmt->bind_param("i", $receiver_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Recipient not found']);
        exit;
    }

    // Insert message
    $stmt = $conn->prepare("
        INSERT INTO messages (sender_id, receiver_id, message, sent_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->bind_param("iis", $user_id, $receiver_id, $message);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Message sent successfully',
            'message_id' => $conn->insert_id
        ]);
    } else {
        throw new Exception("Failed to send message");
    }

} catch (Exception $e) {
    error_log("Send message error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to send message. Please try again.']);
}

$conn->close();
?>