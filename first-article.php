<?php
require_once 'config/db.php';

$query = "SELECT slug FROM posts WHERE status = 'published' LIMIT 1";
$result = mysqli_query($conn, $query);
$post = mysqli_fetch_assoc($result);

if ($post) {
    header("Location: /webai/blog-konten/" . $post['slug']);
} else {
    echo "No posts found";
}
?>
