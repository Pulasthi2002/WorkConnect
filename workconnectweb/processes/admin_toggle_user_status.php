<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';

header('Content-Type: application/json');

SessionManager::requireRole(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid security token']);
    exit;
}

$user_id = intval($_POST['user_id'] ?? 0);
$status = in_array($_POST['status'] ?? '', ['active', 'disabled']) ? $_POST['status'] : '';

if ($user_id <= 0 || empty($status)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user ID or status']);
    exit;
}

try {
    // Verify user exists and is not admin
    $stmt = $conn->prepare("SELECT id, name, role FROM users WHERE id = ? AND role != 'admin'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'User not found or cannot be modified']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    
    // Update user status
    $stmt = $conn->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $status, $user_id);
    
    if ($stmt->execute()) {
        $action = $status === 'active' ? 'activated' : 'disabled';
        echo json_encode([
            'status' => 'success',
            'message' => "User '{$user['name']}' has been {$action} successfully!"
        ]);
    } else {
        throw new Exception('Failed to update user status');
    }

} catch (Exception $e) {
    error_log("Admin toggle user status error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to update user status']);
}

$conn->close();
?>
