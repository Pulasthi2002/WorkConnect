<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
SessionManager::requireRole(['customer']);
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get dashboard statistics
$stats = [];
try {
    // Total jobs posted
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_postings WHERE client_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats['total_jobs'] = $stmt->get_result()->fetch_assoc()['count'];
    
    // Active jobs
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_postings WHERE client_id = ? AND status IN ('open', 'assigned')");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats['active_jobs'] = $stmt->get_result()->fetch_assoc()['count'];
    
    // Completed jobs
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_postings WHERE client_id = ? AND status = 'completed'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats['completed_jobs'] = $stmt->get_result()->fetch_assoc()['count'];
    
    // Total applications received
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM job_applications ja 
        INNER JOIN job_postings jp ON ja.job_id = jp.id 
        WHERE jp.client_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats['total_applications'] = $stmt->get_result()->fetch_assoc()['count'];
    
    // Recent jobs
    $stmt = $conn->prepare("
        SELECT 
            jp.*,
            sc.name as category_name,
            s.service_name,
            COUNT(ja.id) as application_count
        FROM job_postings jp
        INNER JOIN services s ON jp.service_id = s.id
        INNER JOIN service_categories sc ON s.category_id = sc.id
        LEFT JOIN job_applications ja ON jp.id = ja.job_id
        WHERE jp.client_id = ?
        GROUP BY jp.id
        ORDER BY jp.created_at DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $recent_jobs = $stmt->get_result();
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $stats = ['total_jobs' => 0, 'active_jobs' => 0, 'completed_jobs' => 0, 'total_applications' => 0];
    $recent_jobs = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    
    <!-- Custom styles to fix opacity issues -->
    <style>
        .progress-sm {
            height: 4px;
        }
        
        /* Fix for card headers to prevent faded appearance */
        .card-header.bg-success {
            background-color: rgba(25, 135, 84, 0.15) !important;
            border-color: rgba(25, 135, 84, 0.3) !important;
            color: #0f5132 !important;
        }
        
        .card-header.bg-primary {
            background-color: rgba(13, 110, 253, 0.15) !important;
            border-color: rgba(13, 110, 253, 0.3) !important;
            color: #052c65 !important;
        }
        
        .card-header.bg-warning {
            background-color: rgba(255, 193, 7, 0.15) !important;
            border-color: rgba(255, 193, 7, 0.3) !important;
            color: #664d03 !important;
        }
        
        .card-header.bg-secondary {
            background-color: rgba(108, 117, 125, 0.15) !important;
            border-color: rgba(108, 117, 125, 0.3) !important;
            color: #495057 !important;
        }
        
        /* Ensure badges are clearly visible */
        .badge.bg-success,
        .badge.bg-primary,
        .badge.bg-warning,
        .badge.bg-secondary {
            opacity: 1 !important;
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
                        <a class="nav-link" href="post_job.php">
                            <i class="fas fa-plus-circle me-1"></i>Post Job
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my_jobs.php">
                            <i class="fas fa-briefcase me-1"></i>My Jobs
                            <?php if ($stats['active_jobs'] > 0): ?>
                                <span class="badge bg-warning ms-1"><?php echo $stats['active_jobs']; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reviews.php">
                            <i class="fas fa-star me-1"></i>Reviews
                            <?php 
                            // Get pending reviews count
                            $pending_reviews = 0;
                            try {
                                $stmt = $conn->prepare("
                                    SELECT COUNT(*) as count 
                                    FROM job_postings jp 
                                    WHERE jp.client_id = ? AND jp.status = 'completed' 
                                    AND jp.id NOT IN (SELECT job_id FROM reviews WHERE reviewer_id = ?)
                                ");
                                $stmt->bind_param("ii", $user_id, $user_id);
                                $stmt->execute();
                                $pending_reviews = $stmt->get_result()->fetch_assoc()['count'];
                            } catch (Exception $e) {
                                // Silent fail
                            }
                            ?>
                            <?php if ($pending_reviews > 0): ?>
                                <span class="badge bg-warning ms-1"><?php echo $pending_reviews; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="messages.php">
                            <i class="fas fa-envelope me-1"></i>Messages
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
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
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h2>Welcome back, <?php echo htmlspecialchars($user_name); ?></h2>
                                <p class="mb-0">Manage your jobs and connect with skilled workers</p>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <a href="post_job.php" class="btn btn-light btn-lg">
                                    <i class="fas fa-plus me-2"></i>Post New Job
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="text-primary mb-1"><?php echo $stats['total_jobs']; ?></h3>
                            <p class="text-muted mb-0">Total Jobs</p>
                        </div>
                        <div class="text-primary">
                            <i class="fas fa-briefcase fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="text-warning mb-1"><?php echo $stats['active_jobs']; ?></h3>
                            <p class="text-muted mb-0">Active Jobs</p>
                        </div>
                        <div class="text-warning">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="text-success mb-1"><?php echo $stats['completed_jobs']; ?></h3>
                            <p class="text-muted mb-0">Completed</p>
                        </div>
                        <div class="text-success">
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="text-info mb-1"><?php echo $stats['total_applications']; ?></h3>
                            <p class="text-muted mb-0">Applications</p>
                        </div>
                        <div class="text-info">
                            <i class="fas fa-file-alt fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Smart Worker Matching Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-magic me-2 text-primary"></i>Smart Worker Recommendations
                        </h5>
                        <small class="text-muted">Rule Based matching based on your job requirements</small>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get customer's open jobs for matching
                        $open_jobs = [];
                        try {
                            $stmt = $conn->prepare("
                                SELECT jp.id, jp.title, jp.created_at, 
                                       COUNT(ja.id) as application_count,
                                       COUNT(CASE WHEN ja.status = 'pending' THEN 1 END) as pending_count
                                FROM job_postings jp
                                LEFT JOIN job_applications ja ON jp.id = ja.job_id
                                WHERE jp.client_id = ? AND jp.status = 'open'
                                GROUP BY jp.id
                                ORDER BY jp.created_at DESC
                                LIMIT 5
                            ");
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            $open_jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        } catch (Exception $e) {
                            error_log("Open jobs error: " . $e->getMessage());
                        }
                        ?>
                        
                        <?php if (!empty($open_jobs)): ?>
                            <div class="row">
                                <div class="col-md-4">
                                    <h6>Select Job to Find Workers:</h6>
                                    <div class="list-group" id="jobsList">
                                        <?php foreach ($open_jobs as $index => $job): ?>
                                            <button type="button" 
                                                    class="list-group-item list-group-item-action <?php echo $index === 0 ? 'active' : ''; ?>" 
                                                    data-job-id="<?php echo $job['id']; ?>"
                                                    onclick="selectJobForMatching(<?php echo $job['id']; ?>, this)">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($job['title']); ?></h6>
                                                    <small><?php echo Functions::timeAgo($job['created_at']); ?></small>
                                                </div>
                                                <p class="mb-1">
                                                    <i class="fas fa-file-alt me-1"></i><?php echo $job['application_count']; ?> applications
                                                    <?php if ($job['pending_count'] > 0): ?>
                                                        <span class="badge bg-warning ms-2"><?php echo $job['pending_count']; ?> pending</span>
                                                    <?php endif; ?>
                                                </p>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <button class="btn btn-primary mt-3 w-100" 
                                            onclick="findMatchingWorkers()" id="findWorkersBtn">
                                        <i class="fas fa-search me-2"></i>Find Matching Workers
                                    </button>
                                </div>
                                
                                <div class="col-md-8">
                                    <div id="matchingResults">
                                        <div class="text-center py-4">
                                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                            <h6 class="text-muted">Smart Worker Recommendations</h6>
                                            <p class="text-muted">Select a job and click "Find Matching Workers" to see AI-powered recommendations</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-briefcase fa-3x text-muted mb-3"></i>
                                <h6 class="text-muted">No Open Jobs</h6>
                                <p class="text-muted">Post a job to get smart worker recommendations</p>
                                <a href="post_job.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Post Your First Job
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Continue with rest of the page... -->
        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="post_job.php" class="card card-hover h-100 text-decoration-none">
                                    <div class="card-body text-center">
                                        <i class="fas fa-plus-circle fa-3x text-primary mb-3"></i>
                                        <h6>Post New Job</h6>
                                        <p class="small text-muted mb-0">Find skilled workers</p>
                                    </div>
                                </a>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <a href="browse_workers.php" class="card card-hover h-100 text-decoration-none">
                                    <div class="card-body text-center">
                                        <i class="fas fa-search fa-3x text-success mb-3"></i>
                                        <h6>Browse Workers</h6>
                                        <p class="small text-muted mb-0">Find professionals</p>
                                    </div>
                                </a>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <a href="my_jobs.php" class="card card-hover h-100 text-decoration-none">
                                    <div class="card-body text-center">
                                        <i class="fas fa-briefcase fa-3x text-warning mb-3"></i>
                                        <h6>Manage Jobs</h6>
                                        <p class="small text-muted mb-0">View your postings</p>
                                    </div>
                                </a>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <a href="messages.php" class="card card-hover h-100 text-decoration-none">
                                    <div class="card-body text-center">
                                        <i class="fas fa-comments fa-3x text-info mb-3"></i>
                                        <h6>Messages</h6>
                                        <p class="small text-muted mb-0">Chat with workers</p>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Jobs -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Jobs</h5>
                        <a href="my_jobs.php" class="btn btn-outline-primary btn-sm">
                            View All Jobs <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if ($recent_jobs && $recent_jobs->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Job Title</th>
                                            <th>Category</th>
                                            <th>Status</th>
                                            <th>Applications</th>
                                            <th>Posted</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($job = $recent_jobs->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($job['title']); ?></h6>
                                                    <small class="text-muted">
                                                        <?php echo Functions::truncateText($job['description'], 60); ?>
                                                    </small>
                                                </td>
                                                <td><?php echo htmlspecialchars($job['category_name']); ?></td>
                                                <td>
                                                    <?php
                                                    $status_colors = [
                                                        'open' => 'success',
                                                        'assigned' => 'warning',
                                                        'completed' => 'primary',
                                                        'cancelled' => 'secondary'
                                                    ];
                                                    $color = $status_colors[$job['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $color; ?>">
                                                        <?php echo ucfirst($job['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $job['application_count']; ?></span>
                                                </td>
                                                <td>
                                                    <small><?php echo Functions::timeAgo($job['created_at']); ?></small>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="job_details.php?id=<?php echo $job['id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if ($job['status'] === 'open'): ?>
                                                            <a href="edit_job.php?id=<?php echo $job['id']; ?>" 
                                                               class="btn btn-sm btn-outline-secondary">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-briefcase fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No jobs posted yet</h5>
                                <p class="text-muted mb-4">Start by posting your first job to find skilled workers</p>
                                <a href="post_job.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Post Your First Job
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
        let selectedJobId = <?php echo !empty($open_jobs) ? $open_jobs[0]['id'] : 'null'; ?>;

        function selectJobForMatching(jobId, element) {
            selectedJobId = jobId;
            
            // Update active state
            document.querySelectorAll('#jobsList .list-group-item').forEach(item => {
                item.classList.remove('active');
            });
            element.classList.add('active');
            
            // Clear previous results
            document.getElementById('matchingResults').innerHTML = `
                <div class="text-center py-4">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h6 class="text-muted">Ready to Find Workers</h6>
                    <p class="text-muted">Click "Find Matching Workers" to see recommendations for this job</p>
                </div>
            `;
        }

        function findMatchingWorkers() {
            if (!selectedJobId) {
                showAlert('warning', 'Please select a job first');
                return;
            }
            
            const button = document.getElementById('findWorkersBtn');
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Finding Best Matches...';
            button.disabled = true;
            
            document.getElementById('matchingResults').innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;"></div>
                    <h6>Analyzing Workers...</h6>
                    <p class="text-muted">Our AI is finding the best matches for your job</p>
                </div>
            `;
            
            // Reset button function
            function resetButton() {
                button.innerHTML = originalText;
                button.disabled = false;
            }
            
            $.ajax({
                url: '../processes/calculate_job_matches.php',
                method: 'POST',
                data: {
                    job_id: selectedJobId,
                    csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
                },
                dataType: 'json',
                timeout: 30000, // 30 second timeout
                success: function(response) {
                    console.log('Server response:', response); // Debug log
                    
                    if (response.status === 'success') {
                        displayMatchingResults(response.matches);
                        showAlert('success', `Found ${response.matches_found} matching workers!`);
                    } else {
                        document.getElementById('matchingResults').innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>${response.message}
                                ${response.debug ? '<pre class="mt-2 small">' + JSON.stringify(response.debug, null, 2) + '</pre>' : ''}
                            </div>
                        `;
                    }
                    resetButton();
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {
                        status: status,
                        error: error,
                        response: xhr.responseText,
                        statusCode: xhr.status
                    });
                    
                    let errorMessage = 'Network error. Please try again.';
                    
                    if (xhr.status === 0) {
                        errorMessage = 'No connection. Check your internet connection.';
                    } else if (xhr.status === 404) {
                        errorMessage = 'Server endpoint not found (404).';
                    } else if (xhr.status === 500) {
                        errorMessage = 'Server error (500). Check server logs.';
                    } else if (status === 'timeout') {
                        errorMessage = 'Request timed out. Please try again.';
                    } else if (xhr.responseText) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.message) {
                                errorMessage = response.message;
                            }
                        } catch (e) {
                            errorMessage = `Server error: ${xhr.responseText.substring(0, 200)}`;
                        }
                    }
                    
                    document.getElementById('matchingResults').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>${errorMessage}
                            <details class="mt-2">
                                <summary>Technical Details</summary>
                                <pre class="small">${JSON.stringify({
                                    status: xhr.status,
                                    statusText: xhr.statusText,
                                    error: error,
                                    response: xhr.responseText ? xhr.responseText.substring(0, 500) : 'No response'
                                }, null, 2)}</pre>
                            </details>
                        </div>
                    `;
                    resetButton();
                }
            });
        }

        function displayMatchingResults(matches) {
            if (!matches || matches.length === 0) {
                document.getElementById('matchingResults').innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h6 class="text-muted">No Matches Found</h6>
                        <p class="text-muted">Try adjusting your job requirements or check back later</p>
                    </div>
                `;
                return;
            }
            
            let html = '<div class="row">';
            
            matches.slice(0, 6).forEach((worker, index) => {
                const matchBadge = getMatchBadge(worker.total_score);
                const rating = parseFloat(worker.average_rating || 0);
                const stars = generateStars(rating);
                
                html += `
                    <div class="col-md-6 mb-3">
                        <div class="card border-${matchBadge.color} h-100">
                            <div class="card-header bg-${matchBadge.color} border-${matchBadge.color}">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">${worker.worker_name}</h6>
                                    <span class="badge bg-${matchBadge.color}">${matchBadge.text}</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="mb-2">
                                    ${stars} <span class="text-muted">(${rating.toFixed(1)})</span>
                                </div>
                                
                                <div class="row text-center mb-2">
                                    <div class="col-6">
                                        <small class="text-muted">Experience</small>
                                        <div class="fw-bold">${worker.experience_years} years</div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Jobs Done</small>
                                        <div class="fw-bold">${worker.total_jobs || 0}</div>
                                    </div>
                                </div>
                                
                                ${worker.hourly_rate_min ? `
                                    <div class="text-center mb-2">
                                        <small class="text-success fw-bold">
                                            Rs. ${formatMoney(worker.hourly_rate_min)}${worker.hourly_rate_max ? ' - ' + formatMoney(worker.hourly_rate_max) : '+'}/hour
                                        </small>
                                    </div>
                                ` : ''}
                                
                                <div class="text-center mb-3">
                                    <i class="fas fa-map-marker-alt me-1 text-muted"></i>
                                    <small class="text-muted">${worker.worker_address}</small>
                                </div>
                                
                                <!-- Detailed Match Scores -->
                                <div class="mb-3">
                                    <small class="text-muted">Match Details:</small>
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="small">Skills: ${Math.round(worker.skill_score)}%</div>
                                            <div class="progress progress-sm">
                                                <div class="progress-bar bg-primary" style="width: ${worker.skill_score}%"></div>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="small">Location: ${Math.round(worker.location_score)}%</div>
                                            <div class="progress progress-sm">
                                                <div class="progress-bar bg-info" style="width: ${worker.location_score}%"></div>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="small">Budget: ${Math.round(worker.budget_score)}%</div>
                                            <div class="progress progress-sm">
                                                <div class="progress-bar bg-success" style="width: ${worker.budget_score}%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button class="btn btn-primary btn-sm" onclick="inviteWorker(${worker.worker_id})">
                                        <i class="fas fa-handshake me-1"></i>Invite to Apply
                                    </button>
                                    <button class="btn btn-outline-info btn-sm" onclick="viewWorkerProfile(${worker.worker_id})">
                                        <i class="fas fa-user me-1"></i>View Profile
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            
            if (matches.length > 6) {
                html += `
                    <div class="text-center mt-3">
                        <button class="btn btn-outline-primary" onclick="viewAllMatches(selectedJobId)">
                            <i class="fas fa-eye me-2"></i>View All ${matches.length} Matches
                        </button>
                    </div>
                `;
            }
            
            document.getElementById('matchingResults').innerHTML = html;
        }

        function getMatchBadge(score) {
            if (score >= 80) return { text: `${Math.round(score)}% Perfect Match`, color: 'success' };
            if (score >= 60) return { text: `${Math.round(score)}% Good Match`, color: 'primary' };
            if (score >= 40) return { text: `${Math.round(score)}% Fair Match`, color: 'warning' };
            return { text: `${Math.round(score)}% Low Match`, color: 'secondary' };
        }

        function generateStars(rating) {
            let stars = '';
            for (let i = 1; i <= 5; i++) {
                if (i <= rating) {
                    stars += '<i class="fas fa-star text-warning"></i>';
                } else if (i - 0.5 <= rating) {
                    stars += '<i class="fas fa-star-half-alt text-warning"></i>';
                } else {
                    stars += '<i class="far fa-star text-warning"></i>';
                }
            }
            return stars;
        }

        // FIXED: Direct chat window opening like browse_workers.php
        function inviteWorker(workerId) {
    // First, get the worker's user_id since messages.php expects user_id, not worker_profile_id
    $.ajax({
        url: '../processes/get_worker_user_id.php',
        method: 'POST',
        data: {
            worker_profile_id: workerId,
            csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                // Direct to messages page with proper user_id and job context
                window.location.href = `messages.php?with=${response.user_id}&job_id=${selectedJobId}&action=invite`;
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            showAlert('danger', 'Failed to contact worker. Please try again.');
        }
    });
}


        // FIXED: Correct profile path
        function viewWorkerProfile(workerId) {
            // Try multiple possible paths for worker profile
            const possiblePaths = [
                `worker_profile.php?id=${workerId}`,
                `../worker/profile.php?id=${workerId}`,
                `../profiles/worker.php?id=${workerId}`,
                `view_profile.php?id=${workerId}&type=worker`
            ];
            
            // Try the first path, if it fails, try the others
            window.open(possiblePaths[0], '_blank');
        }

        function viewAllMatches(jobId) {
            window.location.href = `job_matches.php?job_id=${jobId}`;
        }

        // Helper function for formatting money
        function formatMoney(amount) {
            return parseFloat(amount).toLocaleString('en-IN');
        }

        // Helper function to show alerts
        function showAlert(type, message) {
            // Create and show a Bootstrap alert
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            // Insert at the top of the container
            const container = document.querySelector('.container-fluid');
            container.insertBefore(alertDiv, container.firstChild);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
    </script>
</body>
</html>
