<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

SessionManager::requireRole(['customer']);

$user_id = $_SESSION['user_id'];

// Get job statistics
$stats = [];
try {
    $stmt = $conn->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
        SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM job_postings WHERE client_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
} catch (Exception $e) {
    $stats = ['total' => 0, 'open' => 0, 'assigned' => 0, 'completed' => 0];
}

// Get jobs with application counts
$jobs = null;
try {
    $stmt = $conn->prepare("
        SELECT 
            jp.*,
            sc.name as category_name,
            s.service_name,
            COUNT(ja.id) as application_count,
            MAX(ja.applied_at) as latest_application
        FROM job_postings jp
        INNER JOIN services s ON jp.service_id = s.id
        INNER JOIN service_categories sc ON s.category_id = sc.id
        LEFT JOIN job_applications ja ON jp.id = ja.job_id
        WHERE jp.client_id = ?
        GROUP BY jp.id
        ORDER BY jp.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $jobs = $stmt->get_result();
} catch (Exception $e) {
    error_log("My jobs error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Jobs - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .rating-stars {
    font-size: 2rem;
    color: #ddd;
    cursor: pointer;
    margin: 10px 0;
}

.rating-stars i.active {
    color: #ffc107;
}

.rating-stars i:hover {
    color: #ffc107;
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
                <h2><i class="fas fa-briefcase text-primary me-2"></i>My Jobs</h2>
                <p class="text-muted">Manage your job postings and applications</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="post_job.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Post New Job
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-primary"><?php echo $stats['total']; ?></h3>
                        <p class="text-muted mb-0">Total Jobs</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-success"><?php echo $stats['open']; ?></h3>
                        <p class="text-muted mb-0">Open</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-warning"><?php echo $stats['assigned']; ?></h3>
                        <p class="text-muted mb-0">In Progress</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-info"><?php echo $stats['completed']; ?></h3>
                        <p class="text-muted mb-0">Completed</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Jobs List -->
        <div class="card">
            <div class="card-body">
                <?php if ($jobs && $jobs->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Job Title</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Applications</th>
                                    <th>Budget</th>
                                    <th>Posted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($job = $jobs->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($job['title']); ?></h6>
                                            <small class="text-muted">
                                                <?php echo Functions::truncateText($job['description'], 80); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <?php echo htmlspecialchars($job['category_name']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $status_colors = [
                                                'open' => 'success',
                                                'assigned' => 'warning',
                                                'completed' => 'info',
                                                'cancelled' => 'secondary'
                                            ];
                                            $color = $status_colors[$job['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo ucfirst($job['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo $job['application_count']; ?></span>
                                            <?php if ($job['latest_application']): ?>
                                                <div class="small text-muted">
                                                    Latest: <?php echo Functions::timeAgo($job['latest_application']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($job['budget_min'] && $job['budget_max']): ?>
                                                <span class="text-success">
                                                    <?php echo Functions::formatMoney($job['budget_min']); ?> - 
                                                    <?php echo Functions::formatMoney($job['budget_max']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">Negotiable</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?php echo Functions::timeAgo($job['created_at']); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="job_details.php?id=<?php echo $job['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <?php if ($job['application_count'] > 0): ?>
                                                    <a href="job_applications.php?job_id=<?php echo $job['id']; ?>" 
                                                       class="btn btn-sm btn-outline-info" title="View Applications">
                                                        <i class="fas fa-file-alt"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <?php if ($job['status'] === 'open'): ?>
                                                    <a href="edit_job.php?id=<?php echo $job['id']; ?>" 
                                                       class="btn btn-sm btn-outline-secondary" title="Edit Job">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($job['status'] === 'completed' && $job['assigned_worker_id']): ?>
                                                    <button class="btn btn-sm btn-warning" onclick="rateWorker(<?php echo $job['id']; ?>, <?php echo $job['assigned_worker_id']; ?>)" title="Rate Worker">
                                                        <i class="fas fa-star"></i>
                                                    </button>
                                                <?php endif; ?>                                            
                                                <button class="btn btn-sm btn-outline-danger" 
                                                        onclick="confirmDelete(<?php echo $job['id']; ?>)" title="Delete Job">
                                                    <i class="fas fa-trash"></i>
                                                </button>
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
    <!-- Rating Modal -->
<div class="modal fade" id="ratingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rate & Review Worker</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="ratingForm">
                <div class="modal-body">
                    <input type="hidden" id="ratingJobId" name="job_id">
                    <input type="hidden" id="ratingWorkerId" name="worker_id">
                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Rating *</label>
                        <div class="rating-stars">
                            <i class="fas fa-star" data-rating="1"></i>
                            <i class="fas fa-star" data-rating="2"></i>
                            <i class="fas fa-star" data-rating="3"></i>
                            <i class="fas fa-star" data-rating="4"></i>
                            <i class="fas fa-star" data-rating="5"></i>
                        </div>
                        <input type="hidden" id="selectedRating" name="rating" required>
                        <div class="form-text">Click stars to rate (1-5 stars)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Review (Optional)</label>
                        <textarea class="form-control" name="review_text" rows="4" 
                                  placeholder="Share your experience working with this professional..." 
                                  maxlength="500"></textarea>
                        <div class="form-text">
                            <span id="reviewCharCount">0</span>/500 characters
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-star me-2"></i>Submit Review
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>






    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    <script>
    function confirmDelete(jobId) {
        if (confirm('Are you sure you want to delete this job? This action cannot be undone.')) {
            makeAjaxRequest(
                '../processes/delete_job.php',
                {
                    job_id: jobId,
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

    let selectedRating = 0;

$(document).ready(function() {
    // Rating stars functionality
    $('.rating-stars i').on('click', function() {
        selectedRating = $(this).data('rating');
        $('#selectedRating').val(selectedRating);
        updateStars(selectedRating);
    });

    $('.rating-stars i').on('mouseover', function() {
        const hoverRating = $(this).data('rating');
        updateStars(hoverRating);
    });

    $('.rating-stars').on('mouseleave', function() {
        updateStars(selectedRating);
    });

    // Character counter
    $('textarea[name="review_text"]').on('input', function() {
        $('#reviewCharCount').text($(this).val().length);
    });

    // Form submission
    $('#ratingForm').on('submit', function(e) {
        e.preventDefault();
        submitReview();
    });
});

function updateStars(rating) {
    $('.rating-stars i').removeClass('active');
    for (let i = 1; i <= rating; i++) {
        $('.rating-stars i[data-rating="' + i + '"]').addClass('active');
    }
}

function rateWorker(jobId, workerId) {
    $('#ratingJobId').val(jobId);
    $('#ratingWorkerId').val(workerId);
    selectedRating = 0;
    $('#selectedRating').val('');
    updateStars(0);
    $('textarea[name="review_text"]').val('');
    $('#reviewCharCount').text('0');
    $('#ratingModal').modal('show');
}

function submitReview() {
    if (selectedRating === 0) {
        showAlert('warning', 'Please select a rating');
        return;
    }

    makeAjaxRequest(
        '../processes/submit_review.php',
        $('#ratingForm').serialize(),
        function(response) {
            $('#ratingModal').modal('hide');
            showAlert('success', response.message);
            // Optionally reload the page to update the UI
            setTimeout(() => location.reload(), 1500);
        },
        function(error) {
            showAlert('danger', error);
        }
    );
}
    </script>
</body>
</html>