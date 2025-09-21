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

$job_id = intval($_POST['job_id'] ?? 0);

if ($job_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid job ID']);
    exit;
}

try {
    // Get job data for editing
    $stmt = $conn->prepare("
        SELECT 
            jp.*,
            sc.id as category_id,
            s.category_id as service_category_id
        FROM job_postings jp
        INNER JOIN services s ON jp.service_id = s.id
        INNER JOIN service_categories sc ON s.category_id = sc.id
        WHERE jp.id = ?
    ");
    
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Job not found']);
        exit;
    }
    
    $job = $result->fetch_assoc();
    
    echo json_encode([
        'status' => 'success',
        'job' => $job
    ]);

} catch (Exception $e) {
    error_log("Admin get job error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to load job data']);
}

$conn->close();
?>
