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

// Parse parameters
$search = Security::sanitizeInput($_POST['search'] ?? '');
$category_id = intval($_POST['category_id'] ?? 0);
$status = Security::sanitizeInput($_POST['status'] ?? '');
$urgency = Security::sanitizeInput($_POST['urgency'] ?? '');
$page = intval($_POST['page'] ?? 1);
$per_page = intval($_POST['per_page'] ?? 25);

// Validate per_page
$per_page = min(max($per_page, 10), 100);

try {
    // Build query
    $base_query = "
        SELECT 
            jp.*,
            sc.name as category_name,
            s.service_name,
            u.name as client_name,
            u.email as client_email,
            (SELECT COUNT(*) FROM job_applications WHERE job_id = jp.id) as application_count,
            CASE 
                WHEN jp.assigned_worker_id IS NOT NULL THEN 
                    (SELECT u2.name FROM worker_profiles wp2 
                     INNER JOIN users u2 ON wp2.user_id = u2.id 
                     WHERE wp2.id = jp.assigned_worker_id)
                ELSE NULL 
            END as assigned_worker_name
        FROM job_postings jp
        INNER JOIN services s ON jp.service_id = s.id
        INNER JOIN service_categories sc ON s.category_id = sc.id
        INNER JOIN users u ON jp.client_id = u.id
        WHERE 1=1
    ";
    
    $params = [];
    $param_types = "";
    
    // Apply filters
    if (!empty($search)) {
        $base_query .= " AND (jp.title LIKE ? OR jp.description LIKE ? OR u.name LIKE ?)";
        $search_param = '%' . $search . '%';
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
        $param_types .= "sss";
    }
    
    if ($category_id > 0) {
        $base_query .= " AND sc.id = ?";
        $params[] = $category_id;
        $param_types .= "i";
    }
    
    if (!empty($status)) {
        $base_query .= " AND jp.status = ?";
        $params[] = $status;
        $param_types .= "s";
    }
    
    if (!empty($urgency)) {
        $base_query .= " AND jp.urgency = ?";
        $params[] = $urgency;
        $param_types .= "s";
    }
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM (" . $base_query . ") as counted";
    $count_stmt = $conn->prepare($count_query);
    if (!empty($params)) {
        $count_stmt->bind_param($param_types, ...$params);
    }
    $count_stmt->execute();
    $total_jobs = $count_stmt->get_result()->fetch_assoc()['total'];
    
    // Calculate pagination
    $total_pages = ceil($total_jobs / $per_page);
    $page = max(1, min($page, $total_pages));
    $offset = ($page - 1) * $per_page;
    
    // Add ordering and pagination
    $base_query .= " ORDER BY jp.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;
    $param_types .= "ii";
    
    // Execute main query
    $stmt = $conn->prepare($base_query);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Generate table HTML
    $jobs_html = '';
    $jobs_cards_html = '';
    
    if ($result->num_rows > 0) {
        while ($job = $result->fetch_assoc()) {
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
            
            // Table row
            $jobs_html .= '
                <tr>
                    <td><strong>#' . $job['id'] . '</strong></td>
                    <td>
                        <h6 class="mb-1">' . htmlspecialchars($job['title']) . '</h6>
                        <small class="text-muted">' . Functions::truncateText($job['description'], 80) . '</small>
                        <div class="mt-1">
                            <span class="badge bg-' . $urgency_color . ' badge-sm">' . ucfirst($job['urgency']) . '</span>
                        </div>
                    </td>
                    <td>
                        <div>' . htmlspecialchars($job['client_name']) . '</div>
                        <small class="text-muted">' . htmlspecialchars($job['client_email']) . '</small>
                    </td>
                    <td>' . htmlspecialchars($job['category_name']) . '</td>
                    <td>' . $budget_text . '</td>
                    <td>
                        <span class="badge bg-' . $status_color . '">' . ucfirst($job['status']) . '</span>
                        ' . ($job['assigned_worker_name'] ? '<div class="small text-muted mt-1">Worker: ' . htmlspecialchars($job['assigned_worker_name']) . '</div>' : '') . '
                    </td>
                    <td><span class="badge bg-info">' . $job['application_count'] . '</span></td>
                    <td>' . Functions::timeAgo($job['created_at']) . '</td>
                    <td>
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-outline-primary" onclick="viewJob(' . $job['id'] . ')" title="View">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-warning" onclick="editJob(' . $job['id'] . ')" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteJob(' . $job['id'] . ', \'' . addslashes($job['title']) . '\')" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            ';
            
            // Card view
            $jobs_cards_html .= '
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card job-card h-100" onclick="viewJob(' . $job['id'] . ')">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">#' . $job['id'] . '</h6>
                            <span class="badge bg-' . $status_color . '">' . ucfirst($job['status']) . '</span>
                        </div>
                        <div class="card-body">
                            <h6 class="card-title">' . htmlspecialchars($job['title']) . '</h6>
                            <p class="card-text text-muted small">' . Functions::truncateText($job['description'], 100) . '</p>
                            
                            <div class="mb-2">
                                <small class="text-muted">
                                    <i class="fas fa-user me-1"></i>' . htmlspecialchars($job['client_name']) . '<br>
                                    <i class="fas fa-tag me-1"></i>' . htmlspecialchars($job['category_name']) . '<br>
                                    <i class="fas fa-money-bill-wave me-1"></i>' . $budget_text . '
                                </small>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-' . $urgency_color . '">' . ucfirst($job['urgency']) . '</span>
                                <small class="text-muted">' . Functions::timeAgo($job['created_at']) . '</small>
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="action-buttons" onclick="event.stopPropagation()">
                                <button class="btn btn-sm btn-outline-primary" onclick="viewJob(' . $job['id'] . ')" title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-warning" onclick="editJob(' . $job['id'] . ')" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteJob(' . $job['id'] . ', \'' . addslashes($job['title']) . '\')" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            ';
        }
    } else {
        $jobs_html = '<tr><td colspan="9" class="text-center py-4">No jobs found</td></tr>';
        $jobs_cards_html = '<div class="col-12 text-center py-5"><h5 class="text-muted">No jobs found</h5></div>';
    }
    
    // Pagination info
    $start = $total_jobs > 0 ? $offset + 1 : 0;
    $end = min($offset + $per_page, $total_jobs);
    
    $pagination = [
        'current_page' => $page,
        'total_pages' => $total_pages,
        'start_page' => max(1, $page - 2),
        'end_page' => min($total_pages, $page + 2)
    ];
    
    echo json_encode([
        'status' => 'success',
        'jobs_html' => $jobs_html,
        'jobs_cards_html' => $jobs_cards_html,
        'total' => $total_jobs,
        'start' => $start,
        'end' => $end,
        'pagination' => $pagination
    ]);

} catch (Exception $e) {
    error_log("Get admin jobs error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error loading jobs']);
}

$conn->close();
?>
