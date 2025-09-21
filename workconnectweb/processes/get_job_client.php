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

$job_id = intval($_POST['job_id'] ?? 0);

if ($job_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid job ID']);
    exit;
}

try {
    // Get client user ID for the job
    $stmt = $conn->prepare("
        SELECT u.id as client_user_id, u.name as client_name
        FROM job_postings jp
        INNER JOIN users u ON jp.client_id = u.id
        WHERE jp.id = ? AND u.status = 'active'
    ");
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Job or client not found']);
        exit;
    }
    
    $client = $result->fetch_assoc();
    
    echo json_encode([
        'status' => 'success',
        'client_user_id' => $client['client_user_id'],
        'client_name' => $client['client_name']
    ]);

} catch (Exception $e) {
    error_log("Get job client error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to get client information']);
}

$conn->close();
?>
