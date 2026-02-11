<?php
session_start();
require_once '../config/db.php';
require_once '../config/helpers.php';

// Debug file
$debug_file = '../uploads/debug_delete.log';

if(isset($_GET['id'])){
    $post_id = intval($_GET['id']);
    
    $debug = "=== DELETE POST DEBUG ===\n";
    $debug .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
    $debug .= "Post ID: $post_id\n";
    
    if($post_id <= 0) {
        $_SESSION['error'] = "❌ ID post tidak valid.";
        header("Location: index.php");
        exit();
    }
    
    // Get post data terlebih dahulu (untuk extract gambar)
    $get_post_query = "SELECT id, title, featured_image, content FROM posts WHERE id = $post_id";
    $post_result = mysqli_query($conn, $get_post_query);
    
    if($post_result && mysqli_num_rows($post_result) > 0) {
        $post = mysqli_fetch_assoc($post_result);
        
        $debug .= "Featured Image: " . $post['featured_image'] . "\n";
        $debug .= "Content length: " . strlen($post['content']) . "\n";
        
        // Get all images yang akan dihapus sebelum delete post
        $images_to_delete = get_post_images($post);
        
        $debug .= "Images detected: " . count($images_to_delete) . "\n";
        if (!empty($images_to_delete)) {
            foreach ($images_to_delete as $img) {
                $debug .= "  - $img\n";
            }
        }
        
        // Hapus post dari database terlebih dahulu
        $delete_query = "DELETE FROM posts WHERE id = $post_id";
        if(mysqli_query($conn, $delete_query)){
            $debug .= "POST DELETED FROM DB: YES\n";
            
            // Delete semua gambar yang terkait dengan post
            $deleted_count = 0;
            $failed_count = 0;
            
            if (!empty($images_to_delete)) {
                foreach($images_to_delete as $image_path) {
                    $debug .= "Deleting: $image_path ... ";
                    if(delete_image_file($image_path)) {
                        $debug .= "SUCCESS\n";
                        $deleted_count++;
                    } else {
                        $debug .= "FAILED\n";
                        $failed_count++;
                    }
                }
            }
            
            $debug .= "Total Deleted: $deleted_count\n";
            $debug .= "Total Failed: $failed_count\n";
            
            if($failed_count == 0 && $deleted_count > 0) {
                $_SESSION['success'] = "✅ Post berhasil dihapus! " . $deleted_count . " gambar dihapus.";
            } elseif($deleted_count == 0 && $failed_count == 0) {
                $_SESSION['success'] = "✅ Post berhasil dihapus! (Tidak ada gambar untuk dihapus)";
            } else {
                $_SESSION['success'] = "⚠️ Post dihapus tetapi " . $failed_count . " gambar gagal dihapus (" . $deleted_count . " berhasil).";
            }
        } else {
            $_SESSION['error'] = "❌ Gagal menghapus post: " . mysqli_error($conn);
            $debug .= "POST DELETED FROM DB: NO - " . mysqli_error($conn) . "\n";
        }
    } else {
        $_SESSION['error'] = "❌ Post tidak ditemukan.";
        $debug .= "POST NOT FOUND\n";
    }
    
    // Write debug
    file_put_contents($debug_file, $debug . "\n\n", FILE_APPEND);
    
    header("Location: index.php");
    exit();
} else {
    $_SESSION['error'] = "❌ ID post tidak valid.";
    header("Location: index.php");
    exit();
}
?>