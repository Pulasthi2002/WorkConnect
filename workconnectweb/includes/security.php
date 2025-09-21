<?php
class Security {
    
    public static function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public static function validatePhone($phone) {
        $pattern = '/^(\+94|0)?[1-9][0-9]{8}$/';
        return preg_match($pattern, preg_replace('/\s+/', '', $phone));
    }
    
    public static function validatePassword($password) {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain uppercase letter';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain lowercase letter';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain number';
        }
        
        return $errors;
    }
    
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public static function rateLimitCheck($action, $max_attempts = 5, $window = 300) {
        $key = $action . '_' . ($_SERVER['REMOTE_ADDR'] ?? '');
        $current_time = time();
        
        if (!isset($_SESSION['rate_limit'][$key])) {
            $_SESSION['rate_limit'][$key] = ['count' => 1, 'reset_time' => $current_time + $window];
            return true;
        }
        
        $data = &$_SESSION['rate_limit'][$key];
        
        if ($current_time > $data['reset_time']) {
            $data['count'] = 1;
            $data['reset_time'] = $current_time + $window;
            return true;
        }
        
        if ($data['count'] >= $max_attempts) {
            return false;
        }
        
        $data['count']++;
        return true;
    }
}
?>
