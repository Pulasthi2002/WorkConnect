<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

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

// Parse search parameters
$name = Security::sanitizeInput($_POST['name'] ?? '');
$category_id = intval($_POST['category_id'] ?? 0);
$location = Security::sanitizeInput($_POST['location'] ?? '');
$min_rating = floatval($_POST['min_rating'] ?? 0);
$available_only = intval($_POST['available_only'] ?? 0);
$sort_by = in_array($_POST['sort_by'] ?? '', ['rating', 'newest', 'experience', 'jobs_completed']) ? $_POST['sort_by'] : 'rating';

try {
    // Build query
    $base_query = "
        SELECT 
            u.id, u.name, u.address, u.profile_image, u.created_at,
            wp.bio, wp.experience_years, wp.hourly_rate_min, wp.hourly_rate_max,
            wp.is_available, wp.average_rating, wp.total_jobs,
            GROUP_CONCAT(DISTINCT s.service_name SEPARATOR ', ') as skills
        FROM users u
        INNER JOIN worker_profiles wp ON u.id = wp.user_id
        LEFT JOIN worker_skills ws ON wp.id = ws.worker_id
        LEFT JOIN services s ON ws.service_id = s.id
        LEFT JOIN service_categories sc ON s.category_id = sc.id
        WHERE u.role = 'worker' AND u.status = 'active'
    ";
    
    $params = [];
    $param_types = "";
    $conditions = [];
    
    // Apply filters
    if (!empty($name)) {
        $conditions[] = "u.name LIKE ?";
        $params[] = '%' . $name . '%';
        $param_types .= "s";
    }
    
    if ($category_id > 0) {
        $conditions[] = "sc.id = ?";
        $params[] = $category_id;
        $param_types .= "i";
    }
    
    if (!empty($location)) {
        $conditions[] = "u.address LIKE ?";
        $params[] = '%' . $location . '%';
        $param_types .= "s";
    }
    
    if ($min_rating > 0) {
        $conditions[] = "wp.average_rating >= ?";
        $params[] = $min_rating;
        $param_types .= "d";
    }
    
    if ($available_only) {
        $conditions[] = "wp.is_available = 1";
    }
    
    // Add conditions to query
    if (!empty($conditions)) {
        $base_query .= " AND " . implode(' AND ', $conditions);
    }
    
    // Add GROUP BY
    $base_query .= " GROUP BY u.id";
    
    // Add ORDER BY
    switch ($sort_by) {
        case 'newest':
            $base_query .= " ORDER BY u.created_at DESC";
            break;
        case 'experience':
            $base_query .= " ORDER BY wp.experience_years DESC";
            break;
        case 'jobs_completed':
            $base_query .= " ORDER BY wp.total_jobs DESC";
            break;
        case 'rating':
        default:
            $base_query .= " ORDER BY wp.average_rating DESC, wp.total_jobs DESC";
            break;
    }
    
    $base_query .= " LIMIT 50";
    
    // Execute query
    $stmt = $conn->prepare($base_query);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Generate HTML
    $workers_html = '';
    $total_workers = 0;
    
    if ($result->num_rows > 0) {
        while ($worker = $result->fetch_assoc()) {
            $total_workers++;
            
            // Generate rating stars
            $rating = floatval($worker['average_rating']);
            $stars_html = '';
            for ($i = 1; $i <= 5; $i++) {
                if ($i <= $rating) {
                    $stars_html .= '<i class="fas fa-star text-warning"></i>';
                } elseif ($i - 0.5 <= $rating) {
                    $stars_html .= '<i class="fas fa-star-half-alt text-warning"></i>';
                } else {
                    $stars_html .= '<i class="far fa-star text-warning"></i>';
                }
            }
            
            $hourly_rate = '';
            if ($worker['hourly_rate_min'] && $worker['hourly_rate_max']) {
                $hourly_rate = 'Rs. ' . number_format($worker['hourly_rate_min']) . ' - ' . number_format($worker['hourly_rate_max']) . '/hour';
            } elseif ($worker['hourly_rate_min']) {
                $hourly_rate = 'From Rs. ' . number_format($worker['hourly_rate_min']) . '/hour';
            }
            
            $workers_html .= '
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-3">
                                ' . ($worker['profile_image'] ? 
                                    '<img src="../' . htmlspecialchars($worker['profile_image']) . '" class="rounded-circle" width="60" height="60" style="object-fit: cover;">' :
                                    '<div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; font-weight: bold;">' . strtoupper(substr($worker['name'], 0, 2)) . '</div>'
                                ) . '
                            </div>
                            <div class="col-9">
                                <h6 class="mb-1">' . htmlspecialchars($worker['name']) . '
                                    ' . ($worker['is_available'] ? '<span class="badge bg-success badge-sm ms-2">Available</span>' : '<span class="badge bg-secondary badge-sm ms-2">Busy</span>') . '
                                </h6>
                                <div class="mb-1">
                                    ' . $stars_html . ' 
                                    <span class="text-muted">(' . number_format($rating, 1) . ')</span>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-map-marker-alt me-1"></i>' . Functions::truncateText($worker['address'], 30) . '
                                </small>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <p class="text-muted small mb-2">
                                ' . Functions::truncateText($worker['bio'] ?: 'Professional service provider', 100) . '
                            </p>
                            
                            ' . ($worker['skills'] ? '<div class="mb-2"><small class="text-primary"><strong>Skills:</strong> ' . Functions::truncateText($worker['skills'], 60) . '</small></div>' : '') . '
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">
                                        ' . $worker['experience_years'] . ' years exp â€¢ ' . $worker['total_jobs'] . ' jobs done
                                    </small>
                                    ' . ($hourly_rate ? '<div class="small text-success fw-bold">' . $hourly_rate . '</div>' : '') . '
                                </div>
                                <div>
                                    <button class="btn btn-outline-primary btn-sm me-1" onclick="viewWorkerProfile(' . $worker['id'] . ')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-primary btn-sm" onclick="contactWorker(' . $worker['id'] . ')">
                                        <i class="fas fa-envelope"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>';
        }
    } else {
        $workers_html = '
        <div class="col-12">
            <div class="text-center py-5">
                <i class="fas fa-users fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">No workers found</h5>
                <p class="text-muted">Try adjusting your search criteria to find more professionals.</p>
            </div>
        </div>';
    }
    
    echo json_encode([
        'status' => 'success',
        'workers_html' => $workers_html,
        'total' => $total_workers
    ]);

} catch (Exception $e) {
    error_log("Search workers error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error searching workers']);
}

$conn->close();
?>