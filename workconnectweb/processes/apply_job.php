processes\apply_job.php
<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

SessionManager::requireRole(['worker']);

// Rate limiting
if (!Security::rateLimitCheck('job_application', 10, 3600)) {
    echo json_encode(['status' => 'error', 'message' => 'Too many applications. Please wait before applying to more jobs.']);
    exit;
}

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
$proposed_rate = floatval($_POST['proposed_rate'] ?? 0);
$proposed_timeline = Security::sanitizeInput($_POST['proposed_timeline'] ?? '');
$cover_message = Security::sanitizeInput($_POST['cover_message'] ?? '');

// Validation
$errors = [];

if ($job_id <= 0) {
    $errors[] = 'Invalid job ID';
}

if ($proposed_rate <= 0) {
    $errors[] = 'Valid proposed rate is required';
}

if (empty($cover_message) || strlen($cover_message) < 20) {
    $errors[] = 'Cover message must be at least 20 characters';
}

if (!empty($errors)) {
    echo json_encode(['status' => 'error', 'message' => implode('. ', $errors)]);
    exit;
}

try {
    // Get worker profile
    $stmt = $conn->prepare("SELECT id FROM worker_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $worker_result = $stmt->get_result();
    
    if ($worker_result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Worker profile not found']);
        exit;
    }
    
    $worker_id = $worker_result->fetch_assoc()['id'];
    
    // Verify job exists and is open
    $stmt = $conn->prepare("
        SELECT jp.*, u.name as client_name 
        FROM job_postings jp 
        INNER JOIN users u ON jp.client_id = u.id 
        WHERE jp.id = ? AND jp.status = 'open'
    ");
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $job_result = $stmt->get_result();
    
    if ($job_result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Job not found or no longer available']);
        exit;
    }
    
    $job = $job_result->fetch_assoc();
    
    // Check if already applied
    $stmt = $conn->prepare("SELECT id FROM job_applications WHERE job_id = ? AND worker_id = ?");
    $stmt->bind_param("ii", $job_id, $worker_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'You have already applied for this job']);
        exit;
    }
    
    // Insert application
    $stmt = $conn->prepare("
        INSERT INTO job_applications (job_id, worker_id, proposed_rate, proposed_timeline, cover_message, status, applied_at) 
        VALUES (?, ?, ?, ?, ?, 'pending', NOW())
    ");
    
    $stmt->bind_param("iidss", $job_id, $worker_id, $proposed_rate, $proposed_timeline, $cover_message);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Application submitted successfully! The client will be notified.',
            'application_id' => $conn->insert_id
        ]);
    } else {
        throw new Exception("Failed to submit application");
    }

} catch (Exception $e) {
    error_log("Job application error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to submit application. Please try again.']);
}

$conn->close();
?>
