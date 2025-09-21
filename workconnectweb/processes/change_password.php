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
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Validation
if (empty($current_password)) {
    echo json_encode(['status' => 'error', 'message' => 'Current password is required']);
    exit;
}

if ($new_password !== $confirm_password) {
    echo json_encode(['status' => 'error', 'message' => 'New passwords do not match']);
    exit;
}

$password_errors = Security::validatePassword($new_password);
if (!empty($password_errors)) {
    echo json_encode(['status' => 'error', 'message' => implode('. ', $password_errors)]);
    exit;
}

try {
    // Get current password hash
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    
    // Verify current password
    if (!Security::verifyPassword($current_password, $user['password'])) {
        echo json_encode(['status' => 'error', 'message' => 'Current password is incorrect']);
        exit;
    }
    
    // Update password
    $new_password_hash = Security::hashPassword($new_password);
    $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $new_password_hash, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Password changed successfully'
        ]);
    } else {
        throw new Exception('Failed to update password');
    }

} catch (Exception $e) {
    error_log("Change password error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to change password']);
}

$conn->close();
?>
