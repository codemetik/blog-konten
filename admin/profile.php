<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$admin_id = $_SESSION['admin_id'] ?? 0;
$message = '';
$alert_type = '';

// Get admin data
$query = "SELECT id, name, email, created_at FROM users WHERE id = $admin_id";
$result = mysqli_query($conn, $query);
$admin = mysqli_fetch_assoc($result);

if (!$admin) {
    $_SESSION['error'] = '❌ Data admin tidak ditemukan!';
    header('Location: index.php');
    exit;
}

// Handle form submission for profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // UPDATE PROFILE
    if ($_POST['action'] === 'update_profile') {
        $name = mysqli_real_escape_string($conn, trim($_POST['name']));
        $email = mysqli_real_escape_string($conn, trim($_POST['email']));
        
        // Validate
        if (empty($name)) {
            $message = '❌ Nama tidak boleh kosong!';
            $alert_type = 'danger';
        } elseif (empty($email)) {
            $message = '❌ Email tidak boleh kosong!';
            $alert_type = 'danger';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = '❌ Format email tidak valid!';
            $alert_type = 'danger';
        } else {
            // Check if email already exists (exclude current admin)
            $check_query = "SELECT id FROM users WHERE email = '$email' AND id != $admin_id";
            $check_result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                $message = '❌ Email sudah digunakan oleh admin lain!';
                $alert_type = 'danger';
            } else {
                $update_query = "UPDATE users SET name = '$name', email = '$email' WHERE id = $admin_id";
                
                if (mysqli_query($conn, $update_query)) {
                    // Update session
                    $_SESSION['admin_name'] = $name;
                    $admin['name'] = $name;
                    $admin['email'] = $email;
                    
                    $message = '✅ Profile berhasil diperbarui!';
                    $alert_type = 'success';
                } else {
                    $message = '❌ Gagal memperbarui profile: ' . mysqli_error($conn);
                    $alert_type = 'danger';
                }
            }
        }
    }
    
    // CHANGE PASSWORD
    elseif ($_POST['action'] === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate
        if (empty($current_password)) {
            $message = '❌ Password saat ini tidak boleh kosong!';
            $alert_type = 'danger';
        } elseif (empty($new_password)) {
            $message = '❌ Password baru tidak boleh kosong!';
            $alert_type = 'danger';
        } elseif (empty($confirm_password)) {
            $message = '❌ Konfirmasi password tidak boleh kosong!';
            $alert_type = 'danger';
        } elseif (strlen($new_password) < 6) {
            $message = '❌ Password baru minimal 6 karakter!';
            $alert_type = 'danger';
        } elseif ($new_password !== $confirm_password) {
            $message = '❌ Password baru dan konfirmasi tidak cocok!';
            $alert_type = 'danger';
        } else {
            // Verify current password
            $pass_query = "SELECT password FROM users WHERE id = $admin_id";
            $pass_result = mysqli_query($conn, $pass_query);
            $pass_data = mysqli_fetch_assoc($pass_result);
            
            if (!password_verify($current_password, $pass_data['password'])) {
                $message = '❌ Password saat ini salah!';
                $alert_type = 'danger';
            } else {
                // Hash new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_pass_query = "UPDATE users SET password = '$hashed_password' WHERE id = $admin_id";
                
                if (mysqli_query($conn, $update_pass_query)) {
                    $message = '✅ Password berhasil diubah!';
                    $alert_type = 'success';
                } else {
                    $message = '❌ Gagal mengubah password: ' . mysqli_error($conn);
                    $alert_type = 'danger';
                }
            }
        }
    }
}

