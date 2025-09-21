<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';

header('Content-Type: application/json');

SessionManager::requireRole(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

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
$role = in_array($_POST['role'] ?? '', ['customer', 'worker']) ? $_POST['role'] : 'customer';
$status = in_array($_POST['status'] ?? '', ['active', 'disabled']) ? $_POST['status'] : 'active';

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

if (empty($password)) {
    $errors[] = 'Password is required';
} else {
    $password_errors = Security::validatePassword($password);
    if (!empty($password_errors)) {
        $errors = array_merge($errors, $password_errors);
    }
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
    $stmt = $conn->prepare("INSERT INTO users (name, email, telephone, address, password, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $name, $email, $telephone, $address, $hashed_password, $role, $status);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to create user account");
    }
    
    $user_id = $conn->insert_id;
    
    // Create worker profile if role is worker
    if ($role === 'worker') {
        $stmt = $conn->prepare("INSERT INTO worker_profiles (user_id) VALUES (?)");
        $stmt->bind_param("i", $user_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create worker profile");
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'User created successfully!',
        'user_id' => $user_id
    ]);

} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    
    error_log("Admin add user error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'An error occurred while creating the user. Please try again.']);
}

$conn->close();
?>
