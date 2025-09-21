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

$export_type = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'csv';

try {
    switch ($export_type) {
        case 'jobs':
            exportJobs($db, $format);
            break;
        case 'users':
            exportUsers($db, $format);
            break;
        case 'applications':
            exportApplications($db, $format);
            break;
        case 'categories':
            exportCategories($db, $format);
            break;
        case 'services':
            exportServices($db, $format);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid export type']);
    }
} catch (Exception $e) {
    error_log("Error in export_data.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Export failed']);
}

function exportJobs($db, $format) {
    $query = "SELECT j.id, j.title, j.description, j.budget, j.location, j.status,
                     j.created_at, j.deadline, u.name as posted_by, u.email as posted_by_email,
                     c.name as category_name,
                     (SELECT COUNT(*) FROM job_applications ja WHERE ja.job_id = j.id) as application_count
              FROM jobs j 
              LEFT JOIN users u ON j.posted_by = u.id 
              LEFT JOIN categories c ON j.category_id = c.id 
              ORDER BY j.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $headers = ['ID', 'Title', 'Description', 'Budget', 'Location', 'Status', 'Created At', 'Deadline', 'Posted By', 'Posted By Email', 'Category', 'Applications'];
    
    outputFile('jobs_export', $data, $headers, $format);
}

function exportUsers($db, $format) {
    $query = "SELECT id, name, email, role, status, created_at, last_login,
                     phone, location, skills, experience_level
              FROM users 
              ORDER BY created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $headers = ['ID', 'Name', 'Email', 'Role', 'Status', 'Created At', 'Last Login', 'Phone', 'Location', 'Skills', 'Experience Level'];
    
    outputFile('users_export', $data, $headers, $format);
}

function exportApplications($db, $format) {
    $query = "SELECT ja.id, j.title as job_title, u.name as applicant_name, u.email as applicant_email,
                     ja.cover_letter, ja.proposed_rate, ja.status, ja.applied_at,
                     employer.name as employer_name
              FROM job_applications ja 
              JOIN jobs j ON ja.job_id = j.id 
              JOIN users u ON ja.user_id = u.id 
              JOIN users employer ON j.posted_by = employer.id 
              ORDER BY ja.applied_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $headers = ['ID', 'Job Title', 'Applicant Name', 'Applicant Email', 'Cover Letter', 'Proposed Rate', 'Status', 'Applied At', 'Employer'];
    
    outputFile('applications_export', $data, $headers, $format);
}

function exportCategories($db, $format) {
    $query = "SELECT c.id, c.name, c.description, c.status, c.created_at,
                     COUNT(j.id) as job_count
              FROM categories c 
              LEFT JOIN jobs j ON c.id = j.category_id 
              GROUP BY c.id 
              ORDER BY c.name ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $headers = ['ID', 'Name', 'Description', 'Status', 'Created At', 'Job Count'];
    
    outputFile('categories_export', $data, $headers, $format);
}

function exportServices($db, $format) {
    $query = "SELECT s.id, s.name, s.description, c.name as category_name, 
                     s.price_range, s.status, s.created_at,
                     COUNT(j.id) as job_count
              FROM services s 
              LEFT JOIN categories c ON s.category_id = c.id
              LEFT JOIN jobs j ON s.id = j.service_id 
              GROUP BY s.id 
              ORDER BY c.name ASC, s.name ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $headers = ['ID', 'Name', 'Description', 'Category', 'Price Range', 'Status', 'Created At', 'Job Count'];
    
    outputFile('services_export', $data, $headers, $format);
}

function outputFile($filename, $data, $headers, $format) {
    $timestamp = date('Y-m-d_H-i-s');
    $filename = $filename . '_' . $timestamp;
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, $headers);
        
        foreach ($data as $row) {
            // Clean data for CSV
            $cleanRow = [];
            foreach ($row as $value) {
                $cleanRow[] = is_null($value) ? '' : str_replace(["\r", "\n"], ' ', $value);
            }
            fputcsv($output, $cleanRow);
        }
        
        fclose($output);
    } else if ($format === 'json') {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '.json"');
        
        $exportData = [
            'export_date' => date('Y-m-d H:i:s'),
            'total_records' => count($data),
            'data' => $data
        ];
        
        echo json_encode($exportData, JSON_PRETTY_PRINT);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Unsupported format']);
    }
}
?>
