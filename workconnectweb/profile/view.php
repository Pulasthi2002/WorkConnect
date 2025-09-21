<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Allow both logged in users and guests to view profiles
$viewer_id = $_SESSION['user_id'] ?? null;
$worker_id = intval($_GET['id'] ?? 0);

if ($worker_id <= 0) {
    header("Location: ../index.php");
    exit;
}

// Get worker profile with user details - CORRECTED QUERY
$worker = null;
try {
    $stmt = $conn->prepare("
        SELECT 
            u.id, u.name, u.email, u.address, u.profile_image, u.created_at,
            wp.bio, wp.experience_years, wp.hourly_rate_min, wp.hourly_rate_max,
            wp.is_available, wp.average_rating, wp.total_jobs
        FROM users u
        INNER JOIN worker_profiles wp ON u.id = wp.user_id
        WHERE u.id = ? AND u.role = 'worker' AND u.status = 'active'
    ");
    
    // Add error checking for prepare
    if ($stmt === false) {
        error_log("Prepare failed for worker query: " . $conn->error);
        throw new Exception('Database prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $worker_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header("Location: ../index.php?error=worker_not_found");
        exit;
    }
    
    $worker = $result->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    error_log("Worker profile error: " . $e->getMessage());
    header("Location: ../index.php");
    exit;
}

// Get worker skills - CORRECTED QUERY
$skills = [];
try {
    $stmt = $conn->prepare("
        SELECT s.service_name, sc.name as category_name
        FROM worker_skills ws
        INNER JOIN services s ON ws.service_id = s.id
        INNER JOIN service_categories sc ON s.category_id = sc.id
        INNER JOIN worker_profiles wp ON ws.worker_id = wp.id
        WHERE wp.user_id = ?
        ORDER BY sc.name, s.service_name
    ");
    
    if ($stmt === false) {
        error_log("Prepare failed for skills query: " . $conn->error);
    } else {
        $stmt->bind_param("i", $worker_id);
        $stmt->execute();
        $skills = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} catch (Exception $e) {
    error_log("Worker skills error: " . $e->getMessage());
}

// Get recent reviews - CORRECTED QUERY
$reviews = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            r.rating, r.review_text, r.created_at,
            u.name as client_name,
            jp.title as job_title
        FROM reviews r
        INNER JOIN job_postings jp ON r.job_id = jp.id
        INNER JOIN users u ON r.reviewer_id = u.id
        INNER JOIN worker_profiles wp ON r.reviewee_id = wp.user_id
        WHERE wp.user_id = ? AND r.reviewer_type = 'customer'
        ORDER BY r.created_at DESC
        LIMIT 10
    ");
    
    if ($stmt === false) {
        error_log("Prepare failed for reviews query: " . $conn->error);
    } else {
        $stmt->bind_param("i", $worker_id);
        $stmt->execute();
        $reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} catch (Exception $e) {
    error_log("Worker reviews error: " . $e->getMessage());
}

$page_title = htmlspecialchars($worker['name']) . " - Worker Profile";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-tools me-2"></i><?php echo APP_NAME; ?>
            </a>
            <div class="navbar-nav ms-auto">
                <?php if ($viewer_id): ?>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'customer'): ?>
                        <a class="nav-link" href="../customer/browse_workers.php">
                            <i class="fas fa-arrow-left me-1"></i>Back to Search
                        </a>
                    <?php else: ?>
                        <a class="nav-link" href="../<?php echo $_SESSION['role']; ?>/dashboard.php">
                            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <a class="nav-link" href="../index.php">
                        <i class="fas fa-arrow-left me-1"></i>Back to Home
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Worker Profile Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-2 text-center">
                                <?php if ($worker['profile_image']): ?>
                                    <img src="../<?php echo htmlspecialchars($worker['profile_image']); ?>" 
                                         class="rounded-circle" width="120" height="120" style="object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-white text-primary rounded-circle d-inline-flex align-items-center justify-content-center" 
                                         style="width: 120px; height: 120px; font-size: 2.5rem; font-weight: bold;">
                                        <?php echo strtoupper(substr($worker['name'], 0, 2)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-7">
                                <div class="d-flex align-items-center mb-2">
                                    <h2 class="me-3"><?php echo htmlspecialchars($worker['name']); ?></h2>
                                    <?php if ($worker['is_available']): ?>
                                        <span class="badge bg-success fs-6">Available</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary fs-6">Busy</span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($worker['average_rating'] > 0): ?>
                                    <div class="mb-2">
                                        <?php 
                                        $rating = floatval($worker['average_rating']);
                                        for ($i = 1; $i <= 5; $i++): ?>
                                            <?php if ($i <= $rating): ?>
                                                <i class="fas fa-star text-warning"></i>
                                            <?php elseif ($i - 0.5 <= $rating): ?>
                                                <i class="fas fa-star-half-alt text-warning"></i>
                                            <?php else: ?>
                                                <i class="far fa-star text-warning"></i>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        <span class="ms-2"><?php echo number_format($rating, 1); ?> 
                                        (<?php echo $worker['total_jobs']; ?> jobs completed)</span>
                                    </div>
                                <?php endif; ?>
                                
                                <p class="mb-1 opacity-75">
                                    <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($worker['address']); ?>
                                </p>
                                <p class="mb-0 opacity-75">
                                    <i class="fas fa-calendar me-1"></i>Member since <?php echo date('M Y', strtotime($worker['created_at'])); ?>
                                </p>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="mb-3">
                                    <?php if ($worker['hourly_rate_min'] || $worker['hourly_rate_max']): ?>
                                        <h4>
                                            <?php if ($worker['hourly_rate_min'] && $worker['hourly_rate_max']): ?>
                                                <?php echo Functions::formatMoney($worker['hourly_rate_min']); ?> - 
                                                <?php echo Functions::formatMoney($worker['hourly_rate_max']); ?>
                                            <?php elseif ($worker['hourly_rate_min']): ?>
                                                From <?php echo Functions::formatMoney($worker['hourly_rate_min']); ?>
                                            <?php endif; ?>
                                        </h4>
                                        <small>per hour</small>
                                    <?php else: ?>
                                        <h4>Rates Negotiable</h4>
                                        <small>Contact for pricing</small>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($viewer_id && isset($_SESSION['role']) && $_SESSION['role'] === 'customer'): ?>
                                    <div class="d-grid gap-2">
                                        <a href="../customer/messages.php?with=<?php echo $worker_id; ?>" class="btn btn-light">
                                            <i class="fas fa-envelope me-2"></i>Send Message
                                        </a>
                                        <button class="btn btn-outline-light" onclick="inviteWorker()">
                                            <i class="fas fa-handshake me-2"></i>Invite to Job
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8">
                <!-- About -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user me-2"></i>About</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($worker['bio']): ?>
                            <p><?php echo nl2br(htmlspecialchars($worker['bio'])); ?></p>
                        <?php else: ?>
                            <p class="text-muted">This worker hasn't added a bio yet.</p>
                        <?php endif; ?>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <strong>Experience:</strong> <?php echo $worker['experience_years']; ?> years
                            </div>
                            <div class="col-md-6">
                                <strong>Total Jobs:</strong> <?php echo $worker['total_jobs']; ?> completed
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Skills -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-tools me-2"></i>Skills & Services</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($skills)): ?>
                            <?php $current_category = ''; ?>
                            <?php foreach ($skills as $skill): ?>
                                <?php if ($skill['category_name'] !== $current_category): ?>
                                    <?php if ($current_category !== ''): ?></div><?php endif; ?>
                                    <div class="mb-3">
                                        <h6 class="text-primary"><?php echo htmlspecialchars($skill['category_name']); ?></h6>
                                    <?php $current_category = $skill['category_name']; ?>
                                <?php endif; ?>
                                <span class="skill-badge me-2 mb-2"><?php echo htmlspecialchars($skill['service_name']); ?></span>
                            <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No skills listed yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Reviews -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-star me-2"></i>Reviews</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($reviews)): ?>
                            <?php foreach ($reviews as $review): ?>
                                <div class="border-bottom pb-3 mb-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($review['client_name']); ?></h6>
                                            <div class="mb-2">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star text-warning<?php echo $i <= $review['rating'] ? '' : '-o'; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <p class="mb-1"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                                            <small class="text-muted">Job: <?php echo htmlspecialchars($review['job_title']); ?></small>
                                        </div>
                                        <small class="text-muted"><?php echo Functions::timeAgo($review['created_at']); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">No reviews yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Quick Stats -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Quick Stats</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <h4 class="text-primary"><?php echo $worker['total_jobs']; ?></h4>
                                <small>Jobs Completed</small>
                            </div>
                            <div class="col-6 mb-3">
                                <h4 class="text-success"><?php echo number_format($worker['average_rating'], 1); ?></h4>
                                <small>Average Rating</small>
                            </div>
                            <div class="col-6">
                                <h4 class="text-info"><?php echo $worker['experience_years']; ?></h4>
                                <small>Years Experience</small>
                            </div>
                            <div class="col-6">
                                <h4 class="text-warning"><?php echo count($reviews); ?></h4>
                                <small>Reviews</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Info (for customers) -->
                <?php if ($viewer_id && isset($_SESSION['role']) && $_SESSION['role'] === 'customer'): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-envelope me-2"></i>Contact</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="../customer/messages.php?with=<?php echo $worker_id; ?>" class="btn btn-primary">
                                    <i class="fas fa-comments me-2"></i>Start Conversation
                                </a>
                                <a href="mailto:<?php echo htmlspecialchars($worker['email']); ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-envelope me-2"></i>Send Email
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Report Profile -->
                <div class="card">
                    <div class="card-body text-center">
                        <button class="btn btn-outline-danger btn-sm" onclick="reportProfile()">
                            <i class="fas fa-flag me-1"></i>Report Profile
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    <script>
    function inviteWorker() {
        <?php if ($viewer_id): ?>
            // Redirect to post job page with pre-selected worker
            window.location.href = '../customer/post_job.php?invite_worker=<?php echo $worker_id; ?>';
        <?php else: ?>
            showAlert('info', 'Please login to invite workers to jobs');
            setTimeout(() => window.location.href = '../login.php', 2000);
        <?php endif; ?>
    }

    function reportProfile() {
        <?php if ($viewer_id): ?>
            const reason = prompt('Please specify the reason for reporting this profile:');
            if (reason && reason.trim()) {
                makeAjaxRequest(
                    '../processes/report_profile.php',
                    {
                        reported_user_id: <?php echo $worker_id; ?>,
                        reason: reason.trim(),
                        csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
                    },
                    function(response) {
                        showAlert('success', response.message);
                    },
                    function(error) {
                        showAlert('danger', error);
                    }
                );
            }
        <?php else: ?>
            showAlert('info', 'Please login to report profiles');
            setTimeout(() => window.location.href = '../login.php', 2000);
        <?php endif; ?>
    }
    </script>
</body>
</html>