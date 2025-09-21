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
            getAllCategories($db);
            break;
        case 'create':
            createCategory($db);
            break;
        case 'update':
            updateCategory($db);
            break;
        case 'delete':
            deleteCategory($db);
            break;
        case 'toggle_status':
            toggleCategoryStatus($db);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Error in manage_categories.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred']);
}

function getAllCategories($db) {
    $query = "SELECT c.id, c.name, c.description, c.status, c.created_at,
                     COUNT(j.id) as job_count
              FROM categories c 
              LEFT JOIN jobs j ON c.id = j.category_id 
              GROUP BY c.id 
              ORDER BY c.name ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $categories]);
}

function createCategory($db) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (empty($name)) {
        echo json_encode(['error' => 'Category name is required']);
        return;
    }
    
    // Check if category already exists
    $checkQuery = "SELECT id FROM categories WHERE name = :name";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':name', $name);
    $checkStmt->execute();
    
    if ($checkStmt->fetch()) {
        echo json_encode(['error' => 'Category already exists']);
        return;
    }
    
    $query = "INSERT INTO categories (name, description, status, created_at) 
              VALUES (:name, :description, 'active', NOW())";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':description', $description);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Category created successfully']);
    } else {
        echo json_encode(['error' => 'Failed to create category']);
    }
}

function updateCategory($db) {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if ($id <= 0 || empty($name)) {
        echo json_encode(['error' => 'Invalid data provided']);
        return;
    }
    
    $query = "UPDATE categories SET name = :name, description = :description 
              WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Category updated successfully']);
    } else {
        echo json_encode(['error' => 'Failed to update category']);
    }
}

function deleteCategory($db) {
    $id = (int)($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['error' => 'Invalid category ID']);
        return;
    }
    
    // Check if category has jobs
    $checkQuery = "SELECT COUNT(*) as job_count FROM jobs WHERE category_id = :id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':id', $id, PDO::PARAM_INT);
    $checkStmt->execute();
    $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['job_count'] > 0) {
        echo json_encode(['error' => 'Cannot delete category with existing jobs']);
        return;
    }
    
    $query = "DELETE FROM categories WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
    } else {
        echo json_encode(['error' => 'Failed to delete category']);
    }
}

function toggleCategoryStatus($db) {
    $id = (int)($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['error' => 'Invalid category ID']);
        return;
    }
    
    $query = "UPDATE categories SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Category status updated successfully']);
    } else {
        echo json_encode(['error' => 'Failed to update category status']);
    }
}
?>
