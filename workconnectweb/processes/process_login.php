<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';

header('Content-Type: application/json');

// Rate limiting
if (!Security::rateLimitCheck('login', 5, 300)) {
    echo json_encode(['status' => 'error', 'message' => 'Too many login attempts. Please try again later.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

// Validate CSRF token
if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid security token']);
    exit;
}

$email = Security::sanitizeInput($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Basic validation
if (empty($email) || empty($password)) {
    echo json_encode(['status' => 'error', 'message' => 'Email and password are required']);
    exit;
}

if (!Security::validateEmail($email)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
    exit;
}

try {
    // Get user data
    $stmt = $conn->prepare("SELECT id, name, email, password, role, status FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email or password']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    
    // Check account status
    if ($user['status'] !== 'active') {
        echo json_encode(['status' => 'error', 'message' => 'Account is disabled. Please contact support.']);
        exit;
    }
    
    // Verify password
    if (!Security::verifyPassword($password, $user['password'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email or password']);
        exit;
    }
    
    // Login successful
    SessionManager::login($user);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Login successful! Welcome back, ' . htmlspecialchars($user['name']),
        'redirect' => SessionManager::getDashboardUrl($user['role'])
    ]);

} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'An error occurred. Please try again.']);
}

$conn->close();
?>
