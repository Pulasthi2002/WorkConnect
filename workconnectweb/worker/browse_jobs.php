<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

SessionManager::requireRole(['worker']);

$user_id = $_SESSION['user_id'];

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
    error_log("Worker profile error: " . $e->getMessage());
}

// Get service categories for filtering
$categories = [];
try {
    $result = $conn->query("SELECT * FROM service_categories WHERE is_active = 1 ORDER BY name");
    if ($result) {
        $categories = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    error_log("Categories error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Jobs - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
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
                <h2><i class="fas fa-search text-success me-2"></i>Browse Jobs</h2>
                <p class="text-muted">Find great job opportunities that match your skills</p>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Filters</h6>
                    </div>
                    <div class="card-body">
                        <form id="searchForm">
                            <div class="mb-3">
                                <label class="form-label">Search Keywords</label>
                                <input type="text" class="form-control" id="keywords" 
                                       placeholder="Job title, description...">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" id="categoryFilter">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Location</label>
                                <input type="text" class="form-control" id="locationFilter" 
                                       placeholder="City, area...">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Urgency</label>
                                <select class="form-select" id="urgencyFilter">
                                    <option value="">Any Priority</option>
                                    <option value="urgent">Urgent</option>
                                    <option value="high">High Priority</option>
                                    <option value="medium">Medium Priority</option>
                                    <option value="low">Low Priority</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="matchingSkills">
                                    <label class="form-check-label" for="matchingSkills">
                                        Only show jobs matching my skills
                                    </label>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-search me-2"></i>Search Jobs
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <!-- Sort Options -->
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <span id="jobCount">Loading jobs...</span>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <label class="form-label mb-0 me-2">Sort by:</label>
                                <select class="form-select d-inline-block" id="sortBy" style="width: auto;">
                                    <option value="newest">Newest First</option>
                                    <option value="budget_high">Highest Budget</option>
                                    <option value="budget_low">Lowest Budget</option>
                                    <option value="urgency">Most Urgent</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Jobs List -->
                <div id="jobsList">
                    <div class="text-center py-5">
                        <div class="spinner-border text-success" role="status">
                            <span class="visually-hidden">Loading jobs...</span>
                        </div>
                        <p class="mt-3">Finding great job opportunities for you...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Job Application Modal -->
    <div class="modal fade" id="applyJobModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Apply for Job</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="jobApplicationForm">
                    <div class="modal-body">
                        <input type="hidden" id="applyJobId">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Proposed Rate (LKR) *</label>
                            <input type="number" class="form-control" name="proposed_rate" 
                                   placeholder="Your rate for this job" required min="0" step="50">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Timeline</label>
                            <input type="text" class="form-control" name="proposed_timeline" 
                                   placeholder="e.g., 2-3 days, 1 week">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Cover Message *</label>
                            <textarea class="form-control" name="cover_message" rows="4" 
                                      placeholder="Explain why you're the right person for this job..." 
                                      required maxlength="500"></textarea>
                            <div class="form-text">
                                <span id="coverCharCount">0</span>/500 characters
                            </div>
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

    <!-- Job Details Modal -->
<div class="modal fade" id="jobDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Job Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="jobDetailsContent">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading job details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="applyFromModal" style="display: none;">
                    <i class="fas fa-paper-plane me-2"></i>Apply for Job
                </button>
            </div>
        </div>
    </div>
</div>


    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    <script>
    $(document).ready(function() {
        loadJobs();

        // Search form submission
        $('#searchForm').on('submit', function(e) {
            e.preventDefault();
            loadJobs();
        });

        // Sort change
        $('#sortBy').on('change', function() {
            loadJobs();
        });

        // Character counter
        $('textarea[name="cover_message"]').on('input', function() {
            $('#coverCharCount').text($(this).val().length);
        });

        // Job application form
        $('#jobApplicationForm').on('submit', function(e) {
            e.preventDefault();
            submitApplication();
        });
    });

    function loadJobs() {
        const searchData = {
            keywords: $('#keywords').val(),
            category_id: $('#categoryFilter').val(),
            location: $('#locationFilter').val(),
            urgency: $('#urgencyFilter').val(),
            matching_skills: $('#matchingSkills').is(':checked') ? 1 : 0,
            sort_by: $('#sortBy').val(),
            csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
        };

        $('#jobsList').html('<div class="text-center py-5"><div class="spinner-border text-success"></div><p class="mt-3">Searching jobs...</p></div>');

        makeAjaxRequest(
            '../processes/search_jobs.php',
            searchData,
            function(response) {
                $('#jobsList').html(response.jobs_html);
                $('#jobCount').text(response.total + ' jobs found');
            },
            function(error) {
                $('#jobsList').html('<div class="alert alert-danger">' + error + '</div>');
            }
        );
    }

    function applyForJob(jobId) {
        $('#applyJobId').val(jobId);
        $('#applyJobModal').modal('show');
    }

    function submitApplication() {
        const formData = $('#jobApplicationForm').serialize() + '&job_id=' + $('#applyJobId').val();

        makeAjaxRequest(
            '../processes/apply_job.php',
            formData,
            function(response) {
                $('#applyJobModal').modal('hide');
                showAlert('success', response.message);
                loadJobs(); // Refresh jobs list
            },
            function(error) {
                showAlert('danger', error);
            }
        );
    }


    function viewJobDetails(jobId) {
    // Show modal and load content
    $('#jobDetailsModal').modal('show');
    $('#jobDetailsContent').html(`
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading job details...</p>
        </div>
    `);
    
    makeAjaxRequest(
        '../processes/get_job_details.php',
        {
            job_id: jobId,
            csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
        },
        function(response) {
            $('#jobDetailsContent').html(response.job_html);
            
            // Show apply button if not already applied
            if (response.job_data && !response.job_data.already_applied) {
                $('#applyFromModal').show().attr('data-job-id', jobId);
            } else {
                $('#applyFromModal').hide();
            }
        },
        function(error) {
            $('#jobDetailsContent').html(`
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Failed to load job details: ${error}
                </div>
            `);
        }
    );
}

// Handle apply from modal
$('#applyFromModal').on('click', function() {
    const jobId = $(this).attr('data-job-id');
    $('#jobDetailsModal').modal('hide');
    applyForJob(jobId);
});

    </script>
</body>
</html>
