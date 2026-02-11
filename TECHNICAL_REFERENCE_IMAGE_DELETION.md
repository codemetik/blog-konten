# 🔧 TECHNICAL REFERENCE - Modified Functions

## Overview Struktur

```
delete-post.php (Entry Point)
    ↓
get_post_images($post)  ← Collect all images
    ├── featured_image path
    ├── extract_images_from_content()  ← Parse content HTML
    └── array_unique()  ← Remove duplicates
        ↓
        For each image:
        └── delete_image_file($image_path)  ← Delete from disk
            ├── Normalize path
            ├── Security check
            └── unlink() file
```

---

## Modified Functions Reference

### 1. `extract_images_from_content($content)`

**Location**: `config/helpers.php` (Line ~264)

**Purpose**: Extract all `<img>` tags from HTML content dan ambil src path

**Input**: `$content` - HTML string dengan gambar

**Output**: Array of image paths (format: `uploads/posts/filename.jpg`)

**Patterns Matched**:

| # | Format | Regex Pattern | Example |
|---|--------|---------------|---------|
| 1 | Relative with `../` | `(?:\.\.\/)*uploads\/posts\/` | `src="../uploads/posts/img.jpg"` |
| 2 | Windows path | `(?:\.\.\\\)*uploads\\posts\\` | `src="..\uploads\posts\img.jpg"` |
| 3 | Direct path | `uploads\/posts\/` | `src="uploads/posts/img.jpg"` |
| 4 | Absolute path | `\/uploads\/posts\/` | `src="/uploads/posts/img.jpg"` |

**How It Works**:
```php
// Pattern 1: Cek ../uploads/posts/
if (preg_match_all('/src\s*=\s*["\'](?:\.\.\/)*uploads\/posts\/([^"\']+)["\']/', $content, $matches)) {
    foreach ($matches[1] as $filename) {
        $images[] = 'uploads/posts/' . $filename;
    }
}

// ... repeat untuk pattern 2, 3, 4 ...

// Return unique array
return array_unique($images);
```

**Return Value**:
```php
[
    'uploads/posts/image1.jpg',
    'uploads/posts/image2.jpg',
    'uploads/featured/featured.jpg'  // jika ada
]
```

---

### 2. `get_post_images($post)`

**Location**: `config/helpers.php` (Line ~320)

**Purpose**: Kumpulkan SEMUA gambar yang terkait dengan post (featured + content)

**Input**: `$post` - Array dari database
```php
[
    'id' => 1,
    'featured_image' => 'uploads/featured/thumb.jpg',
    'content' => '<p><img src="uploads/posts/img.jpg"></p>',
    ...
]
```

**Output**: Array of all image paths

**Logic**:

```php
1. Check featured_image
   - Jika ada, normalize path
   - Validasi file exists
   - Tambah ke array
   
2. Check content
   - Extract images dari HTML
   - Merge dengan featured image array
   
3. Return unique array
```

**Code Flow**:
```php
function get_post_images($post) {
    $images = [];
    
    // Add featured image
    if (!empty($post['featured_image'])) {
        $featured = $post['featured_image'];
        // normalize & validate
        $images[] = $featured;
    }
    
    // Add content images
    if (!empty($post['content'])) {
        $content_images = extract_images_from_content($post['content']);
        $images = array_merge($images, $content_images);
    }
    
    return array_unique($images);  // Remove duplicates
}
```

---

### 3. `delete_image_file($image_path)`

**Location**: `config/helpers.php` (Line ~357)

**Purpose**: Hapus 1 file gambar dari disk (safety + checks)

**Input**: `$image_path` - Path relatif
```php
// Examples yang diterima:
'uploads/posts/image.jpg'
'uploads/featured/thumb.jpg'
'../uploads/posts/old.jpg'
'..\uploads\posts\win.jpg'
```

**Output**: `bool`
- `true` = File deleted atau tidak ada (dianggap sukses)
- `false` = Delete failed

**Security Features**:
```php
1. Path Normalization
   ✓ Remove leading ../
   ✓ Convert \ to /
   ✓ Proper directory separator
   
2. Security Checks
   ✓ Directory boundary check (must be in uploads/)
   ✓ is_file() validation (not directory)
   ✓ realpath() with fallback
   
3. Error Handling
   ✓ Return true jika file tidak ada
   ✓ Use @ to suppress error on unlink
   ✓ No throw exceptions (silent fail)
```

**Detailed Flow**:

```php
function delete_image_file($image_path) {
    // Step 1: Validate
    if (empty($image_path)) return true;
    
    // Step 2: Normalize path
    $clean_path = $image_path;
    while (strpos($clean_path, '../') === 0) {
        $clean_path = substr($clean_path, 3);
    }
    $clean_path = str_replace('\\', '/', $clean_path);
    
    // Step 3: Build full path
    $base_dir = dirname(__DIR__);
    $full_path = $base_dir . DIRECTORY_SEPARATOR . 
                 str_replace('/', DIRECTORY_SEPARATOR, $clean_path);
    
    // Step 4: Security check
    $real_full_path = realpath($full_path) ?: $full_path;
    $real_uploads_dir = realpath($uploads_dir) ?: $uploads_dir;
    
    if (strpos($real_full_path, $real_uploads_dir) !== 0) {
        return false;  // Path outside uploads/ - reject
    }
    
    // Step 5: Delete file
    if (file_exists($full_path) && is_file($full_path)) {
        return @unlink($full_path);
    }
    
    return true;  // File tidak ada
}
```

---

### 4. Modified `delete-post.php`

**Location**: `admin/delete-post.php`

