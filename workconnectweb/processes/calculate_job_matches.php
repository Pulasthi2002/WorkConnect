<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/matching_engine.php';

header('Content-Type: application/json');

// Log the request for debugging
error_log("Calculate matches request: " . print_r($_POST, true));

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

error_log("Processing match calculation for user $user_id, job $job_id");

if ($job_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid job ID']);
    exit;
}

try {
    // Verify job ownership
    $stmt = $conn->prepare("SELECT id, title FROM job_postings WHERE id = ? AND client_id = ? AND status = 'open'");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $job_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Job not found or access denied']);
        exit;
    }
    
    $job = $result->fetch_assoc();
    error_log("Job found: " . $job['title']);
    
    // Check if we have any workers in the database
    $worker_count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM worker_profiles wp INNER JOIN users u ON wp.user_id = u.id WHERE u.status = 'active'");
    $worker_count_stmt->execute();
    $worker_count = $worker_count_stmt->get_result()->fetch_assoc()['count'];
    
    error_log("Available workers: " . $worker_count);
    
    if ($worker_count == 0) {
        echo json_encode([
            'status' => 'success',
            'message' => 'No workers available for matching',
            'matches_found' => 0,
            'matches' => []
        ]);
        exit;
    }
    
    // Initialize matching engine
    $matching_engine = new SmartMatchingEngine($conn);
    
    // Calculate matches
    error_log("Starting match calculation...");
    if ($matching_engine->calculateJobMatches($job_id)) {
        error_log("Match calculation successful, getting results...");
        // Get top matches
        $matches = $matching_engine->getTopMatches($job_id, 20);
        
        error_log("Found " . count($matches) . " matches");
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Matching completed successfully',
            'matches_found' => count($matches),
            'matches' => $matches
        ]);
    } else {
        error_log("Match calculation failed");
        throw new Exception('Failed to calculate matches - matching engine returned false');
    }
    
} catch (Exception $e) {
    error_log("Calculate matches error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'status' => 'error', 
        'message' => 'Failed to calculate matches: ' . $e->getMessage(),
        'debug' => [
            'job_id' => $job_id,
            'user_id' => $user_id,
            'error' => $e->getMessage()
        ]
    ]);
}

$conn->close();
?>
