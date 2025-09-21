<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';

header('Content-Type: application/json');

SessionManager::requireRole(['worker']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid security token']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Flask API configuration
$api_url = 'http://127.0.0.1:5000/predict';
$api_timeout = 30;

try {
    // Prepare data for API
    $api_data = [
        'industry' => Security::sanitizeInput($_POST['industry'] ?? 'J'),
        'occupation' => intval($_POST['occupation'] ?? 2),
        'yrs_qual' => intval($_POST['yrs_qual'] ?? 12),
        'sex' => intval($_POST['sex'] ?? 1),
        'highest_qual' => intval($_POST['highest_qual'] ?? 12),
        'area_of_study' => intval($_POST['area_of_study'] ?? 5),
        'influencing' => intval($_POST['influencing'] ?? 3),
        'negotiating' => intval($_POST['negotiating'] ?? 3),
        'sector' => intval($_POST['sector'] ?? 1),
        'workforce_change' => intval($_POST['workforce_change'] ?? 1),
        'no_subordinates' => intval($_POST['no_subordinates'] ?? 1),
        'choose_hours' => intval($_POST['choose_hours'] ?? 3),
        'choose_method' => intval($_POST['choose_method'] ?? 4),
        'job_quals' => intval($_POST['job_quals'] ?? 12),
        'qual_needed' => intval($_POST['qual_needed'] ?? 1),
        'experience_needed' => intval($_POST['experience_needed'] ?? 4),
        'keeping_current' => intval($_POST['keeping_current'] ?? 4),
        'satisfaction' => intval($_POST['satisfaction'] ?? 2),
        'advising' => intval($_POST['advising'] ?? 3),
        'instructing' => intval($_POST['instructing'] ?? 2),
        'problem_solving_quick' => intval($_POST['problem_solving_quick'] ?? 4),
        'problem_solving_long' => intval($_POST['problem_solving_long'] ?? 4),
        'labour' => intval($_POST['labour'] ?? 1),
        'manual_skill' => intval($_POST['manual_skill'] ?? 2),
        'computer' => intval($_POST['computer'] ?? 1),
        'group_meetings' => intval($_POST['group_meetings'] ?? 1),
        'computer_level' => intval($_POST['computer_level'] ?? 2)
    ];
    
    // Validate required fields
    if (empty($api_data['industry']) || $api_data['occupation'] <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Industry and occupation are required']);
        exit;
    }
    
    // Initialize cURL
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $api_url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($api_data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $api_timeout,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // Check for cURL errors
    if ($curl_error) {
        throw new Exception("API connection failed: " . $curl_error);
    }
    
    // Check HTTP status
    if ($http_code !== 200) {
        throw new Exception("API returned HTTP $http_code");
    }
    
    // Parse response
    $prediction_result = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON response from API");
    }
    
    if (isset($prediction_result['error'])) {
        throw new Exception("API Error: " . $prediction_result['error']);
    }
    
    if (!isset($prediction_result['predicted_salary'])) {
        throw new Exception("Invalid response format from API");
    }
    
    // Store prediction in database for analytics
    try {
        $stmt = $conn->prepare("
            INSERT INTO salary_predictions (
                user_id, predicted_salary, prediction_data, created_at
            ) VALUES (?, ?, ?, NOW())
        ");
        
        $prediction_data_json = json_encode($api_data);
        $stmt->bind_param("ids", 
            $user_id, 
            $prediction_result['predicted_salary'], 
            $prediction_data_json
        );
        $stmt->execute();
    } catch (Exception $db_error) {
        // Log database error but don't fail the main request
        error_log("Failed to store salary prediction: " . $db_error->getMessage());
    }
    
    // Return successful prediction
    echo json_encode([
        'status' => 'success',
        'predicted_salary' => round($prediction_result['predicted_salary'], 2),
        'currency' => $prediction_result['currency'] ?? 'USD',
        'period' => $prediction_result['period'] ?? 'monthly',
        'api_data' => $api_data // For debugging
    ]);

} catch (Exception $e) {
    error_log("Salary prediction error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error', 
        'message' => 'Failed to predict salary: ' . $e->getMessage()
    ]);
}
?>
