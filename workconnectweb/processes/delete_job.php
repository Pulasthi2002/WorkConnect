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

$user_id = $_SESSION['user_id'];
$job_id = intval($_POST['job_id'] ?? 0);

if ($job_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid job ID']);
    exit;
}

try {
    // Verify job ownership
    $stmt = $conn->prepare("
        SELECT id, title, status 
        FROM job_postings 
        WHERE id = ? AND client_id = ?
    ");
    $stmt->bind_param("ii", $job_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Job not found or access denied']);
        exit;
    }
    
    $job = $result->fetch_assoc();
    
    // Check if job can be deleted
    if ($job['status'] !== 'open') {
        echo json_encode(['status' => 'error', 'message' => 'Only open jobs can be deleted']);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    // Delete job applications first
    $stmt = $conn->prepare("DELETE FROM job_applications WHERE job_id = ?");
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    
    // Delete the job
    $stmt = $conn->prepare("DELETE FROM job_postings WHERE id = ?");
    $stmt->bind_param("i", $job_id);
    
    if ($stmt->execute()) {
        $conn->commit();
        echo json_encode([
            'status' => 'success',
            'message' => 'Job deleted successfully'
        ]);
    } else {
        throw new Exception('Failed to delete job');
    }

} catch (Exception $e) {
    $conn->rollback();
    error_log("Delete job error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete job. Please try again.']);
}

$conn->close();
?>
