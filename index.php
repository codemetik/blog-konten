<?php
session_start();
require_once 'config/db.php';
require_once 'config/helpers.php';

// Pagination setup
$posts_per_page = 8;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $posts_per_page;

// Build query based on filters
$where_clause = "WHERE status = 'published'";
$params = [];

// Search filter
$search = '';
if (!empty($_GET['search'])) {
    $search = escape_string($_GET['search']);
    $where_clause .= " AND (title LIKE '%$search%' OR excerpt LIKE '%$search%' OR content LIKE '%$search%')";
}

// Category filter
$selected_category = '';
if (!empty($_GET['category'])) {
    $selected_category = escape_string($_GET['category']);
    $where_clause .= " AND category = '$selected_category'";
}

// Get total posts for pagination
$count_query = "SELECT COUNT(*) as total FROM posts $where_clause";
$count_result = mysqli_query($conn, $count_query);
$total_posts = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_posts / $posts_per_page);

// Get posts with limit
$query = "SELECT id, title, slug, excerpt, featured_image, category, author, created_at, views 
          FROM posts $where_clause 
          ORDER BY created_at DESC 
          LIMIT $posts_per_page OFFSET $offset";
$result = mysqli_query($conn, $query);
$posts = [];
while ($row = mysqli_fetch_assoc($result)) {
    $posts[] = $row;
}

