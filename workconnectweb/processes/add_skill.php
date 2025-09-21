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
$service_id = intval($_POST['service_id'] ?? 0);
$skill_level = in_array($_POST['skill_level'] ?? '', ['beginner', 'intermediate', 'advanced', 'expert']) 
              ? $_POST['skill_level'] : 'intermediate';

if ($service_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Please select a valid service']);
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
    
    // Verify service exists
    $stmt = $conn->prepare("SELECT service_name FROM services WHERE id = ? AND is_active = 1");
    $stmt->bind_param("i", $service_id);
    $stmt->execute();
    $service_result = $stmt->get_result();
    
    if ($service_result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid service selection']);
        exit;
    }
    
    $service_name = $service_result->fetch_assoc()['service_name'];
    
    // Check if skill already exists
    $stmt = $conn->prepare("SELECT id FROM worker_skills WHERE worker_id = ? AND service_id = ?");
    $stmt->bind_param("ii", $worker_id, $service_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'You already have this skill listed']);
        exit;
    }
    
    // Add the skill
    $stmt = $conn->prepare("
        INSERT INTO worker_skills (worker_id, service_id, skill_level, created_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->bind_param("iis", $worker_id, $service_id, $skill_level);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => "Added '{$service_name}' with {$skill_level} level successfully!"
        ]);
    } else {
        throw new Exception('Failed to add skill');
    }

} catch (Exception $e) {
    error_log("Add skill error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to add skill. Please try again.']);
}

$conn->close();
?>
