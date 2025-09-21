<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

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

$job_id = intval($_POST['job_id'] ?? 0);

if ($job_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid job ID']);
    exit;
}

try {
    // Get detailed job information
    $stmt = $conn->prepare("
        SELECT 
            jp.*,
            sc.name as category_name,
            s.service_name,
            u.name as client_name,
            u.address as client_address,
            (SELECT COUNT(*) FROM job_applications WHERE job_id = jp.id) as application_count,
            (SELECT AVG(r.rating) FROM reviews r 
             INNER JOIN job_postings j ON r.job_id = j.id 
             WHERE j.client_id = jp.client_id AND r.reviewer_type = 'worker') as client_rating
        FROM job_postings jp
        INNER JOIN services s ON jp.service_id = s.id
        INNER JOIN service_categories sc ON s.category_id = sc.id
        INNER JOIN users u ON jp.client_id = u.id
        WHERE jp.id = ? AND jp.status = 'open'
    ");
    
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Job not found or no longer available']);
        exit;
    }
    
    $job = $result->fetch_assoc();
    
    // Generate job details HTML
    $urgency_colors = [
        'urgent' => 'danger',
        'high' => 'warning',
        'medium' => 'primary',
        'low' => 'success'
    ];
    $urgency_color = $urgency_colors[$job['urgency']] ?? 'secondary';
    
    $budget_text = 'Negotiable';
    if ($job['budget_min'] && $job['budget_max']) {
        $budget_text = Functions::formatMoney($job['budget_min']) . ' - ' . Functions::formatMoney($job['budget_max']);
    } elseif ($job['budget_min']) {
        $budget_text = 'From ' . Functions::formatMoney($job['budget_min']);
    }
    
    $job_html = '
        <div class="card border-' . $urgency_color . '">
            <div class="card-header bg-' . $urgency_color . ' text-white">
                <h6 class="mb-0">' . htmlspecialchars($job['title']) . '</h6>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Client:</strong> ' . htmlspecialchars($job['client_name']) . '<br>
                        <strong>Location:</strong> ' . htmlspecialchars($job['client_address']) . '<br>
                        <strong>Category:</strong> ' . htmlspecialchars($job['category_name']) . '
                    </div>
                    <div class="col-md-6">
                        <strong>Budget:</strong> ' . $budget_text . '<br>
                        <strong>Urgency:</strong> <span class="badge bg-' . $urgency_color . '">' . ucfirst($job['urgency']) . '</span><br>
                        <strong>Applications:</strong> ' . $job['application_count'] . ' received
                    </div>
                </div>
                <div class="mb-2">
                    <strong>Description:</strong>
                    <p class="mt-2">' . nl2br(htmlspecialchars($job['description'])) . '</p>
                </div>
                ' . ($job['client_rating'] ? '<div><strong>Client Rating:</strong> ' . number_format($job['client_rating'], 1) . ' ★</div>' : '') . '
            </div>
        </div>
    ';
    
    echo json_encode([
        'status' => 'success',
        'job_html' => $job_html,
        'job_data' => $job
    ]);

} catch (Exception $e) {
    error_log("Get job details error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to load job details']);
}

$conn->close();
?>