// Get categories for sidebar
$categories_query = "SELECT category, COUNT(*) as count FROM posts WHERE status = 'published' GROUP BY category ORDER BY category ASC";
$categories_result = mysqli_query($conn, $categories_query);
$categories = [];
while ($row = mysqli_fetch_assoc($categories_result)) {
    $categories[] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Blog SMK Satya Praja 2 - Berbagi informasi, prestasi, dan berita sekolah">
    <meta property="og:title" content="Blog SMK Satya Praja 2">
    <meta property="og:description" content="Blog resmi SMK Satya Praja 2">
    
    <title>Blog - SMK Satya Praja 2</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><defs><linearGradient id='grad1' x1='0%' y1='0%' x2='100%' y2='100%'><stop offset='0%' style='stop-color:%236366f1;stop-opacity:1' /><stop offset='100%' style='stop-color:%238b5cf6;stop-opacity:1' /></linearGradient></defs><rect width='100' height='100' fill='url(%23grad1)'/><path d='M25 20h50v60H25z' fill='white' opacity='0.9'/><line x1='35' y1='30' x2='65' y2='30' stroke='white' stroke-width='3' opacity='0.7'/><line x1='35' y1='42' x2='65' y2='42' stroke='white' stroke-width='2' opacity='0.6'/><line x1='35' y1='52' x2='65' y2='52' stroke='white' stroke-width='2' opacity='0.6'/><circle cx='50' cy='75' r='8' fill='%236366f1'/></svg>" />
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #6366f1;
            --secondary: #8b5cf6;
            --light: #f8f9fa;
            --dark: #1a1a1a;
            --turquoise: #06b6d4;
            --text: #333;
            --border: #e0e0e0;
        }
        
        body {
            background-color: #f5f5f5;
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
        }
        
        /* Header/Navigation */
        .navbar {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-size: 1.3rem;
            font-weight: 700;
            color: white !important;
        }
        
        .navbar-brand i {
            margin-right: 8px;
            color: #FFD700;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }
        
        .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        
        .nav-link:hover {
            color: white !important;
        }
        
        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 15%, #f093fb 30%, #4facfe 45%, #00f2fe 60%, #43e97b 75%, #38f9d7 90%, #667eea 100%);
            background-size: 300% 300%;
            color: white;
            padding: 4.5rem 0;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(99, 102, 241, 0.3);
            animation: gradientShift 8s ease infinite;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        /* Decorative background pattern dengan shapes */
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 10% 20%, rgba(231, 39, 39, 0.25) 0%, transparent 50%),
                radial-gradient(circle at 90% 80%, rgba(255, 255, 255, 0.2) 0%, transparent 50%),
                radial-gradient(circle at 50% 100%, rgba(255, 255, 255, 0.15) 0%, transparent 50%),
                radial-gradient(ellipse at 50% 0%, rgba(255, 255, 255, 0.1) 0%, transparent 60%),
                radial-gradient(circle at 20% 80%, rgba(255, 255, 255, 0.12) 0%, transparent 45%);
            pointer-events: none;
            animation: backgroundShift 15s ease infinite;
        }
        
        /* Animated geometric shapes */
        .hero-section::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                url('data:image/svg+xml,<svg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" width="60" height="60" patternUnits="userSpaceOnUse"><path d="M 60 0 L 0 0 0 60" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="1"/><circle cx="30" cy="30" r="5" fill="rgba(255,255,255,0.03)"/></pattern></defs><rect width="60" height="60" fill="url(%23grid)"/></svg>');
            pointer-events: none;
            opacity: 0.8;
        }
        
        @keyframes backgroundShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        
        .hero-section .container {
            position: relative;
            z-index: 2;
            text-align: center;
        }
        
        .hero-section h1 {
            font-size: 2.8rem;
            font-weight: 800;
            margin-bottom: 0.8rem;
            text-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
            letter-spacing: -0.5px;
            animation: slideDown 0.8s ease-out;
        }
        
        .hero-section p {
            font-size: 1.15rem;
            opacity: 0.98;
            text-shadow: 0 3px 12px rgba(0, 0, 0, 0.15);
            font-weight: 500;
            letter-spacing: 0.3px;
            animation: slideUp 0.8s ease-out 0.1s backwards;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Bottom accent line */
        .hero-section {
            border-bottom: 3px solid rgba(255, 255, 255, 0.15);
        }
        
        @media (max-width: 576px) {
            .hero-section {
                padding: 3rem 0;
            }
            
            .hero-section h1 {
                font-size: 1.8rem;
            }
            
            .hero-section p {
                font-size: 1rem;
            }
        }
        
        /* Main Content Container */
        .container {
            max-width: 1200px;
        }
        
        /* Search Section */
        .search-section {
            margin-bottom: 2rem;
        }
        
        .search-wrapper {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .search-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        /* Filter Row */
        .filter-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
            align-items: flex-end;
        }
        
        @media (max-width: 992px) {
            .filter-row {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        @media (max-width: 576px) {
            .filter-row {
                grid-template-columns: 1fr;
            }
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .filter-group label {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .filter-group label i {
            color: var(--primary);
            margin-right: 6px;
            font-size: 1.05rem;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 0.8rem 1rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            font-family: inherit;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .search-buttons {
            display: flex;
            gap: 0.8rem;
            justify-content: flex-start;
        }
        
        .btn-search {
            padding: 0.8rem 1.5rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.95rem;
        }
        
        .btn-search i {
            font-size: 1.1rem;
        }
        
        .btn-search:hover {
            background: var(--secondary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }
        
        .btn-reset {
            padding: 0.8rem 1.5rem;
            background: #f0f0f0;
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.95rem;
        }
        
        .btn-reset:hover {
            background: var(--light);
            border-color: var(--text);
        }
        
        /* Active Filter Badge */
        .active-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 0.7rem;
            margin-top: 1rem;
        }
        
        .filter-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 0.4rem 0.8rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .filter-badge button {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 0;
            margin-left: 4px;
            font-size: 1rem;
            line-height: 1;
        }
        
        .filter-badge button:hover {
            opacity: 0.8;
        }
        
        /* Content Area */
        .content-wrapper {
            display: grid;
            grid-template-columns: 1fr 280px;
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        @media (max-width: 768px) {
            .content-wrapper {
                grid-template-columns: 1fr;
            }
        }
        
        /* Posts Grid */
        .posts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .posts-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }
        
        @media (max-width: 480px) {
            .posts-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Post Card */
        .post-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
            cursor: pointer;
        }
        
        .post-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.12);
        }
        
        .post-card-image {
            width: 100%;
            height: 100px;
            object-fit: cover;
            background: linear-gradient(135deg, #e0e0e0 0%, #f5f5f5 100%);
        }
        
        .post-card-body {
            padding: 1.2rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .post-category {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 0.8rem;
            width: fit-content;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .post-category::before {
            content: '▸';
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .post-title {
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--dark);
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .post-excerpt {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 1rem;
            flex-grow: 1;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.5;
        }
        
        .post-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: #999;
            border-top: 1px solid var(--border);
            padding-top: 0.8rem;
        }
        
        .post-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .post-meta i {
            font-size: 0.75rem;
            color: var(--primary);
        }
        
        /* Sidebar */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .sidebar-card {
            background: white;
            border-radius: 10px;
            padding: 1.2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .sidebar-card h5 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .sidebar-card h5 i {
            color: var(--primary);
            font-size: 1.2rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .category-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .category-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.6rem 0.8rem;
            border-radius: 6px;
            font-size: 0.9rem;
            text-decoration: none;
            color: var(--text);
            transition: background 0.3s ease;
        }
        
        .category-item:hover {
            background: var(--light);
            color: var(--primary);
        }
        
        .category-item.active {
            background: var(--primary);
            color: white;
            font-weight: 600;
        }
        
        .category-count {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .category-item.active .category-count {
            background: rgba(255,255,255,0.3);
            color: white;
        }
        
        /* Pagination */
        .pagination-wrapper {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin: 2rem 0;
            flex-wrap: wrap;
        }
        
        .pagination-wrapper a,
        .pagination-wrapper span {
            padding: 0.6rem 0.9rem;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .pagination-wrapper a {
            background: white;
            color: var(--text);
            border: 1px solid var(--border);
        }
        
        .pagination-wrapper a:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .pagination-wrapper .active {
            background: var(--primary);
            color: white;
            border: 1px solid var(--primary);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            background: white;
            border-radius: 10px;
            grid-column: 1 / -1;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--border);
            margin-bottom: 1rem;
            opacity: 0.6;
        }
        
        .empty-state h3 {
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .empty-state p {
            color: #999;
            margin-bottom: 1rem;
        }
        
        /* Footer */
        .footer {
            background: var(--dark);
            color: white;
            padding: 2rem 0;
            margin-top: 3rem;
        }
        
        .footer h6 {
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .footer p,
        .footer a {
            font-size: 0.9rem;
            color: rgba(255,255,255,0.7);
        }
        
        .footer a:hover {
            color: white;
            text-decoration: none;
        }
        
        .footer hr {
            background: rgba(255,255,255,0.1);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-book-fill"></i> BlogKonten
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#categories">Kategori</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin/login.php">Admin</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <h1 class="mb-2" style="display: flex; align-items: center; justify-content: center; gap: 15px; flex-wrap: wrap;">
                <i class="bi bi-book-fill" style="background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-size: 2.5rem; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));"></i>
                Blog Konten
            </h1>
            <p>Informasi, prestasi, materi, dan berita terkini dari Blog Konten</p>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container bs-yellow">
        <!-- Search Section -->
        <div class="search-section">
            <div class="search-wrapper">
                <form method="GET" action="index.php" class="search-form">
                    <div class="filter-row">
                        <!-- Search Input -->
                        <div class="filter-group">
                            <label for="search">
                                <i class="bi bi-search"></i> Cari Artikel
                            </label>
                            <input type="text" id="search" name="search" placeholder="Ketik kata kunci..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <!-- Category Select -->
                        <div class="filter-group">
                            <label for="category">
                                <i class="bi bi-tag"></i> Kategori
                            </label>
                            <select id="category" name="category">
                                <option value="">-- Semua Kategori --</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                        <?php echo ($selected_category === $cat['category']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['category']); ?> (<?php echo $cat['count']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="search-buttons">
                            <button type="submit" class="btn-search">
                                <i class="bi bi-search"></i> Cari
                            </button>
                            <a href="index.php" class="btn-reset">
                                <i class="bi bi-arrow-clockwise"></i> Reset
                            </a>
                        </div>
                    </div>
                    
                    <!-- Active Filters Display -->
                    <?php if (!empty($search) || !empty($selected_category)): ?>
                    <div class="active-filters">
                        <span style="color: #666; font-size: 0.9rem; margin-right: 0.5rem;">Filter aktif:</span>
                        <?php if (!empty($search)): ?>
                        <div class="filter-badge">
                            <i class="bi bi-search"></i> "<?php echo htmlspecialchars($search); ?>"
                            <button type="button" onclick="document.getElementById('search').value=''; this.form.submit();">
                                ×
                            </button>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($selected_category)): ?>
                        <div class="filter-badge">
                            <i class="bi bi-tag"></i> <?php echo htmlspecialchars($selected_category); ?>
                            <button type="button" onclick="document.getElementById('category').value=''; this.form.submit();">
                                ×
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </form>
                
                <!-- Search Results Info -->
                <?php if (!empty($search) || !empty($selected_category)): ?>
                <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                    <p style="margin: 0; font-size: 0.9rem; color: #666;">
                        <strong><?php echo $total_posts; ?></strong> 
                        artikel ditemukan
                        <?php if (!empty($search)): ?>
                            untuk "<strong><?php echo htmlspecialchars($search); ?></strong>"
                        <?php endif; ?>
                        <?php if (!empty($selected_category)): ?>
                            di kategori "<strong><?php echo htmlspecialchars($selected_category); ?></strong>"
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Posts and Sidebar -->
        <div class="content-wrapper">
            <!-- Posts Grid -->
            <div>
                <?php if (!empty($posts)): ?>
                <div class="posts-grid">
                    <?php foreach ($posts as $post): ?>
                    <a href="<?php echo htmlspecialchars($post['slug']); ?>" class="post-card" style="text-decoration: none; color: inherit;">
                        <!-- Post Image -->
                            <?php if (!empty($post['featured_image'])): ?>
                            <?php $featured_path = normalize_image_path($post['featured_image']); ?>
                            <?php $base_url = get_base_url(); ?>
                            <img src="<?php echo ($base_url ? $base_url . '/' : '/') . htmlspecialchars($featured_path); ?>" 
                             alt="<?php echo htmlspecialchars($post['title']); ?>"
                             class="post-card-image"
                             loading="lazy">
                        <?php else: ?>
                        <div class="post-card-image d-flex align-items-center justify-content-center" style="background: linear-gradient(135deg, #e0e0e0 0%, #f5f5f5 100%);">
                            <i class="bi bi-image" style="font-size: 3rem; color: #999;"></i>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Post Content -->
                        <div class="post-card-body">
                            <span class="post-category"><?php echo htmlspecialchars($post['category']); ?></span>
                            <h3 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h3>
                            <p class="post-excerpt"><?php echo htmlspecialchars($post['excerpt']); ?></p>
                            
                            <!-- Post Meta -->
                            <div class="post-meta">
                                <span>
                                    <i class="bi bi-calendar-event"></i>
                                    <?php echo format_date_short($post['created_at']); ?>
                                </span>
                                <span>
                                    <i class="bi bi-eye"></i>
                                    <?php echo number_format($post['views']); ?>
                                </span>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination-wrapper">
                    <?php if ($current_page > 1): ?>
                    <a href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($selected_category) ? '&category=' . urlencode($selected_category) : ''; ?>">
                        <i class="bi bi-chevron-double-left"></i> Awal
                    </a>
                    <a href="?page=<?php echo $current_page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($selected_category) ? '&category=' . urlencode($selected_category) : ''; ?>">
                        <i class="bi bi-chevron-left"></i> Sebelumnya
                    </a>
                    <?php endif; ?>

                    <?php
                    // Show page numbers
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    if ($start_page > 1) {
                        echo '<span>...</span>';
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++) {
                        if ($i == $current_page) {
                            echo '<span class="active">' . $i . '</span>';
                        } else {
                            echo '<a href="?page=' . $i . (!empty($search) ? '&search=' . urlencode($search) : '') . (!empty($selected_category) ? '&category=' . urlencode($selected_category) : '') . '">' . $i . '</a>';
                        }
                    }
                    
                    if ($end_page < $total_pages) {
                        echo '<span>...</span>';
                    }
                    ?>

                    <?php if ($current_page < $total_pages): ?>
                    <a href="?page=<?php echo $current_page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($selected_category) ? '&category=' . urlencode($selected_category) : ''; ?>">
                        Berikutnya <i class="bi bi-chevron-right"></i>
                    </a>
                    <a href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($selected_category) ? '&category=' . urlencode($selected_category) : ''; ?>">
                        Akhir <i class="bi bi-chevron-double-right"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <h3>Artikel tidak ditemukan</h3>
                    <p>
                        <?php if (!empty($search) || !empty($selected_category)): ?>
                            Tidak ada artikel yang sesuai dengan filter Anda.
                        <?php else: ?>
                            Belum ada artikel yang dipublikasikan.
                        <?php endif; ?>
                    </p>
                    <a href="index.php" class="btn-reset">
                        <i class="bi bi-arrow-left"></i> Kembali ke Semua Artikel
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <aside class="sidebar">
                <!-- Categories Card -->
                <div class="sidebar-card">
                    <h5 id="categories">
                        <i class="bi bi-tag"></i> Kategori Populer
                    </h5>
                    <div class="category-list">
                        <a href="index.php" class="category-item <?php echo (empty($selected_category) && empty($search)) ? 'active' : ''; ?>">
                            <span>Semua Artikel</span>
                            <span class="category-count"><?php echo $total_posts; ?></span>
                        </a>
                        <?php foreach ($categories as $cat): ?>
                        <a href="?category=<?php echo urlencode($cat['category']); ?>" 
                           class="category-item <?php echo ($selected_category === $cat['category']) ? 'active' : ''; ?>">
                            <span><?php echo htmlspecialchars($cat['category']); ?></span>
                            <span class="category-count"><?php echo $cat['count']; ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Stats Card -->
                <div class="sidebar-card">
                    <h5>
                        <i class="bi bi-graph-up"></i> Statistik
                    </h5>
                    <div style="font-size: 0.9rem; line-height: 2;">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total Artikel:</span>
                            <strong><?php echo $total_posts; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Total Kategori:</span>
                            <strong><?php echo count($categories); ?></strong>
                        </div>
                    </div>
                </div>

                <!-- Info Card -->
                <div class="sidebar-card" style="background: linear-gradient(135deg, var(--turquoise) 0%, var(--secondary) 100%); color: white;">
                    <h5 style="color: white; display: flex; align-items: center; gap: 8px;">
                        <i class="bi bi-info-circle" style="color: white; font-size: 1.3rem; opacity: 0.95;"></i> Info
                    </h5>
                    <p style="font-size: 0.9rem; margin: 0;">
                        Platform blog konten resmi Blog Konten untuk berbagi informasi dan prestasi.
                    </p>
                </div>
            </aside>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
                <div>
                    <h6>Blog Konten</h6>
                    <p style="margin: 0;">Platform blog resmi Blog Konten untuk berbagi informasi dan prestasi sekolah.</p>
                </div>
                <div>
                    <h6>Tautan Cepat</h6>
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="#categories">Kategori</a></li>
                        <li><a href="admin/login.php">Admin</a></li>
                    </ul>
                </div>
                <div>
                    <h6>Ikuti Kami</h6>
                    <div style="display: flex; gap: 10px;">
                        <a href="#" style="display: inline-flex; width: 40px; height: 40px; align-items: center; justify-content: center; background: rgba(255,255,255,0.1); border-radius: 50%; text-decoration: none; color: white;">
                            <i class="bi bi-facebook"></i>
                        </a>
                        <a href="#" style="display: inline-flex; width: 40px; height: 40px; align-items: center; justify-content: center; background: rgba(255,255,255,0.1); border-radius: 50%; text-decoration: none; color: white;">
                            <i class="bi bi-twitter"></i>
                        </a>
                        <a href="#" style="display: inline-flex; width: 40px; height: 40px; align-items: center; justify-content: center; background: rgba(255,255,255,0.1); border-radius: 50%; text-decoration: none; color: white;">
                            <i class="bi bi-instagram"></i>
                        </a>
                    </div>
                </div>
            </div>
            <hr>
            <div style="text-align: center;">
                <p style="margin: 0; font-size: 0.9rem;">&copy; 2025 Blog Konten. Semua hak dilindungi.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
</body>
</html>