<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';

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
$sender_id = intval($_POST['sender_id'] ?? 0);

if ($sender_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid sender ID']);
    exit;
}

try {
    // Mark messages as read
    $stmt = $conn->prepare("
        UPDATE messages 
        SET is_read = 1 
        WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
    ");
    $stmt->bind_param("ii", $sender_id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Messages marked as read',
            'updated_count' => $stmt->affected_rows
        ]);
    } else {
        throw new Exception('Failed to mark messages as read');
    }

} catch (Exception $e) {
    error_log("Mark messages read error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to mark messages as read']);
}

$conn->close();
?>



