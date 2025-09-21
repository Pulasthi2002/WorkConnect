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

try {
    // Get all active workers
    $stmt = $conn->prepare("
        SELECT 
            wp.id,
            u.name,
            u.email,
            wp.experience_years,
            wp.average_rating,
            wp.is_available
        FROM worker_profiles wp
        INNER JOIN users u ON wp.user_id = u.id
        WHERE u.status = 'active'
        ORDER BY u.name
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $workers = [];
    while ($worker = $result->fetch_assoc()) {
        $workers[] = $worker;
    }
    
    echo json_encode([
        'status' => 'success',
        'workers' => $workers
    ]);

} catch (Exception $e) {
    error_log("Get available workers error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to load workers']);
}

$conn->close();
?>
