<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');     
define('DB_NAME', 'pharmacy_inventory'); 

function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        // Log error for the admin
        error_log('Connection failed: ' . $conn->connect_error);
        http_response_code(500);
        die(json_encode(['error' => 'Internal Server Error']));
    }
    
    $conn->set_charset('utf8mb4');
    return $conn;
}

// Initialize the connection for use in other files
$conn = getConnection(); 
?>
