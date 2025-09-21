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

$search = Security::sanitizeInput($_POST['search'] ?? '');
$role = Security::sanitizeInput($_POST['role'] ?? '');
$status = Security::sanitizeInput($_POST['status'] ?? '');
$page = max(1, intval($_POST['page'] ?? 1));
$per_page = max(1, min(50, intval($_POST['per_page'] ?? 10)));
$offset = ($page - 1) * $per_page;

try {
    // Build WHERE conditions
    $conditions = ["u.role != 'admin'"];
    $params = [];
    $param_types = "";

    if (!empty($search)) {
        $conditions[] = "(u.name LIKE ? OR u.email LIKE ? OR u.telephone LIKE ?)";
        $search_param = '%' . $search . '%';
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
        $param_types .= "sss";
    }

    if (!empty($role)) {
        $conditions[] = "u.role = ?";
        $params[] = $role;
        $param_types .= "s";
    }

    if (!empty($status)) {
        $conditions[] = "u.status = ?";
        $params[] = $status;
        $param_types .= "s";
    }

    $where_clause = "WHERE " . implode(' AND ', $conditions);

    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM users u $where_clause";
    $stmt = $conn->prepare($count_query);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $total_users = $stmt->get_result()->fetch_assoc()['total'];
    $total_pages = ceil($total_users / $per_page);

    // Get users with additional data
    $main_query = "
        SELECT 
            u.*,
            COALESCE(jp_count.job_count, 0) as job_count,
            COALESCE(ja_count.application_count, 0) as application_count,
            wp.average_rating,
            wp.total_jobs as completed_jobs
        FROM users u
        LEFT JOIN (
            SELECT client_id, COUNT(*) as job_count 
            FROM job_postings 
            GROUP BY client_id
        ) jp_count ON u.id = jp_count.client_id AND u.role = 'customer'
        LEFT JOIN (
            SELECT wp.user_id, COUNT(*) as application_count 
            FROM job_applications ja
            INNER JOIN worker_profiles wp ON ja.worker_id = wp.id
            GROUP BY wp.user_id
        ) ja_count ON u.id = ja_count.user_id AND u.role = 'worker'
        LEFT JOIN worker_profiles wp ON u.id = wp.user_id AND u.role = 'worker'
        $where_clause
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($main_query);
    $all_params = array_merge($params, [$per_page, $offset]);
    $all_param_types = $param_types . "ii";
    $stmt->bind_param($all_param_types, ...$all_params);
    $stmt->execute();
    $result = $stmt->get_result();

    $users_html = '';
    if ($result->num_rows > 0) {
        while ($user = $result->fetch_assoc()) {
            $role_badge = $user['role'] === 'customer' ? 'primary' : 'success';
            $status_badge = $user['status'] === 'active' ? 'success' : 'danger';
            
            $stats_text = '';
            if ($user['role'] === 'customer') {
                $stats_text = $user['job_count'] . ' jobs posted';
            } else {
                $stats_text = $user['application_count'] . ' applications';
                if ($user['completed_jobs'] > 0) {
                    $stats_text .= ', ' . $user['completed_jobs'] . ' completed';
                }
                if ($user['average_rating'] > 0) {
                    $stats_text .= ', ' . number_format($user['average_rating'], 1) . '★';
                }
            }

            $users_html .= '
            <tr>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="bg-' . $role_badge . ' text-white rounded-circle d-flex align-items-center justify-content-center me-3" 
                             style="width: 40px; height: 40px; font-weight: bold;">
                            ' . strtoupper(substr($user['name'], 0, 2)) . '
                        </div>
                        <div>
                            <h6 class="mb-0">' . htmlspecialchars($user['name']) . '</h6>
                            <small class="text-muted">' . htmlspecialchars($user['telephone']) . '</small>
                        </div>
                    </div>
                </td>
                <td>' . htmlspecialchars($user['email']) . '</td>
                <td><span class="badge bg-' . $role_badge . '">' . ucfirst($user['role']) . '</span></td>
                <td><span class="badge bg-' . $status_badge . '">' . ucfirst($user['status']) . '</span></td>
                <td><small>' . Functions::timeAgo($user['created_at']) . '</small></td>
                <td><small>' . $stats_text . '</small></td>
                <td>
                    <div class="btn-group" role="group">
                        <button class="btn btn-sm btn-outline-primary" onclick="viewUser(' . $user['id'] . ')" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-warning" onclick="editUser(' . $user['id'] . ')" title="Edit User">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(' . $user['id'] . ', \'' . htmlspecialchars($user['name'], ENT_QUOTES) . '\')" title="Delete User">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>';
        }
    } else {
        $users_html = '<tr><td colspan="7" class="text-center text-muted">No users found</td></tr>';
    }

    echo json_encode([
        'status' => 'success',
        'users_html' => $users_html,
        'total_users' => $total_users,
        'current_page' => $page,
        'total_pages' => $total_pages,
        'per_page' => $per_page
    ]);

} catch (Exception $e) {
    error_log("Get admin users error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to load users']);
}

$conn->close();
?>
