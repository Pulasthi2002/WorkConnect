<?php
require_once 'config.php';
require_once 'includes/session.php';
require_once 'includes/security.php';

// Redirect if already logged in
if (SessionManager::isLoggedIn()) {
    header("Location: " . SessionManager::getDashboardUrl($_SESSION['role']));
    exit;
}

$error_message = '';
$success_message = '';

// Check for messages
if (isset($_GET['registered'])) {
    $success_message = 'Registration successful! Please login with your credentials.';
}

if (isset($_GET['logout'])) {
    $success_message = 'You have been logged out successfully.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
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
            --input-bg: rgba(255, 255, 255, 0.06);
            --input-border: rgba(255, 255, 255, 0.12);
            --hover-bg: rgba(255, 255, 255, 0.12);
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
                rgba(10, 10, 10, 0.85) 0%, 
                rgba(20, 20, 30, 0.9) 50%, 
                rgba(10, 10, 10, 0.85) 100%);
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
            opacity: 0.1;
            animation: float 20s ease-in-out infinite;
        }

        .floating-element:nth-child(1) {
            width: 300px;
            height: 300px;
            top: 10%;
            right: 10%;
            animation-delay: 0s;
        }

        .floating-element:nth-child(2) {
            width: 200px;
            height: 200px;
            bottom: 20%;
            left: 15%;
            animation-delay: 7s;
        }

        .floating-element:nth-child(3) {
            width: 150px;
            height: 150px;
            top: 60%;
            right: 20%;
            animation-delay: 14s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            25% { transform: translateY(-30px) rotate(5deg); }
            50% { transform: translateY(-15px) rotate(-5deg); }
            75% { transform: translateY(-45px) rotate(3deg); }
        }

        .auth-card {
            background: var(--glass-bg);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            box-shadow: var(--glass-shadow);
            width: 100%;
            max-width: 480px;
            position: relative;
            overflow: hidden;
            animation: slideInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .auth-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--glass-border), transparent);
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(60px) scale(0.95);
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
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
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

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
            display: block;
        }

        .input-group {
            position: relative;
        }

        .input-group-text {
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-right: none;
            color: var(--text-muted);
            padding: 0.875rem 1rem;
            border-radius: 12px 0 0 12px;
        }

        .form-control {
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            color: var(--text-primary);
            padding: 0.875rem 1rem;
            border-radius: 12px;
            font-weight: 400;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .input-group .form-control {
            border-left: none;
            border-radius: 0 12px 12px 0;
        }

        .form-control:focus {
            background: var(--hover-bg);
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
            color: var(--text-primary);
        }

        .form-control::placeholder {
            color: var(--text-muted);
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

        .form-check {
            display: flex;
            align-items: center;
            justify-content: space-between;
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

        .forgot-password {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .forgot-password:hover {
            color: var(--accent-primary);
        }

        .btn-auth {
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            border: none;
            border-radius: 12px;
            color: white;
            font-weight: 600;
            font-size: 1rem;
            padding: 0.875rem 2rem;
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
                font-size: 1.75rem;
            }
            
            .floating-element {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .auth-container {
                padding: 1rem 0.5rem;
            }
            
            .card-body {
                padding: 1.5rem 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="floating-elements">
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
                    <p class="brand-subtitle">Sign in to your account</p>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div id="loginAlert"></div>

                <form id="loginForm" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="your@email.com" required>
                            <div class="invalid-feedback">Please provide a valid email address.</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Enter your password" required>
                            <button class="btn-toggle-password" type="button" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                            <div class="invalid-feedback">Please enter your password.</div>
                        </div>
                    </div>

                    <div class="form-check">
                        <div>
                            <input class="form-check-input" type="checkbox" id="remember" name="remember">
                            <label class="form-check-label ms-2" for="remember">Remember me</label>
                        </div>
                        <a href="#" class="forgot-password">Forgot Password?</a>
                    </div>

                    <button type="submit" class="btn btn-auth">
                        <span id="loginText">
                            <i class="fas fa-sign-in-alt me-2"></i>Sign In
                        </span>
                        <span id="loginSpinner" class="d-none">
                            <div class="spinner"></div>Signing In...
                        </span>
                    </button>

                    <div class="auth-footer">
                        <p>Don't have an account? 
                            <a href="register.php">Create Account</a>
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

        // Handle form submission
        $('#loginForm').on('submit', function(e) {
            e.preventDefault();
            
            if (!validateForm('#loginForm')) {
                return;
            }

            // Show loading state
            $('#loginText').addClass('d-none');
            $('#loginSpinner').removeClass('d-none');
            $('button[type="submit"]').prop('disabled', true);

            makeAjaxRequest(
                'processes/process_login.php',
                $(this).serialize(),
                function(response) {
                    showAlert('success', response.message);
                    setTimeout(() => {
                        window.location.href = response.redirect || 'index.php';
                    }, 1000);
                },
                function(message) {
                    showAlert('danger', message);
                    // Reset form state
                    $('#loginText').removeClass('d-none');
                    $('#loginSpinner').addClass('d-none');
                    $('button[type="submit"]').prop('disabled', false);
                }
            );
        });
    });
    </script>
</body>
</html>
