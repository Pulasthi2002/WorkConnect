<?php
require_once 'config.php';
require_once 'includes/session.php';
require_once 'includes/security.php';

// Redirect if already logged in
if (SessionManager::isLoggedIn()) {
    header("Location: " . SessionManager::getDashboardUrl($_SESSION['role']));
    exit;
}

// Get platform statistics
$stats = ['users' => 0, 'jobs' => 0, 'completed' => 0];
try {
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin'");
    if ($result) $stats['users'] = $result->fetch_assoc()['count'];
    
    $result = $conn->query("SELECT COUNT(*) as count FROM job_postings");
    if ($result) $stats['jobs'] = $result->fetch_assoc()['count'];
    
    $result = $conn->query("SELECT COUNT(*) as count FROM job_postings WHERE status = 'completed'");
    if ($result) $stats['completed'] = $result->fetch_assoc()['count'];
} catch (Exception $e) {
    // Use default values
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Connect. Work. Succeed.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --dark-gradient: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --shadow-soft: 0 15px 35px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 25px 50px rgba(0, 0, 0, 0.15);
            --hero-overlay: rgba(0, 0, 0, 0.4);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.7;
            overflow-x: hidden;
            background: #f8fafc;
        }

        /* Navigation Styles */
        .modern-navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 1rem 0;
            z-index: 1000;
        }

        .modern-navbar.scrolled {
            padding: 0.5rem 0;
            box-shadow: var(--shadow-soft);
            background: rgba(255, 255, 255, 0.98);
        }

        .navbar-brand {
            font-weight: 900;
            font-size: 1.6rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-decoration: none;
        }

        .nav-link {
            font-weight: 500;
            color: #64748b !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            padding: 0.5rem 1rem !important;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 50%;
            background: var(--primary-gradient);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .nav-link:hover::after {
            width: 80%;
        }

        .nav-link:hover {
            color: #667eea !important;
            transform: translateY(-2px);
        }

        .btn-modern {
            background: var(--primary-gradient);
            border: none;
            border-radius: 50px;
            padding: 12px 30px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }

        .btn-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: all 0.5s;
        }

        .btn-modern:hover::before {
            left: 100%;
        }

        .btn-modern:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.4);
        }

        /* Hero Section with Background Image */
        .hero-modern {
            min-height: 100vh;
            background: linear-gradient(var(--hero-overlay), var(--hero-overlay)), 
                        url('assets/images/workbg.jpg') center center/cover no-repeat;
            position: relative;
            display: flex;
            align-items: center;
            overflow: hidden;
        }

        .hero-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 30% 20%, rgba(102, 126, 234, 0.3) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(118, 75, 162, 0.3) 0%, transparent 50%),
                        radial-gradient(circle at 40% 70%, rgba(240, 147, 251, 0.2) 0%, transparent 50%);
            animation: gradientShift 8s ease-in-out infinite;
        }

        @keyframes gradientShift {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .hero-content {
            position: relative;
            z-index: 3;
        }

        .hero-content h1 {
            font-size: clamp(3rem, 8vw, 5rem);
            font-weight: 900;
            line-height: 1.1;
            margin-bottom: 2rem;
            color: white;
            text-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            letter-spacing: -0.02em;
        }

        .hero-highlight {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 0.5rem 1.5rem;
            border-radius: 25px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: inline-block;
            animation: glow 3s ease-in-out infinite alternate;
        }

        @keyframes glow {
            from { box-shadow: 0 5px 20px rgba(255, 255, 255, 0.3); }
            to { box-shadow: 0 10px 40px rgba(255, 255, 255, 0.5); }
        }

        .hero-content p {
            font-size: 1.4rem;
            font-weight: 400;
            opacity: 0.95;
            margin-bottom: 3rem;
            color: white;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            line-height: 1.6;
        }

        .hero-buttons .btn {
            margin: 0 15px 20px 0;
            padding: 18px 40px;
            font-size: 1.1rem;
            border-radius: 50px;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 1px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .btn-hero-primary {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            backdrop-filter: blur(15px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        }

        .btn-hero-primary:hover {
            background: white;
            color: #667eea;
            transform: translateY(-5px);
            box-shadow: 0 25px 50px rgba(255, 255, 255, 0.3);
        }

        .btn-hero-secondary {
            background: transparent;
            border: 2px solid rgba(255, 255, 255, 0.8);
            color: white;
            backdrop-filter: blur(10px);
        }

        .btn-hero-secondary:hover {
            background: rgba(255, 255, 255, 0.9);
            color: #667eea;
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(255, 255, 255, 0.2);
        }

        /* Floating Elements */
        .hero-decorations {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 1;
        }

        .floating-element {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 20px;
            animation: float 6s ease-in-out infinite;
        }

        .floating-element:nth-child(1) { 
            top: 15%; right: 10%; 
            animation-delay: 0s; 
            animation-duration: 8s;
        }
        
        .floating-element:nth-child(2) { 
            bottom: 25%; right: 15%; 
            animation-delay: 2s; 
            animation-duration: 6s;
        }
        
        .floating-element:nth-child(3) { 
            top: 50%; right: 5%; 
            animation-delay: 4s; 
            animation-duration: 10s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            25% { transform: translateY(-20px) rotate(1deg); }
            50% { transform: translateY(-10px) rotate(-1deg); }
            75% { transform: translateY(-30px) rotate(0.5deg); }
        }

        /* Stats Section */
        .stats-modern {
            padding: 120px 0;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            position: relative;
            margin-top: -60px;
            z-index: 10;
        }

        .stats-container {
            background: white;
            border-radius: 30px;
            padding: 60px 30px;
            box-shadow: var(--shadow-soft);
            border: 1px solid rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }

        .stats-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }

        .stat-card {
            text-align: center;
            position: relative;
            padding: 20px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .stat-card:hover {
            transform: translateY(-10px);
        }

        .stat-number {
            font-size: 4rem;
            font-weight: 900;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
            margin-bottom: 15px;
            text-shadow: none;
        }

        .stat-label {
            font-size: 1.2rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        /* Features Section */
        .features-modern {
            padding: 140px 0;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            position: relative;
        }

        .section-title {
            text-align: center;
            margin-bottom: 100px;
        }

        .section-title h2 {
            font-size: clamp(2.5rem, 6vw, 4rem);
            font-weight: 900;
            background: var(--dark-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 25px;
            letter-spacing: -0.02em;
        }

        .section-title p {
            font-size: 1.4rem;
            color: #64748b;
            font-weight: 400;
            max-width: 600px;
            margin: 0 auto;
        }

        .feature-card {
            background: white;
            border-radius: 30px;
            padding: 60px 40px;
            text-align: center;
            box-shadow: var(--shadow-soft);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(0, 0, 0, 0.05);
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: -100%;
            left: -100%;
            width: 300%;
            height: 300%;
            background: var(--primary-gradient);
            opacity: 0;
            transition: all 0.5s ease;
            transform: rotate(45deg);
        }

        .feature-card:hover::before {
            opacity: 0.03;
            top: -50%;
            left: -50%;
        }

        .feature-card:hover {
            transform: translateY(-20px) scale(1.02);
            box-shadow: var(--shadow-hover);
        }

        .feature-icon {
            width: 100px;
            height: 100px;
            border-radius: 25px;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 35px;
            font-size: 2.5rem;
            color: white;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 2;
        }

        .feature-card:hover .feature-icon {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.3);
        }

        .feature-card h5 {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 25px;
            color: #1e293b;
            position: relative;
            z-index: 2;
        }

        .feature-card p {
            color: #64748b;
            font-size: 1.1rem;
            line-height: 1.8;
            position: relative;
            z-index: 2;
        }

        /* Services Section */
        .services-modern {
            padding: 140px 0;
            background: white;
            position: relative;
        }

        .service-card {
            background: white;
            border-radius: 25px;
            padding: 50px 35px;
            text-align: center;
            box-shadow: var(--shadow-soft);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(0, 0, 0, 0.05);
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .service-card:hover {
            transform: translateY(-15px);
            box-shadow: var(--shadow-hover);
        }

        .service-icon {
            font-size: 3.5rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 25px;
            transition: all 0.3s ease;
        }

        .service-card:hover .service-icon {
            transform: scale(1.1);
        }

        .service-card h5 {
            font-weight: 700;
            margin-bottom: 20px;
            color: #1e293b;
            font-size: 1.3rem;
        }

        .service-card p {
            color: #64748b;
            font-size: 1rem;
            line-height: 1.6;
        }

        /* CTA Section */
        .cta-modern {
            padding: 140px 0;
            background: var(--primary-gradient);
            position: relative;
            overflow: hidden;
        }

        .cta-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(var(--hero-overlay), var(--hero-overlay)), 
                        url('assets/images/workh.png') center center/cover no-repeat;
            animation: floatBg 20s ease-in-out infinite;
        }

        .cta-content {
            text-align: center;
            color: white;
            position: relative;
            z-index: 2;
        }

        .cta-content h2 {
            font-size: clamp(2.5rem, 6vw, 4rem);
            font-weight: 900;
            margin-bottom: 25px;
            text-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            letter-spacing: -0.02em;
        }

        .cta-content p {
            font-size: 1.4rem;
            opacity: 0.95;
            margin-bottom: 50px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .btn-cta {
            background: white;
            color: #667eea;
            border: none;
            border-radius: 50px;
            padding: 20px 50px;
            font-size: 1.3rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 20px 40px rgba(255, 255, 255, 0.3);
            position: relative;
            overflow: hidden;
        }

        .btn-cta::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.1), transparent);
            transition: all 0.5s;
        }

        .btn-cta:hover::before {
            left: 100%;
        }

        .btn-cta:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 30px 60px rgba(255, 255, 255, 0.4);
            color: #667eea;
        }

        /* Footer */
        .footer-modern {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: white;
            padding: 80px 0 40px;
            position: relative;
        }

        .footer-brand {
            font-size: 1.8rem;
            font-weight: 900;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 25px;
        }

        .footer-text {
            color: #94a3b8;
            line-height: 1.8;
            font-size: 1.1rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .hero-content h1 { font-size: 2.8rem; }
            .hero-content p { font-size: 1.2rem; }
            .section-title h2 { font-size: 2.8rem; }
            .stat-number { font-size: 3rem; }
            .feature-card { padding: 40px 25px; }
            .floating-element { display: none; }
            .hero-buttons .btn { margin: 0 10px 15px 0; padding: 15px 30px; }
            .stats-modern { margin-top: -30px; }
            .stats-container { padding: 40px 20px; }
        }

        /* Loading animation */
        .loading {
            opacity: 0;
            animation: fadeInUp 1s ease forwards;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 12px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-gradient);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-gradient);
        }

        /* Ripple effect */
        .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            transform: scale(0);
            animation: ripple-animation 0.6s linear;
            pointer-events: none;
        }

        @keyframes ripple-animation {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }

        @keyframes floatBg {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            25% { transform: translateY(-10px) rotate(2deg); }
            50% { transform: translateY(-20px) rotate(-2deg); }
            75% { transform: translateY(-10px) rotate(1deg); }
        }
    </style>