// Format created_at
$created_date = date('d M Y H:i', strtotime($admin['created_at']));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Admin - SMK Satya Praja 2</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/admin-style.css">
    <style>
        :root {
            --primary: #6366f1;
            --secondary: #8b5cf6;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }

        body {
            background-color: #f8f9fa;
        }

        .sidebar {
            border-right: 1px solid #dee2e6;
            background-color: #f8f9fa;
        }

        .nav-link {
            color: #495057;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            padding-left: 1rem;
        }

        .nav-link:hover {
            color: var(--primary);
            background-color: #e9ecef;
            border-left-color: var(--primary);
        }

        .nav-link.active {
            color: var(--primary);
            background-color: #e9ecef;
            border-left-color: var(--primary);
            font-weight: 600;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border: none;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        }

        .card {
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        }

        .profile-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border: 3px solid white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
        }

        .info-group {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 6px;
            margin-bottom: 1rem;
        }

        .info-label {
            font-size: 0.85rem;
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 1.1rem;
            color: #212529;
            margin-top: 0.25rem;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .form-section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #212529;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--primary);
        }

        .password-input-group {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--primary);
            background: none;
            border: none;
            z-index: 10;
        }

        .alert {
            border: none;
            border-left: 4px solid;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            border-left-color: var(--success);
            background-color: #ecfdf5;
            color: #065f46;
        }

        .alert-danger {
            border-left-color: var(--danger);
            background-color: #fef2f2;
            color: #7f1d1d;
        }

        .btn-group-custom {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }

        @media (max-width: 768px) {
            .btn-group-custom {
                flex-direction: column;
            }

            .btn-group-custom button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top shadow-sm" style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);">
        <div class="container-fluid px-3 px-md-4">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="bi bi-book-fill text-warning"></i> Admin SMK Blog
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="adminNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['admin_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item active" href="profile.php"><i class="bi bi-person me-2"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="../index.php" target="_blank"><i class="bi bi-globe me-2"></i> Lihat Website</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar py-3">
                <div class="position-sticky">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="bi bi-house-door me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="add-post.php">
                                <i class="bi bi-plus-circle me-2"></i> Tambah Post
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="posts.php">
                                <i class="bi bi-newspaper me-2"></i> Semua Post
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="categories.php">
                                <i class="bi bi-tag me-2"></i> Kategori
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="cleanup-orphaned-images.php">
                                <i class="bi bi-file-image me-2"></i> Clean Orphaned
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="profile.php">
                                <i class="bi bi-person-circle me-2"></i> Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">
                                <i class="bi bi-gear me-2"></i> Pengaturan
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h2">
                        <i class="bi bi-person-circle me-2"></i> Profile Admin
                    </h1>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i> Kembali
                    </a>
                </div>

                <!-- Alert Messages -->
                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $alert_type; ?>" role="alert">
                    <i class="bi bi-<?php echo $alert_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <!-- Profile Header Card -->
                <div class="profile-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="profile-avatar">
                            <i class="bi bi-person-fill"></i>
                        </div>
                        <div>
                            <h3 class="mb-1"><?php echo htmlspecialchars($admin['name']); ?></h3>
                            <p class="mb-0 opacity-75">
                                <i class="bi bi-envelope me-1"></i> <?php echo htmlspecialchars($admin['email']); ?>
                            </p>
                            <small class="opacity-75">
                                <i class="bi bi-clock me-1"></i> Bergabung: <?php echo $created_date; ?>
                            </small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Left Column -->
                    <div class="col-lg-8">
                        <!-- Edit Profile Form -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-body">
                                <div class="form-section-title">
                                    <i class="bi bi-pencil-square me-2"></i> Edit Profile
                                </div>

                                <form method="POST" action="" class="needs-validation">
                                    <input type="hidden" name="action" value="update_profile">

                                    <div class="mb-3">
                                        <label for="name" class="form-label fw-500">
                                            <i class="bi bi-person me-1"></i> Nama Lengkap
                                        </label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($admin['name']); ?>" 
                                               required placeholder="Masukkan nama lengkap">
                                        <small class="text-muted">Nama yang akan ditampilkan di sistem</small>
                                    </div>

                                    <div class="mb-3">
                                        <label for="email" class="form-label fw-500">
                                            <i class="bi bi-envelope me-1"></i> Email
                                        </label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($admin['email']); ?>" 
                                               required placeholder="Masukkan email">
                                        <small class="text-muted">Email harus unik dan valid</small>
                                    </div>

                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-check-circle me-1"></i> Simpan Perubahan
                                        </button>
                                        <button type="reset" class="btn btn-outline-secondary">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Change Password Form -->
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <div class="form-section-title">
                                    <i class="bi bi-shield-lock me-2"></i> Ubah Password
                                </div>

                                <form method="POST" action="" class="needs-validation">
                                    <input type="hidden" name="action" value="change_password">

                                    <div class="mb-3">
                                        <label for="current_password" class="form-label fw-500">
                                            <i class="bi bi-lock me-1"></i> Password Saat Ini
                                        </label>
                                        <div class="password-input-group">
                                            <input type="password" class="form-control" id="current_password" 
                                                   name="current_password" required 
                                                   placeholder="Masukkan password saat ini">
                                            <button type="button" class="toggle-password" 
                                                    onclick="togglePassword('current_password')">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                        <small class="text-muted">Wajib untuk verifikasi keamanan</small>
                                    </div>

                                    <div class="mb-3">
                                        <label for="new_password" class="form-label fw-500">
                                            <i class="bi bi-lock me-1"></i> Password Baru
                                        </label>
                                        <div class="password-input-group">
                                            <input type="password" class="form-control" id="new_password" 
                                                   name="new_password" required 
                                                   placeholder="Masukkan password baru (minimal 6 karakter)">
                                            <button type="button" class="toggle-password" 
                                                    onclick="togglePassword('new_password')">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                        <small class="text-muted">Minimal 6 karakter, gunakan kombinasi huruf dan angka</small>
                                    </div>

                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label fw-500">
                                            <i class="bi bi-lock me-1"></i> Konfirmasi Password Baru
                                        </label>
                                        <div class="password-input-group">
                                            <input type="password" class="form-control" id="confirm_password" 
                                                   name="confirm_password" required 
                                                   placeholder="Konfirmasi password baru">
                                            <button type="button" class="toggle-password" 
                                                    onclick="togglePassword('confirm_password')">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                        <small class="text-muted">Harus sama dengan password baru</small>
                                    </div>

                                    <!-- Password Requirements -->
                                    <div class="alert alert-info mb-3">
                                        <small>
                                            <strong>Persyaratan Password:</strong>
                                            <ul class="mb-0 mt-2 ps-3">
                                                <li>Minimal 6 karakter</li>
                                                <li>Gunakan kombinasi huruf besar dan kecil</li>
                                                <li>Tambahkan angka atau simbol untuk keamanan lebih</li>
                                            </ul>
                                        </small>
                                    </div>

                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-check-circle me-1"></i> Ubah Password
                                        </button>
                                        <button type="reset" class="btn btn-outline-secondary">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column - Info & Security -->
                    <div class="col-lg-4">
                        <!-- Account Info -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-body">
                                <h5 class="card-title fw-bold mb-3">
                                    <i class="bi bi-info-circle me-2"></i> Informasi Akun
                                </h5>

                                <div class="info-group">
                                    <div class="info-label">ID Admin</div>
                                    <div class="info-value">#<?php echo $admin['id']; ?></div>
                                </div>

                                <div class="info-group">
                                    <div class="info-label">Username</div>
                                    <div class="info-value"><?php echo htmlspecialchars($admin['name']); ?></div>
                                </div>

                                <div class="info-group">
                                    <div class="info-label">Email</div>
                                    <div class="info-value">
                                        <a href="mailto:<?php echo $admin['email']; ?>" style="color: var(--primary);">
                                            <?php echo htmlspecialchars($admin['email']); ?>
                                        </a>
                                    </div>
                                </div>

                                <div class="info-group">
                                    <div class="info-label">Bergabung Sejak</div>
                                    <div class="info-value"><?php echo $created_date; ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Security Tips -->
                        <div class="card shadow-sm bg-light border-0">
                            <div class="card-body">
                                <h5 class="card-title fw-bold mb-3">
                                    <i class="bi bi-shield-check me-2" style="color: var(--success);"></i> Tips Keamanan
                                </h5>

                                <ul class="list-unstyled">
                                    <li class="mb-2">
                                        <i class="bi bi-check-circle-fill me-2" style="color: var(--success);"></i>
                                        <small>Gunakan password yang kuat dan unik</small>
                                    </li>
                                    <li class="mb-2">
                                        <i class="bi bi-check-circle-fill me-2" style="color: var(--success);"></i>
                                        <small>Jangan bagikan password dengan orang lain</small>
                                    </li>
                                    <li class="mb-2">
                                        <i class="bi bi-check-circle-fill me-2" style="color: var(--success);"></i>
                                        <small>Ubah password secara berkala (3-6 bulan)</small>
                                    </li>
                                    <li class="mb-2">
                                        <i class="bi bi-check-circle-fill me-2" style="color: var(--success);"></i>
                                        <small>Logout setelah selesai menggunakan admin</small>
                                    </li>
                                    <li>
                                        <i class="bi bi-check-circle-fill me-2" style="color: var(--success);"></i>
                                        <small>Jangan gunakan password yang sama dengan akun lain</small>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const button = event.target.closest('.toggle-password');
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }

        // Form validation
        (function () {
            'use strict';
            window.addEventListener('load', function () {
                const forms = document.querySelectorAll('.needs-validation');
                Array.prototype.slice.call(forms).forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            });
        })();

        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function () {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>
