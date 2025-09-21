<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

SessionManager::requireRole(['admin']);

// Get job statistics
$stats = [];
try {
    $result = $conn->query("SELECT COUNT(*) as total FROM job_postings");
    $stats['total_jobs'] = $result->fetch_assoc()['total'];
    
    $result = $conn->query("SELECT COUNT(*) as open FROM job_postings WHERE status = 'open'");
    $stats['open_jobs'] = $result->fetch_assoc()['open'];
    
    $result = $conn->query("SELECT COUNT(*) as completed FROM job_postings WHERE status = 'completed'");
    $stats['completed_jobs'] = $result->fetch_assoc()['completed'];
    
    $result = $conn->query("SELECT COUNT(*) as applications FROM job_applications");
    $stats['total_applications'] = $result->fetch_assoc()['applications'];
} catch (Exception $e) {
    $stats = ['total_jobs' => 0, 'open_jobs' => 0, 'completed_jobs' => 0, 'total_applications' => 0];
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

// Get services for job creation/editing
$services = [];
try {
    $result = $conn->query("
        SELECT s.*, sc.name as category_name 
        FROM services s 
        INNER JOIN service_categories sc ON s.category_id = sc.id 
        WHERE s.is_active = 1 
        ORDER BY sc.name, s.service_name
    ");
    if ($result) {
        $services = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    error_log("Services error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Jobs - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .job-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .job-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .action-buttons .btn {
            margin: 0 2px;
        }
        .modal-lg {
            max-width: 900px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-danger">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-tools me-2"></i><?php echo APP_NAME; ?> Admin
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h2><i class="fas fa-briefcase text-primary me-2"></i>Job Management</h2>
                <p class="text-muted">Complete CRUD operations for job postings</p>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createJobModal">
                    <i class="fas fa-plus me-2"></i>Create New Job
                </button>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-primary"><?php echo number_format($stats['total_jobs']); ?></h3>
                        <p class="text-muted mb-0">Total Jobs</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-success"><?php echo number_format($stats['open_jobs']); ?></h3>
                        <p class="text-muted mb-0">Open Jobs</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-info"><?php echo number_format($stats['completed_jobs']); ?></h3>
                        <p class="text-muted mb-0">Completed</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-warning"><?php echo number_format($stats['total_applications']); ?></h3>
                        <p class="text-muted mb-0">Applications</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Search Jobs</label>
                        <input type="text" class="form-control" id="searchJobs" placeholder="Job title, description...">
                    </div>
                    <div class="col-md-2 mb-3">
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
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="statusFilter">
                            <option value="">All Status</option>
                            <option value="open">Open</option>
                            <option value="assigned">Assigned</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="paused">Paused</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Urgency</label>
                        <select class="form-select" id="urgencyFilter">
                            <option value="">All Urgency</option>
                            <option value="urgent">Urgent</option>
                            <option value="high">High</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Per Page</label>
                        <select class="form-select" id="perPageFilter">
                            <option value="10">10 per page</option>
                            <option value="25" selected>25 per page</option>
                            <option value="50">50 per page</option>
                            <option value="100">100 per page</option>
                        </select>
                    </div>
                    <div class="col-md-1 mb-3">
                        <label class="form-label">&nbsp;</label>
                        <button class="btn btn-primary w-100" onclick="loadJobs()">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Jobs List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Jobs List</h5>
                <div>
                    <button class="btn btn-outline-secondary btn-sm me-2" onclick="toggleView('table')">
                        <i class="fas fa-table"></i> Table
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="toggleView('card')">
                        <i class="fas fa-th-large"></i> Cards
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- Table View -->
                <div id="tableView">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Job Details</th>
                                    <th>Client</th>
                                    <th>Category</th>
                                    <th>Budget</th>
                                    <th>Status</th>
                                    <th>Applications</th>
                                    <th>Posted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="jobsTableBody">
                                <tr>
                                    <td colspan="9" class="text-center">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading jobs...</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Card View -->
                <div id="cardView" style="display: none;">
                    <div id="jobsCardContainer" class="row">
                        <div class="col-12 text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading jobs...</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div id="paginationInfo">
                        Showing 0 - 0 of 0 jobs
                    </div>
                    <nav>
                        <ul class="pagination" id="pagination">
                            <!-- Pagination will be generated here -->
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Job Modal -->
    <div class="modal fade" id="createJobModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Job</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="createJobForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Job Title *</label>
                                <input type="text" class="form-control" name="title" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Urgency *</label>
                                <select class="form-select" name="urgency" required>
                                    <option value="low">Low Priority</option>
                                    <option value="medium" selected>Medium Priority</option>
                                    <option value="high">High Priority</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Service Category *</label>
                                <select class="form-select" id="createCategory" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Specific Service *</label>
                                <select class="form-select" id="createService" name="service_id" required>
                                    <option value="">First select a category</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Job Description *</label>
                            <textarea class="form-control" name="description" rows="4" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Location *</label>
                            <input type="text" class="form-control" name="location_address" required>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Budget Type</label>
                                <select class="form-select" name="budget_type" id="createBudgetType">
                                    <option value="negotiable">Negotiable</option>
                                    <option value="fixed">Fixed Budget</option>
                                    <option value="hourly">Hourly Rate</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3" id="createMinBudgetDiv" style="display: none;">
                                <label class="form-label">Min Budget (LKR)</label>
                                <input type="number" class="form-control" name="budget_min" min="0" step="50">
                            </div>
                            <div class="col-md-4 mb-3" id="createMaxBudgetDiv" style="display: none;">
                                <label class="form-label">Max Budget (LKR)</label>
                                <input type="number" class="form-control" name="budget_max" min="0" step="50">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Client *</label>
                                <select class="form-select" name="client_id" required>
                                    <option value="">Select Client</option>
                                    <?php
                                    try {
                                        $clients_result = $conn->query("SELECT id, name, email FROM users WHERE role = 'customer' AND status = 'active' ORDER BY name");
                                        while ($client = $clients_result->fetch_assoc()) {
                                            echo '<option value="' . $client['id'] . '">' . htmlspecialchars($client['name']) . ' (' . htmlspecialchars($client['email']) . ')</option>';
                                        }
                                    } catch (Exception $e) {
                                        echo '<option value="">Error loading clients</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="open">Open</option>
                                    <option value="paused">Paused</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus me-2"></i>Create Job
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Job Modal -->
    <div class="modal fade" id="editJobModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Job</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editJobForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                        <input type="hidden" name="job_id" id="editJobId">
                        
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Job Title *</label>
                                <input type="text" class="form-control" name="title" id="editTitle" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Urgency *</label>
                                <select class="form-select" name="urgency" id="editUrgency" required>
                                    <option value="low">Low Priority</option>
                                    <option value="medium">Medium Priority</option>
                                    <option value="high">High Priority</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Service Category *</label>
                                <select class="form-select" id="editCategory" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Specific Service *</label>
                                <select class="form-select" id="editService" name="service_id" required>
                                    <option value="">First select a category</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Job Description *</label>
                            <textarea class="form-control" name="description" id="editDescription" rows="4" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Location *</label>
                            <input type="text" class="form-control" name="location_address" id="editLocation" required>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Budget Type</label>
                                <select class="form-select" name="budget_type" id="editBudgetType">
                                    <option value="negotiable">Negotiable</option>
                                    <option value="fixed">Fixed Budget</option>
                                    <option value="hourly">Hourly Rate</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3" id="editMinBudgetDiv">
                                <label class="form-label">Min Budget (LKR)</label>
                                <input type="number" class="form-control" name="budget_min" id="editBudgetMin" min="0" step="50">
                            </div>
                            <div class="col-md-4 mb-3" id="editMaxBudgetDiv">
                                <label class="form-label">Max Budget (LKR)</label>
                                <input type="number" class="form-control" name="budget_max" id="editBudgetMax" min="0" step="50">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" id="editStatus">
                                    <option value="open">Open</option>
                                    <option value="assigned">Assigned</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                    <option value="paused">Paused</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Assigned Worker</label>
                                <select class="form-select" name="assigned_worker_id" id="editWorker">
                                    <option value="">No worker assigned</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Job
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Job Modal -->
    <div class="modal fade" id="viewJobModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Job Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewJobContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="editFromViewBtn">
                        <i class="fas fa-edit me-2"></i>Edit Job
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    <script>
    let currentPage = 1;
    let currentView = 'table';

    $(document).ready(function() {
        loadJobs();

        // Search handlers
        $('#searchJobs').on('keyup', debounce(function() {
            currentPage = 1;
            loadJobs();
        }, 500));
        
        $('#categoryFilter, #statusFilter, #urgencyFilter, #perPageFilter').on('change', function() {
            currentPage = 1;
            loadJobs();
        });

        // Budget type handlers
        $('#createBudgetType').on('change', function() {
            toggleBudgetFields('create', $(this).val());
        });

        $('#editBudgetType').on('change', function() {
            toggleBudgetFields('edit', $(this).val());
        });

        // Category handlers
        $('#createCategory').on('change', function() {
            loadServicesForCategory('create', $(this).val());
        });

        $('#editCategory').on('change', function() {
            loadServicesForCategory('edit', $(this).val());
        });

        // Form submissions
        $('#createJobForm').on('submit', function(e) {
            e.preventDefault();
            createJob();
        });

        $('#editJobForm').on('submit', function(e) {
            e.preventDefault();
            updateJob();
        });

        // Edit from view modal
        $('#editFromViewBtn').on('click', function() {
            const jobId = $(this).data('job-id');
            $('#viewJobModal').modal('hide');
            setTimeout(() => editJob(jobId), 300);
        });
    });

    function loadJobs() {
        const searchData = {
            search: $('#searchJobs').val(),
            category_id: $('#categoryFilter').val(),
            status: $('#statusFilter').val(),
            urgency: $('#urgencyFilter').val(),
            page: currentPage,
            per_page: $('#perPageFilter').val(),
            csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
        };

        if (currentView === 'table') {
            $('#jobsTableBody').html('<tr><td colspan="9" class="text-center"><div class="spinner-border text-primary"></div></td></tr>');
        } else {
            $('#jobsCardContainer').html('<div class="col-12 text-center"><div class="spinner-border text-primary"></div></div>');
        }

        makeAjaxRequest(
            'processes/get_admin_jobs.php',
            searchData,
            function(response) {
                if (currentView === 'table') {
                    $('#jobsTableBody').html(response.jobs_html);
                } else {
                    $('#jobsCardContainer').html(response.jobs_cards_html);
                }
                
                updatePagination(response.pagination);
                $('#paginationInfo').text(`Showing ${response.start} - ${response.end} of ${response.total} jobs`);
            },
            function(error) {
                const errorHtml = `<div class="col-12 text-center text-danger">${error}</div>`;
                if (currentView === 'table') {
                    $('#jobsTableBody').html(`<tr><td colspan="9" class="text-center text-danger">${error}</td></tr>`);
                } else {
                    $('#jobsCardContainer').html(errorHtml);
                }
            }
        );
    }

    function toggleView(view) {
        currentView = view;
        if (view === 'table') {
            $('#tableView').show();
            $('#cardView').hide();
        } else {
            $('#tableView').hide();
            $('#cardView').show();
        }
        loadJobs();
    }

    function toggleBudgetFields(prefix, budgetType) {
        const minDiv = $(`#${prefix}MinBudgetDiv`);
        const maxDiv = $(`#${prefix}MaxBudgetDiv`);
        
        if (budgetType === 'negotiable') {
            minDiv.hide();
            maxDiv.hide();
        } else {
            minDiv.show();
            maxDiv.show();
        }
    }

    function loadServicesForCategory(prefix, categoryId) {
        const serviceSelect = $(`#${prefix}Service`);
        
        if (!categoryId) {
            serviceSelect.html('<option value="">First select a category</option>');
            return;
        }

        serviceSelect.html('<option value="">Loading services...</option>');

        makeAjaxRequest(
            '../processes/get_services.php',
            { 
                category_id: categoryId, 
                csrf_token: '<?php echo Security::generateCSRFToken(); ?>' 
            },
            function(response) {
                serviceSelect.html(response.options);
            },
            function(error) {
                serviceSelect.html('<option value="">Error loading services</option>');
            }
        );
    }

    function createJob() {
        const $button = $('#createJobForm button[type="submit"]');
        const originalHtml = $button.html();
        
        $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Creating...');
        
        makeAjaxRequest(
            'processes/admin_create_job.php',
            $('#createJobForm').serialize(),
            function(response) {
                showAlert('success', response.message);
                $('#createJobModal').modal('hide');
                $('#createJobForm')[0].reset();
                toggleBudgetFields('create', 'negotiable');
                loadJobs();
            },
            function(error) {
                showAlert('danger', error);
            }
        ).always(function() {
            $button.prop('disabled', false).html(originalHtml);
        });
    }

    function viewJob(jobId) {
        $('#viewJobModal').modal('show');
        $('#editFromViewBtn').data('job-id', jobId);
        
        makeAjaxRequest(
            'processes/admin_view_job.php',
            { 
                job_id: jobId, 
                csrf_token: '<?php echo Security::generateCSRFToken(); ?>' 
            },
            function(response) {
                $('#viewJobContent').html(response.job_html);
            },
            function(error) {
                $('#viewJobContent').html(`<div class="alert alert-danger">${error}</div>`);
            }
        );
    }

    function editJob(jobId) {
        makeAjaxRequest(
            'processes/admin_get_job.php',
            { 
                job_id: jobId, 
                csrf_token: '<?php echo Security::generateCSRFToken(); ?>' 
            },
            function(response) {
                const job = response.job;
                
                $('#editJobId').val(job.id);
                $('#editTitle').val(job.title);
                $('#editDescription').val(job.description);
                $('#editLocation').val(job.location_address);
                $('#editUrgency').val(job.urgency);
                $('#editBudgetType').val(job.budget_type);
                $('#editBudgetMin').val(job.budget_min);
                $('#editBudgetMax').val(job.budget_max);
                $('#editStatus').val(job.status);
                
                // Load category and service
                $('#editCategory').val(job.category_id);
                loadServicesForCategory('edit', job.category_id);
                
                setTimeout(() => {
                    $('#editService').val(job.service_id);
                }, 500);
                
                toggleBudgetFields('edit', job.budget_type);
                
                // Load workers if job is assigned
                if (job.status === 'assigned' && job.assigned_worker_id) {
                    loadWorkersForJob(job.assigned_worker_id);
                }
                
                $('#editJobModal').modal('show');
            },
            function(error) {
                showAlert('danger', error);
            }
        );
    }

    function updateJob() {
        const $button = $('#editJobForm button[type="submit"]');
        const originalHtml = $button.html();
        
        $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Updating...');
        
        makeAjaxRequest(
            'processes/admin_update_job.php',
            $('#editJobForm').serialize(),
            function(response) {
                showAlert('success', response.message);
                $('#editJobModal').modal('hide');
                loadJobs();
            },
            function(error) {
                showAlert('danger', error);
            }
        ).always(function() {
            $button.prop('disabled', false).html(originalHtml);
        });
    }

    function deleteJob(jobId, jobTitle) {
        if (confirm(`Are you sure you want to permanently delete the job "${jobTitle}"? This action cannot be undone and will also delete all related applications.`)) {
            makeAjaxRequest(
                'processes/admin_delete_job.php',
                { 
                    job_id: jobId, 
                    csrf_token: '<?php echo Security::generateCSRFToken(); ?>' 
                },
                function(response) {
                    showAlert('success', response.message);
                    loadJobs();
                },
                function(error) {
                    showAlert('danger', error);
                }
            );
        }
    }

    function updateJobStatus(jobId, newStatus) {
        makeAjaxRequest(
            'processes/admin_update_job_status.php',
            { 
                job_id: jobId, 
                status: newStatus,
                csrf_token: '<?php echo Security::generateCSRFToken(); ?>' 
            },
            function(response) {
                showAlert('success', response.message);
                loadJobs();
            },
            function(error) {
                showAlert('danger', error);
            }
        );
    }

    function loadWorkersForJob(selectedWorkerId = null) {
        makeAjaxRequest(
            'processes/get_available_workers.php',
            { 
                csrf_token: '<?php echo Security::generateCSRFToken(); ?>' 
            },
            function(response) {
                let options = '<option value="">No worker assigned</option>';
                response.workers.forEach(worker => {
                    const selected = worker.id == selectedWorkerId ? 'selected' : '';
                    options += `<option value="${worker.id}" ${selected}>${worker.name} (${worker.email})</option>`;
                });
                $('#editWorker').html(options);
            },
            function(error) {
                $('#editWorker').html('<option value="">Error loading workers</option>');
            }
        );
    }

    function updatePagination(pagination) {
        let paginationHtml = '';
        
        // Previous button
        if (pagination.current_page > 1) {
            paginationHtml += `<li class="page-item">
                <a class="page-link" href="javascript:void(0)" onclick="goToPage(${pagination.current_page - 1})">Previous</a>
            </li>`;
        }
        
        // Page numbers
        for (let i = pagination.start_page; i <= pagination.end_page; i++) {
            const active = i === pagination.current_page ? 'active' : '';
            paginationHtml += `<li class="page-item ${active}">
                <a class="page-link" href="javascript:void(0)" onclick="goToPage(${i})">${i}</a>
            </li>`;
        }
        
        // Next button
        if (pagination.current_page < pagination.total_pages) {
            paginationHtml += `<li class="page-item">
                <a class="page-link" href="javascript:void(0)" onclick="goToPage(${pagination.current_page + 1})">Next</a>
            </li>`;
        }
        
        $('#pagination').html(paginationHtml);
    }

    function goToPage(page) {
        currentPage = page;
        loadJobs();
    }

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    </script>
</body>
</html>
