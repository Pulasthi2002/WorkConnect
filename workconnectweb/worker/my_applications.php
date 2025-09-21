<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

SessionManager::requireRole(['worker']);

$user_id = $_SESSION['user_id'];

// Get worker profile
$worker_profile = null;
try {
    $stmt = $conn->prepare("SELECT id FROM worker_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $worker_profile = $stmt->get_result()->fetch_assoc();
} catch (Exception $e) {
    header("Location: dashboard.php");
    exit;
}

// Get application statistics
$stats = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
            COUNT(CASE WHEN status = 'accepted' THEN 1 END) as accepted,
            COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
            AVG(proposed_rate) as avg_rate
        FROM job_applications 
        WHERE worker_id = ?
    ");
    $stmt->bind_param("i", $worker_profile['id']);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
} catch (Exception $e) {
    $stats = ['total' => 0, 'pending' => 0, 'accepted' => 0, 'rejected' => 0, 'avg_rate' => 0];
}

// Get all applications with filters
$status_filter = $_GET['status'] ?? 'all';
$applications = null;

try {
    $base_query = "
        SELECT 
            ja.*,
            jp.title as job_title,
            jp.status as job_status,
            jp.budget_min, jp.budget_max, jp.urgency,
            u.name as client_name,
            sc.name as category_name,
            s.service_name
        FROM job_applications ja
        INNER JOIN job_postings jp ON ja.job_id = jp.id
        INNER JOIN services s ON jp.service_id = s.id
        INNER JOIN service_categories sc ON s.category_id = sc.id
        INNER JOIN users u ON jp.client_id = u.id
        WHERE ja.worker_id = ?
    ";
    
    $params = [$worker_profile['id']];
    
    if ($status_filter !== 'all') {
        $base_query .= " AND ja.status = ?";
        $params[] = $status_filter;
    }
    
    $base_query .= " ORDER BY ja.applied_at DESC";
    
    $stmt = $conn->prepare($base_query);
    if (count($params) > 1) {
        $stmt->bind_param("is", ...$params);
    } else {
        $stmt->bind_param("i", $params[0]);
    }
    
    $stmt->execute();
    $applications = $stmt->get_result();
} catch (Exception $e) {
    error_log("Applications error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Applications - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-tools me-2"></i><?php echo APP_NAME; ?> Worker
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h2><i class="fas fa-file-alt text-success me-2"></i>My Applications</h2>
                <p class="text-muted">Track and manage your job applications</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="browse_jobs.php" class="btn btn-success">
                    <i class="fas fa-search me-2"></i>Find More Jobs
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-primary"><?php echo $stats['total']; ?></h3>
                        <p class="text-muted mb-0">Total Applications</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-warning"><?php echo $stats['pending']; ?></h3>
                        <p class="text-muted mb-0">Pending</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-success"><?php echo $stats['accepted']; ?></h3>
                        <p class="text-muted mb-0">Accepted</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-info"><?php echo Functions::formatMoney($stats['avg_rate']); ?></h3>
                        <p class="text-muted mb-0">Average Rate</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-pills card-header-pills" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status_filter === 'all' ? 'active' : ''; ?>" 
                           href="?status=all">All (<?php echo $stats['total']; ?>)</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status_filter === 'pending' ? 'active' : ''; ?>" 
                           href="?status=pending">Pending (<?php echo $stats['pending']; ?>)</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status_filter === 'accepted' ? 'active' : ''; ?>" 
                           href="?status=accepted">Accepted (<?php echo $stats['accepted']; ?>)</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>" 
                           href="?status=rejected">Rejected (<?php echo $stats['rejected']; ?>)</a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <?php if ($applications && $applications->num_rows > 0): ?>
                    <?php while ($app = $applications->fetch_assoc()): ?>
                        <div class="card mb-3 border-start border-<?php
                            echo $app['status'] === 'accepted' ? 'success' : 
                                ($app['status'] === 'rejected' ? 'danger' : 'warning');
                        ?> border-3">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h5 class="mb-0"><?php echo htmlspecialchars($app['job_title']); ?></h5>
                                            <span class="badge bg-<?php
                                                echo $app['status'] === 'accepted' ? 'success' : 
                                                    ($app['status'] === 'rejected' ? 'danger' : 'warning');
                                            ?>"><?php echo ucfirst($app['status']); ?></span>
                                        </div>
                                        
                                        <p class="text-muted mb-2">
                                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($app['client_name']); ?>
                                            • <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($app['category_name']); ?>
                                            • <i class="fas fa-clock me-1"></i><?php echo Functions::timeAgo($app['applied_at']); ?>
                                        </p>
                                        
                                        <div class="mb-2">
                                            <strong>Your Proposal:</strong> <?php echo Functions::formatMoney($app['proposed_rate']); ?>
                                            <?php if ($app['proposed_timeline']): ?>
                                                • Timeline: <?php echo htmlspecialchars($app['proposed_timeline']); ?>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <p class="mb-0">
                                            <strong>Cover Message:</strong>
                                            <?php echo Functions::truncateText($app['cover_message'], 150); ?>
                                        </p>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="card bg-light h-100">
                                            <div class="card-body">
                                                <h6>Job Details</h6>
                                                
                                                <?php if ($app['budget_min'] && $app['budget_max']): ?>
                                                    <div class="mb-1">
                                                        <strong>Budget:</strong> 
                                                        <?php echo Functions::formatMoney($app['budget_min']); ?> - 
                                                        <?php echo Functions::formatMoney($app['budget_max']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="mb-2">
                                                    <strong>Urgency:</strong> 
                                                    <span class="badge bg-<?php
                                                        echo $app['urgency'] === 'urgent' ? 'danger' : 
                                                            ($app['urgency'] === 'high' ? 'warning' : 'primary');
                                                    ?>"><?php echo ucfirst($app['urgency']); ?></span>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <strong>Job Status:</strong> 
                                                    <span class="badge bg-<?php
                                                        echo $app['job_status'] === 'completed' ? 'success' : 
                                                            ($app['job_status'] === 'assigned' ? 'warning' : 'primary');
                                                    ?>"><?php echo ucfirst($app['job_status']); ?></span>
                                                </div>
                                                
                                                <div class="d-grid gap-2">
                                                    <button class="btn btn-outline-primary btn-sm" 
                                                            onclick="viewApplication(<?php echo $app['id']; ?>)">
                                                        <i class="fas fa-eye me-1"></i>View Details
                                                    </button>
                                                    
                                                    <?php if ($app['status'] === 'accepted'): ?>
                                                        <button class="btn btn-success btn-sm" 
                                                                onclick="contactClient(<?php echo $app['job_id']; ?>)">
                                                            <i class="fas fa-comments me-1"></i>Contact Client
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($app['status'] === 'pending'): ?>
                                                        <button class="btn btn-outline-secondary btn-sm" 
                                                                onclick="withdrawApplication(<?php echo $app['id']; ?>)">
                                                            <i class="fas fa-times me-1"></i>Withdraw
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">
                            <?php echo $status_filter === 'all' ? 'No applications yet' : 'No ' . $status_filter . ' applications'; ?>
                        </h5>
                        <p class="text-muted">
                            <?php echo $status_filter === 'all' ? 'Start applying to jobs to build your track record' : 'Applications with this status will appear here'; ?>
                        </p>
                        <?php if ($status_filter === 'all'): ?>
                            <a href="browse_jobs.php" class="btn btn-success">
                                <i class="fas fa-search me-2"></i>Browse Jobs
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    
    <script>
        function viewApplication(applicationId) {
            window.location.href = `application_details.php?id=${applicationId}`;
        }

        function contactClient(jobId) {
            window.location.href = `messages.php?job=${jobId}`;
        }

        function withdrawApplication(applicationId) {
            if (confirm('Are you sure you want to withdraw this application?')) {
                makeAjaxRequest(
                    '../processes/withdraw_application.php',
                    {
                        application_id: applicationId,
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
    </script>
</body>
</html>
