<?php
// Jangan include db.php di sini jika sudah diinclude di file lain
// Cukup include jika belum ada koneksi

/**
 * Generate slug from title
 * Mengubah judul menjadi URL-friendly slug
 * Contoh: "Hari Guru Nasional" -> "hari-guru-nasional"
 * 
 * @param string $title - Title/judul yang akan diubah menjadi slug
 * @return string - URL-friendly slug
 */
function generate_slug($title) {
    // Convert to lowercase
    $slug = strtolower($title);
    
    // Replace spaces with hyphens
    $slug = preg_replace('/\s+/', '-', $slug);
    
    // Replace Indonesian characters
    $slug = str_replace(['á', 'à', 'ả', 'ã', 'ạ', 'ă', 'ắ', 'ằ', 'ẳ', 'ẵ', 'ặ', 'â', 'ấ', 'ầ', 'ẩ', 'ẫ', 'ậ'], 'a', $slug);
    $slug = str_replace(['é', 'è', 'ẻ', 'ẽ', 'ẹ', 'ê', 'ế', 'ề', 'ể', 'ễ', 'ệ'], 'e', $slug);
    $slug = str_replace(['í', 'ì', 'ỉ', 'ĩ', 'ị'], 'i', $slug);
    $slug = str_replace(['ó', 'ò', 'ỏ', 'õ', 'ọ', 'ô', 'ố', 'ồ', 'ổ', 'ỗ', 'ộ', 'ơ', 'ớ', 'ờ', 'ở', 'ỡ', 'ợ'], 'o', $slug);
    $slug = str_replace(['ú', 'ù', 'ủ', 'ũ', 'ụ', 'ư', 'ứ', 'ừ', 'ử', 'ữ', 'ự'], 'u', $slug);
    $slug = str_replace(['ý', 'ỳ', 'ỷ', 'ỹ', 'ỵ'], 'y', $slug);
    $slug = str_replace('đ', 'd', $slug);
    
    // Remove special characters except hyphens
    $slug = preg_replace('/[^a-z0-9\-_]/', '', $slug);
    
    // Remove consecutive hyphens
    $slug = preg_replace('/-+/', '-', $slug);
    
    // Trim hyphens from start and end
    $slug = trim($slug, '-');
    
    return $slug;
}

/**
 * Sanitize slug untuk keamanan
 * 
 * @param string $slug - Slug yang akan disanitasi
 * @return string - Slug yang sudah aman
 */
function sanitize_slug($slug) {
    return preg_replace('/[^a-z0-9\-_]/', '', strtolower($slug));
}

/**
 * Process content images - normalize image URLs in content
 * @param string $content - HTML content dengan image tags
 * @param bool $is_admin - apakah dipanggil dari admin (true) atau frontend (false)
 * @return string - content dengan URL gambar yang sudah dinormalisasi
 */
function process_content_images($content, $is_admin = false) {
    if (empty($content)) {
        return $content;
    }
    
    // Jika dari admin, convert relative path dari admin folder ke root path
    if ($is_admin) {
        // Pattern: ../uploads/posts/filename
        // Convert ke: uploads/posts/filename
        $content = preg_replace_callback(
            '/src=["\'](\.\.[\/\\\\])?uploads[\/\\\\]posts[\/\\\\]([^"\']+)["\']/' ,
            function($matches) {
                $filename = $matches[2];
                return 'src="uploads/posts/' . $filename . '"';
            },
            $content
        );
    } else {
        // Jika dari frontend (post.php), pastikan URL sudah benar
        // Tidak perlu diubah jika sudah dalam format uploads/posts/filename
    }
    
    return $content;
}

/**
 * Show alert message
 */
function show_message($type, $message) {
    $icons = [
        'success' => '✅',
        'danger' => '❌',
        'warning' => '⚠️',
        'info' => 'ℹ️'
    ];
    
    $icon = $icons[$type] ?? '📢';
    
    return '
    <div class="alert alert-' . htmlspecialchars($type) . ' alert-dismissible fade show" role="alert">
        ' . $icon . ' ' . $message . '
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    ';
}

