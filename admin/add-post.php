<?php
session_start();
require_once '../config/db.php';
require_once '../config/helpers.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$message = '';
$alert_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = escape_string($_POST['title']);
    $category = escape_string($_POST['category']);
    $excerpt = escape_string($_POST['excerpt']);
    $content = $_POST['content'];
    $status = escape_string($_POST['status']);
    $author = escape_string($_SESSION['admin_name']);
    
    // Generate slug from title menggunakan helper function
    $slug = generate_slug($title);
    
    // Handle featured image upload
    $featured_image = '';
    if (!empty($_FILES['featured_image']['name'])) {
        $file = $_FILES['featured_image'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        // Get actual mime type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            $alert_type = 'warning';
            $message = '⚠️ Tipe gambar tidak valid. Gunakan JPG, PNG, GIF, atau WebP';
        } elseif ($file['size'] > $max_size) {
            $alert_type = 'warning';
            $message = '⚠️ Ukuran gambar terlalu besar (max 5MB). Post akan tetap disimpan tanpa gambar utama';
        } else {
            // Buat folder uploads/posts jika belum ada
            $upload_dir = '../uploads/posts/';
            if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, 0755, true);
            }
            
            // Generate nama file dengan format konsisten
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $unique_id = bin2hex(random_bytes(8));
            $timestamp = time();
            $file_name = 'posts_' . $timestamp . '_' . $unique_id . '.' . $file_ext;
            $upload_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Simpan path relatif terhadap root (uploads/posts/filename)
                $featured_image = 'uploads/posts/' . $file_name;
            } else {
                $alert_type = 'warning';
                $message = '⚠️ Gagal upload gambar. Post akan tetap disimpan tanpa gambar utama';
            }
        }
    }
    
    // Validation
    if (empty($title)) {
        $alert_type = 'danger';
        $message = '❌ Judul tidak boleh kosong!';
    } elseif (empty($excerpt)) {
        $alert_type = 'danger';
        $message = '❌ Ringkasan tidak boleh kosong!';
    } elseif (empty($content)) {
        $alert_type = 'danger';
        $message = '❌ Konten tidak boleh kosong!';
    } elseif (empty($category)) {
        $alert_type = 'danger';
        $message = '❌ Kategori harus dipilih!';
    } else {
        // Normalize dan process content images SEBELUM escape
        $content = normalize_image_paths($content);
        $content = process_content_images($content, true);
        
        // Baru escape untuk database
        $content = mysqli_real_escape_string($conn, $content);
        $title = mysqli_real_escape_string($conn, $title);
        $excerpt = mysqli_real_escape_string($conn, $excerpt);
        $featured_image = mysqli_real_escape_string($conn, $featured_image);
        
        // Insert post
        $query = "INSERT INTO posts (title, slug, excerpt, content, category, featured_image, author, status) 
                  VALUES ('$title', '$slug', '$excerpt', '$content', '$category', '$featured_image', '$author', '$status')";
        
        if (mysqli_query($conn, $query)) {
            $post_id = mysqli_insert_id($conn);
            $alert_type = 'success';
            $message = "✅ Post berhasil disimpan! <a href='../post.php?id=$post_id' target='_blank' class='alert-link'>Lihat post</a>";
            
            // Reset form
            $_POST = array();
        } else {
            $alert_type = 'danger';
            $message = '❌ Gagal menyimpan post: ' . mysqli_error($conn);
        }
    }
}

