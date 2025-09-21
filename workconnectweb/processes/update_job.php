<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

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

// Sanitize input (same validation as process_job.php)
$title = Security::sanitizeInput($_POST['title'] ?? '');
$service_id = intval($_POST['service_id'] ?? 0);
$description = Security::sanitizeInput($_POST['description'] ?? '');
$location_address = Security::sanitizeInput($_POST['location_address'] ?? '');
$budget_type = in_array($_POST['budget_type'] ?? '', ['fixed', 'hourly', 'negotiable']) ? $_POST['budget_type'] : 'negotiable';
$budget_min = ($budget_type !== 'negotiable' && !empty($_POST['budget_min'])) ? floatval($_POST['budget_min']) : null;
$budget_max = ($budget_type !== 'negotiable' && !empty($_POST['budget_max'])) ? floatval($_POST['budget_max']) : null;
$urgency = in_array($_POST['urgency'] ?? '', ['low', 'medium', 'high', 'urgent']) ? $_POST['urgency'] : 'medium';

// Validation (same as process_job.php)
$errors = [];

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
    // Verify job ownership and status
    $stmt = $conn->prepare("
        SELECT id, status 
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
    
    if ($job['status'] !== 'open') {
        echo json_encode(['status' => 'error', 'message' => 'Only open jobs can be edited']);
        exit;
    }
    
    $stmt = $conn->prepare("
    UPDATE job_postings SET
        service_id = ?, title = ?, description = ?, location_address = ?,
        budget_type = ?, budget_min = ?, budget_max = ?, urgency = ?, 
        updated_at = NOW()
    WHERE id = ?
");

$stmt->bind_param("issssddsi", 
    $service_id, $title, $description, $location_address,
    $budget_type, $budget_min, $budget_max, $urgency, $job_id
);

    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Job updated successfully!'
        ]);
    } else {
        throw new Exception("Failed to update job");
    }

} catch (Exception $e) {
    error_log("Update job error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to update job. Please try again.']);
}

$conn->close();
?>
