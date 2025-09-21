<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

SessionManager::requireRole(['customer']);

$user_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo APP_NAME; ?></title>
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
        <div class="row">
            <div class="col-md-3">
                <!-- Settings Navigation -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Settings</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="#notifications" class="list-group-item list-group-item-action active" data-bs-toggle="tab">
                            <i class="fas fa-bell me-2"></i>Notifications
                        </a>
                        <a href="#privacy" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                            <i class="fas fa-shield-alt me-2"></i>Privacy
                        </a>
                        <a href="#preferences" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                            <i class="fas fa-sliders-h me-2"></i>Preferences
                        </a>
                        <a href="#account" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                            <i class="fas fa-user-cog me-2"></i>Account
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-9">
                <div class="tab-content">
                    <!-- Notifications Settings -->
                    <div class="tab-pane fade show active" id="notifications">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Notification Preferences</h5>
                            </div>
                            <div class="card-body">
                                <form id="notificationForm">
                                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                                    
                                    <h6>Email Notifications</h6>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="email_job_applications" checked>
                                        <label class="form-check-label">
                                            New job applications received
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="email_messages" checked>
                                        <label class="form-check-label">
                                            New messages received
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="email_job_updates">
                                        <label class="form-check-label">
                                            Job status updates
                                        </label>
                                    </div>
                                    <div class="form-check mb-4">
                                        <input class="form-check-input" type="checkbox" name="email_marketing">
                                        <label class="form-check-label">
                                            Marketing emails and promotions
                                        </label>
                                    </div>

                                    <h6>Push Notifications</h6>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="push_applications" checked>
                                        <label class="form-check-label">
                                            New applications (Browser notifications)
                                        </label>
                                    </div>
                                    <div class="form-check mb-4">
                                        <input class="form-check-input" type="checkbox" name="push_messages" checked>
                                        <label class="form-check-label">
                                            New messages (Browser notifications)
                                        </label>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Save Notification Settings
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Privacy Settings -->
                    <div class="tab-pane fade" id="privacy">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Privacy Settings</h5>
                            </div>
                            <div class="card-body">
                                <form id="privacyForm">
                                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                                    
                                    <h6>Profile Visibility</h6>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="profile_visibility" value="public" checked>
                                        <label class="form-check-label">
                                            <strong>Public</strong> - Visible to all workers
                                        </label>
                                    </div>
                                    <div class="form-check mb-4">
                                        <input class="form-check-input" type="radio" name="profile_visibility" value="private">
                                        <label class="form-check-label">
                                            <strong>Private</strong> - Only visible when posting jobs
                                        </label>
                                    </div>

                                    <h6>Data Sharing</h6>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="share_analytics" checked>
                                        <label class="form-check-label">
                                            Share usage analytics to improve the platform
                                        </label>
                                    </div>
                                    <div class="form-check mb-4">
                                        <input class="form-check-input" type="checkbox" name="share_feedback">
                                        <label class="form-check-label">
                                            Allow WorkConnect to contact me for feedback
                                        </label>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Save Privacy Settings
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Preferences -->
                    <div class="tab-pane fade" id="preferences">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Platform Preferences</h5>
                            </div>
                            <div class="card-body">
                                <form id="preferencesForm">
                                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Language</label>
                                            <select class="form-select" name="language">
                                                <option value="en" selected>English</option>
                                                <option value="si">Sinhala</option>
                                                <option value="ta">Tamil</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Timezone</label>
                                            <select class="form-select" name="timezone">
                                                <option value="Asia/Colombo" selected>Asia/Colombo (Sri Lanka)</option>
                                                <option value="Asia/Dhaka">Asia/Dhaka</option>
                                                <option value="Asia/Karachi">Asia/Karachi</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Currency</label>
                                            <select class="form-select" name="currency">
                                                <option value="LKR" selected>Sri Lankan Rupee (LKR)</option>
                                                <option value="USD">US Dollar (USD)</option>
                                                <option value="EUR">Euro (EUR)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Theme</label>
                                            <select class="form-select" name="theme">
                                                <option value="light" selected>Light</option>
                                                <option value="dark">Dark</option>
                                                <option value="auto">Auto (System)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <h6 class="mt-4">Dashboard Preferences</h6>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="show_tips" checked>
                                        <label class="form-check-label">
                                            Show helpful tips and tutorials
                                        </label>
                                    </div>
                                    <div class="form-check mb-4">
                                        <input class="form-check-input" type="checkbox" name="auto_refresh" checked>
                                        <label class="form-check-label">
                                            Auto-refresh notifications every 2 minutes
                                        </label>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Save Preferences
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Account Settings -->
                    <div class="tab-pane fade" id="account">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Account Management</h5>
                            </div>
                            <div class="card-body">
                                <h6>Account Actions</h6>
                                <p class="text-muted">Manage your account settings and data.</p>
                                
                                <div class="d-grid gap-2 d-md-block">
                                    <button class="btn btn-info me-2" onclick="downloadData()">
                                        <i class="fas fa-download me-2"></i>Download My Data
                                    </button>
                                    <button class="btn btn-warning me-2" onclick="deactivateAccount()">
                                        <i class="fas fa-pause me-2"></i>Deactivate Account
                                    </button>
                                    <button class="btn btn-danger" onclick="deleteAccount()">
                                        <i class="fas fa-trash me-2"></i>Delete Account
                                    </button>
                                </div>

                                <div class="mt-4">
                                    <h6>Account Information</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <tr>
                                                <td><strong>Account Created:</strong></td>
                                                <td><?php echo date('F j, Y', strtotime($_SESSION['created_at'] ?? 'now')); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Account Type:</strong></td>
                                                <td>Customer Account</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Status:</strong></td>
                                                <td><span class="badge bg-success">Active</span></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Data Usage:</strong></td>
                                                <td>~2.5 MB</td>
                                            </tr>
                                        </table>
                                    </div>
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
    // Form handlers
    $('#notificationForm, #privacyForm, #preferencesForm').on('submit', function(e) {
        e.preventDefault();
        
        makeAjaxRequest(
            '../processes/update_settings.php',
            $(this).serialize() + '&setting_type=' + $(this).closest('.tab-pane').attr('id'),
            function(response) {
                showAlert('success', response.message);
            },
            function(error) {
                showAlert('danger', error);
            }
        );
    });

    function downloadData() {
        if (confirm('Download all your account data? This may take a few minutes.')) {
            window.location.href = '../processes/download_data.php?token=<?php echo Security::generateCSRFToken(); ?>';
        }
    }

    function deactivateAccount() {
        if (confirm('Are you sure you want to deactivate your account? You can reactivate it later by logging in.')) {
            makeAjaxRequest(
                '../processes/deactivate_account.php',
                { csrf_token: '<?php echo Security::generateCSRFToken(); ?>' },
                function(response) {
                    showAlert('success', response.message);
                    setTimeout(() => window.location.href = '../logout.php', 2000);
                }
            );
        }
    }

    function deleteAccount() {
        const confirmation = prompt('To permanently delete your account, type "DELETE" below:');
        if (confirmation === 'DELETE') {
            makeAjaxRequest(
                '../processes/delete_account.php',
                { csrf_token: '<?php echo Security::generateCSRFToken(); ?>' },
                function(response) {
                    showAlert('success', response.message);
                    setTimeout(() => window.location.href = '../index.php', 3000);
                }
            );
        } else if (confirmation !== null) {
            showAlert('warning', 'Account deletion cancelled - incorrect confirmation.');
        }
    }
    </script>
</body>
</html>
