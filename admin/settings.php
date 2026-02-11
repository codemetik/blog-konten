<?php
session_start();
require_once '../config/db.php';
require_once '../config/helpers.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Function to scan directory recursively and build tree
function scanDirectoryTree($path, $basePath = null, $depth = 0) {
    if ($basePath === null) {
        $basePath = $path;
    }
    
    $items = [];
    $maxDepth = 3; // Limit depth to avoid too much nesting
    
    if (!is_dir($path) || $depth > $maxDepth) {
        return $items;
    }
    
    try {
        $files = scandir($path);
        sort($files);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $fullPath = $path . DIRECTORY_SEPARATOR . $file;
            $relativePath = str_replace($basePath, '', $fullPath);
            
            // Skip certain directories
            $skipDirs = ['.git', 'node_modules', '.vscode'];
            $skip = false;
            foreach ($skipDirs as $skipDir) {
                if (strpos($file, $skipDir) !== false) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;
            
            if (is_dir($fullPath)) {
                $items[] = [
                    'name' => $file,
                    'type' => 'dir',
                    'path' => $relativePath,
                    'children' => scanDirectoryTree($fullPath, $basePath, $depth + 1),
                    'depth' => $depth
                ];
            } else {
                $size = filesize($fullPath);
                $items[] = [
                    'name' => $file,
                    'type' => 'file',
                    'path' => $relativePath,
                    'size' => $size,
                    'size_display' => formatFileSize($size),
                    'modified' => filemtime($fullPath),
                    'modified_date' => date('d M Y H:i', filemtime($fullPath)),
                    'depth' => $depth
                ];
            }
        }
    } catch (Exception $e) {
        // Handle errors silently
    }
    
    return $items;
}

// Function to format file size
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

// Function to get file icon
function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $icons = [
        'php' => 'bi-filetype-php',
        'html' => 'bi-filetype-html',
        'css' => 'bi-filetype-css',
        'js' => 'bi-filetype-js',
        'json' => 'bi-filetype-json',
        'sql' => 'bi-database',
        'jpg' => 'bi-image',
        'jpeg' => 'bi-image',
        'png' => 'bi-image',
        'gif' => 'bi-image',
        'webp' => 'bi-image',
        'txt' => 'bi-file-text',
        'md' => 'bi-markdown',
        'pdf' => 'bi-filetype-pdf'
    ];
    
    return isset($icons[$ext]) ? $icons[$ext] : 'bi-file-earmark';
}