// Get categories for dropdown
$categories_query = "SELECT name FROM categories ORDER BY name ASC";
$categories_result = mysqli_query($conn, $categories_query);
$categories = [];
while ($row = mysqli_fetch_assoc($categories_result)) {
    $categories[] = $row['name'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Post Baru - Admin SMK Satya Praja 2</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/admin-style.css">
    <style>
        /* ===== SUMMERNOTE PROFESSIONAL STYLING ===== */
        
        /* Textarea - Base styling sebelum Summernote transform */
        textarea#summernote {
            display: block;
            width: 100%;
            min-height: 300px;
            padding: 1.5rem;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            font-family: inherit;
            font-size: 1rem;
            line-height: 1.65;
        }
        
        /* Editor Container */
        .note-editor {
            display: block !important;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
            overflow: visible;
            background-color: #ffffff;
            position: relative;
        }

        .note-editor.note-focused {
            border-color: #6366f1;
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.1);
        }

        /* ===== TOOLBAR STYLING ===== */
        .note-toolbar {
            background: linear-gradient(to bottom, #f8f9fa 0%, #f5f6f7 100%);
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
            min-height: auto;
            overflow: visible;
            position: relative;
        }

        .note-toolbar.note-btn-group {
            margin-right: 0.25rem;
            display: flex;
            gap: 0.25rem;
            border-right: 1px solid #dee2e6;
            padding-right: 0.5rem;
        }

        /* Toolbar Buttons */
        .note-btn {
            padding: 0.425rem 0.625rem;
            font-size: 0.85rem;
            border: 1px solid transparent;
            background-color: transparent;
            color: #495057;
            cursor: pointer;
            transition: all 0.2s ease;
            border-radius: 0.375rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            min-height: 32px;
            white-space: nowrap;
        }

        .note-btn:not(:disabled):hover {
            background-color: #e9ecef;
            border-color: #dee2e6;
            color: #212529;
        }

        .note-btn:not(:disabled):active {
            background-color: #e0e0e0;
        }

        .note-btn.active,
        .note-btn.note-btn-active,
        .note-btn[aria-pressed="true"] {
            background-color: #6366f1;
            border-color: #6366f1;
            color: white;
            box-shadow: 0 2px 4px rgba(99, 102, 241, 0.3);
        }

        .note-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Dropdown Separator */
        .note-btn.note-dropdown-toggle::after {
            content: '';
            display: inline-block;
            width: 0;
            height: 0;
            margin-left: 0.3rem;
            vertical-align: 0.255em;
            border-top: 0.3em solid;
            border-right: 0.3em solid transparent;
            border-bottom: 0;
            border-left: 0.3em solid transparent;
        }

        /* ===== EDITABLE AREA ===== */
        .note-editable {
            background-color: #ffffff;
            font-size: 1rem;
            line-height: 1.65;
            padding: 1.5rem;
            min-height: 300px;
            max-height: 600px;
            color: #212529;
            overflow-y: auto;
            position: relative;
            word-wrap: break-word;
            word-break: break-word;
            overflow-wrap: break-word;
            white-space: pre-wrap;
            display: block;
        }

        .note-editable:focus {
            outline: none;
        }

        .note-editable * {
            max-width: 100%;
            box-sizing: border-box;
        }

        /* ===== TEXT STYLING DALAM EDITOR ===== */
        .note-editable h1 { font-size: 2rem; font-weight: 700; margin: 1.5rem 0 1rem; line-height: 1.2; }
        .note-editable h2 { font-size: 1.75rem; font-weight: 600; margin: 1.25rem 0 0.875rem; line-height: 1.3; }
        .note-editable h3 { font-size: 1.5rem; font-weight: 600; margin: 1rem 0 0.75rem; line-height: 1.3; }
        .note-editable h4 { font-size: 1.25rem; font-weight: 600; margin: 0.875rem 0 0.625rem; }
        .note-editable h5 { font-size: 1.1rem; font-weight: 600; margin: 0.75rem 0 0.5rem; }
        .note-editable h6 { font-size: 1rem; font-weight: 600; margin: 0.75rem 0 0.5rem; }

        .note-editable p {
            margin: 0 0 1rem;
            text-align: justify;
        }

        .note-editable strong, .note-editable b {
            font-weight: 600;
            color: #212529;
        }

        .note-editable em, .note-editable i {
            font-style: italic;
        }

        /* Lists */
        .note-editable ul, .note-editable ol {
            margin: 1rem 0;
            padding-left: 2rem;
        }

        .note-editable li {
            margin-bottom: 0.5rem;
            line-height: 1.65;
        }

        .note-editable ul li::marker {
            color: #6366f1;
        }

        /* Blockquote */
        .note-editable blockquote {
            margin: 1rem 0;
            padding: 1rem 1.25rem;
            border-left: 4px solid #6366f1;
            background-color: #f8f9fa;
            color: #495057;
            font-style: italic;
            border-radius: 0.375rem;
        }

        /* Links */
        .note-editable a {
            color: #6366f1;
            text-decoration: none;
            border-bottom: 1px solid transparent;
            transition: all 0.2s ease;
        }

        .note-editable a:hover {
            color: #4f46e5;
            border-bottom-color: #6366f1;
        }

        /* Code */
        .note-editable code {
            background-color: #f5f5f5;
            border: 1px solid #e0e0e0;
            border-radius: 0.25rem;
            padding: 0.2rem 0.4rem;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            color: #d63384;
        }

        .note-editable pre {
            display: block;
            background-color: #f5f5f5;
            border: 1px solid #e0e0e0;
            border-radius: 0.375rem;
            padding: 1rem;
            margin: 1rem 0;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            line-height: 1.45;
            color: #212529;
        }

        /* Tables */
        .note-editable table {
            border-collapse: collapse;
            width: 100%;
            margin: 1rem 0;
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            overflow: hidden;
        }

        .note-editable table th,
        .note-editable table td {
            padding: 0.75rem 1rem;
            border: 1px solid #dee2e6;
            text-align: left;
        }

        .note-editable table thead th {
            background: linear-gradient(to bottom, #f8f9fa, #e9ecef);
            font-weight: 600;
            color: #212529;
            border-bottom: 2px solid #dee2e6;
        }

        .note-editable table tbody tr:hover {
            background-color: #f8f9fa;
        }

        /* Images */
        .note-editable img {
            max-width: 100%;
            height: auto;
            display: block;
            margin: 1rem 0;
            border-radius: 0.375rem;
            border: 1px solid #dee2e6;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
            transition: all 0.2s ease;
        }

        .note-editable img:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.12);
        }

        /* Video Containers */
        .note-editable iframe {
            max-width: 100%;
            height: auto;
            margin: 1rem 0;
            border-radius: 0.375rem;
        }

        .note-editable video {
            max-width: 100%;
            height: auto;
            display: block;
            margin: 1rem 0;
            border-radius: 0.375rem;
        }

        .video-container,
        .embed-container {
            position: relative;
            width: 100%;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
            margin: 1rem 0;
            border-radius: 0.375rem;
            background-color: #000;
        }

        .video-container iframe,
        .embed-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: 0;
        }

        /* Horizontal Rule */
        .note-editable hr {
            margin: 1.5rem 0;
            border: 0;
            height: 1px;
            background: linear-gradient(to right, transparent, #dee2e6, transparent);
        }

        /* Placeholder */
        .note-placeholder {
            color: #adb5bd;
            font-style: italic;
        }

        /* ===== DROPDOWN MENU ===== */
        .note-dropdown-menu {
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 0.375rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            min-width: 180px;
            z-index: 10000 !important;
            padding: 0.5rem 0;
            animation: fadeIn 0.15s ease;
            position: absolute !important;
            overflow: visible !important;
            max-height: 500px;
            overflow-y: auto;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .note-dropdown-menu > .note-dropdown-item {
            display: block;
            width: 100%;
            padding: 0.625rem 1rem;
            color: #212529;
            text-decoration: none;
            cursor: pointer;
            background-color: transparent;
            border: 0;
            text-align: left;
            transition: all 0.15s ease;
            font-size: 0.9rem;
        }

        .note-dropdown-menu > .note-dropdown-item:hover {
            background-color: #f0f0f0;
            color: #6366f1;
            padding-left: 1.25rem;
        }

        .note-dropdown-menu > .note-dropdown-item.note-dropdown-divider {
            padding: 0.5rem 0;
            margin: 0.5rem 0;
            border-top: 1px solid #e0e0e0;
        }

        /* ===== MODAL/DIALOG ===== */
        .note-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 9999 !important;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.15s ease;
            overflow: auto;
        }

        .note-modal.in {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .note-modal-content {
            position: relative;
            background-color: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            z-index: 10001 !important;
        }

        .note-modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #dee2e6;
            background: linear-gradient(to bottom, #f8f9fa, #f5f6f7);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .note-modal-title {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: #212529;
        }

        .note-modal-body {
            padding: 1.5rem;
        }

        .note-modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid #dee2e6;
            background-color: #f8f9fa;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        /* ===== FORM ELEMENTS ===== */
        .note-form-group {
            margin-bottom: 1.25rem;
        }

        .note-form-group label {
            display: block;
            margin-bottom: 0.625rem;
            font-weight: 500;
            color: #212529;
            font-size: 0.95rem;
        }

        .note-form-group input[type="text"],
        .note-form-group input[type="url"],
        .note-form-group input[type="number"],
        .note-form-group select,
        .note-form-group textarea {
            display: block;
            width: 100%;
            padding: 0.625rem 0.875rem;
            font-size: 0.95rem;
            line-height: 1.5;
            color: #495057;
            background-color: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            transition: all 0.2s ease;
            font-family: inherit;
        }

        .note-form-group input[type="text"]:focus,
        .note-form-group input[type="url"]:focus,
        .note-form-group input[type="number"]:focus,
        .note-form-group select:focus,
        .note-form-group textarea:focus {
            color: #495057;
            background-color: #ffffff;
            border-color: #6366f1;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.1);
        }

        /* ===== POPOVER ===== */
        .note-popover {
            display: none;
            position: absolute;
            top: 0;
            left: 0;
            z-index: 10000 !important;
            background-color: rgba(0, 0, 0, 0.95);
            border-radius: 0.375rem;
            padding: 0.5rem;
            min-width: 200px;
            animation: fadeIn 0.15s ease;
            overflow: visible !important;
        }

        .note-popover.in {
            display: block;
        }

        .note-popover-arrow {
            position: absolute;
            bottom: -5px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 5px solid transparent;
            border-right: 5px solid transparent;
            border-top: 5px solid rgba(0, 0, 0, 0.95);
        }

        /* ===== CODE VIEW ===== */
        .note-codable {
            background-color: #f5f5f5;
            border: none;
            border-radius: 0;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            padding: 1.5rem;
            min-height: 300px;
            max-height: 600px;
            line-height: 1.5;
            color: #212529;
            overflow-y: auto;
        }

        /* ===== STATUS BAR ===== */
        .note-statusbar {
            background: linear-gradient(to top, #f5f6f7, #f8f9fa);
            border-top: 1px solid #e0e0e0;
            padding: 0.75rem 1rem;
            font-size: 0.8rem;
            color: #6c757d;
            border-radius: 0 0 0.5rem 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* ===== SCROLLBAR STYLING ===== */
        .note-editable::-webkit-scrollbar,
        .note-codable::-webkit-scrollbar {
            width: 8px;
        }

        .note-editable::-webkit-scrollbar-track,
        .note-codable::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .note-editable::-webkit-scrollbar-thumb,
        .note-codable::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }

        .note-editable::-webkit-scrollbar-thumb:hover,
        .note-codable::-webkit-scrollbar-thumb:hover {
            background: #6366f1;
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
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['admin_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
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
                            <a class="nav-link active" href="add-post.php">
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h2">
                        <i class="bi bi-plus-circle me-2"></i> Tambah Post Baru
                    </h1>
                    <a href="posts.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Kembali
                    </a>
                </div>

                <!-- Alert Messages -->
                <?php if (!empty($message)) {
                    echo show_message($alert_type, $message);
                } ?>

                <!-- Form -->
                <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <div class="row g-4">
                        <!-- Main Content Column -->
                        <div class="col-lg-8">
                            <!-- Title -->
                            <div class="card shadow-sm mb-4">
                                <div class="card-body">
                                    <h5 class="card-title fw-bold mb-3">
                                        <i class="bi bi-pencil me-2"></i> Judul Post
                                    </h5>
                                    <input type="text" class="form-control form-control-lg" 
                                           name="title" placeholder="Masukkan judul post..." 
                                           value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                                    <small class="text-muted d-block mt-2">
                                        💡 Judul harus menarik dan deskriptif
                                    </small>
                                </div>
                            </div>

                            <!-- Featured Image -->
                            <div class="card shadow-sm mb-4">
                                <div class="card-body">
                                    <h5 class="card-title fw-bold mb-3">
                                        <i class="bi bi-image me-2"></i> Gambar Utama
                                    </h5>
                                    <div class="mb-3">
                                        <input type="file" class="form-control" 
                                               name="featured_image" accept="image/jpeg,image/png,image/gif,image/webp"
                                               id="featuredImage">
                                        <small class="text-muted d-block mt-2">
                                            📷 Format: JPG, PNG, GIF, WebP | Ukuran Maksimal: 5MB
                                        </small>
                                    </div>
                                    <div id="imagePreview"></div>
                                </div>
                            </div>

                            <!-- Excerpt -->
                            <div class="card shadow-sm mb-4">
                                <div class="card-body">
                                    <h5 class="card-title fw-bold mb-3">
                                        <i class="bi bi-chat-left-text me-2"></i> Ringkasan (Excerpt)
                                    </h5>
                                    <textarea class="form-control" name="excerpt" rows="3" 
                                              placeholder="Ringkasan singkat post (akan ditampilkan di halaman daftar)..."
                                              required><?php echo htmlspecialchars($_POST['excerpt'] ?? ''); ?></textarea>
                                    <small class="text-muted d-block mt-2">
                                        <span id="charCount">0</span>/250 karakter
                                    </small>
                                </div>
                            </div>

                            <!-- Content Editor -->
                            <div class="card shadow-sm mb-4">
                                <div class="card-body">
                                    <h5 class="card-title fw-bold mb-3">
                                        <i class="bi bi-file-earmark-richtext me-2"></i> Konten Post
                                    </h5>
                                    <textarea class="form-control" id="summernote" name="content" required><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Sidebar Column -->
                        <div class="col-lg-4">
                            <!-- Status -->
                            <div class="card shadow-sm mb-4">
                                <div class="card-body">
                                    <h5 class="card-title fw-bold mb-3">
                                        <i class="bi bi-arrow-repeat me-2"></i> Status
                                    </h5>
                                    <div class="mb-3">
                                        <select class="form-select" name="status" required>
                                            <option value="draft" <?php echo ($_POST['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>
                                                📝 Draft
                                            </option>
                                            <option value="published" <?php echo ($_POST['status'] ?? '') === 'published' ? 'selected' : ''; ?>>
                                                ✅ Publish
                                            </option>
                                        </select>
                                    </div>
                                    <small class="text-muted d-block">
                                        🔔 Draft tidak akan ditampilkan di website
                                    </small>
                                </div>
                            </div>

                            <!-- Category -->
                            <div class="card shadow-sm mb-4">
                                <div class="card-body">
                                    <h5 class="card-title fw-bold mb-3">
                                        <i class="bi bi-tag me-2"></i> Kategori
                                    </h5>
                                    <select class="form-select" name="category" required>
                                        <option value="">Pilih Kategori...</option>
                                        <?php foreach ($categories as $cat) { ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>" 
                                                <?php echo $cat; ?>>
                                            <?php echo htmlspecialchars($cat); ?>
                                        </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Tips -->
                            <div class="alert alert-info">
                                <h6 class="alert-heading fw-bold mb-2">
                                    <i class="bi bi-lightbulb me-2"></i> Tips Menulis Post
                                </h6>
                                <ul class="small mb-0 ps-3">
                                    <li>Gunakan judul yang menarik dan deskriptif</li>
                                    <li>Sertakan gambar berkualitas tinggi</li>
                                    <li>Tulis ringkasan yang menarik</li>
                                    <li>Format konten dengan heading dan paragraf</li>
                                    <li>Gunakan bold/italic untuk emphasis</li>
                                    <li>Sertakan link yang relevan</li>
                                </ul>
                            </div>

                            <!-- Buttons -->
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg fw-bold" id="submitBtn">
                                    <i class="bi bi-check-circle me-2"></i> Simpan Post
                                </button>
                                <a href="posts.php" class="btn btn-secondary btn-lg">
                                    <i class="bi bi-x-circle me-2"></i> Batal
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/lang/summernote-id-ID.min.js"></script>

    <script>
    $(document).ready(function() {
        // Debug log
        console.log('Document ready');
        console.log('Summernote element exists:', $('#summernote').length > 0);
        console.log('jQuery version:', $.fn.jquery);
        
        // Summernote Editor - Konfigurasi Standard
        try {
            $('#summernote').summernote({
                lang: 'id-ID',
                height: 400,
                minHeight: 300,
                maxHeight: 600,
                tabsize: 2,
                fontNames: ['Arial', 'Arial Black', 'Comic Sans MS', 'Courier New', 'Georgia', 'Tahoma', 'Times New Roman', 'Trebuchet MS', 'Verdana'],
                fontSizes: ['8', '9', '10', '11', '12', '14', '16', '18', '20', '24', '28', '32'],
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
                    ['fontname', ['fontname']],
                    ['fontsize', ['fontsize']],
                    ['color', ['color', 'forecolor', 'backcolor']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['height', ['height']],
                    ['table', ['table']],
                    ['insert', ['link', 'picture', 'hr']],
                    ['view', ['fullscreen', 'codeview', 'help']]
                ],
                placeholder: 'Mulai menulis konten post di sini...',
                codetags: ['pre', 'code'],
                styleTags: ['p', 'blockquote', 'pre', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'],
                // Video configuration
                videoAttributes: {
                    videoResizeHelper: {
                        options: ['height', 'width'],
                        onImageLinkInput: function(url) {
                            console.log('Video URL input:', url);
                        }
                    }
                },
                callbacks: {
                    onImageUpload: function(files) {
                        uploadImage(files[0]);
                    },
                    onMediaDelete: function($target) {
                        const imageSrc = $target.attr('src');
                        console.log('Image deleted - attempting to remove from server:', imageSrc);
                        deleteImageFromServer(imageSrc);
                    },
                    onInit: function() {
                        console.log('Summernote initialized successfully');
                        fixMediaStyles();
                        // Ensure editor is visible
                        $('.note-editor').css('display', 'block');
                        $('.note-editable').css('display', 'block');
                    },
                    onChange: function(contents, $editable) {
                        // Process and convert video URLs to embed format
                        processAndConvertVideos();
                        processVideoEmbeds();
                    }
                }
            });
            console.log('Summernote initialized');
            
            // Start observing for iframe insertions
            if (typeof observeEditorForIframes === 'function') {
                observeEditorForIframes();
            }
            
            // Hook into Summernote's insertNode to catch video/iframe insertions
            $('#summernote').on('summernote.change', function() {
                console.log('Summernote change event fired');
                processAndConvertVideos();
                processVideoEmbeds();
            });
        } catch(err) {
            console.error('Error initializing Summernote:', err);
        }

        // Summernote dialog handler - REMOVED - Let Summernote handle all dialogs natively
        // No custom handlers - Summernote 0.8.18 handles image, link, video dialogs perfectly without interference
        
        // When Summernote dialog opens, bind to the dialog's insert button to capture video URL
        $(document).on('summernote.dialog.shown', function() {
            const $dialog = $('.note-modal.in, .note-dialog, .note-modal').last();
            if (!$dialog || !$dialog.length) return;

            const $insertBtn = $dialog.find('.note-btn-primary, .btn-primary');
            $insertBtn.off('click.insertVideo').on('click.insertVideo', function(e) {
                const urlInput = $dialog.find('input[type="text"], input[type="url"]').val();
                console.log('Video dialog insert clicked, URL:', urlInput);
                if (urlInput) {
                    e.preventDefault();
                    e.stopPropagation();
                    insertVideoEmbed(urlInput);
                    // Close dialog/backdrop
                    $dialog.removeClass('in').hide();
                    $('.modal-backdrop, .note-modal-backdrop').remove();
                    return false;
                }
            });
        });


        // Excerpt character counter
        $('textarea[name="excerpt"]').on('keyup', function() {
            const charCount = $(this).val().length;
            $('#charCount').text(charCount);
            
            if (charCount > 250) {
                $(this).val($(this).val().substring(0, 250));
                $('#charCount').text('250');
            }
        });

        // Initialize character count on load
        $('#charCount').text($('textarea[name="excerpt"]').val().length);

        // Image Preview untuk featured image
        $('#featuredImage').on('change', function(e) {
            const file = this.files[0];
            
            if (file) {
                // Validasi tipe file
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    showNotification('danger', '❌ Tipe file tidak sesuai. Gunakan JPG, PNG, GIF, atau WebP');
                    $(this).val('');
                    $('#imagePreview').html('');
                    return;
                }
                
                // Validasi ukuran (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    showNotification('danger', '❌ Ukuran file terlalu besar. Maksimal 5MB');
                    $(this).val('');
                    $('#imagePreview').html('');
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const fileSize = (file.size / 1024).toFixed(2);
                    $('#imagePreview').html(`
                        <div class="mt-3 p-3 bg-light rounded">
                            <label class="d-block mb-2 small fw-bold text-success">
                                <i class="bi bi-check-circle me-1"></i> Preview Gambar Baru:
                            </label>
                            <img src="${e.target.result}" class="img-fluid rounded" style="max-width: 100%; max-height: 300px; display: block;">
                            <small class="text-muted d-block mt-2">
                                ✅ Gambar siap diupload | Ukuran: ${fileSize} KB
                            </small>
                        </div>
                    `);
                };
                reader.readAsDataURL(file);
            }
        });

        // Form Validation
        const forms = document.querySelectorAll('.needs-validation');
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
                
                // Disable submit button
                const submitBtn = document.getElementById('submitBtn');
                if (form.checkValidity()) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Menyimpan...';
                }
            });
        });
    });

    // Upload image function untuk Summernote - dengan full storage handling
    function uploadImage(file) {
        // Validasi file sebelum upload
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        const maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!allowedTypes.includes(file.type)) {
            showNotification('danger', '❌ Tipe file tidak sesuai. Gunakan JPG, PNG, GIF, atau WebP');
            $('#summernote').summernote('restoreRange');
            return;
        }
        
        if (file.size > maxSize) {
            showNotification('danger', '❌ Ukuran file terlalu besar. Maksimal 5MB');
            $('#summernote').summernote('restoreRange');
            return;
        }
        
        const formData = new FormData();
        formData.append('file', file);
        
        // Show loading indicator
        showNotification('info', 'ℹ️ Sedang mengunggah gambar...');

        $.ajax({
            url: 'upload-image.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            timeout: 30000,
            success: function(response) {
                console.log('Upload response:', response);
                
                if (response.success) {
                    // Gunakan URL yang sesuai untuk Summernote
                    // URL relatif dari admin folder: ../uploads/posts/filename
                    const imageUrl = response.url;
                    
                    console.log('Inserting image with URL:', imageUrl);
                    console.log('DB Path:', response.db_path);
                    
                    // Insert image ke Summernote dengan error handling
                    try {
                        $('#summernote').summernote('insertImage', imageUrl, function($image) {
                            if ($image && $image.length) {
                                $image.css('max-width', '100%');
                                $image.css('height', 'auto');
                                $image.addClass('img-fluid');
                                $image.attr('alt', file.name);
                                $image.attr('title', file.name);
                                $image.attr('data-file-name', response.file_name);
                                
                                // Store reference untuk tracking
                                console.log('Image inserted successfully:', {
                                    src: imageUrl,
                                    filename: response.file_name,
                                    size: response.file_size_kb + ' KB',
                                    mime: response.mime_type
                                });
                            }
                        });
                        
                        showNotification('success', '✅ Gambar berhasil diupload dan disimpan: ' + response.file_name + ' (' + response.file_size_kb + ' KB)');
                    } catch (err) {
                        console.error('Error inserting image:', err);
                        showNotification('danger', '❌ Gambar diupload tapi gagal ditambahkan ke editor. Coba lagi atau copy URL: ' + imageUrl);
                    }
                } else {
                    showNotification('danger', '❌ Gagal upload gambar: ' + (response.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('Upload error:', error);
                console.error('Status:', status);
                console.error('Response:', xhr.responseText);
                
                let errorMsg = 'Gagal upload gambar';
                let errorDetails = '';
                
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMsg = response.message || errorMsg;
                    errorDetails = response.detected_mime ? ' (Detected MIME: ' + response.detected_mime + ')' : '';
                } catch(e) {
                    if (status === 'timeout') {
                        errorMsg = 'Upload timeout. File terlalu besar atau koneksi lambat.';
                    }
                }
                
                showNotification('danger', '❌ ' + errorMsg + errorDetails);
            }
        });
    }


    // Function untuk show notification
    function showNotification(type, message) {
        const alertClass = 'alert-' + type;
        
        const alertHTML = `
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        
        // Insert notification
        $('main').prepend(alertHTML);
        
        // Auto dismiss setelah 5 detik
        setTimeout(function() {
            $('.alert').fadeOut('fast', function() {
                $(this).remove();
            });
        }, 5000);
    }

    // ===== VIDEO HANDLING FUNCTIONS =====
    
    // Validate video URL format
    function isValidVideoUrl(url) {
        if (!url) return false;
        
        // YouTube patterns
        const youtubePatterns = [
            /youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/,
            /youtu\.be\/([a-zA-Z0-9_-]{11})/,
            /youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/
        ];
        
        // Vimeo patterns
        const vimeoPatterns = [
            /vimeo\.com\/(\d+)/,
            /player\.vimeo\.com\/video\/(\d+)/
        ];
        
        // Check YouTube
        for (let pattern of youtubePatterns) {
            if (pattern.test(url)) return true;
        }
        
        // Check Vimeo
        for (let pattern of vimeoPatterns) {
            if (pattern.test(url)) return true;
        }
        
        // Check if it's already an embed URL
        if (url.includes('iframe') || url.startsWith('<iframe')) {
            return true;
        }
        
        // Check if it's a direct video file
        if (/\.(mp4|webm|ogg|mov|avi)$/i.test(url)) {
            return true;
        }
        
        return false;
    }

    // Insert video embed directly into editor
    function insertVideoEmbed(url) {
        console.log('insertVideoEmbed called with URL:', url);
        
        if (!url) {
            showNotification('danger', '❌ URL video tidak boleh kosong');
            return;
        }
        
        // Convert URL to embed format
        const embedUrl = convertToEmbedUrl(url);
        console.log('Embed URL:', embedUrl);
        
        if (!embedUrl) {
            showNotification('danger', '❌ Format URL video tidak didukung. Gunakan YouTube atau Vimeo');
            return;
        }
        
        // Create iframe HTML
        const iframeHTML = `<div class="video-container">
            <iframe src="${embedUrl}" 
                    width="560" 
                    height="315" 
                    frameborder="0" 
                    allow="autoplay; encrypted-media" 
                    allowfullscreen></iframe>
        </div>`;
        
        // Insert into editor
        $('#summernote').summernote('pasteHTML', iframeHTML);
        console.log('Video iframe inserted');
        showNotification('success', '✅ Video berhasil disisipkan ke dalam artikel');
    }

    // Convert video URL to embed URL
    function convertToEmbedUrl(url) {
        if (!url) return null;
        
        // YouTube patterns
        let youtubeMatch = url.match(/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/);
        if (!youtubeMatch) {
            youtubeMatch = url.match(/youtu\.be\/([a-zA-Z0-9_-]{11})/);
        }
        if (youtubeMatch) {
            return `https://www.youtube.com/embed/${youtubeMatch[1]}`;
        }
        
        // YouTube embed format
        let youtubeEmbedMatch = url.match(/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/);
        if (youtubeEmbedMatch) {
            return url;
        }
        
        // Vimeo patterns
        let vimeoMatch = url.match(/vimeo\.com\/(\d+)/);
        if (!vimeoMatch) {
            vimeoMatch = url.match(/player\.vimeo\.com\/video\/(\d+)/);
        }
        if (vimeoMatch) {
            return `https://player.vimeo.com/video/${vimeoMatch[1]}`;
        }
        
        // If already iframe or direct file, return as is
        return url;
    }

    // Process and convert video URLs to embed format
    function processAndConvertVideos() {
        const editor = $('#summernote').next('.note-editor').find('.note-editable');
        
        console.log('processAndConvertVideos called');
        
        // 1. First, handle any text links/URLs that are just sitting in the editor
        editor.contents().each(function() {
            if (this.nodeType === Node.TEXT_NODE) {
                let text = this.nodeValue;
                const youtubeMatch = text.match(/(https?:\/\/(?:www\.)?(youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11}))/);
                const vimeoMatch = text.match(/(https?:\/\/(?:www\.)?vimeo\.com\/(\d+))/);
                
                if (youtubeMatch || vimeoMatch) {
                    console.log('Found video URL in text:', youtubeMatch ? youtubeMatch[0] : vimeoMatch[0]);
                    // We found a video URL, but we need to replace it in context
                    // For now, we'll let onChange be called after user pastes
                }
            }
        });
        
        // 2. Find all iframes with video URLs and convert them to embed URLs
        editor.find('iframe').each(function() {
            const $iframe = $(this);
            let src = $iframe.attr('src');
            
            if (!src) return;
            
            console.log('Processing iframe with src:', src);
            
            // Check if URL needs conversion (YouTube, Vimeo, etc)
            if (isValidVideoUrl(src)) {
                // Convert URL to proper embed format
                const embedUrl = convertToEmbedUrl(src);
                
                if (embedUrl !== src) {
                    console.log('Converting URL from', src, 'to', embedUrl);
                    $iframe.attr('src', embedUrl);
                }
            }
            
            // Ensure iframe has proper attributes for video
            if (!$iframe.attr('allowfullscreen')) {
                $iframe.attr('allowfullscreen', 'allowfullscreen');
            }
            if (!$iframe.attr('allow')) {
                $iframe.attr('allow', 'autoplay; encrypted-media');
            }
            if (!$iframe.attr('frameborder')) {
                $iframe.attr('frameborder', '0');
            }
            
            // Wrap with video-container if not already wrapped
            if (!$iframe.parent().hasClass('video-container')) {
                $iframe.wrap('<div class="video-container"></div>');
                console.log('Wrapped iframe in video-container');
            }
        });
    }
    
    function processVideoEmbeds() {
        const editor = $('#summernote').next('.note-editor').find('.note-editable');
        
        editor.find('iframe').each(function() {
            const $iframe = $(this);
            const src = $iframe.attr('src');
            
            if (src && (src.includes('youtube') || src.includes('vimeo'))) {
                // Wrap in video container if not already wrapped
                if (!$iframe.parent().hasClass('video-container')) {
                    $iframe.wrap('<div class="video-container"></div>');
                }
                
                // Add styling
                $iframe.css({
                    'border': 'none',
                    'border-radius': '6px'
                });
            }
        });
    }

    // Observe editor for newly inserted iframes and process them immediately
    function observeEditorForIframes() {
        const editorNode = document.querySelector('#summernote').nextElementSibling?.querySelector('.note-editable');
        if (!editorNode) return;

        const observer = new MutationObserver(function(mutations) {
            for (const m of mutations) {
                if (m.addedNodes && m.addedNodes.length) {
                    m.addedNodes.forEach(function(node) {
                        // If an iframe was directly added
                        if (node.nodeName === 'IFRAME') {
                            processAndConvertVideos();
                            processVideoEmbeds();
                        } else if (node.querySelector) {
                            // If node contains iframes
                            const iframes = node.querySelectorAll('iframe');
                            if (iframes.length) {
                                processAndConvertVideos();
                                processVideoEmbeds();
                            }
                        }
                    });
                }
            }
        });

        observer.observe(editorNode, { childList: true, subtree: true });
    }

    // Function untuk fix media styles (images, videos, etc)
    function fixMediaStyles() {
        const editor = $('#summernote').next('.note-editor').find('.note-editable');
        
        // Fix images
        editor.find('img').each(function() {
            $(this).css({
                'max-width': '100%',
                'height': 'auto',
                'display': 'block',
                'margin': '0.5rem 0',
                'border-radius': '0.25rem'
            }).addClass('img-fluid');
        });
        
        // Fix iframes (video embeds)
        editor.find('iframe').each(function() {
            const $iframe = $(this);
            const src = $iframe.attr('src');
            
            // Only wrap if not already wrapped
            if (!$iframe.parent().hasClass('video-container')) {
                $iframe.wrap('<div class="video-container"></div>');
            }
            
            $iframe.css({
                'max-width': '100%',
                'height': 'auto',
                'border': 'none',
                'border-radius': '6px'
            });
        });
        
        // Fix embedded videos
        editor.find('video').each(function() {
            $(this).css({
                'max-width': '100%',
                'height': 'auto',
                'display': 'block',
                'margin': '0.5rem 0',
                'border-radius': '6px'
            }).addClass('img-fluid');
        });
    }

    // Function untuk delete gambar dari server
    function deleteImageFromServer(imageSrc) {
        // Extract filename dari URL
        let filename = '';
        
        if (imageSrc.indexOf('posts_') > -1) {
            // Extract filename dari path: ../uploads/posts/posts_xxxxx.jpg atau uploads/posts/posts_xxxxx.jpg
            const parts = imageSrc.split('/');
            filename = parts[parts.length - 1];
        }
        
        if (!filename) {
            console.log('Could not extract filename from:', imageSrc);
            return;
        }
        
        console.log('Attempting to delete:', filename);
        
        // AJAX call ke delete handler
        $.ajax({
            url: 'delete-image.php',
            type: 'POST',
            data: {
                file_name: filename
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    console.log('Image deleted from server:', filename);
                    showNotification('success', '✅ Gambar berhasil dihapus dari server');
                } else {
                    console.log('Failed to delete image:', response.message);
                    showNotification('warning', '⚠️ Gambar dihapus dari editor tapi file tidak dihapus dari server');
                }
            },
            error: function(xhr, status, error) {
                console.log('Error deleting image:', error);
                // Tidak show error notification karena user sudah menghapus dari editor
                // yang penting adalah gambar sudah dihapus dari UI
            }
        });
    }
    </script>
</body>
</html>