/**
 * Normalize featured image path
 * Handle various path formats and ensure correct file path
 * 
 * @param string $image_path - Path from database
 * @return string - Corrected image path
 */
function normalize_image_path($image_path) {
    if (empty($image_path)) {
        return '';
    }
    
    // Remove leading ../ if present
    $path = $image_path;
    while (strpos($path, '../') === 0) {
        $path = substr($path, 3);
    }
    
    // Ensure path starts with uploads/
    if (strpos($path, 'uploads/') !== 0) {
        $path = 'uploads/' . ltrim($path, '/');
    }
    
    // Check if file exists with this path
    if (file_exists($path)) {
        return $path;
    }
    
    // If not found, try uploads/featured/
    if (strpos($path, 'uploads/featured/') !== 0) {
        $filename = basename($path);
        $featured_path = 'uploads/featured/' . $filename;
        if (file_exists($featured_path)) {
            return $featured_path;
        }
    }
    
    // Try uploads/posts/ as fallback
    $filename = basename($path);
    $posts_path = 'uploads/posts/' . $filename;
    if (file_exists($posts_path)) {
        return $posts_path;
    }
    
    // Return original path if no file found (let browser handle 404)
    return $path;
}

/**
 * Get base URL path (useful when app is in subfolder)
 * Returns value like '/webai/blog-konten' or '' for root
 */
function get_base_url() {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = rtrim(dirname($script), '/\\');
    if ($dir === '/' || $dir === '.') {
        return '';
    }
    return $dir;
}

/**
 * Format date to short format (DD MMM YYYY)
 */
function format_date_short($date) {
    return date('d M Y', strtotime($date));
}

/**
 * Format date to long format (Indonesian)
 * Replaces deprecated strftime()
 */
function format_date_long($date) {
    $months = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    
    $days = [
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu',
        'Sunday' => 'Minggu'
    ];
    
    $date_obj = new DateTime($date);
    $day_name = $days[$date_obj->format('l')] ?? $date_obj->format('l');
    $day = $date_obj->format('d');
    $month = $months[(int)$date_obj->format('m')] ?? $date_obj->format('m');
    $year = $date_obj->format('Y');
    
    return "$day_name, $day $month $year";
}

/**
 * Format date Indonesian style (DD MMM YYYY)
 */
function format_date($date) {
    $months = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    
    $date_obj = new DateTime($date);
    $day = $date_obj->format('d');
    $month = $months[(int)$date_obj->format('m')] ?? $date_obj->format('m');
    $year = $date_obj->format('Y');
    
    return "$day $month $year";
}

/**
 * Get excerpt from content
 */
function get_excerpt($content, $length = 150) {
    // Remove HTML tags
    $text = strip_tags($content);
    
    // Trim and truncate
    $text = trim($text);
    if (strlen($text) > $length) {
        $text = substr($text, 0, $length) . '...';
    }
    
    return htmlspecialchars($text);
}

/**
 * Get reading time estimate
 */
function get_reading_time($content) {
    $word_count = str_word_count(strip_tags($content));
    $reading_time = ceil($word_count / 200); // Asumsi 200 words per minute
    
    return $reading_time . ' menit';
}

/**
 * Sanitize filename
 */
function sanitize_filename($filename) {
    return preg_replace('/[^A-Za-z0-9._-]/', '', $filename);
}

/**
 * Safe htmlspecialchars - handle null values
 */
