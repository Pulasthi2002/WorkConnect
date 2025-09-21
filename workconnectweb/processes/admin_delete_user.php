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

$user_id = intval($_POST['user_id'] ?? 0);

if ($user_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user ID']);
    exit;
}

try {
    // Check if user exists and is not admin
    $stmt = $conn->prepare("SELECT id, name, role FROM users WHERE id = ? AND role != 'admin'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'User not found or cannot be deleted']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    
    // Check for dependencies
    $can_delete = true;
    $dependency_message = '';
    
    if ($user['role'] === 'customer') {
        // Check for active jobs
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_postings WHERE client_id = ? AND status IN ('open', 'assigned')");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $active_jobs = $stmt->get_result()->fetch_assoc()['count'];
        
        if ($active_jobs > 0) {
            $can_delete = false;
            $dependency_message = "Cannot delete user with {$active_jobs} active job(s). Complete or cancel jobs first.";
        }
    } elseif ($user['role'] === 'worker') {
        // Check for active assignments
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM job_postings jp 
            INNER JOIN worker_profiles wp ON jp.assigned_worker_id = wp.id 
            WHERE wp.user_id = ? AND jp.status IN ('assigned')
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $active_assignments = $stmt->get_result()->fetch_assoc()['count'];
        
        if ($active_assignments > 0) {
            $can_delete = false;
            $dependency_message = "Cannot delete worker with {$active_assignments} active assignment(s).";
        }
    }
    
    if (!$can_delete) {
        echo json_encode(['status' => 'error', 'message' => $dependency_message]);
        exit;
    }
    
    // Start transaction for cascade deletion
    $conn->begin_transaction();
    
    if ($user['role'] === 'worker') {
        // Get worker profile ID
        $stmt = $conn->prepare("SELECT id FROM worker_profiles WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $worker_profile = $stmt->get_result()->fetch_assoc();
        
        if ($worker_profile) {
            $worker_id = $worker_profile['id'];
            
            // Delete worker-related data
            $stmt = $conn->prepare("DELETE FROM worker_skills WHERE worker_id = ?");
            $stmt->bind_param("i", $worker_id);
            $stmt->execute();
            
            $stmt = $conn->prepare("DELETE FROM job_applications WHERE worker_id = ?");
            $stmt->bind_param("i", $worker_id);
            $stmt->execute();
            
            $stmt = $conn->prepare("DELETE FROM worker_profiles WHERE id = ?");
            $stmt->bind_param("i", $worker_id);
            $stmt->execute();
        }
    } elseif ($user['role'] === 'customer') {
        // Delete customer-related data (only completed/cancelled jobs)
        $stmt = $conn->prepare("DELETE FROM job_postings WHERE client_id = ? AND status IN ('completed', 'cancelled')");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    }
    
    // Delete messages
    $stmt = $conn->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?");
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    
    // Delete reviews
    $stmt = $conn->prepare("DELETE FROM reviews WHERE reviewer_id = ? OR reviewee_id = ?");
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    
    // Delete salary predictions
    $stmt = $conn->prepare("DELETE FROM salary_predictions WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Finally delete the user
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        $conn->commit();
        echo json_encode([
            'status' => 'success',
            'message' => 'User "' . $user['name'] . '" deleted successfully!'
        ]);
    } else {
        throw new Exception('Failed to delete user');
    }

} catch (Exception $e) {
    $conn->rollback();
    error_log("Admin delete user error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete user. Please try again.']);
}

$conn->close();
?>
