<?php
session_start();
require_once '../config/db.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = escape_string($_POST['email']);
    $password = $_POST['password'];

    $query = "SELECT * FROM users WHERE email='$email'";
    $result = mysqli_query($conn, $query);
    $user = mysqli_fetch_assoc($result);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_name'] = $user['name'];
        $_SESSION['admin_email'] = $user['email'];
        $_SESSION['admin_role'] = $user['role'];

        header('Location: index.php');
        exit;
    } else {
        $error_message = 'Email atau password salah!';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - SMK Satya Praja 2</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            width: 100%;
            max-width: 400px;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }
        .card-header {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 2rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="card">
            <div class="card-header">
                <h3 class="fw-bold mb-1">
                    <i class="bi bi-book-fill me-2"></i> Admin Panel
                </h3>
                <p class="mb-0 text-white-50">SMK Satya Praja 2 Blog</p>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($error_message)) { ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-circle me-2"></i> <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php } ?>

                <form method="POST">
                    <div class="mb-3">
                        <label for="email" class="form-label fw-500">Email</label>
                        <input type="email" class="form-control form-control-lg" 
                               id="email" name="email" placeholder="admin@smksatyapraja2.sch.id" required>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label fw-500">Password</label>
                        <input type="password" class="form-control form-control-lg" 
                               id="password" name="password" placeholder="Masukkan password..." required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">
                        <i class="bi bi-box-arrow-in-right me-2"></i> Login
                    </button>
                </form>

                <div class="mt-3 p-3 bg-light rounded">
                    <small class="text-muted d-block mb-2">
                        <strong>Demo Credentials:</strong>
                    </small>
                    <small class="text-muted d-block">
                        Email: admin@smksatyapraja2.sch.id<br>
                        Password: admin123
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>