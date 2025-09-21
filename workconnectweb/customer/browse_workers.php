<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

SessionManager::requireRole(['customer']);

$user_id = $_SESSION['user_id'];

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
    <title>Browse Workers - <?php echo APP_NAME; ?></title>
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
                <h2><i class="fas fa-users text-primary me-2"></i>Browse Workers</h2>
                <p class="text-muted">Find skilled professionals for your projects</p>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Search Filters</h6>
                    </div>
                    <div class="card-body">
                        <form id="searchForm">
                            <div class="mb-3">
                                <label class="form-label">Search by Name</label>
                                <input type="text" class="form-control" id="workerName" placeholder="Worker name...">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Service Category</label>
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
                                <input type="text" class="form-control" id="locationFilter" placeholder="City, area...">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Rating</label>
                                <select class="form-select" id="ratingFilter">
                                    <option value="">Any Rating</option>
                                    <option value="4">4+ Stars</option>
                                    <option value="3">3+ Stars</option>
                                    <option value="2">2+ Stars</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="availableOnly">
                                    <label class="form-check-label" for="availableOnly">
                                        Available workers only
                                    </label>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-2"></i>Search Workers
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span id="workerCount">Loading workers...</span>
                            <select class="form-select w-auto" id="sortBy">
                                <option value="rating">Highest Rated</option>
                                <option value="newest">Newest Members</option>
                                <option value="experience">Most Experienced</option>
                                <option value="jobs_completed">Most Jobs Completed</option>
                            </select>
                        </div>
                        
                        <div id="workersList">
                            <div class="text-center py-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading workers...</span>
                                </div>
                            </div>
                        </div>
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
        loadWorkers();

        $('#searchForm').on('submit', function(e) {
            e.preventDefault();
            loadWorkers();
        });

        $('#sortBy').on('change', function() {
            loadWorkers();
        });
    });

    function loadWorkers() {
        const searchData = {
            name: $('#workerName').val(),
            category_id: $('#categoryFilter').val(),
            location: $('#locationFilter').val(),
            min_rating: $('#ratingFilter').val(),
            available_only: $('#availableOnly').is(':checked') ? 1 : 0,
            sort_by: $('#sortBy').val(),
            csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
        };

        $('#workersList').html('<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>');

        makeAjaxRequest(
            '../processes/search_workers.php',
            searchData,
            function(response) {
                $('#workersList').html(response.workers_html);
                $('#workerCount').text(response.total + ' workers found');
            },
            function(error) {
                $('#workersList').html('<div class="alert alert-danger">' + error + '</div>');
            }
        );
    }

    function contactWorker(workerId) {
        window.location.href = `messages.php?with=${workerId}`;
    }

    function viewWorkerProfile(workerId) {
        window.open(`../profile/view.php?id=${workerId}`, '_blank');
    }
    </script>
</body>
</html>