function safe_html($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Extract all image URLs from HTML content
 * @param string $content - HTML content dengan image tags
 * @return array - Array of image URLs (relative paths)
 */
function extract_images_from_content($content) {
    $images = [];
    if (empty($content)) {
        return $images;
    }
    
    // Normalize backslashes to forward slashes for consistent matching
    $content = str_replace('\\', '/', $content);

    // 1) Main pattern: Match <img src="...uploads/posts/...">
    // This handles: src="path", src='path', src=path (unquoted)
    if (preg_match_all('/<img[^>]+src\s*=\s*["\']?([^"\'>\s]*uploads\/posts\/[^"\'>\s]*)["\']?/i', $content, $matches)) {
        foreach ($matches[1] as $path) {
            $path = trim($path, "'\" \t\n\r");
            if (empty($path)) continue;
            
            // Remove query string if present
            $path = preg_replace('/[\?#].*/', '', $path);
            
            $filename = basename($path);
            if (!empty($filename) && strlen($filename) > 3 && strpos($filename, 'uploads') === false) {
                // Ensure path is in correct format
                if (strpos($path, 'uploads/posts/') === 0) {
                    $images[] = $path;
                } else {
                    $images[] = 'uploads/posts/' . $filename;
                }
            }
        }
    }

    // 2) Match srcset attributes (contain multiple urls separated by comma)
    if (preg_match_all('/<img[^>]+srcset\s*=\s*["\']([^"\']+)["\']/i', $content, $srcset_matches)) {
        foreach ($srcset_matches[1] as $srcset) {
            $parts = explode(',', $srcset);
            foreach ($parts as $part) {
                $url = trim(explode(' ', trim($part))[0]);
                if (stripos($url, 'uploads/posts/') !== false) {
                    $url = preg_replace('/[\?#].*/', '', $url);
                    $filename = basename($url);
                    if (!empty($filename) && strlen($filename) > 3) {
                        $images[] = 'uploads/posts/' . $filename;
                    }
                }
            }
        }
    }

    // 3) background-image in style attributes
    if (preg_match_all('/background-image\s*:\s*url\(\s*["\']?([^)\'\"]+uploads\/posts\/[^)\'\"]*)["\']?\s*\)/i', $content, $bg_matches)) {
        foreach ($bg_matches[1] as $path) {
            $path = trim($path, "'\" ");
            $path = preg_replace('/[\?#].*/', '', $path);
            $filename = basename($path);
            if (!empty($filename) && strlen($filename) > 3) {
                $images[] = 'uploads/posts/' . $filename;
            }
        }
    }

    return array_unique($images);
}

/**
 * Get image files associated with a post (from content)
 * Extracts both featured image dan images dalam content
 * @param array $post - Post data dari database
 * @return array - Array of all image paths
 */
function get_post_images($post) {
    $images = [];
    
    // Tambah featured image dengan path normalisasi
    if (!empty($post['featured_image'])) {
        $featured = $post['featured_image'];
        // Normalize featured image path
        if (strpos($featured, 'uploads/featured') === 0 || strpos($featured, 'uploads/posts') === 0) {
            $images[] = $featured;
        } else {
            // Try to fix path
            $filename = basename($featured);
            if (file_exists('uploads/featured/' . $filename)) {
                $images[] = 'uploads/featured/' . $filename;
            } elseif (file_exists('uploads/posts/' . $filename)) {
                $images[] = 'uploads/posts/' . $filename;
            } else {
                // Assume it's featured image path
                $images[] = 'uploads/featured/' . $filename;
            }
        }
    }
    
    // Extract images dari content
    if (!empty($post['content'])) {
        $content_images = extract_images_from_content($post['content']);
        $images = array_merge($images, $content_images);
    }
    
    return array_unique($images);
}

/**
 * Delete image file from filesystem
 * @param string $image_path - Relative path dari root (uploads/posts/filename)
 * @return bool - True jika berhasil atau file tidak ada, False jika gagal
 */
function delete_image_file($image_path) {
    if (empty($image_path)) {
        return true;
    }
    
    // Trim whitespace
    $image_path = trim($image_path);
    if (empty($image_path)) {
        return true;
    }
    
    // Normalize path - remove leading ../ or ..\
    $clean_path = $image_path;
    while (strpos($clean_path, '../') === 0 || strpos($clean_path, '..\\') === 0) {
        $clean_path = substr($clean_path, 3);
    }
    
    // Replace backslashes dengan forward slashes untuk consistency
    $clean_path = str_replace('\\', '/', $clean_path);
    
    // Get base directory of this config folder (go up one level to project root)
    $base_dir = dirname(__DIR__);
    
    // Build full absolute path
    $full_path = $base_dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $clean_path);
    
    // Check if file exists
    if (!file_exists($full_path)) {
        // File sudah tidak ada, dianggap sukses
        return true;
    }
    
    // Double-check it's a file, not a directory
    if (!is_file($full_path)) {
        // Not a file, reject
        return false;
    }
    
    // Safety check - verify path is within uploads folder
    // Use realpath untuk get absolute path yang canonical
    $real_full_path = realpath($full_path);
    $uploads_dir = realpath($base_dir . DIRECTORY_SEPARATOR . 'uploads');
    
    // If either path cannot be realized, use fallback check with string comparison
    if ($real_full_path === false || $uploads_dir === false) {
        // Fallback: check if path contains 'uploads'
        if (stripos($clean_path, 'uploads') === false) {
            return false; // Path doesn't contain 'uploads', reject for safety
        }
    } else {
        // Use realpath for strict security check
        if (strpos($real_full_path, $uploads_dir) !== 0) {
            return false; // Path is outside uploads directory
        }
    }
    
    // Attempt to delete file
    $deleted = @unlink($full_path);
    
    return $deleted;
}

