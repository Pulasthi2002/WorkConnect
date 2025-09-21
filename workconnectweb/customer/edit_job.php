<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

SessionManager::requireRole(['customer']);

$user_id = $_SESSION['user_id'];
$job_id = intval($_GET['id'] ?? 0);

if ($job_id <= 0) {
    header("Location: my_jobs.php");
    exit;
}

// Get job details for editing
$job = null;
try {
    $stmt = $conn->prepare("
        SELECT jp.*, sc.id as category_id
        FROM job_postings jp
        INNER JOIN services s ON jp.service_id = s.id
        INNER JOIN service_categories sc ON s.category_id = sc.id
        WHERE jp.id = ? AND jp.client_id = ?
    ");
    $stmt->bind_param("ii", $job_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header("Location: my_jobs.php?error=job_not_found");
        exit;
    }
    
    $job = $result->fetch_assoc();
    
    // Check if job can be edited
    if ($job['status'] !== 'open') {
        header("Location: job_details.php?id=" . $job_id . "&error=cannot_edit");
        exit;
    }
} catch (Exception $e) {
    error_log("Edit job error: " . $e->getMessage());
    header("Location: my_jobs.php");
    exit;
}

// Get service categories
$categories = [];
try {
    $result = $conn->query("SELECT * FROM service_categories WHERE is_active = 1 ORDER BY name");
    if ($result) {
        $categories = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    error_log("Categories fetch error: " . $e->getMessage());
}

// Get services for the current category
$services = [];
try {
    $stmt = $conn->prepare("SELECT id, service_name FROM services WHERE category_id = ? AND is_active = 1 ORDER BY service_name");
    $stmt->bind_param("i", $job['category_id']);
    $stmt->execute();
    $services = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Services fetch error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Job - <?php echo APP_NAME; ?></title>
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
                <a class="nav-link" href="job_details.php?id=<?php echo $job_id; ?>">
                    <i class="fas fa-arrow-left me-1"></i>Back to Job Details
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h4 class="mb-0">
                            <i class="fas fa-edit me-2"></i>Edit Job
                        </h4>
                        <p class="mb-0">Update your job details and requirements</p>
                    </div>
                    <div class="card-body">
                        <div id="editAlert"></div>
                        
                        <form id="editJobForm" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                            <input type="hidden" name="job_id" value="<?php echo $job_id; ?>">
                            
                            <!-- Job Title -->
                            <div class="mb-3">
                                <label for="title" class="form-label">Job Title *</label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo htmlspecialchars($job['title']); ?>"
                                       required maxlength="200">
                                <div class="form-text">Be specific about what you need done</div>
                                <div class="invalid-feedback">Please provide a clear job title.</div>
                            </div>

                            <!-- Service Category -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="categoryId" class="form-label">Service Category *</label>
                                    <select class="form-select" id="categoryId" name="category_id" required>
                                        <option value="">Select category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" 
                                                    <?php echo $category['id'] == $job['category_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a service category.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="serviceId" class="form-label">Specific Service *</label>
                                    <select class="form-select" id="serviceId" name="service_id" required>
                                        <?php foreach ($services as $service): ?>
                                            <option value="<?php echo $service['id']; ?>" 
                                                    <?php echo $service['id'] == $job['service_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($service['service_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a specific service.</div>
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="mb-3">
                                <label for="description" class="form-label">Job Description *</label>
                                <textarea class="form-control" id="description" name="description" rows="5" 
                                          required maxlength="1000"><?php echo htmlspecialchars($job['description']); ?></textarea>
                                <div class="form-text">
                                    <span id="charCount"><?php echo strlen($job['description']); ?></span>/1000 characters
                                </div>
                                <div class="invalid-feedback">Please provide a detailed description.</div>
                            </div>

                            <!-- Location -->
                            <div class="mb-3">
                                <label for="location" class="form-label">Location *</label>
                                <input type="text" class="form-control" id="location" name="location_address" 
                                       value="<?php echo htmlspecialchars($job['location_address']); ?>"
                                       required maxlength="255">
                                <div class="invalid-feedback">Please provide your location.</div>
                            </div>

                            <!-- Budget Type -->
                            <div class="mb-3">
                                <label class="form-label">Budget Type *</label>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="budget_type" 
                                                   id="fixed" value="fixed" 
                                                   <?php echo $job['budget_type'] === 'fixed' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="fixed">
                                                <strong>Fixed Price</strong><br>
                                                <small class="text-muted">One-time payment</small>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="budget_type" 
                                                   id="hourly" value="hourly"
                                                   <?php echo $job['budget_type'] === 'hourly' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="hourly">
                                                <strong>Hourly Rate</strong><br>
                                                <small class="text-muted">Pay per hour</small>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="budget_type" 
                                                   id="negotiable" value="negotiable"
                                                   <?php echo $job['budget_type'] === 'negotiable' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="negotiable">
                                                <strong>Negotiable</strong><br>
                                                <small class="text-muted">Discuss with worker</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Budget Range -->
                            <div class="row mb-3" id="budgetRange" style="<?php echo $job['budget_type'] === 'negotiable' ? 'display:none;' : ''; ?>">
                                <div class="col-md-6">
                                    <label for="budgetMin" class="form-label">Minimum Budget (LKR)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rs.</span>
                                        <input type="number" class="form-control" id="budgetMin" name="budget_min" 
                                               value="<?php echo $job['budget_min']; ?>" min="0" step="100">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="budgetMax" class="form-label">Maximum Budget (LKR)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rs.</span>
                                        <input type="number" class="form-control" id="budgetMax" name="budget_max" 
                                               value="<?php echo $job['budget_max']; ?>" min="0" step="100">
                                    </div>
                                </div>
                            </div>

                            <!-- Urgency -->
                            <div class="mb-3">
                                <label class="form-label">How urgent is this job?</label>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="urgency" 
                                                   id="low" value="low" <?php echo $job['urgency'] === 'low' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="low">
                                                <span class="badge bg-success">Low</span> Within weeks
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="urgency" 
                                                   id="medium" value="medium" <?php echo $job['urgency'] === 'medium' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="medium">
                                                <span class="badge bg-primary">Medium</span> Within days
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="urgency" 
                                                   id="high" value="high" <?php echo $job['urgency'] === 'high' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="high">
                                                <span class="badge bg-warning">High</span> Within hours
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="urgency" 
                                                   id="urgent" value="urgent" <?php echo $job['urgency'] === 'urgent' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="urgent">
                                                <span class="badge bg-danger">Urgent</span> ASAP
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="d-grid gap-2 d-md-flex">
                                <button type="submit" class="btn btn-success btn-lg me-md-2">
                                    <span id="submitText">
                                        <i class="fas fa-save me-2"></i>Update Job
                                    </span>
                                    <span id="submitSpinner" class="d-none">
                                        <i class="fas fa-spinner fa-spin me-2"></i>Updating...
                                    </span>
                                </button>
                                <a href="job_details.php?id=<?php echo $job_id; ?>" class="btn btn-outline-secondary btn-lg">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
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
        // Character counter
        $('#description').on('input', function() {
            $('#charCount').text($(this).val().length);
        });

        // Budget type change handler
        $('input[name="budget_type"]').change(function() {
            if ($(this).val() === 'negotiable') {
                $('#budgetRange').hide();
            } else {
                $('#budgetRange').show();
            }
        });

        // Category change handler
        $('#categoryId').change(function() {
            const categoryId = $(this).val();
            loadServices(categoryId);
        });

        // Form submission
        $('#editJobForm').on('submit', function(e) {
            e.preventDefault();
            
            if (!validateForm('#editJobForm')) {
                return;
            }

            // Show loading state
            $('#submitText').addClass('d-none');
            $('#submitSpinner').removeClass('d-none');
            $('button[type="submit"]').prop('disabled', true);

            makeAjaxRequest(
                '../processes/update_job.php',
                $(this).serialize(),
                function(response) {
                    showAlert('success', response.message, '#editAlert');
                    setTimeout(() => {
                        window.location.href = 'job_details.php?id=<?php echo $job_id; ?>';
                    }, 1500);
                },
                function(message) {
                    showAlert('danger', message, '#editAlert');
                    // Reset form state
                    $('#submitText').removeClass('d-none');
                    $('#submitSpinner').addClass('d-none');
                    $('button[type="submit"]').prop('disabled', false);
                }
            );
        });
    });

    function loadServices(categoryId) {
        if (!categoryId) {
            $('#serviceId').html('<option value="">First select a category</option>');
            return;
        }

        $('#serviceId').html('<option value="">Loading...</option>');

        makeAjaxRequest(
            '../processes/get_services.php',
            { category_id: categoryId, csrf_token: '<?php echo Security::generateCSRFToken(); ?>' },
            function(response) {
                $('#serviceId').html(response.options);
            },
            function(message) {
                $('#serviceId').html('<option value="">Error loading services</option>');
                showAlert('warning', message, '#editAlert');
            }
        );
    }
    </script>
</body>
</html>
