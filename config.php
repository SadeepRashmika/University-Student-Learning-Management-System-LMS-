<?php
/**
 * University LMS Configuration File
 * Updated Version with Enhanced Features
 * This file contains all core configuration settings and helper functions
 */

// ==================== DATABASE CONFIGURATION ====================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'university_lms');

// ==================== APPLICATION CONFIGURATION ====================
define('SITE_URL', 'http://localhost/university_lms');
define('SITE_NAME', 'University LMS');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 10485760); // 10MB in bytes
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'zip', 'txt', 'jpg', 'jpeg', 'png', 'gif']);

// ==================== SESSION CONFIGURATION ====================
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
ini_set('session.cookie_lifetime', 0);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Session timeout (30 minutes of inactivity)
define('SESSION_TIMEOUT', 1800);

// Check session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    session_unset();
    session_destroy();
    header('Location: login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// ==================== DATABASE FUNCTIONS ====================

/**
 * Create database connection
 * @return mysqli Database connection object
 */
function getDBConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            error_log("Database Connection Error: " . $conn->connect_error);
            die("Unable to connect to database. Please try again later.");
        }
        
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Exception $e) {
        error_log("Database Exception: " . $e->getMessage());
        die("Database connection error. Please contact administrator.");
    }
}

/**
 * Execute a prepared statement and return results
 * @param mysqli $conn Database connection
 * @param string $sql SQL query with placeholders
 * @param string $types Parameter types (i, s, d, b)
 * @param array $params Parameters array
 * @return mysqli_result|bool Query result
 */
function executeQuery($conn, $sql, $types = '', $params = []) {
    $stmt = $conn->prepare($sql);
    
    if ($types && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    return $stmt->get_result();
}

// ==================== AUTHENTICATION FUNCTIONS ====================

/**
 * Check if user is logged in
 * @return bool True if logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

/**
 * Check if user is admin
 * @return bool True if admin, false otherwise
 */
function isAdmin() {
    return isLoggedIn() && $_SESSION['user_type'] === 'admin';
}

/**
 * Check if user is student
 * @return bool True if student, false otherwise
 */
function isStudent() {
    return isLoggedIn() && $_SESSION['user_type'] === 'student';
}

/**
 * Require user to be logged in
 * Redirects to login page if not authenticated
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
        exit();
    }
}

/**
 * Require user to be admin
 * Redirects appropriately if not admin
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: student_dashboard.php');
        exit();
    }
}

/**
 * Require user to be student
 * Redirects appropriately if not student
 */
function requireStudent() {
    requireLogin();
    if (!isStudent()) {
        header('Location: admin_dashboard.php');
        exit();
    }
}

/**
 * Get current logged in user ID
 * @return int|null User ID or null if not logged in
 */
function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/**
 * Get current logged in user type
 * @return string|null User type or null if not logged in
 */
function getCurrentUserType() {
    return isset($_SESSION['user_type']) ? $_SESSION['user_type'] : null;
}

/**
 * Login user and set session variables
 * @param int $user_id User ID
 * @param string $user_type User type (admin/student)
 * @param array $user_data Additional user data to store in session
 */
function loginUser($user_id, $user_type, $user_data = []) {
    $_SESSION['user_id'] = $user_id;
    $_SESSION['user_type'] = $user_type;
    $_SESSION['username'] = $user_data['username'] ?? '';
    $_SESSION['full_name'] = $user_data['full_name'] ?? '';
    $_SESSION['email'] = $user_data['email'] ?? '';
    $_SESSION['last_activity'] = time();
    
    // Update last login in database
    updateUserActivity($user_id);
    logActivity($user_id, 'login', 'User logged in');
}

/**
 * Logout current user
 */
function logoutUser() {
    $user_id = getCurrentUserId();
    if ($user_id) {
        logActivity($user_id, 'logout', 'User logged out');
    }
    
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit();
}

// ==================== DATA SANITIZATION & VALIDATION ====================

/**
 * Sanitize input data
 * @param mixed $data Input data to sanitize
 * @return mixed Sanitized data
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Clean string for database (prevents XSS)
 * @param string $string String to clean
 * @return string Cleaned string
 */
function cleanString($string) {
    return trim(strip_tags($string));
}

/**
 * Validate email address
 * @param string $email Email to validate
 * @return bool True if valid, false otherwise
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number
 * @param string $phone Phone number to validate
 * @return bool True if valid, false otherwise
 */
function isValidPhone($phone) {
    return preg_match('/^[0-9+\-\s()]{10,20}$/', $phone);
}

/**
 * Validate password strength
 * @param string $password Password to validate
 * @return array ['valid' => bool, 'message' => string]
 */
function validatePassword($password) {
    if (strlen($password) < 6) {
        return ['valid' => false, 'message' => 'Password must be at least 6 characters'];
    }
    if (strlen($password) > 50) {
        return ['valid' => false, 'message' => 'Password must be less than 50 characters'];
    }
    return ['valid' => true, 'message' => 'Password is valid'];
}

/**
 * Hash password securely
 * @param string $password Plain text password
 * @return string Hashed password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

/**
 * Verify password against hash
 * @param string $password Plain text password
 * @param string $hash Hashed password
 * @return bool True if password matches
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// ==================== USER FUNCTIONS ====================

/**
 * Get user information by ID
 * @param int $user_id User ID
 * @return array|null User data or null if not found
 */
function getUserInfo($user_id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $user;
}

/**
 * Get user by username
 * @param string $username Username
 * @return array|null User data or null if not found
 */
function getUserByUsername($username) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $user;
}

