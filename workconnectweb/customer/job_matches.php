<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
require_once '../includes/matching_engine.php';

SessionManager::requireRole(['customer']);

$user_id = $_SESSION['user_id'];
$job_id = intval($_GET['job_id'] ?? 0);

// Verify job ownership and get job details
$job = null;
try {
    $stmt = $conn->prepare("
        SELECT jp.*, sc.name as category_name, s.service_name
        FROM job_postings jp
        INNER JOIN services s ON jp.service_id = s.id
        INNER JOIN service_categories sc ON s.category_id = sc.id
        WHERE jp.id = ? AND jp.client_id = ?
    ");
    $stmt->bind_param("ii", $job_id, $user_id);
    $stmt->execute();
    $job = $stmt->get_result()->fetch_assoc();
    
    if (!$job) {
        header("Location: my_jobs.php");
        exit;
    }
} catch (Exception $e) {
    header("Location: my_jobs.php");
    exit;
}

// Get matching results
$matching_engine = new SmartMatchingEngine($conn);
$matches = $matching_engine->getTopMatches($job_id, 50);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Worker Matches - <?php echo APP_NAME; ?></title>
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
        <!-- Job Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h3><?php echo htmlspecialchars($job['title']); ?></h3>
                        <p class="text-muted"><?php echo htmlspecialchars($job['category_name'] . ' â€¢ ' . $job['service_name']); ?></p>
                        <div class="row">
                            <div class="col-md-8">
                                <p><?php echo Functions::truncateText($job['description'], 200); ?></p>
                            </div>
                            <div class="col-md-4 text-end">
                                <button class="btn btn-primary" onclick="recalculateMatches()">
                                    <i class="fas fa-refresh me-2"></i>Recalculate Matches
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Matches Results -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-users me-2"></i>Smart Match Results
                            <span class="badge bg-primary ms-2"><?php echo count($matches); ?> workers found</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($matches)): ?>
                            <div class="row">
                                <?php foreach ($matches as $worker): ?>
                                    <div class="col-lg-4 col-md-6 mb-4">
                                        <div class="card h-100 border-<?php 
                                            echo $worker['total_score'] >= 80 ? 'success' : 
                                                ($worker['total_score'] >= 60 ? 'primary' : 'warning'); 
                                        ?>">
                                            <div class="card-header">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($worker['worker_name']); ?></h6>
                                                    <span class="badge bg-<?php 
                                                        echo $worker['total_score'] >= 80 ? 'success' : 
                                                            ($worker['total_score'] >= 60 ? 'primary' : 'warning'); 
                                                    ?>">
                                                        <?php echo number_format($worker['total_score'], 1); ?>% Match
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <!-- Worker details and match scores -->
                                                <div class="text-center mb-3">
                                                    <?php if ($worker['average_rating'] > 0): ?>
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star text-warning<?php echo $i <= $worker['average_rating'] ? '' : '-o'; ?>"></i>
                                                        <?php endfor; ?>
                                                        <span class="ms-2 text-muted">(<?php echo number_format($worker['average_rating'], 1); ?>)</span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Match breakdown -->
                                                <div class="mb-3">
                                                    <small class="text-muted">Match Breakdown:</small>
                                                    <div class="mt-2">
                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                            <small>Skills</small>
                                                            <small><?php echo number_format($worker['skill_score'], 0); ?>%</small>
                                                        </div>
                                                        <div class="progress mb-2" style="height: 4px;">
                                                            <div class="progress-bar bg-primary" style="width: <?php echo $worker['skill_score']; ?>%"></div>
                                                        </div>
                                                        
                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                            <small>Location</small>
                                                            <small><?php echo number_format($worker['location_score'], 0); ?>%</small>
                                                        </div>
                                                        <div class="progress mb-2" style="height: 4px;">
                                                            <div class="progress-bar bg-info" style="width: <?php echo $worker['location_score']; ?>%"></div>
                                                        </div>
                                                        
                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                            <small>Budget</small>
                                                            <small><?php echo number_format($worker['budget_score'], 0); ?>%</small>
                                                        </div>
                                                        <div class="progress mb-2" style="height: 4px;">
                                                            <div class="progress-bar bg-success" style="width: <?php echo $worker['budget_score']; ?>%"></div>
                                                        </div>
                                                        
                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                            <small>Experience</small>
                                                            <small><?php echo number_format($worker['experience_score'], 0); ?>%</small>
                                                        </div>
                                                        <div class="progress" style="height: 4px;">
                                                            <div class="progress-bar bg-warning" style="width: <?php echo $worker['experience_score']; ?>%"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="d-grid gap-2">
                                                    <button class="btn btn-primary btn-sm" 
                                                            onclick="inviteWorker(<?php echo $worker['worker_id']; ?>)">
                                                        <i class="fas fa-handshake me-1"></i>Invite to Apply
                                                    </button>
                                                    <button class="btn btn-outline-secondary btn-sm" 
                                                            onclick="viewWorkerProfile(<?php echo $worker['worker_id']; ?>)">
                                                        <i class="fas fa-eye me-1"></i>View Full Profile
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-search fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No matches calculated yet</h5>
                                <p class="text-muted">Click "Recalculate Matches" to find workers for this job</p>
                                <button class="btn btn-primary" onclick="recalculateMatches()">
                                    <i class="fas fa-magic me-2"></i>Calculate Matches Now
                                </button>
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
    <script>
    function recalculateMatches() {
        if (confirm('Recalculate matches for this job? This may take a few moments.')) {
            makeAjaxRequest(
                '../processes/calculate_job_matches.php',
                {
                    job_id: <?php echo $job_id; ?>,
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

    function inviteWorker(workerId) {
        window.location.href = `messages.php?with=${workerId}&job_id=<?php echo $job_id; ?>`;
    }

    function viewWorkerProfile(workerId) {
        window.open(`../profile/view.php?id=${workerId}`, '_blank');
    }
    </script>
</body>
</html>
