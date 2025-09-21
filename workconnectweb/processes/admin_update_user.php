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

$user_id = intval($_POST['user_id'] ?? 0);
$name = Security::sanitizeInput($_POST['name'] ?? '');
$email = Security::sanitizeInput($_POST['email'] ?? '');
$telephone = Security::sanitizeInput($_POST['telephone'] ?? '');
$address = Security::sanitizeInput($_POST['address'] ?? '');
$password = $_POST['password'] ?? '';
$role = in_array($_POST['role'] ?? '', ['customer', 'worker']) ? $_POST['role'] : 'customer';
$status = in_array($_POST['status'] ?? '', ['active', 'disabled']) ? $_POST['status'] : 'active';

// Validation
$errors = [];

if ($user_id <= 0) {
    $errors[] = 'Invalid user ID';
}

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

if (!empty($password)) {
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
    // Check if user exists and is not admin
    $stmt = $conn->prepare("SELECT id, role FROM users WHERE id = ? AND role != 'admin'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit;
    }
    
    $current_user = $result->fetch_assoc();
    
    // Check if email already exists for other users
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'An account with this email already exists']);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    // Update user
    if (!empty($password)) {
        $hashed_password = Security::hashPassword($password);
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, telephone = ?, address = ?, password = ?, role = ?, status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("sssssssi", $name, $email, $telephone, $address, $hashed_password, $role, $status, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, telephone = ?, address = ?, role = ?, status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("ssssssi", $name, $email, $telephone, $address, $role, $status, $user_id);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update user");
    }
    
    // Handle role changes
    if ($current_user['role'] !== $role) {
        if ($role === 'worker' && $current_user['role'] === 'customer') {
            // Create worker profile
            $stmt = $conn->prepare("INSERT INTO worker_profiles (user_id) VALUES (?)");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
        } elseif ($role === 'customer' && $current_user['role'] === 'worker') {
            // Remove worker profile and related data
            $stmt = $conn->prepare("DELETE FROM worker_profiles WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'User updated successfully!'
    ]);

} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    
    error_log("Admin update user error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'An error occurred while updating the user. Please try again.']);
}

$conn->close();
?>
