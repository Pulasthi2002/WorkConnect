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
$worker_id = intval($_POST['worker_id'] ?? 0);
$rating = intval($_POST['rating'] ?? 0);
$review_text = Security::sanitizeInput($_POST['review_text'] ?? '');

// Validation
if ($job_id <= 0 || $worker_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid job or worker ID']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['status' => 'error', 'message' => 'Rating must be between 1 and 5']);
    exit;
}

try {
    // Verify job ownership and completion
    $stmt = $conn->prepare("
        SELECT jp.id, wp.user_id as worker_user_id 
        FROM job_postings jp
        INNER JOIN worker_profiles wp ON jp.assigned_worker_id = wp.id
        WHERE jp.id = ? AND jp.client_id = ? AND jp.status = 'completed' AND wp.id = ?
    ");
    $stmt->bind_param("iii", $job_id, $user_id, $worker_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Job not found or cannot be reviewed']);
        exit;
    }
    
    $job_data = $result->fetch_assoc();
    $worker_user_id = $job_data['worker_user_id'];
    
    // Check if already reviewed
    $stmt = $conn->prepare("SELECT id FROM reviews WHERE job_id = ? AND reviewer_id = ?");
    $stmt->bind_param("ii", $job_id, $user_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'You have already reviewed this job']);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    // Insert review
    $stmt = $conn->prepare("
        INSERT INTO reviews (job_id, reviewer_id, reviewee_id, reviewer_type, rating, review_text) 
        VALUES (?, ?, ?, 'customer', ?, ?)
    ");
    $stmt->bind_param("iiiis", $job_id, $user_id, $worker_user_id, $rating, $review_text);
    $stmt->execute();
    
    // Update worker's average rating and total jobs
    $stmt = $conn->prepare("
        UPDATE worker_profiles wp SET 
            average_rating = (
                SELECT AVG(rating) FROM reviews 
                WHERE reviewee_id = wp.user_id AND reviewer_type = 'customer'
            ),
            total_jobs = (
                SELECT COUNT(*) FROM job_postings 
                WHERE assigned_worker_id = wp.id AND status = 'completed'
            )
        WHERE user_id = ?
    ");
    $stmt->bind_param("i", $worker_user_id);
    $stmt->execute();
    
    $conn->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Thank you for your review! It helps other customers make informed decisions.'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Submit review error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to submit review']);
}

$conn->close();
?>