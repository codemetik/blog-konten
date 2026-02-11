<?php
session_start();
require_once '../config/db.php';
require_once '../config/helpers.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Get post ID from URL
$post_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($post_id === 0) {
    $_SESSION['error'] = '❌ Post tidak ditemukan!';
    header('Location: index.php');
    exit;
}

// Get post data
$query = "SELECT * FROM posts WHERE id = $post_id";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) === 0) {
    $_SESSION['error'] = '❌ Post tidak ditemukan!';
    header('Location: index.php');
    exit;
}

$post = mysqli_fetch_assoc($result);

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
    
    // Store old content untuk cleanup gambar
    $old_content = $post['content'];
    $old_featured = $post['featured_image'];
    
    // Generate slug from title menggunakan helper function
    $slug = generate_slug($title);
    
    $featured_image = $post['featured_image'];
    $upload_success = true;
    
    // Handle featured image upload
    if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['featured_image'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 5 * 1024 * 1024;
            
            // Get actual mime type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mime_type, $allowed_types)) {
                $message = '❌ Tipe file tidak sesuai. Gunakan JPG, PNG, GIF, atau WebP';
                $alert_type = 'danger';
                $upload_success = false;
            } elseif ($file['size'] > $max_size) {
                $message = '❌ Ukuran file terlalu besar. Maksimal 5MB';
                $alert_type = 'danger';
                $upload_success = false;
            } else {
                // Gunakan folder yang sama: uploads/posts/
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
                    // Delete old featured image if exists
                    if (!empty($post['featured_image']) && file_exists('../' . $post['featured_image'])) {
                        @unlink('../' . $post['featured_image']);
                    }
                    // Simpan path relatif terhadap root
                    $featured_image = 'uploads/posts/' . $file_name;
                } else {
                    $message = '❌ Gagal upload gambar. Periksa permission folder uploads';
                    $alert_type = 'danger';
                    $upload_success = false;
                }
            }
        } else {
            $message = '❌ Terjadi error saat upload file';
            $alert_type = 'danger';
            $upload_success = false;
        }
    }

    // Observe editor for newly inserted iframes and process them immediately
    // (Moved to client-side script section to avoid PHP parse errors)
    
    // Validasi form
    if (empty($title)) {
        $message = '❌ Judul tidak boleh kosong!';
        $alert_type = 'danger';
        $upload_success = false;
    } elseif (empty($excerpt)) {
        $message = '❌ Ringkasan tidak boleh kosong!';
        $alert_type = 'danger';
        $upload_success = false;
    } elseif (empty($content)) {
        $message = '❌ Konten tidak boleh kosong!';
        $alert_type = 'danger';
        $upload_success = false;
    } elseif (empty($category)) {
        $message = '❌ Kategori harus dipilih!';
        $alert_type = 'danger';
        $upload_success = false;
    } else {
        // Proses konten jika semua validasi lolos dan upload berhasil
        if ($upload_success) {
            // Cleanup orphaned images SEBELUM escape string
            // (jika gambar tidak di-upload, cleanup tetap perlu dilakukan)
            $content = normalize_image_paths($content);
            $content = process_content_images($content, true);
            
            // Cleanup orphaned images SEBELUM real_escape_string
            $cleanup_result = cleanup_orphaned_images($old_content, $content, $old_featured, $featured_image);
            
            // Baru escape untuk database
            $content = mysqli_real_escape_string($conn, $content);
            $featured_image = mysqli_real_escape_string($conn, $featured_image);
            $title = mysqli_real_escape_string($conn, $title);
            $excerpt = mysqli_real_escape_string($conn, $excerpt);
            
            $update_query = "UPDATE posts 
                            SET title = '$title', 
                                slug = '$slug', 
                                excerpt = '$excerpt', 
                                content = '$content', 
                                category = '$category', 
                                featured_image = '$featured_image', 
                                author = '$author', 
                                status = '$status', 
                                updated_at = NOW() 
                            WHERE id = $post_id";
            
            if (mysqli_query($conn, $update_query)) {
                $_SESSION['success'] = '✅ Post berhasil diperbarui! (Gambar dibersihkan: ' . $cleanup_result['deleted'] . ')';
                
                // Redirect ke index.php admin
                echo "<script>
                    setTimeout(function() {
                        window.location.href = 'index.php';
                    }, 1500);
                </script>";
                exit;
            } else {
                $message = '❌ Gagal memperbarui post: ' . mysqli_error($conn);
                $alert_type = 'danger';
            }
        }
    }
}