/**
 * Get user by email
 * @param string $email Email address
 * @return array|null User data or null if not found
 */
function getUserByEmail($email) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $user;
}

/**
 * Get current logged in user information
 * @return array|null User data or null if not logged in
 */
function getCurrentUser() {
    $user_id = getCurrentUserId();
    return $user_id ? getUserInfo($user_id) : null;
}

/**
 * Update user last activity
 * @param int $user_id User ID
 */
function updateUserActivity($user_id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

/**
 * Check if username exists
 * @param string $username Username to check
 * @param int|null $exclude_user_id User ID to exclude from check
 * @return bool True if exists, false otherwise
 */
function usernameExists($username, $exclude_user_id = null) {
    $conn = getDBConnection();
    
    if ($exclude_user_id) {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
        $stmt->bind_param("si", $username, $exclude_user_id);
    } else {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    $conn->close();
    
    return $exists;
}

/**
 * Check if email exists
 * @param string $email Email to check
 * @param int|null $exclude_user_id User ID to exclude from check
 * @return bool True if exists, false otherwise
 */
function emailExists($email, $exclude_user_id = null) {
    $conn = getDBConnection();
    
    if ($exclude_user_id) {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->bind_param("si", $email, $exclude_user_id);
    } else {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    $conn->close();
    
    return $exists;
}

// ==================== ACTIVITY LOGGING ====================

/**
 * Log user activity
 * @param int $user_id User ID
 * @param string $action Action performed
 * @param string $description Description of action
 */
function logActivity($user_id, $action, $description = '') {
    try {
        $conn = getDBConnection();
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $action, $description, $ip_address);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        error_log("Activity logging failed: " . $e->getMessage());
    }
}

/**
 * Get user activity logs
 * @param int $user_id User ID
 * @param int $limit Number of records to retrieve
 * @return array Array of activity logs
 */
function getUserActivityLogs($user_id, $limit = 50) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM activity_logs WHERE user_id = ? ORDER BY timestamp DESC LIMIT ?");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    return $logs;
}

// ==================== FLASH MESSAGES ====================

/**
 * Set flash message for next page load
 * @param string $message Message to display
 * @param string $type Message type (success, error, warning, info)
 */
function setFlashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/**
 * Get and clear flash message
 * @return array|null ['message' => string, 'type' => string] or null
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

/**
 * Check if flash message exists
 * @return bool True if flash message exists
 */
function hasFlashMessage() {
    return isset($_SESSION['flash_message']);
}

/**
 * Display alert message HTML
 * @param string $message Message to display
 * @param string $type Alert type (success, error, warning, info)
 * @return string HTML for alert
 */
function displayAlert($message, $type = 'info') {
    $alertClass = 'alert-' . $type;
    $icons = [
        'success' => '✓',
        'error' => '⚠️',
        'warning' => '⚡',
        'info' => 'ℹ️'
    ];
    $icon = $icons[$type] ?? 'ℹ️';
    return '<div class="alert ' . $alertClass . '">' . $icon . ' ' . htmlspecialchars($message) . '</div>';
}

/**
 * Redirect with flash message
 * @param string $url URL to redirect to
 * @param string $message Message to display
 * @param string $type Message type
 */
function redirectWithMessage($url, $message, $type = 'success') {
    setFlashMessage($message, $type);
    header('Location: ' . $url);
    exit();
}

// ==================== FILE HANDLING ====================

/**
 * Format file size to human readable format
 * @param int $bytes File size in bytes
 * @return string Formatted file size
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Validate uploaded file
 * @param array $file $_FILES array element
 * @return array ['valid' => bool, 'message' => string, 'extension' => string]
 */
function validateUploadedFile($file) {
    if (!isset($file['error'])) {
        return ['valid' => false, 'message' => 'No file uploaded', 'extension' => ''];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server maximum upload size',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form maximum upload size',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        
        $error_message = $errors[$file['error']] ?? 'Unknown upload error';
        return ['valid' => false, 'message' => $error_message, 'extension' => ''];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['valid' => false, 'message' => 'File size exceeds maximum allowed size (' . formatFileSize(MAX_FILE_SIZE) . ')', 'extension' => ''];
    }
    
    if ($file['size'] === 0) {
        return ['valid' => false, 'message' => 'File is empty', 'extension' => ''];
    }
    
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_ext, ALLOWED_FILE_TYPES)) {
        return ['valid' => false, 'message' => 'Invalid file type. Allowed: ' . implode(', ', ALLOWED_FILE_TYPES), 'extension' => ''];
    }
    
    return ['valid' => true, 'message' => 'File is valid', 'extension' => $file_ext];
}

