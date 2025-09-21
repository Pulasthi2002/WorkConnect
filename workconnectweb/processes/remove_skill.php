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
$skill_id = intval($_POST['skill_id'] ?? 0);

if ($skill_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid skill ID']);
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
    
    // Verify skill ownership and get skill name
    $stmt = $conn->prepare("
        SELECT ws.id, s.service_name
        FROM worker_skills ws
        INNER JOIN services s ON ws.service_id = s.id
        WHERE ws.id = ? AND ws.worker_id = ?
    ");
    $stmt->bind_param("ii", $skill_id, $worker_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Skill not found or access denied']);
        exit;
    }
    
    $skill = $result->fetch_assoc();
    
    // Remove the skill
    $stmt = $conn->prepare("DELETE FROM worker_skills WHERE id = ? AND worker_id = ?");
    $stmt->bind_param("ii", $skill_id, $worker_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => "Removed '{$skill['service_name']}' from your skills successfully!"
        ]);
    } else {
        throw new Exception('Failed to remove skill');
    }

} catch (Exception $e) {
    error_log("Remove skill error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to remove skill. Please try again.']);
}

$conn->close();
?>
