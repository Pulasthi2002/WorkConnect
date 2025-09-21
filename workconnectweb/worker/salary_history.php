<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

SessionManager::requireRole(['worker']);

$user_id = $_SESSION['user_id'];

// Get prediction history
$predictions = [];
try {
    $stmt = $conn->prepare("
        SELECT predicted_salary, created_at, prediction_data
        FROM salary_predictions 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $predictions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Prediction history error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Prediction History - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-tools me-2"></i><?php echo APP_NAME; ?> Worker
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="salary_predictor.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to Predictor
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2><i class="fas fa-history me-2"></i>Salary Prediction History</h2>
        
        <div class="card">
            <div class="card-body">
                <?php if (!empty($predictions)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Predicted Salary</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($predictions as $prediction): ?>
                                    <tr>
                                        <td><?php echo Functions::timeAgo($prediction['created_at']); ?></td>
                                        <td>
                                            <strong class="text-success">
                                                $<?php echo number_format($prediction['predicted_salary'], 0); ?>
                                            </strong>
                                            <small class="text-muted">/ month</small>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="viewDetails('<?php echo htmlspecialchars($prediction['prediction_data']); ?>')">
                                                View Details
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-chart-line fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">No Predictions Yet</h5>
                        <p class="text-muted">Start by creating your first salary prediction</p>
                        <a href="salary_predictor.php" class="btn btn-success">
                            <i class="fas fa-plus me-2"></i>Create Prediction
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    <script>
        function viewDetails(predictionData) {
            try {
                const data = JSON.parse(predictionData);
                let details = '<div class="row">';
                
                Object.keys(data).forEach(key => {
                    details += `<div class="col-md-6 mb-2">
                        <strong>${key.replace(/_/g, ' ').toUpperCase()}:</strong> ${data[key]}
                    </div>`;
                });
                
                details += '</div>';
                
                // Show in modal or alert
                showAlert('info', details, 'body', 10000);
            } catch (e) {
                showAlert('error', 'Failed to parse prediction data');
            }
        }
    </script>
</body>
</html>
