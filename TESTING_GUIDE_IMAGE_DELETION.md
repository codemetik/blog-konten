# 📝 TESTING GUIDE - Perbaikan Bug Penghapusan Gambar

## Quick Start Testing

### Prasyarat
- Sudah login ke admin panel
- Punya akses upload gambar
- Laragon/localhost berjalan

---

## 🧪 Test Scenario 1: Delete Article dengan Featured Image

### Setup
```
1. Buka Admin → Add Post (atau edit post existing)
2. Upload featured image
3. Catat nama file: contoh "featured_123.jpg"
4. Publish/Save artikel
```

### Execute
```
1. Pergi ke Admin → Posts
2. Cari artikel yang baru dibuat
3. Klik tombol Delete/Hapus
4. Konfirmasi penghapusan
```

### Verifikasi (Expected Result)
```
✓ Muncul pesan: "✅ Post berhasil dihapus! 1 gambar dihapus."
✓ Di folder uploads/featured/ file "featured_123.jpg" HARUS HILANG

Jika file masih ada = BUG tidak fixed
Jika pesan muncul = Perbaikan BERHASIL ✅
```

### Windows Command untuk Verifikasi
```powershell
# Cek file yang dihapus
Get-ChildItem "C:\laragon\www\webai\blog-konten\uploads\featured\" | Where-Object {$_.Name -like "*featured_123*"}

# Output harusnya: (kosong/tidak ada hasil)
```

---

## 🧪 Test Scenario 2: Delete Article dengan Image di Content

### Setup
```
1. Buat artikel baru
2. Jangan upload featured image (biarkan kosong)
3. Di bagian Content, upload/sisipkan gambar
4. Catat nama file: contoh "content_456.jpg"
5. Publish artikel
```

### Execute
```
1. Admin → Posts
2. Cari artikel tersebut
3. Klik Delete
4. Konfirmasi
```

### Verifikasi (Expected Result)
```
✓ Pesan: "✅ Post berhasil dihapus! 1 gambar dihapus."
✓ File "content_456.jpg" di uploads/posts/ HILANG

Command:
Get-ChildItem "C:\laragon\www\webai\blog-konten\uploads\posts\" | Where-Object {$_.Name -like "*content_456*"}
```

---

## 🧪 Test Scenario 3: Delete Article dengan Multiple Images

### Setup (Complete Test)
```
1. Buat artikel baru
2. Upload featured image: "feat_multi.jpg"
3. Di Content, sisipkan 2-3 gambar:
   - "img_1.jpg"
   - "img_2.jpg"  
   - "img_3.jpg"
4. Publish
```

### Execute
```
1. Admin → Posts
2. Delete artikel
3. Konfirmasi
```

### Verifikasi (Expected Result)
```
✓ Pesan: "✅ Post berhasil dihapus! 4 gambar dihapus."
✓ Semua 4 file HILANG:
  - uploads/featured/feat_multi.jpg (HILANG)
  - uploads/posts/img_1.jpg (HILANG)
  - uploads/posts/img_2.jpg (HILANG)
  - uploads/posts/img_3.jpg (HILANG)

PowerShell check:
$files = @("feat_multi.jpg", "img_1.jpg", "img_2.jpg", "img_3.jpg")
foreach($f in $files) {
    Test-Path "C:\laragon\www\webai\blog-konten\uploads\posts\$f" -PathType Leaf
    Test-Path "C:\laragon\www\webai\blog-konten\uploads\featured\$f" -PathType Leaf
}
# Semua harus return: False
```

---

## 🧪 Test Scenario 4: Delete Article dengan Special Cases

### Case 4a: Article dengan Duplicate Images
```
1. Buat artikel dengan featured image "same.jpg"
2. Di content, juga gunakan img dengan nama file yang sama
3. Publish
4. Delete
```

**Expected**: File hanya dihapus 1x (array unique), artikel hilang

### Case 4b: Article dengan Path Format Berbeda
```
Jika HTML content memiliki:
- <img src="../uploads/posts/image.jpg">
- <img src="uploads/posts/image.jpg">
- <img src="../../uploads/posts/image.jpg">

Semua format harus terdeteksi dan file dihapus ✓
```

---

## 🧪 Test Scenario 5: Cleanup Orphaned Images Tool

### Setup Skenario Orphaned Images
```
Simulasi: Gambar-gambar lama yang tidak terreference
(Dari test sebelumnya yang mungkin tidak terhapus sempurna)
```

### Access Tool
```
1. Login ke Admin
2. Akses URL: /admin/cleanup-orphaned-images.php
3. Script akan scan uploads/ folder
```

### Expected Output
```
Tabel menampilkan:
- Orphaned in uploads/posts/: X file
- Orphaned in uploads/featured/: Y file
- Total: X+Y file

Daftar file yang akan dihapus ditampilkan
```

