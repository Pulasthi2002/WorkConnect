<?php
// Include config.php first to define constants
require_once __DIR__ . '/../config.php';

class SessionManager {
    
    public static function isLoggedIn() {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_id']) && isset($_SESSION['user_email']);
    }
    
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header("Location: " . APP_URL . "/login.php");
            exit;
        }
    }
    
    public static function requireRole($allowed_roles) {
        self::requireLogin();
        
        if (!in_array($_SESSION['role'] ?? '', $allowed_roles)) {
            header("Location: " . APP_URL . "/index.php");
            exit;
        }
    }
    
    public static function login($user_data) {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user_data['id'];
        $_SESSION['user_name'] = $user_data['name'];
        $_SESSION['user_email'] = $user_data['email'];
        $_SESSION['role'] = $user_data['role'];
        $_SESSION['login_time'] = time();
        
        return true;
    }
    
    public static function logout() {
        // Ensure session is started before destroying
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Clear all session variables
        $_SESSION = array();
        
        // Clear session cookie if it exists
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Only destroy if session is active
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        // Redirect to index page
        header("Location: " . APP_URL . "/index.php?logout=1");
        exit;
    }
    
    public static function getDashboardUrl($role) {
        $dashboards = [
            'admin' => '/admin/dashboard.php',
            'worker' => '/worker/dashboard.php',
            'customer' => '/customer/dashboard.php'
        ];
        
        return APP_URL . ($dashboards[$role] ?? '/customer/dashboard.php');
    }
    
    public static function getCurrentUser() {
        if (!self::isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email'],
            'role' => $_SESSION['role']
        ];
    }
    
    public static function checkSessionTimeout() {
        if (self::isLoggedIn()) {
            $login_time = $_SESSION['login_time'] ?? 0;
            if (time() - $login_time > SESSION_LIFETIME) {
                self::logout();
            }
        }
    }
}
?>