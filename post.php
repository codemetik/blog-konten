<?php
session_start();
require_once 'config/db.php';
require_once 'config/helpers.php';

// Get post slug from URL (method GET melalui .htaccess)
$post_slug = isset($_GET['slug']) ? sanitize_slug($_GET['slug']) : '';

if (empty($post_slug)) {
    header('Location: index.php');
    exit;
}

// Get post data menggunakan slug (lebih aman dari ID numerik)
$slug_escaped = escape_string($post_slug);
$query = "SELECT id, title, slug, content, excerpt, category, featured_image, author, views, created_at FROM posts 
          WHERE slug = '$slug_escaped' AND status = 'published'";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) === 0) {
    header('Location: index.php');
    exit;
}

$post = mysqli_fetch_assoc($result);
$post_id = $post['id'];

// Update views
$update_query = "UPDATE posts SET views = views + 1 WHERE id = $post_id";
mysqli_query($conn, $update_query);

// Get related posts menggunakan slug
$related_query = "SELECT id, title, slug, excerpt, featured_image, created_at FROM posts 
                  WHERE category = '" . escape_string($post['category']) . "' 
                  AND id != $post_id 
                  AND status = 'published'
                  ORDER BY created_at DESC 
                  LIMIT 3";
$related_result = mysqli_query($conn, $related_query);
$related_posts = [];
while ($row = mysqli_fetch_assoc($related_result)) {
    $related_posts[] = $row;
}

// Process content
$content = $post['content'];
$content = str_replace('../uploads/posts/', 'uploads/posts/', $content);

// Process images
$content = preg_replace_callback(
    '/<img\s+([^>]*?)src=["\']([^"\']+)["\']([^>]*?)>/i',
    function($matches) {
        $before = $matches[1];
        $src = $matches[2];
        $after = $matches[3];
        
        $src = str_replace('../', '', $src);
        
        if (strpos($src, 'uploads') !== false && strpos($src, 'uploads/posts/') === false) {
            if (preg_match('/^[a-zA-Z0-9_.\-]+\.(jpg|jpeg|png|gif|webp)$/i', $src)) {
                $src = 'uploads/posts/' . $src;
            }
        }
        
        $class = 'content-img';
        if (preg_match('/class=["\']([^"\']*)["\']/', $after, $class_match)) {
            $after = str_replace('class="' . $class_match[1] . '"', 'class="' . $class_match[1] . ' ' . $class . '"', $after);
        } else {
            $after = 'class="' . $class . '" ' . $after;
        }
        
        return '<img ' . $before . 'src="' . $src . '"' . $after . '>';
    },
    $content
);

// Process iframes (video embeds) untuk wrapping dengan video-container
$content = preg_replace_callback(
    '/<iframe\s+([^>]*?)src=["\']([^"\']+)["\']([^>]*?)><\/iframe>/i',
    function($matches) {
        $before = $matches[1];
        $src = $matches[2];
        $after = $matches[3];
        
        // Check if src is YouTube or Vimeo or other video provider
        if (preg_match('/(youtube|youtu\.be|vimeo|dailymotion|wistia)/', $src)) {
            // Wrap with video-container for responsive display
            $iframe = '<iframe ' . $before . 'src="' . $src . '" allowfullscreen' . $after . '></iframe>';
            return '<div class="video-container">' . $iframe . '</div>';
        }
        
        return '<iframe ' . $before . 'src="' . $src . '"' . $after . '></iframe>';
    },
    $content
);