/**
 * Delete all images associated with a post
 * @param array $post - Post data
 * @return array - Report array ['deleted' => count, 'failed' => count]
 */
function delete_post_images($post) {
    $images = get_post_images($post);
    $report = ['deleted' => 0, 'failed' => 0];
    
    foreach ($images as $image_path) {
        if (delete_image_file($image_path)) {
            $report['deleted']++;
        } else {
            $report['failed']++;
        }
    }
    
    return $report;
}

/**
 * Compare old and new content images, delete removed images
 * @param string $old_content - Content lama
 * @param string $new_content - Content baru
 * @param string $old_featured - Featured image lama
 * @param string $new_featured - Featured image baru
 * @return array - Report array ['deleted' => count, 'kept' => count]
 */
function cleanup_orphaned_images($old_content, $new_content, $old_featured, $new_featured) {
    $report = ['deleted' => 0, 'kept' => 0];
    
    // Extract images dari old content
    $old_images = extract_images_from_content($old_content);
    if (!empty($old_featured)) {
        $old_images[] = $old_featured;
    }
    
    // Extract images dari new content
    $new_images = extract_images_from_content($new_content);
    if (!empty($new_featured)) {
        $new_images[] = $new_featured;
    }
    
    // Find orphaned images (ada di old tapi tidak di new)
    $orphaned = array_diff($old_images, $new_images);
    
    foreach ($orphaned as $image_path) {
        if (delete_image_file($image_path)) {
            $report['deleted']++;
        }
    }
    
    $report['kept'] = count($new_images);
    
    return $report;
}

/**
 * Normalize image paths dalam content
 * Convert dari berbagai format ke format yang konsisten: uploads/posts/filename
 * @param string $content - HTML content
 * @return string - Normalized content
 */
function normalize_image_paths($content) {
    if (empty($content)) {
        return $content;
    }
    
    // Pattern 1: ../uploads/posts/filename -> uploads/posts/filename
    // Handle both single dan double quotes, serta escaped quotes
    $content = preg_replace_callback(
        '/src\s*=\s*["\'](?:\.\.\/)+uploads\/posts\/([^"\'\\\\]+)["\']/',
        function($matches) {
            $filename = str_replace('\\', '', $matches[1]);
            return 'src="uploads/posts/' . $filename . '"';
        },
        $content
    );
    
    // Pattern 2: ..\uploads\posts\filename -> uploads/posts/filename (Windows path)
    $content = preg_replace_callback(
        '/src\s*=\s*["\'](?:\.\.\\\\)+uploads\\\\posts\\\\([^"\'\\\\]+)["\']/',
        function($matches) {
            $filename = str_replace('\\', '', $matches[1]);
            return 'src="uploads/posts/' . $filename . '"';
        },
        $content
    );
    
    // Pattern 3: /uploads/posts/filename -> uploads/posts/filename (absolute path)
    $content = preg_replace_callback(
        '/src\s*=\s*["\']\/uploads\/posts\/([^"\']+)["\']/',
        function($matches) {
            return 'src="uploads/posts/' . $matches[1] . '"';
        },
        $content
    );
    
    return $content;
}
?>