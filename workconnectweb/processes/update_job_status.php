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
$job_id = intval($_POST['job_id'] ?? 0);
$action = Security::sanitizeInput($_POST['action'] ?? '');

// Map actions to statuses
$action_status_map = [
    'complete' => 'completed',
    'cancel' => 'cancelled',
    'pause' => 'paused',
    'reopen' => 'open'
];

if (!isset($action_status_map[$action])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    exit;
}

$new_status = $action_status_map[$action];

if ($job_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid job ID']);
    exit;
}

try {
    // Verify job ownership and get current status with worker details
    $stmt = $conn->prepare("
        SELECT 
            jp.id, jp.title, jp.status, jp.assigned_worker_id,
            u.name as worker_name, u.id as worker_user_id
        FROM job_postings jp
        LEFT JOIN worker_profiles wp ON jp.assigned_worker_id = wp.id
        LEFT JOIN users u ON wp.user_id = u.id
        WHERE jp.id = ? AND jp.client_id = ?
    ");
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        throw new Exception('Database prepare error');
    }
    
    $stmt->bind_param("ii", $job_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Job not found or access denied']);
        exit;
    }
    
    $job = $result->fetch_assoc();
    
    // Validate status transitions
    $valid_transitions = [
        'open' => ['cancelled', 'paused'],
        'assigned' => ['completed', 'cancelled'],
        'paused' => ['open', 'cancelled'],
        'completed' => [], // Cannot change completed jobs
        'cancelled' => ['open'] // Can reopen cancelled jobs
    ];
    
    if (!in_array($new_status, $valid_transitions[$job['status']] ?? [])) {
        $current_status = ucfirst($job['status']);
        $new_status_text = ucfirst($new_status);
        echo json_encode([
            'status' => 'error', 
            'message' => "Cannot change job from {$current_status} to {$new_status_text}"
        ]);
        exit;
    }
    
    // Special validation for completing jobs
    if ($new_status === 'completed' && empty($job['assigned_worker_id'])) {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Job must be assigned to a worker before it can be completed'
        ]);
        exit;
    }
    
    // Start transaction for job completion with review prompts
    $conn->begin_transaction();
    
    // Update job status
    $update_stmt = $conn->prepare("
        UPDATE job_postings 
        SET status = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    
    if (!$update_stmt) {
        error_log("Update prepare failed: " . $conn->error);
        throw new Exception('Database update prepare error');
    }
    
    $update_stmt->bind_param("si", $new_status, $job_id);
    
    if (!$update_stmt->execute()) {
        error_log("Update execute failed: " . $update_stmt->error);
        throw new Exception('Failed to execute update query');
    }
    
    // Handle job completion specific actions
    if ($new_status === 'completed') {
        // Update worker's total jobs count
        if ($job['assigned_worker_id']) {
            $worker_update_stmt = $conn->prepare("
                UPDATE worker_profiles 
                SET total_jobs = total_jobs + 1 
                WHERE id = ?
            ");
            $worker_update_stmt->bind_param("i", $job['assigned_worker_id']);
            $worker_update_stmt->execute();
            $worker_update_stmt->close();
        }
        
        // Create review reminder notifications (optional - can be implemented later)
        if ($job['worker_user_id']) {
            try {
                // Insert notification for client to review worker
                $notification_stmt = $conn->prepare("
                    INSERT INTO notifications (user_id, type, title, message, created_at, is_read) 
                    VALUES (?, 'review_reminder', 'Rate Your Worker', ?, NOW(), 0)
                ");
                if ($notification_stmt) {
                    $notification_message = "Please rate your experience with {$job['worker_name']} for the job '{$job['title']}'.";
                    $notification_stmt->bind_param("is", $user_id, $notification_message);
                    $notification_stmt->execute();
                    $notification_stmt->close();
                }
                
                // Insert notification for worker to request review from client
                $worker_notification_stmt = $conn->prepare("
                    INSERT INTO notifications (user_id, type, title, message, created_at, is_read) 
                    VALUES (?, 'review_request', 'Job Completed', ?, NOW(), 0)
                ");
                if ($worker_notification_stmt) {
                    $worker_notification_message = "Job '{$job['title']}' has been marked as completed. You can now request a review from the client.";
                    $worker_notification_stmt->bind_param("is", $job['worker_user_id'], $worker_notification_message);
                    $worker_notification_stmt->execute();
                    $worker_notification_stmt->close();
                }
            } catch (Exception $notification_error) {
                // Log notification errors but don't fail the main transaction
                error_log("Notification creation failed: " . $notification_error->getMessage());
            }
        }
        
        $conn->commit();
        
        // Enhanced response for completed jobs with review prompt
        echo json_encode([
            'status' => 'success',
            'message' => 'Job marked as completed successfully! Don\'t forget to rate your worker.',
            'new_status' => $new_status,
            'show_review_prompt' => true,
            'job_id' => $job_id,
            'worker_id' => $job['assigned_worker_id'],
            'worker_user_id' => $job['worker_user_id'],
            'worker_name' => $job['worker_name'],
            'job_title' => $job['title'],
            'review_data' => [
                'reviewee_id' => $job['worker_user_id'],
                'reviewee_name' => $job['worker_name'],
                'job_title' => $job['title'],
                'reviewer_type' => 'customer'
            ]
        ]);
        
    } else {
        // For non-completion status changes
        $conn->commit();
        
        // Generate standard success messages
        $messages = [
            'cancelled' => 'Job cancelled successfully!',
            'paused' => 'Job paused successfully!',
            'open' => 'Job reopened successfully!'
        ];
        
        $message = $messages[$new_status] ?? 'Job status updated successfully!';
        
        echo json_encode([
            'status' => 'success',
            'message' => $message,
            'new_status' => $new_status,
            'show_review_prompt' => false
        ]);
    }
    
    $update_stmt->close();

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Update job status error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error', 
        'message' => 'Failed to update job status. Please try again.'
    ]);
}

$conn->close();
?>
