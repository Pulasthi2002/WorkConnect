<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

SessionManager::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid security token']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$profile_type = Security::sanitizeInput($_POST['profile_type'] ?? 'basic');

try {
    $conn->begin_transaction();
    
    if ($profile_type === 'basic') {
        // Basic Information Update
        $name = Security::sanitizeInput($_POST['name'] ?? '');
        $telephone = Security::sanitizeInput($_POST['telephone'] ?? '');
        $address = Security::sanitizeInput($_POST['address'] ?? '');
        $bio = Security::sanitizeInput($_POST['bio'] ?? '');

        // Validation
        $errors = [];

        if (empty($name) || strlen($name) < 2) {
            $errors[] = 'Name must be at least 2 characters';
        }

        if (empty($telephone) || !Security::validatePhone($telephone)) {
            $errors[] = 'Valid phone number is required';
        }

        if (empty($address) || strlen($address) < 3) {
            $errors[] = 'Address must be at least 3 characters';
        }

        if (!empty($errors)) {
            echo json_encode(['status' => 'error', 'message' => implode('. ', $errors)]);
            exit;
        }

        // Update basic user information
        $stmt = $conn->prepare("UPDATE users SET name = ?, telephone = ?, address = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("sssi", $name, $telephone, $address, $user_id);
        $stmt->execute();

        // Update worker bio if worker role
        if ($user_role === 'worker' && !empty($bio)) {
            $stmt = $conn->prepare("
                INSERT INTO worker_profiles (user_id, bio, created_at) 
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE bio = VALUES(bio)
            ");
            $stmt->bind_param("is", $user_id, $bio);
            $stmt->execute();
        }

        // Update session data
        $_SESSION['user_name'] = $name;
        
        $conn->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Basic information updated successfully!',
            'user_name' => $name
        ]);

    } elseif ($profile_type === 'professional' && $user_role === 'worker') {
        // Professional Information Update
        $experience_years = intval($_POST['experience_years'] ?? 0);
        $hourly_rate_min = !empty($_POST['hourly_rate_min']) ? floatval($_POST['hourly_rate_min']) : null;
        $hourly_rate_max = !empty($_POST['hourly_rate_max']) ? floatval($_POST['hourly_rate_max']) : null;
        $is_available = isset($_POST['is_available']) ? 1 : 0;

        // Validation
        if ($hourly_rate_min !== null && $hourly_rate_max !== null && $hourly_rate_min > $hourly_rate_max) {
            echo json_encode(['status' => 'error', 'message' => 'Minimum rate cannot be higher than maximum rate']);
            exit;
        }

        // Update worker profile
        $stmt = $conn->prepare("
            INSERT INTO worker_profiles (user_id, experience_years, hourly_rate_min, hourly_rate_max, is_available, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                experience_years = VALUES(experience_years),
                hourly_rate_min = VALUES(hourly_rate_min),
                hourly_rate_max = VALUES(hourly_rate_max),
                is_available = VALUES(is_available)
        ");
        $stmt->bind_param("idddi", $user_id, $experience_years, $hourly_rate_min, $hourly_rate_max, $is_available);
        $stmt->execute();
        
        $conn->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Professional information updated successfully!'
        ]);

    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid profile type']);
        exit;
    }

} catch (Exception $e) {
    $conn->rollback();
    error_log("Update profile error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to update profile. Please try again.']);
}

$conn->close();
?>
