<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';

header('Content-Type: application/json');

SessionManager::requireRole(['worker']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid security token']);
    exit;
}

$user_id = $_SESSION['user_id'];
$available = intval($_POST['available'] ?? 0) === 1;

try {
    $stmt = $conn->prepare("UPDATE worker_profiles SET is_available = ? WHERE user_id = ?");
    $stmt->bind_param("ii", $available, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Availability updated successfully',
            'available' => $available
        ]);
    } else {
        throw new Exception('Failed to update availability');
    }

} catch (Exception $e) {
    error_log("Update availability error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to update availability']);
}

$conn->close();
?>