</head>
<body class="loading">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top modern-navbar" id="mainNavbar">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-tools me-2"></i><?php echo APP_NAME; ?>
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#services">Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                    <li class="nav-item ms-3">
                        <a class="btn btn-modern text-white" href="register.php">Get Started</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-modern">
        <div class="hero-decorations">
           
            </div>
        </div>
        
        <div class="container">
            <div class="row align-items-center min-vh-100">
                <div class="col-lg-7" data-aos="fade-right" data-aos-duration="1000">
                    <div class="hero-content">
                        <h1>Connect. Work. <span class="hero-highlight">Succeed.</span></h1>
                        <p>Transform the way you work with our revolutionary platform that connects skilled professionals with clients who need their expertise. Experience the future of freelancing today.</p>
                        <div class="hero-buttons">
                            <a href="register.php" class="btn btn-hero-primary">
                                <i class="fas fa-rocket me-2"></i>Start Your Journey
                            </a>
                            <a href="#features" class="btn btn-hero-secondary">
                                <i class="fas fa-play me-2"></i>Learn More
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5" data-aos="fade-left" data-aos-duration="1000" data-aos-delay="200">
                    <!-- Additional hero content can be added here -->
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-modern">
        <div class="container">
            <div class="stats-container" data-aos="fade-up" data-aos-duration="800">
                <div class="row g-4">
                    <div class="col-lg-4">
                        <div class="stat-card">
                            <div class="stat-number" data-count="<?php echo $stats['users']; ?>">0</div>
                            <div class="stat-label">Active Users</div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="stat-card">
                            <div class="stat-number" data-count="<?php echo $stats['jobs']; ?>">0</div>
                            <div class="stat-label">Jobs Posted</div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="stat-card">
                            <div class="stat-number" data-count="<?php echo $stats['completed']; ?>">0</div>
                            <div class="stat-label">Jobs Completed</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-modern" id="features">
        <div class="container">
            <div class="section-title" data-aos="fade-up" data-aos-duration="800">
                <h2>How It Works</h2>
                <p>Three simple steps to connect and get work done efficiently</p>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-4" data-aos="fade-up" data-aos-duration="800" data-aos-delay="100">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <h5>Create Your Profile</h5>
                        <p>Build a comprehensive profile showcasing your skills, experience, and expertise. Upload your portfolio and set your rates to attract the right clients.</p>
                    </div>
                </div>
                
                <div class="col-lg-4" data-aos="fade-up" data-aos-duration="800" data-aos-delay="200">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <h5>Find & Connect</h5>
                        <p>Browse through thousands of opportunities or let clients find you. Our smart matching system connects you with projects that fit your skills perfectly.</p>
                    </div>
                </div>
                
                <div class="col-lg-4" data-aos="fade-up" data-aos-duration="800" data-aos-delay="300">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <h5>Achieve Success</h5>
                        <p>Complete projects with confidence using our secure platform. Build your reputation, earn great reviews, and grow your freelancing business.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="services-modern" id="services">
        <div class="container">
            <div class="section-title" data-aos="fade-up" data-aos-duration="800">
                <h2>Popular Services</h2>
                <p>Find skilled professionals for any project you have in mind</p>
            </div>
            
            <div class="row g-4">
                <?php
                $services_query = "SELECT sc.name, sc.icon, COUNT(jp.id) as job_count 
                                  FROM service_categories sc 
                                  LEFT JOIN services s ON sc.id = s.category_id 
                                  LEFT JOIN job_postings jp ON s.id = jp.service_id 
                                  WHERE sc.is_active = 1 
                                  GROUP BY sc.id 
                                  ORDER BY job_count DESC 
                                  LIMIT 6";
                $services_result = $conn->query($services_query);
                
                if ($services_result && $services_result->num_rows > 0):
                    $delay = 100;
                    while ($service = $services_result->fetch_assoc()):
                ?>
                    <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="<?php echo $delay; ?>">
                        <div class="service-card">
                            <div class="service-icon">
                                <i class="<?php echo htmlspecialchars($service['icon']); ?>"></i>
                            </div>
                            <h5><?php echo htmlspecialchars($service['name']); ?></h5>
                            <p><?php echo $service['job_count']; ?> projects completed</p>
                        </div>
                    </div>
                <?php 
                    $delay += 100;
                    endwhile;
                else:
                ?>
                    <div class="col-12 text-center">
                        <p class="text-muted">Services will be displayed here as they become available.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-modern">
        <div class="container">
            <div class="cta-content" data-aos="fade-up" data-aos-duration="1000">
                <h2>Ready to Transform Your Career?</h2>
                <p>Join thousands of professionals who are already earning more and working smarter</p>
                <a href="register.php" class="btn btn-cta">
                    <i class="fas fa-rocket me-2"></i>Get Started Today
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer-modern">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="footer-brand">
                        <i class="fas fa-tools me-2"></i><?php echo APP_NAME; ?>
                    </div>
                    <p class="footer-text">Empowering professionals and businesses to achieve more through meaningful connections and quality collaborations.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="footer-text mb-0">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
                    <p class="footer-text">Built with ❤️ for the future of work</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
        // Initialize AOS (Animate On Scroll)
        AOS.init({
            duration: 1000,
            easing: 'ease-in-out-cubic',
            once: true,
            offset: 50,
            delay: 100
        });

        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('mainNavbar');
            if (window.scrollY > 100) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    const navHeight = document.getElementById('mainNavbar').offsetHeight;
                    const targetPosition = target.offsetTop - navHeight - 20;
                    
                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Counter animation with improved performance
        function animateCounters() {
            const counters = document.querySelectorAll('.stat-number');
            const duration = 2000;
            
            counters.forEach(counter => {
                const target = +counter.getAttribute('data-count');
                const increment = target / (duration / 16);
                let current = 0;
                
                const updateCounter = () => {
                    if (current < target) {
                        current += increment;
                        counter.textContent = Math.floor(current);
                        requestAnimationFrame(updateCounter);
                    } else {
                        counter.textContent = target;
                    }
                };
                
                updateCounter();
            });
        }

        // Trigger counter animation when stats section is visible
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounters();
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        const statsSection = document.querySelector('.stats-modern');
        if (statsSection) {
            observer.observe(statsSection);
        }

        // Enhanced parallax effect
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const hero = document.querySelector('.hero-modern');
            const floatingElements = document.querySelectorAll('.floating-element');
            
            if (hero) {
                hero.style.transform = `translateY(${scrolled * 0.3}px)`;
            }
            
            floatingElements.forEach((element, index) => {
                const speed = 0.5 + (index * 0.1);
                element.style.transform = `translateY(${scrolled * speed}px) rotate(${scrolled * 0.01}deg)`;
            });
        });

        // Add loading animation
        window.addEventListener('load', function() {
            document.body.classList.remove('loading');
            
            // Add stagger animation to elements
            const elements = document.querySelectorAll('.feature-card, .service-card');
            elements.forEach((el, index) => {
                el.style.animationDelay = `${index * 0.1}s`;
            });
        });

        // Enhanced hover effects for cards
        document.querySelectorAll('.feature-card, .service-card, .stat-card').forEach(card => {
            card.addEventListener('mouseenter', function(e) {
                this.style.transform = 'translateY(-15px) scale(1.02)';
                this.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
            });
            
            card.addEventListener('mouseleave', function(e) {
                this.style.transform = 'translateY(0) scale(1)';
            });
            
            // Add tilt effect
            card.addEventListener('mousemove', function(e) {
                const rect = this.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                const rotateX = (y - centerY) / 10;
                const rotateY = (centerX - x) / 10;
                
                this.style.transform = `translateY(-15px) scale(1.02) perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1) rotateX(0) rotateY(0)';
            });
        });

        // Enhanced ripple effect
        document.querySelectorAll('.btn').forEach(button => {
            button.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.classList.add('ripple');
                
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });

        // Navbar hide/show on scroll
        let lastScrollTop = 0;
        const navbar = document.getElementById('mainNavbar');
        
        window.addEventListener('scroll', function() {
            let scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            if (scrollTop > lastScrollTop && scrollTop > 100) {
                navbar.style.transform = 'translateY(-100%)';
            } else {
                navbar.style.transform = 'translateY(0)';
            }
            
            lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
        }, false);

        // Performance optimization: Throttle scroll events
        function throttle(func, wait, immediate) {
            let timeout;
            return function() {
                const context = this, args = arguments;
                const later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                const callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        }

        // Add intersection observer for animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const animateObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe all animated elements
        document.querySelectorAll('[data-aos]').forEach(el => {
            animateObserver.observe(el);
        });
    </script>
</body>
</html>
