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

if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'No file uploaded or upload error']);
    exit;
}

try {
    // Create upload directory if it doesn't exist
    $upload_dir = '../uploads/profiles/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $upload_result = Functions::uploadFile($_FILES['profile_image'], $upload_dir);
    
    if (!$upload_result['success']) {
        echo json_encode(['status' => 'error', 'message' => $upload_result['message']]);
        exit;
    }

    // Update user profile image path
    $image_path = 'uploads/profiles/' . $upload_result['filename'];
    $stmt = $conn->prepare("UPDATE users SET profile_image = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $image_path, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Profile image updated successfully!',
            'image_path' => $image_path
        ]);
    } else {
        throw new Exception('Failed to update profile image in database');
    }

} catch (Exception $e) {
    error_log("Upload profile image error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to upload profile image']);
}

$conn->close();
?>
