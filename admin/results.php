<?php
/**
 * E-Pilketos v2.0 - Hasil & Berita Acara Resmi Pemilihan
 * SMK SIG (Sekolah Menengah Kejuruan SIG)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

require_admin();
$pdo = getDb();
$settings = get_election_settings($pdo);

// Handle saving default letter settings
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_letter_defaults') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $stmt = $pdo->prepare("UPDATE settings SET 
            letter_number = ?, 
            letter_datetime = ?, 
            letter_sign_date = ?, 
            letter_city = ?, 
            updated_at = ? 
            WHERE id = 1");
        $stmt->execute([
            trim($_POST['letter_number'] ?? ''),
            trim($_POST['letter_datetime'] ?? ''),
            trim($_POST['letter_sign_date'] ?? ''),
            trim($_POST['letter_city'] ?? 'Gresik'),
            date('Y-m-d H:i:s')
        ]);
        set_flash('success', 'Pengaturan tanggal, jam, dan nomor Berita Acara berhasil disimpan.');
        header('Location: results.php');
        exit;
    }
}

// Resolved Letter Dates & Information
$letterNumber = trim($_GET['letter_number'] ?? ($settings['letter_number'] ?: ('BA.' . date('Y') . '/PILKETOS/SMK-SIG/' . date('m'))));
$letterDatetime = trim($_GET['letter_datetime'] ?? ($settings['letter_datetime'] ?? ''));
$letterSignDate = trim($_GET['letter_sign_date'] ?? ($settings['letter_sign_date'] ?? ''));
$letterCity = trim($_GET['letter_city'] ?? ($settings['letter_city'] ?: 'Gresik'));

// Resolved Formatted Times
$execDatetime = !empty($letterDatetime) ? $letterDatetime : date('Y-m-d H:i:s');
$signDate = !empty($letterSignDate) ? $letterSignDate : date('Y-m-d');

// Statistics Calculation
$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalEmployees = (int)$pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
$totalDPT = $totalStudents + $totalEmployees;

$totalVotes = (int)$pdo->query("SELECT COUNT(*) FROM votes")->fetchColumn();
$unvotedTotal = max(0, $totalDPT - $totalVotes);
$turnoutPct = ($totalDPT > 0) ? round(($totalVotes / $totalDPT) * 100, 1) : 0;

$empVoted = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE has_voted = 1")->fetchColumn();
$empTurnoutPct = ($totalEmployees > 0) ? round(($empVoted / $totalEmployees) * 100, 1) : 0;

// Candidate results
$stmt = $pdo->query("SELECT c.*, COUNT(v.id) as vote_count 
    FROM candidates c 
    LEFT JOIN votes v ON c.id = v.candidate_id 
    GROUP BY c.id 
    ORDER BY vote_count DESC, c.number ASC");
$candidates = $stmt->fetchAll();

// Determine winner (highest vote count)
$winner = null;
if ($totalVotes > 0 && !empty($candidates) && (int)$candidates[0]['vote_count'] > 0) {
    $winner = $candidates[0];
}

// Breakdown by Class
$stmt = $pdo->query("SELECT c.name as class_name, c.grade, c.major,
    COUNT(s.id) as total_class_students,
    COALESCE(SUM(CASE WHEN s.has_voted = 1 THEN 1 ELSE 0 END), 0) as class_voted
    FROM classes c
    LEFT JOIN students s ON c.id = s.class_id
    GROUP BY c.id
    ORDER BY c.grade ASC, c.name ASC");
$classTurnout = $stmt->fetchAll();
$halfCount = !empty($classTurnout) ? (int)ceil(count($classTurnout) / 2) : 0;
$classCol1 = array_slice($classTurnout, 0, $halfCount);
$classCol2 = array_slice($classTurnout, $halfCount);

$pageTitle = 'Hasil & Berita Acara - E-Pilketos v2.0 SMK SIG';
require_once __DIR__ . '/../templates/admin_header.php';
?>

<!-- Print Styling for Official Document (Guaranteed 1-Page A4) -->
<style>
@page {
    size: A4 portrait;
    margin: 8mm 10mm 8mm 10mm;
}

@media print {
    *, *::before, *::after {
        box-sizing: border-box !important;
    }
    html, body {
        background: #ffffff !important;
        color: #000000 !important;
        font-size: 10px !important;
        line-height: 1.25 !important;
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .school-topbar, .navbar-institutional, .btn, .no-print, footer, .alert {
        display: none !important;
    }
    .print-sheet {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        page-break-after: avoid !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }
    .print-only {
        display: block !important;
    }
    
    /* Kop Surat Ringkas */
    .kop-surat {
        border-bottom: 2.5px double #000000 !important;
        padding-bottom: 5px !important;
        margin-bottom: 7px !important;
    }
    .kop-logo {
        height: 52px !important;
        width: auto !important;
    }
    .kop-title-1 {
        font-size: 11px !important;
        letter-spacing: 0.04em !important;
    }
    .kop-title-2 {
        font-size: 15px !important;
        color: #8B0000 !important;
    }
    .kop-title-3 {
        font-size: 9.5px !important;
    }
    .kop-title-4 {
        font-size: 10.5px !important;
    }

    /* Judul & Pengantar */
    .doc-title-block {
        margin-bottom: 5px !important;
    }
    .doc-title {
        font-size: 13.5px !important;
        margin-bottom: 1px !important;
    }
    .doc-number {
        font-size: 10px !important;
    }
    .doc-intro {
        font-size: 10px !important;
        line-height: 1.35 !important;
        margin-bottom: 6px !important;
    }

    /* Stat Cards Mini */
    .rekap-stat-grid {
        margin-bottom: 6px !important;
        --bs-gutter-x: 0.4rem !important;
        --bs-gutter-y: 0.4rem !important;
    }
    .rekap-stat-card {
        padding: 3px 4px !important;
        border: 1px solid #bbb !important;
        background: #f8f9fa !important;
    }
    .rekap-stat-card .stat-label {
        font-size: 8.5px !important;
        font-weight: 600 !important;
        margin-bottom: 0px !important;
    }
    .rekap-stat-card .stat-value {
        font-size: 14px !important;
        font-weight: 700 !important;
        line-height: 1.1 !important;
    }

    /* Tabel Paslon & Kelas */
    .doc-section-title {
        font-size: 10.5px !important;
        font-weight: 700 !important;
        margin-top: 5px !important;
        margin-bottom: 3px !important;
    }
    .table-rekap {
        margin-bottom: 5px !important;
        border-color: #333 !important;
    }
    .table-rekap th, 
    .table-rekap td {
        padding: 2px 4px !important;
        font-size: 9px !important;
        vertical-align: middle !important;
        line-height: 1.2 !important;
    }
    .table-rekap thead th {
        font-size: 9px !important;
        font-weight: 700 !important;
        background-color: #f0f0f0 !important;
    }

    /* Signature Block */
    .signature-block {
        margin-top: 8px !important;
        padding-top: 2px !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }
    .signature-role {
        font-size: 9.5px !important;
    }
    .signature-space {
        height: 42px !important;
    }
    .signature-name {
        font-size: 10px !important;
        font-weight: 700 !important;
    }
    .signature-nip {
        font-size: 8.5px !important;
    }
}
</style>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 border-bottom pb-3 no-print">
    <div>
        <h1 class="h4 fw-bold text-dark mb-1">Hasil &amp; Berita Acara Pemilihan</h1>
        <div class="text-muted small">Rekapitulasi resmi perolehan suara pemilihan ketua OSIS SMK SIG Periode <?= e($settings['election_period']) ?></div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="settings.php" class="btn btn-sm btn-outline-secondary" title="Ubah Nama Kepala Sekolah, Pembina OSIS, atau Panitia">
            <i class="bi bi-pen me-1"></i> Edit Pejabat Pengesah
        </a>
        <button type="button" class="btn btn-sm btn-danger fw-bold" onclick="window.print()" style="background-color: var(--maroon-800); border-color: var(--maroon-900);">
            <i class="bi bi-printer me-1"></i> Cetak Berita Acara (1 Lembar A4)
        </button>
        <a href="results.php" class="btn btn-sm btn-sig-secondary">
            <i class="bi bi-arrow-clockwise me-1"></i> Perbarui
        </a>
    </div>