// Get categories for dropdown
$categories_query = "SELECT DISTINCT category FROM posts ORDER BY category ASC";
$categories_result = mysqli_query($conn, $categories_query);
$categories = [];
while ($row = mysqli_fetch_assoc($categories_result)) {
    $categories[] = $row['category'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Post - Admin SMK Satya Praja 2</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/admin-style.css">
    <style>
        /* ===== SUMMERNOTE PROFESSIONAL STYLING ===== */
        
        /* GLOBAL OVERRIDE - Remove all height restrictions */
        .note-editor * {
            max-height: none !important;
        }
        
        .note-editor,
        .note-frame {
            height: auto !important;
            max-height: none !important;
        }
        
        /* Textarea - Base styling sebelum Summernote transform */
        textarea#summernote {
            display: block;
            width: 100%;
            min-height: 500px;
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
            border: 1px solid #dee2e6 !important;
            border-radius: 0.5rem !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08) !important;
            overflow: visible !important;
            background-color: #ffffff !important;
            position: relative !important;
            height: auto !important;
            min-height: 600px !important;
            max-height: none !important;
        }

        .note-editor.note-focused {
            border-color: #6366f1 !important;
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.1) !important;
        }

        /* Editable area - no height restrictions for full content display */
        .note-editable {
            min-height: 500px !important;
            max-height: none !important;
            height: auto !important;
            overflow: visible !important;
            padding: 1.5rem !important;
            line-height: 1.65 !important;
            word-break: break-word !important;
            word-wrap: break-word !important;
            white-space: normal !important;
            display: block !important;
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
        }

        .note-editable * {
            max-width: 100%;
            box-sizing: border-box;
            word-break: break-word;
            word-wrap: break-word;
        }

        /* ===== TEXT STYLING DALAM EDITOR ===== */
        .note-editable h1 { font-size: 2rem; font-weight: 700; margin: 1.5rem 0 1rem; line-height: 1.2; overflow-wrap: break-word; }
        .note-editable h2 { font-size: 1.75rem; font-weight: 600; margin: 1.25rem 0 0.875rem; line-height: 1.3; overflow-wrap: break-word; }
        .note-editable h3 { font-size: 1.5rem; font-weight: 600; margin: 1rem 0 0.75rem; line-height: 1.3; overflow-wrap: break-word; }
        .note-editable h4 { font-size: 1.25rem; font-weight: 600; margin: 0.875rem 0 0.625rem; overflow-wrap: break-word; }
        .note-editable h5 { font-size: 1.1rem; font-weight: 600; margin: 0.75rem 0 0.5rem; overflow-wrap: break-word; }
        .note-editable h6 { font-size: 1rem; font-weight: 600; margin: 0.75rem 0 0.5rem; overflow-wrap: break-word; }

        .note-editable p {
            margin: 0 0 1rem;
            text-align: justify;
            overflow-wrap: break-word;
            word-break: break-word;
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
            word-break: break-word;
            overflow-wrap: break-word;
            white-space: normal;
        }

        .note-editable pre {
            display: block;
            background-color: #f5f5f5;
            border: 1px solid #e0e0e0;
            border-radius: 0.375rem;
            padding: 1rem;
            margin: 1rem 0;
            overflow-x: auto;
            overflow-y: visible;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            line-height: 1.45;
            color: #212529;
            max-height: none;
            white-space: pre-wrap;
            word-break: break-word;
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
            min-height: 500px;
            max-height: none !important;
            height: auto !important;
            line-height: 1.5;
            color: #212529;
            overflow-x: auto;
            overflow-y: visible;
            word-break: break-word;
            white-space: pre-wrap;
            word-wrap: break-word;
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

        /* Additional: Fix Summernote internal containers */
        .note-editor .note-editing-area {
            height: auto !important;
            max-height: none !important;
        }

        .note-editor .note-editable-area {
            height: auto !important;
            max-height: none !important;
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h2">
                        <i class="bi bi-pencil-square me-2"></i> Edit Post
                    </h1>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
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
                                           value="<?php echo htmlspecialchars($post['title']); ?>" required>
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
                                    
                                    <!-- Current Image -->
                                    <?php if (!empty($post['featured_image']) && file_exists('../' . $post['featured_image'])) { ?>
                                    <div class="mb-4">
                                        <label class="d-block mb-2 text-muted small fw-bold">
                                            <i class="bi bi-check-circle me-1 text-success"></i> Gambar Saat Ini:
                                        </label>
                                        <div>
                                            <img src="../<?php echo htmlspecialchars($post['featured_image']); ?>" 
                                                 class="img-fluid rounded" style="max-width: 100%; max-height: 250px; display: block;">
                                            <small class="text-muted d-block mt-2">
                                                Path: <?php echo htmlspecialchars($post['featured_image']); ?> | 
                                                Ukuran: <?php 
                                                    $file_size = filesize('../' . $post['featured_image']);
                                                    echo number_format($file_size / 1024, 2) . ' KB';
                                                ?>
                                            </small>
                                        </div>
                                    </div>
                                    <?php } elseif (!empty($post['featured_image'])) { ?>
                                    <div class="alert alert-warning mb-3">
                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                        <strong>File Gambar Tidak Ditemukan:</strong> <?php echo htmlspecialchars($post['featured_image']); ?>
                                        <br><small>Silakan upload gambar baru untuk menggantikan</small>
                                    </div>
                                    <?php } ?>

                                    <div class="mb-3">
                                        <label class="d-block mb-2 small fw-bold">
                                            <?php echo !empty($post['featured_image']) ? 'Ganti Gambar (Opsional):' : 'Upload Gambar (Wajib):'; ?>
                                        </label>
                                        <div class="input-group">
                                            <input type="file" class="form-control" 
                                                   name="featured_image" accept="image/jpeg,image/png,image/gif,image/webp"
                                                   id="featuredImage">
                                            <span class="input-group-text">
                                                <i class="bi bi-upload"></i>
                                            </span>
                                        </div>
                                        <small class="text-muted d-block mt-2">
                                            📷 Format: JPG, PNG, GIF, WebP | Ukuran Maksimal: 5MB
                                        </small>
                                    </div>

                                    <!-- Image Preview -->
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
                                              required><?php echo htmlspecialchars($post['excerpt']); ?></textarea>
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
                                    <textarea class="form-control" id="summernote" 
                                              name="content" required data-initial-content="<?php echo htmlspecialchars(json_encode($post['content']), ENT_QUOTES, 'UTF-8'); ?>">
<?php echo htmlspecialchars_decode($post['content']); ?>
                                    </textarea>
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
                                            <option value="draft" <?php echo $post['status'] === 'draft' ? 'selected' : ''; ?>>
                                                📝 Draft
                                            </option>
                                            <option value="published" <?php echo $post['status'] === 'published' ? 'selected' : ''; ?>>
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
                                                <?php echo $post['category'] === $cat ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat); ?>
                                        </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Post Info -->
                            <div class="card shadow-sm mb-4 bg-light">
                                <div class="card-body">
                                    <h5 class="card-title fw-bold mb-3">
                                        <i class="bi bi-info-circle me-2"></i> Informasi Post
                                    </h5>
                                    <dl class="row g-2 mb-0 small">
                                        <dt class="col-6">ID:</dt>
                                        <dd class="col-6">#<?php echo $post['id']; ?></dd>
                                        
                                        <dt class="col-6">Dibuat:</dt>
                                        <dd class="col-6"><?php echo format_date_short($post['created_at']); ?></dd>
                                        
                                        <dt class="col-6">Diperbarui:</dt>
                                        <dd class="col-6"><?php echo format_date_short($post['updated_at']); ?></dd>
                                        
                                        <dt class="col-6">Penulis:</dt>
                                        <dd class="col-6"><?php echo htmlspecialchars($post['author']); ?></dd>
                                        
                                        <dt class="col-6">Views:</dt>
                                        <dd class="col-6"><?php echo number_format($post['views']); ?> 👁️</dd>
                                    </dl>
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
                                    <i class="bi bi-check-circle me-2"></i> Simpan Perubahan
                                </button>
                                <a href="index.php" class="btn btn-secondary btn-lg">
                                    <i class="bi bi-x-circle me-2"></i> Batal ke Dashboard
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
    // ============================================================================
    // EARLY DEBUG - CHECK TEXTAREA CONTENT BEFORE SUMMERNOTE TOUCHES IT
    // ============================================================================
    const textareaEl = document.getElementById('summernote');
    if (textareaEl) {
        const textareaLen = textareaEl.value.length;
        const dataAttrLen = textareaEl.getAttribute('data-initial-content')?.length || 0;
        console.log('🔍 EARLY INSPECTION:');
        console.log('  Textarea content length:', textareaLen);
        console.log('  Data attribute length:', dataAttrLen);
        console.log('  First 200 chars:', textareaEl.value.substring(0, 200));
        if (textareaLen === 0) {
            console.warn('⚠️ WARNING: Textarea is empty!');
        }
    }
    
    // INJECT POWERFUL CSS OVERRIDE SEBELUM SUMMERNOTE INITIALIZE
    const styleSheet = document.createElement('style');
    styleSheet.textContent = `
        /* AGGRESSIVE HEIGHT REMOVAL FOR SUMMERNOTE */
        .note-editor,
        .note-editor.note-frame,
        .note-editor .note-editing-area,
        .note-editor .note-editable,
        .note-editor .note-editable-area,
        .note-editable.note-placeholder {
            height: auto !important;
            max-height: none !important;
            min-height: 600px !important;
            overflow: visible !important;
        }
        
        .note-codable {
            height: auto !important;
            max-height: none !important;
            overflow-y: visible !important;
        }
        
        /* Remove any potential overflow that hides content */
        .note-editor .note-editable {
            overflow: visible !important;
        }
        
        .note-editing-area {
            overflow: visible !important;
            height: auto !important;
            max-height: none !important;
        }
    `;
    document.head.appendChild(styleSheet);
    
    $(document).ready(function() {
        // Debug log
        console.log('Document ready (edit-post)');
        console.log('Summernote element exists:', $('#summernote').length > 0);
        console.log('jQuery version:', $.fn.jquery);
        
        // Summernote Editor - Konfigurasi dengan DISABLE HEIGHT LIMITS
        try {
            $('#summernote').summernote({
                lang: 'id-ID',
                height: undefined,
                minHeight: undefined,
                maxHeight: undefined,
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
            videoAttributes: {
                videoResizeHelper: {
                    options: ['height', 'width'],
                    onImageLinkInput: function(url) {
                        console.log('Video URL input detected:', url);
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
                    console.log('Summernote initialized successfully (edit-post)');
                    
                    // GET FULL CONTENT FROM DATA ATTRIBUTE dan ensure di-load ke editor
                    const $summernote = $('#summernote');
                    const initialContentAttr = $summernote.attr('data-initial-content');
                    let fullContent = '';
                    
                    if (initialContentAttr) {
                        try {
                            fullContent = JSON.parse(initialContentAttr);
                            console.log('📥 Loaded initial content from data attribute, length:', fullContent.length);
                            
                            // Set content ke Summernote editor dengan multiple attempts
                            for (let attempt = 0; attempt < 5; attempt++) {
                                setTimeout(() => {
                                    $summernote.summernote('code', fullContent);
                                    console.log(`📝 Attempt ${attempt + 1}: Content set to Summernote editor`);
                                    
                                    // Force the editor to recognize the content
                                    $summernote.summernote('focus');
                                }, 50 + (attempt * 100));
                            }
                        } catch (e) {
                            console.error('❌ Error parsing initial content:', e);
                            fullContent = $summernote.val();
                        }
                    } else {
                        fullContent = $summernote.val();
                        console.log('ℹ️ No data attribute found, using textarea content');
                    }
                    
                    console.log('Initial content length in editor:', fullContent.length);
                    
                    // Immediately start adjusting heights AFTER content loaded
                    for (let i = 0; i < 15; i++) {
                        setTimeout(() => {
                            adjustEditorHeight();
                            autoAdjustWithRetry();
                        }, 150 + (i * 200));
                    }
                    
                    fixMediaStyles();
                    fixImagePaths();
                    // Start observing editor for inserted iframes
                    if (typeof observeEditorForIframes === 'function') {
                        observeEditorForIframes();
                    }
                    // Ensure editor is visible
                    $('.note-editor').css({
                        'display': 'block',
                        'height': 'auto',
                        'max-height': 'none'
                    });
                    $('.note-editable').css({
                        'display': 'block',
                        'height': 'auto',
                        'max-height': 'none'
                    });
                },
                onChange: function(contents, $editable) {
                    // Process and convert video URLs to embed format
                    processAndConvertVideos();
                    processVideoEmbeds();
                    // Adjust height based on content
                    adjustEditorHeight();
                }
            }
        });
        } catch(err) {
            console.error('Error initializing Summernote:', err);
        }

        // Start observing for iframe insertions
        if (typeof observeEditorForIframes === 'function') {
            observeEditorForIframes();
        }

        // Hook into Summernote's change event to catch video/iframe insertions
        $('#summernote').on('summernote.change', function() {
            console.log('Summernote change event fired (edit-post)');
            processAndConvertVideos();
            processVideoEmbeds();
        });

        // When Summernote dialog opens, bind to the dialog's insert button to capture video URL
        $(document).on('summernote.dialog.shown', function() {
            const $dialog = $('.note-modal.in, .note-dialog, .note-modal').last();
            if (!$dialog || !$dialog.length) return;

            const $insertBtn = $dialog.find('.note-btn-primary, .btn-primary');
            $insertBtn.off('click.insertVideo').on('click.insertVideo', function(e) {
                const urlInput = $dialog.find('input[type="text"], input[type="url"]').val();
                console.log('Video dialog insert clicked (edit-post), URL:', urlInput);
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

        // Fix media styles and paths whenever content changes
        $('#summernote').on('summernote.change', function() {
            fixMediaStyles();
            fixImagePaths();
        });
        
        // Fix image paths on load (convert uploads/posts/ to ../uploads/posts/)
        fixImagePaths();

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
                                ✅ Gambar siap diganti | Ukuran: ${fileSize} KB
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

    // Upload image function untuk Summernote
    function uploadImage(file) {
        // Validasi file sebelum upload
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        const maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!allowedTypes.includes(file.type)) {
            showNotification('danger', '❌ Tipe file tidak sesuai. Gunakan JPG, PNG, GIF, atau WebP');
            return;
        }
        
        if (file.size > maxSize) {
            showNotification('danger', '❌ Ukuran file terlalu besar. Maksimal 5MB');
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
                    // Gunakan URL relatif dari admin folder
                    const imageUrl = response.url; // '../uploads/posts/filename'
                    
                    console.log('Inserting image with URL:', imageUrl);
                    console.log('DB Path:', response.db_path);
                    
                    // Insert image ke Summernote
                    try {
                        $('#summernote').summernote('insertImage', imageUrl, function($image) {
                            if ($image && $image.length) {
                                $image.css('max-width', '100%');
                                $image.css('height', 'auto');
                                $image.addClass('img-fluid');
                                $image.attr('alt', file.name);
                                $image.attr('title', file.name);
                                $image.attr('data-file-name', response.file_name);
                                
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
        console.log('Video iframe inserted (edit-post)');
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
        
        console.log('processAndConvertVideos called (edit-post)');
        
        // 1. First, handle any text links/URLs that are just sitting in the editor
        editor.contents().each(function() {
            if (this.nodeType === Node.TEXT_NODE) {
                let text = this.nodeValue;
                const youtubeMatch = text.match(/(https?:\/\/(?:www\.)?(youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11}))/);
                const vimeoMatch = text.match(/(https?:\/\/(?:www\.)?vimeo\.com\/(\d+))/);
                
                if (youtubeMatch || vimeoMatch) {
                    console.log('Found video URL in text:', youtubeMatch ? youtubeMatch[0] : vimeoMatch[0]);
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

    // Process video embeds in editor
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

    // Function untuk fix image paths - convert uploads/posts/ ke ../uploads/posts/ untuk Summernote
    function fixImagePaths() {
        const editor = $('#summernote').next('.note-editor').find('.note-editable');
        
        editor.find('img').each(function() {
            let src = $(this).attr('src');
            
            // If src starts with uploads/posts/, convert to ../uploads/posts/
            if (src && src.indexOf('uploads/posts/') === 0) {
                src = '../' + src;
                $(this).attr('src', src);
                console.log('Fixed image path:', src);
            }
            
            // Ensure image is visible and has correct attributes
            $(this).css('display', 'block');
            if (!$(this).attr('alt')) {
                $(this).attr('alt', 'Uploaded image');
            }
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

    // Observe editor for newly inserted iframes and process them immediately
    function observeEditorForIframes() {
        const editorNode = document.querySelector('#summernote').nextElementSibling?.querySelector('.note-editable');
        if (!editorNode) return;

        const observer = new MutationObserver(function(mutations) {
            for (const m of mutations) {
                if (m.addedNodes && m.addedNodes.length) {
                    m.addedNodes.forEach(function(node) {
                        if (node.nodeName === 'IFRAME') {
                            processAndConvertVideos();
                            processVideoEmbeds();
                        } else if (node.querySelector) {
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

    // Intercept link creation untuk video URL
    document.addEventListener('summernote.dialog.shown', function() {
        // Handle video URL input
        const videoUrlInput = document.querySelector('.note-dialog-body [data-toggle="tab"][href="#sn-video-url"]');
        if (videoUrlInput) {
            console.log('Video URL dialog detected');
        }
    });

    // Function untuk adjust editor height berdasarkan konten
    function adjustEditorHeight() {
        const $editable = $('.note-editable');
        const $noteEditor = $('.note-editor');
        
        if ($editable.length === 0) {
            console.log('Note-editable not found yet');
            return;
        }
        
        // Get the element
        const editableEl = $editable[0];
        
        // Force browser reflow
        editableEl.style.display = 'block';
        const _ = editableEl.offsetHeight;
        
        // Get real scrollHeight (tidak bergantung pada current height)
        let realHeight = editableEl.scrollHeight;
        
        // If content is very large, make sure we're capturing all of it
        if (realHeight < 600) {
            // Maybe content hasn't fully rendered, try alternative method
            realHeight = Math.max(realHeight, editableEl.clientHeight + 50);
        }
        
        const minHeight = 600;
        const finalHeight = Math.max(realHeight + 40, minHeight);
        
        // Apply without jQuery to have better control
        editableEl.style.cssText += 'height: ' + finalHeight + 'px !important; max-height: none !important; overflow: visible !important;';
        
        // Update note-editor as well
        $noteEditor.css({
            'height': 'auto !important',
            'min-height': (finalHeight + 100) + 'px !important',
            'max-height': 'none !important'
        });
        
        console.log('Editor height set to:', finalHeight, 'px (realHeight was:', realHeight, 'px)');
    }

    // Auto-adjust height dengan multiple attempts untuk memastikan content fully loaded
    let adjustAttempts = 0;
    function autoAdjustWithRetry() {
        adjustAttempts++;
        
        const $editor = $('#summernote');
        const $noteEditor = $editor.next('.note-editor');
        const $noteEditable = $noteEditor.find('.note-editable');
        const $editingArea = $noteEditor.find('.note-editing-area');
        
        if ($noteEditable.length === 0) {
            if (adjustAttempts < 10) {
                setTimeout(autoAdjustWithRetry, 150);
            }
            return;
        }
        
        try {
            // FORCE REFLOW MULTIPLE TIMES
            const el = $noteEditable[0];
            el.offsetHeight; // trigger reflow
            
            // Get all content heights
            const scrollHeight = el.scrollHeight;
            const clientHeight = el.clientHeight;
            const offsetHeight = el.offsetHeight;
            
            // Also check innerHTML length as indicator
            const contentLength = el.innerHTML.length;
            
            // Calculate real content height
            let realHeight = Math.max(scrollHeight, clientHeight, offsetHeight);
            
            // If we have lots of content but small height reported, something is wrong
            if (contentLength > 5000 && realHeight < 1000) {
                realHeight = Math.max(realHeight, contentLength / 5); // rough estimate
            }
            
            const minHeight = 700;
            const bufferHeight = 100;
            const targetHeight = Math.max(realHeight + bufferHeight, minHeight);
            
            // FORCE SET HEIGHT on element and parent
            el.style.height = targetHeight + 'px';
            el.style.minHeight = minHeight + 'px';
            el.style.maxHeight = 'none';
            el.style.overflow = 'visible';
            
            if ($editingArea.length > 0) {
                $editingArea[0].style.height = 'auto';
                $editingArea[0].style.maxHeight = 'none';
                $editingArea[0].style.overflow = 'visible';
            }
            
            // Also set on parent containers
            $noteEditor.css({
                'height': 'auto',
                'min-height': (targetHeight + 150) + 'px',
                'max-height': 'none'
            });
            
            console.log(`Adjust attempt ${adjustAttempts}: scrollH=${scrollHeight}, clientH=${clientHeight}, target=${targetHeight}, contentLen=${contentLength}`);
            
            // Keep retrying to ensure all content is visible
            if (adjustAttempts < 15) {
                setTimeout(autoAdjustWithRetry, 200 + (adjustAttempts * 50));
            }
        } catch (err) {
            console.error('Error in autoAdjustWithRetry:', err);
        }
    }

    // Utility function untuk inspect konten yang di-load
    function inspectEditorContent() {
        const $editable = $('.note-editable');
        const $textarea = $('#summernote');
        
        if ($editable.length === 0) {
            console.warn('Note-editable not found');
            return;
        }
        
        const editableContent = $editable.html();
        const textareaContent = $textarea.val();
        const dataAttrContent = $textarea.attr('data-initial-content');
        const currentCode = $textarea.summernote('code');
        
        console.group('📋 EDITOR CONTENT INSPECTION');
        console.log('Textarea value length:', textareaContent.length);
        console.log('Data attribute length:', dataAttrContent ? JSON.parse(dataAttrContent).length : 'N/A');
        console.log('Editable HTML length:', editableContent.length);
        console.log('Current Summernote code length:', currentCode.length);
        console.log('First 300 chars of editable content:', editableContent.substring(0, 300));
        console.log('Last 300 chars of editable content:', editableContent.substring(Math.max(0, editableContent.length - 300)));
        console.groupEnd();
        
        // Alert if content seems incomplete
        if (editableContent.length < textareaContent.length / 2) {
            console.warn('⚠️ WARNING: Editable content seems incomplete!');
            console.warn('  Editable length:', editableContent.length, 'Textarea length:', textareaContent.length);
            
            // Try to re-load content from data attribute
            const dataContent = $textarea.attr('data-initial-content');
            if (dataContent) {
                try {
                    const fullContent = JSON.parse(dataContent);
                    console.log('🔄 Attempting to reload content from data attribute...');
                    $textarea.summernote('code', fullContent);
                    
                    // Trigger height adjustment
                    setTimeout(() => {
                        adjustEditorHeight();
                        autoAdjustWithRetry();
                    }, 300);
                } catch (e) {
                    console.error('Error reloading content:', e);
                }
            }
        } else {
            console.log('✅ Content appears complete');
        }
    }
    
    // Function untuk force load content dari data attribute
    function forceLoadContentFromDataAttr() {
        const $textarea = $('#summernote');
        const dataContent = $textarea.attr('data-initial-content');
        
        if (!dataContent) {
            console.warn('No data-initial-content attribute found');
            return;
        }
        
        try {
            const content = JSON.parse(dataContent);
            console.log('📥 Force loading content from data attribute, length:', content.length);
            
            // Multiple attempts to ensure it sticks
            for (let i = 0; i < 5; i++) {
                setTimeout(() => {
                    $textarea.summernote('code', content);
                    console.log(`Force load attempt ${i + 1}`);
                }, 50 + (i * 150));
            }
            
            // After loading, trigger height adjustment
            setTimeout(() => {
                adjustEditorHeight();
                autoAdjustWithRetry();
                inspectEditorContent();
            }, 500);
            
        } catch (e) {
            console.error('Error force loading content:', e);
        }
    }
    
    // Run inspection after editor fully loaded
    setTimeout(() => {
        inspectEditorContent();
    }, 2500);
    
    // Run force load if content seems incomplete
    setTimeout(() => {
        const $editable = $('.note-editable');
        if ($editable.length > 0) {
            const editableLen = $editable.html().length;
            const textareaLen = $('#summernote').val().length;
            if (editableLen < textareaLen / 2) {
                console.log('⚠️ Content incomplete detected, forcing reload...');
                forceLoadContentFromDataAttr();
            }
        }
    }, 3000);
    setTimeout(autoAdjustWithRetry, 100);
    
    // CONTINUOUS MONITORING - Ensure content always visible
    let lastContentLength = 0;
    setInterval(function() {
        const $editable = $('.note-editable');
        if ($editable.length === 0) return;
        
        const contentLength = $editable[0].innerHTML.length;
        const scrollHeight = $editable[0].scrollHeight;
        const currentHeight = $editable[0].offsetHeight;
        
        // If content grew or height seems wrong, readjust
        if (contentLength !== lastContentLength || scrollHeight > (currentHeight + 50)) {
            autoAdjustWithRetry();
            lastContentLength = contentLength;
        }
    }, 500);
    
    // Also adjust when user types/changes content
    $(document).on('summernote.change', function() {
        setTimeout(() => {
            adjustEditorHeight();
            autoAdjustWithRetry();
        }, 100);
    });
    
    </script>
</body>
</html>