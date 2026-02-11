<?php
session_start();

// Sesuaikan include config DB jika Anda punya file konfigurasi
// require_once __DIR__ . '/config.php';
// Jika tidak ada, gunakan koneksi default (Laragon: user root tanpa password)
if (!isset($mysqli)) {
    $mysqli = new mysqli('localhost', 'root', '', 'smk_blog_db');
    if ($mysqli->connect_errno) {
        die('DB connect error: ' . $mysqli->connect_error);
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '') $errors[] = 'Nama wajib diisi';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email tidak valid';
    if (strlen($password) < 6) $errors[] = 'Password minimal 6 karakter';

    if (empty($errors)) {
        $stmt = $mysqli->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = 'Email sudah terdaftar';
        }
        $stmt->close();
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $role = 'user';
        $stmt = $mysqli->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('ssss', $name, $email, $hash, $role);
        if ($stmt->execute()) {
            // Arahkan ke halaman login admin jika itu yang dipakai di proyek Anda
            header('Location: admin/login.php?registered=1');
            exit;
        } else {
            $errors[] = 'Gagal menyimpan data: ' . $stmt->error;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Register</title>
    <!-- Jika admin/login.php menggunakan stylesheet khusus, ganti path di bawah -->
    <link rel="stylesheet" href="admin/assets/css/style.css">
    <link rel="stylesheet" href="../css/admin-style.css">
    <style>
        /* fallback styling mirip form login jika tidak ada stylesheet project */
        body { font-family: Arial, sans-serif; background:#f4f6f9; display:flex; align-items:center; justify-content:center; height:100vh; margin:0; }
        .card { background:#fff; padding:28px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.08); width:360px; }
        .card h2 { margin:0 0 16px; font-size:20px; text-align:center; }
        .form-group { margin-bottom:12px; }
        .form-group label { display:block; font-size:13px; margin-bottom:6px; }
        .form-group input { width:100%; padding:10px; border:1px solid #ccd0d5; border-radius:4px; }
        .btn { width:100%; padding:10px; background:#007bff; color:#fff; border:none; border-radius:4px; cursor:pointer; }
        .errors { background:#fff3f3; color:#b00020; padding:8px; border-radius:4px; margin-bottom:12px; font-size:14px; }
        .muted { text-align:center; margin-top:12px; font-size:13px; color:#666; }
        a.small { color:#007bff; text-decoration:none; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Register</h2>

        <?php if (!empty($errors)): ?>
            <div class="errors">
                <?php foreach ($errors as $e): ?>
                    <div><?php echo htmlspecialchars($e); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="form-group">
                <label for="name">Nama</label>
                <input id="name" name="name" type="text" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="password">Password (min 6 karakter)</label>
                <input id="password" name="password" type="password" required>
            </div>

            <button class="btn" type="submit">Daftar</button>
        </form>

        <div class="muted">
            Sudah punya akun? <a class="small" href="login.php">Login</a>
        </div>
    </div>
</body>
</html>