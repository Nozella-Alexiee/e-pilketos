<?php
/**
 * E-Pilketos v2.0 - Administrator Login
 * SMK SIG (Sekolah Menengah Kejuruan SIG)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

$pdo = getDb();
$settings = get_election_settings($pdo);

// If already logged in, redirect to dashboard
if (is_admin()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Sesi form telah kedaluwarsa. Silakan muat ulang halaman.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        $attempt = $_SESSION['admin_login_attempt'] ?? ['count' => 0, 'last' => 0];
        if ((int)$attempt['count'] >= 5 && (time() - (int)$attempt['last']) < 300) {
            $error = 'Terlalu banyak percobaan masuk. Silakan tunggu 5 menit sebelum mencoba kembali.';
        } elseif (empty($username) || $password === '') {
            $error = 'Harap isi nama pengguna (username) dan kata sandi.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'admin' LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_name'] = $user['name'];
                $_SESSION['admin_role'] = $user['role'];
                $_SESSION['admin_last_activity'] = time();
                unset($_SESSION['admin_login_attempt']);

                set_flash('success', 'Selamat datang, ' . $user['name'] . '! Anda berhasil masuk ke panel admin.');
                header('Location: index.php');
                exit;
            } else {
                $_SESSION['admin_login_attempt'] = [
                    'count' => (int)$attempt['count'] + 1,
                    'last' => time(),
                ];
                $error = 'Kombinasi nama pengguna atau kata sandi tidak cocok.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Administrator - E-Pilketos v2.0 SMK SIG</title>
    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons (Local first + CDN fallback) -->
    <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/logo-smk-sig.png">
    <style>
        body {
            background: linear-gradient(180deg, #511524 0%, #3c101d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card-wrap {
            width: 100%;
            max-width: 440px;
        }
        .login-card {
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            overflow: hidden;
        }
        .login-header {
            background: #ffffff;
            padding: 30px 24px 20px;
            text-align: center;
            border-bottom: 1px solid #f0f0f0;
        }
        .login-school-logo {
            width: 110px;
            height: auto;
            object-fit: contain;
            margin-bottom: 14px;
        }
    </style>
</head>
<body>

<div class="login-card-wrap">
    <div class="login-card">
        <!-- Logo SMK SIG prominently placed on Login Landing -->
        <div class="login-header">
            <img src="../assets/images/logo-smk-sig.png" alt="Logo SMK SIG" class="login-school-logo">
            <h1 class="fs-5 fw-bold text-dark mb-1">E-Pilketos v2.0</h1>
            <div class="text-danger fw-semibold small">SMK SIG (Sekolah Menengah Kejuruan SIG)</div>
            <div class="text-muted" style="font-size: 11.5px;">Portal Administrasi Pemilihan Ketua OSIS</div>
        </div>

        <div class="p-4">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 px-3 small mb-3">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <div class="mb-3">
                    <label for="username" class="form-label small fw-bold text-secondary">Nama Pengguna (Username)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-person"></i></span>
                        <input type="text" name="username" id="username" class="form-control border-start-0" placeholder="Masukkan username" required autofocus value="<?= e($_POST['username'] ?? '') ?>">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label small fw-bold text-secondary">Kata Sandi (Password)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" id="password" class="form-control border-start-0" placeholder="Masukkan password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-danger w-100 py-2 fw-bold" style="background-color: var(--maroon-800); border-color: var(--maroon-900);">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Masuk ke Sistem
                </button>
            </form>

            <div class="mt-4 pt-3 border-top text-center">
                <a href="../index.php" class="text-decoration-none small text-muted">
                    <i class="bi bi-arrow-left me-1"></i> Kembali ke Halaman Pemilih
                </a>
            </div>
        </div>

        <div class="bg-light p-3 border-top text-center text-muted" style="font-size: 11px;">
            Semen Indonesia Foundation &bull; SMK SIG &bull; E-Pilketos v2.0
        </div>
    </div>
</div>

</body>
</html>
