<?php
session_start();
require_once '../config/db.php';
require_once '../config/helpers.php';

// Cek login
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

// Handle Delete Post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'delete') {
        $post_id = (int)($_POST['post_id'] ?? 0);
        
        if ($post_id > 0) {
            // Get post data untuk extract gambar SEBELUM delete
            $get_post_query = "SELECT id, title, featured_image, content FROM posts WHERE id = $post_id";
            $post_result = mysqli_query($conn, $get_post_query);
            
            if ($post_result && mysqli_num_rows($post_result) > 0) {
                $post = mysqli_fetch_assoc($post_result);

                // Normalize content image paths sehingga berbagai format ter-handle
                if (!empty($post['content'])) {
                    $post['content'] = normalize_image_paths($post['content']);
                }

                // Prepare debug info
                $debug_info = [];
                $debug_info[] = "Post ID: " . $post['id'];
                $debug_info[] = "Featured Image: " . ($post['featured_image'] ?? '');
                $debug_info[] = "Content length: " . strlen($post['content']);

                // Get all images yang akan dihapus
                $images_to_delete = get_post_images($post);
                $debug_info[] = "Images detected: " . count($images_to_delete);
                foreach ($images_to_delete as $img) {
                    $debug_info[] = "  - " . $img;
                }

                // Delete post dari database
                $delete_query = "DELETE FROM posts WHERE id = $post_id";
                if (mysqli_query($conn, $delete_query)) {
                    // Delete semua gambar yang terkait
                    $deleted_count = 0;
                    $failed_count = 0;

                    if (!empty($images_to_delete)) {
                        foreach ($images_to_delete as $image_path) {
                            $debug_info[] = "Attempting: " . $image_path;
                            if (delete_image_file($image_path)) {
                                $deleted_count++;
                                $debug_info[] = "  ✓ Deleted";
                            } else {
                                $failed_count++;
                                $debug_info[] = "  ✗ Failed";
                            }
                        }
                    }

                    $debug_info[] = "Total deleted: $deleted_count";
                    $debug_info[] = "Total failed: $failed_count";

                    // Store debug info in session for inspection
                    $_SESSION['debug_info'] = implode("\n", $debug_info);
                    // Also append to persistent log in uploads for cases session is lost
                    $log_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
                    if (!is_dir($log_dir)) {
                        @mkdir($log_dir, 0755, true);
                    }
                    $log_file = $log_dir . DIRECTORY_SEPARATOR . 'debug_delete.log';
                    $log_entry = "--- " . date('Y-m-d H:i:s') . " ---\n" . implode("\n", $debug_info) . "\n\n";
                    @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);

                    // Set success message
                    if ($failed_count == 0 && $deleted_count > 0) {
                        $success = "✅ Post berhasil dihapus! " . $deleted_count . " gambar dihapus.";
                    } elseif ($deleted_count == 0 && $failed_count == 0) {
                        $success = "✅ Post berhasil dihapus! (Tidak ada gambar untuk dihapus)";
                    } else {
                        $success = "⚠️ Post dihapus tetapi " . $failed_count . " gambar gagal dihapus (" . $deleted_count . " berhasil).";
                    }
                } else {
                    $error = "❌ Gagal menghapus post: " . mysqli_error($conn);
                }
            } else {
                $error = "❌ Post tidak ditemukan.";
            }
        }
    }
}

// Get semua posts dengan pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$total_query = "SELECT COUNT(*) as total FROM posts";
$total_result = mysqli_query($conn, $total_query);
$total_data = mysqli_fetch_assoc($total_result);
$total_posts = $total_data['total'];
$total_pages = ceil($total_posts / $limit);

$posts_query = "
    SELECT 
        id,
        title,
        author,
        category,
        status,
        DATE_FORMAT(created_at, '%d %M %Y') as formatted_date,
        created_at
    FROM posts
    ORDER BY created_at DESC
    LIMIT $limit OFFSET $offset
