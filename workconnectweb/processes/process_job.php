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

// Validate CSRF token
if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid security token']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Sanitize input
$title = Security::sanitizeInput($_POST['title'] ?? '');
$category_id = intval($_POST['category_id'] ?? 0);
$service_id = intval($_POST['service_id'] ?? 0);
$description = Security::sanitizeInput($_POST['description'] ?? '');
$location_address = Security::sanitizeInput($_POST['location_address'] ?? '');
$budget_type = in_array($_POST['budget_type'] ?? '', ['fixed', 'hourly', 'negotiable']) ? $_POST['budget_type'] : 'negotiable';
$budget_min = ($budget_type !== 'negotiable' && !empty($_POST['budget_min'])) ? floatval($_POST['budget_min']) : null;
$budget_max = ($budget_type !== 'negotiable' && !empty($_POST['budget_max'])) ? floatval($_POST['budget_max']) : null;
$urgency = in_array($_POST['urgency'] ?? '', ['low', 'medium', 'high', 'urgent']) ? $_POST['urgency'] : 'medium';

// Validation
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
    // Verify service exists
    $stmt = $conn->prepare("
        SELECT s.id, s.service_name, sc.name as category_name 
        FROM services s 
        INNER JOIN service_categories sc ON s.category_id = sc.id 
        WHERE s.id = ? AND sc.id = ? AND s.is_active = 1
    ");
    $stmt->bind_param("ii", $service_id, $category_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid service selection']);
        exit;
    }
    
    // Insert job posting
    $stmt = $conn->prepare("
        INSERT INTO job_postings (
            client_id, service_id, title, description, location_address,
            budget_type, budget_min, budget_max, urgency, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', NOW())
    ");
    
    $stmt->bind_param("iissssdds", 
        $user_id, $service_id, $title, $description, $location_address,
        $budget_type, $budget_min, $budget_max, $urgency
    );
    
    if ($stmt->execute()) {
        $job_id = $conn->insert_id;
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Job posted successfully! Workers can now see and apply for your job.',
            'job_id' => $job_id
        ]);
    } else {
        throw new Exception("Failed to post job");
    }

} catch (Exception $e) {
    error_log("Job posting error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to post job. Please try again.']);
}

$conn->close();
?>