/**
 * Generate unique filename
 * @param string $original_name Original filename
 * @return string Unique filename
 */
function generateUniqueFilename($original_name) {
    $extension = pathinfo($original_name, PATHINFO_EXTENSION);
    return uniqid() . '_' . time() . '.' . strtolower($extension);
}

/**
 * Delete file safely
 * @param string $file_path Path to file
 * @return bool True if deleted successfully
 */
function deleteFile($file_path) {
    if (file_exists($file_path) && is_file($file_path)) {
        return unlink($file_path);
    }
    return false;
}

/**
 * Get file extension
 * @param string $filename Filename
 * @return string File extension
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Get file icon based on extension
 * @param string $extension File extension
 * @return string Emoji icon
 */
function getFileIcon($extension) {
    $icons = [
        'pdf' => '📕',
        'doc' => '📘',
        'docx' => '📘',
        'ppt' => '📊',
        'pptx' => '📊',
        'xls' => '📗',
        'xlsx' => '📗',
        'zip' => '📦',
        'txt' => '📄',
        'jpg' => '🖼️',
        'jpeg' => '🖼️',
        'png' => '🖼️',
        'gif' => '🖼️'
    ];
    
    return $icons[$extension] ?? '📄';
}

// ==================== UTILITY FUNCTIONS ====================

/**
 * Format date for display
 * @param string $date Date string
 * @param string $format Date format
 * @return string Formatted date
 */
function formatDate($date, $format = 'M j, Y') {
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    return date($format, strtotime($date));
}

/**
 * Format datetime for display
 * @param string $datetime Datetime string
 * @param string $format Datetime format
 * @return string Formatted datetime
 */
function formatDateTime($datetime, $format = 'M j, Y g:i A') {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    return date($format, strtotime($datetime));
}

/**
 * Calculate time ago
 * @param string $datetime Datetime string
 * @return string Time ago string
 */
function timeAgo($datetime) {
    if (empty($datetime)) {
        return 'N/A';
    }
    
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    
    return formatDateTime($datetime);
}

/**
 * Generate random string
 * @param int $length Length of string
 * @return string Random string
 */
function generateRandomString($length = 10) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Truncate text
 * @param string $text Text to truncate
 * @param int $length Maximum length
 * @param string $suffix Suffix to add
 * @return string Truncated text
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

/**
 * Generate slug from string
 * @param string $string String to convert
 * @return string URL-friendly slug
 */
function generateSlug($string) {
    $string = strtolower(trim($string));
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

/**
 * Check if string is JSON
 * @param string $string String to check
 * @return bool True if valid JSON
 */
function isJson($string) {
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
}

/**
 * Redirect to URL
 * @param string $url URL to redirect to
 */
function redirect($url) {
    header('Location: ' . $url);
    exit();
}

/**
 * Get current page URL
 * @return string Current page URL
 */
function getCurrentUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

/**
 * Get client IP address
 * @return string IP address
 */
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}

