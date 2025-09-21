<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

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
            u.email as client_email,
            u.telephone as client_phone,
            u.address as client_address,
            (SELECT COUNT(*) FROM job_applications WHERE job_id = jp.id) as application_count,
            (SELECT COUNT(*) FROM job_applications WHERE job_id = jp.id AND status = 'pending') as pending_applications,
            (SELECT COUNT(*) FROM job_applications WHERE job_id = jp.id AND status = 'accepted') as accepted_applications,
            CASE 
                WHEN jp.assigned_worker_id IS NOT NULL THEN 
                    (SELECT u2.name FROM worker_profiles wp2 
                     INNER JOIN users u2 ON wp2.user_id = u2.id 
                     WHERE wp2.id = jp.assigned_worker_id)
                ELSE NULL 
            END as assigned_worker_name,
            CASE 
                WHEN jp.assigned_worker_id IS NOT NULL THEN 
                    (SELECT u2.email FROM worker_profiles wp2 
                     INNER JOIN users u2 ON wp2.user_id = u2.id 
                     WHERE wp2.id = jp.assigned_worker_id)
                ELSE NULL 
            END as assigned_worker_email
        FROM job_postings jp
        INNER JOIN services s ON jp.service_id = s.id
        INNER JOIN service_categories sc ON s.category_id = sc.id
        INNER JOIN users u ON jp.client_id = u.id
        WHERE jp.id = ?
    ");
    
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Job not found']);
        exit;
    }
    
    $job = $result->fetch_assoc();
    
    // Get recent applications
    $stmt = $conn->prepare("
        SELECT 
            ja.*,
            u.name as worker_name,
            u.email as worker_email,
            wp.experience_years,
            wp.average_rating
        FROM job_applications ja
        INNER JOIN worker_profiles wp ON ja.worker_id = wp.id
        INNER JOIN users u ON wp.user_id = u.id
        WHERE ja.job_id = ?
        ORDER BY ja.applied_at DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $applications = $stmt->get_result();
    
    // Status colors
    $status_colors = [
        'open' => 'success',
        'assigned' => 'warning',
        'completed' => 'info',
        'cancelled' => 'danger',
        'paused' => 'secondary'
    ];
    $status_color = $status_colors[$job['status']] ?? 'secondary';
    
    // Urgency colors
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
    
    // Generate job details HTML
    $job_html = '
        <div class="row">
            <div class="col-md-8">
                <div class="mb-4">
                    <h4>' . htmlspecialchars($job['title']) . '</h4>
                    <div class="mb-3">
                        <span class="badge bg-' . $status_color . ' me-2">' . ucfirst($job['status']) . '</span>
                        <span class="badge bg-' . $urgency_color . ' me-2">' . ucfirst($job['urgency']) . ' Priority</span>
                        <span class="badge bg-light text-dark">' . htmlspecialchars($job['category_name']) . '</span>
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
                        <p class="mb-0">' . htmlspecialchars($job['location_address']) . '</p>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-money-bill-wave me-2 text-success"></i>Budget</h6>
                        <p class="mb-0 text-success fw-bold">' . $budget_text . '</p>
                        <small class="text-muted">Budget Type: ' . ucfirst($job['budget_type']) . '</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-user me-2"></i>Client Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <strong>Name:</strong> ' . htmlspecialchars($job['client_name']) . '
                        </div>
                        <div class="mb-2">
                            <strong>Email:</strong> ' . htmlspecialchars($job['client_email']) . '
                        </div>
                        <div class="mb-2">
                            <strong>Phone:</strong> ' . htmlspecialchars($job['client_phone']) . '
                        </div>
                        <div class="mb-0">
                            <strong>Address:</strong> ' . htmlspecialchars($job['client_address']) . '
                        </div>
                    </div>
                </div>
                
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Job Details</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <strong>Job ID:</strong> #' . $job['id'] . '
                        </div>
                        <div class="mb-2">
                            <strong>Service:</strong> ' . htmlspecialchars($job['service_name']) . '
                        </div>
                        <div class="mb-2">
                            <strong>Posted:</strong> ' . Functions::timeAgo($job['created_at']) . '
                        </div>
                        <div class="mb-0">
                            <strong>Last Updated:</strong> ' . Functions::timeAgo($job['updated_at']) . '
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Application Stats</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <strong>Total Applications:</strong> ' . $job['application_count'] . '
                        </div>
                        <div class="mb-2">
                            <strong>Pending:</strong> ' . $job['pending_applications'] . '
                        </div>
                        <div class="mb-2">
                            <strong>Accepted:</strong> ' . $job['accepted_applications'] . '
                        </div>
                        ' . ($job['assigned_worker_name'] ? '
                        <div class="mb-0">
                            <strong>Assigned Worker:</strong><br>
                            ' . htmlspecialchars($job['assigned_worker_name']) . '<br>
                            <small class="text-muted">' . htmlspecialchars($job['assigned_worker_email']) . '</small>
                        </div>' : '') . '
                    </div>
                </div>
            </div>
        </div>
    ';
    
    // Add recent applications if any
    if ($applications->num_rows > 0) {
        $job_html .= '
            <div class="mt-4">
                <h6><i class="fas fa-users me-2"></i>Recent Applications</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Worker</th>
                                <th>Proposed Rate</th>
                                <th>Timeline</th>
                                <th>Status</th>
                                <th>Applied</th>
                            </tr>
                        </thead>
                        <tbody>
        ';
        
        while ($app = $applications->fetch_assoc()) {
            $app_status_colors = [
                'pending' => 'warning',
                'accepted' => 'success',
                'rejected' => 'danger'
            ];
            $app_status_color = $app_status_colors[$app['status']] ?? 'secondary';
            
            $job_html .= '
                <tr>
                    <td>
                        ' . htmlspecialchars($app['worker_name']) . '<br>
                        <small class="text-muted">' . $app['experience_years'] . ' years exp | ' . number_format($app['average_rating'], 1) . '★</small>
                    </td>
                    <td>' . Functions::formatMoney($app['proposed_rate']) . '</td>
                    <td>' . htmlspecialchars($app['proposed_timeline'] ?: 'Not specified') . '</td>
                    <td><span class="badge bg-' . $app_status_color . '">' . ucfirst($app['status']) . '</span></td>
                    <td>' . Functions::timeAgo($app['applied_at']) . '</td>
                </tr>
            ';
        }
        
        $job_html .= '
                        </tbody>
                    </table>
                </div>
            </div>
        ';
    }
    
    echo json_encode([
        'status' => 'success',
        'job_html' => $job_html,
        'job_data' => $job
    ]);

} catch (Exception $e) {
    error_log("Admin view job error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to load job details']);
}

$conn->close();
?>
