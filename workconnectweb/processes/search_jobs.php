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

// Get worker profile
$worker_profile = null;
try {
    $stmt = $conn->prepare("SELECT id FROM worker_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $worker_profile = $result->fetch_assoc();
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Worker profile not found']);
    exit;
}

$worker_id = $worker_profile['id'];

// Parse search parameters
$keywords = Security::sanitizeInput($_POST['keywords'] ?? '');
$category_id = intval($_POST['category_id'] ?? 0);
$location = Security::sanitizeInput($_POST['location'] ?? '');
$urgency = Security::sanitizeInput($_POST['urgency'] ?? '');
$matching_skills = intval($_POST['matching_skills'] ?? 0);
$sort_by = in_array($_POST['sort_by'] ?? '', ['newest', 'budget_high', 'budget_low', 'urgency']) ? $_POST['sort_by'] : 'newest';

try {
    // Build query
    $base_query = "
        SELECT 
            jp.*,
            sc.name as category_name,
            s.service_name,
            u.name as client_name,
            (SELECT COUNT(*) FROM job_applications WHERE job_id = jp.id) as application_count,
            (SELECT COUNT(*) FROM job_applications WHERE job_id = jp.id AND worker_id = ?) as already_applied
        FROM job_postings jp
        INNER JOIN services s ON jp.service_id = s.id
        INNER JOIN service_categories sc ON s.category_id = sc.id
        INNER JOIN users u ON jp.client_id = u.id
    ";
    
    $params = [$worker_id];
    $param_types = "i";
    $conditions = ["jp.status = 'open'"];
    
    // Apply filters
    if (!empty($keywords)) {
        $conditions[] = "(jp.title LIKE ? OR jp.description LIKE ?)";
        $keyword_param = '%' . $keywords . '%';
        $params[] = $keyword_param;
        $params[] = $keyword_param;
        $param_types .= "ss";
    }
    
    if ($category_id > 0) {
        $conditions[] = "sc.id = ?";
        $params[] = $category_id;
        $param_types .= "i";
    }
    
    if (!empty($location)) {
        $conditions[] = "jp.location_address LIKE ?";
        $params[] = '%' . $location . '%';
        $param_types .= "s";
    }
    
    if (!empty($urgency)) {
        $conditions[] = "jp.urgency = ?";
        $params[] = $urgency;
        $param_types .= "s";
    }
    
    if ($matching_skills) {
        $base_query .= " INNER JOIN worker_skills ws ON s.id = ws.service_id AND ws.worker_id = ?";
        $params[] = $worker_id;
        $param_types .= "i";
    }
    
    // Add WHERE clause
    $base_query .= " WHERE " . implode(' AND ', $conditions);
    
    // Add ORDER BY
    switch ($sort_by) {
        case 'budget_high':
            $base_query .= " ORDER BY COALESCE(jp.budget_max, jp.budget_min, 0) DESC";
            break;
        case 'budget_low':
            $base_query .= " ORDER BY COALESCE(jp.budget_min, jp.budget_max, 999999) ASC";
            break;
        case 'urgency':
            $base_query .= " ORDER BY CASE jp.urgency WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 END";
            break;
        case 'newest':
        default:
            $base_query .= " ORDER BY jp.created_at DESC";
            break;
    }
    
    $base_query .= " LIMIT 50";
    
    // Execute query
    $stmt = $conn->prepare($base_query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Generate HTML
    $jobs_html = '';
    $total_jobs = 0;
    
    if ($result->num_rows > 0) {
        while ($job = $result->fetch_assoc()) {
            $total_jobs++;
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
            } elseif ($job['budget_max']) {
                $budget_text = 'Up to ' . Functions::formatMoney($job['budget_max']);
            }
            
            $jobs_html .= '
            <div class="card job-card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="card-title mb-0">' . htmlspecialchars($job['title']) . '</h5>
                        <span class="badge bg-' . $urgency_color . '">' . ucfirst($job['urgency']) . '</span>
                    </div>
                    
                    <p class="card-text text-muted">
                        ' . Functions::truncateText($job['description'], 150) . '
                    </p>
                    
                    <div class="mb-3">
                        <span class="badge bg-light text-dark me-2">
                            <i class="fas fa-tag me-1"></i>' . htmlspecialchars($job['category_name']) . '
                        </span>
                        <span class="badge bg-light text-dark me-2">
                            <i class="fas fa-map-marker-alt me-1"></i>' . Functions::truncateText($job['location_address'], 30) . '
                        </span>
                        <span class="badge bg-success">
                            <i class="fas fa-money-bill-wave me-1"></i>' . $budget_text . '
                        </span>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted small">
                            <i class="fas fa-user me-1"></i>by ' . htmlspecialchars($job['client_name']) . ' â€¢ 
                            <i class="fas fa-clock me-1"></i>' . Functions::timeAgo($job['created_at']) . ' â€¢ 
                            <i class="fas fa-file-alt me-1"></i>' . $job['application_count'] . ' applications
                        </div>
                        
                        <div>';
            
            if ($job['already_applied']) {
                $jobs_html .= '<span class="badge bg-info me-2">Applied</span>';
            } else {
                $jobs_html .= '<button class="btn btn-success btn-sm me-2" onclick="applyForJob(' . $job['id'] . ')">
                                <i class="fas fa-paper-plane me-1"></i>Apply
                              </button>';
            }
            
            $jobs_html .= '<button class="btn btn-outline-primary btn-sm" onclick="viewJobDetails(' . $job['id'] . ')">
                            <i class="fas fa-eye me-1"></i>Details
                          </button>
                        </div>
                    </div>
                </div>
            </div>';
        }
    } else {
        $jobs_html = '
        <div class="text-center py-5">
            <i class="fas fa-search fa-4x text-muted mb-3"></i>
            <h5 class="text-muted">No jobs found</h5>
            <p class="text-muted">Try adjusting your search criteria to find more opportunities.</p>
        </div>';
    }
    
    echo json_encode([
        'status' => 'success',
        'jobs_html' => $jobs_html,
        'total' => $total_jobs
    ]);

} catch (Exception $e) {
    error_log("Search jobs error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error searching jobs']);
}

$conn->close();
?>
