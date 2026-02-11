<?php
session_start();
require_once '../config/db.php';
require_once '../config/helpers.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    die("❌ Akses ditolak");
}

// Get valid categories from database
$cat_query = "SELECT name FROM categories LIMIT 1";
$cat_result = mysqli_query($conn, $cat_query);

if (!$cat_result || mysqli_num_rows($cat_result) == 0) {
    die("❌ No categories found in database. Please create a category first.");
}

$cat_row = mysqli_fetch_assoc($cat_result);
$valid_category = $cat_row['name'];

// Create test post with featured image
$title = "TEST DELETE - " . date('Y-m-d H:i:s');
$category = $valid_category;  // Use valid category from DB
$excerpt = "This is a test post to verify image deletion";
$content = "<p>Test content without images</p>";
$status = "published";
$author = $_SESSION['admin_name'];
$slug = strtolower(str_replace([' ', ':'], ['-', ''], $title));

// Use existing test image
$test_image = 'uploads/posts/posts_1770696340_aa78e64c494e6899.png';

// Escape strings for safety
$title = mysqli_real_escape_string($conn, $title);
$category = mysqli_real_escape_string($conn, $category);
$excerpt = mysqli_real_escape_string($conn, $excerpt);
$content = mysqli_real_escape_string($conn, $content);
$slug = mysqli_real_escape_string($conn, $slug);
$author = mysqli_real_escape_string($conn, $author);

// Insert post
$query = "INSERT INTO posts (title, slug, excerpt, content, category, featured_image, author, status, created_at, views) 
          VALUES ('$title', '$slug', '$excerpt', '$content', '$category', '$test_image', '$author', '$status', NOW(), 0)";

if (mysqli_query($conn, $query)) {
    $post_id = mysqli_insert_id($conn);
    
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Test Delete</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { background: #f5f5f5; padding: 2rem 0; }
            .container-test { background: white; border-radius: 10px; padding: 2rem; max-width: 600px; margin: 0 auto; }
        </style>
    </head>
    <body>
        <div class="container-test">
            <h1 class="mb-4">✅ Test Post Created</h1>
            
            <div class="alert alert-success">
                <p><strong>Post created successfully!</strong></p>
                <p>Post ID: <code>$post_id</code></p>
                <p>Featured Image: <code>$test_image</code></p>
                <p>Category: <code>$valid_category</code></p>
            </div>
            
            <h5 class="mt-4">Step 1: Image File Location</h5>
            <p>Image is located at:</p>
            <code>C:\laragon\www\webai\blog-konten\uploads\posts\posts_1770696340_aa78e64c494e6899.png</code>
            
            <h5 class="mt-4">Step 2: Delete the Post</h5>
            <p>Click the button below to delete the test post AND verify images are deleted:</p>
            
            <a href="delete-post.php?id=$post_id" class="btn btn-danger btn-lg" onclick="return confirm('Delete test post and images?')">
                🗑️ Delete Post & Images
            </a>
            
            <h5 class="mt-4">Step 3: Verify in Windows Explorer</h5>
            <p>After clicking delete:</p>
            <ol>
                <li>You should see success message: "✅ Post berhasil dihapus! 1 gambar dihapus."</li>
                <li>Open Windows Explorer and navigate to: <code>C:\laragon\www\webai\blog-konten\uploads\posts\</code></li>
                <li>Check if file <code>posts_1770696340_aa78e64c494e6899.png</code> is GONE</li>
            </ol>
            
            <div class="alert alert-warning mt-4">
                <strong>⚠️ IMPORTANT:</strong> Only click DELETE once! After delete, image should be gone.
            </div>
            
            <div class="mt-4">
                <a href="index.php" class="btn btn-secondary">← Back to Posts</a>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    HTML;
} else {
    echo "Error creating test post: " . mysqli_error($conn);
}
?>