// Process any existing video-container divs with iframes
$content = preg_replace(
    '/<div class="video-container"><div class="video-container">/i',
    '<div class="video-container">',
    $content
);
$content = preg_replace(
    '/<\/div><\/div>\s*$/i',
    '</div>',
    $content
);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo htmlspecialchars($post['excerpt']); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($post['title']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($post['excerpt']); ?>">
    <?php if (!empty($post['featured_image']) && file_exists($post['featured_image'])): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($post['featured_image']); ?>">
    <?php endif; ?>
    
    <title><?php echo htmlspecialchars($post['title']); ?> - SMK Satya Praja 2</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><defs><linearGradient id='grad1' x1='0%' y1='0%' x2='100%' y2='100%'><stop offset='0%' style='stop-color:%236366f1;stop-opacity:1' /><stop offset='100%' style='stop-color:%238b5cf6;stop-opacity:1' /></linearGradient></defs><rect width='100' height='100' fill='url(%23grad1)'/><path d='M25 20h50v60H25z' fill='white' opacity='0.9'/><line x1='35' y1='30' x2='65' y2='30' stroke='white' stroke-width='3' opacity='0.7'/><line x1='35' y1='42' x2='65' y2='42' stroke='white' stroke-width='2' opacity='0.6'/><line x1='35' y1='52' x2='65' y2='52' stroke='white' stroke-width='2' opacity='0.6'/><circle cx='50' cy='75' r='8' fill='%236366f1'/></svg>" />
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
    
    <style>
        :root {
            --primary: #6366f1;
            --secondary: #8b5cf6;
            --light: #f8f9fa;
            --dark: #1a1a1a;
            --turquoise: #06b6d4;
            --text: #333;
            --border: #e0e0e0;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background-color: #f5f5f5;
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
        }
        
        /* Navigation */
        .navbar {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-size: 1.3rem;
            font-weight: 700;
            color: white !important;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        
        .nav-link:hover {
            color: white !important;
        }
        
        /* Main Content */
        .post-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        @media (max-width: 768px) {
            .post-container {
                padding: 1.5rem;
            }
        }
        
        /* Post Header */
        .post-header {
            border-bottom: 2px solid var(--border);
            padding-bottom: 2rem;
            margin-bottom: 2rem;
        }
        
        .post-header h1 {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 1rem;
            line-height: 1.3;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .post-header h1 i {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 2.5rem;
            filter: drop-shadow(0 2px 4px rgba(99, 102, 241, 0.3));
        }
        
        @media (max-width: 768px) {
            .post-header h1 {
                font-size: 1.8rem;
            }
        }
        
        .post-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            color: #666;
            font-size: 0.95rem;
        }
        
        .post-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .post-meta i {
            color: var(--primary);
            font-size: 1rem;
        }
        
        /* Featured Image Container */
        .featured-image-container {
            width: 100%;
            aspect-ratio: 16 / 9;
            overflow: hidden;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
            margin-bottom: 2rem;
            background: linear-gradient(135deg, #e0e0e0 0%, #f5f5f5 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .featured-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            display: block;
        }
        
        .featured-image-placeholder {
            font-size: 4rem;
            color: #ddd;
        }
        
        /* Post Excerpt */
        .post-excerpt {
            font-size: 1.15rem;
            color: #555;
            font-style: italic;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: var(--light);
            border-left: 4px solid var(--primary);
            border-radius: 6px;
        }
        
        /* Post Content */
        .post-content {
            font-size: 1.05rem;
            line-height: 1.8;
            color: var(--text);
            margin-bottom: 2rem;
            word-wrap: break-word;
            word-break: break-word;
            overflow-wrap: break-word;
        }

        .post-content * {
            max-width: 100%;
            box-sizing: border-box;
        }

        .post-content img,
        .content-img {
            max-width: 100%;
            height: auto;
            margin: 1.5rem 0;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: block;
        }

        .content-img {
            aspect-ratio: auto;
            object-fit: cover;
            object-position: center;
            width: 100%;
            max-height: 500px;
        }

        @media (min-width: 768px) {
            .content-img {
                max-height: 600px;
            }
        }

        @media (max-width: 576px) {
            .content-img {
                max-height: 350px;
            }
        }

        .post-content iframe {
            max-width: 100%;
            height: auto;
            margin: 1.5rem 0;
        }

        .post-content video {
            max-width: 100%;
            height: auto;
            margin: 1.5rem 0;
            border-radius: 10px;
            display: block;
        }

        .video-container,
        .embed-container {
            position: relative;
            width: 100%;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
            margin: 1.5rem 0;
            border-radius: 10px;
        }

        .video-container iframe,
        .embed-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: 0;
            border-radius: 10px;
        }

        /* ===== TEXT FORMATTING STYLING ===== */
        
        /* Paragraph - Normal Text */
        .post-content p {
            margin-bottom: 1.5rem;
            word-wrap: break-word;
            word-break: break-word;
            color: var(--dark);
            font-weight: 400;
            font-style: normal;
            text-decoration: none;
            line-height: 1.8;
        }

        /* Bold Text */
        .post-content strong,
        .post-content b {
            font-weight: 700;
            color: #1a1a1a;
        }

        /* Italic Text */
        .post-content em,
        .post-content i:not([class]) {
            font-style: italic;
            font-weight: 400;
            color: #555;
        }

        /* Underline Text */
        .post-content u,
        .post-content .underline,
        .post-content ins {
            text-decoration: underline;
            text-decoration-color: var(--primary);
            text-decoration-thickness: 2px;
            text-underline-offset: 3px;
            font-weight: 400;
        }

        /* Bold + Italic */
        .post-content strong em,
        .post-content strong i:not([class]),
        .post-content b em,
        .post-content b i:not([class]) {
            font-weight: 700;
            font-style: italic;
        }

        /* Bold + Underline */
        .post-content strong u,
        .post-content strong .underline,
        .post-content b u,
        .post-content b .underline {
            font-weight: 700;
            text-decoration: underline;
        }

        /* Italic + Underline */
        .post-content em u,
        .post-content i:not([class]) u,
        .post-content em .underline,
        .post-content i:not([class]) .underline {
            font-style: italic;
            text-decoration: underline;
        }

        /* Heading Styling */
        .post-content h1 {
            font-size: 2rem;
            font-weight: 800;
            color: var(--dark);
            margin-top: 2rem;
            margin-bottom: 1rem;
            line-height: 1.3;
            border-bottom: 3px solid var(--primary);
            padding-bottom: 0.75rem;
        }
        
        .post-content h2 {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--dark);
            border-bottom: 2px solid var(--primary);
            padding-bottom: 0.5rem;
            margin-top: 1.5rem;
            margin-bottom: 1rem;
            line-height: 1.4;
        }
        
        .post-content h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--dark);
            margin-top: 1.25rem;
            margin-bottom: 0.75rem;
            line-height: 1.4;
            border-left: 4px solid var(--primary);
            padding-left: 1rem;
        }

        .post-content h4 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            margin-top: 1rem;
            margin-bottom: 0.75rem;
        }

        .post-content h5 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .post-content h6 {
            font-size: 0.95rem;
            font-weight: 600;
            color: #555;
            margin-bottom: 0.5rem;
        }
        
        /* Lists */
        .post-content ul,
        .post-content ol {
            margin-bottom: 1.5rem;
            padding-left: 2.5rem;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .post-content ul {
            list-style-type: disc;
        }

        .post-content ol {
            list-style-type: decimal;
        }

        .post-content ul ul,
        .post-content ol ol {
            margin-top: 0.75rem;
            margin-bottom: 0;
            padding-left: 2rem;
        }
        
        .post-content li {
            margin-bottom: 0.7rem;
            line-height: 1.7;
            color: var(--dark);
            font-weight: 400;
        }

        .post-content li strong {
            font-weight: 700;
        }

        .post-content li em {
            font-style: italic;
        }
        
        /* Blockquote */
        .post-content blockquote {
            border-left: 4px solid var(--primary);
            padding: 1rem 1.5rem;
            background: linear-gradient(to right, rgba(99, 102, 241, 0.05), transparent);
            margin: 1.5rem 0;
            border-radius: 6px;
            font-style: italic;
            color: #555;
            font-weight: 400;
            font-size: 1.05rem;
        }

        .post-content blockquote p {
            margin-bottom: 0;
            color: #555;
        }

        .post-content blockquote cite {
            display: block;
            margin-top: 0.75rem;
            font-style: normal;
            font-weight: 600;
            color: var(--primary);
            font-size: 0.9rem;
        }
        
        /* Inline Code */
        .post-content code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
            font-family: 'Courier New', Courier, monospace;
            color: #d63384;
            word-break: break-word;
            word-wrap: break-word;
            font-size: 0.95em;
            font-weight: 500;
        }

        /* Code Block */
        .post-content pre {
            background: #f4f4f4;
            border: 1px solid #e0e0e0;
            padding: 1rem;
            border-radius: 8px;
            overflow-x: auto;
            margin: 1.5rem 0;
            word-wrap: break-word;
            word-break: break-word;
            white-space: pre-wrap;
            border-left: 4px solid var(--primary);
        }

        .post-content pre code {
            background: none;
            border: none;
            padding: 0;
            color: #333;
            font-weight: 400;
            font-size: 0.875rem;
            line-height: 1.5;
        }

        /* Tables */
        .post-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            overflow: hidden;
            background: white;
        }

        .post-content table th {
            background: linear-gradient(to bottom, #f8f9fa, #f0f0f0);
            padding: 0.75rem 1rem;
            text-align: left;
            font-weight: 700;
            color: var(--dark);
            border: 1px solid #e0e0e0;
        }

        .post-content table td {
            padding: 0.75rem 1rem;
            border: 1px solid #e0e0e0;
            font-weight: 400;
        }

        .post-content table tr:hover {
            background: #f9f9f9;
        }

        /* Horizontal Line */
        .post-content hr {
            margin: 2rem 0;
            border: none;
            height: 2px;
            background: linear-gradient(to right, transparent, var(--border), transparent);
        }

        /* Mark/Highlight */
        .post-content mark,
        .post-content .mark {
            background-color: #fff3cd;
            padding: 2px 4px;
            border-radius: 3px;
            color: #333;
        }
        
        .share-section h5 {
            font-weight: 700;
            margin-bottom: 1.2rem;
        }
        
        .share-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
        }
        
        .share-btn {
            padding: 0.7rem 1.2rem;
            border: 2px solid;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .share-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        /* Sidebar */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .sidebar-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .sidebar-card h5 {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .sidebar-card i {
            color: var(--primary);
        }
        
        /* Related Posts */
        .related-item {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            border-radius: 8px;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
        }
        
        .related-item:hover {
            background: var(--light);
            transform: translateX(4px);
        }
        
        .related-thumbnail {
            width: 80px;
            height: 80px;
            aspect-ratio: 1 / 1;
            object-fit: cover;
            border-radius: 6px;
            flex-shrink: 0;
            background: var(--light);
        }
        
        .related-content h6 {
            font-weight: 700;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .related-date {
            font-size: 0.8rem;
            color: #999;
        }
        
        /* Categories List */
        .category-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .category-link {
            padding: 0.7rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            color: var(--text);
            transition: all 0.3s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .category-link:hover {
            background: var(--light);
            color: var(--primary);
        }
        
        .category-badge {
            background: var(--primary);
            color: white;
            padding: 0.3rem 0.7rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
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
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-book-fill"></i> Blog Konten
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
                        <a class="nav-link" href="index.php#categories">Kategori</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin/login.php">Admin</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container my-5">
        <div class="row">
            <!-- Post Content -->
            <div class="col-lg-8">
                <article class="post-container">
                    <!-- Post Header -->
                    <header class="post-header">
                        <h1>
                            <i class="bi bi-book-fill"></i>
                            <?php echo htmlspecialchars($post['title']); ?>
                        </h1>
                        
                        <div class="post-meta">
                            <span>
                                <i class="bi bi-calendar-event"></i>
                                <?php echo format_date_long($post['created_at']); ?>
                            </span>
                            <span>
                                <i class="bi bi-person"></i>
                                <?php echo htmlspecialchars($post['author']); ?>
                            </span>
                            <span>
                                <i class="bi bi-tag"></i>
                                <?php echo htmlspecialchars($post['category']); ?>
                            </span>
                            <span>
                                <i class="bi bi-eye"></i>
                                <?php echo number_format($post['views']); ?> views
                            </span>
                            <span>
                                <i class="bi bi-clock"></i>
                                <?php echo get_reading_time($post['content']); ?> baca
                            </span>
                        </div>
                    </header>

                    <!-- Featured Image -->
                    <?php if (!empty($post['featured_image'])): ?>
                    <?php $featured_path = normalize_image_path($post['featured_image']); ?>
                    <?php $base_url = get_base_url(); ?>
                    <div class="featured-image-container">
                        <img src="<?php echo ($base_url ? $base_url . '/' : '/') . htmlspecialchars($featured_path); ?>" 
                             alt="<?php echo htmlspecialchars($post['title']); ?>"
                             class="featured-image"
                             loading="lazy">
                    </div>
                    <?php else: ?>
                    <div class="featured-image-container">
                        <i class="bi bi-image featured-image-placeholder"></i>
                    </div>
                    <?php endif; ?>

                    <!-- Excerpt -->
                    <div class="post-excerpt">
                        <?php echo htmlspecialchars($post['excerpt']); ?>
                    </div>

                    <!-- Post Content -->
                    <div class="post-content">
                        <?php echo $content; ?>
                    </div>
                    
                    <!-- Disqus -->
                    <div id="disqus_thread"></div>
                    <script>
                        /**
                        *  RECOMMENDED CONFIGURATION VARIABLES: EDIT AND UNCOMMENT THE SECTION BELOW TO INSERT DYNAMIC VALUES FROM YOUR PLATFORM OR CMS.
                        *  LEARN WHY DEFINING THESE VARIABLES IS IMPORTANT: https://disqus.com/admin/universalcode/#configuration-variables    */
                        /*
                        var disqus_config = function () {
                        this.page.url = PAGE_URL;  // Replace PAGE_URL with your page's canonical URL variable
                        this.page.identifier = PAGE_IDENTIFIER; // Replace PAGE_IDENTIFIER with your page's unique identifier variable
                        };
                        */
                        (function() { // DON'T EDIT BELOW THIS LINE
                        var d = document, s = d.createElement('script');
                        s.src = 'https://pplg.disqus.com/embed.js';
                        s.setAttribute('data-timestamp', +new Date());
                        (d.head || d.body).appendChild(s);
                        })();
                    </script>
                    <noscript>Please enable JavaScript to view the <a href="https://disqus.com/?ref_noscript">comments powered by Disqus.</a></noscript>
                    <!-- /Disqus -->
                    <!-- Share Section -->
                    <section class="share-section">
                        <h5>📤 Bagikan Artikel Ini</h5>
                        <div class="share-buttons">
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" 
                               class="share-btn" style="border-color: #1877F2; color: #1877F2;" target="_blank" rel="noopener">
                                <i class="bi bi-facebook"></i> Facebook
                            </a>
                            <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>&text=<?php echo urlencode($post['title']); ?>" 
                               class="share-btn" style="border-color: #1DA1F2; color: #1DA1F2;" target="_blank" rel="noopener">
                                <i class="bi bi-twitter"></i> Twitter
                            </a>
                            <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" 
                               class="share-btn" style="border-color: #0A66C2; color: #0A66C2;" target="_blank" rel="noopener">
                                <i class="bi bi-linkedin"></i> LinkedIn
                            </a>
                            <a href="https://wa.me/?text=<?php echo urlencode($post['title'] . ' ' . 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" 
                               class="share-btn" style="border-color: #25D366; color: #25D366;" target="_blank" rel="noopener">
                                <i class="bi bi-whatsapp"></i> WhatsApp
                            </a>
                        </div>
                    </section>
                </article>
            </div>

            <!-- Sidebar -->
            <aside class="col-lg-4">
                <div class="sidebar">
                    <!-- Search Card -->
                    <div class="sidebar-card">
                        <h5>
                            <i class="bi bi-search"></i> Cari Artikel
                        </h5>
                        <form method="GET" action="index.php" class="d-flex gap-2">
                            <input type="text" class="form-control form-control-sm" placeholder="Cari..." 
                                   name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                            <button class="btn btn-sm btn-primary" type="submit">
                                <i class="bi bi-search"></i>
                            </button>
                        </form>
                    </div>

                    <!-- Related Posts Card -->
                    <?php if (!empty($related_posts)): ?>
                    <div class="sidebar-card">
                        <h5>
                            <i class="bi bi-lightning"></i> Artikel Terkait
                        </h5>
                        <div class="category-list">
                            <?php foreach ($related_posts as $related): ?>
                            <a href="<?php echo htmlspecialchars($related['slug']); ?>" class="related-item">
                                <?php
                                    $related_img_path = normalize_image_path($related['featured_image'] ?? '');
                                    $base_url = get_base_url();
                                ?>
                                <?php if (!empty($related_img_path)): ?>
                                    <img src="<?php echo ($base_url ? $base_url . '/' : '/') . htmlspecialchars($related_img_path); ?>"
                                         alt="<?php echo htmlspecialchars($related['title']); ?>"
                                         class="related-thumbnail"
                                         loading="lazy"
                                         onerror="this.style.display='none'; this.parentElement.querySelector('.related-content').style.marginLeft='0';">
                                <?php else: ?>
                                    <div class="related-thumbnail d-flex align-items-center justify-content-center">
                                        <i class="bi bi-image" style="color: #ddd; font-size: 1.5rem;"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="related-content">
                                    <h6><?php echo htmlspecialchars($related['title']); ?></h6>
                                    <span class="related-date">
                                        <i class="bi bi-calendar-event"></i>
                                        <?php echo format_date_short($related['created_at']); ?>
                                    </span>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Categories Card -->
                    <div class="sidebar-card">
                        <h5>
                            <i class="bi bi-tag"></i> Kategori
                        </h5>
                        <div class="category-list">
                            <a href="index.php" class="category-link">
                                <span>Semua Artikel</span>
                            </a>
                            <?php
                            $categories_query = "SELECT DISTINCT category FROM posts WHERE status = 'published' ORDER BY category ASC";
                            $categories_result = mysqli_query($conn, $categories_query);
                            while ($cat = mysqli_fetch_assoc($categories_result)) {
                                $count_query = "SELECT COUNT(*) as count FROM posts WHERE category = '" . escape_string($cat['category']) . "' AND status = 'published'";
                                $count_result = mysqli_query($conn, $count_query);
                                $count = mysqli_fetch_assoc($count_result)['count'];
                                ?>
                                <a href="index.php?category=<?php echo urlencode($cat['category']); ?>" class="category-link">
                                    <span><?php echo htmlspecialchars($cat['category']); ?></span>
                                    <span class="category-badge"><?php echo $count; ?></span>
                                </a>
                                <?php
                            }
                            ?>
                        </div>
                    </div>

                    <!-- About Card -->
                    <div class="sidebar-card" style="background: linear-gradient(135deg, var(--turquoise) 0%, var(--secondary) 100%); color: white;">
                        <h5 style="color: white;">
                            <i class="bi bi-info-circle"></i> Tentang Blog
                        </h5>
                        <p style="font-size: 0.9rem; margin: 0; line-height: 1.6;">
                            Platform blog konten resmi Blog Konten untuk berbagi informasi dan prestasi.
                        </p>
                    </div>
                </div>
            </aside>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
                <div>
                    <h6>Blog Konten</h6>
                    <p style="margin: 0;">Platform blog resmi Blog Konten untuk berbagi informasi dan prestasi.</p>
                </div>
                <div>
                    <h6>Navigasi</h6>
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="index.php#categories">Kategori</a></li>
                        <li><a href="admin/login.php">Admin</a></li>
                    </ul>
                </div>
                <div>
                    <h6>Ikuti Kami</h6>
                    <div style="display: flex; gap: 10px;">
                        <a href="#" style="display: inline-flex; width: 40px; height: 40px; align-items: center; justify-content: center; background: rgba(255,255,255,0.1); border-radius: 50%; text-decoration: none; color: white; transition: all 0.3s;">
                            <i class="bi bi-facebook"></i>
                        </a>
                        <a href="#" style="display: inline-flex; width: 40px; height: 40px; align-items: center; justify-content: center; background: rgba(255,255,255,0.1); border-radius: 50%; text-decoration: none; color: white; transition: all 0.3s;">
                            <i class="bi bi-twitter"></i>
                        </a>
                        <a href="#" style="display: inline-flex; width: 40px; height: 40px; align-items: center; justify-content: center; background: rgba(255,255,255,0.1); border-radius: 50%; text-decoration: none; color: white; transition: all 0.3s;">
                            <i class="bi bi-instagram"></i>
                        </a>
                    </div>
                </div>
            </div>
            <hr style="background: rgba(255,255,255,0.1);">
            <div style="text-align: center;">
                <p style="margin: 0; font-size: 0.9rem;">&copy; 2025 Blog Konten. Semua hak dilindungi.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>