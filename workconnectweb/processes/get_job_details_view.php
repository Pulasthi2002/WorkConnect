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
$job_id = intval($_POST['job_id'] ?? 0);

if ($job_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid job ID']);
    exit;
}

try {
    // Get worker profile
    $worker_profile = null;
    $stmt = $conn->prepare("SELECT id FROM worker_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $worker_profile = $result->fetch_assoc();
    }

    // Get detailed job information
    $stmt = $conn->prepare("
        SELECT 
            jp.*,
            sc.name as category_name,
            s.service_name,
            u.name as client_name,
            u.address as client_address,
            u.created_at as client_joined,
            (SELECT COUNT(*) FROM job_applications WHERE job_id = jp.id) as application_count,
            (SELECT COUNT(*) FROM job_applications WHERE job_id = jp.id AND worker_id = ?) as already_applied,
            (SELECT AVG(r.rating) FROM reviews r 
             INNER JOIN job_postings j ON r.job_id = j.id 
             WHERE j.client_id = jp.client_id AND r.reviewer_type = 'worker') as client_rating,
            (SELECT COUNT(*) FROM job_postings WHERE client_id = jp.client_id AND status = 'completed') as client_jobs_completed
        FROM job_postings jp
        INNER JOIN services s ON jp.service_id = s.id
        INNER JOIN service_categories sc ON s.category_id = sc.id
        INNER JOIN users u ON jp.client_id = u.id
        WHERE jp.id = ? AND jp.status = 'open'
    ");
    
    $worker_id = $worker_profile ? $worker_profile['id'] : 0;
    $stmt->bind_param("ii", $worker_id, $job_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Job not found or no longer available']);
        exit;
    }
    
    $job = $result->fetch_assoc();
    
    // Generate urgency badge
    $urgency_colors = [
        'urgent' => 'danger',
        'high' => 'warning',
        'medium' => 'primary',
        'low' => 'success'
    ];
    $urgency_color = $urgency_colors[$job['urgency']] ?? 'secondary';
    
    // Format budget
    $budget_text = 'Negotiable';
    if ($job['budget_min'] && $job['budget_max']) {
        $budget_text = Functions::formatMoney($job['budget_min']) . ' - ' . Functions::formatMoney($job['budget_max']);
    } elseif ($job['budget_min']) {
        $budget_text = 'From ' . Functions::formatMoney($job['budget_min']);
    } elseif ($job['budget_max']) {
        $budget_text = 'Up to ' . Functions::formatMoney($job['budget_max']);
    }
    
    // Generate rating stars for client
    $client_rating = floatval($job['client_rating'] ?? 0);
    $stars_html = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $client_rating) {
            $stars_html .= '<i class="fas fa-star text-warning"></i>';
        } elseif ($i - 0.5 <= $client_rating) {
            $stars_html .= '<i class="fas fa-star-half-alt text-warning"></i>';
        } else {
            $stars_html .= '<i class="far fa-star text-warning"></i>';
        }
    }
    
    // Generate job details HTML
    $job_html = '
        <div class="row">
            <div class="col-md-8">
                <div class="mb-4">
                    <h4>' . htmlspecialchars($job['title']) . '</h4>
                    <div class="mb-3">
                        <span class="badge bg-' . $urgency_color . ' me-2">' . ucfirst($job['urgency']) . ' Priority</span>
                        <span class="badge bg-light text-dark me-2">
                            <i class="fas fa-tag me-1"></i>' . htmlspecialchars($job['category_name']) . '
                        </span>
                        <span class="badge bg-light text-dark">
                            <i class="fas fa-tools me-1"></i>' . htmlspecialchars($job['service_name']) . '
                        </span>
                    </div>
                </div>
                
                <div class="mb-4">
                    <h6><i class="fas fa-file-text me-2"></i>Job Description</h6>
                    <div class="bg-light p-3 rounded">
                        ' . nl2br(htmlspecialchars($job['description'])) . '
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6><i class="fas fa-map-marker-alt me-2 text-primary"></i>Location</h6>
                        <p class="mb-0">' . htmlspecialchars($job['client_address']) . '</p>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-money-bill-wave me-2 text-success"></i>Budget</h6>
                        <p class="mb-0 text-success fw-bold">' . $budget_text . '</p>
                        <small class="text-muted">Budget Type: ' . ucfirst($job['budget_type']) . '</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-user me-2"></i>Client Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2" 
                                 style="width: 60px; height: 60px; font-size: 1.5rem; font-weight: bold;">
                                ' . strtoupper(substr($job['client_name'], 0, 2)) . '
                            </div>
                            <h6 class="mb-1">' . htmlspecialchars($job['client_name']) . '</h6>
                            ' . ($client_rating > 0 ? '<div class="mb-2">' . $stars_html . ' <span class="text-muted">(' . number_format($client_rating, 1) . ')</span></div>' : '<div class="text-muted mb-2">No ratings yet</div>') . '
                        </div>
                        
                        <div class="small">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Jobs Completed:</span>
                                <strong>' . intval($job['client_jobs_completed']) . '</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Member Since:</span>
                                <strong>' . date('M Y', strtotime($job['client_joined'])) . '</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Applications:</span>
                                <strong>' . intval($job['application_count']) . ' received</strong>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Job Info</h6>
                    </div>
                    <div class="card-body">
                        <div class="small">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Posted:</span>
                                <strong>' . Functions::timeAgo($job['created_at']) . '</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Status:</span>
                                <span class="badge bg-success">Open</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Job ID:</span>
                                <strong>#' . $job['id'] . '</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    ';
    
    echo json_encode([
        'status' => 'success',
        'job_html' => $job_html,
        'job_data' => [
            'id' => $job['id'],
            'title' => $job['title'],
            'already_applied' => $job['already_applied'] > 0,
            'client_name' => $job['client_name'],
            'budget' => $budget_text,
            'urgency' => $job['urgency']
        ]
    ]);

} catch (Exception $e) {
    error_log("Get job details view error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to load job details']);
}

$conn->close();
?>
