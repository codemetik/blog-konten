<?php
session_start();
require_once '../config/db.php';
require_once '../config/helpers.php';

if(isset($_GET['id'])){
    $post_id = intval($_GET['id']);
    
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
        
        // DEBUG: Store untuk ditampilkan nanti
        $debug_info = [];
        $debug_info[] = "Post ID: " . $post['id'];
        $debug_info[] = "Featured Image: " . $post['featured_image'];
        $debug_info[] = "Content length: " . strlen($post['content']);
        
        // Get all images yang akan dihapus sebelum delete post
        $images_to_delete = get_post_images($post);
        
        $debug_info[] = "Images detected: " . count($images_to_delete);
        foreach ($images_to_delete as $img) {
            $debug_info[] = "  - " . $img;
        }
        
        // Hapus post dari database
        $delete_query = "DELETE FROM posts WHERE id = $post_id";
        if(mysqli_query($conn, $delete_query)){
            $debug_info[] = "Post deleted from DB: YES";
            
            // Delete semua gambar yang terkait dengan post
            $deleted_count = 0;
            $failed_count = 0;
            
            if (!empty($images_to_delete)) {
                foreach($images_to_delete as $image_path) {
                    $delete_result = delete_image_file($image_path);
                    if($delete_result) {
                        $debug_info[] = "Deleted: " . $image_path;
                        $deleted_count++;
                    } else {
                        $debug_info[] = "Failed: " . $image_path;
                        $failed_count++;
                    }
                }
            }
            
            $debug_info[] = "Total deleted: $deleted_count";
            $debug_info[] = "Total failed: $failed_count";
            
            // Store debug info di session
            $_SESSION['debug_info'] = implode("\n", $debug_info);
            
            if($failed_count == 0 && $deleted_count > 0) {
                $_SESSION['success'] = "✅ Post berhasil dihapus! " . $deleted_count . " gambar dihapus.";
            } elseif($deleted_count == 0 && $failed_count == 0) {
                $_SESSION['success'] = "✅ Post berhasil dihapus! (Tidak ada gambar untuk dihapus)";
            } else {
                $_SESSION['success'] = "⚠️ Post dihapus tetapi " . $failed_count . " gambar gagal dihapus (" . $deleted_count . " berhasil).";
            }
        } else {
            $_SESSION['error'] = "❌ Gagal menghapus post: " . mysqli_error($conn);
        }
    } else {
        $_SESSION['error'] = "❌ Post tidak ditemukan.";
    }
    
    header("Location: index.php");
    exit();
} else {
    $_SESSION['error'] = "❌ ID post tidak valid.";
    header("Location: index.php");
    exit();
}
?>