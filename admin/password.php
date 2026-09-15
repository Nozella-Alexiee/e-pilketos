<?php
/**
 * E-Pilketos v2.0 - Ganti Kata Sandi Administrator
 * SMK SIG (Sekolah Menengah Kejuruan SIG)
 * Mengubah kata sandi secara dinamis di basis data tanpa hardcode.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

require_admin();
$pdo = getDb();

$adminId = $_SESSION['admin_id'] ?? 1;

// Ambil data user dari database
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$adminId]);
$user = $stmt->fetch();

if (!$user) {
    set_flash('danger', 'Data akun administrator tidak ditemukan.');
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Sesi keamanan formulir tidak valid. Silakan coba lagi.');
        header('Location: password.php');
        exit;
    }

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        set_flash('danger', 'Seluruh kolom kata sandi wajib diisi.');
    } elseif (!password_verify($currentPassword, $user['password_hash'])) {
        set_flash('danger', 'Kata sandi saat ini salah. Periksa kembali kata sandi lama Anda.');
    } elseif (strlen($newPassword) < 6) {
        set_flash('danger', 'Kata sandi baru minimal 6 karakter demi keamanan sistem.');
    } elseif ($newPassword !== $confirmPassword) {
        set_flash('danger', 'Konfirmasi kata sandi baru tidak cocok.');
    } else {
        // Hash kata sandi baru dengan Bcrypt standar industri
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        
        // Simpan langsung ke database tabel users
        $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $updateStmt->execute([$newHash, $adminId]);

        log_activity($pdo, 'CHANGE_PASSWORD', "Mengubah kata sandi akun sendiri (username: '{$user['username']}', role: '{$user['role']}')");

        // Refresh data di memori
        $user['password_hash'] = $newHash;

        set_flash('success', 'Kata sandi administrator berhasil diubah di basis data! Mulai sekarang, gunakan kata sandi baru ini untuk login.');
    }

    header('Location: password.php');
    exit;
}

$pageTitle = 'Ganti Password - E-Pilketos v2.0 SMK SIG';
require_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 border-bottom pb-3">
    <div>
        <h1 class="h4 fw-bold text-dark mb-1">Ganti Kata Sandi Administrator</h1>
        <div class="text-muted small">Ubah kata sandi akun panitia secara dinamis dan aman tersimpan di database</div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card-inst shadow-sm">
            <div class="card-inst-header bg-white py-3">
                <h2 class="card-inst-title fs-6">
                    <i class="bi bi-shield-lock me-1 text-danger"></i> Formulir Perubahan Kata Sandi
                </h2>
            </div>

            <div class="card-inst-body p-4">
                <form action="password.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Nama Pengguna (Username)</label>
                        <input type="text" class="form-control bg-light" value="<?= e($user['username']) ?>" readonly>
                        <div class="form-text" style="font-size: 11.5px;">Username administrator tidak dapat diubah.</div>
                    </div>

                    <div class="mb-3">
                        <label for="current_password" class="form-label small fw-bold text-secondary">
                            Kata Sandi Saat Ini <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-key"></i></span>
                            <input type="password" name="current_password" id="current_password" class="form-control" placeholder="Masukkan kata sandi lama" required autofocus>
                        </div>
                    </div>

                    <hr class="my-3">

                    <div class="mb-3">
                        <label for="new_password" class="form-label small fw-bold text-secondary">
                            Kata Sandi Baru <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-lock"></i></span>
                            <input type="password" name="new_password" id="new_password" class="form-control" placeholder="Minimal 6 karakter" required minlength="6">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label small fw-bold text-secondary">
                            Ulangi Kata Sandi Baru <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-check2-circle"></i></span>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Ketik ulang kata sandi baru" required minlength="6">
                        </div>
                    </div>

                    <div class="p-3 bg-light rounded border small text-muted mb-4">
                        <div class="fw-bold mb-1 text-dark"><i class="bi bi-info-circle me-1 text-primary"></i> Keamanan Sandi:</div>
                        Kata sandi baru akan langsung dienkripsi dengan algoritma Bcrypt dan disimpan ke tabel <code>users</code> pada basis data. Tidak ada kata sandi yang disimpan dalam bentuk teks polos atau hardcode.
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="index.php" class="btn btn-sm btn-sig-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Kembali ke Dashboard
                        </a>
                        <button type="submit" class="btn btn-sm btn-danger px-4 py-2 fw-bold" style="background-color: var(--maroon-800); border-color: var(--maroon-900);">
                            <i class="bi bi-save me-1"></i> Simpan Kata Sandi Baru
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/admin_footer.php'; ?>
