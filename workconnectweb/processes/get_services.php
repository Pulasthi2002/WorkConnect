<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';

header('Content-Type: application/json');

SessionManager::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid security token']);
    exit;
}

$category_id = intval($_POST['category_id'] ?? 0);

if ($category_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid category ID']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT id, service_name 
        FROM services 
        WHERE category_id = ? AND is_active = 1 
        ORDER BY service_name
    ");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $options = '<option value="">Select specific service</option>';
    
    while ($service = $result->fetch_assoc()) {
        $options .= sprintf(
            '<option value="%d">%s</option>',
            $service['id'],
            htmlspecialchars($service['service_name'])
        );
    }
    
    echo json_encode([
        'status' => 'success',
        'options' => $options,
        'count' => $result->num_rows
    ]);

} catch (Exception $e) {
    error_log("Get services error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to load services']);
}

$conn->close();
?>
