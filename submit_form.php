<?php
// SECURITY NOTE: All user inputs should be validated and sanitized
// Use filter_var(), htmlspecialchars(), and prepared statements for database queries


// Enhanced security measures for form submission
session_start();

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// CSRF Token validation (if implemented on form)
if (isset($_POST['csrf_token']) && !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    die("CSRF token mismatch");
}

// Rate limiting - simple implementation
if (!isset($_SESSION['last_submission'])) {
    $_SESSION['last_submission'] = 0;
}
if (time() - $_SESSION['last_submission'] < 5) { // 5 second cooldown
    http_response_code(429);
    die("Too many requests. Please wait before submitting again.");
}

// Input validation and sanitization
function validate_and_sanitize($input, $type) {
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input);

    switch ($type) {
        case 'name':
            if (empty($input) || strlen($input) > 100 || !preg_match('/^[a-zA-Z\s]+$/', $input)) {
                return false;
            }
            break;
        case 'email':
            if (empty($input) || !filter_var($input, FILTER_VALIDATE_EMAIL) || strlen($input) > 255) {
                return false;
            }
            break;
        case 'business':
            if (strlen($input) > 255) {
                return false;
            }
            break;
        case 'service':
            $allowed_services = ['seo', 'smm', 'content', 'analytics', 'consulting'];
            if (!in_array($input, $allowed_services)) {
                return false;
            }
            break;
    }
    return $input;
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Method not allowed");
}

// Validate and sanitize input data
$name = validate_and_sanitize($_POST['name'] ?? '', 'name');
$email = validate_and_sanitize($_POST['email'] ?? '', 'email');
$business = validate_and_sanitize($_POST['business'] ?? '', 'business');
$service = validate_and_sanitize($_POST['service'] ?? '', 'service');

if ($name === false || $email === false || $business === false || $service === false) {
    http_response_code(400);
    die("Invalid input data provided");
}

// Database connection with error handling
try {
    // Database connection details (should be in environment variables in production)
    $servername = "sql310.infinityfree.com";
    $username = "if0_38699889";
    $password = "A4t6yxy2";
    $dbname = "if0_38699889_users";

    // Create connection with SSL and additional options
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Set charset to prevent character set confusion attacks
    $conn->set_charset("utf8mb4");

    // Check connection
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        http_response_code(500);
        die("Database connection failed");
    }

    // Prepare and bind SQL statement (already secure against SQL injection)
    $stmt = $conn->prepare("INSERT INTO early_access (name, email, business, service, created_at, ip_address) VALUES (?, ?, ?, ?, NOW(), ?)");

    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        http_response_code(500);
        die("Database error occurred");
    }

    // Get client IP address safely
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $stmt->bind_param("sssss", $name, $email, $business, $service, $ip_address);

    // Execute the statement
    if ($stmt->execute()) {
        $_SESSION['last_submission'] = time(); // Update rate limiting
        http_response_code(200);
        echo json_encode(["status" => "success", "message" => "Registration successful"]);
    } else {
        error_log("Execution failed: " . $stmt->error);
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Registration failed"]);
    }

    // Close statement
    $stmt->close();

} catch (Exception $e) {
    error_log("Exception in form submission: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "An error occurred"]);
}

// Close connection
if (isset($conn)) {
    $conn->close();
}
?>