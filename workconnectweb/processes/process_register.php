<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';

header('Content-Type: application/json');

// Rate limiting
if (!Security::rateLimitCheck('register', 3, 600)) {
    echo json_encode(['status' => 'error', 'message' => 'Too many registration attempts. Please try again later.']);
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

// Sanitize input
$name = Security::sanitizeInput($_POST['name'] ?? '');
$email = Security::sanitizeInput($_POST['email'] ?? '');
$telephone = Security::sanitizeInput($_POST['telephone'] ?? '');
$address = Security::sanitizeInput($_POST['address'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$role = in_array($_POST['role'] ?? '', ['customer', 'worker']) ? $_POST['role'] : 'customer';
$terms_agreed = isset($_POST['terms_agreed']);

// Worker-specific fields
$service_category = $role === 'worker' ? intval($_POST['service_category'] ?? 0) : null;
$experience_years = $role === 'worker' ? intval($_POST['experience_years'] ?? 0) : 0;
$hourly_rate = $role === 'worker' && !empty($_POST['hourly_rate']) ? floatval($_POST['hourly_rate']) : null;

// Validation
$errors = [];

if (empty($name) || strlen($name) < 2 || strlen($name) > 100) {
    $errors[] = 'Name must be between 2 and 100 characters';
}

if (empty($email) || !Security::validateEmail($email)) {
    $errors[] = 'Valid email address is required';
}

if (empty($telephone) || !Security::validatePhone($telephone)) {
    $errors[] = 'Valid phone number is required';
}

if (empty($address) || strlen($address) < 3 || strlen($address) > 255) {
    $errors[] = 'Address must be between 3 and 255 characters';
}

// Password validation
$password_errors = Security::validatePassword($password);
if (!empty($password_errors)) {
    $errors = array_merge($errors, $password_errors);
}

if ($password !== $confirm_password) {
    $errors[] = 'Passwords do not match';
}

if (!$terms_agreed) {
    $errors[] = 'You must agree to the terms and conditions';
}

if (!empty($errors)) {
    echo json_encode(['status' => 'error', 'message' => implode('. ', $errors)]);
    exit;
}

try {
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'An account with this email already exists']);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    // Hash password
    $hashed_password = Security::hashPassword($password);
    
    // Insert user
    $stmt = $conn->prepare("INSERT INTO users (name, email, telephone, address, password, role) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $name, $email, $telephone, $address, $hashed_password, $role);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to create user account");
    }
    
    $user_id = $conn->insert_id;
    
    // Create worker profile if role is worker
    if ($role === 'worker') {
        $stmt = $conn->prepare("INSERT INTO worker_profiles (user_id, experience_years, hourly_rate_min) VALUES (?, ?, ?)");
        $stmt->bind_param("iid", $user_id, $experience_years, $hourly_rate);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create worker profile");
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Account created successfully! You can now login with your credentials.',
        'redirect' => 'login.php?registered=1'
    ]);

} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    
    error_log("Registration error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'An error occurred while creating your account. Please try again.']);
}

$conn->close();
?>
