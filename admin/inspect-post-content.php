<?php
session_start();
require_once '../config/db.php';
require_once '../config/helpers.php';

if (!isset($_SESSION['admin_logged_in'])) {
    die("Akses ditolak");
}

// Get latest post with content
$query = "SELECT id, title, featured_image, content FROM posts ORDER BY id DESC LIMIT 1";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    die("Tidak ada post ditemukan");
}

$post = mysqli_fetch_assoc($result);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inspect Post Content</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f5f5; padding: 2rem 0; }
        .container-inspect { background: white; border-radius: 10px; padding: 2rem; max-width: 1200px; margin: 0 auto; }
        .code-box { background: #2d2d2d; color: #f8f8f2; padding: 1.5rem; border-radius: 5px; font-family: monospace; font-size: 12px; max-height: 500px; overflow-y: auto; line-height: 1.4; word-break: break-all; }
        .section-title { font-weight: bold; color: #6366f1; margin-top: 2rem; margin-bottom: 1rem; border-bottom: 2px solid #6366f1; padding-bottom: 0.5rem; }
        .img-count { background: #ede9fe; color: #6366f1; padding: 0.5rem 1rem; border-radius: 5px; font-weight: bold; margin-bottom: 1rem; }
        .img-item { background: #f3f4f6; padding: 0.8rem; margin-bottom: 0.5rem; border-radius: 4px; border-left: 3px solid #ef4444; }
    </style>
</head>
<body>
    <div class="container-inspect">
        <h1 class="mb-4">🔍 Inspect Post Content - Image Detection</h1>
        
        <div class="alert alert-info">
            <strong>Post ID:</strong> <?php echo $post['id']; ?> | 
            <strong>Title:</strong> <?php echo htmlspecialchars($post['title']); ?>
        </div>

        <!-- Featured Image -->
        <div class="section-title">Featured Image</div>
        <div class="code-box">
<?php echo htmlspecialchars($post['featured_image'] ?? '(kosong)'); ?>
        </div>

        <!-- Raw Content -->
        <div class="section-title">Raw Content dari Database (snippet pertama 2000 chars)</div>
        <div class="code-box">
<?php 
$content_snippet = substr($post['content'], 0, 2000);
echo htmlspecialchars($content_snippet);
if (strlen($post['content']) > 2000) {
    echo "\n\n... [truncated, total length: " . strlen($post['content']) . " chars] ...";
}
?>
        </div>

        <!-- Search untuk img tags -->
        <div class="section-title">IMG Tags Found (raw)</div>
        <?php
        if (preg_match_all('/<img[^>]*>/i', $post['content'], $img_matches)) {
            echo '<div class="img-count">Total IMG tags: ' . count($img_matches[0]) . '</div>';
            foreach ($img_matches[0] as $idx => $img_tag) {
                echo '<div class="img-item">';
                echo '<strong>Tag #' . ($idx + 1) . ':</strong><br>';
                echo '<code style="font-size: 11px; color: #666;">' . htmlspecialchars(substr($img_tag, 0, 300)) . '</code>';
                if (strlen($img_tag) > 300) {
                    echo '... [truncated]';
                }
                echo '</div>';
            }
        } else {
            echo '<div class="alert alert-warning">Tidak ditemukan IMG tags</div>';
        }
        ?>

        <!-- Extracted Images -->
        <div class="section-title">Images Detected by extract_images_from_content()</div>
        <?php
        $extracted = extract_images_from_content($post['content']);
        
        if (!empty($extracted)) {
            echo '<div class="img-count">Total Images: ' . count($extracted) . '</div>';
            foreach ($extracted as $idx => $img) {
                echo '<div class="img-item">';
                echo ($idx + 1) . '. <code>' . htmlspecialchars($img) . '</code>';
                $full_path = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $img);
                $exists = file_exists($full_path) ? '✓ EXISTS' : '✗ NOT FOUND';
                echo ' <span style="color: ' . (file_exists($full_path) ? '#059669' : '#dc2626') . ';">' . $exists . '</span>';
                echo '</div>';
            }
        } else {
            echo '<div class="alert alert-danger">⚠️ TIDAK ADA IMAGE TERDETEKSI - INI MASALAHNYA!</div>';
        }
        ?>

        <!-- Normalized Content -->
        <div class="section-title">Content After normalize_image_paths()</div>
        <?php
        $normalized_content = normalize_image_paths($post['content']);
        $content_snippet_norm = substr($normalized_content, 0, 2000);
        ?>
        <div class="code-box">
<?php echo htmlspecialchars($content_snippet_norm); ?>
        </div>

        <!-- Re-extract after normalize -->
        <div class="section-title">Images Detected AFTER normalize (untuk verify)</div>
        <?php
        $extracted_after = extract_images_from_content($normalized_content);
        
        if (!empty($extracted_after)) {
            echo '<div class="img-count">Total Images: ' . count($extracted_after) . '</div>';
            foreach ($extracted_after as $idx => $img) {
                echo '<div class="img-item">';
                echo ($idx + 1) . '. <code>' . htmlspecialchars($img) . '</code>';
                echo '</div>';
            }
        } else {
            echo '<div class="alert alert-warning">Still no images detected after normalize</div>';
        }
        ?>

        <div class="mt-4">
            <a href="posts.php" class="btn btn-primary">← Kembali ke Posts</a>
            <button class="btn btn-secondary" onclick="location.reload()">Refresh</button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
