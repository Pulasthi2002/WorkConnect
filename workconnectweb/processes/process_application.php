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
$application_id = intval($_POST['application_id'] ?? 0);
$action = Security::sanitizeInput($_POST['action'] ?? '');
$message = Security::sanitizeInput($_POST['message'] ?? '');

if ($application_id <= 0 || !in_array($action, ['accept', 'reject'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid application ID or action']);
    exit;
}

try {
    // Verify application belongs to user's job
    $stmt = $conn->prepare("
        SELECT ja.*, jp.title as job_title, u.name as worker_name, wp.user_id as worker_user_id
        FROM job_applications ja
        INNER JOIN job_postings jp ON ja.job_id = jp.id
        INNER JOIN worker_profiles wp ON ja.worker_id = wp.id
        INNER JOIN users u ON wp.user_id = u.id
        WHERE ja.id = ? AND jp.client_id = ? AND ja.status = 'pending'
    ");
    $stmt->bind_param("ii", $application_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Application not found or already processed']);
        exit;
    }
    
    $application = $result->fetch_assoc();
    
    // Start transaction
    $conn->begin_transaction();
    
    // Update application status
    $new_status = $action === 'accept' ? 'accepted' : 'rejected';
    $stmt = $conn->prepare("UPDATE job_applications SET status = ?, response_message = ?, responded_at = NOW() WHERE id = ?");
    $stmt->bind_param("ssi", $new_status, $message, $application_id);
    $stmt->execute();
    
    // If accepted, update job status and assign worker
    if ($action === 'accept') {
        $stmt = $conn->prepare("UPDATE job_postings SET status = 'assigned', assigned_worker_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $application['worker_id'], $application['job_id']);
        $stmt->execute();
        
        // Reject other pending applications for this job
        $stmt = $conn->prepare("UPDATE job_applications SET status = 'rejected', response_message = 'Job was assigned to another worker' WHERE job_id = ? AND id != ? AND status = 'pending'");
        $stmt->bind_param("ii", $application['job_id'], $application_id);
        $stmt->execute();
    }
    
    // Send notification to worker
    $notification_title = $action === 'accept' ? 'Application Accepted!' : 'Application Response';
    $notification_message = $action === 'accept' 
        ? "Congratulations! Your application for '{$application['job_title']}' has been accepted."
        : "Your application for '{$application['job_title']}' has been reviewed.";
    
    // Create notification (assuming we have a notifications table)
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, created_at) VALUES (?, 'application_response', ?, ?, NOW())");
    $stmt->bind_param("iss", $application['worker_user_id'], $notification_title, $notification_message);
    $stmt->execute();
    
    $conn->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => $action === 'accept' ? 'Application accepted successfully!' : 'Application rejected.',
        'action' => $action
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Process application error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to process application']);
}

$conn->close();
?>