**Purpose**: Main handler untuk menghapus post dan semua gambarnya

**Key Changes**:

| Aspek | Sebelum | Sesudah |
|-------|---------|----------|
| Query | `SELECT *` | `SELECT id, title, featured_image, content` |
| Image extraction | Setelah delete | Sebelum delete |
| Feedback | Generic message | Detailed count |
| Error handling | Basic | Comprehensive |
| Validation | Minimal | ID validation added |

**Flow Diagram**:

```
Request: GET /admin/delete-post.php?id=123
    ↓
Validate ID (> 0)
    ↓ Valid
SELECT post data
    ↓ Found
Extract all images with get_post_images()
    ↓ [img1, img2, img3, ...]
DELETE FROM posts WHERE id=123
    ↓ Success
Loop each image:
    ├→ delete_image_file(img1) → success
    ├→ delete_image_file(img2) → success
    └→ delete_image_file(img3) → success
    ↓
Set $_SESSION['success'] = "Post berhasil dihapus! 3 gambar dihapus."
    ↓
Redirect to index.php
    ↓
Show success message to admin
```

**Message Examples**:

```php
// Scenario 1: All deleted
"✅ Post berhasil dihapus! 3 gambar dihapus."

// Scenario 2: Some failed
"⚠️ Post dihapus tetapi 1 gambar gagal (2 berhasil)."

// Scenario 3: Post not found
"❌ Post tidak ditemukan."

// Scenario 4: Delete failed
"❌ Gagal menghapus post: [error message]"
```

---

## Function Call Chain Example

### Scenario: Delete Article dengan 2 images

```
1. Admin clicks Delete button
   └─→ delete-post.php?id=5

2. Get post data
   SELECT id, title, featured_image, content FROM posts WHERE id = 5
   Result:
   [
       'id' => 5,
       'featured_image' => 'uploads/featured/hero.jpg',
       'content' => '<img src="uploads/posts/detail1.jpg"><img src="uploads/posts/detail2.jpg">'
   ]

3. Call get_post_images($post)
   ├─ Check featured_image → 'uploads/featured/hero.jpg' ✓
   └─ Call extract_images_from_content($content)
       ├─ Pattern 1-4 matching...
       ├─ Found 'uploads/posts/detail1.jpg' ✓
       └─ Found 'uploads/posts/detail2.jpg' ✓
   
   Return: [
       'uploads/featured/hero.jpg',
       'uploads/posts/detail1.jpg',
       'uploads/posts/detail2.jpg'
   ]

4. DELETE FROM posts WHERE id = 5
   ✓ Post deleted from database

5. Loop delete each image:
   ├─ delete_image_file('uploads/featured/hero.jpg')
   │  └─ /xampp/htdocs/blog-konten/uploads/featured/hero.jpg ✓ DELETED
   │
   ├─ delete_image_file('uploads/posts/detail1.jpg')
   │  └─ /xampp/htdocs/blog-konten/uploads/posts/detail1.jpg ✓ DELETED
   │
   └─ delete_image_file('uploads/posts/detail2.jpg')
      └─ /xampp/htdocs/blog-konten/uploads/posts/detail2.jpg ✓ DELETED

6. Set $deleted_count = 3, $failed_count = 0

7. $_SESSION['success'] = "✅ Post berhasil dihapus! 3 gambar dihapus."

8. header("Location: index.php")
   └─ Redirect to posts list, show success message
```

---

## Error Handling Matrix

| Scenario | Handled | Behavior |
|----------|---------|----------|
| Post not found | ✓ | Show error: "Post tidak ditemukan" |
| Invalid ID | ✓ | Show error: "ID post tidak valid" |
| Delete fails | ✓ | Show warning message with details |
| Image file missing | ✓ | Counted as success (already gone) |
| Permission denied | ✓ | Counted as failed, reported |
| Path traversal attempt | ✓ | Rejected at delete_image_file level |

---

## Testing Key Functions Independently

### Test 1: extract_images_from_content()

```php
<?php
require_once 'config/helpers.php';

$html = '<p><img src="uploads/posts/image1.jpg"><img src="../uploads/posts/image2.jpg"></p>';
$images = extract_images_from_content($html);

var_dump($images);
// Expected:
// array(2) {
//   [0]=> string(26) "uploads/posts/image1.jpg"
//   [1]=> string(26) "uploads/posts/image2.jpg"
// }
?>
```

### Test 2: delete_image_file()

```php
<?php
require_once 'config/helpers.php';

// Create test file
file_put_contents('uploads/posts/test_delete.jpg', 'test content');

// Delete it
$result = delete_image_file('uploads/posts/test_delete.jpg');

var_dump($result);  // Should be: bool(true)
// And file should not exist:
file_exists('uploads/posts/test_delete.jpg');  // bool(false)
?>
```

---

## Database Considerations

### Featured Image Storage
- Column: `featured_image` (varchar)
- Format: `uploads/featured/filename.jpg` atau `../uploads/featured/filename.jpg`
- Nullable: YES

### Content Storage
- Column: `content` (longtext)
- Format: HTML dengan `<img src="...">` tags
- Images can be: relative, absolute, Windows format

### Best Practice
```sql
-- Ensure paths are consistent
UPDATE posts SET featured_image = REPLACE(featured_image, '../', 'uploads/') 
WHERE featured_image LIKE '../%';

-- Check for orphaned entries
SELECT featured_image FROM posts WHERE featured_image IS NULL OR featured_image = '';
```

---

**Document Version**: 1.0  
**Last Updated**: February 10, 2026  
**Status**: Production Ready

