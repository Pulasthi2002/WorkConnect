<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

SessionManager::requireRole(['worker']);

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get or create worker profile with comprehensive data
$worker_profile = null;
try {
    $stmt = $conn->prepare("
        SELECT wp.*, COUNT(DISTINCT ws.id) as total_skills,
               COUNT(DISTINCT ja.id) as total_applications,
               COUNT(DISTINCT CASE WHEN ja.status = 'accepted' THEN ja.id END) as accepted_applications,
               COALESCE(SUM(CASE WHEN jp.status = 'completed' AND ja.status = 'accepted' THEN ja.proposed_rate ELSE 0 END), 0) as total_earnings,
               COUNT(DISTINCT CASE WHEN jp.status = 'completed' AND ja.status = 'accepted' THEN jp.id END) as completed_jobs
        FROM worker_profiles wp
        LEFT JOIN worker_skills ws ON wp.id = ws.worker_id
        LEFT JOIN job_applications ja ON wp.id = ja.worker_id
        LEFT JOIN job_postings jp ON ja.job_id = jp.id
        WHERE wp.user_id = ?
        GROUP BY wp.id
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Create worker profile if doesn't exist
        $stmt = $conn->prepare("INSERT INTO worker_profiles (user_id, created_at) VALUES (?, NOW())");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        // Get the created profile
        $stmt = $conn->prepare("
            SELECT *, 0 as total_skills, 0 as total_applications, 
                   0 as accepted_applications, 0 as total_earnings, 0 as completed_jobs 
            FROM worker_profiles WHERE user_id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $worker_profile = $stmt->get_result()->fetch_assoc();
    } else {
        $worker_profile = $result->fetch_assoc();
    }
} catch (Exception $e) {
    error_log("Worker profile error: " . $e->getMessage());
    $worker_profile = ['total_skills' => 0, 'total_applications' => 0, 'accepted_applications' => 0, 'total_earnings' => 0, 'completed_jobs' => 0];
}

// Get detailed application statistics
$application_stats = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
            COUNT(CASE WHEN status = 'accepted' THEN 1 END) as accepted,
            COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
            COUNT(CASE WHEN applied_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as this_week,
            COUNT(CASE WHEN applied_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as this_month
        FROM job_applications 
        WHERE worker_id = ?
    ");
    $stmt->bind_param("i", $worker_profile['id']);
    $stmt->execute();
    $application_stats = $stmt->get_result()->fetch_assoc();
} catch (Exception $e) {
    error_log("Application stats error: " . $e->getMessage());
    $application_stats = ['total' => 0, 'pending' => 0, 'accepted' => 0, 'rejected' => 0, 'this_week' => 0, 'this_month' => 0];
}

// Get smart job recommendations
$recommended_jobs = null;
try {
    $stmt = $conn->prepare("
        SELECT 
            jp.*,
            sc.name as category_name,
            s.service_name,
            u.name as client_name,
            u.address as client_location,
            (SELECT COUNT(*) FROM job_applications WHERE job_id = jp.id) as application_count,
            (SELECT COUNT(*) FROM job_applications WHERE job_id = jp.id AND worker_id = ?) as already_applied,
            CASE WHEN ws.service_id IS NOT NULL THEN 1 ELSE 0 END as skill_match
        FROM job_postings jp
        INNER JOIN services s ON jp.service_id = s.id
        INNER JOIN service_categories sc ON s.category_id = sc.id
        INNER JOIN users u ON jp.client_id = u.id
        LEFT JOIN worker_skills ws ON s.id = ws.service_id AND ws.worker_id = ?
        WHERE jp.status = 'open' 
        AND jp.id NOT IN (SELECT job_id FROM job_applications WHERE worker_id = ?)
        ORDER BY 
            skill_match DESC,
            CASE jp.urgency WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 END,
            jp.created_at DESC
        LIMIT 8
    ");
    $stmt->bind_param("iii", $worker_profile['id'], $worker_profile['id'], $worker_profile['id']);
    $stmt->execute();
    $recommended_jobs = $stmt->get_result();
} catch (Exception $e) {
    error_log("Recommended jobs error: " . $e->getMessage());
}

// Get recent applications with status updates
$recent_applications = null;
try {
    $stmt = $conn->prepare("
        SELECT 
            ja.*,
            jp.title as job_title,
            jp.status as job_status,
            u.name as client_name,
            sc.name as category_name
        FROM job_applications ja
        INNER JOIN job_postings jp ON ja.job_id = jp.id
        INNER JOIN services s ON jp.service_id = s.id
        INNER JOIN service_categories sc ON s.category_id = sc.id
        INNER JOIN users u ON jp.client_id = u.id
        WHERE ja.worker_id = ?
        ORDER BY ja.applied_at DESC
        LIMIT 10
    ");
    $stmt->bind_param("i", $worker_profile['id']);
    $stmt->execute();
    $recent_applications = $stmt->get_result();
} catch (Exception $e) {
    error_log("Recent applications error: " . $e->getMessage());
}

// Get profile completion percentage
$profile_completion = 0;
$completion_tasks = [];
try {
    // Check various profile components
    $tasks = [
        'basic_info' => !empty($worker_profile['bio']) ? 20 : 0,
        'skills' => $worker_profile['total_skills'] > 0 ? 20 : 0,
        'experience' => $worker_profile['experience_years'] > 0 ? 15 : 0,
        'rates' => $worker_profile['hourly_rate_min'] > 0 ? 15 : 0,
        'photo' => !empty($_SESSION['profile_image']) ? 10 : 0,
        'applications' => $application_stats['total'] > 0 ? 10 : 0,
        'availability' => $worker_profile['is_available'] ? 10 : 0
    ];
    
    $completion_tasks = [
        'basic_info' => ['complete' => $tasks['basic_info'] > 0, 'text' => 'Add bio and description'],
        'skills' => ['complete' => $tasks['skills'] > 0, 'text' => 'Add your skills'],
        'experience' => ['complete' => $tasks['experience'] > 0, 'text' => 'Set experience years'],
        'rates' => ['complete' => $tasks['rates'] > 0, 'text' => 'Set hourly rates'],
        'photo' => ['complete' => $tasks['photo'] > 0, 'text' => 'Upload profile photo'],
        'applications' => ['complete' => $tasks['applications'] > 0, 'text' => 'Apply to jobs'],
        'availability' => ['complete' => $tasks['availability'] > 0, 'text' => 'Set availability status']
    ];
    
    $profile_completion = array_sum($tasks);
} catch (Exception $e) {
    error_log("Profile completion error: " . $e->getMessage());
}

// Get performance metrics
$performance_metrics = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            ROUND(AVG(CASE WHEN ja.status = 'accepted' THEN 100 ELSE 0 END), 1) as success_rate,
            COUNT(DISTINCT jp.client_id) as unique_clients,
            AVG(ja.proposed_rate) as avg_proposal_rate,
            DATEDIFF(NOW(), MIN(ja.applied_at)) as days_active
        FROM job_applications ja
        INNER JOIN job_postings jp ON ja.job_id = jp.id
        WHERE ja.worker_id = ? AND ja.applied_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    ");
    $stmt->bind_param("i", $worker_profile['id']);
    $stmt->execute();
    $performance_metrics = $stmt->get_result()->fetch_assoc();
} catch (Exception $e) {
    error_log("Performance metrics error: " . $e->getMessage());
    $performance_metrics = ['success_rate' => 0, 'unique_clients' => 0, 'avg_proposal_rate' => 0, 'days_active' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Worker Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    
    <style>
        .completion-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: conic-gradient(#28a745 0deg, #28a745 <?php echo ($profile_completion * 3.6); ?>deg, #e9ecef <?php echo ($profile_completion * 3.6); ?>deg);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        
        .completion-circle::before {
            content: '';
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: white;
            position: absolute;
        }
        
        .completion-text {
            position: relative;
            z-index: 1;
            font-weight: bold;
            color: #28a745;
        }
        
        .metric-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .metric-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .job-card {
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
        }
        
        .job-card.skill-match {
            border-left-color: #28a745;
            background: linear-gradient(135deg, #f8f9fa 0%, #e8f5e8 100%);
        }
        
        .job-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        
        .urgency-urgent { border-left-color: #dc3545 !important; }
        .urgency-high { border-left-color: #ffc107 !important; }
        .urgency-medium { border-left-color: #0dcaf0 !important; }
        .urgency-low { border-left-color: #6c757d !important; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-tools me-2"></i><?php echo APP_NAME; ?> Worker
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="browse_jobs.php">
                            <i class="fas fa-search me-1"></i>Find Jobs
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my_applications.php">
                            <i class="fas fa-file-alt me-1"></i>My Applications
                            <?php if ($application_stats['pending'] > 0): ?>
                                <span class="badge bg-warning ms-1"><?php echo $application_stats['pending']; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="fas fa-user me-1"></i>My Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="salary_predictor.php">
                            <i class="fas fa-chart-line me-1"></i>Salary Predictor
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="messages.php">
                            <i class="fas fa-envelope me-1"></i>Messages
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <!-- Availability Toggle -->
                    <li class="nav-item me-3">
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" id="availabilitySwitch" 
                                   <?php echo $worker_profile['is_available'] ? 'checked' : ''; ?>>
                            <label class="form-check-label text-white" for="availabilitySwitch">
                                Available for work
                            </label>
                        </div>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($user_name); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">
                                <i class="fas fa-user me-2"></i>Profile
                            </a></li>
                            <li><a class="dropdown-item" href="settings.php">
                                <i class="fas fa-cog me-2"></i>Settings
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-fluid mt-4">
        <!-- Welcome Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h2>Welcome back, <?php echo htmlspecialchars($user_name); ?>!</h2>
                                <p class="mb-0">Ready to find your next opportunity? Let's get you connected with great clients.</p>
                                <?php if ($profile_completion < 80): ?>
                                    <div class="mt-2">
                                        <small class="opacity-75">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            Complete your profile to get better job matches
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <div class="d-grid gap-2 d-md-block">
                                    <a href="browse_jobs.php" class="btn btn-light btn-lg">
                                        <i class="fas fa-search me-2"></i>Find Jobs
                                    </a>
                                    <a href="profile.php" class="btn btn-outline-light btn-lg">
                                        <i class="fas fa-user me-2"></i>Complete Profile
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Row -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card metric-card h-100">
                    <div class="card-body text-center">
                        <div class="display-4 text-primary mb-2">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h3 class="text-primary"><?php echo $application_stats['total']; ?></h3>
                        <p class="text-muted mb-0">Total Applications</p>
                        <small class="text-success">+<?php echo $application_stats['this_week']; ?> this week</small>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card metric-card h-100">
                    <div class="card-body text-center">
                        <div class="display-4 text-warning mb-2">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3 class="text-warning"><?php echo $application_stats['pending']; ?></h3>
                        <p class="text-muted mb-0">Pending Applications</p>
                        <small class="text-info"><?php echo round(($application_stats['pending'] / max($application_stats['total'], 1)) * 100); ?>% of total</small>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card metric-card h-100">
                    <div class="card-body text-center">
                        <div class="display-4 text-success mb-2">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3 class="text-success"><?php echo $worker_profile['completed_jobs']; ?></h3>
                        <p class="text-muted mb-0">Jobs Completed</p>
                        <small class="text-muted"><?php echo number_format($worker_profile['average_rating'], 1); ?>★ avg rating</small>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card metric-card h-100">
                    <div class="card-body text-center">
                        <div class="display-4 text-info mb-2">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <h3 class="text-info"><?php echo Functions::formatMoney($worker_profile['total_earnings']); ?></h3>
                        <p class="text-muted mb-0">Total Earnings</p>
                        <small class="text-muted"><?php echo Functions::formatMoney($performance_metrics['avg_proposal_rate'] ?? 0); ?> avg/job</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Main Content Column -->
            <div class="col-lg-8">
                <!-- Smart Job Recommendations -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-magic text-success me-2"></i>Job Recommendations
                        </h5>
                    
                    </div>
                    <div class="card-body">
                        <?php if ($recommended_jobs && $recommended_jobs->num_rows > 0): ?>
                            <div class="row">
                                <?php while ($job = $recommended_jobs->fetch_assoc()): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card job-card h-100 <?php echo $job['skill_match'] ? 'skill-match' : ''; ?> urgency-<?php echo $job['urgency']; ?>">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="card-title mb-0"><?php echo htmlspecialchars($job['title']); ?></h6>
                                                    <?php if ($job['skill_match']): ?>
                                                        <span class="badge bg-success">Perfect Match</span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <p class="card-text text-muted small">
                                                    <?php echo Functions::truncateText($job['description'], 80); ?>
                                                </p>
                                                
                                                <div class="mb-2">
                                                    <small class="text-muted">
                                                        <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($job['category_name']); ?>
                                                        •
                                                        <i class="fas fa-map-marker-alt me-1"></i><?php echo Functions::truncateText($job['client_location'], 20); ?>
                                                    </small>
                                                </div>
                                                
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <?php if ($job['budget_min'] && $job['budget_max']): ?>
                                                            <strong class="text-success">
                                                                <?php echo Functions::formatMoney($job['budget_min']); ?> - 
                                                                <?php echo Functions::formatMoney($job['budget_max']); ?>
                                                            </strong>
                                                        <?php else: ?>
                                                            <strong class="text-muted">Negotiable</strong>
                                                        <?php endif; ?>
                                                        <div>
                                                            <small class="text-muted">
                                                                <?php echo $job['application_count']; ?> applications
                                                                • <?php echo Functions::timeAgo($job['created_at']); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <button class="btn btn-success btn-sm" onclick="applyForJob(<?php echo $job['id']; ?>)">
                                                        <i class="fas fa-paper-plane me-1"></i>Apply
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                            <div class="text-center mt-3">
                                <a href="browse_jobs.php" class="btn btn-outline-success">
                                    <i class="fas fa-search me-2"></i>Browse All Jobs
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-briefcase fa-3x text-muted mb-3"></i>
                                <h6 class="text-muted">No job recommendations yet</h6>
                                <p class="text-muted">Add skills to your profile to get personalized job matches</p>
                                <a href="profile.php" class="btn btn-success">
                                    <i class="fas fa-user me-2"></i>Complete Profile
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Applications -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>Recent Applications
                        </h5>
                        <a href="my_applications.php" class="btn btn-outline-success btn-sm">
                            View All <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if ($recent_applications && $recent_applications->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Job</th>
                                            <th>Client</th>
                                            <th>Proposed Rate</th>
                                            <th>Status</th>
                                            <th>Applied</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($app = $recent_applications->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($app['job_title']); ?></h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($app['category_name']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($app['client_name']); ?></td>
                                                <td><?php echo Functions::formatMoney($app['proposed_rate']); ?></td>
                                                <td>
                                                    <?php
                                                    $status_colors = [
                                                        'pending' => 'warning',
                                                        'accepted' => 'success',
                                                        'rejected' => 'danger'
                                                    ];
                                                    $color = $status_colors[$app['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $color; ?>">
                                                        <?php echo ucfirst($app['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small><?php echo Functions::timeAgo($app['applied_at']); ?></small>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                onclick="viewApplication(<?php echo $app['id']; ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <?php if ($app['status'] === 'accepted'): ?>
                                                            <button class="btn btn-sm btn-success" 
                                                                    onclick="contactClient(<?php echo $app['job_id']; ?>)">
                                                                <i class="fas fa-comments"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                <h6 class="text-muted">No applications yet</h6>
                                <p class="text-muted">Start applying to jobs to build your track record</p>
                                <a href="browse_jobs.php" class="btn btn-success">
                                    <i class="fas fa-search me-2"></i>Find Jobs to Apply
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Profile Completion -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-user-check me-2"></i>Profile Completion
                        </h6>
                    </div>
                    <div class="card-body text-center">
                        <div class="completion-circle mx-auto mb-3">
                            <div class="completion-text">
                                <div class="h4 mb-0"><?php echo $profile_completion; ?>%</div>
                                <small>Complete</small>
                            </div>
                        </div>
                        
                        <div class="text-start">
                            <?php foreach ($completion_tasks as $task => $data): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-<?php echo $data['complete'] ? 'check-circle text-success' : 'circle text-muted'; ?> me-2"></i>
                                    <span class="<?php echo $data['complete'] ? 'text-success' : 'text-muted'; ?>"><?php echo $data['text']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if ($profile_completion < 100): ?>
                            <a href="profile.php" class="btn btn-success btn-sm mt-3">
                                <i class="fas fa-edit me-2"></i>Complete Profile
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Performance Metrics -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-chart-line me-2"></i>Performance Metrics
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <h5 class="text-primary"><?php echo $performance_metrics['success_rate'] ?? 0; ?>%</h5>
                                <small class="text-muted">Success Rate</small>
                            </div>
                            <div class="col-6 mb-3">
                                <h5 class="text-info"><?php echo $performance_metrics['unique_clients'] ?? 0; ?></h5>
                                <small class="text-muted">Unique Clients</small>
                            </div>
                            <div class="col-12">
                                <div class="progress mb-2" style="height: 8px;">
                                    <div class="progress-bar bg-success" 
                                         style="width: <?php echo min($performance_metrics['success_rate'] ?? 0, 100); ?>%"></div>
                                </div>
                                <small class="text-muted">
                                    <?php echo $performance_metrics['days_active'] ?? 0; ?> days active on platform
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-bolt me-2"></i>Quick Actions
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="browse_jobs.php" class="btn btn-success">
                                <i class="fas fa-search me-2"></i>Find New Jobs
                            </a>
                            <a href="my_applications.php" class="btn btn-outline-primary">
                                <i class="fas fa-file-alt me-2"></i>View Applications
                            </a>
                            <a href="profile.php" class="btn btn-outline-info">
                                <i class="fas fa-user me-2"></i>Edit Profile
                            </a>
                            <a href="salary_predictor.php" class="btn btn-outline-warning">
                                <i class="fas fa-chart-line me-2"></i>Predict Salary
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Job Application Modal -->
<div class="modal fade" id="applicationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Apply for Job</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="applicationForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                    <input type="hidden" name="job_id" id="applicationJobId">
                    
                    <div id="jobDetails" class="mb-4"></div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Proposed Rate (LKR) *</label>
                            <div class="input-group">
                                <span class="input-group-text">Rs.</span>
                                <input type="number" class="form-control" name="proposed_rate" required min="100" step="50">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Estimated Timeline</label>
                            <input type="text" class="form-control" name="proposed_timeline" 
                                   placeholder="e.g., 3 days, 1 week">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Cover Message *</label>
                        <textarea class="form-control" name="cover_message" rows="4" required
                                  placeholder="Explain why you're the right person for this job..." minlength="20"></textarea>
                        <div class="form-text">Minimum 20 characters</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-paper-plane me-2"></i>Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Application Details Modal -->
<div class="modal fade" id="applicationDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Application Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="applicationDetailsContent">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading application details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="contactClientFromModal" style="display: none;">
                    <i class="fas fa-comments me-2"></i>Contact Client
                </button>
            </div>
        </div>
    </div>
</div>



    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    
    <script>
// Availability toggle
$('#availabilitySwitch').change(function() {
    const available = $(this).is(':checked') ? 1 : 0;
    const $switch = $(this);
    
    makeAjaxRequest(
        '../processes/update_availability.php',
        {
            available: available,
            csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
        },
        function(response) {
            showAlert('success', response.message);
        },
        function(error) {
            // Revert switch on error
            $switch.prop('checked', !available);
            showAlert('danger', error);
        }
    );
});

    // Application form submission
// Application form submission
$('#applicationForm').on('submit', function(e) {
    e.preventDefault();
    
    const $form = $(this);
    const $button = $form.find('button[type="submit"]');
    const originalHtml = $button.html();
    
    // Show loading state
    $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Submitting...');
    
    const formData = $form.serialize();
    
    makeAjaxRequest(
        '../processes/apply_job.php',
        formData,
        function(response) {
            $('#applicationModal').modal('hide');
            showAlert('success', response.message);
            setTimeout(() => location.reload(), 1500);
        },
        function(error) {
            showAlert('danger', error);
        }
    ).always(function() {
        // Reset button state
        $button.prop('disabled', false).html(originalHtml);
    });
});


function applyForJob(jobId) {
    // Set job ID in the form
    $('#applicationJobId').val(jobId);
    
    // Get job details and show modal
    makeAjaxRequest(
        '../processes/get_job_details.php',
        {
            job_id: jobId,
            csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
        },
        function(response) {
            $('#jobDetails').html(response.job_html);
            $('#applicationModal').modal('show');
        },
        function(error) {
            showAlert('danger', 'Failed to load job details: ' + error);
        }
    );
}


function viewApplication(applicationId) {
    // Show modal and load content
    $('#applicationDetailsModal').modal('show');
    $('#applicationDetailsContent').html(`
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading application details...</p>
        </div>
    `);
    
    makeAjaxRequest(
        '../processes/get_application_details.php',
        {
            application_id: applicationId,
            csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
        },
        function(response) {
            $('#applicationDetailsContent').html(response.application_html);
            if (response.client_user_id) {
                $('#contactClientFromModal').show().attr('data-client-id', response.client_user_id);
            }
        },
        function(error) {
            $('#applicationDetailsContent').html(`
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Failed to load application details: ${error}
                </div>
            `);
        }
    );
}

function contactClient(jobId) {
    // Get client user ID for this job and redirect to messages
    makeAjaxRequest(
        '../processes/get_job_client.php',
        {
            job_id: jobId,
            csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
        },
        function(response) {
            if (response.client_user_id) {
                window.location.href = `messages.php?with=${response.client_user_id}`;
            } else {
                showAlert('error', 'Unable to contact client at this time.');
            }
        },
        function(error) {
            showAlert('error', 'Failed to get client information: ' + error);
        }
    );
}

// Handle contact client from modal
$('#contactClientFromModal').on('click', function() {
    const clientId = $(this).attr('data-client-id');
    if (clientId) {
        window.location.href = `messages.php?with=${clientId}`;
    }
});

</script>

</body>
</html>