### Execute Cleanup
```
1. Klik "Hapus Orphaned Images"
2. Konfirmasi alert
3. Tunggu proses
```

### Verifikasi Hasil
```
✓ Muncul: "Cleanup Selesai!"
✓ Report: "Gambar dihapus: X", "Gagal dihapus: 0"
✓ Folder uploads/ bersih dari orphaned files
```

---

## 📊 Success Criteria Checklist

Sebelum menganggap perbaikan SUKSES, pastikan:

### Basic Functionality
- [ ] Bisa upload featured image
- [ ] Bisa upload multiple images di content
- [ ] Artikel bisa dihapus tanpa error
- [ ] Pesan success muncul dengan jumlah gambar yang benar

### File Deletion
- [ ] Featured image terhapus otomatis saat delete post
- [ ] Content images terhapus otomatis saat delete post
- [ ] File truly deleted (bukan hanya dari DB)
- [ ] Tidak ada remnant/partial files

### Edge Cases
- [ ] Multiple images dalam 1 artikel terhapus semua
- [ ] Duplicate image filenames handled correctly
- [ ] Different path formats recognized
- [ ] Special characters in filename handled

### Tools & Features
- [ ] Cleanup orphaned images tool accessible
- [ ] Tool correctly identifies orphaned files
- [ ] Cleanup execution works without errors
- [ ] Report accurate about deleted files

### Error Handling
- [ ] Permission denied handled gracefully
- [ ] Invalid paths rejected safely
- [ ] Database errors shown to user
- [ ] Failed deletions reported in message

---

## 🔍 Manual Verification Commands

### Check File Count Before Delete
```powershell
# Count files sebelum
(Get-ChildItem "C:\laragon\www\webai\blog-konten\uploads\posts\" | Measure-Object).Count
(Get-ChildItem "C:\laragon\www\webai\blog-konten\uploads\featured\" | Measure-Object).Count
```

### Check File Count After Delete
```powershell
# Count files sesudah (harusnya berkurang)
(Get-ChildItem "C:\laragon\www\webai\blog-konten\uploads\posts\" | Measure-Object).Count
(Get-ChildItem "C:\laragon\www\webai\blog-konten\uploads\featured\" | Measure-Object).Count
```

### List All Files with Details
```powershell
# Detail list
Get-ChildItem "C:\laragon\www\webai\blog-konten\uploads\posts\" -Recurse | Format-Table FullName, Length, LastWriteTime
Get-ChildItem "C:\laragon\www\webai\blog-konten\uploads\featured\" -Recurse | Format-Table FullName, Length, LastWriteTime
```

### Find Recently Created/Deleted Files
```powershell
# Files dari hari ini
Get-ChildItem "C:\laragon\www\webai\blog-konten\uploads\" -Recurse | 
Where-Object {$_.LastWriteTime -gt (Get-Date).Date}
```

---

## 📸 Screenshots to Capture

Document testing dengan screenshot dari:

1. **Admin Panel Posts List**
   - Artikel yang dibuat
   - Tombol Delete

2. **Success Message After Delete**
   - Menampilkan "X gambar dihapus"
   - Confirm article hilang dari list

3. **File Explorer Before/After**
   - uploads/posts/ sebelum delete
   - uploads/posts/ setelah delete
   - Gambar tidak ada (clean)

4. **Cleanup Tool UI**
   - Scan results
   - Orphaned files list
   - Cleanup report

---

## 🐛 If Bug Still Exists...

Jika gambar masih tidak terhapus:

### Debug Steps
```
1. Check error logs:
   C:\laragon\logs\php_error.log
   
2. Check file permissions:
   Right-click folder → Properties → Security
   
3. Check path issues:
   - Buka uploads/posts dalam Explorer
   - Cari file dengan nama yang sesuai
   - Cek apakah file masih ada
   
4. Check database:
   SELECT id, featured_image, content FROM posts LIMIT 1;
   Lihat format path yang disimpan
   
5. Check PHP log:
   Buka browser console (F12) → Console tab
   Lihat ada error message?
```

### Data untuk Report Bug
Jika masih bug, gather:
- Database content structure
- File path format yang disimpan
- Error messages dari logs
- Screenshots dari issue
- Database screenshot (paths)

---

## ✅ Approval Criteria

Testing dianggap LULUS jika:
- ✅ Semua 5 test scenarios berhasil
- ✅ Tidak ada error messages
- ✅ File truly deleted (verified)
- ✅ Feedback message akurat
- ✅ Cleanup tool berfungsi
- ✅ No permission errors

---

**Last Updated**: February 10, 2026  
**Status**: Ready for Testing  
**Tester**: [Your Name]  
**Date Tested**: ___________  
**Result**: ✅ PASS / ❌ FAIL

