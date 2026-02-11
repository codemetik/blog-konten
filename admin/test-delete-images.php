<?php
session_start();
require_once '../config/db.php';
require_once '../config/helpers.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    die("❌ Akses ditolak. Silakan login terlebih dahulu.");
}

// Get post ID from query string
$post_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($post_id <= 0) {
    die("❌ ID post tidak valid.");
}

// Get post data
$get_post_query = "SELECT id, title, featured_image, content FROM posts WHERE id = $post_id";
$post_result = mysqli_query($conn, $get_post_query);

if (!$post_result || mysqli_num_rows($post_result) == 0) {
    die("❌ Post tidak ditemukan.");
}

$post = mysqli_fetch_assoc($post_result);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Delete Images</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f5f5f5;
            padding: 2rem 0;
        }
        .container-test {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            max-width: 900px;
            margin: 0 auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .info-box {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 5px;
            border-left: 4px solid #6366f1;
        }
        .success-box {
            background: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }
        .danger-box {
            background: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }
        .code-block {
            background: #f9f9f9;
            padding: 1rem;
            border-radius: 5px;
            font-family: monospace;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container-test">
        <h1 class="mb-4">🧪 Test Delete Images</h1>
        
        <div class="info-box" style="background: #e7f3ff; border-left-color: #0066cc; color: #003d99;">
            <h5>📝 Post Information</h5>
            <p><strong>ID:</strong> <?php echo $post['id']; ?></p>
            <p><strong>Title:</strong> <?php echo htmlspecialchars($post['title']); ?></p>
        </div>
        
        <h5 class="mt-4 mb-3">Step 1: Get Images to Delete</h5>
        <?php
        $images = get_post_images($post);
        
        if (!empty($images)) {
            echo '<div class="info-box success-box">';
            echo '<strong>✓ Gambar ditemukan:</strong> ' . count($images) . ' file(s)<br><br>';
            foreach ($images as $i => $img) {
                echo '<div class="code-block">' . ($i+1) . '. ' . htmlspecialchars($img) . '</div>';
            }
            echo '</div>';
        } else {
            echo '<div class="info-box danger-box"><strong>✗ Tidak ada gambar ditemukan</strong></div>';
        }
        ?>
        
        <h5 class="mt-4 mb-3">Step 2: Check File Exists on Disk</h5>
        <?php
        $base_dir = dirname(__DIR__);
        
        foreach ($images as $img) {
            $full_path = $base_dir . '/' . $img;
            $exists = file_exists($full_path);
            $status = $exists ? '✓ FOUND' : '✗ NOT FOUND';
            $class = $exists ? 'success-box' : 'danger-box';
            
            echo '<div class="info-box ' . $class . '">';
            echo '<strong>Path:</strong> ' . htmlspecialchars($img) . '<br>';
            echo '<strong>Full Path:</strong> ' . htmlspecialchars($full_path) . '<br>';
            echo '<strong>Status:</strong> ' . $status;
            if ($exists) {
                echo '<br><strong>File Size:</strong> ' . number_format(filesize($full_path)) . ' bytes';
            }
            echo '</div>';
        }
        ?>
        
        <h5 class="mt-4 mb-3">Step 3: Delete Images</h5>
        <?php
        if (!empty($images)) {
            $deleted = 0;
            $failed = 0;
            
            foreach ($images as $img) {
                if (delete_image_file($img)) {
                    $deleted++;
                } else {
                    $failed++;
                }
            }
            
            if ($failed == 0) {
                echo '<div class="info-box success-box">';
                echo '<strong>✓ Sukses!</strong> ' . $deleted . ' gambar berhasil dihapus.';
                echo '</div>';
            } else {
                echo '<div class="info-box danger-box">';
                echo '<strong>⚠️ Parsial!</strong> ' . $deleted . ' dihapus, ' . $failed . ' gagal.';
                echo '</div>';
            }
        }
        ?>
        
        <h5 class="mt-4 mb-3">Step 4: Verify Deletion</h5>
        <?php
        foreach ($images as $img) {
            $full_path = $base_dir . '/' . $img;
            $exists = file_exists($full_path);
            
            if (!$exists) {
                echo '<div class="info-box success-box">';
                echo '<strong>✓ DELETED:</strong> ' . htmlspecialchars($img);
                echo '</div>';
            } else {
                echo '<div class="info-box danger-box">';
                echo '<strong>✗ STILL EXISTS:</strong> ' . htmlspecialchars($img);
                echo '</div>';
            }
        }
        ?>
        
        <h5 class="mt-4 mb-3">Step 5: Delete Post from Database</h5>
        <?php
        $delete_query = "DELETE FROM posts WHERE id = $post_id";
        if (mysqli_query($conn, $delete_query)) {
            echo '<div class="info-box success-box">';
            echo '<strong>✓ Post berhasil dihapus dari database</strong>';
            echo '</div>';
        } else {
            echo '<div class="info-box danger-box">';
            echo '<strong>✗ Gagal menghapus post:</strong> ' . mysqli_error($conn);
            echo '</div>';
        }
        ?>
        
        <div class="mt-4">
            <a href="index.php" class="btn btn-primary">← Kembali ke Posts</a>
            <a href="debug-images.php" class="btn btn-secondary">← Debug Images</a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