";
$posts_result = mysqli_query($conn, $posts_query);
$posts = [];
while ($row = mysqli_fetch_assoc($posts_result)) {
    $posts[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Post - Admin SMK Blog</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/admin-style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top shadow-sm" style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);">
        <div class="container-fluid px-3 px-md-4">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="bi bi-book-fill text-warning"></i> Admin SMK Blog
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="adminNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="../index.php" target="_blank">Lihat Website</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Sidebar + Content -->
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar py-3">
                <div class="position-sticky">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="bi bi-house-door me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="add-post.php">
                                <i class="bi bi-plus-circle me-2"></i> Tambah Post
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="posts.php">
                                <i class="bi bi-newspaper me-2"></i> Semua Post
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="categories.php">
                                <i class="bi bi-tag me-2"></i> Kategori
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="cleanup-orphaned-images.php">
                                <i class="bi bi-file-image me-2"></i> Clean Orphaned
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">
                                <i class="bi bi-gear me-2"></i> Pengaturan
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h2">
                        <i class="bi bi-newspaper me-2"></i> Kelola Post
                    </h1>
                    <a href="add-post.php" class="btn btn-primary fw-bold">
                        <i class="bi bi-plus-circle me-2"></i> Buat Post Baru
                    </a>
                </div>

                <!-- Alerts -->
                <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>
                    <strong>Berhasil!</strong> <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    <strong>Error!</strong> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Posts List Card -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="card-title fw-bold mb-0">
                            <i class="bi bi-list-ul me-2" style="color: var(--primary);"></i>Daftar Post (<?php echo $total_posts; ?>)
                        </h5>
                    </div>
                    <div class="table-responsive">
                        <?php if (!empty($posts)): ?>
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="fw-600">
                                        <i class="bi bi-file-text me-2"></i>Judul
                                    </th>
                                    <th class="fw-600">
                                        <i class="bi bi-tag me-2"></i>Kategori
                                    </th>
                                    <th class="fw-600">
                                        <i class="bi bi-person me-2"></i>Penulis
                                    </th>
                                    <th class="fw-600">
                                        <i class="bi bi-calendar me-2"></i>Tanggal
                                    </th>
                                    <th class="fw-600">
                                        <i class="bi bi-toggle2-on me-2"></i>Status
                                    </th>
                                    <th class="fw-600 text-center">
                                        <i class="bi bi-sliders me-2"></i>Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($posts as $post): ?>
                                <tr>
                                    <td class="fw-500">
                                        <div style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?php echo htmlspecialchars($post['title']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); padding: 0.5rem 0.8rem; font-size: 0.85rem;">
                                            <i class="bi bi-tag me-1"></i><?php echo htmlspecialchars($post['category']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark" style="padding: 0.5rem 0.8rem;">
                                            <i class="bi bi-person me-1"></i><?php echo htmlspecialchars($post['author']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <i class="bi bi-calendar-event me-1"></i><?php echo $post['formatted_date']; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($post['status'] === 'published'): ?>
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle me-1"></i>Publish
                                        </span>
                                        <?php else: ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="bi bi-pencil me-1"></i>Draft
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="edit-post.php?id=<?php echo $post['id']; ?>" class="btn btn-outline-primary fw-600" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger fw-600" title="Hapus" data-bs-toggle="modal" 
                                                    data-bs-target="#deleteModal"
                                                    onclick="setDeleteData(<?php echo $post['id']; ?>, '<?php echo htmlspecialchars($post['title']); ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="card-body text-center py-5">
                            <i class="bi bi-inbox" style="font-size: 3rem; color: #ddd; display: block; margin-bottom: 1rem;"></i>
                            <h5 class="mb-1 text-muted">Belum Ada Post</h5>
                            <p class="text-muted small mb-3">Belum ada post yang dibuat. Mulai membuat post baru sekarang!</p>
                            <a href="add-post.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>Buat Post Pertama
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="card-footer bg-white border-top">
                        <nav aria-label="Page navigation">
                            <ul class="pagination mb-0 justify-content-center">
                                <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1" title="Halaman Pertama">
                                        <i class="bi bi-chevron-double-left"></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>" title="Halaman Sebelumnya">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>
                                <?php endif; ?>

                                <?php 
                                $start = max(1, $page - 2);
                                $end = min($total_pages, $page + 2);
                                
                                if ($start > 1): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                                <?php endif; ?>

                                <?php for ($i = $start; $i <= $end; $i++): ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>

                                <?php if ($end < $total_pages): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                                <?php endif; ?>

                                <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>" title="Halaman Berikutnya">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $total_pages; ?>" title="Halaman Terakhir">
                                        <i class="bi bi-chevron-double-right"></i>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Stats Cards -->
                <div class="row mt-4">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="card-title text-muted mb-3">
                                    <i class="bi bi-file-text me-2"></i>Total Post
                                </h6>
                                <h3 class="mb-0" style="color: #6366f1;">
                                    <?php echo $total_posts; ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="card-title text-muted mb-3">
                                    <i class="bi bi-check-circle me-2"></i>Post Publish
                                </h6>
                                <h3 class="mb-0" style="color: #10b981;">
                                    <?php 
                                    $published = mysqli_fetch_assoc(
                                        mysqli_query($conn, "SELECT COUNT(*) as count FROM posts WHERE status='published'")
                                    )['count'];
                                    echo $published;
                                    ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="card-title text-muted mb-3">
                                    <i class="bi bi-pencil me-2"></i>Post Draft
                                </h6>
                                <h3 class="mb-0" style="color: #f59e0b;">
                                    <?php 
                                    $draft = mysqli_fetch_assoc(
                                        mysqli_query($conn, "SELECT COUNT(*) as count FROM posts WHERE status='draft'")
                                    )['count'];
                                    echo $draft;
                                    ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header bg-white border-bottom">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-trash me-2" style="color: #ef4444;"></i>Hapus Post
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Perhatian!</strong> Tindakan ini tidak dapat dibatalkan.
                    </div>
                    <p>Apakah Anda yakin ingin menghapus post:</p>
                    <p class="fw-bold" id="deleteTitle" style="color: #6366f1;"></p>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary fw-600" data-bs-dismiss="modal">Batal</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="post_id" id="deletePostId" value="">
                        <button type="submit" class="btn btn-danger fw-600">
                            <i class="bi bi-trash me-2"></i>Hapus Post
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function setDeleteData(postId, title) {
            document.getElementById('deletePostId').value = postId;
            document.getElementById('deleteTitle').textContent = '"' + title + '"';
        }
    </script>
</body>
</html>