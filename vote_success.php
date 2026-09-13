<?php
/**
 * E-Pilketos v2.0 - Voting Berhasil / Tanda Terima
 * SMK SIG (Sekolah Menengah Kejuruan SIG)
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/functions.php';

$receipt = $_SESSION['vote_receipt'] ?? null;

// Clear the receipt from session so refreshing or coming back won't allow re-actions
if ($receipt) {
    unset($_SESSION['vote_receipt']);
}

$pageTitle = 'Voting Berhasil - E-Pilketos v2.0 SMK SIG';
require_once __DIR__ . '/templates/header.php';
?>

<div class="row justify-content-center my-4">
    <div class="col-md-7 col-lg-6">
        <div class="card-inst text-center p-4 p-md-5 border-success-subtle shadow-sm">
            <div class="mb-3">
                <div class="rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center" style="width: 72px; height: 72px;">
                    <i class="bi bi-check2-circle" style="font-size: 42px;"></i>
                </div>
            </div>

            <h1 class="h4 fw-bold text-dark mb-2">Suara Berhasil Disimpan!</h1>
            <p class="text-secondary mb-4" style="line-height: 1.6;">
                Terima kasih telah berpartisipasi dalam <strong>Pemilihan Ketua OSIS SMK SIG</strong>. Hak suara Anda telah dicatat secara sah dan rahasia ke dalam sistem.
            </p>

            <?php if ($receipt): ?>
                <div class="text-start bg-light p-3 rounded border border-light-subtle mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                        <span class="small text-muted fw-semibold">BUKTI PARTISIPASI DIGITAL</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">TERVERIFIKASI</span>
                    </div>
                    <div class="small mb-1 d-flex justify-content-between">
                        <span class="text-muted">Nama Pemilih:</span>
                        <span class="fw-bold text-dark"><?= e($receipt['student_name'] ?? $receipt['name'] ?? '-') ?></span>
                    </div>
                    <?php if (($receipt['voter_type'] ?? 'student') === 'employee'): ?>
                        <div class="small mb-1 d-flex justify-content-between">
                            <span class="text-muted">Kategori / NIP:</span>
                            <span class="fw-semibold text-dark"><?= ($receipt['type'] ?? '') === 'karyawan' ? 'Tenaga Kependidikan (Karyawan)' : 'Tenaga Pendidik (Guru)' ?> &bull; NIP <?= e($receipt['nip'] ?? '-') ?></span>
                        </div>
                    <?php else: ?>
                        <div class="small mb-1 d-flex justify-content-between">
                            <span class="text-muted">Kelas / No. Absen:</span>
                            <span class="fw-semibold text-dark"><?= e($receipt['class_name'] ?? '-') ?> / Absen <?= sprintf('%02d', (int)($receipt['attendance_number'] ?? 0)) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="small mb-1 d-flex justify-content-between">
                        <span class="text-muted">Waktu Partisipasi:</span>
                        <span class="text-secondary"><?= e(format_date_id($receipt['participated_at'] ?? null)) ?></span>
                    </div>
                    <div class="small d-flex justify-content-between">
                        <span class="text-muted">Status Suara:</span>
                        <span class="text-success fw-bold">Terkunci &amp; Rahasia</span>
                    </div>
                </div>
                <p class="small text-muted mb-4"><i class="bi bi-incognito me-1"></i>Bukti ini hanya menyatakan partisipasi. Pilihan kandidat tidak dicetak, disimpan, atau ditampilkan pada tanda terima.</p>
            <?php endif; ?>

            <div class="alert alert-secondary py-2 px-3 small text-muted mb-4">
                <i class="bi bi-shield-lock-fill me-1"></i>
                Akun Anda telah ditandai sebagai <strong>Sudah Memilih</strong> dan tidak dapat memberikan suara kembali.
            </div>

            <div class="d-flex justify-content-center gap-2 flex-wrap">
                <a href="face-poc/index.php" class="btn btn-sig px-4 py-2">
                    <i class="bi bi-camera-fill me-1"></i> Selesai &amp; Pemilih Berikutnya
                </a>
                <a href="index.php" class="btn btn-outline-secondary px-3 py-2">
                    <i class="bi bi-house-door me-1"></i> Beranda
                </a>
            </div>

            <div class="text-center mt-3 text-muted small">
                Bilik suara akan otomatis kembali ke kamera dalam <strong id="countdownSec" class="text-danger">10</strong> detik...
            </div>

            <script>
                (function() {
                    let sec = 10;
                    const el = document.getElementById('countdownSec');
                    const timer = setInterval(() => {
                        sec--;
                        if (el) el.textContent = sec;
                        if (sec <= 0) {
                            clearInterval(timer);
                            window.location.href = 'face-poc/index.php';
                        }
                    }, 1000);
                })();
            </script>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
