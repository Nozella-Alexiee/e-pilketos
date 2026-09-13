<?php
/**
 * E-Pilketos v2.0 - Admin Dashboard
 * SMK SIG (Sekolah Menengah Kejuruan SIG)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

require_admin();
$pdo = getDb();
$settings = get_election_settings($pdo);

// Handle quick election status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        if ($_POST['action'] === 'update_status') {
            $newStatus = $_POST['status'] ?? 'DRAFT';
            if (in_array($newStatus, ['DRAFT', 'OPEN', 'CLOSED'])) {
                $now = date('Y-m-d H:i:s');
                $stmt = $pdo->prepare("UPDATE settings SET election_status = ?, updated_at = ? WHERE id = 1");
                $stmt->execute([$newStatus, $now]);
                set_flash('success', 'Status pemilihan berhasil diubah menjadi: ' . $newStatus);
            }
        } elseif ($_POST['action'] === 'toggle_public_results') {
            $show = (int)($_POST['show_results'] ?? 0);
            $now = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare("UPDATE settings SET show_results_to_students = ?, updated_at = ? WHERE id = 1");
            $stmt->execute([$show, $now]);
            set_flash('success', 'Visibilitas hasil ke siswa berhasil diperbarui.');
        }
    } else {
        set_flash('danger', 'Token keamanan CSRF tidak valid.');
    }
    header('Location: index.php');
    exit;
}

// Analytics Calculation
$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalEmployees = (int)$pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
$totalDPT = $totalStudents + $totalEmployees;

$totalVotes = (int)$pdo->query("SELECT COUNT(*) FROM votes")->fetchColumn();
$unvotedDPT = max(0, $totalDPT - $totalVotes);
$turnoutPct = ($totalDPT > 0) ? round(($totalVotes / $totalDPT) * 100, 1) : 0;
$totalCandidates = (int)$pdo->query("SELECT COUNT(*) FROM candidates WHERE is_active = 1")->fetchColumn();
$totalClasses = (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE is_active = 1")->fetchColumn();

$employeeVotedCount = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE has_voted = 1")->fetchColumn();
$employeeTurnoutPct = ($totalEmployees > 0) ? round(($employeeVotedCount / $totalEmployees) * 100, 1) : 0;

// Candidate votes tally
$stmt = $pdo->query("SELECT c.*, COUNT(v.id) as vote_count 
    FROM candidates c 
    LEFT JOIN votes v ON c.id = v.candidate_id 
    WHERE c.is_active = 1 
    GROUP BY c.id 
    ORDER BY c.number ASC");
$candidates = $stmt->fetchAll();

// Participation per Grade
$stmt = $pdo->query("SELECT c.grade, 
    COUNT(s.id) as total_students,
    SUM(CASE WHEN s.has_voted = 1 THEN 1 ELSE 0 END) as voted_count
    FROM classes c
    LEFT JOIN students s ON c.id = s.class_id
    WHERE c.is_active = 1
    GROUP BY c.grade
    ORDER BY c.grade ASC");
$gradeStats = $stmt->fetchAll();

$pageTitle = 'Dashboard Admin - E-Pilketos v2.0 SMK SIG';
require_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 border-bottom pb-3">
    <div>
        <h1 class="h4 fw-bold text-dark mb-1">Ikhtisar Pemilihan (Dashboard)</h1>
        <div class="text-muted small">
            Periode Akademik: <strong><?= e($settings['election_period']) ?></strong> &bull; Pemantauan langsung surat suara digital.
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <!-- Quick Status Switcher -->
        <form action="index.php" method="POST" class="d-inline-flex align-items-center gap-2">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="update_status">
            <label class="small text-muted fw-semibold">Ubah Status:</label>
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="DRAFT" <?= ($settings['election_status'] === 'DRAFT') ? 'selected' : '' ?>>DRAFT (Persiapan)</option>
                <option value="OPEN" <?= ($settings['election_status'] === 'OPEN') ? 'selected' : '' ?>>OPEN (Buka Pemilihan)</option>
                <option value="CLOSED" <?= ($settings['election_status'] === 'CLOSED') ? 'selected' : '' ?>>CLOSED (Tutup Pemilihan)</option>
            </select>
        </form>

        <a href="index.php" class="btn btn-sm btn-sig-secondary" title="Perbarui Statistik">
            <i class="bi bi-arrow-clockwise"></i>
        </a>
    </div>
</div>

<!-- Primary Metrics Cards -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="card-inst p-3">
            <div class="d-flex align-items-center justify-content-between text-muted mb-1">
                <span class="small text-uppercase fw-bold" style="font-size: 11px;">Total DPT Pemilih</span>
                <i class="bi bi-people fs-5 text-secondary"></i>
            </div>
            <div class="fs-3 fw-bold text-dark"><?= number_format($totalDPT) ?></div>
            <div class="text-muted small" style="font-size: 11.5px;"><?= number_format($totalStudents) ?> Siswa &bull; <?= number_format($totalEmployees) ?> Guru/Karyawan</div>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="card-inst p-3">
            <div class="d-flex align-items-center justify-content-between text-muted mb-1">
                <span class="small text-uppercase fw-bold" style="font-size: 11px;">Suara Masuk</span>
                <i class="bi bi-check2-circle fs-5 text-success"></i>
            </div>
            <div class="fs-3 fw-bold text-success"><?= number_format($totalVotes) ?></div>
            <div class="text-success small fw-semibold" style="font-size: 11.5px;"><?= $turnoutPct ?>% Tingkat Partisipasi</div>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="card-inst p-3">
            <div class="d-flex align-items-center justify-content-between text-muted mb-1">
                <span class="small text-uppercase fw-bold" style="font-size: 11px;">Belum Memilih</span>
                <i class="bi bi-clock-history fs-5 text-danger"></i>
            </div>
            <div class="fs-3 fw-bold text-danger"><?= number_format($unvotedDPT) ?></div>
            <div class="text-muted small" style="font-size: 11.5px;"><?= (100 - $turnoutPct) ?>% Dari total pemilih</div>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="card-inst p-3">
            <div class="d-flex align-items-center justify-content-between text-muted mb-1">
                <span class="small text-uppercase fw-bold" style="font-size: 11px;">Kandidat Terdaftar</span>
                <i class="bi bi-person-badge fs-5 text-secondary"></i>
            </div>
            <div class="fs-3 fw-bold text-dark"><?= $totalCandidates ?></div>
            <div class="text-muted small" style="font-size: 11.5px;">Pasangan Calon Aktif</div>
        </div>
    </div>
</div>

<!-- Perolehan Suara Kandidat Real-time -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card-inst h-100">
            <div class="card-inst-header">
                <div>
                    <h2 class="card-inst-title fs-6">Perolehan Suara Kandidat</h2>
                    <div class="text-muted small">Rekapitulasi resmi perolehan suara ketua OSIS</div>
                </div>
                <a href="results.php" class="btn btn-sm btn-sig-outline py-1 px-2.5" style="font-size: 12px;">
                    <i class="bi bi-printer me-1"></i> Cetak Berita Acara
                </a>
            </div>
            <div class="card-inst-body p-4">
                <?php if (empty($candidates)): ?>
                    <div class="alert alert-warning mb-0">Belum ada kandidat aktif yang terdaftar.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table-inst mb-4">
                            <thead>
                                <tr>
                                    <th style="width: 70px;">No.</th>
                                    <th>Kandidat</th>
                                    <th>Kelas</th>
                                    <th class="text-end" style="width: 120px;">Jumlah Suara</th>
                                    <th class="text-end" style="width: 100px;">Persentase</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($candidates as $cand): ?>
                                    <?php 
                                        $cnt = (int)$cand['vote_count'];
                                        $pct = ($totalVotes > 0) ? round(($cnt / $totalVotes) * 100, 1) : 0;
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-danger text-white px-2 py-1 fs-6">
                                                <?= e($cand['number']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if (!empty($cand['photo']) && file_exists(__DIR__ . '/../' . $cand['photo'])): ?>
                                                    <img src="../<?= e($cand['photo']) ?>" class="rounded border" width="36" height="36" style="object-fit: cover;">
                                                <?php else: ?>
                                                    <div class="rounded bg-light border d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                                        <i class="bi bi-person text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <span class="fw-bold text-dark d-block"><?= e($cand['name']) ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= e($cand['class']) ?></td>
                                        <td class="text-end fw-bold fs-6 text-dark"><?= number_format($cnt) ?> suara</td>
                                        <td class="text-end">
                                            <span class="badge bg-light text-danger border fw-bold fs-6 px-2"><?= $pct ?>%</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="5" class="p-0 border-0">
                                            <div class="progress" style="height: 6px; border-radius: 0;">
                                                <div class="progress-bar bg-danger" role="progressbar" style="width: <?= $pct ?>%;" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Settings & Grade Turnout -->
    <div class="col-lg-4">
        <!-- Quick Settings Card -->
        <div class="card-inst mb-4">
            <div class="card-inst-header">
                <h2 class="card-inst-title fs-6">Kontrol Pemilihan</h2>
            </div>
            <div class="card-inst-body p-3">
                <div class="mb-3">
                    <span class="small text-muted d-block mb-1">Status Sistem:</span>
                    <?php if ($settings['election_status'] === 'OPEN'): ?>
                        <div class="p-2 rounded bg-success-subtle text-success border border-success-subtle small fw-semibold">
                            <i class="bi bi-check-circle-fill me-1"></i> Pemilihan SEDANG DIBUKA. Siswa dapat memberikan suara.
                        </div>
                    <?php elseif ($settings['election_status'] === 'DRAFT'): ?>
                        <div class="p-2 rounded bg-warning-subtle text-warning border border-warning-subtle small fw-semibold">
                            <i class="bi bi-exclamation-circle-fill me-1"></i> Mode DRAFT. Surat suara belum dapat diakses siswa.
                        </div>
                    <?php else: ?>
                        <div class="p-2 rounded bg-danger-subtle text-danger border border-danger-subtle small fw-semibold">
                            <i class="bi bi-lock-fill me-1"></i> Pemilihan DITUTUP. Proses voting dinonaktifkan.
                        </div>
                    <?php endif; ?>
                </div>

                <form action="index.php" method="POST" class="border-top pt-3">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="toggle_public_results">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="switchShowResults" name="show_results" value="1" <?= (!empty($settings['show_results_to_students'])) ? 'checked' : '' ?> onchange="this.form.submit()">
                        <label class="form-check-label small fw-semibold" for="switchShowResults">Tampilkan Hasil ke Siswa</label>
                    </div>
                    <div class="text-muted" style="font-size: 11px;">
                        Jika dimatikan, halaman hasil publik akan disembunyikan.
                    </div>
                </form>
            </div>
        </div>

        <!-- Grade Turnout Card -->
        <div class="card-inst">
            <div class="card-inst-header">
                <h2 class="card-inst-title fs-6">Partisipasi Berdasarkan Tingkat</h2>
            </div>
            <div class="card-inst-body p-3">
                <?php foreach ($gradeStats as $gs): ?>
                    <?php 
                        $gTotal = (int)$gs['total_students'];
                        $gVoted = (int)$gs['voted_count'];
                        $gPct = ($gTotal > 0) ? round(($gVoted / $gTotal) * 100, 1) : 0;
                    ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="fw-bold">Kelas <?= e($gs['grade']) ?></span>
                            <span class="text-muted"><?= $gVoted ?> / <?= $gTotal ?> (<?= $gPct ?>%)</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= $gPct ?>%;" aria-valuenow="<?= $gPct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if ($totalEmployees > 0): ?>
                    <div class="mb-1 border-top pt-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="fw-bold text-primary">Guru &amp; Karyawan</span>
                            <span class="text-muted"><?= $employeeVotedCount ?> / <?= $totalEmployees ?> (<?= $employeeTurnoutPct ?>%)</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $employeeTurnoutPct ?>%;" aria-valuenow="<?= $employeeTurnoutPct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/admin_footer.php'; ?>
