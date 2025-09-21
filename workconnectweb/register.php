<?php
require_once 'config.php';
require_once 'includes/session.php';
require_once 'includes/security.php';

// Redirect if already logged in
if (SessionManager::isLoggedIn()) {
    header("Location: " . SessionManager::getDashboardUrl($_SESSION['role']));
    exit;
}

// Get service categories for worker registration
$categories = [];
try {
    $result = $conn->query("SELECT * FROM service_categories WHERE is_active = 1 ORDER BY name");
    if ($result) {
        $categories = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    // Handle error silently
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.08);
            --glass-border: rgba(255, 255, 255, 0.18);
            --glass-shadow: 0 25px 45px rgba(0, 0, 0, 0.3);
            --text-primary: #ffffff;
            --text-secondary: rgba(255, 255, 255, 0.7);
            --text-muted: rgba(255, 255, 255, 0.5);
            --accent-primary: #667eea;
            --accent-secondary: #764ba2;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --input-bg: rgba(255, 255, 255, 0.06);
            --input-border: rgba(255, 255, 255, 0.12);
            --hover-bg: rgba(255, 255, 255, 0.12);
            --card-bg: rgba(255, 255, 255, 0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #0a0a0a url('assets/images/workh.png') center center/cover no-repeat;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, 
                rgba(10, 10, 10, 0.87) 0%, 
                rgba(20, 20, 30, 0.92) 50%, 
                rgba(10, 10, 10, 0.87) 100%);
            z-index: -1;
        }

        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            position: relative;
        }

        .floating-elements {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }

        .floating-element {
            position: absolute;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            border-radius: 50%;
            opacity: 0.08;
            animation: float 25s ease-in-out infinite;
        }

        .floating-element:nth-child(1) {
            width: 400px;
            height: 400px;
            top: 5%;
            right: 5%;
            animation-delay: 0s;
        }

        .floating-element:nth-child(2) {
            width: 250px;
            height: 250px;
            bottom: 15%;
            left: 10%;
            animation-delay: 8s;
        }

        .floating-element:nth-child(3) {
            width: 180px;
            height: 180px;
            top: 50%;
            right: 25%;
            animation-delay: 16s;
        }

        .floating-element:nth-child(4) {
            width: 120px;
            height: 120px;
            top: 25%;
            left: 20%;
            animation-delay: 12s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            25% { transform: translateY(-40px) rotate(8deg); }
            50% { transform: translateY(-20px) rotate(-8deg); }
            75% { transform: translateY(-60px) rotate(5deg); }
        }

        .auth-card {
            background: var(--glass-bg);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 1px solid var(--glass-border);
            border-radius: 28px;
            box-shadow: var(--glass-shadow);
            width: 100%;
            max-width: 600px;
            position: relative;
            overflow: hidden;
            animation: slideInUp 0.9s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .auth-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, 
                transparent, 
                var(--accent-primary), 
                var(--accent-secondary), 
                transparent);
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(80px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .card-body {
            padding: 3rem;
            color: var(--text-primary);
        }

        .brand-section {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .brand-title {
            font-size: 2.2rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
            letter-spacing: -0.8px;
        }

        .brand-subtitle {
            color: var(--text-muted);
            font-size: 1rem;
            font-weight: 400;
        }

        .alert {
            background: var(--glass-bg);
            backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            padding: 1rem 1.25rem;
        }

        .alert-success {
            border-color: rgba(16, 185, 129, 0.3);
            background: rgba(16, 185, 129, 0.1);
        }

        .alert-danger {
            border-color: rgba(239, 68, 68, 0.3);
            background: rgba(239, 68, 68, 0.1);
        }

        .role-selection {
            margin-bottom: 2rem;
        }

        .role-selection label {
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 1rem;
            display: block;
        }

        .btn-check:checked + .btn-role {
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            border-color: var(--accent-primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-role {
            background: var(--card-bg);
            border: 1px solid var(--glass-border);
            color: var(--text-secondary);
            padding: 1.5rem 1rem;
            border-radius: 16px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
        }

        .btn-role:hover {
            background: var(--hover-bg);
            border-color: var(--accent-primary);
            color: var(--text-primary);
            transform: translateY(-1px);
        }

        .btn-role i {
            font-size: 2rem;
            margin-bottom: 0.75rem;
        }

        .btn-role strong {
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }

        .btn-role small {
            font-size: 0.85rem;
            opacity: 0.8;
        }

        .form-section {
            margin-bottom: 1.5rem;
        }

        .form-label {
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
            display: block;
        }

        .form-control, .form-select {
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            color: var(--text-primary);
            padding: 0.875rem 1rem;
            border-radius: 12px;
            font-weight: 400;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .form-control:focus, .form-select:focus {
            background: var(--hover-bg);
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
            color: var(--text-primary);
        }

        .form-control::placeholder {
            color: var(--text-muted);
        }

        .form-select option {
            background: #1a1a1a;
            color: var(--text-primary);
        }

        .input-group-text {
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-right: none;
            color: var(--text-muted);
            padding: 0.875rem 1rem;
            border-radius: 12px 0 0 12px;
        }

        .input-group .form-control {
            border-left: none;
            border-radius: 0 12px 12px 0;
        }

        .btn-toggle-password {
            background: transparent;
            border: 1px solid var(--input-border);
            border-left: none;
            color: var(--text-muted);
            padding: 0.875rem 1rem;
            border-radius: 0 12px 12px 0;
            transition: all 0.3s ease;
        }

        .btn-toggle-password:hover {
            background: var(--hover-bg);
            color: var(--text-secondary);
        }

        .worker-fields {
            background: var(--card-bg);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            backdrop-filter: blur(10px);
            animation: fadeInScale 0.5s ease;
        }

        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .form-text {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }

        .form-check {
            margin: 1.5rem 0;
        }

        .form-check-input {
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 6px;
        }

        .form-check-input:checked {
            background: var(--accent-primary);
            border-color: var(--accent-primary);
        }

        .form-check-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .form-check-label a {
            color: var(--accent-primary);
            text-decoration: none;
        }

        .form-check-label a:hover {
            color: var(--accent-secondary);
        }

        .btn-auth {
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            border: none;
            border-radius: 12px;
            color: white;
            font-weight: 600;
            font-size: 1rem;
            padding: 1rem 2rem;
            width: 100%;
            margin: 1.5rem 0;
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.25);
        }

        .btn-auth::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn-auth:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.35);
        }

        .btn-auth:hover::before {
            left: 100%;
        }

        .btn-auth:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .auth-footer {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--glass-border);
        }

        .auth-footer p {
            color: var(--text-secondary);
            margin: 0;
        }

        .auth-footer a {
            color: var(--accent-primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .auth-footer a:hover {
            color: var(--accent-secondary);
        }

        .back-home {
            text-align: center;
            margin-top: 2rem;
        }

        .back-home a {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .back-home a:hover {
            color: var(--text-secondary);
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 0.5rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .invalid-feedback {
            color: var(--danger);
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .card-body {
                padding: 2rem 1.5rem;
            }
            
            .brand-title {
                font-size: 1.8rem;
            }
            
            .floating-element {
                display: none;
            }
            
            .btn-role {
                padding: 1rem 0.75rem;
            }
            
            .btn-role i {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .auth-container {
                padding: 1rem 0.5rem;
            }
            
            .card-body {
                padding: 1.5rem 1rem;
            }
            
            .worker-fields {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="floating-elements">
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
    </div>

    <div class="auth-container">
        <div class="auth-card">
            <div class="card-body">
                <div class="brand-section">
                    <h2 class="brand-title">
                        <i class="fas fa-tools me-2"></i><?php echo APP_NAME; ?>
                    </h2>
                    <p class="brand-subtitle">Create your account and get started</p>
                </div>

                <div id="registerAlert"></div>

                <form id="registerForm" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                    
                    <!-- Account Type Selection -->
                    <div class="role-selection">
                        <label class="form-label">I want to:</label>
                        <div class="row">
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="role" id="roleCustomer" value="customer" checked>
                                <label class="btn btn-role w-100" for="roleCustomer">
                                    <i class="fas fa-user-tie"></i>
                                    <strong>Hire Workers</strong>
                                    <small>Find skilled professionals</small>
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="role" id="roleWorker" value="worker">
                                <label class="btn btn-role w-100" for="roleWorker">
                                    <i class="fas fa-tools"></i>
                                    <strong>Offer Services</strong>
                                    <small>Provide expertise</small>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Personal Information -->
                    <div class="row">
                        <div class="col-md-6 form-section">
                            <label for="name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   placeholder="Your full name" required maxlength="100">
                            <div class="invalid-feedback">Please provide your full name.</div>
                        </div>
                        <div class="col-md-6 form-section">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="your@email.com" required maxlength="100">
                            <div class="invalid-feedback">Please provide a valid email address.</div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-section">
                            <label for="telephone" class="form-label">Phone Number *</label>
                            <input type="tel" class="form-control" id="telephone" name="telephone" 
                                   placeholder="+94 XX XXX XXXX" required maxlength="20">
                            <div class="invalid-feedback">Please provide a valid phone number.</div>
                        </div>
                        <div class="col-md-6 form-section">
                            <label for="address" class="form-label">City/Area *</label>
                            <input type="text" class="form-control" id="address" name="address" 
                                   placeholder="Colombo, Kandy, Galle..." required maxlength="255">
                            <div class="invalid-feedback">Please provide your location.</div>
                        </div>
                    </div>

                    <!-- Worker-specific fields -->
                    <div id="workerFields" class="worker-fields d-none">
                        <h6 class="mb-3"><i class="fas fa-briefcase me-2"></i>Professional Details</h6>
                        
                        <div class="form-section">
                            <label for="serviceCategory" class="form-label">Primary Service Category</label>
                            <select class="form-select" id="serviceCategory" name="service_category">
                                <option value="">Select your main service area</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 form-section">
                                <label for="experience" class="form-label">Years of Experience</label>
                                <select class="form-select" id="experience" name="experience_years">
                                    <option value="0">Less than 1 year</option>
                                    <option value="1">1-2 years</option>
                                    <option value="3">3-5 years</option>
                                    <option value="6">6-10 years</option>
                                    <option value="11">10+ years</option>
                                </select>
                            </div>
                            <div class="col-md-6 form-section">
                                <label for="hourlyRate" class="form-label">Hourly Rate (LKR)</label>
                                <input type="number" class="form-control" id="hourlyRate" name="hourly_rate" 
                                       placeholder="1500" min="0" step="50">
                            </div>
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="row">
                        <div class="col-md-6 form-section">
                            <label for="password" class="form-label">Password *</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Create strong password" required minlength="8">
                                <button class="btn-toggle-password" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">
                                At least 8 characters with uppercase, lowercase, and number
                            </div>
                            <div class="invalid-feedback">Password must be at least 8 characters long.</div>
                        </div>
                        <div class="col-md-6 form-section">
                            <label for="confirmPassword" class="form-label">Confirm Password *</label>
                            <input type="password" class="form-control" id="confirmPassword" name="confirm_password" 
                                   placeholder="Confirm your password" required>
                            <div class="invalid-feedback">Passwords do not match.</div>
                        </div>
                    </div>

                    <!-- Terms Agreement -->
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="terms" name="terms_agreed" required>
                        <label class="form-check-label" for="terms">
                            I agree to the <a href="#">Terms of Service</a> 
                            and <a href="#">Privacy Policy</a>
                        </label>
                        <div class="invalid-feedback">You must agree to the terms and conditions.</div>
                    </div>

                    <button type="submit" class="btn btn-auth">
                        <span id="registerText">
                            <i class="fas fa-user-plus me-2"></i>Create Account
                        </span>
                        <span id="registerSpinner" class="d-none">
                            <div class="spinner"></div>Creating Account...
                        </span>
                    </button>

                    <div class="auth-footer">
                        <p>Already have an account? 
                            <a href="login.php">Sign In</a>
                        </p>
                    </div>
                </form>
            </div>
        </div>

        <div class="back-home">
            <a href="index.php">
                <i class="fas fa-arrow-left me-2"></i>Back to Home
            </a>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
    $(document).ready(function() {
        // Show/hide worker fields based on role selection
        $('input[name="role"]').change(function() {
            if ($(this).val() === 'worker') {
                $('#workerFields').removeClass('d-none');
            } else {
                $('#workerFields').addClass('d-none');
            }
        });

        // Toggle password visibility
        $('#togglePassword').click(function() {
            const passwordField = $('#password');
            const icon = $(this).find('i');
            
            if (passwordField.attr('type') === 'password') {
                passwordField.attr('type', 'text');
                icon.removeClass('fa-eye').addClass('fa-eye-slash');
            } else {
                passwordField.attr('type', 'password');
                icon.removeClass('fa-eye-slash').addClass('fa-eye');
            }
        });

        // Password confirmation validation
        $('#confirmPassword').on('input', function() {
            const password = $('#password').val();
            const confirmPassword = $(this).val();
            
            if (password && confirmPassword && password !== confirmPassword) {
                $(this).addClass('is-invalid');
            } else {
                $(this).removeClass('is-invalid');
            }
        });

        // Handle form submission
        $('#registerForm').on('submit', function(e) {
            e.preventDefault();
            
            // Validate passwords match
            const password = $('#password').val();
            const confirmPassword = $('#confirmPassword').val();
            
            if (password !== confirmPassword) {
                showAlert('danger', 'Passwords do not match.');
                return;
            }
            
            if (!validateForm('#registerForm')) {
                return;
            }

            // Show loading state
            $('#registerText').addClass('d-none');
            $('#registerSpinner').removeClass('d-none');
            $('button[type="submit"]').prop('disabled', true);

            makeAjaxRequest(
                'processes/process_register.php',
                $(this).serialize(),
                function(response) {
                    showAlert('success', response.message);
                    setTimeout(() => {
                        window.location.href = 'login.php?registered=1';
                    }, 2000);
                },
                function(message) {
                    showAlert('danger', message);
                    // Reset form state
                    $('#registerText').removeClass('d-none');
                    $('#registerSpinner').addClass('d-none');
                    $('button[type="submit"]').prop('disabled', false);
                }
            );
        });
    });
    </script>
</body>
</html>
