<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Get statistics
$total_posts = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM posts"))['count'];
$published_posts = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM posts WHERE status='published'"))['count'];
$draft_posts = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM posts WHERE status='draft'"))['count'];
$total_views = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(views) as count FROM posts"))['count'] ?? 0;

// Get recent posts
$recent_posts = mysqli_query($conn, "SELECT id, title, status, created_at FROM posts ORDER BY created_at DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - SMK Satya Praja 2</title>
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
                            <i class="bi bi-person-circle"></i> <?php echo $_SESSION['admin_name']; ?>
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
                            <a class="nav-link active" href="index.php">
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
                <h1 class="h2 mb-4">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard Admin
                </h1>

                <!-- Statistics Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6 col-lg-3">
                        <div class="card border-0 shadow-sm" style="border-left: 4px solid var(--primary);">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <small class="text-muted">Total Post</small>
                                        <h3 class="fw-bold" style="color: var(--primary);"><?php echo $total_posts; ?></h3>
                                    </div>
                                    <i class="bi bi-newspaper" style="font-size: 2rem; color: var(--primary); opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-3">
                        <div class="card border-0 shadow-sm" style="border-left: 4px solid var(--success);">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <small class="text-muted">Published</small>
                                        <h3 class="fw-bold" style="color: var(--success);"><?php echo $published_posts; ?></h3>
                                    </div>
                                    <i class="bi bi-check-circle" style="font-size: 2rem; color: var(--success); opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-3">
                        <div class="card border-0 shadow-sm" style="border-left: 4px solid var(--warning);">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <small class="text-muted">Draft</small>
                                        <h3 class="fw-bold" style="color: var(--warning);"><?php echo $draft_posts; ?></h3>
                                    </div>
                                    <i class="bi bi-pencil" style="font-size: 2rem; color: var(--warning); opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-3">
                        <div class="card border-0 shadow-sm" style="border-left: 4px solid var(--accent);">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <small class="text-muted">Total Views</small>
                                        <h3 class="fw-bold" style="color: var(--accent);"><?php echo number_format($total_views); ?></h3>
                                    </div>
                                    <i class="bi bi-eye" style="font-size: 2rem; color: var(--accent); opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Posts -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="card-title fw-bold mb-0">
                            <i class="bi bi-clock-history me-2"></i> Post Terbaru
                        </h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Judul</th>
                                    <th>Status</th>
                                    <th>Tanggal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($post = mysqli_fetch_assoc($recent_posts)) { ?>
                                <tr>
                                    <td class="fw-500"><?php echo htmlspecialchars($post['title']); ?></td>
                                    <td>
                                        <?php if ($post['status'] === 'published') { ?>
                                            <span class="badge bg-success">Published</span>
                                        <?php } else { ?>
                                            <span class="badge bg-warning">Draft</span>
                                        <?php } ?>
                                    </td>
                                    <td><small class="text-muted"><?php echo date('d M Y', strtotime($post['created_at'])); ?></small></td>
                                    <td>
                                        <a href="edit-post.php?id=<?php echo $post['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                    ||
                                        <a href="delete-post.php?id=<?php echo $post['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus post ini?')">
                                            <i class="bi bi-trash"></i> Delete
                                        </a>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>