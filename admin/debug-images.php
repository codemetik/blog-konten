<?php
session_start();
require_once '../config/db.php';
require_once '../config/helpers.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    die("❌ Akses ditolak. Silakan login terlebih dahulu.");
}

// Get latest post
$query = "SELECT id, title, featured_image, content FROM posts ORDER BY id DESC LIMIT 1";
$result = mysqli_query($conn, $query);
$post = mysqli_fetch_assoc($result);

if (!$post) {
    die("❌ Tidak ada post ditemukan.");
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Images in Post</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f5f5f5;
            padding: 2rem 0;
        }
        .debug-container {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            max-width: 1000px;
            margin: 0 auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #f9f9f9;
            border-left: 4px solid #6366f1;
            border-radius: 5px;
        }
        .code-block {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 1rem;
            border-radius: 5px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            line-height: 1.5;
        }
        .found {
            color: #28a745;
            font-weight: bold;
        }
        .notfound {
            color: #dc3545;
            font-weight: bold;
        }
        pre {
            margin: 0;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
    </style>
</head>
<body>
    <div class="debug-container">
        <h1 class="mb-4">🔍 Debug: Images dalam Post</h1>
        
        <div class="section">
            <h5>📝 Post Info</h5>
            <table class="table table-sm">
                <tr>
                    <td><strong>ID:</strong></td>
                    <td><?php echo $post['id']; ?></td>
                </tr>
                <tr>
                    <td><strong>Title:</strong></td>
                    <td><?php echo htmlspecialchars($post['title']); ?></td>
                </tr>
                <tr>
                    <td><strong>Featured Image:</strong></td>
                    <td><code><?php echo htmlspecialchars($post['featured_image']); ?></code></td>
                </tr>
            </table>
        </div>
        
        <div class="section">
            <h5>🖼️ Featured Image Analysis</h5>
            <?php if (!empty($post['featured_image'])): ?>
                <p><strong>Path di Database:</strong> <code><?php echo htmlspecialchars($post['featured_image']); ?></code></p>
                <p><strong>Filename:</strong> <code><?php echo htmlspecialchars(basename($post['featured_image'])); ?></code></p>
                <?php
                $featured_path = $post['featured_image'];
                $base_dir = dirname(__DIR__);
                
                // Try different locations
                $locations = [
                    $featured_path => $base_dir . '/' . $featured_path,
                    'uploads/featured/' . basename($featured_path) => $base_dir . '/uploads/featured/' . basename($featured_path),
                    'uploads/posts/' . basename($featured_path) => $base_dir . '/uploads/posts/' . basename($featured_path),
                ];
                
                echo "<p><strong>Lokasi File:</strong></p>";
                foreach ($locations as $label => $full_path) {
                    $exists = file_exists($full_path);
                    $status = $exists ? '<span class="found">✓ FOUND</span>' : '<span class="notfound">✗ NOT FOUND</span>';
                    echo "<div>$label: $status</div>";
                }
                ?>
            <?php else: ?>
                <p><span class="notfound">Tidak ada featured image</span></p>
            <?php endif; ?>
        </div>
        
        <div class="section">
            <h5>🖼️ Content Images Analysis</h5>
            <?php
            $content = $post['content'];
            
            // Show first 500 chars of content
            echo "<p><strong>Content Preview (first 500 chars):</strong></p>";
            echo "<div class='code-block'>";
            echo htmlspecialchars(substr($content, 0, 500));
            if (strlen($content) > 500) {
                echo "...";
            }
            echo "</div>";
            
            // Extract all img tags
            echo "<p style='margin-top: 1rem;'><strong>Semua &lt;img&gt; tags dalam content:</strong></p>";
            if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
                echo "<div class='code-block'>";
                foreach ($matches[1] as $i => $src) {
                    echo "<div>" . ($i+1) . ". " . htmlspecialchars($src) . "</div>";
                }
                echo "</div>";
            } else {
                echo "<p><span class='notfound'>Tidak ada img tag ditemukan</span></p>";
            }
            
            // Try extraction function
            echo "<p style='margin-top: 1rem;'><strong>Hasil extract_images_from_content():</strong></p>";
            $extracted = extract_images_from_content($content);
            if (!empty($extracted)) {
                echo "<div class='code-block'>";
                foreach ($extracted as $i => $img) {
                    echo "<div>" . ($i+1) . ". " . htmlspecialchars($img) . "</div>";
                }
                echo "</div>";
            } else {
                echo "<p><span class='notfound'>Tidak ada image terdeteksi</span></p>";
            }
            
            // Test get_post_images function
            echo "<p style='margin-top: 1rem;'><strong>Hasil get_post_images():</strong></p>";
            $all_images = get_post_images($post);
            if (!empty($all_images)) {
                echo "<div class='code-block'>";
                foreach ($all_images as $i => $img) {
                    echo "<div>" . ($i+1) . ". " . htmlspecialchars($img) . "</div>";
                }
                echo "</div>";
            } else {
                echo "<p><span class='notfound'>Tidak ada image terdeteksi</span></p>";
            }
            ?>
        </div>
        
        <div class="section">
            <h5>📊 Raw HTML Content</h5>
            <p><strong>Full content (raw):</strong></p>
            <div class="code-block">
                <pre><?php echo htmlspecialchars($content); ?></pre>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="index.php" class="btn btn-primary">← Kembali ke Posts</a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
