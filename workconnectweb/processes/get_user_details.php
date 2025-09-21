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

$user_id = intval($_POST['user_id'] ?? 0);
$detailed = isset($_POST['detailed']);

if ($user_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user ID']);
    exit;
}

try {
    // Get user details with additional info
    $stmt = $conn->prepare("
        SELECT 
            u.*,
            wp.bio, wp.experience_years, wp.hourly_rate_min, wp.hourly_rate_max,
            wp.is_available, wp.average_rating, wp.total_jobs,
            (SELECT COUNT(*) FROM job_postings WHERE client_id = u.id) as jobs_posted,
            (SELECT COUNT(*) FROM job_applications ja 
             INNER JOIN worker_profiles wp2 ON ja.worker_id = wp2.id 
             WHERE wp2.user_id = u.id) as applications_sent
        FROM users u
        LEFT JOIN worker_profiles wp ON u.id = wp.user_id
        WHERE u.id = ? AND u.role != 'admin'
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    
    if ($detailed) {
        // Generate detailed HTML for modal
        $user_html = '
            <div class="row">
                <div class="col-md-4 text-center">
                    <div class="bg-' . ($user['role'] === 'customer' ? 'primary' : 'success') . ' text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                         style="width: 100px; height: 100px; font-size: 2rem; font-weight: bold;">
                        ' . strtoupper(substr($user['name'], 0, 2)) . '
                    </div>
                    <h5>' . htmlspecialchars($user['name']) . '</h5>
                    <span class="badge bg-' . ($user['role'] === 'customer' ? 'primary' : 'success') . ' mb-2">' . ucfirst($user['role']) . '</span>
                    <br>
                    <span class="badge bg-' . ($user['status'] === 'active' ? 'success' : 'danger') . '">' . ucfirst($user['status']) . '</span>
                </div>
                <div class="col-md-8">
                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Email:</strong></div>
                        <div class="col-sm-8">' . htmlspecialchars($user['email']) . '</div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Phone:</strong></div>
                        <div class="col-sm-8">' . htmlspecialchars($user['telephone']) . '</div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Address:</strong></div>
                        <div class="col-sm-8">' . htmlspecialchars($user['address']) . '</div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Joined:</strong></div>
                        <div class="col-sm-8">' . date('M j, Y', strtotime($user['created_at'])) . ' (' . Functions::timeAgo($user['created_at']) . ')</div>
                    </div>';

        if ($user['role'] === 'customer') {
            $user_html .= '
                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Jobs Posted:</strong></div>
                        <div class="col-sm-8">' . intval($user['jobs_posted']) . '</div>
                    </div>';
        } else {
            $user_html .= '
                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Applications:</strong></div>
                        <div class="col-sm-8">' . intval($user['applications_sent']) . '</div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Jobs Completed:</strong></div>
                        <div class="col-sm-8">' . intval($user['total_jobs']) . '</div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Experience:</strong></div>
                        <div class="col-sm-8">' . intval($user['experience_years']) . ' years</div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Rating:</strong></div>
                        <div class="col-sm-8">' . number_format($user['average_rating'] ?? 0, 1) . ' ★</div>
                    </div>';
        }

        $user_html .= '
                </div>
            </div>';

        if ($user['role'] === 'worker' && !empty($user['bio'])) {
            $user_html .= '
            <div class="row mt-4">
                <div class="col-12">
                    <h6>Bio:</h6>
                    <p class="text-muted">' . nl2br(htmlspecialchars($user['bio'])) . '</p>
                </div>
            </div>';
        }

        echo json_encode([
            'status' => 'success',
            'user_html' => $user_html,
            'user' => $user
        ]);
    } else {
        // Return just user data for editing
        echo json_encode([
            'status' => 'success',
            'user' => $user
        ]);
    }

} catch (Exception $e) {
    error_log("Get user details error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to load user details']);
}

$conn->close();
?>
