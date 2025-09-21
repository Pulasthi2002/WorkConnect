<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

SessionManager::requireRole(['customer']);

$user_id = $_SESSION['user_id'];
$job_id = intval($_GET['job_id'] ?? 0);

if ($job_id <= 0) {
    header("Location: my_jobs.php");
    exit;
}

// Verify job ownership
$job = null;
try {
    $stmt = $conn->prepare("
        SELECT jp.*, sc.name as category_name, s.service_name
        FROM job_postings jp
        INNER JOIN services s ON jp.service_id = s.id
        INNER JOIN service_categories sc ON s.category_id = sc.id
        WHERE jp.id = ? AND jp.client_id = ?
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
    error_log("Job verification error: " . $e->getMessage());
    header("Location: my_jobs.php");
    exit;
}

// Get applications for this job - CORRECTED JOINS
$applications = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            ja.*,
            u.id as worker_user_id,  -- Add this line
            u.name as worker_name,
            u.email as worker_email,
            u.telephone as worker_phone,
            u.address as worker_address,
            wp.bio, wp.experience_years, wp.average_rating, wp.total_jobs,
            wp.hourly_rate_min, wp.hourly_rate_max
        FROM job_applications ja
        INNER JOIN worker_profiles wp ON ja.worker_id = wp.id
        INNER JOIN users u ON wp.user_id = u.id
        WHERE ja.job_id = ?
        ORDER BY 
            CASE ja.status 
                WHEN 'pending' THEN 1 
                WHEN 'accepted' THEN 2 
                WHEN 'rejected' THEN 3 
            END,
            ja.applied_at DESC
    ");
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $applications = $stmt->get_result();
} catch (Exception $e) {
    error_log("Applications fetch error: " . $e->getMessage());
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Applications - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .application-card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .application-card:hover {
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
    </style>
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
        <!-- Job Info Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-light">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4 class="mb-1"><?php echo htmlspecialchars($job['title']); ?></h4>
                                <p class="text-muted mb-2">
                                    <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($job['category_name']); ?> â€¢
                                    <?php echo htmlspecialchars($job['service_name']); ?> â€¢
                                    <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($job['location_address']); ?>
                                </p>
                                <p class="mb-0"><?php echo Functions::truncateText($job['description'], 150); ?></p>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <?php
                                $status_colors = [
                                    'open' => 'success',
                                    'assigned' => 'warning', 
                                    'completed' => 'info',
                                    'cancelled' => 'secondary'
                                ];
                                $color = $status_colors[$job['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $color; ?> fs-6 mb-2">
                                    <?php echo ucfirst($job['status']); ?>
                                </span>
                                <div>
                                    <?php if ($job['budget_min'] && $job['budget_max']): ?>
                                        <strong class="text-success">
                                            <?php echo Functions::formatMoney($job['budget_min']); ?> - 
                                            <?php echo Functions::formatMoney($job['budget_max']); ?>
                                        </strong>
                                    <?php else: ?>
                                        <strong class="text-muted">Budget: Negotiable</strong>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Applications -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-users me-2"></i>Applications
                            <span class="badge bg-primary ms-2"><?php echo $applications ? $applications->num_rows : 0; ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($applications && $applications->num_rows > 0): ?>
                            <?php while ($app = $applications->fetch_assoc()): ?>
                                <div class="card mb-3 application-card">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-lg-8">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <h5 class="mb-1"><?php echo htmlspecialchars($app['worker_name']); ?></h5>
                                                        <div class="mb-2">
                                                            <?php if ($app['average_rating'] > 0): ?>
                                                                <span class="text-warning">
                                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                        <i class="fas fa-star<?php echo $i <= $app['average_rating'] ? '' : '-o'; ?>"></i>
                                                                    <?php endfor; ?>
                                                                </span>
                                                                <span class="text-muted">(<?php echo number_format($app['average_rating'], 1); ?>)</span>
                                                            <?php endif; ?>
                                                            
                                                            <span class="badge bg-info ms-2"><?php echo $app['total_jobs']; ?> jobs completed</span>
                                                            <span class="badge bg-secondary ms-1"><?php echo $app['experience_years']; ?> years exp</span>
                                                        </div>
                                                    </div>
                                                    <?php
                                                    $status_colors = [
                                                        'pending' => 'warning',
                                                        'accepted' => 'success',
                                                        'rejected' => 'danger'
                                                    ];
                                                    $status_color = $status_colors[$app['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $status_color; ?> fs-6" id="status-<?php echo $app['id']; ?>">
                                                        <?php echo ucfirst($app['status']); ?>
                                                    </span>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <h6>Proposal:</h6>
                                                    <p class="mb-2"><?php echo nl2br(htmlspecialchars($app['cover_message'])); ?></p>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <strong>Rate: <?php echo Functions::formatMoney($app['proposed_rate']); ?></strong>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <?php if ($app['proposed_timeline']): ?>
                                                                <strong>Timeline: <?php echo htmlspecialchars($app['proposed_timeline']); ?></strong>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-2">
                                                    <small class="text-muted">
                                                        <i class="fas fa-clock me-1"></i>Applied <?php echo Functions::timeAgo($app['applied_at']); ?>
                                                    </small>
                                                </div>
                                                
                                                <?php if ($app['bio']): ?>
                                                    <div class="mb-2">
                                                        <small><strong>Bio:</strong> <?php echo Functions::truncateText($app['bio'], 100); ?></small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="col-lg-4">
                                                <div class="card bg-light h-100">
                                                    <div class="card-body">
                                                        <h6>Contact Information</h6>
                                                        <p class="mb-1">
                                                            <i class="fas fa-envelope me-1"></i>
                                                            <a href="mailto:<?php echo htmlspecialchars($app['worker_email']); ?>">
                                                                <?php echo htmlspecialchars($app['worker_email']); ?>
                                                            </a>
                                                        </p>
                                                        <p class="mb-1">
                                                            <i class="fas fa-phone me-1"></i>
                                                            <a href="tel:<?php echo htmlspecialchars($app['worker_phone']); ?>">
                                                                <?php echo htmlspecialchars($app['worker_phone']); ?>
                                                            </a>
                                                        </p>
                                                        <p class="mb-3">
                                                            <i class="fas fa-map-marker-alt me-1"></i>
                                                            <?php echo htmlspecialchars($app['worker_address']); ?>
                                                        </p>
                                                        
                                                        <?php if ($app['hourly_rate_min'] || $app['hourly_rate_max']): ?>
                                                            <div class="mb-3">
                                                                <small><strong>Hourly Rates:</strong></small>
                                                                <div class="small text-muted">
                                                                    <?php if ($app['hourly_rate_min'] && $app['hourly_rate_max']): ?>
                                                                        <?php echo Functions::formatMoney($app['hourly_rate_min']); ?> - 
                                                                        <?php echo Functions::formatMoney($app['hourly_rate_max']); ?>
                                                                    <?php elseif ($app['hourly_rate_min']): ?>
                                                                        From <?php echo Functions::formatMoney($app['hourly_rate_min']); ?>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($app['status'] === 'pending'): ?>
                                                            <div class="d-grid gap-2">
                                                                <button class="btn btn-success btn-sm" 
                                                                        onclick="handleApplication(<?php echo $app['id']; ?>, 'accepted')">
                                                                    <i class="fas fa-check me-1"></i>Accept
                                                                </button>
                                                                <button class="btn btn-outline-danger btn-sm" 
                                                                        onclick="handleApplication(<?php echo $app['id']; ?>, 'rejected')">
                                                                    <i class="fas fa-times me-1"></i>Reject
                                                                </button>
                                                                <!-- Replace the existing contact worker button with this -->
                                                                <button class="btn btn-outline-primary btn-sm" 
                                                                        onclick="contactWorker(<?php echo $app['worker_user_id']; ?>)">
                                                                    <i class="fas fa-envelope me-1"></i>Message
                                                                </button>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="d-grid">
                                                                <button class="btn btn-outline-primary btn-sm" 
                                                                        onclick="contactWorker(<?php echo $app['worker_id']; ?>)">
                                                                    <i class="fas fa-envelope me-1"></i>Message Worker
                                                                </button>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No Applications Yet</h5>
                                <p class="text-muted">Workers will apply for your job and appear here</p>
                                <a href="browse_workers.php" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Browse Workers
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    <script>
    function handleApplication(applicationId, action) {
        const actionText = action === 'accepted' ? 'accept' : 'reject';
        
        if (confirm(`Are you sure you want to ${actionText} this application?`)) {
            $.ajax({
                url: '../processes/manage_application.php',
                method: 'POST',
                data: {
                    application_id: applicationId,
                    action: action,
                    csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        showAlert('success', response.message);
                        
                        // Update the status badge
                        const statusBadge = $('#status-' + applicationId);
                        statusBadge.removeClass('bg-warning bg-success bg-danger')
                                   .addClass(action === 'accepted' ? 'bg-success' : 'bg-danger')
                                   .text(action === 'accepted' ? 'Accepted' : 'Rejected');
                        
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert('danger', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', xhr.status, xhr.responseText);
                    showAlert('danger', 'Failed to update application status. Please try again.');
                }
            });
        }
    }

    function contactWorker(workerId) {
        window.location.href = `messages.php?with=${workerId}`;
    }
    </script>
</body>
</html>