// Scan project directory
$projectPath = '../';
$projectStructure = scanDirectoryTree($projectPath);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/admin-style.css">
    <style>
        .folder-tree { margin: 1rem 0; }
        .tree-item { margin: 0.25rem 0; }
        .tree-dir { font-weight: 600; color: #6366f1; }
        .tree-file { color: #333; }
        .tree-indent { display: inline-block; }
        .file-badge { font-size: 0.75rem; }
        .tree-item-info { color: #999; font-size: 0.85rem; }
        .file-type-icon { width: 20px; text-align: center; margin-right: 0.5rem; }
        .tree-container { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 1rem; font-family: 'Courier New', monospace; max-height: 600px; overflow-y: auto; }
        .breadcrumb-path { background: #e7f3ff; border-left: 4px solid #2196F3; padding: 1rem; border-radius: 4px; margin-bottom: 1rem; font-family: monospace; }
        .stats-card { border-left: 4px solid #6366f1; }
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
                            <a class="nav-link" href="cleanup-orphaned-images.php">
                                <i class="bi bi-file-image me-2"></i> Clean Orphaned
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="settings.php">
                                <i class="bi bi-gear me-2"></i> Pengaturan
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <h1 class="h2 mb-4">
                    <i class="bi bi-gear me-2"></i> Pengaturan & Struktur Proyek
                </h1>

                <!-- Breadcrumb Path -->
                <div class="breadcrumb-path">
                    <i class="bi bi-folder-fill"></i> 
                    <strong>Lokasi Proyek:</strong> <code>c:\laragon\www\webai\blog-konten</code>
                </div>

                <!-- Project Statistics -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6 col-lg-3">
                        <div class="card border-0 shadow-sm stats-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <small class="text-muted">Versi PHP</small>
                                        <h3 class="fw-bold" style="color: #6366f1;"><?php echo phpversion(); ?></h3>
                                    </div>
                                    <i class="bi bi-filetype-php" style="font-size: 2rem; color: #6366f1; opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-3">
                        <div class="card border-0 shadow-sm stats-card" style="border-left-color: #ff9800;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <small class="text-muted">Max Upload</small>
                                        <h3 class="fw-bold" style="color: #ff9800;"><?php echo ini_get('upload_max_filesize'); ?></h3>
                                    </div>
                                    <i class="bi bi-cloud-arrow-up" style="font-size: 2rem; color: #ff9800; opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-3">
                        <div class="card border-0 shadow-sm stats-card" style="border-left-color: #4caf50;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <small class="text-muted">Memory Limit</small>
                                        <h3 class="fw-bold" style="color: #4caf50;"><?php echo ini_get('memory_limit'); ?></h3>
                                    </div>
                                    <i class="bi bi-memory" style="font-size: 2rem; color: #4caf50; opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-3">
                        <div class="card border-0 shadow-sm stats-card" style="border-left-color: #2196f3;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <small class="text-muted">Max Post Size</small>
                                        <h3 class="fw-bold" style="color: #2196f3;"><?php echo ini_get('post_max_size'); ?></h3>
                                    </div>
                                    <i class="bi bi-hdd" style="font-size: 2rem; color: #2196f3; opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Folder Structure -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="card-title fw-bold mb-0">
                            <i class="bi bi-folder2-open me-2" style="color: #ff9800;"></i>
                            Struktur Folder & File Proyek
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="tree-container">
                            <div class="tree-item" style="margin-bottom: 1rem;">
                                <span class="tree-dir">
                                    <i class="bi bi-folder-fill"></i> blog-konten/
                                </span>
                            </div>
                            
                            <?php 
                            function renderTreeItems($items, $depth = 1) {
                                foreach ($items as $item): 
                                    $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth);
                                    $isLast = (array_key_last($items) === array_search($item, array_values($items)));
                                    $connector = $isLast ? '└── ' : '├── ';
                                    ?>
                                    <div class="tree-item" style="margin-left: <?php echo ($depth * 15); ?>px;">
                                        <span style="font-size: 0.9rem; color: #666;"><?php echo $connector; ?></span>
                                        
                                        <?php if ($item['type'] === 'dir'): ?>
                                            <span class="tree-dir">
                                                <i class="bi bi-folder-fill"></i> <?php echo htmlspecialchars($item['name']); ?>/
                                            </span>
                                            <?php if (!empty($item['children'])): ?>
                                                <div>
                                                    <?php renderTreeItems($item['children'], $depth + 1); ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="tree-file">
                                                <i class="bi <?php echo getFileIcon($item['name']); ?>"></i> 
                                                <?php echo htmlspecialchars($item['name']); ?>
                                            </span>
                                            <span class="tree-item-info ms-2">
                                                (<?php echo $item['size_display']; ?> • <?php echo $item['modified_date']; ?>)
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach;
                            }
                            
                            renderTreeItems($projectStructure);
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Tab Sections -->
                <ul class="nav nav-tabs mb-4" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="config-tab" data-bs-toggle="tab" data-bs-target="#config-content" type="button" role="tab">
                            <i class="bi bi-gear me-2"></i>Konfigurasi
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="uploads-tab" data-bs-toggle="tab" data-bs-target="#uploads-content" type="button" role="tab">
                            <i class="bi bi-cloud-arrow-up me-2"></i>Upload Folders
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="database-tab" data-bs-toggle="tab" data-bs-target="#database-content" type="button" role="tab">
                            <i class="bi bi-database me-2"></i>Database
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- Config Tab -->
                    <div class="tab-pane fade show active" id="config-content" role="tabpanel">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="card-title mb-3"><i class="bi bi-file-earmark-code me-2"></i>File Konfigurasi</h6>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item">
                                        <strong>config/db.php</strong>
                                        <small class="d-block text-muted">Konfigurasi koneksi database MySQL</small>
                                    </li>
                                    <li class="list-group-item">
                                        <strong>config/helpers.php</strong>
                                        <small class="d-block text-muted">Fungsi-fungsi helper dan utilitas</small>
                                    </li>
                                    <li class="list-group-item">
                                        <strong>css/style.css</strong>
                                        <small class="d-block text-muted">Stylesheet untuk halaman publik</small>
                                    </li>
                                    <li class="list-group-item">
                                        <strong>css/admin-style.css</strong>
                                        <small class="d-block text-muted">Stylesheet untuk halaman admin</small>
                                    </li>
                                    <li class="list-group-item">
                                        <strong>js/script.js</strong>
                                        <small class="d-block text-muted">Script JavaScript untuk frontend</small>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Uploads Tab -->
                    <div class="tab-pane fade" id="uploads-content" role="tabpanel">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="card-title mb-3"><i class="bi bi-folder-check me-2"></i>Folder Upload</h6>
                                <div class="row g-3">
                                    <?php 
                                    $uploadFolders = [
                                        ['path' => 'uploads/articles', 'desc' => 'Gambar artikel'],
                                        ['path' => 'uploads/featured', 'desc' => 'Gambar featured article'],
                                        ['path' => 'uploads/posts', 'desc' => 'Gambar dalam post/konten']
                                    ];
                                    
                                    foreach ($uploadFolders as $folder):
                                        $fullPath = '../' . $folder['path'];
                                        $exists = is_dir($fullPath);
                                        $fileCount = 0;
                                        $folderSize = 0;
                                        
                                        if ($exists) {
                                            $files = array_diff(scandir($fullPath), ['.', '..']);
                                            foreach ($files as $file) {
                                                if (is_file($fullPath . '/' . $file)) {
                                                    $fileCount++;
                                                    $folderSize += filesize($fullPath . '/' . $file);
                                                }
                                            }
                                        }
                                    ?>
                                    <div class="col-md-6">
                                        <div class="card border <?php echo $exists ? 'border-success' : 'border-danger'; ?>">
                                            <div class="card-body">
                                                <h6 class="card-title">
                                                    <i class="bi bi-folder-fill me-2" style="color: #ff9800;"></i>
                                                    <?php echo htmlspecialchars($folder['path']); ?>
                                                </h6>
                                                <p class="card-text text-muted small mb-2"><?php echo htmlspecialchars($folder['desc']); ?></p>
                                                <div class="d-flex justify-content-between">
                                                    <span><strong>File:</strong> <?php echo $fileCount; ?></span>
                                                    <span><strong>Ukuran:</strong> <?php echo formatFileSize($folderSize); ?></span>
                                                </div>
                                                <div class="mt-2">
                                                    <span class="badge <?php echo $exists ? 'bg-success' : 'bg-danger'; ?>">
                                                        <?php echo $exists ? '✓ Ada' : '✗ Tidak Ada'; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Database Tab -->
                    <div class="tab-pane fade" id="database-content" role="tabpanel">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="card-title mb-3"><i class="bi bi-database me-2"></i>Informasi Database</h6>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item">
                                        <strong>Database</strong>
                                        <small class="d-block text-muted">smk_blog_db</small>
                                    </li>
                                    <li class="list-group-item">
                                        <strong>File Dump</strong>
                                        <small class="d-block text-muted">database/smk_blog_db.sql</small>
                                    </li>
                                    <li class="list-group-item">
                                        <strong>Koneksi</strong>
                                        <small class="d-block text-muted">
                                            User: root | Host: localhost
                                        </small>
                                    </li>
                                    <li class="list-group-item">
                                        <strong>Tabel Utama</strong>
                                        <small class="d-block text-muted">
                                            • posts • categories • users • images_log
                                        </small>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Documentation -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="card-title fw-bold mb-0">
                            <i class="bi bi-file-earmark-text me-2"></i>File Dokumentasi
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php 
                            $docFiles = [
                                'BUG_FIX_SUMMARY.md',
                                'CLEANUP_MENU_INTEGRATION.md',
                                'IMAGE_DELETION_FINAL_REPORT.md',
                                'IMAGE_DELETION_FIX_COMPLETE.md',
                                'IMAGE_DELETION_FIX.md',
                                'ORPHANED_IMAGES_CLEANUP.md',
                                'TECHNICAL_REFERENCE_IMAGE_DELETION.md',
                                'TESTING_GUIDE_IMAGE_DELETION.md'
                            ];
                            
                            foreach ($docFiles as $doc):
                                $docPath = '../' . $doc;
                                if (file_exists($docPath)):
                                    $size = formatFileSize(filesize($docPath));
                            ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">
                                            <i class="bi bi-markdown me-2" style="color: #6366f1;"></i>
                                            <?php echo htmlspecialchars($doc); ?>
                                        </h6>
                                    </div>
                                    <span class="badge bg-light text-dark"><?php echo $size; ?></span>
                                </div>
                            </div>
                            <?php endif; endforeach; ?>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle tab switching
        document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
            tab.addEventListener('shown.bs.tab', function() {
                // Store active tab in localStorage
                localStorage.setItem('activeSettingsTab', this.getAttribute('data-bs-target'));
            });
        });

        // Restore active tab
        const activeTab = localStorage.getItem('activeSettingsTab');
        if (activeTab) {
            const tabButton = document.querySelector(`[data-bs-target="${activeTab}"]`);
            if (tabButton) {
                new bootstrap.Tab(tabButton).show();
            }
        }
    </script>
</body>
</html>