</div>

<!-- Form Pengaturan Tanggal, Jam & Nomor Surat (No Print) -->
<div class="card-inst mb-4 bg-light border no-print shadow-sm">
    <div class="card-inst-header bg-white py-2.5 px-3 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-calendar-event text-danger"></i>
            <span class="fw-bold small text-dark">Kustomisasi Tanggal, Jam &amp; Nomor Berita Acara</span>
        </div>
        <span class="badge bg-secondary-subtle text-secondary border small">Khusus Layar Panitia &bull; Tidak Tercetak</span>
    </div>
    <div class="card-inst-body p-3">
        <form action="results.php" method="POST" id="formLetter">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="save_letter_defaults">

            <div class="row g-2">
                <div class="col-md-4 col-sm-6">
                    <label class="form-label small fw-bold text-secondary mb-1">
                        <i class="bi bi-hash me-1"></i> Nomor Berita Acara
                    </label>
                    <input type="text" name="letter_number" id="inputLetterNumber" class="form-control form-control-sm" value="<?= e($letterNumber) ?>" placeholder="Contoh: BA.2026/PILKETOS/SMK-SIG/09" required>
                </div>
                <div class="col-md-3 col-sm-6">
                    <label class="form-label small fw-bold text-secondary mb-1">
                        <i class="bi bi-clock me-1"></i> Hari, Tanggal &amp; Jam Rekap
                    </label>
                    <input type="datetime-local" name="letter_datetime" id="inputLetterDatetime" class="form-control form-control-sm" value="<?= !empty($letterDatetime) ? date('Y-m-d\TH:i', strtotime($letterDatetime)) : '' ?>">
                    <div class="form-text text-muted" style="font-size: 10.5px;">Kosong = otomatis tanggal &amp; jam sekarang.</div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <label class="form-label small fw-bold text-secondary mb-1">
                        <i class="bi bi-calendar-check me-1"></i> Tanggal Tanda Tangan
                    </label>
                    <input type="date" name="letter_sign_date" id="inputLetterSignDate" class="form-control form-control-sm" value="<?= !empty($letterSignDate) ? date('Y-m-d', strtotime($letterSignDate)) : '' ?>">
                    <div class="form-text text-muted" style="font-size: 10.5px;">Kosong = otomatis hari ini.</div>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-bold text-secondary mb-1">
                        <i class="bi bi-geo-alt me-1"></i> Kota Tanda Tangan
                    </label>
                    <input type="text" name="letter_city" id="inputLetterCity" class="form-control form-control-sm" value="<?= e($letterCity) ?>" placeholder="Gresik">
                </div>
            </div>

            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-3 pt-2 border-top">
                <div class="small text-muted" style="font-size: 11.5px;">
                    Tampil di surat: <strong><?= format_date_id($execDatetime) ?></strong> &bull; Tanda Tangan: <strong><?= e($letterCity) ?>, <?= format_date_only_id($signDate) ?></strong>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetLetterNow()">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Set Waktu Sekarang
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="previewLetterChanges()">
                        <i class="bi bi-eye me-1"></i> Terapkan ke Preview
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger px-3 fw-bold">
                        <i class="bi bi-save me-1"></i> Simpan Permanen
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="print-sheet card-inst p-3 p-md-4 mb-4 bg-white shadow-sm">
    <!-- Official School Document Header (Kop Surat) -->
    <div class="kop-surat">
        <div class="d-flex align-items-center justify-content-center text-center gap-3">
            <img src="../assets/images/logo-smk-sig.png" alt="Logo SMK SIG" class="kop-logo" style="height: 65px; width: auto;">
            <div>
                <div class="text-uppercase fw-bold text-dark kop-title-1" style="font-size: 13.5px; letter-spacing: 0.04em;">Yayasan Semen Indonesia (Semen Indonesia Foundation)</div>
                <div class="text-uppercase fw-bold text-danger fs-5 kop-title-2">SMK SEMEN GRESIK (SMK SIG)</div>
                <div class="text-muted kop-title-3" style="font-size: 11px;">
                    Jl. Arief Rachman Hakim No. 90, Gresik, Jawa Timur &bull; Akreditasi A &bull; SMK Pusat Keunggulan
                </div>
                <div class="fw-bold text-dark kop-title-4" style="font-size: 11.5px;">
                    KOMISI PEMILIHAN KETUA ORGANISASI SISWA INTRA SEKOLAH (OSIS)
                </div>
            </div>
        </div>
    </div>

    <div class="text-center mb-3 doc-title-block">
        <h2 class="h5 fw-bold text-uppercase text-dark mb-1 doc-title" style="text-decoration: underline;">BERITA ACARA REKAPITULASI HASIL PEMILIHAN</h2>
        <div class="text-muted small doc-number">Nomor: <?= e($letterNumber) ?></div>
    </div>

    <p class="small text-dark mb-2 doc-intro" style="line-height: 1.45;">
        Pada hari ini, <strong><?= format_date_id($execDatetime) ?></strong>, telah dilaksanakan rekapitulasi penghitungan suara secara elektronik melalui sistem <strong>E-Pilketos v2.0</strong> untuk Pemilihan Ketua OSIS SMK SIG Periode <strong><?= e($settings['election_period']) ?></strong> dengan rincian perolehan suara sebagai berikut:
    </p>

    <!-- Stat Ringkasan (Mini Grid) -->
    <div class="row g-2 mb-3 rekap-stat-grid">
        <div class="col-3">
            <div class="border rounded p-2 text-center bg-light rekap-stat-card">
                <div class="text-muted stat-label" style="font-size: 10px;">TOTAL DPT PEMILIH</div>
                <div class="fs-5 fw-bold text-dark stat-value"><?= number_format($totalDPT) ?></div>
            </div>
        </div>
        <div class="col-3">
            <div class="border rounded p-2 text-center bg-light rekap-stat-card">
                <div class="text-muted stat-label" style="font-size: 10px;">SUARA MASUK (SAH)</div>
                <div class="fs-5 fw-bold text-success stat-value"><?= number_format($totalVotes) ?></div>
            </div>
        </div>
        <div class="col-3">
            <div class="border rounded p-2 text-center bg-light rekap-stat-card">
                <div class="text-muted stat-label" style="font-size: 10px;">BELUM MEMILIH</div>
                <div class="fs-5 fw-bold text-danger stat-value"><?= number_format($unvotedTotal) ?></div>
            </div>
        </div>
        <div class="col-3">
            <div class="border rounded p-2 text-center bg-light rekap-stat-card">
                <div class="text-muted stat-label" style="font-size: 10px;">TINGKAT PARTISIPASI</div>
                <div class="fs-5 fw-bold text-dark stat-value"><?= $turnoutPct ?>%</div>
            </div>
        </div>
    </div>

    <!-- Candidate Results Table -->
    <h3 class="fs-6 fw-bold text-dark mb-1 doc-section-title">I. Perolehan Suara Pasangan Calon</h3>
    <table class="table table-bordered table-sm mb-3 table-rekap" style="font-size: 12px;">
        <thead class="table-light">
            <tr>
                <th style="width: 70px;" class="text-center">No. Urut</th>
                <th>Nama Pasangan Calon</th>
                <th style="width: 140px;">Kelas</th>
                <th style="width: 115px;" class="text-end">Jumlah Suara</th>
                <th style="width: 95px;" class="text-end">Persentase</th>
                <th style="width: 130px;" class="text-center">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($candidates)): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted py-2">Belum ada data calon terdaftar</td>
                </tr>
            <?php else: ?>
                <?php foreach ($candidates as $idx => $cand): ?>
                    <?php 
                        $vCount = (int)$cand['vote_count'];
                        $vPct = ($totalVotes > 0) ? round(($vCount / $totalVotes) * 100, 1) : 0;
                        $isWinner = ($winner && $winner['id'] === $cand['id']);
                    ?>
                    <tr class="<?= ($isWinner) ? 'table-success-subtle fw-semibold' : '' ?>">
                        <td class="text-center fw-bold"><?= e($cand['number']) ?></td>
                        <td><?= e($cand['name']) ?></td>
                        <td><?= e($cand['class']) ?></td>
                        <td class="text-end fw-bold"><?= number_format($vCount) ?></td>
                        <td class="text-end fw-bold"><?= $vPct ?>%</td>
                        <td class="text-center">
                            <?php if ($isWinner): ?>
                                <span class="badge bg-success">Suara Terbanyak</span>
                            <?php else: ?>
                                <span class="text-muted small">Peringkat <?= ($idx + 1) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot class="table-light fw-bold">
            <tr>
                <td colspan="3" class="text-end">Total Suara Sah:</td>
                <td class="text-end"><?= number_format($totalVotes) ?></td>
                <td class="text-end"><?= ($totalVotes > 0) ? '100%' : '0%' ?></td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <!-- Turnout Per Class Table (2 Kolom Berdampingan agar Pas 1 Halaman A4) -->
    <h3 class="fs-6 fw-bold text-dark mb-1 doc-section-title">II. Rekapitulasi Partisipasi Pemilih</h3>
    <?php if ($totalEmployees > 0): ?>
        <div class="mb-2 p-1.5 px-2 bg-light border rounded d-flex justify-content-between align-items-center" style="font-size: 9.5px;">
            <span><strong>Partisipasi Guru &amp; Karyawan:</strong> DPT: <strong><?= $totalEmployees ?></strong> orang &bull; Menggunakan Hak Suara: <strong><?= $empVoted ?></strong> orang</span>
            <span class="fw-bold text-primary">Tingkat Partisipasi: <?= $empTurnoutPct ?>%</span>
        </div>
    <?php endif; ?>
    <div class="row g-2 mb-3">
        <!-- Kolom 1 (Kelas 1 - 9) -->
        <div class="col-6">
            <table class="table table-bordered table-sm mb-0 table-rekap" style="font-size: 11px;">
                <thead class="table-light">
                    <tr>
                        <th style="width: 32px;" class="text-center">No</th>
                        <th>Nama Kelas</th>
                        <th class="text-end" style="width: 55px;">DPT</th>
                        <th class="text-end" style="width: 60px;">Masuk</th>
                        <th class="text-end" style="width: 55px;">%</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($classCol1)): ?>
                        <tr><td colspan="5" class="text-center text-muted">Belum ada kelas terdaftar</td></tr>
                    <?php else: ?>
                        <?php foreach ($classCol1 as $idx => $ct): ?>
                            <?php 
                                $cTot = (int)$ct['total_class_students'];
                                $cVot = (int)$ct['class_voted'];
                                $cPct = ($cTot > 0) ? round(($cVot / $cTot) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td class="text-center text-muted"><?= ($idx + 1) ?></td>
                                <td><strong><?= e($ct['class_name']) ?></strong></td>
                                <td class="text-end"><?= $cTot ?></td>
                                <td class="text-end"><?= $cVot ?></td>
                                <td class="text-end fw-semibold"><?= $cPct ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Kolom 2 (Kelas 10 - 18) -->
        <div class="col-6">
            <table class="table table-bordered table-sm mb-0 table-rekap" style="font-size: 11px;">
                <thead class="table-light">
                    <tr>
                        <th style="width: 32px;" class="text-center">No</th>
                        <th>Nama Kelas</th>
                        <th class="text-end" style="width: 55px;">DPT</th>
                        <th class="text-end" style="width: 60px;">Masuk</th>
                        <th class="text-end" style="width: 55px;">%</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($classCol2)): ?>
                        <tr><td colspan="5" class="text-center text-muted">-</td></tr>
                    <?php else: ?>
                        <?php foreach ($classCol2 as $idx => $ct): ?>
                            <?php 
                                $cTot = (int)$ct['total_class_students'];
                                $cVot = (int)$ct['class_voted'];
                                $cPct = ($cTot > 0) ? round(($cVot / $cTot) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td class="text-center text-muted"><?= ($halfCount + $idx + 1) ?></td>
                                <td><strong><?= e($ct['class_name']) ?></strong></td>
                                <td class="text-end"><?= $cTot ?></td>
                                <td class="text-end"><?= $cVot ?></td>
                                <td class="text-end fw-semibold"><?= $cPct ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Official Signatures Block (100% Dinamis dari Pengaturan) -->
    <div class="signature-block">
        <div class="row text-center" style="font-size: 11.5px;">
            <div class="col-4">
                <div class="signature-role">Mengetahui,</div>
                <div class="fw-bold signature-role">Kepala SMK Semen Gresik</div>
                <div class="signature-space"></div>
                <div class="signature-name text-decoration-underline"><?= e($settings['headmaster_name'] ?? 'Choirul Ichsan, S.Psi.') ?></div>
                <div class="text-muted signature-nip">
                    <?= (!empty($settings['headmaster_nip']) && $settings['headmaster_nip'] !== '-') ? 'NIP. ' . e($settings['headmaster_nip']) : 'Kepala Sekolah' ?>
                </div>
            </div>
            <div class="col-4">
                <div class="signature-role">Menyetujui,</div>
                <div class="fw-bold signature-role">Pembina OSIS SMK SIG</div>
                <div class="signature-space"></div>
                <div class="signature-name text-decoration-underline"><?= e($settings['counselor_name'] ?? 'Hajar Alia Rachmi, S.Pd.') ?></div>
                <div class="text-muted signature-nip">
                    <?= (!empty($settings['counselor_nip']) && $settings['counselor_nip'] !== '-') ? 'NIP. ' . e($settings['counselor_nip']) : 'Pembina Kesiswaan' ?>
                </div>
            </div>
            <div class="col-4">
                <div class="signature-role"><?= e($letterCity) ?>, <?= format_date_only_id($signDate) ?></div>
                <div class="fw-bold signature-role"><?= e($settings['committee_title'] ?? 'Ketua Panitia Pilketos') ?></div>
                <div class="signature-space"></div>
                <div class="signature-name text-decoration-underline"><?= e($settings['committee_name'] ?? 'Panitia Pemilihan OSIS SMK SIG') ?></div>
                <div class="text-muted signature-nip">Komisi Pemilihan Ketua OSIS</div>
            </div>
        </div>
    </div>
</div>

<script>
function previewLetterChanges() {
    const num = document.getElementById('inputLetterNumber').value;
    const dt = document.getElementById('inputLetterDatetime').value;
    const sd = document.getElementById('inputLetterSignDate').value;
    const city = document.getElementById('inputLetterCity').value;

    const params = new URLSearchParams();
    if (num) params.set('letter_number', num);
    if (dt) params.set('letter_datetime', dt.replace('T', ' ') + ':00');
    if (sd) params.set('letter_sign_date', sd);
    if (city) params.set('letter_city', city);

    window.location.href = 'results.php?' + params.toString();
}

function resetLetterNow() {
    document.getElementById('inputLetterDatetime').value = '';
    document.getElementById('inputLetterSignDate').value = '';
    window.location.href = 'results.php';
}
</script>

<?php require_once __DIR__ . '/../templates/admin_footer.php'; ?>
