<?php
session_start();
require_once '../config/db.php';
require_once '../config/helpers.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: add-post.php');
    exit;
}

// Ambil dan validasi data
$title = escape_string($_POST['title']);
$category = escape_string($_POST['category']);
$excerpt = escape_string($_POST['excerpt']);
$content = $_POST['content']; // Jangan escape - ada HTML dari Summernote
$status = escape_string($_POST['status']);
$author = escape_string($_SESSION['admin_name']);

// Generate slug
$slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));

// Process konten - fix image paths
$content = process_content_images($content, true);

// Handle featured image
$featured_image = '';
if (!empty($_FILES['featured_image']['name'])) {
    $file = $_FILES['featured_image'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024;
    
    $mime_type = mime_content_type($file['tmp_name']);
    
    if (in_array($mime_type, $allowed_types) && $file['size'] <= $max_size) {
        $upload_dir = '../uploads/featured/';
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0755, true);
        }
        
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $file_name = 'featured_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
        $upload_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            $featured_image = 'uploads/featured/' . $file_name;
        }
    }
}

// Validasi
if (empty($title) || empty($excerpt) || empty($content) || empty($category)) {
    $_SESSION['error'] = '❌ Semua field harus diisi!';
    header('Location: add-post.php');
    exit;
}

// Escape content untuk database
$content = mysqli_real_escape_string($conn, $content);

// Insert post
$query = "INSERT INTO posts (title, slug, excerpt, content, category, featured_image, author, status, created_at, views) 
          VALUES ('$title', '$slug', '$excerpt', '$content', '$category', '$featured_image', '$author', '$status', NOW(), 0)";

if (mysqli_query($conn, $query)) {
    $post_id = mysqli_insert_id($conn);
    $_SESSION['success'] = "✅ Post berhasil disimpan!";
    header('Location: posts.php');
    exit;
} else {
    $_SESSION['error'] = 'Gagal menyimpan post: ' . mysqli_error($conn);
    header('Location: add-post.php');
    exit;
}
?>