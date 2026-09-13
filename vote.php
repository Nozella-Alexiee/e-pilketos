<?php
/**
 * E-Pilketos v2.0 - Bilik Suara Siswa (Surat Suara Digital)
 * SMK SIG (Sekolah Menengah Kejuruan SIG)
 *
 * Alur: Masuk lewat Kamera Face Recognition (face-poc/index.php) -> Wajah Teridentifikasi -> Surat Suara Digital
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/functions.php';

$pdo = getDb();
$settings = get_election_settings($pdo);

// 1. Cek status pemilihan
if ($settings['election_status'] !== 'OPEN') {
    set_flash('warning', 'Pemilihan saat ini ' . ($settings['election_status'] === 'DRAFT' ? 'belum dibuka (Tahap Persiapan).' : 'telah resmi ditutup.'));
    header('Location: index.php');
    exit;
}

// 2. Batal / Ganti pemilih -> Kembali ke bilik kamera Face Recognition
if (isset($_GET['action']) && $_GET['action'] === 'reset_voter') {
    unset($_SESSION['voter_student_id'], $_SESSION['voter_id'], $_SESSION['voter_type'], $_SESSION['voter_verified_at']);
    header('Location: face-poc/index.php');
    exit;
}

// 3. Wajib teridentifikasi lewat Kamera Face Recognition terlebih dahulu
$voterType = $_SESSION['voter_type'] ?? 'student';
$voterId = (int)($_SESSION['voter_id'] ?? $_SESSION['voter_student_id'] ?? 0);

if ($voterId <= 0) {
    set_flash('info', 'Silakan hadap ke kamera bilik suara untuk identifikasi wajah terlebih dahulu.');
    header('Location: face-poc/index.php');
    exit;
}

// 4. Ambil data pemilih terverifikasi dari database
if ($voterType === 'employee') {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$voterId]);
    $currentVoter = $stmt->fetch();

    if (!$currentVoter || (int)$currentVoter['has_voted'] === 1) {
        unset($_SESSION['voter_student_id'], $_SESSION['voter_id'], $_SESSION['voter_type'], $_SESSION['voter_verified_at']);
        set_flash('danger', 'Hak suara pemilih ini sudah digunakan. Setiap pemilih hanya memiliki 1 hak suara.');
        header('Location: face-poc/index.php');
        exit;
    }
} else {
    $stmt = $pdo->prepare("SELECT s.*, c.name as class_name FROM students s JOIN classes c ON s.class_id = c.id WHERE s.id = ?");
    $stmt->execute([$voterId]);
    $currentVoter = $stmt->fetch();

    if (!$currentVoter || (int)$currentVoter['has_voted'] === 1) {
        unset($_SESSION['voter_student_id'], $_SESSION['voter_id'], $_SESSION['voter_type'], $_SESSION['voter_verified_at']);
        set_flash('danger', 'Siswa tersebut sudah melakukan pemilihan. Setiap siswa hanya memiliki 1 hak suara.');
        header('Location: face-poc/index.php');
        exit;
    }
}

// 5. Ambil data kandidat aktif
$stmt = $pdo->query("SELECT * FROM candidates WHERE is_active = 1 ORDER BY number ASC");
$candidates = $stmt->fetchAll();

$pageTitle = 'Surat Suara Pemilihan - E-Pilketos v2.0 SMK SIG';
require_once __DIR__ . '/templates/header.php';
?>

<!-- Voter Identity Banner -->
<div class="card-inst mb-4 bg-white <?= $voterType === 'employee' ? 'border-primary-subtle' : 'border-danger-subtle' ?>">
    <div class="card-inst-body py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <?php if ($voterType === 'employee'): ?>
                <div class="rounded <?= $currentVoter['type'] === 'karyawan' ? 'bg-info' : 'bg-primary' ?> text-white fw-bold text-center px-3 py-1.5" style="min-width: 65px;">
                    <div style="font-size: 10px; text-transform: uppercase;">DPT</div>
                    <div class="fs-6 lh-1"><?= strtoupper(e($currentVoter['type'])) ?></div>
                </div>
                <div>
                    <div class="small text-muted text-uppercase fw-semibold" style="letter-spacing: 0.04em;">Pemilih Terverifikasi (Guru &amp; Karyawan)</div>
                    <div class="fs-5 fw-bold text-dark"><?= e($currentVoter['name']) ?></div>
                    <div class="text-muted small">NIP: <strong class="text-dark"><?= e($currentVoter['nip'] ?? '-') ?></strong> &bull; Kategori: <strong class="text-dark"><?= $currentVoter['type'] === 'karyawan' ? 'Tenaga Kependidikan (Karyawan)' : 'Tenaga Pendidik (Guru)' ?></strong></div>
                </div>
            <?php else: ?>
                <div class="rounded bg-danger text-white fw-bold text-center px-3 py-1.5" style="min-width: 55px;">
                    <div style="font-size: 10px; text-transform: uppercase;">Absen</div>
                    <div class="fs-4 lh-1"><?= sprintf('%02d', (int)$currentVoter['attendance_number']) ?></div>
                </div>
                <div>
                    <div class="small text-muted text-uppercase fw-semibold" style="letter-spacing: 0.04em;">Pemilih Terverifikasi (Face Recognition)</div>
                    <div class="fs-5 fw-bold text-dark"><?= e($currentVoter['name']) ?></div>
                    <div class="text-muted small">Kelas: <strong class="text-dark"><?= e($currentVoter['class_name']) ?></strong></div>
                </div>
            <?php endif; ?>
        </div>
        <div>
            <a href="vote.php?action=reset_voter" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Kembali ke bilik kamera untuk ganti pemilih?');">
                <i class="bi bi-camera me-1"></i> Kembali ke Kamera
            </a>
        </div>
    </div>
</div>

<div class="mb-3 d-flex align-items-center justify-content-between">
    <div>
        <h2 class="h5 fw-bold text-dark mb-1">Surat Suara Calon Ketua OSIS</h2>
        <div class="text-muted small">Pelajari visi &amp; misi lengkap kandidat di bawah ini, lalu tentukan pilihan Anda.</div>
    </div>
    <span class="badge bg-danger text-white px-3 py-1.5 fw-semibold">1 Hak Suara</span>
</div>

<!-- Candidate Cards with Full Information -->
<div class="row g-4 mb-5">
    <?php if (empty($candidates)): ?>
        <div class="col-12 text-center py-5">
            <div class="card-inst p-5 bg-white">
                <i class="bi bi-people text-muted" style="font-size: 48px;"></i>
                <h3 class="h5 fw-bold text-dark mt-3 mb-2">Belum Ada Paslon Terdaftar</h3>
                <p class="text-secondary small mb-3">
                    Daftar kandidat ketua OSIS masih kosong (belum diinput oleh panitia). Silakan tambahkan kandidat resmi terlebih dahulu melalui Panel Administrator.
                </p>
                <a href="face-poc/index.php" class="btn btn-sig btn-sm px-3">
                    <i class="bi bi-camera me-1"></i> Kembali ke Bilik Kamera
                </a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($candidates as $cand): ?>
            <div class="col-lg-4 col-md-6">
                <div class="ballot-card h-100">
                    <div class="ballot-number-badge">
                        <span>Nomor <?= e($cand['number']) ?></span>
                        <span class="ballot-number-sub">Calon Ketua OSIS</span>
                    </div>

                    <div class="ballot-photo-wrap" style="height: 240px;">
                        <?php if (!empty($cand['photo']) && file_exists(__DIR__ . '/' . $cand['photo'])): ?>
                            <img src="<?= e($cand['photo']) ?>" alt="<?= e($cand['name']) ?>">
                        <?php else: ?>
                            <div class="d-flex flex-column align-items-center justify-content-center text-muted">
                                <i class="bi bi-person-fill" style="font-size: 64px;"></i>
                                <span class="small">Foto Resmi</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="ballot-info p-3 d-flex flex-column flex-grow-1">
                        <h3 class="ballot-name fs-5 fw-bold mb-1"><?= e($cand['name']) ?></h3>
                        <div class="ballot-class mb-3">
                            <span class="badge bg-secondary-subtle text-secondary border px-2 py-1">
                                <i class="bi bi-mortarboard-fill me-1"></i> Kelas <?= e($cand['class']) ?>
                            </span>
                        </div>

                        <!-- Visi Lengkap -->
                        <div class="mb-3">
                            <div class="ballot-section-title text-muted small fw-bold text-uppercase" style="letter-spacing: 0.05em;">
                                <i class="bi bi-eye-fill text-danger me-1"></i> Visi:
                            </div>
                            <div class="p-2.5 bg-light rounded border small text-dark" style="line-height: 1.55;">
                                <?= nl2br(e($cand['vision'])) ?>
                            </div>
                        </div>

                        <!-- Misi Lengkap -->
                        <div class="mb-4">
                            <div class="ballot-section-title text-muted small fw-bold text-uppercase" style="letter-spacing: 0.05em;">
                                <i class="bi bi-card-checklist text-danger me-1"></i> Misi:
                            </div>
                            <div class="p-2.5 bg-light rounded border small text-dark" style="line-height: 1.6;">
                                <?= nl2br(e($cand['mission'])) ?>
                            </div>
                        </div>

                        <!-- Action Button -->
                        <div class="mt-auto pt-2">
                            <button type="button" 
                                    class="btn-vote-candidate btn-trigger-vote w-100 py-2.5 fs-6"
                                    data-candidate-id="<?= (int)$cand['id'] ?>"
                                    data-candidate-number="<?= e($cand['number']) ?>"
                                    data-candidate-name="<?= e($cand['name']) ?>">
                                <i class="bi bi-check2-circle me-1"></i> PILIH PASLON <?= e($cand['number']) ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal Konfirmasi Pilihan -->
<div class="modal fade" id="modalKonfirmasiPilihan" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-danger text-white py-3">
                <h5 class="modal-title fs-6 fw-bold mb-0">
                    <i class="bi bi-shield-check me-1"></i> Konfirmasi Pilihan Suara
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Batal"></button>
            </div>
            <form action="vote_process.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="voter_type" value="<?= e($voterType) ?>">
                <input type="hidden" name="voter_id" value="<?= (int)$currentVoter['id'] ?>">
                <input type="hidden" name="student_id" value="<?= (int)$currentVoter['id'] ?>">
                <input type="hidden" name="candidate_id" id="inputCandidateId" value="">

                <div class="modal-body p-4 text-start">
                    <p class="mb-3 text-secondary">
                        Anda akan memberikan hak suara Anda untuk calon Ketua OSIS berikut:
                    </p>
                    
                    <div class="p-3 bg-light rounded border border-danger-subtle mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-danger text-white fw-bold fs-3 rounded px-3 py-2 text-center" id="confirmCandNumber">
                                01
                            </div>
                            <div>
                                <div class="small text-muted text-uppercase fw-semibold">Nama Pasangan Calon</div>
                                <div class="fs-5 fw-bold text-dark" id="confirmCandName">-</div>
                            </div>
                        </div>
                    </div>

                    <div class="small text-muted mb-3 bg-white p-2.5 rounded border">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Pemilih:</span>
                            <strong class="text-dark"><?= e($currentVoter['name']) ?></strong>
                        </div>
                        <?php if ($voterType === 'employee'): ?>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Kategori:</span>
                                <strong class="text-dark"><?= $currentVoter['type'] === 'karyawan' ? 'Karyawan' : 'Guru' ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>NIP:</span>
                                <strong class="text-dark"><?= e($currentVoter['nip'] ?? '-') ?></strong>
                            </div>
                        <?php else: ?>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Kelas:</span>
                                <strong class="text-dark"><?= e($currentVoter['class_name']) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Nomor Absen:</span>
                                <strong class="text-dark"><?= sprintf('%02d', (int)$currentVoter['attendance_number']) ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="alert alert-warning py-2 px-3 small mb-0 border-warning-subtle">
                        <i class="bi bi-exclamation-triangle-fill me-1 text-warning"></i> 
                        <strong>Apakah Anda yakin dengan pilihan ini?</strong> Pilihan tidak dapat diubah setelah disimpan.
                    </div>
                </div>

                <div class="modal-footer bg-light border-top py-2.5 d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">
                        <i class="bi bi-arrow-left me-1"></i> Batal / Cek Lagi
                    </button>
                    <button type="submit" class="btn btn-danger btn-sm px-4 fw-bold">
                        <i class="bi bi-check-lg me-1"></i> Ya, Saya Yakin
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
