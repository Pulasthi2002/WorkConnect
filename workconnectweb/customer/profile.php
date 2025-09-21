<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

SessionManager::requireRole(['customer']);

$user_id = $_SESSION['user_id'];

// Get user profile data
$profile = null;
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    
    if (!$profile) {
        header("Location: ../logout.php");
        exit;
    }
} catch (Exception $e) {
    error_log("Profile error: " . $e->getMessage());
    header("Location: dashboard.php");
    exit;
}

// Get profile statistics
$stats = [];
try {
    // Total jobs posted
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_postings WHERE client_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats['total_jobs'] = $stmt->get_result()->fetch_assoc()['count'];

    // Completed jobs
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_postings WHERE client_id = ? AND status = 'completed'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats['completed_jobs'] = $stmt->get_result()->fetch_assoc()['count'];

    // Total spent (if you have a payments table)
    $stats['total_spent'] = 0; // Placeholder
    
    // Member since
    $stats['member_since'] = $profile['created_at'];
} catch (Exception $e) {
    $stats = ['total_jobs' => 0, 'completed_jobs' => 0, 'total_spent' => 0, 'member_since' => date('Y-m-d')];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo APP_NAME; ?></title>
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
        <!-- Profile Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-3 text-center">
                                <?php if ($profile['profile_image']): ?>
                                    <img src="../<?php echo htmlspecialchars($profile['profile_image']); ?>" 
                                         class="rounded-circle" width="120" height="120" style="object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-white text-primary rounded-circle d-inline-flex align-items-center justify-content-center" 
                                         style="width: 120px; height: 120px; font-size: 2.5rem; font-weight: bold;">
                                        <?php echo strtoupper(substr($profile['name'], 0, 2)); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="mt-3">
                                    <button class="btn btn-light btn-sm" onclick="$('#profileImageInput').click()">
                                        <i class="fas fa-camera me-1"></i>Change Photo
                                    </button>
                                    <input type="file" id="profileImageInput" style="display: none;" accept="image/*">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h2><?php echo htmlspecialchars($profile['name']); ?></h2>
                                <p class="mb-1 opacity-75">
                                    <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($profile['email']); ?>
                                </p>
                                <p class="mb-1 opacity-75">
                                    <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($profile['telephone']); ?>
                                </p>
                                <p class="mb-0 opacity-75">
                                    <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($profile['address']); ?>
                                </p>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="row">
                                    <div class="col-6">
                                        <h4><?php echo $stats['total_jobs']; ?></h4>
                                        <small>Jobs Posted</small>
                                    </div>
                                    <div class="col-6">
                                        <h4><?php echo $stats['completed_jobs']; ?></h4>
                                        <small>Completed</small>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <small>Member since <?php echo date('M Y', strtotime($stats['member_since'])); ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Profile Information -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user me-2"></i>Profile Information</h5>
                    </div>
                    <div class="card-body">
                        <div id="profileAlert"></div>
                        
                        <form id="profileForm">
                            <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" name="name" 
                                           value="<?php echo htmlspecialchars($profile['name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo htmlspecialchars($profile['email']); ?>" required readonly>
                                    <div class="form-text">Email cannot be changed</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone Number *</label>
                                    <input type="tel" class="form-control" name="telephone" 
                                           value="<?php echo htmlspecialchars($profile['telephone']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Status</label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo ucfirst($profile['status']); ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Address *</label>
                                <textarea class="form-control" name="address" rows="3" required><?php echo htmlspecialchars($profile['address']); ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Profile
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Change Password</h5>
                    </div>
                    <div class="card-body">
                        <form id="passwordForm">
                            <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Current Password *</label>
                                <input type="password" class="form-control" name="current_password" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">New Password *</label>
                                    <input type="password" class="form-control" name="new_password" required>
                                    <div class="form-text">Minimum 8 characters with uppercase, lowercase, and numbers</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirm New Password *</label>
                                    <input type="password" class="form-control" name="confirm_password" required>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-key me-2"></i>Change Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Account Overview</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <h4 class="text-primary"><?php echo $stats['total_jobs']; ?></h4>
                                <small>Total Jobs</small>
                            </div>
                            <div class="col-6 mb-3">
                                <h4 class="text-success"><?php echo $stats['completed_jobs']; ?></h4>
                                <small>Completed</small>
                            </div>
                            <div class="col-12">
                                <?php 
                                $completion_rate = $stats['total_jobs'] > 0 ? 
                                    round(($stats['completed_jobs'] / $stats['total_jobs']) * 100) : 0;
                                ?>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo $completion_rate; ?>%"></div>
                                </div>
                                <small class="text-muted"><?php echo $completion_rate; ?>% Success Rate</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Account Security -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Account Security</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Two-Factor Authentication</span>
                            <span class="badge bg-secondary">Not Enabled</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Password Strength</span>
                            <span class="badge bg-warning">Medium</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Last Login</span>
                            <small class="text-muted">
                                <?php echo $profile['last_login'] ? Functions::timeAgo($profile['last_login']) : 'Never'; ?>
                            </small>
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
    // Profile form submission
    $('#profileForm').on('submit', function(e) {
        e.preventDefault();
        
        makeAjaxRequest(
            '../processes/update_profile.php',
            $(this).serialize(),
            function(response) {
                showAlert('success', response.message, '#profileAlert');
            },
            function(error) {
                showAlert('danger', error, '#profileAlert');
            }
        );
    });

    // Password form submission
    $('#passwordForm').on('submit', function(e) {
        e.preventDefault();
        
        const newPassword = $('input[name="new_password"]').val();
        const confirmPassword = $('input[name="confirm_password"]').val();
        
        if (newPassword !== confirmPassword) {
            showAlert('danger', 'New passwords do not match');
            return;
        }
        
        makeAjaxRequest(
            '../processes/change_password.php',
            $(this).serialize(),
            function(response) {
                showAlert('success', response.message);
                $('#passwordForm')[0].reset();
            },
            function(error) {
                showAlert('danger', error);
            }
        );
    });

    // Profile image upload
    $('#profileImageInput').on('change', function() {
        const file = this.files[0];
        if (!file) return;
        
        const formData = new FormData();
        formData.append('profile_image', file);
        formData.append('csrf_token', '<?php echo Security::generateCSRFToken(); ?>');
        
        $.ajax({
            url: '../processes/upload_profile_image.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    showAlert('success', response.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('danger', response.message);
                }
            },
            error: function() {
                showAlert('danger', 'Error uploading image');
            }
        });
    });
    </script>
</body>
</html>