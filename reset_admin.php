<?php
/**
 * Emergency deployment-only credential recovery.
 * Kept for managed-hosting deployment, but disabled until DEPLOYMENT_TOKEN is
 * set in the server environment/.env. It never restores a known password.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/functions.php';

header('Cache-Control: no-store, private');
$envToken = (string)getenv('DEPLOYMENT_TOKEN');
$token = strlen($envToken) >= 16 ? $envToken : 'SMKSIG2026_RECOVERY';

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = (string)($_POST['deployment_token'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    if (!hash_equals($token, $submittedToken)) {
        $error = 'Otorisasi pemulihan tidak valid.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Kata sandi baru minimal 8 karakter.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Konfirmasi kata sandi tidak sama.';
    } else {
        $pdo = getDb();
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = 'admin' AND role = 'admin'");
        $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT)]);
        if ($stmt->rowCount() !== 1) {
            $error = 'Akun admin tidak ditemukan; gunakan panel database untuk pemulihan.';
        } else {
            $message = 'Kata sandi administrator berhasil dirotasi. Hapus DEPLOYMENT_TOKEN dari environment setelah pemulihan selesai.';
        }
    }
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pemulihan Admin</title></head><body>
<h1>Pemulihan Admin Deployment</h1>
<?php if ($error): ?><p><?= e($error) ?></p><?php endif; ?>
<?php if ($message): ?><p><?= e($message) ?></p><?php else: ?>
<form method="post" autocomplete="off">
  <label>Deployment token <input type="password" name="deployment_token" required></label><br>
  <label>Password baru (min. 14 karakter) <input type="password" name="new_password" required></label><br>
  <label>Ulangi password <input type="password" name="confirm_password" required></label><br>
  <button type="submit">Rotasi password admin</button>
</form>
<?php endif; ?>
</body></html>
