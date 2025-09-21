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
    // Verify job exists
    $stmt = $conn->prepare("SELECT id, title FROM job_postings WHERE id = ?");
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Job not found']);
        exit;
    }
    
    $job = $result->fetch_assoc();
    
    // Start transaction
    $conn->begin_transaction();
    
    // Delete related data first (foreign key constraints)
    
    // Delete job applications
    $stmt = $conn->prepare("DELETE FROM job_applications WHERE job_id = ?");
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    
    // Delete reviews related to this job
    $stmt = $conn->prepare("DELETE FROM reviews WHERE job_id = ?");
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    
    // Delete messages related to this job
    $stmt = $conn->prepare("DELETE FROM messages WHERE job_id = ?");
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    
    // Delete worker matching scores
    $stmt = $conn->prepare("DELETE FROM worker_matching_scores WHERE job_id = ?");
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    
    // Finally, delete the job posting
    $stmt = $conn->prepare("DELETE FROM job_postings WHERE id = ?");
    $stmt->bind_param("i", $job_id);
    
    if ($stmt->execute()) {
        $conn->commit();
        echo json_encode([
            'status' => 'success',
            'message' => "Job '{$job['title']}' and all related data deleted successfully!"
        ]);
    } else {
        throw new Exception('Failed to delete job');
    }

} catch (Exception $e) {
    $conn->rollback();
    error_log("Admin delete job error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete job. Please try again.']);
}

$conn->close();
?>
