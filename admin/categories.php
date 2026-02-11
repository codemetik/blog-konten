<?php
session_start();
require_once '../config/db.php';
require_once '../config/helpers.php';

// Cek login
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Handle Add Category
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add') {
        $category_name = escape_string($_POST['category_name'] ?? '');
        
        if (empty($category_name)) {
            $error = "Nama kategori tidak boleh kosong!";
        } else {
            // Cek kategori sudah ada
            $check_query = "SELECT COUNT(*) as count FROM categories WHERE name = '$category_name'";
            $check_result = mysqli_query($conn, $check_query);
            $check_data = mysqli_fetch_assoc($check_result);
            
            if ($check_data['count'] > 0) {
                $error = "Kategori '$category_name' sudah ada!";
            } else {
                $sql = mysqli_query($conn, "INSERT INTO categories(name) VALUES('$category_name')");
                if($sql){
                    $success = "Kategori '$category_name' berhasil ditambahkan!";
                }else{
                    $success = "Kategori '$category_name' gagal ditambahkan!";
                }
            }
        }
    }
    
    // Handle Update Category
    if ($action === 'update') {
        $old_category = escape_string($_POST['old_category'] ?? '');
        $new_category = escape_string($_POST['new_category'] ?? '');
        
        if (empty($new_category)) {
            $error = "Nama kategori tidak boleh kosong!";
        } elseif ($old_category === $new_category) {
            $error = "Nama kategori sama dengan sebelumnya!";
        } else {
            // Update kategori di tabel categories dan posts
            $update_query = "UPDATE categories SET name = '$new_category' WHERE name = '$old_category'";
            $update_posts = "UPDATE posts SET category = '$new_category' WHERE category = '$old_category'";
            if (mysqli_query($conn, $update_query) && mysqli_query($conn, $update_posts)) {
                $success = "Kategori berhasil diperbarui menjadi '$new_category'!";
            } else {
                $error = "Gagal memperbarui kategori: " . mysqli_error($conn);
            }
        }
    }
    
    // Handle Delete Category
    if ($action === 'delete') {
        $category = escape_string($_POST['category'] ?? '');
        $new_category = escape_string($_POST['new_category'] ?? '');
        
        if (empty($new_category)) {
            $error = "Pilih kategori tujuan untuk artikel yang akan dipindahkan!";
        } else {
            // Pindahkan artikel ke kategori baru dan hapus kategori
            $update_query = "UPDATE posts SET category = '$new_category' WHERE category = '$category'";
            $delete_query = "DELETE FROM categories WHERE name = '$category'";
            if (mysqli_query($conn, $update_query) && mysqli_query($conn, $delete_query)) {
                $success = "Kategori '$category' berhasil dihapus dan artikel dipindahkan!";
            } else {
                $error = "Gagal menghapus kategori: " . mysqli_error($conn);
            }
        }
    }
}

// Get semua kategori dari tabel categories dengan LEFT JOIN untuk hitung artikel
$category_query = "
    SELECT 
        c.id,
        c.name,
        COUNT(p.id) as post_count
    FROM categories c
    LEFT JOIN posts p ON c.name = p.category
    GROUP BY c.id, c.name
    ORDER BY c.name ASC
