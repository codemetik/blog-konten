# Perbaikan Bug Penghapusan Gambar Artikel

## Masalah yang Dilaporkan
Ketika artikel dihapus melalui admin panel, gambar-gambar yang terkait dengan artikel (baik featured image maupun gambar dalam konten) tidak ikut terhapus dari folder `uploads/posts/` dan `uploads/featured/`. Hal ini mengakibatkan file-file gambar orphan (tidak memiliki referensi) menumpuk di server.

## Penyebab Masalah

1. **Ekstraksi Gambar Tidak Sempurna**: Fungsi `extract_images_from_content()` tidak menangkap semua varian format path yang mungkin disimpan di database
2. **Path Normalisasi Lemah**: Beberapa file path tidak terdeteksi dengan benar karena berbagai format penyimpanan
3. **Featured Image Path Tidak Konsisten**: Path featured image yang disimpan tidak selalu dalam format yang diharapkan
4. **Safety Check Terlalu Ketat**: Beberapa path valid ditolak karena logika path validation

## Solusi yang Diterapkan

### 1. Perbaikan `delete_image_file()` di helpers.php
**File**: `config/helpers.php`

**Perubahan**:
- Menambahkan normalisasi path yang lebih robust
- Menangani berbagai format path (relative, absolute, Windows backslash)
- Memperbaiki logika `realpath()` yang bisa gagal pada beberapa sistem
- Menambahkan check `is_file()` untuk memastikan target adalah file

**Sebelum**:
```php
$full_path = dirname(__DIR__) . DIRECTORY_SEPARATOR . $image_path;
$full_path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $full_path);
if (strpos(realpath($full_path) ?: '', realpath($uploads_dir)) !== 0) {
    return false;
}
```

**Sesudah**:
```php
// Normalize path - remove leading ../ or ..\
$clean_path = $image_path;
while (strpos($clean_path, '../') === 0 || strpos($clean_path, '..\\') === 0) {
    $clean_path = substr($clean_path, 3);
}

// Replace backslashes dengan forward slashes
$clean_path = str_replace('\\', '/', $clean_path);

// Construct full path dengan proper handling
$base_dir = dirname(__DIR__);
$full_path = $base_dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $clean_path);
```

### 2. Perbaikan `extract_images_from_content()` di helpers.php
**File**: `config/helpers.php`

**Perubahan**:
- Menambahkan 4 regex pattern berbeda untuk menangkap semua varian format path:
  - Relative paths dengan `../` atau `../../`
  - Windows backslash paths
  - Direct paths (sudah normalized)
  - Absolute paths (dengan atau tanpa base URL)

**Pattern yang ditangani**:
```
1. src="../uploads/posts/image.jpg"
2. src="../../uploads/posts/image.jpg"
3. src="..\uploads\posts\image.jpg"  (Windows)
4. src="uploads/posts/image.jpg"
5. src="/uploads/posts/image.jpg"
6. src="/webai/blog-konten/uploads/posts/image.jpg"
```

### 3. Perbaikan `get_post_images()` di helpers.php
**File**: `config/helpers.php`

**Perubahan**:
- Menambahkan validasi path untuk featured image
- Mencoba menemukan featured image di berbagai lokasi jika path tidak valid
- Normalisasi path featured image sebelum penghapusan

### 4. Peningkatan Error Handling di delete-post.php
**File**: `admin/delete-post.php`

**Perubahan**:
- Menggunakan `SELECT` dengan column spesifik instead of `SELECT *`
- Mengambil daftar gambar SEBELUM menghapus post dari database
- Menambahkan loop yang lebih transparan untuk menghapus setiap gambar
- Menyediakan feedback yang lebih detail tentang gambar mana saja yang dihapus
- Menambahkan validasi ID post

## Pengujian

Untuk memverifikasi perbaikan berfungsi:

1. **Buat Artikel dengan Gambar**:
   - Buat artikel baru dengan featured image
   - Tambahkan gambar dalam konten artikel
   - Catat nama file gambar

2. **Verifikasi File Exists**:
   ```bash
   # Cek file di uploads/posts/
   dir c:\laragon\www\webai\blog-konten\uploads\posts\
   # Cek file di uploads/featured/
   dir c:\laragon\www\webai\blog-konten\uploads\featured\
   ```

3. **Hapus Artikel**:
   - Pergi ke Admin Panel > Posts
   - Klik tombol Delete pada artikel yang baru dibuat
   - Konfirmasi penghapusan

4. **Verifikasi Gambar Terhapus**:
   ```bash
   # Cek bahwa file gambar sudah tidak ada
   dir c:\laragon\www\webai\blog-konten\uploads\posts\
   # File dengan nama yang sesuai harus sudah hilang
   ```

5. **Cek Feedback Message**:
   - Perhatikan pesan sukses yang menampilkan:
     - "✅ Post berhasil dihapus! X gambar dihapus."
     - "⚠️ Post dihapus tetapi X gambar gagal dihapus (Y berhasil)."

## File yang Dimodifikasi

1. **config/helpers.php**
   - `delete_image_file()` - Perbaikan logika penghapusan file
   - `extract_images_from_content()` - Penambahan 4 regex pattern
   - `get_post_images()` - Penambahan validasi path untuk featured image

2. **admin/delete-post.php**
   - Perbaikan error handling
   - Penambahan feedback yang lebih detail
   - Validasi ID post yang lebih baik

## Compatibility

- PHP 7.4+
- MySQL/MySQLi
- Cross-platform (Windows/Linux)
- Backward compatible dengan artikel existing

## Future Improvements

1. Menambahkan database log untuk track penghapusan gambar
2. Membuat script untuk cleanup orphaned images
3. Menambahkan soft delete option untuk recovery
4. Implementasi image compression/optimization saat upload

## Support

Jika masih ada gambar orphan dari operasi sebelumnya, jalankan query manual:
```sql
-- Ini hanya untuk reference, JANGAN jalankan tanpa backup database
-- Hapus file di uploads/posts yang tidak memiliki referensi di database
-- Akan memerlukan PHP script khusus untuk identify orphaned files
```

---
**Status**: ✅ Perbaikan Selesai
**Tanggal**: February 10, 2026
**Tested**: Yes
