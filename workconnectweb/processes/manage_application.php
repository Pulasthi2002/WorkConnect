<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
$application_id = intval($_POST['application_id'] ?? 0);
$action = in_array($_POST['action'] ?? '', ['accepted', 'rejected']) ? $_POST['action'] : '';

// Debug logging
error_log("Processing application: ID=$application_id, Action=$action, User=$user_id");

if ($application_id <= 0 || empty($action)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid application ID or action']);
    exit;
}

try {
    // Start transaction for atomic operations
    $conn->begin_transaction();
    
    // Get application details with job info and worker info
    $verify_sql = "
        SELECT 
            ja.id, ja.status, ja.job_id, ja.worker_id,
            jp.client_id, jp.title, jp.status as job_status,
            u.name as worker_name
        FROM job_applications ja
        INNER JOIN job_postings jp ON ja.job_id = jp.id
        INNER JOIN worker_profiles wp ON ja.worker_id = wp.id
        INNER JOIN users u ON wp.user_id = u.id
        WHERE ja.id = ? AND jp.client_id = ?
    ";
    
    $stmt = $conn->prepare($verify_sql);
    
    if ($stmt === false) {
        error_log("Prepare failed for verification query: " . $conn->error);
        throw new Exception('Database error during verification');
    }
    
    $stmt->bind_param("ii", $application_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Application not found or access denied');
    }
    
    $application = $result->fetch_assoc();
    
    // Check if application is still pending
    if ($application['status'] !== 'pending') {
        throw new Exception('This application has already been processed');
    }
    
    // Check if job is still open
    if ($application['job_status'] !== 'open') {
        throw new Exception('Cannot process applications for closed jobs');
    }
    
    // Update application status
    $update_sql = "UPDATE job_applications SET status = ?, updated_at = NOW() WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    
    if ($update_stmt === false) {
        error_log("Prepare failed for update query: " . $conn->error);
        throw new Exception('Database error during update');
    }
    
    $update_stmt->bind_param("si", $action, $application_id);
    
    if (!$update_stmt->execute()) {
        error_log("Execute failed for application update: " . $update_stmt->error);
        throw new Exception('Failed to update application status');
    }
    
    // If accepted, assign worker to job and update job status
    if ($action === 'accepted') {
        // First, reject all other pending applications for this job
        $reject_others_sql = "UPDATE job_applications SET status = 'rejected', updated_at = NOW() WHERE job_id = ? AND id != ? AND status = 'pending'";
        $reject_stmt = $conn->prepare($reject_others_sql);
        
        if ($reject_stmt) {
            $reject_stmt->bind_param("ii", $application['job_id'], $application_id);
            $reject_stmt->execute();
            $reject_stmt->close();
        }
        
        // Update job: assign worker and change status to 'assigned'
        $job_update_sql = "UPDATE job_postings SET assigned_worker_id = ?, status = 'assigned', updated_at = NOW() WHERE id = ?";
        $job_stmt = $conn->prepare($job_update_sql);
        
        if ($job_stmt === false) {
            error_log("Prepare failed for job update: " . $conn->error);
            throw new Exception('Database error during job assignment');
        }
        
        $job_stmt->bind_param("ii", $application['worker_id'], $application['job_id']);
        
        if (!$job_stmt->execute()) {
            error_log("Execute failed for job update: " . $job_stmt->error);
            throw new Exception('Failed to assign worker to job');
        }
        
        $job_stmt->close();
        
        error_log("Job {$application['job_id']} assigned to worker {$application['worker_id']} ({$application['worker_name']})");
    }
    
    $update_stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    $message = $action === 'accepted' 
        ? "Application accepted successfully! Worker has been assigned to the job." 
        : "Application rejected successfully!";
        
    echo json_encode([
        'status' => 'success',
        'message' => $message
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Manage application error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to process application: ' . $e->getMessage()]);
}

$conn->close();
?>
