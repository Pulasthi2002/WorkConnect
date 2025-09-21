// processes/withdraw_application.php
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
$application_id = intval($_POST['application_id'] ?? 0);

if ($application_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid application ID']);
    exit;
}

try {
    // Get worker profile
    $stmt = $conn->prepare("SELECT id FROM worker_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $worker_profile = $stmt->get_result()->fetch_assoc();
    
    if (!$worker_profile) {
        echo json_encode(['status' => 'error', 'message' => 'Worker profile not found']);
        exit;
    }

    // Verify application ownership and status
    $stmt = $conn->prepare("
        SELECT ja.id, ja.status, jp.title 
        FROM job_applications ja
        INNER JOIN job_postings jp ON ja.job_id = jp.id
        WHERE ja.id = ? AND ja.worker_id = ? AND ja.status = 'pending'
    ");
    $stmt->bind_param("ii", $application_id, $worker_profile['id']);
    $stmt->execute();
    $application = $stmt->get_result()->fetch_assoc();
    
    if (!$application) {
        echo json_encode(['status' => 'error', 'message' => 'Application not found or cannot be withdrawn']);
        exit;
    }

    // Delete the application
    $stmt = $conn->prepare("DELETE FROM job_applications WHERE id = ?");
    $stmt->bind_param("i", $application_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Application withdrawn successfully'
        ]);
    } else {
        throw new Exception('Failed to withdraw application');
    }

} catch (Exception $e) {
    error_log("Withdraw application error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to withdraw application']);
}

$conn->close();
?>
