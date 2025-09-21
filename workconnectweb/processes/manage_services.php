<?php
session_start();
require_once '../config/database.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$action = isset($_POST['action']) ? $_POST['action'] : '';

try {
    switch ($action) {
        case 'get_all':
            getAllServices($db);
            break;
        case 'create':
            createService($db);
            break;
        case 'update':
            updateService($db);
            break;
        case 'delete':
            deleteService($db);
            break;
        case 'toggle_status':
            toggleServiceStatus($db);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Error in manage_services.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred']);
}

function getAllServices($db) {
    $query = "SELECT s.id, s.name, s.description, s.category_id, s.price_range, 
                     s.status, s.created_at, c.name as category_name,
                     COUNT(j.id) as job_count
              FROM services s 
              LEFT JOIN categories c ON s.category_id = c.id
              LEFT JOIN jobs j ON s.id = j.service_id 
              GROUP BY s.id 
              ORDER BY c.name ASC, s.name ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $services]);
}

function createService($db) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $price_range = trim($_POST['price_range'] ?? '');
    
    if (empty($name) || $category_id <= 0) {
        echo json_encode(['error' => 'Service name and category are required']);
        return;
    }
    
    // Check if service already exists in this category
    $checkQuery = "SELECT id FROM services WHERE name = :name AND category_id = :category_id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':name', $name);
    $checkStmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
    $checkStmt->execute();
    
    if ($checkStmt->fetch()) {
        echo json_encode(['error' => 'Service already exists in this category']);
        return;
    }
    
    $query = "INSERT INTO services (name, description, category_id, price_range, status, created_at) 
              VALUES (:name, :description, :category_id, :price_range, 'active', NOW())";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
    $stmt->bindParam(':price_range', $price_range);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Service created successfully']);
    } else {
        echo json_encode(['error' => 'Failed to create service']);
    }
}

function updateService($db) {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $price_range = trim($_POST['price_range'] ?? '');
    
    if ($id <= 0 || empty($name) || $category_id <= 0) {
        echo json_encode(['error' => 'Invalid data provided']);
        return;
    }
    
    $query = "UPDATE services SET name = :name, description = :description, 
              category_id = :category_id, price_range = :price_range 
              WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
    $stmt->bindParam(':price_range', $price_range);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Service updated successfully']);
    } else {
        echo json_encode(['error' => 'Failed to update service']);
    }
}

function deleteService($db) {
    $id = (int)($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['error' => 'Invalid service ID']);
        return;
    }
    
    // Check if service has jobs
    $checkQuery = "SELECT COUNT(*) as job_count FROM jobs WHERE service_id = :id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':id', $id, PDO::PARAM_INT);
    $checkStmt->execute();
    $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['job_count'] > 0) {
        echo json_encode(['error' => 'Cannot delete service with existing jobs']);
        return;
    }
    
    $query = "DELETE FROM services WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Service deleted successfully']);
    } else {
        echo json_encode(['error' => 'Failed to delete service']);
    }
}

function toggleServiceStatus($db) {
    $id = (int)($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['error' => 'Invalid service ID']);
        return;
    }
    
    $query = "UPDATE services SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Service status updated successfully']);
    } else {
        echo json_encode(['error' => 'Failed to update service status']);
    }
}
?>
