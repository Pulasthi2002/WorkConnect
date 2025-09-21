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
$title = Security::sanitizeInput($_POST['title'] ?? '');
$service_id = intval($_POST['service_id'] ?? 0);
$description = Security::sanitizeInput($_POST['description'] ?? '');
$location_address = Security::sanitizeInput($_POST['location_address'] ?? '');
$budget_type = in_array($_POST['budget_type'] ?? '', ['fixed', 'hourly', 'negotiable']) ? $_POST['budget_type'] : 'negotiable';
$budget_min = ($budget_type !== 'negotiable' && !empty($_POST['budget_min'])) ? floatval($_POST['budget_min']) : null;
$budget_max = ($budget_type !== 'negotiable' && !empty($_POST['budget_max'])) ? floatval($_POST['budget_max']) : null;
$urgency = in_array($_POST['urgency'] ?? '', ['low', 'medium', 'high', 'urgent']) ? $_POST['urgency'] : 'medium';
$status = in_array($_POST['status'] ?? '', ['open', 'assigned', 'completed', 'cancelled', 'paused']) ? $_POST['status'] : 'open';
$assigned_worker_id = !empty($_POST['assigned_worker_id']) ? intval($_POST['assigned_worker_id']) : null;

// Validation
$errors = [];

if ($job_id <= 0) {
    $errors[] = 'Invalid job ID';
}

if (empty($title) || strlen($title) < 5 || strlen($title) > 200) {
    $errors[] = 'Job title must be between 5 and 200 characters';
}

if ($service_id <= 0) {
    $errors[] = 'Please select a valid service';
}

if (empty($description) || strlen($description) < 20 || strlen($description) > 1000) {
    $errors[] = 'Job description must be between 20 and 1000 characters';
}

if (empty($location_address) || strlen($location_address) < 5) {
    $errors[] = 'Please provide a valid location';
}

if ($budget_type !== 'negotiable' && $budget_min !== null && $budget_max !== null) {
    if ($budget_min < 0 || $budget_max < 0) {
        $errors[] = 'Budget amounts must be positive';
    }
    if ($budget_min > $budget_max) {
        $errors[] = 'Minimum budget cannot be greater than maximum budget';
    }
}

if (!empty($errors)) {
    echo json_encode(['status' => 'error', 'message' => implode('. ', $errors)]);
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
    
    // Verify service exists
    $stmt = $conn->prepare("
        SELECT s.id, s.service_name, sc.name as category_name 
        FROM services s 
        INNER JOIN service_categories sc ON s.category_id = sc.id 
        WHERE s.id = ? AND s.is_active = 1
    ");
    $stmt->bind_param("i", $service_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid service selection']);
        exit;
    }
    
    // Verify worker if assigned
    if ($assigned_worker_id) {
        $stmt = $conn->prepare("
            SELECT wp.id, u.name 
            FROM worker_profiles wp 
            INNER JOIN users u ON wp.user_id = u.id 
            WHERE wp.id = ? AND u.status = 'active'
        ");
        $stmt->bind_param("i", $assigned_worker_id);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid worker selection']);
            exit;
        }
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    // Update job
    $stmt = $conn->prepare("
        UPDATE job_postings SET
            service_id = ?, title = ?, description = ?, location_address = ?,
            budget_type = ?, budget_min = ?, budget_max = ?, urgency = ?, 
            status = ?, assigned_worker_id = ?, updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->bind_param("issssddssii", 
        $service_id, $title, $description, $location_address,
        $budget_type, $budget_min, $budget_max, $urgency, 
        $status, $assigned_worker_id, $job_id
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update job");
    }
    
    // If status changed to assigned and worker is assigned, update applications
    if ($status === 'assigned' && $assigned_worker_id) {
        // Accept the application from the assigned worker
        $stmt = $conn->prepare("
            UPDATE job_applications 
            SET status = 'accepted', updated_at = NOW() 
            WHERE job_id = ? AND worker_id = ?
        ");
        $stmt->bind_param("ii", $job_id, $assigned_worker_id);
        $stmt->execute();
        
        // Reject other pending applications
        $stmt = $conn->prepare("
            UPDATE job_applications 
            SET status = 'rejected', updated_at = NOW() 
            WHERE job_id = ? AND worker_id != ? AND status = 'pending'
        ");
        $stmt->bind_param("ii", $job_id, $assigned_worker_id);
        $stmt->execute();
    }
    
    $conn->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Job updated successfully!'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Admin update job error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to update job. Please try again.']);
}

$conn->close();
?>
