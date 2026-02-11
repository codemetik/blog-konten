<?php
require_once 'config/db.php';

// Cek request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

// Validasi file upload
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'File upload failed']);
    exit;
}

$file = $_FILES['file'];

// Validasi tipe file
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($file['type'], $allowed_types)) {
    http_response_code(400);
    echo json_encode(['error' => 'File type not allowed. Only JPEG, PNG, GIF, and WebP are allowed']);
    exit;
}

// Validasi ukuran file (max 5MB)
$max_size = 5 * 1024 * 1024;
if ($file['size'] > $max_size) {
    http_response_code(400);
    echo json_encode(['error' => 'File size exceeds maximum limit of 5MB']);
    exit;
}

// Buat direktori jika belum ada
$upload_dir = __DIR__ . '/uploads/articles/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate nama file yang unik
$file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$file_name = 'article_' . time() . '_' . uniqid() . '.' . $file_ext;
$file_path = $upload_dir . $file_name;

// Compress dan simpan gambar
try {
    // Baca gambar original
    $image = null;
    switch ($file['type']) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($file['tmp_name']);
            break;
        case 'image/png':
            $image = imagecreatefrompng($file['tmp_name']);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($file['tmp_name']);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($file['tmp_name']);
            break;
    }

    if ($image === false) {
        throw new Exception('Failed to create image from file');
    }

    // Resize jika terlalu besar (max width 1200px)
    $width = imagesx($image);
    $height = imagesy($image);
    $max_width = 1200;

    if ($width > $max_width) {
        $new_height = intval($height * ($max_width / $width));
        $resized = imagecreatetruecolor($max_width, $new_height);
        
        // Preserve transparency untuk PNG
        if ($file['type'] === 'image/png') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
        }
        
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $max_width, $new_height, $width, $height);
        imagedestroy($image);
        $image = $resized;
    }

    // Simpan gambar dengan kompresi
    if ($file['type'] === 'image/jpeg') {
        imagejpeg($image, $file_path, 85);
    } elseif ($file['type'] === 'image/png') {
        imagepng($image, $file_path, 9);
    } elseif ($file['type'] === 'image/gif') {
        imagegif($image, $file_path);
    } elseif ($file['type'] === 'image/webp') {
        imagewebp($image, $file_path, 85);
    }

    imagedestroy($image);

    // Return URL gambar
    $upload_url = 'uploads/articles/' . $file_name;
    echo json_encode([
        'success' => true,
        'file' => [
            'url' => $upload_url,
            'name' => $file_name
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>