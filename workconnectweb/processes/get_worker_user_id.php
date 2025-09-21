<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';

header('Content-Type: application/json');

SessionManager::requireRole(['customer']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid security token']);
    exit;
}

$worker_profile_id = intval($_POST['worker_profile_id'] ?? 0);

if ($worker_profile_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid worker ID']);
    exit;
}

try {
    // Get the user_id from worker_profiles
    $stmt = $conn->prepare("
        SELECT wp.user_id, u.name, u.status 
        FROM worker_profiles wp 
        INNER JOIN users u ON wp.user_id = u.id 
        WHERE wp.id = ? AND u.status = 'active'
    ");
    $stmt->bind_param("i", $worker_profile_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Worker not found']);
        exit;
    }
    
    $worker = $result->fetch_assoc();
    
    echo json_encode([
        'status' => 'success',
        'user_id' => $worker['user_id'],
        'name' => $worker['name']
    ]);

} catch (Exception $e) {
    error_log("Get worker user ID error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}

$conn->close();
?>
