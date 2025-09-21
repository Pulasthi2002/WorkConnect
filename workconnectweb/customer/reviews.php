<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

SessionManager::requireRole(['customer']);

$user_id = $_SESSION['user_id'];

// Get customer's given reviews with job and worker details
$given_reviews = null;
try {
    $stmt = $conn->prepare("
        SELECT 
            r.*,
            jp.title as job_title,
            u.name as worker_name,
            wp.user_id as worker_user_id
        FROM reviews r
        INNER JOIN job_postings jp ON r.job_id = jp.id
        INNER JOIN users u ON r.reviewee_id = u.id
        INNER JOIN worker_profiles wp ON u.id = wp.user_id
        WHERE r.reviewer_id = ? AND r.reviewer_type = 'customer'
        ORDER BY r.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $given_reviews = $stmt->get_result();
} catch (Exception $e) {
    error_log("Customer reviews error: " . $e->getMessage());
}

// Get reviews received by customer (from workers)
$received_reviews = null;
try {
    $stmt = $conn->prepare("
        SELECT 
            r.*,
            jp.title as job_title,
            u.name as worker_name
        FROM reviews r
        INNER JOIN job_postings jp ON r.job_id = jp.id
        INNER JOIN users reviewer ON r.reviewer_id = reviewer.id
        INNER JOIN worker_profiles wp ON reviewer.id = wp.user_id
        INNER JOIN users u ON wp.user_id = u.id
        WHERE r.reviewee_id = ? AND r.reviewer_type = 'worker'
        ORDER BY r.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $received_reviews = $stmt->get_result();
} catch (Exception $e) {
    error_log("Received reviews error: " . $e->getMessage());
}

// Get review statistics
$stats = ['given' => 0, 'received' => 0, 'avg_given' => 0, 'avg_received' => 0];
try {
    // Given reviews stats
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count, ROUND(AVG(rating), 1) as avg_rating 
        FROM reviews 
        WHERE reviewer_id = ? AND reviewer_type = 'customer'
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stats['given'] = $result['count'];
    $stats['avg_given'] = $result['avg_rating'] ?: 0;
    
    // Received reviews stats
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count, ROUND(AVG(rating), 1) as avg_rating 
        FROM reviews 
        WHERE reviewee_id = ? AND reviewer_type = 'worker'
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stats['received'] = $result['count'];
    $stats['avg_received'] = $result['avg_rating'] ?: 0;
} catch (Exception $e) {
    error_log("Review stats error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reviews - <?php echo APP_NAME; ?></title>
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
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h2><i class="fas fa-star text-warning me-2"></i>My Reviews & Feedback</h2>
                <p class="text-muted">Manage your reviews and see feedback from workers</p>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-primary"><?php echo $stats['given']; ?></h3>
                        <p class="text-muted mb-0">Reviews Given</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-success"><?php echo $stats['received']; ?></h3>
                        <p class="text-muted mb-0">Reviews Received</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-warning"><?php echo $stats['avg_given']; ?></h3>
                        <p class="text-muted mb-0">Avg Rating Given</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-info"><?php echo $stats['avg_received']; ?></h3>
                        <p class="text-muted mb-0">My Avg Rating</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Review Tabs -->
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="reviewTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="given-tab" data-bs-toggle="tab" data-bs-target="#given" type="button" role="tab">
                            <i class="fas fa-edit me-2"></i>Reviews I Gave (<?php echo $stats['given']; ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="received-tab" data-bs-toggle="tab" data-bs-target="#received" type="button" role="tab">
                            <i class="fas fa-star me-2"></i>Reviews I Received (<?php echo $stats['received']; ?>)
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="reviewTabsContent">
                    <!-- Given Reviews Tab -->
                    <div class="tab-pane fade show active" id="given" role="tabpanel">
                        <?php if ($given_reviews && $given_reviews->num_rows > 0): ?>
                            <?php while ($review = $given_reviews->fetch_assoc()): ?>
                                <div class="card mb-3 border-left-primary">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="mb-0">
                                                        <a href="../profile/view.php?id=<?php echo $review['worker_user_id']; ?>" target="_blank">
                                                            <?php echo htmlspecialchars($review['worker_name']); ?>
                                                        </a>
                                                    </h6>
                                                    <small class="text-muted"><?php echo Functions::timeAgo($review['created_at']); ?></small>
                                                </div>
                                                <p class="text-muted small mb-2">
                                                    Job: <strong><?php echo htmlspecialchars($review['job_title']); ?></strong>
                                                </p>
                                                <div class="mb-2">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                                    <?php endfor; ?>
                                                    <span class="ms-2"><?php echo $review['rating']; ?>/5</span>
                                                </div>
                                                <?php if ($review['review_text']): ?>
                                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="row text-center">
                                                    <?php if ($review['quality_rating']): ?>
                                                        <div class="col-4">
                                                            <small class="text-muted d-block">Quality</small>
                                                            <strong><?php echo $review['quality_rating']; ?>/5</strong>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($review['timeliness_rating']): ?>
                                                        <div class="col-4">
                                                            <small class="text-muted d-block">Timeliness</small>
                                                            <strong><?php echo $review['timeliness_rating']; ?>/5</strong>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($review['communication_rating']): ?>
                                                        <div class="col-4">
                                                            <small class="text-muted d-block">Communication</small>
                                                            <strong><?php echo $review['communication_rating']; ?>/5</strong>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($review['recommend'] !== null): ?>
                                                    <div class="mt-2 text-center">
                                                        <?php if ($review['recommend']): ?>
                                                            <span class="badge bg-success">
                                                                <i class="fas fa-thumbs-up me-1"></i>Recommended
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">
                                                                <i class="fas fa-thumbs-down me-1"></i>Not Recommended
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-edit fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No reviews given yet</h5>
                                <p class="text-muted">Reviews will appear here after you rate workers</p>
                                <a href="my_jobs.php" class="btn btn-primary">
                                    <i class="fas fa-briefcase me-2"></i>View My Jobs
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Received Reviews Tab -->
                    <div class="tab-pane fade" id="received" role="tabpanel">
                        <?php if ($received_reviews && $received_reviews->num_rows > 0): ?>
                            <?php while ($review = $received_reviews->fetch_assoc()): ?>
                                <div class="card mb-3 border-left-success">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="mb-0">From: <?php echo htmlspecialchars($review['worker_name']); ?></h6>
                                                    <small class="text-muted"><?php echo Functions::timeAgo($review['created_at']); ?></small>
                                                </div>
                                                <p class="text-muted small mb-2">
                                                    Job: <strong><?php echo htmlspecialchars($review['job_title']); ?></strong>
                                                </p>
                                                <div class="mb-2">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                                    <?php endfor; ?>
                                                    <span class="ms-2"><?php echo $review['rating']; ?>/5</span>
                                                </div>
                                                <?php if ($review['review_text']): ?>
                                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="row text-center">
                                                    <?php if ($review['communication_rating']): ?>
                                                        <div class="col-4">
                                                            <small class="text-muted d-block">Communication</small>
                                                            <strong><?php echo $review['communication_rating']; ?>/5</strong>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($review['payment_rating']): ?>
                                                        <div class="col-4">
                                                            <small class="text-muted d-block">Payment</small>
                                                            <strong><?php echo $review['payment_rating']; ?>/5</strong>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($review['clarity_rating']): ?>
                                                        <div class="col-4">
                                                            <small class="text-muted d-block">Clarity</small>
                                                            <strong><?php echo $review['clarity_rating']; ?>/5</strong>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($review['recommend'] !== null): ?>
                                                    <div class="mt-2 text-center">
                                                        <?php if ($review['recommend']): ?>
                                                            <span class="badge bg-success">
                                                                <i class="fas fa-heart me-1"></i>Would Work Again
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">
                                                                <i class="fas fa-times me-1"></i>Would Not Work Again
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-star fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No reviews received yet</h5>
                                <p class="text-muted">Workers will rate you after completing jobs</p>
                                <a href="browse_workers.php" class="btn btn-success">
                                    <i class="fas fa-search me-2"></i>Find Workers
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
</body>
</html>