<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

SessionManager::requireRole(['admin']);

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get platform statistics
$stats = [];
try {
    // User statistics
    $result = $conn->query("SELECT COUNT(*) as total_users FROM users WHERE role != 'admin'");
    $stats['total_users'] = $result ? $result->fetch_assoc()['total_users'] : 0;
    
    $result = $conn->query("SELECT COUNT(*) as total_customers FROM users WHERE role = 'customer'");
    $stats['total_customers'] = $result ? $result->fetch_assoc()['total_customers'] : 0;
    
    $result = $conn->query("SELECT COUNT(*) as total_workers FROM users WHERE role = 'worker'");
    $stats['total_workers'] = $result ? $result->fetch_assoc()['total_workers'] : 0;
    
    // Job statistics
    $result = $conn->query("SELECT COUNT(*) as total_jobs FROM job_postings");
    $stats['total_jobs'] = $result ? $result->fetch_assoc()['total_jobs'] : 0;
    
    $result = $conn->query("SELECT COUNT(*) as open_jobs FROM job_postings WHERE status = 'open'");
    $stats['open_jobs'] = $result ? $result->fetch_assoc()['open_jobs'] : 0;
    
    $result = $conn->query("SELECT COUNT(*) as completed_jobs FROM job_postings WHERE status = 'completed'");
    $stats['completed_jobs'] = $result ? $result->fetch_assoc()['completed_jobs'] : 0;
    
    // Application statistics
    $result = $conn->query("SELECT COUNT(*) as total_applications FROM job_applications");
    $stats['total_applications'] = $result ? $result->fetch_assoc()['total_applications'] : 0;
    
    // Recent activities
    $stmt = $conn->prepare("
        SELECT u.name, u.role, u.created_at, 'registration' as activity_type
        FROM users u 
        WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND u.role != 'admin'
        UNION ALL
        SELECT u.name, 'customer' as role, jp.created_at, 'job_posted' as activity_type
        FROM job_postings jp
        INNER JOIN users u ON jp.client_id = u.id
        WHERE jp.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_activities = $stmt->get_result();
    
} catch (Exception $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    $stats = array_fill_keys(['total_users', 'total_customers', 'total_workers', 'total_jobs', 'open_jobs', 'completed_jobs', 'total_applications'], 0);
    $recent_activities = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-danger">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-tools me-2"></i><?php echo APP_NAME; ?> Admin
            </a>
            
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-shield me-1"></i><?php echo htmlspecialchars($user_name); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h2>Admin Dashboard</h2>
                <p class="text-muted">Platform overview and management</p>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="text-primary mb-1"><?php echo number_format($stats['total_users']); ?></h3>
                            <p class="text-muted mb-0">Total Users</p>
                            <small class="text-success">
                                <?php echo number_format($stats['total_customers']); ?> customers, 
                                <?php echo number_format($stats['total_workers']); ?> workers
                            </small>
                        </div>
                        <div class="text-primary">
                            <i class="fas fa-users fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="text-success mb-1"><?php echo number_format($stats['total_jobs']); ?></h3>
                            <p class="text-muted mb-0">Total Jobs</p>
                            <small class="text-warning">
                                <?php echo number_format($stats['open_jobs']); ?> open jobs
                            </small>
                        </div>
                        <div class="text-success">
                            <i class="fas fa-briefcase fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="text-warning mb-1"><?php echo number_format($stats['total_applications']); ?></h3>
                            <p class="text-muted mb-0">Applications</p>
                            <small class="text-info">
                                Total job applications
                            </small>
                        </div>
                        <div class="text-warning">
                            <i class="fas fa-file-alt fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="text-info mb-1"><?php echo number_format($stats['completed_jobs']); ?></h3>
                            <p class="text-muted mb-0">Completed Jobs</p>
                            <small class="text-success">
                                Successfully finished
                            </small>
                        </div>
                        <div class="text-info">
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="users.php" class="card card-hover h-100 text-decoration-none">
                                    <div class="card-body text-center">
                                        <i class="fas fa-users fa-3x text-primary mb-3"></i>
                                        <h6>Manage Users</h6>
                                        <p class="small text-muted mb-0">View and manage all users</p>
                                    </div>
                                </a>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <a href="jobs.php" class="card card-hover h-100 text-decoration-none">
                                    <div class="card-body text-center">
                                        <i class="fas fa-briefcase fa-3x text-success mb-3"></i>
                                        <h6>Manage Jobs</h6>
                                        <p class="small text-muted mb-0">Monitor job postings</p>
                                    </div>
                                </a>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <a href="categories.php" class="card card-hover h-100 text-decoration-none">
                                    <div class="card-body text-center">
                                        <i class="fas fa-tags fa-3x text-warning mb-3"></i>
                                        <h6>Categories</h6>
                                        <p class="small text-muted mb-0">Manage service categories</p>
                                    </div>
                                </a>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <a href="reports.php" class="card card-hover h-100 text-decoration-none">
                                    <div class="card-body text-center">
                                        <i class="fas fa-chart-bar fa-3x text-info mb-3"></i>
                                        <h6>Reports</h6>
                                        <p class="small text-muted mb-0">View platform analytics</p>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Recent Platform Activity</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($recent_activities && $recent_activities->num_rows > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php while ($activity = $recent_activities->fetch_assoc()): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <div>
                                                <h6 class="mb-1">
                                                    <?php echo htmlspecialchars($activity['name']); ?>
                                                    <span class="badge bg-<?php echo $activity['role'] === 'customer' ? 'primary' : 'success'; ?> ms-2">
                                                        <?php echo ucfirst($activity['role']); ?>
                                                    </span>
                                                </h6>
                                                <p class="mb-1">
                                                    <?php 
                                                    echo $activity['activity_type'] === 'registration' 
                                                        ? 'New user registered' 
                                                        : 'Posted a new job';
                                                    ?>
                                                </p>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo Functions::timeAgo($activity['created_at']); ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No recent activity</p>
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
</body>
</html>