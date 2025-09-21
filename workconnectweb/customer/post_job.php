<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

SessionManager::requireRole(['customer']);

$user_id = $_SESSION['user_id'];

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Job - <?php echo APP_NAME; ?></title>
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
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-plus-circle text-primary me-2"></i>Post a New Job
                        </h4>
                        <p class="text-muted mb-0">Describe your project and find the right worker</p>
                    </div>
                    <div class="card-body">
                        <div id="jobAlert"></div>
                        
                        <form id="postJobForm" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                            
                            <!-- Job Title -->
                            <div class="mb-3">
                                <label for="title" class="form-label">Job Title *</label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       placeholder="e.g., Fix leaking kitchen faucet" 
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
                                            <option value="<?php echo $category['id']; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a service category.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="serviceId" class="form-label">Specific Service *</label>
                                    <select class="form-select" id="serviceId" name="service_id" required>
                                        <option value="">First select a category</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a specific service.</div>
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="mb-3">
                                <label for="description" class="form-label">Job Description *</label>
                                <textarea class="form-control" id="description" name="description" rows="5" 
                                          placeholder="Describe the work needed, materials, timeline, etc." 
                                          required maxlength="1000"></textarea>
                                <div class="form-text">
                                    <span id="charCount">0</span>/1000 characters
                                </div>
                                <div class="invalid-feedback">Please provide a detailed description.</div>
                            </div>

                            <!-- Location -->
                            <div class="mb-3">
                                <label for="location" class="form-label">Location *</label>
                                <input type="text" class="form-control" id="location" name="location_address" 
                                       placeholder="Enter your address or area" required maxlength="255">
                                <div class="invalid-feedback">Please provide your location.</div>
                            </div>

                            <!-- Budget -->
                            <div class="mb-3">
                                <label class="form-label">Budget Type *</label>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="budget_type" 
                                                   id="fixed" value="fixed" checked>
                                            <label class="form-check-label" for="fixed">
                                                <strong>Fixed Price</strong><br>
                                                <small class="text-muted">One-time payment</small>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="budget_type" 
                                                   id="hourly" value="hourly">
                                            <label class="form-check-label" for="hourly">
                                                <strong>Hourly Rate</strong><br>
                                                <small class="text-muted">Pay per hour</small>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="budget_type" 
                                                   id="negotiable" value="negotiable">
                                            <label class="form-check-label" for="negotiable">
                                                <strong>Negotiable</strong><br>
                                                <small class="text-muted">Discuss with worker</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Budget Range -->
                            <div class="row mb-3" id="budgetRange">
                                <div class="col-md-6">
                                    <label for="budgetMin" class="form-label">Minimum Budget (LKR)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rs.</span>
                                        <input type="number" class="form-control" id="budgetMin" name="budget_min" 
                                               placeholder="5000" min="0" step="100">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="budgetMax" class="form-label">Maximum Budget (LKR)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rs.</span>
                                        <input type="number" class="form-control" id="budgetMax" name="budget_max" 
                                               placeholder="15000" min="0" step="100">
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
                                                   id="low" value="low">
                                            <label class="form-check-label" for="low">
                                                <span class="badge bg-success">Low</span> Within weeks
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="urgency" 
                                                   id="medium" value="medium" checked>
                                            <label class="form-check-label" for="medium">
                                                <span class="badge bg-primary">Medium</span> Within days
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="urgency" 
                                                   id="high" value="high">
                                            <label class="form-check-label" for="high">
                                                <span class="badge bg-warning">High</span> Within hours
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="urgency" 
                                                   id="urgent" value="urgent">
                                            <label class="form-check-label" for="urgent">
                                                <span class="badge bg-danger">Urgent</span> ASAP
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <span id="submitText">
                                        <i class="fas fa-paper-plane me-2"></i>Post Job
                                    </span>
                                    <span id="submitSpinner" class="d-none">
                                        <i class="fas fa-spinner fa-spin me-2"></i>Posting...
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
        $('#postJobForm').on('submit', function(e) {
            e.preventDefault();
            
            if (!validateForm('#postJobForm')) {
                return;
            }

            // Show loading state
            $('#submitText').addClass('d-none');
            $('#submitSpinner').removeClass('d-none');
            $('button[type="submit"]').prop('disabled', true);

            makeAjaxRequest(
                '../processes/process_job.php',
                $(this).serialize(),
                function(response) {
                    showAlert('success', response.message);
                    setTimeout(() => {
                        window.location.href = 'my_jobs.php';
                    }, 1500);
                },
                function(message) {
                    showAlert('danger', message);
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
                showAlert('warning', message);
            }
        );
    }
    </script>
</body>
</html>
