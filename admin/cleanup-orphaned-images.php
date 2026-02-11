<?php
session_start();
require_once '../config/db.php';
require_once '../config/helpers.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Handle cleanup action
$cleanup_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'cleanup_selected') {
        $orphaned_files = isset($_POST['files']) ? (array)$_POST['files'] : [];
        
        $deleted_count = 0;
        $failed_count = 0;
        $errors = [];
        
        foreach ($orphaned_files as $filename) {
            $filename = basename($filename); // Security: only allow filename, no path
            $file_path = 'uploads/posts/' . $filename;
            
            if (delete_image_file($file_path)) {
                $deleted_count++;
            } else {
                $failed_count++;
                $errors[] = $filename;
            }
        }
        
        $cleanup_result = [
            'deleted' => $deleted_count,
            'failed' => $failed_count,
            'errors' => $errors
        ];
    }
}

// Scan all images in uploads/posts/
$uploads_dir = '../uploads/posts/';
$all_images = [];
if (is_dir($uploads_dir)) {
    $files = scandir($uploads_dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && is_file($uploads_dir . $file)) {
            // Only include image files
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $all_images[] = $file;
            }
        }
    }
}

// Get all image references from all posts
$referenced_images = [];
$posts_query = "SELECT content FROM posts WHERE content IS NOT NULL AND content != ''";
$posts_result = mysqli_query($conn, $posts_query);

while ($row = mysqli_fetch_assoc($posts_result)) {
    $extracted = extract_images_from_content($row['content']);
    foreach ($extracted as $img_path) {
        $filename = basename($img_path);
        $referenced_images[$filename] = true;
    }
}

// Also check featured images
$featured_query = "SELECT featured_image FROM posts WHERE featured_image IS NOT NULL AND featured_image != ''";
$featured_result = mysqli_query($conn, $featured_query);

while ($row = mysqli_fetch_assoc($featured_result)) {
    $filename = basename($row['featured_image']);
    $referenced_images[$filename] = true;
}

// Find orphaned images
$orphaned_images = [];
foreach ($all_images as $img) {
    if (!isset($referenced_images[$img])) {
        $file_path = $uploads_dir . $img;
        $orphaned_images[] = [
            'filename' => $img,
            'file_path' => $file_path,
            'size' => filesize($file_path),
            'size_kb' => round(filesize($file_path) / 1024, 2),
            'modified' => filemtime($file_path),
            'modified_date' => date('d M Y H:i:s', filemtime($file_path))
        ];
    }
}

// Sort by date (newest first)
usort($orphaned_images, function($a, $b) {
    return $b['modified'] - $a['modified'];
});

