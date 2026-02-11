<?php
session_start();
require_once '../config/db.php';
require_once '../config/helpers.php';

header('Content-Type: application/json; charset=utf-8');

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'Unauthorized - Silakan login terlebih dahulu'
    ]);
    exit;
}

// Get filename from POST
$filename = isset($_POST['file_name']) ? trim($_POST['file_name']) : '';

if (empty($filename)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Filename tidak ditemukan'
    ]);
    exit;
}

// Validasi filename format - harus posts_xxxxx.ext
if (!preg_match('/^posts_[a-z0-9]+\.[a-z]{3,4}$/i', $filename)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Format filename tidak valid'
    ]);
    exit;
}

// Build file path
$file_path = '../uploads/posts/' . $filename;

// Debug logging
error_log("Delete request - Filename: {$filename}, Path: {$file_path}");

// Check if file exists
if (!file_exists($file_path)) {
    http_response_code(404);
    echo json_encode([
        'success' => false, 
        'message' => 'File tidak ditemukan di server',
        'path' => $file_path
    ]);
    exit;
}

// Check if file is readable
if (!is_readable($file_path)) {
    http_response_code(500);
    error_log("File exists but not readable: {$file_path}");
    echo json_encode([
        'success' => false, 
        'message' => 'File tidak bisa diakses'
    ]);
    exit;
}

// Try to delete file
if (@unlink($file_path)) {
    http_response_code(200);
    error_log("Image deleted successfully: {$filename}");
    echo json_encode([
        'success' => true, 
        'message' => 'Gambar berhasil dihapus',
        'file_name' => $filename
    ]);
} else {
    http_response_code(500);
    error_log("Failed to delete image: {$filename}");
    echo json_encode([
        'success' => false, 
        'message' => 'Gagal menghapus file. Periksa permission server.'
    ]);
}
?>
