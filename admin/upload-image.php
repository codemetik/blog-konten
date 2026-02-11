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

// Check if file uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    $error_msg = 'Tidak ada file yang diupload';
    
    if (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $error_codes = [
            UPLOAD_ERR_INI_SIZE => 'File terlalu besar (exceeds upload_max_filesize)',
            UPLOAD_ERR_FORM_SIZE => 'File terlalu besar (exceeds form MAX_FILE_SIZE)',
            UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
            UPLOAD_ERR_NO_FILE => 'Tidak ada file yang dipilih',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ditemukan',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
            UPLOAD_ERR_EXTENSION => 'Extension file tidak diizinkan'
        ];
        $error_msg = $error_codes[$_FILES['file']['error']] ?? 'Unknown upload error';
    }
    
    echo json_encode([
        'success' => false, 
        'message' => $error_msg
    ]);
    exit;
}

$file = $_FILES['file'];
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$max_size = 5 * 1024 * 1024; // 5MB

// Get actual mime type menggunakan finfo
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

// Debug logging
error_log("Upload attempt - Filename: {$file['name']}, MIME: {$mime_type}, Size: {$file['size']}");

// Validasi tipe file
if (!in_array($mime_type, $allowed_types)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Tipe file tidak sesuai. Gunakan JPG, PNG, GIF, atau WebP (Detected: ' . $mime_type . ')',
        'detected_mime' => $mime_type
    ]);
    exit;
}

// Validasi ukuran file
if ($file['size'] > $max_size) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Ukuran file terlalu besar. Maksimal 5MB (Ukuran file: ' . round($file['size'] / 1024 / 1024, 2) . ' MB)'
    ]);
    exit;
}

// Buat folder uploads/posts jika belum ada
$base_upload_dir = '../uploads/posts/';
$base_upload_abs = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'posts' . DIRECTORY_SEPARATOR;

if (!is_dir($base_upload_dir)) {
    if (!@mkdir($base_upload_dir, 0755, true)) {
        http_response_code(500);
        error_log("Failed to create directory: {$base_upload_dir}");
        echo json_encode([
            'success' => false, 
            'message' => 'Gagal membuat folder uploads. Periksa permission server.'
        ]);
        exit;
    }
}

// Generate nama file dengan format yang konsisten
// Format: posts_TIMESTAMP_RANDOMHEX.ext
$file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// Validasi extension (double-check)
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
if (!in_array($file_ext, $allowed_extensions)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Extension file tidak diizinkan. Gunakan: ' . implode(', ', $allowed_extensions)
    ]);
    exit;
}

$unique_id = bin2hex(random_bytes(8)); // 16 character hex
$timestamp = time();
$file_name = 'posts_' . $timestamp . '_' . $unique_id . '.' . $file_ext;
$upload_path = $base_upload_dir . $file_name;
$upload_abs_path = $base_upload_abs . $file_name;

// Upload file
if (move_uploaded_file($file['tmp_name'], $upload_path)) {
    // Verify file exists
    if (!file_exists($upload_path)) {
        http_response_code(500);
        error_log("File uploaded tapi tidak ditemukan: {$upload_path}");
        echo json_encode([
            'success' => false, 
            'message' => 'File gagal disimpan. Verifikasi error.'
        ]);
        exit;
    }
    
    // Set proper permissions
    @chmod($upload_path, 0644);
    
    // Get file size untuk confirmation
    $file_size = filesize($upload_path);
    
    // Relative URL yang konsisten - relatif terhadap admin folder
    // Jika di admin/upload-image.php, maka ../uploads/posts/filename
    $relative_url = '../uploads/posts/' . $file_name;
    
    // Database path - path yang disimpan di database
    // Format: uploads/posts/filename (relatif terhadap root)
    $db_path = 'uploads/posts/' . $file_name;
    
    // Return response dengan berbagai format path
    echo json_encode([
        'success' => true,
        'message' => 'Gambar berhasil diupload',
        'file_name' => $file_name,
        'url' => $relative_url,           // URL untuk Summernote (dari admin folder)
        'db_path' => $db_path,            // Path untuk database (dari root)
        'file_size' => $file_size,
        'file_size_kb' => round($file_size / 1024, 2),
        'mime_type' => $mime_type
    ]);
    
    http_response_code(200);
} else {
    http_response_code(500);
    error_log("Move uploaded file failed - from: {$file['tmp_name']} to: {$upload_path}");
    echo json_encode([
        'success' => false, 
        'message' => 'Gagal upload file. Periksa permission folder uploads atau server error.'
    ]);
}
?>
