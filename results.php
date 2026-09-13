<?php
/**
 * E-Pilketos v2.0 - Hasil Suara Publik (Siswa & Umum)
 * SMK SIG (Sekolah Menengah Kejuruan SIG)
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/functions.php';

$pdo = getDb();
$settings = get_election_settings($pdo);

$pageTitle = 'Hasil Perolehan Suara - E-Pilketos v2.0 SMK SIG';
require_once __DIR__ . '/templates/header.php';

// Check if results are allowed to be viewed by students
if (empty($settings['show_results_to_students'])) {
    ?>
    <div class="row justify-content-center my-5">
        <div class="col-md-6 text-center">
            <div class="card-inst p-5">
                <i class="bi bi-eye-slash text-muted" style="font-size: 48px;"></i>
                <h2 class="h5 fw-bold text-dark mt-3 mb-2">Hasil Pemilihan Disembunyikan</h2>
                <p class="text-secondary small mb-4">
                    Panitia pemilihan SMK SIG mengatur agar perolehan suara saat ini tidak ditampilkan kepada umum hingga batas waktu yang ditentukan.
                </p>
                <a href="index.php" class="btn btn-sig btn-sm px-3">
                    <i class="bi bi-arrow-left me-1"></i> Kembali ke Beranda
                </a>
            </div>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/templates/footer.php';
    exit;
}

// Calculate Election Analytics
$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalEmployees = (int)$pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
$totalDPT = $totalStudents + $totalEmployees;

$totalVotes = (int)$pdo->query("SELECT COUNT(*) FROM votes")->fetchColumn();
$unvotedTotal = max(0, $totalDPT - $totalVotes);
$turnoutPct = ($totalDPT > 0) ? round(($totalVotes / $totalDPT) * 100, 1) : 0;

// Calculate votes per candidate
$stmt = $pdo->query("SELECT c.*, COUNT(v.id) as vote_count 
    FROM candidates c 
    LEFT JOIN votes v ON c.id = v.candidate_id 
    WHERE c.is_active = 1 
    GROUP BY c.id 
    ORDER BY c.number ASC");
$candidateResults = $stmt->fetchAll();
?>

<div class="mb-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 border-bottom pb-2">
        <div>
            <h1 class="h4 fw-bold text-dark mb-1">Hasil Perolehan Suara Real-Time</h1>
            <div class="text-muted small">
                Pemilihan Ketua OSIS SMK SIG Periode <?= e($settings['election_period']) ?> &bull; Data dihitung langsung dari basis data sistem.
            </div>
        </div>
        <div>
            <a href="results.php" class="btn btn-sm btn-sig-secondary">
                <i class="bi bi-arrow-clockwise me-1"></i> Perbarui Data
            </a>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="card-inst p-3">
                <div class="text-muted small fw-semibold text-uppercase" style="font-size: 11px;">Total DPT (Pemilih)</div>
                <div class="fs-3 fw-bold text-dark"><?= number_format($totalDPT) ?></div>
                <div class="text-muted" style="font-size: 11px;"><?= number_format($totalStudents) ?> Siswa &bull; <?= number_format($totalEmployees) ?> Guru/Karyawan</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card-inst p-3">
                <div class="text-muted small fw-semibold text-uppercase" style="font-size: 11px;">Suara Masuk</div>
                <div class="fs-3 fw-bold text-success"><?= number_format($totalVotes) ?></div>
                <div class="text-success" style="font-size: 11px;"><?= $turnoutPct ?>% Partisipasi</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card-inst p-3">
                <div class="text-muted small fw-semibold text-uppercase" style="font-size: 11px;">Belum Memilih</div>
                <div class="fs-3 fw-bold text-danger"><?= number_format($unvotedTotal) ?></div>
                <div class="text-muted" style="font-size: 11px;"><?= (100 - $turnoutPct) ?>% Belum hadir</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card-inst p-3">
                <div class="text-muted small fw-semibold text-uppercase" style="font-size: 11px;">Status Pemilihan</div>
                <div class="fs-5 fw-bold text-dark mt-1">
                    <?php if ($settings['election_status'] === 'OPEN'): ?>
                        <span class="text-success"><i class="bi bi-circle-fill" style="font-size: 10px;"></i> Sedang Dibuka</span>
                    <?php elseif ($settings['election_status'] === 'DRAFT'): ?>
                        <span class="text-warning"><i class="bi bi-clock"></i> Persiapan</span>
                    <?php else: ?>
                        <span class="text-danger"><i class="bi bi-lock-fill"></i> Selesai/Ditutup</span>
                    <?php endif; ?>
                </div>
                <div class="text-muted" style="font-size: 11px;">Terverifikasi sistem</div>
            </div>
        </div>
    </div>

    <!-- Candidate Results Breakdown -->
    <div class="card-inst mb-4">
        <div class="card-inst-header">
            <h2 class="card-inst-title fs-6">Perolehan Suara Pasangan Calon</h2>
            <span class="badge bg-light text-secondary border">Perhitungan Langsung</span>
        </div>
        <div class="card-inst-body p-4">
            <div class="row g-4">
                <?php foreach ($candidateResults as $cand): ?>
                    <?php 
                        $candVotes = (int)$cand['vote_count'];
                        $candPct = ($totalVotes > 0) ? round(($candVotes / $totalVotes) * 100, 1) : 0;
                    ?>
                    <div class="col-md-4">
                        <div class="border rounded p-3 bg-white h-100 d-flex flex-column">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <span class="badge bg-danger text-white fs-6 px-2.5 py-1">Nomor <?= e($cand['number']) ?></span>
                                <span class="fs-5 fw-bold text-dark"><?= number_format($candVotes) ?> <small class="text-muted fs-6 fw-normal">suara</small></span>
                            </div>

                            <div class="text-center mb-3">
                                <div class="rounded border p-1 d-inline-block bg-light" style="width: 100px; height: 100px; overflow: hidden;">
                                    <?php if (!empty($cand['photo']) && file_exists(__DIR__ . '/' . $cand['photo'])): ?>
                                        <img src="<?= e($cand['photo']) ?>" class="w-100 h-100 object-fit-cover rounded" alt="<?= e($cand['name']) ?>">
                                    <?php else: ?>
                                        <i class="bi bi-person-fill text-muted fs-1"></i>
                                    <?php endif; ?>
                                </div>
                                <h3 class="fs-6 fw-bold text-dark mt-2 mb-0"><?= e($cand['name']) ?></h3>
                                <div class="text-muted small"><?= e($cand['class']) ?></div>
                            </div>

                            <div class="mt-auto">
                                <div class="d-flex justify-content-between small fw-bold mb-1">
                                    <span>Persentase Suara:</span>
                                    <span class="text-danger"><?= $candPct ?>%</span>
                                </div>
                                <div class="progress" style="height: 12px;">
                                    <div class="progress-bar bg-danger" role="progressbar" style="width: <?= $candPct ?>%;" aria-valuenow="<?= $candPct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
