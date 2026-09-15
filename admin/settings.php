<?php
/**
 * E-Pilketos v2.0 - Pengaturan Sistem & Status Pemilihan
 * SMK SIG (Sekolah Menengah Kejuruan SIG)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

require_admin();
$pdo = getDb();
$settings = get_election_settings($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Sesi formulir keamanan tidak valid.');
        header('Location: settings.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_general') {
        $electionName = trim($_POST['election_name'] ?? '');
        $electionPeriod = trim($_POST['election_period'] ?? '');
        $electionStatus = $_POST['election_status'] ?? 'DRAFT';
        $showResults = isset($_POST['show_results_to_students']) ? 1 : 0;
        
        $headmasterName = trim($_POST['headmaster_name'] ?? 'Choirul Ichsan, S.Psi.');
        $headmasterNip = trim($_POST['headmaster_nip'] ?? '-');
        $counselorName = trim($_POST['counselor_name'] ?? 'Hajar Alia Rachmi, S.Pd.');
        $counselorNip = trim($_POST['counselor_nip'] ?? '-');
        $committeeName = trim($_POST['committee_name'] ?? 'Panitia Pemilihan OSIS SMK SIG');
        $committeeTitle = trim($_POST['committee_title'] ?? 'Ketua Panitia Pilketos');

        $letterNumber = trim($_POST['letter_number'] ?? '');
        $letterDatetime = trim($_POST['letter_datetime'] ?? '');
        $letterSignDate = trim($_POST['letter_sign_date'] ?? '');
        $letterCity = trim($_POST['letter_city'] ?? 'Gresik');

        if (empty($electionName) || empty($electionPeriod)) {
            set_flash('danger', 'Nama pemilihan dan periode wajib diisi.');
        } elseif (!in_array($electionStatus, ['DRAFT', 'OPEN', 'CLOSED'])) {
            set_flash('danger', 'Status pemilihan tidak valid.');
        } else {
            $now = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare("UPDATE settings SET 
                election_name = ?, 
                election_period = ?, 
                election_status = ?, 
                show_results_to_students = ?,
                headmaster_name = ?,
                headmaster_nip = ?,
                counselor_name = ?,
                counselor_nip = ?,
                committee_name = ?,
                committee_title = ?,
                letter_number = ?,
                letter_datetime = ?,
                letter_sign_date = ?,
                letter_city = ?,
                updated_at = ? 
                WHERE id = 1");
            $stmt->execute([
                $electionName, 
                $electionPeriod, 
                $electionStatus, 
                $showResults,
                $headmasterName,
                $headmasterNip,
                $counselorName,
                $counselorNip,
                $committeeName,
                $committeeTitle,
                $letterNumber,
                $letterDatetime,
                $letterSignDate,
                $letterCity,
                $now
            ]);
            log_activity($pdo, 'UPDATE_SETTINGS', "Memperbarui konfigurasi pemilihan. Nama: '$electionName', Periode: '$electionPeriod', Status: '$electionStatus', Tampilkan Hasil: " . ($showResults ? 'Ya' : 'Tidak'));
            set_flash('success', 'Pengaturan pemilihan, surat berita acara, dan pejabat pengesah berhasil diperbarui.');
        }
    } elseif ($action === 'change_password') {
        $oldPass = $_POST['old_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        $adminId = $_SESSION['admin_id'] ?? 1;
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$adminId]);
        $currHash = $stmt->fetchColumn();

        if (!password_verify($oldPass, $currHash)) {
            set_flash('danger', 'Kata sandi lama salah.');
        } elseif (strlen($newPass) < 6) {
            set_flash('danger', 'Kata sandi baru minimal 6 karakter.');
        } elseif ($newPass !== $confirmPass) {
            set_flash('danger', 'Konfirmasi kata sandi baru tidak cocok.');
        } else {
            $newHash = password_hash($newPass, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$newHash, $adminId]);
            log_activity($pdo, 'CHANGE_PASSWORD', "Mengubah kata sandi akun admin sendiri (ID: $adminId)");
            set_flash('success', 'Kata sandi admin berhasil diubah.');
        }
    } elseif ($action === 'reset_all_votes') {
        $confirmText = trim($_POST['confirm_reset_text'] ?? '');
        $settingsNow = get_election_settings($pdo);
        if (($settingsNow['election_status'] ?? 'DRAFT') !== 'DRAFT') {
            set_flash('danger', 'Reset seluruh suara hanya diizinkan saat status pemilihan DRAFT. Tutup dan dokumentasikan pemilihan terlebih dahulu.');
        } elseif ($confirmText === 'RESET-SUARA-SMK-SIG') {
            try {
                $pdo->beginTransaction();
                $pdo->exec("DELETE FROM votes");
                $pdo->exec("UPDATE students SET has_voted = 0, voted_at = NULL");
                $pdo->commit();
                log_activity($pdo, 'RESET_ALL_VOTES', "Mereset dan mengosongkan seluruh data suara serta status memilih pemilih");
                set_flash('success', 'Seluruh data suara berhasil dikosongkan dan status memilih siswa telah direset.');
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                set_flash('danger', 'Gagal mereset data: ' . $e->getMessage());
            }
        } else {
            set_flash('danger', 'Teks konfirmasi reset suara tidak tepat.');
        }
    } elseif ($action === 'reset_new_election') {
        $confirmText = trim($_POST['confirm_reset_election_text'] ?? '');
        $adminPassword = $_POST['admin_password'] ?? '';
        $adminId = $_SESSION['admin_id'] ?? 1;

        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$adminId]);
        $currHash = $stmt->fetchColumn();

        if ($confirmText !== 'RESET-PEMILIHAN-BARU') {
            set_flash('danger', 'Teks konfirmasi salah. Harap ketik "RESET-PEMILIHAN-BARU" persis seperti yang diinstruksikan.');
        } elseif (!password_verify($adminPassword, $currHash)) {
            set_flash('danger', 'Kata sandi administrator salah. Tindakan reset total dibatalkan demi keamanan.');
        } else {
            try {
                $pdo->beginTransaction();

                if (defined('DB_DRIVER') && DB_DRIVER !== 'sqlite') {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                }

                $pdo->exec("DELETE FROM votes");
                $pdo->exec("DELETE FROM students");
                $pdo->exec("DELETE FROM candidates");

                // Update settings: status kembali ke DRAFT, hasil ditutup, nomor surat dikosongkan
                $now = date('Y-m-d H:i:s');
                $newPeriod = trim($_POST['new_period'] ?? '');
                if (!empty($newPeriod)) {
                    $stmt = $pdo->prepare("UPDATE settings SET 
                        election_period = ?, 
                        election_status = 'DRAFT', 
                        show_results_to_students = 0,
                        letter_number = '',
                        letter_datetime = NULL,
                        letter_sign_date = NULL,
                        updated_at = ? 
                        WHERE id = 1");
                    $stmt->execute([$newPeriod, $now]);
                } else {
                    $stmt = $pdo->prepare("UPDATE settings SET 
                        election_status = 'DRAFT', 
                        show_results_to_students = 0,
                        letter_number = '',
                        letter_datetime = NULL,
                        letter_sign_date = NULL,
                        updated_at = ? 
                        WHERE id = 1");
                    $stmt->execute([$now]);
                }

                if (defined('DB_DRIVER') && DB_DRIVER !== 'sqlite') {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                }

                if ($pdo->inTransaction()) {
                    $pdo->commit();
                }

                log_activity($pdo, 'RESET_NEW_ELECTION', "Reset pemilihan baru: mengosongkan seluruh suara, siswa, dan kandidat");

                // Reset AUTO_INCREMENT setelah transaksi commit (DDL statement)
                if (defined('DB_DRIVER') && DB_DRIVER !== 'sqlite') {
                    $pdo->exec("ALTER TABLE votes AUTO_INCREMENT = 1");
                    $pdo->exec("ALTER TABLE students AUTO_INCREMENT = 1");
                    $pdo->exec("ALTER TABLE candidates AUTO_INCREMENT = 1");
                }

                // Bersihkan file foto fisik kandidat di uploads/candidates/
                $candidatePhotos = glob(__DIR__ . '/../uploads/candidates/*');
                if ($candidatePhotos) {
                    foreach ($candidatePhotos as $file) {
                        if (is_file($file) && basename($file) !== '.gitkeep') {
                            @unlink($file);
                        }
                    }
                }

                set_flash('success', 'Reset pemilihan berhasil! Seluruh suara, paslon kandidat, dan siswa DPT telah dibersihkan. Data kelas (18 kelas) dan akun login admin tetap tersimpan utuh.');
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                set_flash('danger', 'Gagal mereset pemilihan: ' . $e->getMessage());
            }
        }
    }

    header('Location: settings.php');
    exit;
}

// Reload settings
$settings = get_election_settings($pdo);

$pageTitle = 'Pengaturan - E-Pilketos v2.0 SMK SIG';
require_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 border-bottom pb-3">
    <div>
        <h1 class="h4 fw-bold text-dark mb-1">Pengaturan Sistem E-Pilketos</h1>
        <div class="text-muted small">Konfigurasi operasional status pemilihan, periode, dan keamanan akun</div>
    </div>
</div>

<div class="row g-4">
    <!-- General Settings Form -->
    <div class="col-lg-7">
        <div class="card-inst">
            <div class="card-inst-header">
                <h2 class="card-inst-title fs-6">Pengaturan Umum Pemilihan</h2>
            </div>
            <div class="card-inst-body p-4">
                <form action="settings.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="update_general">

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nama Kegiatan Pemilihan</label>
                        <input type="text" name="election_name" class="form-control" value="<?= e($settings['election_name']) ?>" required>
                        <div class="form-text">Contoh: Pemilihan Ketua OSIS SMK SIG</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Periode Masa Bakti</label>
                        <input type="text" name="election_period" class="form-control" value="<?= e($settings['election_period']) ?>" required>
                        <div class="form-text">Contoh: 2026/2027</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold">Status Pemilihan (Lifecycle)</label>
                        <select name="election_status" class="form-select">
                            <option value="DRAFT" <?= ($settings['election_status'] === 'DRAFT') ? 'selected' : '' ?>>
                                DRAFT — Tahap Persiapan (Siswa belum dapat melihat surat suara)
                            </option>
                            <option value="OPEN" <?= ($settings['election_status'] === 'OPEN') ? 'selected' : '' ?>>
                                OPEN — Pemilihan Dibuka (Siswa dapat memilih di bilik suara)
                            </option>
                            <option value="CLOSED" <?= ($settings['election_status'] === 'CLOSED') ? 'selected' : '' ?>>
                                CLOSED — Pemilihan Ditutup (Proses pemungutan suara dihentikan)
                            </option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="show_results_to_students" id="checkShowResults" value="1" <?= (!empty($settings['show_results_to_students'])) ? 'checked' : '' ?>>
                            <label class="form-check-label small fw-semibold" for="checkShowResults">
                                Tampilkan Hasil Perolehan Suara ke Siswa / Publik
                            </label>
                        </div>
                        <div class="text-muted small ps-4">
                            Jika dinonaktifkan, siswa tidak dapat mengakses halaman perolehan suara saat pemilihan sedang berlangsung.
                        </div>
                    </div>

                    <hr class="my-4">
                    <h3 class="h6 fw-bold text-dark mb-3">
                        <i class="bi bi-pen-fill text-danger me-1"></i> Pejabat Pengesah Berita Acara (Dapat Disesuaikan)
                    </h3>
                    <div class="text-muted small mb-3">
                        Nama-nama di bawah ini akan tercetak secara otomatis pada Berita Acara Rekapitulasi Hasil Pemilihan resmi sekolah tanpa hardcode.
                    </div>

                    <!-- Kepala Sekolah -->
                    <div class="p-3 bg-light rounded border mb-3">
                        <div class="fw-bold small text-dark mb-2">1. Kepala Sekolah</div>
                        <div class="row g-2">
                            <div class="col-md-7">
                                <label class="form-label small text-muted mb-1">Nama Lengkap & Gelar</label>
                                <input type="text" name="headmaster_name" class="form-control form-control-sm" value="<?= e($settings['headmaster_name'] ?? 'Choirul Ichsan, S.Psi.') ?>" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small text-muted mb-1">NIP / Identitas (Opsional)</label>
                                <input type="text" name="headmaster_nip" class="form-control form-control-sm" value="<?= e($settings['headmaster_nip'] ?? '-') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Pembina OSIS -->
                    <div class="p-3 bg-light rounded border mb-3">
                        <div class="fw-bold small text-dark mb-2">2. Pembina OSIS SMK SIG</div>
                        <div class="row g-2">
                            <div class="col-md-7">
                                <label class="form-label small text-muted mb-1">Nama Lengkap & Gelar</label>
                                <input type="text" name="counselor_name" class="form-control form-control-sm" value="<?= e($settings['counselor_name'] ?? 'Hajar Alia Rachmi, S.Pd.') ?>" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small text-muted mb-1">NIP / Identitas (Opsional)</label>
                                <input type="text" name="counselor_nip" class="form-control form-control-sm" value="<?= e($settings['counselor_nip'] ?? '-') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Panitia Pemilihan -->
                    <div class="p-3 bg-light rounded border mb-4">
                        <div class="fw-bold small text-dark mb-2">3. Ketua Panitia Pemilihan (KPU OSIS)</div>
                        <div class="row g-2">
                            <div class="col-md-7">
                                <label class="form-label small text-muted mb-1">Nama Ketua Panitia</label>
                                <input type="text" name="committee_name" class="form-control form-control-sm" value="<?= e($settings['committee_name'] ?? 'Panitia Pemilihan OSIS SMK SIG') ?>" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small text-muted mb-1">Keterangan / Jabatan</label>
                                <input type="text" name="committee_title" class="form-control form-control-sm" value="<?= e($settings['committee_title'] ?? 'Ketua Panitia Pilketos') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Pengaturan Surat Berita Acara -->
                    <h3 class="card-inst-title fs-6 mb-2 mt-4 pt-3 border-top">
                        <i class="bi bi-file-earmark-text text-danger me-1"></i> Format Berita Acara Hasil Pemilihan
                    </h3>
                    <div class="text-muted small mb-3">
                        Kustomisasi nomor surat, hari/tanggal/jam rekapitulasi, dan kota tanda tangan pada lembar Berita Acara.
                    </div>

                    <div class="p-3 bg-light rounded border mb-4">
                        <div class="row g-2 mb-2">
                            <div class="col-md-6">
                                <label class="form-label small text-muted mb-1">Nomor Surat Berita Acara</label>
                                <input type="text" name="letter_number" class="form-control form-control-sm" value="<?= e($settings['letter_number'] ?? '') ?>" placeholder="Contoh: BA.2026/PILKETOS/SMK-SIG/09">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted mb-1">Kota Penandatanganan</label>
                                <input type="text" name="letter_city" class="form-control form-control-sm" value="<?= e($settings['letter_city'] ?? 'Gresik') ?>" placeholder="Gresik">
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small text-muted mb-1">Hari, Tanggal &amp; Jam Rekap</label>
                                <input type="datetime-local" name="letter_datetime" class="form-control form-control-sm" value="<?= !empty($settings['letter_datetime']) ? date('Y-m-d\TH:i', strtotime($settings['letter_datetime'])) : '' ?>">
                                <div class="form-text text-muted" style="font-size: 10px;">Kosong = otomatis tanggal &amp; jam saat ini.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted mb-1">Tanggal Tanda Tangan</label>
                                <input type="date" name="letter_sign_date" class="form-control form-control-sm" value="<?= !empty($settings['letter_sign_date']) ? date('Y-m-d', strtotime($settings['letter_sign_date'])) : '' ?>">
                                <div class="form-text text-muted" style="font-size: 10px;">Kosong = otomatis hari ini.</div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-danger btn-sm px-4 py-2 fw-semibold" style="background-color: var(--maroon-800); border-color: var(--maroon-900);">
                        <i class="bi bi-save me-1"></i> Simpan Pengaturan
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Side: Security & Reset -->
    <div class="col-lg-5">
        <!-- Change Password Card -->
        <div class="card-inst mb-4">
            <div class="card-inst-header">
                <h2 class="card-inst-title fs-6">Ganti Kata Sandi Administrator</h2>
            </div>
            <div class="card-inst-body p-4">
                <form action="settings.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="change_password">

                    <div class="mb-2">
                        <label class="form-label small fw-bold">Kata Sandi Lama</label>
                        <input type="password" name="old_password" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Kata Sandi Baru</label>
                        <input type="password" name="new_password" class="form-control form-control-sm" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Ulangi Kata Sandi Baru</label>
                        <input type="password" name="confirm_password" class="form-control form-control-sm" required minlength="6">
                    </div>

                    <button type="submit" class="btn btn-outline-dark btn-sm w-100">
                        <i class="bi bi-key me-1"></i> Perbarui Kata Sandi
                    </button>
                </form>
            </div>
        </div>

        <!-- Danger Zone 1: Reset Votes -->
        <div class="card-inst border-warning mb-4">
            <div class="card-inst-header bg-warning-subtle text-dark border-warning-subtle d-flex align-items-center justify-content-between">
                <h2 class="card-inst-title fs-6 text-dark mb-0"><i class="bi bi-arrow-counterclockwise text-warning me-1"></i> Reset Kotak Suara Saja</h2>
                <span class="badge bg-warning text-dark" style="font-size: 10px;">Simulasi / Uji Coba</span>
            </div>
            <div class="card-inst-body p-3">
                <p class="small text-muted mb-2" style="line-height: 1.5; font-size: 12px;">
                    Digunakan jika pemilihan akan dimulai atau setelah simulasi/gladi resik voting selesai. Seluruh suara pemilih akan <strong>dikosongkan</strong> dan status siswa dikembalikan menjadi <strong>Belum Memilih</strong>.
                </p>
                <div class="text-muted small mb-3" style="font-size: 11px;">
                    <i class="bi bi-check-circle-fill text-success me-1"></i> Data paslon, siswa DPT, dan kelas <strong>tetap utuh</strong>.
                </div>
                <button type="button" class="btn btn-sm btn-outline-warning text-dark w-100 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalResetVotes">
                    <i class="bi bi-trash3 me-1"></i> Kosongkan Kotak Suara Saja
                </button>
            </div>
        </div>

        <!-- Danger Zone 2: Reset Pemilihan Baru (Tahun Depan) -->
        <div class="card-inst border-danger">
            <div class="card-inst-header bg-danger-subtle text-danger border-danger-subtle d-flex align-items-center justify-content-between">
                <h2 class="card-inst-title fs-6 text-danger mb-0"><i class="bi bi-calendar2-range text-danger me-1"></i> Reset Total Pemilihan Baru</h2>
                <span class="badge bg-danger" style="font-size: 10px;">Tahun Depan / Periode Baru</span>
            </div>
            <div class="card-inst-body p-4">
                <p class="small text-dark fw-semibold mb-2" style="line-height: 1.5;">
                    Gunakan tombol ini jika tahun depan ada pemilihan baru lagi.
                </p>
                <p class="small text-muted mb-3" style="line-height: 1.5; font-size: 12px;">
                    Sistem akan membersihkan semua data transaksi pemilihan lama dan mengembalikan sistem ke tahap <strong>DRAFT</strong> untuk periode baru.
                </p>

                <!-- What's wiped vs what's kept -->
                <div class="p-2.5 bg-light rounded border mb-3 small" style="font-size: 11.5px;">
                    <div class="text-danger fw-bold mb-1">
                        <i class="bi bi-x-circle-fill me-1"></i> Data yang Dibersihkan Total:
                    </div>
                    <ul class="mb-2 ps-3 text-muted">
                        <li>Semua surat suara pemilih (kotak suara 0)</li>
                        <li>Semua daftar kandidat paslon &amp; file fotonya</li>
                        <li>Semua data pemilih DPT siswa lama</li>
                        <li>Nomor &amp; tanggal surat berita acara</li>
                    </ul>
                    <div class="text-success fw-bold mb-1">
                        <i class="bi bi-check-circle-fill me-1"></i> Data yang TETAP UTUH (Tidak Terhapus):
                    </div>
                    <ul class="mb-0 ps-3 text-dark fw-semibold">
                        <li>Semua Data Kelas (18 Kelas Tetap Utuh, tidak perlu buat ulang)</li>
                        <li>Akun login Administrator (Username &amp; Password tetap aman)</li>
                        <li>Template nama pejabat (Kepsek &amp; Pembina OSIS)</li>
                    </ul>
                </div>

                <button type="button" class="btn btn-sm btn-danger w-100 fw-bold py-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalResetElection">
                    <i class="bi bi-arrow-repeat me-1"></i> Reset Total Pemilihan (Periode Baru)
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Reset Votes Confirmation -->
<div class="modal fade" id="modalResetVotes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i> Konfirmasi Kosongkan Kotak Suara</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="settings.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="reset_all_votes">

                <div class="modal-body">
                    <div class="alert alert-warning small">
                        <strong>PERINGATAN:</strong> Seluruh data pemungutan suara akan dihapus dari basis data dan status seluruh siswa kembali menjadi "Belum Memilih". Kandidat dan siswa <strong>tidak akan terhapus</strong>.
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Ketik teks berikut untuk konfirmasi:</label>
                        <div class="mb-2"><code class="fs-6 fw-bold text-dark">RESET-SUARA-SMK-SIG</code></div>
                        <input type="text" name="confirm_reset_text" class="form-control" placeholder="RESET-SUARA-SMK-SIG" required autocomplete="off">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning btn-sm fw-bold">Ya, Kosongkan Kotak Suara</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Reset Election Confirmation (Periode Baru / Tahun Depan) -->
<div class="modal fade" id="modalResetElection" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-danger shadow-lg">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fs-6 fw-bold">
                    <i class="bi bi-shield-exclamation me-1"></i> Konfirmasi Reset Total Pemilihan Baru
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="settings.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="reset_new_election">

                <div class="modal-body">
                    <div class="alert alert-danger small mb-3">
                        <div class="fw-bold mb-1"><i class="bi bi-exclamation-octagon-fill me-1"></i> PERHATIAN: TINDAKAN BESAR INI BERSIFAT PERMANEN!</div>
                        Seluruh perolehan suara, data kandidat paslon &amp; foto di server, dan data DPT siswa lama akan dihapus total.
                        <div class="mt-2 pt-2 border-top border-danger-subtle text-dark fw-bold">
                            <i class="bi bi-check-circle-fill text-success me-1"></i> Data Kelas (18 kelas SMK SIG) dan akun login admin TETAP UTUH &amp; TIDAK AKAN TERHAPUS.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Periode Pemilihan Baru (Opsional)</label>
                        <?php
                            // Suggest next period, e.g. 2026/2027 -> 2027/2028
                            $currPeriod = $settings['election_period'] ?? '2026/2027';
                            $parts = explode('/', $currPeriod);
                            $suggestedPeriod = (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1]))
                                ? ((int)$parts[0] + 1) . '/' . ((int)$parts[1] + 1)
                                : '2027/2028';
                        ?>
                        <input type="text" name="new_period" class="form-control form-control-sm" value="<?= e($suggestedPeriod) ?>" placeholder="Contoh: 2027/2028">
                        <div class="form-text text-muted" style="font-size: 11px;">Otomatis mengganti label periode akademik pemilihan.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Kata Sandi Administrator Aktif <span class="text-danger">*</span></label>
                        <input type="password" name="admin_password" class="form-control" placeholder="Masukkan kata sandi admin Anda" required autocomplete="current-password">
                        <div class="form-text text-muted" style="font-size: 11px;">Dibutuhkan verifikasi kata sandi untuk mencegah tindakan reset tidak disengaja.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Ketik teks konfirmasi persis di bawah ini: <span class="text-danger">*</span></label>
                        <div class="mb-2 p-2 bg-light border rounded text-center">
                            <code class="fs-6 fw-bold text-danger">RESET-PEMILIHAN-BARU</code>
                        </div>
                        <input type="text" name="confirm_reset_election_text" class="form-control" placeholder="Ketik RESET-PEMILIHAN-BARU di sini" required autocomplete="off">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batalkan</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-bold px-3">
                        <i class="bi bi-trash3-fill me-1"></i> Ya, Reset Total Pemilihan Baru
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/admin_footer.php'; ?>
