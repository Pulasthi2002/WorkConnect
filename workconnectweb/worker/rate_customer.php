<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

SessionManager::requireRole(['worker']);

$user_id = $_SESSION['user_id'];
$job_id = intval($_GET['job_id'] ?? 0);

if ($job_id <= 0) {
    header("Location: my_applications.php");
    exit;
}

// Get worker profile
$worker_profile = null;
try {
    $stmt = $conn->prepare("SELECT id FROM worker_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $worker_profile = $result->fetch_assoc();
    }
} catch (Exception $e) {
    header("Location: profile.php");
    exit;
}

// Verify job assignment and completion
$job = null;
try {
    $stmt = $conn->prepare("
        SELECT 
            jp.*,
            u.name as client_name,
            u.id as client_user_id,
            (SELECT COUNT(*) FROM reviews WHERE job_id = jp.id AND reviewer_id = ?) as already_reviewed
        FROM job_postings jp
        INNER JOIN users u ON jp.client_id = u.id
        WHERE jp.id = ? AND jp.assigned_worker_id = ? AND jp.status = 'completed'
    ");
    $stmt->bind_param("iii", $user_id, $job_id, $worker_profile['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header("Location: my_applications.php?error=invalid_job");
        exit;
    }
    
    $job = $result->fetch_assoc();
    
    if ($job['already_reviewed'] > 0) {
        header("Location: my_applications.php?message=already_reviewed");
        exit;
    }
    
} catch (Exception $e) {
    error_log("Rate customer error: " . $e->getMessage());
    header("Location: my_applications.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Customer - <?php echo APP_NAME; ?></title>
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
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-tools me-2"></i><?php echo APP_NAME; ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="my_applications.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to Applications
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-star me-2"></i>Rate Client Experience
                        </h4>
                    </div>
                    <div class="card-body">
                        <!-- Job Info -->
                        <div class="bg-light p-3 rounded mb-4">
                            <h5><?php echo htmlspecialchars($job['title']); ?></h5>
                            <p class="text-muted mb-2"><?php echo Functions::truncateText($job['description'], 150); ?></p>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Client:</strong> <?php echo htmlspecialchars($job['client_name']); ?>
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
                            <input type="hidden" name="reviewee_id" value="<?php echo $job['client_user_id']; ?>">
                            <input type="hidden" name="reviewer_type" value="worker">
                            
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
                                <div class="form-text">Rate your overall experience with this client</div>
                                <div class="invalid-feedback">Please provide a rating</div>
                            </div>

                            <!-- Review Categories -->
                            <div class="row mb-4">
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
                                <div class="col-md-4">
                                    <label class="form-label">Payment Promptness</label>
                                    <select class="form-select" name="payment_rating">
                                        <option value="">Not Specified</option>
                                        <option value="5">Very Prompt</option>
                                        <option value="4">On Time</option>
                                        <option value="3">Acceptable</option>
                                        <option value="2">Delayed</option>
                                        <option value="1">Very Delayed</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Clarity of Requirements</label>
                                    <select class="form-select" name="clarity_rating">
                                        <option value="">Not Specified</option>
                                        <option value="5">Very Clear</option>
                                        <option value="4">Clear</option>
                                        <option value="3">Somewhat Clear</option>
                                        <option value="2">Unclear</option>
                                        <option value="1">Very Unclear</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Written Review -->
                            <div class="mb-4">
                                <label for="reviewText" class="form-label">
                                    <strong>Write Your Review</strong>
                                </label>
                                <textarea class="form-control" id="reviewText" name="review_text" rows="5" 
                                          placeholder="Share your experience working with this client. Were they easy to work with? Clear communication? Fair payment?"
                                          maxlength="1000"></textarea>
                                <div class="form-text">
                                    <span id="reviewCharCount">0</span>/1000 characters
                                </div>
                            </div>

                            <!-- Recommendation -->
                            <div class="mb-4">
                                <label class="form-label"><strong>Would you work with this client again?</strong></label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="recommend" id="recommendYes" value="1">
                                    <label class="form-check-label" for="recommendYes">
                                        <i class="fas fa-thumbs-up text-success me-2"></i>Yes, I would work with them again
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="recommend" id="recommendNo" value="0">
                                    <label class="form-check-label" for="recommendNo">
                                        <i class="fas fa-thumbs-down text-danger me-2"></i>No, I would not work with them again
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

        // Star rating interaction (same as customer rating)
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
                        window.location.href = 'my_applications.php?review_submitted=1';
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