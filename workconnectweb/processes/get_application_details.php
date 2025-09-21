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

$user_id = $_SESSION['user_id'];
$application_id = intval($_POST['application_id'] ?? 0);

if ($application_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid application ID']);
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
    
    // Get detailed application information
    $stmt = $conn->prepare("
        SELECT 
            ja.*,
            jp.title as job_title,
            jp.description as job_description,
            jp.location_address,
            jp.budget_min,
            jp.budget_max,
            jp.budget_type,
            jp.urgency,
            jp.status as job_status,
            u.name as client_name,
            u.id as client_user_id,
            u.address as client_address,
            sc.name as category_name,
            s.service_name
        FROM job_applications ja
        INNER JOIN job_postings jp ON ja.job_id = jp.id
        INNER JOIN services s ON jp.service_id = s.id
        INNER JOIN service_categories sc ON s.category_id = sc.id
        INNER JOIN users u ON jp.client_id = u.id
        WHERE ja.id = ? AND ja.worker_id = ?
    ");
    $stmt->bind_param("ii", $application_id, $worker_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Application not found or access denied']);
        exit;
    }
    
    $app = $result->fetch_assoc();
    
    // Generate status badge
    $status_colors = [
        'pending' => 'warning',
        'accepted' => 'success',
        'rejected' => 'danger'
    ];
    $status_color = $status_colors[$app['status']] ?? 'secondary';
    
    // Generate urgency badge
    $urgency_colors = [
        'urgent' => 'danger',
        'high' => 'warning',
        'medium' => 'primary',
        'low' => 'success'
    ];
    $urgency_color = $urgency_colors[$app['urgency']] ?? 'secondary';
    
    // Format budget
    $budget_text = 'Negotiable';
    if ($app['budget_min'] && $app['budget_max']) {
        $budget_text = Functions::formatMoney($app['budget_min']) . ' - ' . Functions::formatMoney($app['budget_max']);
    } elseif ($app['budget_min']) {
        $budget_text = 'From ' . Functions::formatMoney($app['budget_min']);
    } elseif ($app['budget_max']) {
        $budget_text = 'Up to ' . Functions::formatMoney($app['budget_max']);
    }
    
    // Generate HTML
    $application_html = '
        <div class="row">
            <div class="col-md-8">
                <h5 class="mb-3">' . htmlspecialchars($app['job_title']) . '</h5>
                
                <div class="mb-3">
                    <h6>Job Description:</h6>
                    <p class="text-muted">' . nl2br(htmlspecialchars($app['job_description'])) . '</p>
                </div>
                
                <div class="mb-3">
                    <h6>Your Application:</h6>
                    <div class="bg-light p-3 rounded">
                        <div class="row mb-2">
                            <div class="col-sm-4"><strong>Proposed Rate:</strong></div>
                            <div class="col-sm-8">' . Functions::formatMoney($app['proposed_rate']) . '</div>
                        </div>
                        ' . ($app['proposed_timeline'] ? '
                        <div class="row mb-2">
                            <div class="col-sm-4"><strong>Timeline:</strong></div>
                            <div class="col-sm-8">' . htmlspecialchars($app['proposed_timeline']) . '</div>
                        </div>' : '') . '
                        <div class="row mb-2">
                            <div class="col-sm-4"><strong>Applied:</strong></div>
                            <div class="col-sm-8">' . Functions::timeAgo($app['applied_at']) . '</div>
                        </div>
                        <div class="row">
                            <div class="col-sm-4"><strong>Status:</strong></div>
                            <div class="col-sm-8">
                                <span class="badge bg-' . $status_color . '">' . ucfirst($app['status']) . '</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <h6>Cover Message:</h6>
                    <div class="border p-3 rounded">
                        ' . nl2br(htmlspecialchars($app['cover_message'])) . '
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Job Details</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <strong>Client:</strong><br>
                            ' . htmlspecialchars($app['client_name']) . '
                        </div>
                        <div class="mb-2">
                            <strong>Location:</strong><br>
                            ' . htmlspecialchars($app['location_address']) . '
                        </div>
                        <div class="mb-2">
                            <strong>Category:</strong><br>
                            ' . htmlspecialchars($app['category_name']) . '
                        </div>
                        <div class="mb-2">
                            <strong>Service:</strong><br>
                            ' . htmlspecialchars($app['service_name']) . '
                        </div>
                        <div class="mb-2">
                            <strong>Budget:</strong><br>
                            ' . $budget_text . '
                        </div>
                        <div class="mb-2">
                            <strong>Urgency:</strong><br>
                            <span class="badge bg-' . $urgency_color . '">' . ucfirst($app['urgency']) . '</span>
                        </div>
                        <div class="mb-0">
                            <strong>Job Status:</strong><br>
                            <span class="badge bg-info">' . ucfirst($app['job_status']) . '</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    ';
    
    echo json_encode([
        'status' => 'success',
        'application_html' => $application_html,
        'client_user_id' => $app['client_user_id'],
        'application_data' => $app
    ]);

} catch (Exception $e) {
    error_log("Get application details error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to load application details']);
}

$conn->close();
?>