// ==================== GRADE & GPA FUNCTIONS ====================

/**
 * Calculate letter grade from numeric grade
 * @param float $grade Numeric grade (0-100)
 * @return string Letter grade
 */
function calculateLetterGrade($grade) {
    if ($grade >= 90) return 'A';
    if ($grade >= 80) return 'B';
    if ($grade >= 70) return 'C';
    if ($grade >= 60) return 'D';
    return 'F';
}

/**
 * Calculate GPA from letter grade
 * @param string $letter_grade Letter grade
 * @return float GPA value
 */
function calculateGPA($letter_grade) {
    $gpa_map = [
        'A+' => 4.0,
        'A' => 4.0,
        'A-' => 3.7,
        'B+' => 3.3,
        'B' => 3.0,
        'B-' => 2.7,
        'C+' => 2.3,
        'C' => 2.0,
        'C-' => 1.7,
        'D+' => 1.3,
        'D' => 1.0,
        'D-' => 0.7,
        'F' => 0.0
    ];
    return $gpa_map[$letter_grade] ?? 0.0;
}

/**
 * Calculate numeric grade from percentage
 * @param float $percentage Percentage (0-100)
 * @return array ['letter' => string, 'gpa' => float]
 */
function calculateGradeFromPercentage($percentage) {
    $letter = calculateLetterGrade($percentage);
    $gpa = calculateGPA($letter);
    
    return [
        'letter' => $letter,
        'gpa' => $gpa,
        'percentage' => round($percentage, 2)
    ];
}

// ==================== COURSE FUNCTIONS ====================

/**
 * Get course by ID
 * @param int $course_id Course ID
 * @return array|null Course data
 */
function getCourseById($course_id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM courses WHERE course_id = ?");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $course = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $course;
}

/**
 * Check if student is enrolled in course
 * @param int $student_id Student ID
 * @param int $course_id Course ID
 * @return bool True if enrolled
 */
function isEnrolled($student_id, $course_id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT enrollment_id FROM enrollments WHERE student_id = ? AND course_id = ? AND status = 'enrolled'");
    $stmt->bind_param("ii", $student_id, $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $enrolled = $result->num_rows > 0;
    $stmt->close();
    $conn->close();
    return $enrolled;
}

// ==================== PAGINATION FUNCTIONS ====================

/**
 * Calculate pagination
 * @param int $total_items Total number of items
 * @param int $items_per_page Items per page
 * @param int $current_page Current page number
 * @return array Pagination data
 */
function calculatePagination($total_items, $items_per_page, $current_page) {
    $total_pages = ceil($total_items / $items_per_page);
    $current_page = max(1, min($current_page, $total_pages));
    $offset = ($current_page - 1) * $items_per_page;
    
    return [
        'total_items' => $total_items,
        'items_per_page' => $items_per_page,
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'offset' => $offset,
        'has_prev' => $current_page > 1,
        'has_next' => $current_page < $total_pages
    ];
}

// ==================== DIRECTORY SETUP ====================

// Create upload directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// Create subdirectories
$subdirs = ['courses', 'assignments', 'profiles'];
foreach ($subdirs as $dir) {
    $path = UPLOAD_DIR . $dir;
    if (!file_exists($path)) {
        mkdir($path, 0755, true);
    }
}

// ==================== ERROR HANDLING ====================

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

// Custom error handler
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $error_message = date('Y-m-d H:i:s') . " - Error [$errno]: $errstr in $errfile on line $errline\n";
    error_log($error_message, 3, __DIR__ . '/error_log.txt');
    
    // Don't execute PHP internal error handler
    return true;
}

set_error_handler("customErrorHandler");

// ==================== TIMEZONE ====================
date_default_timezone_set('Asia/Colombo'); // Changed to Sri Lanka timezone based on your location

// ==================== CONSTANTS ====================
define('ITEMS_PER_PAGE', 20);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 900); // 15 minutes

// ==================== DEBUGGING FUNCTIONS ====================

/**
 * Debug print (only in development)
 * @param mixed $data Data to print
 * @param bool $die Whether to die after printing
 */
function dd($data, $die = true) {
    echo '<pre>';
    print_r($data);
    echo '</pre>';
    if ($die) die();
}

/**
 * Dump variable
 * @param mixed $data Data to dump
 */
function dump($data) {
    dd($data, false);
}

// ==================== END OF CONFIGURATION ====================
?>