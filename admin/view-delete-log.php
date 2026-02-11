<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['admin_logged_in'])) {
    die("Akses ditolak");
}

$debug_file = '../uploads/debug_delete.log';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Delete Log</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f5f5; padding: 2rem 0; }
        .container-debug { background: white; border-radius: 10px; padding: 2rem; max-width: 1000px; margin: 0 auto; }
        .log-content { background: #2d2d2d; color: #f8f8f2; padding: 1.5rem; border-radius: 5px; font-family: monospace; max-height: 600px; overflow-y: auto; }
        .log-line { line-height: 1.6; }
    </style>
</head>
<body>
    <div class="container-debug">
        <h1 class="mb-4">📋 Debug Delete Log</h1>
        
        <div class="alert alert-info">
            <p><strong>Cara Menggunakan:</strong></p>
            <ol>
                <li>Ganti delete button di posts untuk pakai <code>delete-post-debug.php</code></li>
                <li>Atau akses manual: <code>/admin/delete-post-debug.php?id=X</code></li>
                <li>Log akan tercatat di <code>uploads/debug_delete.log</code></li>
                <li>Buka halaman ini untuk melihat log</li>
            </ol>
        </div>
        
        <h5 class="mb-3">Log File Contents:</h5>
        
        <?php
        if (file_exists($debug_file)) {
            $content = file_get_contents($debug_file);
            if (!empty($content)) {
                echo '<div class="log-content">';
                echo '<div class="log-line">' . htmlspecialchars($content) . '</div>';
                echo '</div>';
                
                echo '<div class="mt-3">';
                echo '<button class="btn btn-danger" onclick="if(confirm(\'Clear log?\')) { fetch(\'clear-log.php\').then(() => location.reload()); }\">Clear Log</button>';
                echo '</div>';
            } else {
                echo '<div class="alert alert-warning">Log file exists but is empty</div>';
            }
        } else {
            echo '<div class="alert alert-warning">Log file not found. Delete something first!</div>';
        }
        ?>
        
        <div class="mt-4">
            <h5>Untuk Test Delete dengan Debug:</h5>
            <p>Akses salah satu URL ini dengan Post ID yang ingin dihapus:</p>
            <code>/admin/delete-post-debug.php?id=1</code>
        </div>
        
        <a href="index.php" class="btn btn-primary mt-3">← Kembali ke Posts</a>
    </div>
    
    <script>
        // Auto-refresh setiap 3 detik
        setInterval(() => location.reload(), 3000);
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
