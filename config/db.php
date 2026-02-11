<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'smk_blog_db');

// Create connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to utf8
mysqli_set_charset($conn, "utf8");

// Function to escape string
function escape_string($string) {
    global $conn;
    return mysqli_real_escape_string($conn, $string);
}

// Function to display messages
// function show_message($type, $message) {
//     return "<div class='alert alert-$type alert-dismissible fade show' role='alert'>
//         <i class='bi bi-" . ($type === 'success' ? 'check-circle' : 'exclamation-circle') . " me-2'></i>
//         $message
//         <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
//     </div>";
// }
?>