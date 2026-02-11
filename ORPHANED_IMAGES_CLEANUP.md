# 📋 Panduan Cleanup Orphaned Images

## Masalah
Ketika Anda upload gambar ke Summernote tapi kemudian:
- Membatalkan penambahan gambar
- Menghapus/mengganti gambar sebelum save artikel

Gambar tetap tersimpan di folder `uploads/posts/` dan tidak otomatis terhapus.

## Solusi
Gunakan **Cleanup Orphaned Images** tool di admin panel untuk:
1. Scan semua gambar di folder `uploads/posts/`
2. Identifikasi gambar yang tidak digunakan di artikel manapun
3. Hapus gambar orphaned dengan aman

## Cara Menggunakan

### Method 1: Via Admin Panel (Recommended)
1. Login ke admin panel
2. Buka menu **Cleanup Orphaned Images** (atau akses: `/admin/cleanup-orphaned-images.php`)
3. Tool akan menampilkan:
   - Total gambar di folder
   - Jumlah orphaned images
   - Total ukuran storage yang terbuang
4. Pilih gambar yang ingin dihapus (atau "Select All")
5. Klik **Delete Selected**
6. Confirm penghapusan

### Method 2: Otomatis (Opsional)
Admin bisa membuat scheduled task untuk cleanup otomatis setiap minggu/bulan.

## Keamanan
✅ **Gambar yang digunakan di artikel TIDAK akan dihapus** — tool scan semua artikel content sebelum hapus
✅ **Preview thumbnail** — lihat preview gambar sebelum dihapus  
✅ **Confirm dialog** — harus confirm sebelum dihapus
✅ **Size info** — tahu berapa storage yang bisa dihemat

## Contoh Skenario

### Skenario 1: Upload tapi Batalkan
```
1. Admin buka Add Post
2. Upload gambar "foto1.png" ke Summernote
3. Berubah pikiran, klik undo / hapus img dari editor
4. Tidak save post
→ "foto1.png" orphaned di uploads/posts/
→ Cleanup tool akan deteksi sebagai orphaned image
→ Bisa dihapus dengan aman
```

### Skenario 2: Ganti Gambar
```
1. Admin edit post
2. Hapus gambar lama "old.png" dari content
3. Upload gambar baru "new.png"
4. Save post
→ "old.png" menjadi orphaned (tidak digunakan di manapun)
→ Cleanup tool akan deteksi
→ Bisa dihapus dengan aman
```

## Tips
- Jalankan cleanup tool 1-2 kali sebulan untuk maintain folder
- Selalu review hasil scan sebelum delete
- Tidak perlu backup karena gambar dapat re-upload ulang dari Summernote

## Technical Details

### Bagaimana Tool Bekerja?
1. Scan semua `.png`, `.jpg`, `.jpeg`, `.gif`, `.webp` di `uploads/posts/`
2. Query semua posts dan ekstrak image references dari:
   - `posts.content` (menggunakan `extract_images_from_content()`)
   - `posts.featured_image` (untuk featured images)
3. Bandingkan: Image di folder vs Image yang direferensi
4. Gambar yang tidak direferensi = Orphaned
5. Safe delete menggunakan `delete_image_file()` dengan security checks

### File yang Terlibat
- `admin/cleanup-orphaned-images.php` — Main tool
- `config/helpers.php`:
  - `extract_images_from_content()` — Extract image paths dari HTML content
  - `delete_image_file()` — Safe delete dengan path validation
  - `get_post_images()` — Get all images associated with post
