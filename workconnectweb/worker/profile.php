<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

SessionManager::requireRole(['worker']);

$user_id = $_SESSION['user_id'];

// Get user and worker profile data
$profile = null;
try {
    $stmt = $conn->prepare("
        SELECT 
            u.*,
            wp.bio, wp.experience_years, wp.hourly_rate_min, wp.hourly_rate_max,
            wp.is_available, wp.average_rating, wp.total_jobs
        FROM users u
        LEFT JOIN worker_profiles wp ON u.id = wp.user_id
        WHERE u.id = ?
    ");
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

// Get service categories for skills
$categories = [];
try {
    $result = $conn->query("SELECT * FROM service_categories WHERE is_active = 1 ORDER BY name");
    if ($result) {
        $categories = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    error_log("Categories error: " . $e->getMessage());
}

// Get worker skills
$worker_skills = [];
if ($profile) {
    try {
        $stmt = $conn->prepare("
            SELECT ws.*, s.service_name, sc.name as category_name
            FROM worker_skills ws
            INNER JOIN services s ON ws.service_id = s.id
            INNER JOIN service_categories sc ON s.category_id = sc.id
            INNER JOIN worker_profiles wp ON ws.worker_id = wp.id
            WHERE wp.user_id = ?
            ORDER BY sc.name, s.service_name
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $worker_skills = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        error_log("Worker skills error: " . $e->getMessage());
    }
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
        <!-- Profile Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-3 text-center">
                                <?php if ($profile['profile_image']): ?>
                                    <img src="../<?php echo htmlspecialchars($profile['profile_image']); ?>" 
                                         class="rounded-circle" width="120" height="120" style="object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-white text-success rounded-circle d-inline-flex align-items-center justify-content-center" 
                                         style="width: 120px; height: 120px; font-size: 2.5rem; font-weight: bold;">
                                        <?php echo strtoupper(substr($profile['name'], 0, 2)); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="mt-3">
                                    <button class="btn btn-light btn-sm" onclick="$('#profileImageInput').click()">
                                        <i class="fas fa-camera me-1"></i>Change Photo
                                    </button>
                                    <input type="file" id="profileImageInput" style="display: none;" accept="image/*" onchange="uploadProfileImage(this)">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h2><?php echo htmlspecialchars($profile['name']); ?></h2>
                                <p class="mb-2 opacity-75"><?php echo htmlspecialchars($profile['email']); ?></p>
                                <p class="mb-0"><?php echo htmlspecialchars($profile['bio'] ?: 'Add your professional bio...'); ?></p>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="row">
                                    <div class="col-6">
                                        <h4><?php echo number_format($profile['average_rating'] ?: 0, 1); ?></h4>
                                        <small>Rating</small>
                                    </div>
                                    <div class="col-6">
                                        <h4><?php echo $profile['total_jobs'] ?: 0; ?></h4>
                                        <small>Jobs Done</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <!-- Basic Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user me-2"></i>Basic Information</h5>
                    </div>
                    <div class="card-body">
                        <form id="basicInfoForm">
                            <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" name="name" 
                                           value="<?php echo htmlspecialchars($profile['name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone Number *</label>
                                    <input type="tel" class="form-control" name="telephone" 
                                           value="<?php echo htmlspecialchars($profile['telephone']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Address *</label>
                                <input type="text" class="form-control" name="address" 
                                       value="<?php echo htmlspecialchars($profile['address']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Professional Bio</label>
                                <textarea class="form-control" name="bio" rows="4" maxlength="500"
                                          placeholder="Tell clients about your experience and expertise..."><?php echo htmlspecialchars($profile['bio'] ?: ''); ?></textarea>
                                <div class="form-text">
                                    <span id="bioCount"><?php echo strlen($profile['bio'] ?: ''); ?></span>/500 characters
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-2"></i>Update Information
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Professional Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-briefcase me-2"></i>Professional Details</h5>
                    </div>
                    <div class="card-body">
                        <form id="professionalForm">
                            <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Years of Experience</label>
                                    <select class="form-select" name="experience_years">
                                        <?php for ($i = 0; $i <= 20; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php echo ($profile['experience_years'] == $i) ? 'selected' : ''; ?>>
                                                <?php echo $i == 0 ? 'Less than 1 year' : $i . ' year' . ($i > 1 ? 's' : ''); ?>
                                            </option>
                                        <?php endfor; ?>
                                        <option value="21" <?php echo ($profile['experience_years'] > 20) ? 'selected' : ''; ?>>20+ years</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Minimum Rate (LKR/hour)</label>
                                    <input type="number" class="form-control" name="hourly_rate_min" 
                                           value="<?php echo $profile['hourly_rate_min']; ?>" min="0" step="50">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Maximum Rate (LKR/hour)</label>
                                    <input type="number" class="form-control" name="hourly_rate_max" 
                                           value="<?php echo $profile['hourly_rate_max']; ?>" min="0" step="50">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_available" 
                                           <?php echo $profile['is_available'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label">
                                        <strong>I am currently available for work</strong>
                                    </label>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-2"></i>Update Professional Info
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Skills Management -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-tools me-2"></i>My Skills</h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSkillModal">
                            <i class="fas fa-plus me-1"></i>Add Skill
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($worker_skills)): ?>
                            <div class="row">
                                <?php foreach ($worker_skills as $skill): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="skill-badge d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo htmlspecialchars($skill['service_name']); ?></strong>
                                                <div class="small opacity-75"><?php echo htmlspecialchars($skill['category_name']); ?></div>
                                                <span class="badge bg-light text-dark"><?php echo ucfirst($skill['skill_level']); ?></span>
                                            </div>
                                            <button class="btn btn-sm btn-outline-danger" onclick="removeSkill(<?php echo $skill['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-tools fa-3x text-muted mb-3"></i>
                                <h6 class="text-muted">No skills added yet</h6>
                                <p class="text-muted mb-3">Add your skills to attract more clients</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSkillModal">
                                    <i class="fas fa-plus me-2"></i>Add Your First Skill
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Profile Completion -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Profile Completion</h6>
                    </div>
                    <div class="card-body">
                        <?php
                        $completion = 0;
                        $completion += $profile['name'] ? 20 : 0;
                        $completion += $profile['bio'] ? 20 : 0;
                        $completion += $profile['telephone'] ? 10 : 0;
                        $completion += $profile['address'] ? 10 : 0;
                        $completion += !empty($worker_skills) ? 30 : 0;
                        $completion += $profile['profile_image'] ? 10 : 0;
                        ?>
                        <div class="progress mb-3" style="height: 20px;">
                            <div class="progress-bar bg-success" style="width: <?php echo $completion; ?>%">
                                <?php echo $completion; ?>%
                            </div>
                        </div>
                        
                        <div class="small">
                            <div class="<?php echo $profile['name'] ? 'text-success' : 'text-muted'; ?>">
                                <i class="fas fa-<?php echo $profile['name'] ? 'check' : 'times'; ?> me-2"></i>Name (20%)
                            </div>
                            <div class="<?php echo $profile['bio'] ? 'text-success' : 'text-muted'; ?>">
                                <i class="fas fa-<?php echo $profile['bio'] ? 'check' : 'times'; ?> me-2"></i>Professional Bio (20%)
                            </div>
                            <div class="<?php echo !empty($worker_skills) ? 'text-success' : 'text-muted'; ?>">
                                <i class="fas fa-<?php echo !empty($worker_skills) ? 'check' : 'times'; ?> me-2"></i>Skills Added (30%)
                            </div>
                            <div class="<?php echo $profile['profile_image'] ? 'text-success' : 'text-muted'; ?>">
                                <i class="fas fa-<?php echo $profile['profile_image'] ? 'check' : 'times'; ?> me-2"></i>Profile Photo (10%)
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Quick Stats</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <h4 class="text-success"><?php echo count($worker_skills); ?></h4>
                                <small>Skills Listed</small>
                            </div>
                            <div class="col-6 mb-3">
                                <h4 class="text-primary"><?php echo $profile['total_jobs'] ?: 0; ?></h4>
                                <small>Jobs Completed</small>
                            </div>
                            <div class="col-6">
                                <h4 class="text-warning"><?php echo number_format($profile['average_rating'] ?: 0, 1); ?></h4>
                                <small>Average Rating</small>
                            </div>
                            <div class="col-6">
                                <h4 class="text-info"><?php echo $profile['experience_years'] ?: 0; ?></h4>
                                <small>Years Experience</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Skill Modal -->
    <div class="modal fade" id="addSkillModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Skill</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addSkillForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Service Category *</label>
                            <select class="form-select" id="skillCategory" name="category_id" required>
                                <option value="">Select category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Specific Service *</label>
                            <select class="form-select" id="skillService" name="service_id" required>
                                <option value="">First select a category</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Skill Level *</label>
                            <select class="form-select" name="skill_level" required>
                                <option value="beginner">Beginner</option>
                                <option value="intermediate" selected>Intermediate</option>
                                <option value="advanced">Advanced</option>
                                <option value="expert">Expert</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus me-2"></i>Add Skill
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    <script>
$(document).ready(function() {
    // Bio character counter
    $('textarea[name="bio"]').on('input', function() {
        $('#bioCount').text($(this).val().length);
    });

    // Category change handler for skills
    $('#skillCategory').change(function() {
        loadServicesForSkill($(this).val());
    });

    // Form submissions with enhanced button handling
    $('#basicInfoForm').on('submit', function(e) {
        e.preventDefault();
        const $button = $(this).find('button[type="submit"]');
        const originalHtml = $button.html();
        
        // Store original text for safety
        $button.data('original-text', originalHtml);
        
        updateProfile($(this).serialize() + '&profile_type=basic', 'Basic information');
    });

    $('#professionalForm').on('submit', function(e) {
        e.preventDefault();
        const $button = $(this).find('button[type="submit"]');
        const originalHtml = $button.html();
        
        // Store original text for safety
        $button.data('original-text', originalHtml);
        
        updateProfile($(this).serialize() + '&profile_type=professional', 'Professional information');
    });

    $('#addSkillForm').on('submit', function(e) {
        e.preventDefault();
        addSkill();
    });
});

function updateProfile(formData, type) {
    const $form = $(event.target.form);
    const $button = $form.find('button[type="submit"]');
    const originalText = $button.html();
    
    // Show loading state
    $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Updating...');
    
    $.ajax({
        url: '../processes/update_profile.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        timeout: 30000,
        success: function(response) {
            if (response.status === 'success') {
                showAlert('success', response.message);
                // Refresh page after success like skills functionality
                setTimeout(function() {
                    window.location.reload();
                }, 1000);
            } else {
                showAlert('danger', response.message || 'An error occurred');
                // Reset button only on failure
                $button.prop('disabled', false).html(originalText);
            }
        },
        error: function(xhr, status, error) {
            let message = 'Failed to update profile. Please try again.';
            if (status === 'timeout') {
                message = 'Request timed out. Please try again.';
            }
            showAlert('danger', message);
            // Reset button on error
            $button.prop('disabled', false).html(originalText);
        }
    });
}





function loadServicesForSkill(categoryId) {
    if (!categoryId) {
        $('#skillService').html('<option value="">First select a category</option>');
        return;
    }

    $('#skillService').html('<option value="">Loading services...</option>');

    makeAjaxRequest(
        '../processes/get_services.php',
        { 
            category_id: categoryId, 
            csrf_token: '<?php echo Security::generateCSRFToken(); ?>' 
        },
        function(response) {
            $('#skillService').html(response.options);
        },
        function(error) {
            $('#skillService').html('<option value="">Error loading services</option>');
            showAlert('danger', 'Failed to load services: ' + error);
        }
    );
}

function addSkill() {
    const $button = $('#addSkillForm button[type="submit"]');
    const originalText = $button.html();
    
    $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Adding...');
    
    makeAjaxRequest(
        '../processes/add_skill.php',
        $('#addSkillForm').serialize(),
        function(response) {
            showAlert('success', response.message);
            $('#addSkillModal').modal('hide');
            $('#addSkillForm')[0].reset();
            $('#skillService').html('<option value="">First select a category</option>');
            setTimeout(() => location.reload(), 1500);
        },
        function(error) {
            showAlert('danger', error);
        }
    ).always(function() {
        $button.prop('disabled', false).html(originalText);
    });
}

function removeSkill(skillId) {
    if (confirm('Are you sure you want to remove this skill from your profile?')) {
        makeAjaxRequest(
            '../processes/remove_skill.php',
            { 
                skill_id: skillId, 
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

function uploadProfileImage(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!allowedTypes.includes(file.type)) {
            showAlert('danger', 'Please select a valid image file (JPEG, PNG, or GIF)');
            return;
        }
        
        // Validate file size (5MB max)
        if (file.size > 5242880) {
            showAlert('danger', 'File size must be less than 5MB');
            return;
        }
        
        const formData = new FormData();
        formData.append('profile_image', file);
        formData.append('csrf_token', '<?php echo Security::generateCSRFToken(); ?>');
        
        $.ajax({
            url: '../processes/upload_profile_image.php',
            method: 'POST',
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
                showAlert('danger', 'Error uploading image. Please try again.');
            }
        });
    }
}


// Add this at the top of worker/profile.php script section for debugging
function debugButtonState() {
    $('button[type="submit"]').each(function(index) {
        const $btn = $(this);
        console.log(`Button ${index}:`, {
            disabled: $btn.prop('disabled'),
            html: $btn.html(),
            hasSpinner: $btn.html().includes('fa-spinner')
        });
    });
}

// Call this in browser console to check button states
window.debugButtonState = debugButtonState;

</script>

</body>
</html>
