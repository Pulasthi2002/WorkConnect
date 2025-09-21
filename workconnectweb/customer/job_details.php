<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

SessionManager::requireRole(['customer']);

$user_id = $_SESSION['user_id'];
$job_id = intval($_GET['id'] ?? 0);

if ($job_id <= 0) {
    header("Location: my_jobs.php");
    exit;
}

// Get job details with application statistics
$job = null;
try {
    $stmt = $conn->prepare("
        SELECT 
            jp.*,
            sc.name as category_name,
            s.service_name,
            u.name as client_name,
            aw.name as assigned_worker_name,
            COUNT(DISTINCT ja.id) as total_applications,
            COUNT(CASE WHEN ja.status = 'pending' THEN 1 END) as pending_applications,
            COUNT(CASE WHEN ja.status = 'accepted' THEN 1 END) as accepted_applications,
            COUNT(CASE WHEN ja.status = 'rejected' THEN 1 END) as rejected_applications
        FROM job_postings jp
        INNER JOIN services s ON jp.service_id = s.id
        INNER JOIN service_categories sc ON s.category_id = sc.id
        INNER JOIN users u ON jp.client_id = u.id
        LEFT JOIN worker_profiles wp ON jp.assigned_worker_id = wp.id
        LEFT JOIN users aw ON wp.user_id = aw.id
        LEFT JOIN job_applications ja ON jp.id = ja.job_id
        WHERE jp.id = ? AND jp.client_id = ?
        GROUP BY jp.id
    ");
    $stmt->bind_param("ii", $job_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header("Location: my_jobs.php");
        exit;
    }
    
    $job = $result->fetch_assoc();
} catch (Exception $e) {
    error_log("Job details error: " . $e->getMessage());
    header("Location: my_jobs.php");
    exit;
}

// Get recent applications
$recent_applications = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            ja.*,
            u.name as worker_name,
            wp.average_rating,
            wp.total_jobs
        FROM job_applications ja
        INNER JOIN worker_profiles wp ON ja.worker_id = wp.id
        INNER JOIN users u ON wp.user_id = u.id
        WHERE ja.job_id = ?
        ORDER BY ja.applied_at DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $recent_applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Recent applications error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Details - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-tools me-2"></i><?php echo APP_NAME; ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="my_jobs.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to My Jobs
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Main Job Details -->
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h2 class="mb-2"><?php echo htmlspecialchars($job['title']); ?></h2>
                                <div class="mb-2">
                                    <span class="badge bg-light text-dark me-2">
                                        <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($job['category_name']); ?>
                                    </span>
                                    <span class="badge bg-light text-dark">
                                        <?php echo htmlspecialchars($job['service_name']); ?>
                                    </span>
                                </div>
                                <p class="text-muted mb-2">
                                    <i class="fas fa-map-marker-alt me-1"></i>
                                    <?php echo htmlspecialchars($job['location_address']); ?>
                                </p>
                                <p class="text-muted">
                                    <i class="fas fa-calendar-plus me-1"></i>
                                    Posted <?php echo Functions::timeAgo($job['created_at']); ?>
                                    <?php if ($job['updated_at'] != $job['created_at']): ?>
                                        â€¢ Updated <?php echo Functions::timeAgo($job['updated_at']); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="text-end">
                                <?php
                                $status_colors = [
                                    'open' => 'success',
                                    'assigned' => 'warning',
                                    'completed' => 'primary',
                                    'cancelled' => 'secondary'
                                ];
                                $color = $status_colors[$job['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $color; ?> fs-6 mb-2">
                                    <?php echo ucfirst($job['status']); ?>
                                </span>
                                <div>
                                    <?php
                                    $urgency_colors = [
                                        'urgent' => 'danger',
                                        'high' => 'warning',
                                        'medium' => 'primary',
                                        'low' => 'success'
                                    ];
                                    $urgency_color = $urgency_colors[$job['urgency']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $urgency_color; ?>">
                                        <?php echo ucfirst($job['urgency']); ?> Priority
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h5><i class="fas fa-file-alt me-2"></i>Description</h5>
                            <div class="bg-light p-3 rounded">
                                <?php echo nl2br(htmlspecialchars($job['description'])); ?>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-4">
                                <h6><i class="fas fa-money-bill-wave me-2"></i>Budget</h6>
                                <?php if ($job['budget_min'] && $job['budget_max']): ?>
                                    <p class="text-success fw-bold fs-5">
                                        <?php echo Functions::formatMoney($job['budget_min']); ?> - 
                                        <?php echo Functions::formatMoney($job['budget_max']); ?>
                                    </p>
                                    <small class="text-muted">Budget Type: <?php echo ucfirst($job['budget_type']); ?></small>
                                <?php else: ?>
                                    <p class="text-muted">Negotiable</p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <h6><i class="fas fa-users me-2"></i>Applications</h6>
                                <p class="fs-5 mb-1">
                                    <span class="fw-bold text-primary"><?php echo $job['total_applications']; ?></span> total
                                </p>
                                <small class="text-muted">
                                    <?php echo $job['pending_applications']; ?> pending â€¢ 
                                    <?php echo $job['accepted_applications']; ?> accepted â€¢ 
                                    <?php echo $job['rejected_applications']; ?> rejected
                                </small>
                            </div>
                            <div class="col-md-4">
                                <h6><i class="fas fa-clock me-2"></i>Timeline</h6>
                                <?php if ($job['assigned_worker_name']): ?>
                                    <p class="text-success mb-1">
                                        <i class="fas fa-user-check me-1"></i>
                                        Assigned to <?php echo htmlspecialchars($job['assigned_worker_name']); ?>
                                    </p>
                                <?php else: ?>
                                    <p class="text-muted">Not yet assigned</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex gap-2 flex-wrap">
                            <?php if ($job['status'] === 'open'): ?>
                                <a href="edit_job.php?id=<?php echo $job['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-edit me-2"></i>Edit Job
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($job['total_applications'] > 0): ?>
                                <a href="job_applications.php?job_id=<?php echo $job['id']; ?>" class="btn btn-info">
                                    <i class="fas fa-users me-2"></i>
                                    View All Applications (<?php echo $job['total_applications']; ?>)
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($job['status'] === 'open'): ?>
                                <button class="btn btn-warning" onclick="changeJobStatus(<?php echo $job['id']; ?>, 'pause')">
                                    <i class="fas fa-pause me-2"></i>Pause Job
                                </button>
                                <button class="btn btn-danger" onclick="changeJobStatus(<?php echo $job['id']; ?>, 'cancel')">
                                    <i class="fas fa-times me-2"></i>Cancel Job
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($job['status'] === 'assigned'): ?>
                                <button class="btn btn-success" onclick="changeJobStatus(<?php echo $job['id']; ?>, 'complete')">
                                    <i class="fas fa-check me-2"></i>Mark Complete
                                </button>
                            <?php endif; ?>
                            
                            <button class="btn btn-outline-secondary" onclick="shareJob()">
                                <i class="fas fa-share me-2"></i>Share Job
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Recent Applications -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-file-alt me-2"></i>Recent Applications</h6>
                        <?php if ($job['pending_applications'] > 0): ?>
                            <span class="badge bg-warning"><?php echo $job['pending_applications']; ?> pending</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_applications)): ?>
                            <?php foreach ($recent_applications as $app): ?>
                                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($app['worker_name']); ?></h6>
                                        <small class="text-muted">
                                            <?php echo Functions::formatMoney($app['proposed_rate']); ?>
                                            <?php if ($app['average_rating'] > 0): ?>
                                                â€¢ <i class="fas fa-star text-warning"></i> <?php echo number_format($app['average_rating'], 1); ?>
                                            <?php endif; ?>
                                        </small>
                                        <div class="small text-muted">
                                            <?php echo Functions::timeAgo($app['applied_at']); ?>
                                        </div>
                                    </div>
                                    <div>
                                        <?php
                                        $app_status_colors = [
                                            'pending' => 'warning',
                                            'accepted' => 'success',
                                            'rejected' => 'danger'
                                        ];
                                        $app_color = $app_status_colors[$app['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $app_color; ?>">
                                            <?php echo ucfirst($app['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($job['total_applications'] > 5): ?>
                                <div class="text-center">
                                    <a href="job_applications.php?job_id=<?php echo $job['id']; ?>" class="btn btn-outline-primary btn-sm">
                                        View All <?php echo $job['total_applications']; ?> Applications
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">No applications yet</p>
                                <small class="text-muted">Applications will appear here when workers apply</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Job Statistics -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Job Statistics</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <h4 class="text-primary"><?php echo $job['total_applications']; ?></h4>
                                <small>Total Applications</small>
                            </div>
                            <div class="col-6 mb-3">
                                <h4 class="text-warning"><?php echo $job['pending_applications']; ?></h4>
                                <small>Pending Review</small>
                            </div>
                            <div class="col-6">
                                <h4 class="text-success"><?php echo $job['accepted_applications']; ?></h4>
                                <small>Accepted</small>
                            </div>
                            <div class="col-6">
                                <h4 class="text-danger"><?php echo $job['rejected_applications']; ?></h4>
                                <small>Rejected</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="browse_workers.php" class="btn btn-outline-primary">
                                <i class="fas fa-search me-2"></i>Find Similar Workers
                            </a>
                            <a href="post_job.php" class="btn btn-outline-success">
                                <i class="fas fa-plus me-2"></i>Post Similar Job
                            </a>
                            <a href="messages.php" class="btn btn-outline-info">
                                <i class="fas fa-comments me-2"></i>Messages
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    <script>
function changeJobStatus(jobId, action) {
    let confirmText = '';
    switch(action) {
        case 'pause': confirmText = 'pause this job? It will be hidden from workers.'; break;
        case 'cancel': confirmText = 'cancel this job? This action cannot be undone.'; break;
        case 'complete': confirmText = 'mark this job as complete?'; break;
        case 'reopen': confirmText = 'reopen this job for new applications?'; break;
    }
    
    if (confirm(`Are you sure you want to ${confirmText}`)) {
        makeAjaxRequest(
            '../processes/update_job_status.php',
            {
                job_id: jobId,
                action: action,
                csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
            },
            function(response) {
                showAlert('success', response.message);
                setTimeout(() => location.reload(), 1500);
            },
            function(error) {
                showAlert('danger', error);
            }
        );
    }
}


    function shareJob() {
        const jobUrl = window.location.href;
        if (navigator.share) {
            navigator.share({
                title: '<?php echo htmlspecialchars($job['title']); ?>',
                text: 'Check out this job opportunity on <?php echo APP_NAME; ?>',
                url: jobUrl
            });
        } else {
            // Fallback - copy to clipboard
            navigator.clipboard.writeText(jobUrl).then(() => {
                showAlert('success', 'Job URL copied to clipboard!');
            });
        }
    }
    </script>
</body>
</html>