";
$category_result = mysqli_query($conn, $category_query);
$category_counts = [];
while ($row = mysqli_fetch_assoc($category_result)) {
    $category_counts[$row['name']] = $row['post_count'];
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategori - Admin SMK Blog</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/admin-style.css">
    
    <style>
        :root {
            --primary: #6366f1;
            --secondary: #8b5cf6;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --accent: #3b82f6;
        }
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
                            <i class="bi bi-person-circle"></i> <?php echo $_SESSION['admin_name'] ?? 'Admin'; ?>
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
                            <a class="nav-link active" href="categories.php">
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
                <h1 class="h2 mb-4">
                    <i class="bi bi-tag me-2"></i> Kelola Kategori
                </h1>

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

                <!-- Add Category Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="card-title fw-bold mb-0">
                            <i class="bi bi-plus-circle me-2" style="color: var(--primary);"></i>Tambah Kategori Baru
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="add">
                            
                            <div class="mb-3">
                                <label for="category_name" class="form-label fw-600">
                                    <i class="bi bi-tag me-2"></i>Nama Kategori
                                </label>
                                <input type="text" class="form-control" id="category_name" name="category_name" 
                                       placeholder="Contoh: Teknologi, Olahraga, Budaya, Kesehatan..." required>
                                <small class="text-muted d-block mt-2">
                                    <i class="bi bi-info-circle me-1"></i>Masukkan nama kategori yang unik dan deskriptif
                                </small>
                            </div>
                            
                            <div class="d-flex gap-2 pt-2">
                                <button type="submit" class="btn btn-primary fw-600">
                                    <i class="bi bi-plus me-2"></i>Tambah Kategori
                                </button>
                                <button type="reset" class="btn btn-outline-secondary fw-600">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Reset
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Categories List Card -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="card-title fw-bold mb-0">
                            <i class="bi bi-list-ul me-2" style="color: var(--primary);"></i>Daftar Kategori (<?php echo count($category_counts); ?>)
                        </h5>
                    </div>
                    <div class="table-responsive">
                        <?php if (!empty($category_counts)): ?>
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="fw-600">Nama Kategori</th>
                                    <th class="fw-600">Jumlah Artikel</th>
                                    <th class="fw-600">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($category_counts as $category => $count): ?>
                                <tr>
                                    <td class="fw-500">
                                        <span class="badge" style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); padding: 0.5rem 1rem;">
                                            <i class="bi bi-tag me-2"></i><?php echo htmlspecialchars($category); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-primary">
                                            <i class="bi bi-file-text me-1"></i><?php echo $count; ?> artikel
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-outline-primary fw-600" data-bs-toggle="modal" 
                                                    data-bs-target="#editModal" 
                                                    onclick="setEditData('<?php echo htmlspecialchars($category); ?>', <?php echo $count; ?>)">
                                                <i class="bi bi-pencil me-1"></i> Edit
                                            </button>
                                            <button type="button" class="btn btn-outline-danger fw-600" data-bs-toggle="modal" 
                                                    data-bs-target="#deleteModal"
                                                    onclick="setDeleteData('<?php echo htmlspecialchars($category); ?>', <?php echo $count; ?>)">
                                                <i class="bi bi-trash me-1"></i> Hapus
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
                            <h5 class="mb-1 text-muted">Belum Ada Kategori</h5>
                            <p class="text-muted small">Tambahkan kategori pertama Anda di form di atas</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header bg-white border-bottom">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-pencil me-2" style="color: var(--primary);"></i>Edit Kategori
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="old_category" id="oldCategory">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-600">Nama Kategori Baru</label>
                            <input type="text" class="form-control" name="new_category" id="newCategory" required>
                        </div>
                        <p class="small text-muted">
                            <i class="bi bi-info-circle me-1"></i> <span id="editInfo"></span>
                        </p>
                    </div>
                    
                    <div class="modal-footer border-top">
                        <button type="button" class="btn btn-outline-secondary fw-600" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary fw-600">
                            <i class="bi bi-save me-2"></i>Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header bg-white border-bottom">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-trash me-2" style="color: var(--danger);"></i>Hapus Kategori
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="category" id="deleteCategory">
                    
                    <div class="modal-body">
                        <div class="alert alert-warning mb-3">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Perhatian!</strong> <span id="deleteWarning"></span>
                        </div>
                        
                        <p class="text-muted small mb-3">Pilih kategori tujuan untuk memindahkan semua artikel dari kategori ini:</p>
                        
                        <div class="mb-3">
                            <label class="form-label fw-600">Pindahkan ke Kategori</label>
                            <select class="form-select" name="new_category" id="deleteNewCategory" required>
                                <option value="">-- Pilih Kategori --</option>
                                <?php foreach ($category_counts as $cat => $cnt): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>">
                                    <?php echo htmlspecialchars($cat); ?> (<?php echo $cnt; ?> artikel)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="modal-footer border-top">
                        <button type="button" class="btn btn-outline-secondary fw-600" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger fw-600">
                            <i class="bi bi-trash me-2"></i>Hapus Kategori
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function setEditData(category, count) {
            document.getElementById('oldCategory').value = category;
            document.getElementById('newCategory').value = category;
            document.getElementById('editInfo').textContent = 'Mengubah nama kategori akan memperbarui ' + count + ' artikel';
        }
        
        function setDeleteData(category, count) {
            document.getElementById('deleteCategory').value = category;
            document.getElementById('deleteWarning').textContent = 'Kategori ini memiliki ' + count + ' artikel.';
            
            // Disable current category in select
            const select = document.getElementById('deleteNewCategory');
            Array.from(select.options).forEach(option => {
                if (option.value === category) {
                    option.disabled = true;
                } else {
                    option.disabled = false;
                }
            });
        }
    </script>
</body>
</html>