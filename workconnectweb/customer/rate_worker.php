<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

SessionManager::requireRole(['customer']);

$user_id = $_SESSION['user_id'];
$job_id = intval($_GET['job_id'] ?? 0);
$worker_user_id = intval($_GET['worker_id'] ?? 0);

if ($job_id <= 0 || $worker_user_id <= 0) {
    header("Location: my_jobs.php");
    exit;
}

// Verify job ownership and completion status
$job = null;
$worker_info = null;
try {
    $stmt = $conn->prepare("
        SELECT 
            jp.*,
            u.name as worker_name,
            wp.id as worker_profile_id,
            wp.user_id as worker_user_id,
            (SELECT COUNT(*) FROM reviews WHERE job_id = jp.id AND reviewer_id = ?) as already_reviewed
        FROM job_postings jp
        INNER JOIN worker_profiles wp ON jp.assigned_worker_id = wp.id
        INNER JOIN users u ON wp.user_id = u.id
        WHERE jp.id = ? AND jp.client_id = ? AND jp.status = 'completed'
    ");
    $stmt->bind_param("iii", $user_id, $job_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header("Location: my_jobs.php?error=invalid_job");
        exit;
    }
    
    $job = $result->fetch_assoc();
    
    if ($job['already_reviewed'] > 0) {
        header("Location: my_jobs.php?message=already_reviewed");
        exit;
    }
    
} catch (Exception $e) {
    error_log("Rate worker error: " . $e->getMessage());
    header("Location: my_jobs.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Worker - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .rating-stars {
            font-size: 2rem;
            cursor: pointer;
            color: #ddd;
        }
        .rating-stars .star.active,
        .rating-stars .star:hover {
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
                <a class="nav-link" href="my_jobs.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to My Jobs
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-star me-2"></i>Rate Worker Performance
                        </h4>
                    </div>
                    <div class="card-body">
                        <!-- Job Info -->
                        <div class="bg-light p-3 rounded mb-4">
                            <h5><?php echo htmlspecialchars($job['title']); ?></h5>
                            <p class="text-muted mb-2"><?php echo Functions::truncateText($job['description'], 150); ?></p>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Worker:</strong> <?php echo htmlspecialchars($job['worker_name']); ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Completed:</strong> <?php echo Functions::timeAgo($job['updated_at']); ?>
                                </div>
                            </div>
                        </div>

                        <div id="reviewAlert"></div>

                        <form id="reviewForm" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                            <input type="hidden" name="job_id" value="<?php echo $job_id; ?>">
                            <input type="hidden" name="reviewee_id" value="<?php echo $job['worker_user_id']; ?>">
                            <input type="hidden" name="reviewer_type" value="customer">
                            
                            <!-- Rating -->
                            <div class="mb-4">
                                <label class="form-label"><strong>Overall Rating *</strong></label>
                                <div class="rating-stars" id="ratingStars">
                                    <span class="star" data-rating="1"><i class="fas fa-star"></i></span>
                                    <span class="star" data-rating="2"><i class="fas fa-star"></i></span>
                                    <span class="star" data-rating="3"><i class="fas fa-star"></i></span>
                                    <span class="star" data-rating="4"><i class="fas fa-star"></i></span>
                                    <span class="star" data-rating="5"><i class="fas fa-star"></i></span>
                                </div>
                                <input type="hidden" name="rating" id="ratingValue" required>
                                <div class="form-text">Click on the stars to rate the worker's performance</div>
                                <div class="invalid-feedback">Please provide a rating</div>
                            </div>

                            <!-- Review Categories -->
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <label class="form-label">Quality of Work</label>
                                    <select class="form-select" name="quality_rating">
                                        <option value="">Not Specified</option>
                                        <option value="5">Excellent</option>
                                        <option value="4">Good</option>
                                        <option value="3">Average</option>
                                        <option value="2">Below Average</option>
                                        <option value="1">Poor</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Timeliness</label>
                                    <select class="form-select" name="timeliness_rating">
                                        <option value="">Not Specified</option>
                                        <option value="5">Very Punctual</option>
                                        <option value="4">On Time</option>
                                        <option value="3">Mostly On Time</option>
                                        <option value="2">Sometimes Late</option>
                                        <option value="1">Often Late</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Communication</label>
                                    <select class="form-select" name="communication_rating">
                                        <option value="">Not Specified</option>
                                        <option value="5">Excellent</option>
                                        <option value="4">Good</option>
                                        <option value="3">Average</option>
                                        <option value="2">Poor</option>
                                        <option value="1">Very Poor</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Written Review -->
                            <div class="mb-4">
                                <label for="reviewText" class="form-label">
                                    <strong>Write Your Review</strong>
                                </label>
                                <textarea class="form-control" id="reviewText" name="review_text" rows="5" 
                                          placeholder="Share your experience working with this professional. What did they do well? Any areas for improvement?"
                                          maxlength="1000"></textarea>
                                <div class="form-text">
                                    <span id="reviewCharCount">0</span>/1000 characters
                                </div>
                            </div>

                            <!-- Recommendation -->
                            <div class="mb-4">
                                <label class="form-label"><strong>Would you recommend this worker?</strong></label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="recommend" id="recommendYes" value="1">
                                    <label class="form-check-label" for="recommendYes">
                                        <i class="fas fa-thumbs-up text-success me-2"></i>Yes, I would recommend
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="recommend" id="recommendNo" value="0">
                                    <label class="form-check-label" for="recommendNo">
                                        <i class="fas fa-thumbs-down text-danger me-2"></i>No, I would not recommend
                                    </label>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="d-grid">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <span id="submitText">
                                        <i class="fas fa-star me-2"></i>Submit Review
                                    </span>
                                    <span id="submitSpinner" class="d-none">
                                        <i class="fas fa-spinner fa-spin me-2"></i>Submitting...
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    <script>
    $(document).ready(function() {
        let selectedRating = 0;

        // Character counter
        $('#reviewText').on('input', function() {
            $('#reviewCharCount').text($(this).val().length);
        });

        // Star rating interaction
        $('.star').on('click', function() {
            selectedRating = parseInt($(this).data('rating'));
            $('#ratingValue').val(selectedRating);
            updateStars(selectedRating);
        });

        $('.star').on('mouseenter', function() {
            const hoverRating = parseInt($(this).data('rating'));
            updateStars(hoverRating);
        });

        $('.rating-stars').on('mouseleave', function() {
            updateStars(selectedRating);
        });

        function updateStars(rating) {
            $('.star').each(function(index) {
                if (index < rating) {
                    $(this).addClass('active');
                } else {
                    $(this).removeClass('active');
                }
            });
        }

        // Form submission
        $('#reviewForm').on('submit', function(e) {
            e.preventDefault();
            
            if (selectedRating === 0) {
                showAlert('warning', 'Please select a rating before submitting.', '#reviewAlert');
                return;
            }
            
            if (!validateForm('#reviewForm')) {
                return;
            }

            // Show loading state
            $('#submitText').addClass('d-none');
            $('#submitSpinner').removeClass('d-none');
            $('button[type="submit"]').prop('disabled', true);

            makeAjaxRequest(
                '../processes/submit_review.php',
                $(this).serialize(),
                function(response) {
                    showAlert('success', response.message, '#reviewAlert');
                    setTimeout(() => {
                        window.location.href = 'my_jobs.php?review_submitted=1';
                    }, 1500);
                },
                function(message) {
                    showAlert('danger', message, '#reviewAlert');
                    // Reset form state
                    $('#submitText').removeClass('d-none');
                    $('#submitSpinner').addClass('d-none');
                    $('button[type="submit"]').prop('disabled', false);
                }
            );
        });
    });
    </script>
</body>
</html>