$total_orphaned = count($orphaned_images);
$total_orphaned_size = array_sum(array_column($orphaned_images, 'size'));

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cleanup Orphaned Images - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/admin-style.css">
    <style>
        .orphaned-item { background: #fff; border-left: 4px solid #ef4444; padding: 1rem; margin-bottom: 0.8rem; border-radius: 4px; }
        .orphaned-item.selected { background: #ffe0e0; }
        .orphaned-item input[type="checkbox"] { margin-right: 1rem; }
        .orphaned-item .filename { font-weight: 600; font-family: monospace; color: #6366f1; }
        .orphaned-item .meta { font-size: 0.85rem; color: #666; margin-top: 0.5rem; }
        .btn-group-actions { margin-top: 1rem; }
        .thumbnail-preview { max-width: 80px; max-height: 80px; border-radius: 4px; margin-right: 1rem; }
    </style>
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
                            <a class="nav-link" href="posts.php">
                                <i class="bi bi-newspaper me-2"></i> Semua Post
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="categories.php">
                                <i class="bi bi-tag me-2"></i> Kategori
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="cleanup-orphaned-images.php">
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
                <h1 class="h2 mb-4">
                    <i class="bi bi-broom me-2"></i> Cleanup Orphaned Images
                </h1>

                <!-- Statistics Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6 col-lg-3">
                        <div class="card border-0 shadow-sm" style="border-left: 4px solid var(--primary);">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <small class="text-muted">Total Images</small>
                                        <h3 class="fw-bold" style="color: var(--primary);"><?php echo count($all_images); ?></h3>
                                    </div>
                                    <i class="bi bi-images" style="font-size: 2rem; color: var(--primary); opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-3">
                        <div class="card border-0 shadow-sm" style="border-left: 4px solid var(--danger);">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <small class="text-muted">Orphaned Images</small>
                                        <h3 class="fw-bold" style="color: var(--danger);"><?php echo $total_orphaned; ?></h3>
                                    </div>
                                    <i class="bi bi-exclamation-circle" style="font-size: 2rem; color: var(--danger); opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-3">
                        <div class="card border-0 shadow-sm" style="border-left: 4px solid var(--warning);">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <small class="text-muted">Orphaned Size</small>
                                        <h3 class="fw-bold" style="color: var(--warning);"><?php echo round($total_orphaned_size / 1024 / 1024, 2); ?> MB</h3>
                                    </div>
                                    <i class="bi bi-folder-fill" style="font-size: 2rem; color: var(--warning); opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-3">
                        <div class="card border-0 shadow-sm" style="border-left: 4px solid var(--success);">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <small class="text-muted">Clean Status</small>
                                        <h3 class="fw-bold" style="color: var(--success);"><?php echo ($total_orphaned == 0 ? '✓ Clean' : 'Alert'); ?></h3>
                                    </div>
                                    <i class="bi bi-<?php echo ($total_orphaned == 0 ? 'check-circle' : 'broom'); ?>" style="font-size: 2rem; color: var(--success); opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

        <!-- Cleanup Result Alert -->
        <?php if ($cleanup_result): ?>
        <div class="alert <?php echo ($cleanup_result['failed'] == 0) ? 'alert-success' : 'alert-warning'; ?> alert-dismissible fade show" role="alert">
            <i class="bi <?php echo ($cleanup_result['failed'] == 0) ? 'bi-check-circle' : 'bi-exclamation-circle'; ?> me-2"></i>
            <strong>Cleanup Result!</strong>
            Berhasil dihapus: <strong><?php echo $cleanup_result['deleted']; ?></strong> file
            <?php if ($cleanup_result['failed'] > 0): ?>
                | Gagal: <strong><?php echo $cleanup_result['failed']; ?></strong> file
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="bi bi-info-circle me-2"></i>
            Halaman akan direload untuk menampilkan data terbaru...
            <script>
                setTimeout(() => location.reload(), 2000);
            </script>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

                <!-- Orphaned Images List -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="card-title fw-bold mb-0">
                            <i class="bi bi-trash me-2" style="color: #ef4444;"></i>
                            Orphaned Images (<?php echo $total_orphaned; ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($total_orphaned > 0): ?>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="cleanup_selected">
                            
                            <div class="mb-3">
                                <div class="btn-group-actions">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllBtn">
                                        <i class="bi bi-check-all me-1"></i>Select All
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllBtn">
                                        <i class="bi bi-x-lg me-1"></i>Deselect All
                                    </button>
                                    <button type="submit" class="btn btn-sm btn-danger" id="deleteSelectedBtn" disabled>
                                        <i class="bi bi-trash me-1"></i>Delete Selected
                                    </button>
                                </div>
                            </div>

                            <div class="orphaned-list">
                                <?php foreach ($orphaned_images as $img): ?>
                                <div class="orphaned-item" data-filename="<?php echo htmlspecialchars($img['filename']); ?>">
                                    <div class="d-flex align-items-center">
                                        <input type="checkbox" class="orphaned-checkbox" name="files[]" value="<?php echo htmlspecialchars($img['filename']); ?>">
                                        
                                        <?php
                                        $ext = strtolower(pathinfo($img['filename'], PATHINFO_EXTENSION));
                                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                                            $img_src = '../uploads/posts/' . htmlspecialchars($img['filename']);
                                            echo '<img src="' . $img_src . '" alt="preview" class="thumbnail-preview">';
                                        }
                                        ?>
                                        
                                        <div class="flex-grow-1">
                                            <div class="filename"><?php echo htmlspecialchars($img['filename']); ?></div>
                                            <div class="meta">
                                                <span class="badge bg-light text-dark me-2"><?php echo $img['size_kb']; ?> KB</span>
                                                <span class="text-muted"><?php echo $img['modified_date']; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <hr>
                            
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>Peringatan:</strong> Gambar yang dihapus tidak bisa dikembalikan. Pastikan tidak ada artikel yang menggunakan gambar ini.
                            </div>
                        </form>

                        <?php else: ?>
                        
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i>
                            <strong>Bagus!</strong> Tidak ada orphaned images. Folder uploads/posts/ sudah clean.
                        </div>

                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const checkboxes = document.querySelectorAll('.orphaned-checkbox');
        const selectAllBtn = document.getElementById('selectAllBtn');
        const deselectAllBtn = document.getElementById('deselectAllBtn');
        const deleteBtn = document.getElementById('deleteSelectedBtn');

        function updateDeleteButton() {
            const checkedCount = document.querySelectorAll('.orphaned-checkbox:checked').length;
            deleteBtn.disabled = checkedCount === 0;
            deleteBtn.textContent = checkedCount > 0 
                ? `Delete Selected (${checkedCount})` 
                : 'Delete Selected';
        }

        function updateItemStyle() {
            document.querySelectorAll('.orphaned-item').forEach(item => {
                const checkbox = item.querySelector('.orphaned-checkbox');
                if (checkbox.checked) {
                    item.classList.add('selected');
                } else {
                    item.classList.remove('selected');
                }
            });
        }

        selectAllBtn.addEventListener('click', () => {
            checkboxes.forEach(cb => cb.checked = true);
            updateDeleteButton();
            updateItemStyle();
        });

        deselectAllBtn.addEventListener('click', () => {
            checkboxes.forEach(cb => cb.checked = false);
            updateDeleteButton();
            updateItemStyle();
        });

        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                updateDeleteButton();
                updateItemStyle();
            });
        });

        // Confirm before delete
        document.querySelector('form')?.addEventListener('submit', function(e) {
            const checkedCount = document.querySelectorAll('.orphaned-checkbox:checked').length;
            if (checkedCount > 0) {
                if (!confirm(`Apakah Anda yakin ingin menghapus ${checkedCount} gambar? Tindakan ini tidak bisa dibatalkan.`)) {
                    e.preventDefault();
                }
            }
        });
    </script>
</body>
</html>
