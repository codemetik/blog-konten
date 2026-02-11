<?php
session_start();

if(!isset($_SESSION['debug_info'])) {
    die("Tidak ada debug info tersedia.");
}

$debug_info = $_SESSION['debug_info'];
unset($_SESSION['debug_info']); // Clear setelah ditampilkan
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Info - Delete Post</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background-color: #f5f5f5; }
        .debug-container { background: white; padding: 20px; border-radius: 8px; max-width: 600px; margin: 0 auto; }
        pre { background: #f0f0f0; padding: 15px; border-radius: 4px; font-size: 12px; max-height: 400px; overflow-y: auto; }
    </style>
</head>
<body>
<div class="debug-container">
    <h3>🔍 Debug Info - Delete Post Process</h3>
    <pre><?php echo htmlspecialchars($debug_info); ?></pre>
    <a href="index.php" class="btn btn-primary mt-3">Kembali ke Posts</a>
</div>
</body>
</html>
