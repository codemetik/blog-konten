<?php
session_start();
require_once '../config/db.php';
require_once '../config/helpers.php';

if (!isset($_SESSION['admin_logged_in'])) {
    die("Akses ditolak.");
}

// Get latest 5 posts untuk verify delete functionality
$query = "SELECT id, title, featured_image FROM posts ORDER BY id DESC LIMIT 5";
$result = mysqli_query($conn, $query);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Post Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f5f5; padding: 2rem 0; }
        .container-verify { background: white; border-radius: 10px; padding: 2rem; max-width: 800px; margin: 0 auto; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="container-verify">
        <h1 class="mb-4">✅ Verifikasi Delete Post dengan Delete Gambar</h1>
        
        <div class="alert alert-info">
            <p><strong>Instruksi:</strong></p>
            <ol>
                <li>Pilih salah satu post dari daftar di bawah</li>
                <li>Klik tombol "DELETE" dan konfirmasi</li>
                <li>Sistem akan menghapus post DAN gambar-gambarnya secara otomatis</li>
                <li>Cek pesan success yang menunjukkan berapa gambar dihapus</li>
                <li>Verifikasi folder uploads/posts/ gambar hilang</li>
            </ol>
        </div>
        
        <h5 class="mt-4 mb-3">5 Post Terakhir (Pilih untuk Delete):</h5>
        
        <table class="table table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Featured Image</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                while ($row = mysqli_fetch_assoc($result)) {
                    $img_name = basename($row['featured_image']);
                    echo '<tr>';
                    echo '<td>' . $row['id'] . '</td>';
                    echo '<td>' . htmlspecialchars(substr($row['title'], 0, 50)) . '...</td>';
                    echo '<td><code style="font-size: 0.8rem;">' . htmlspecialchars($img_name) . '</code></td>';
                    echo '<td><a href="delete-post.php?id=' . $row['id'] . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Yakin hapus? Gambar akan otomatis dihapus juga!\');">DELETE</a></td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
        
        <div class="alert alert-warning mt-4">
            <strong>⚠️ Catatan:</strong> Setelah klik DELETE dan konfirmasi, periksa:<br>
            1. Message success yang menunjukkan "X gambar dihapus"<br>
            2. Folder <code>uploads/posts/</code> → gambar harus hilang<br>
            3. Database → post harus hilang
        </div>
        
        <a href="index.php" class="btn btn-primary">← Kembali ke Posts List</a>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
