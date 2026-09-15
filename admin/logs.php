<?php
/**
 * E-Pilketos v2.0 - Pusat Log Aktivitas (Audit Trail)
 * SMK SIG (Sekolah Menengah Kejuruan SIG)
 * Khusus Super Administrator
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

require_superadmin();
$pdo = getDb();

// Export CSV Handler
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="audit_log_epilketos_' . date('Y-m-d_His') . '.csv"');
    header('Cache-Control: max-age=0, no-cache, must-revalidate');
    header('Pragma: public');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
    fputcsv($output, ['ID', 'Waktu', 'Username', 'Role', 'Aksi', 'Detail Aktivitas', 'Alamat IP', 'User Agent']);

    $q = $pdo->query("SELECT id, created_at, username, role, action, details, ip_address, user_agent FROM activity_logs ORDER BY id DESC");
    while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            $row['created_at'],
            $row['username'],
            $row['role'],
            $row['action'],
            $row['details'],
            $row['ip_address'],
            $row['user_agent']
        ]);
    }
    fclose($output);
    exit;
}

// Handle Purge Action (Hapus log lebih tua dari 30 hari)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'purge_old') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Sesi formulir tidak valid.');
        header('Location: logs.php');
        exit;
    }
    try {
        $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute();
        $deleted = $stmt->rowCount();
        log_activity($pdo, 'PURGE_LOGS', "Membersihkan $deleted baris log lama (> 30 hari)");
        set_flash('success', "Berhasil membersihkan <strong>$deleted</strong> baris log aktivitas yang berusia lebih dari 30 hari.");
    } catch (PDOException $e) {
        set_flash('danger', 'Gagal membersihkan log: ' . $e->getMessage());
    }
    header('Location: logs.php');
    exit;
}

// Filter Parameters
$filterAction = trim($_GET['action_type'] ?? '');
$filterUser = trim($_GET['username'] ?? '');
$search = trim($_GET['q'] ?? '');
$limit = in_array((int)($_GET['limit'] ?? 50), [25, 50, 100, 250], true) ? (int)$_GET['limit'] : 50;

$where = [];
$params = [];

if (!empty($filterAction)) {
    if ($filterAction === 'LOGIN') {
        $where[] = "(action LIKE 'LOGIN%' OR action = 'LOGOUT')";
    } else {
        $where[] = "action LIKE ?";
        $params[] = "%$filterAction%";
    }
}

if (!empty($filterUser)) {
    $where[] = "username = ?";
    $params[] = $filterUser;
}

if (!empty($search)) {
    $where[] = "(details LIKE ? OR ip_address LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Count Stats
$today = date('Y-m-d');
$totalToday = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = '$today'")->fetchColumn();
$totalLoginSuccess = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs WHERE action = 'LOGIN_SUCCESS'")->fetchColumn();
$totalLoginFailed = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs WHERE action = 'LOGIN_FAILED'")->fetchColumn();
$totalChanges = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs WHERE action NOT IN ('LOGIN_SUCCESS', 'LOGIN_FAILED', 'LOGOUT')")->fetchColumn();

// Fetch Logs
$stmt = $pdo->prepare("SELECT * FROM activity_logs $whereSql ORDER BY id DESC LIMIT $limit");
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Distinct usernames for filter dropdown
$userList = $pdo->query("SELECT DISTINCT username FROM activity_logs WHERE username != '' ORDER BY username ASC")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Log Aktivitas Sistem (Audit Trail) - E-Pilketos v2.0';
require_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold text-dark mb-1">
            <i class="bi bi-clock-history text-danger me-2"></i>Pusat Log Aktivitas &amp; Audit Trail
        </h1>
        <div class="text-muted small">Catatan seluruh rekam jejak autentikasi login dan perubahan data oleh administrator.</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="logs.php?action=export_csv" class="btn btn-outline-success btn-sm px-3">
            <i class="bi bi-file-earmark-excel-fill me-1"></i> Unduh Log (CSV)
        </a>
        <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#modalPurge">
            <i class="bi bi-trash me-1"></i> Bersihkan Log Lama
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card-inst p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Aktivitas Hari Ini</div>
                    <div class="fs-4 fw-bold text-primary mt-1"><?= number_format($totalToday) ?></div>
                    <div class="small text-muted mt-0.5">Catatan log <?= date('d M Y') ?></div>
                </div>
                <div class="rounded-circle bg-primary-subtle text-primary p-2.5 d-inline-flex">
                    <i class="bi bi-activity fs-5"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card-inst p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Login Berhasil</div>
                    <div class="fs-4 fw-bold text-success mt-1"><?= number_format($totalLoginSuccess) ?></div>
                    <div class="small text-muted mt-0.5">Sesi masuk valid</div>
                </div>
                <div class="rounded-circle bg-success-subtle text-success p-2.5 d-inline-flex">
                    <i class="bi bi-box-arrow-in-right fs-5"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card-inst p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Login Gagal (Ditolak)</div>
                    <div class="fs-4 fw-bold text-danger mt-1"><?= number_format($totalLoginFailed) ?></div>
                    <div class="small text-muted mt-0.5">Percobaan salah sandi</div>
                </div>
                <div class="rounded-circle bg-danger-subtle text-danger p-2.5 d-inline-flex">
                    <i class="bi bi-shield-x fs-5"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card-inst p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Perubahan Data</div>
                    <div class="fs-4 fw-bold text-warning mt-1"><?= number_format($totalChanges) ?></div>
                    <div class="small text-muted mt-0.5">Aksi manipulasi data</div>
                </div>
                <div class="rounded-circle bg-warning-subtle text-warning p-2.5 d-inline-flex">
                    <i class="bi bi-pencil-square fs-5"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card-inst p-3 bg-white mb-4">
    <form method="GET" class="row g-2 align-items-center">
        <div class="col-md-3">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control" placeholder="Cari isi log atau IP..." value="<?= e($search) ?>">
            </div>
        </div>
        <div class="col-md-3">
            <select name="action_type" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Semua Kategori Aksi</option>
                <option value="LOGIN" <?= $filterAction === 'LOGIN' ? 'selected' : '' ?>>Autentikasi (Login &amp; Logout)</option>
                <option value="STUDENT" <?= $filterAction === 'STUDENT' ? 'selected' : '' ?>>DPT Siswa</option>
                <option value="EMPLOYEE" <?= $filterAction === 'EMPLOYEE' ? 'selected' : '' ?>>DPT Guru &amp; Karyawan</option>
                <option value="CANDIDATE" <?= $filterAction === 'CANDIDATE' ? 'selected' : '' ?>>Pasangan Calon (Kandidat)</option>
                <option value="FACE" <?= $filterAction === 'FACE' ? 'selected' : '' ?>>Biometrik Wajah</option>
                <option value="SETTINGS" <?= $filterAction === 'SETTINGS' ? 'selected' : '' ?>>Pengaturan &amp; Status</option>
                <option value="USER" <?= $filterAction === 'USER' ? 'selected' : '' ?>>Manajemen Admin</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="username" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Semua Pengguna</option>
                <?php foreach ($userList as $u): ?>
                    <option value="<?= e($u) ?>" <?= $filterUser === $u ? 'selected' : '' ?>><?= e($u) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="limit" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="25" <?= $limit === 25 ? 'selected' : '' ?>>25 Baris</option>
                <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50 Baris</option>
                <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100 Baris</option>
                <option value="250" <?= $limit === 250 ? 'selected' : '' ?>>250 Baris</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-sm btn-sig w-100">Terapkan</button>
            <?php if (!empty($search) || !empty($filterAction) || !empty($filterUser) || $limit !== 50): ?>
                <a href="logs.php" class="btn btn-sm btn-outline-secondary" title="Reset Filter"><i class="bi bi-x-circle"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Logs Table -->
<div class="card-inst bg-white">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
            <thead class="table-light">
                <tr>
                    <th style="width: 170px;">Waktu Kejadian</th>
                    <th style="width: 150px;">Pengguna &amp; Akses</th>
                    <th style="width: 160px;">Jenis Aksi</th>
                    <th>Detail Aktivitas</th>
                    <th style="width: 160px;">Alamat IP &amp; Info</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-journal-x fs-2 d-block mb-2 text-secondary"></i>
                            Belum ada catatan log aktivitas yang cocok dengan kriteria filter.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="text-nowrap text-secondary">
                                <div class="fw-semibold text-dark"><?= date('d M Y', strtotime($log['created_at'])) ?></div>
                                <div class="font-monospace small text-muted"><?= date('H:i:s', strtotime($log['created_at'])) ?> WIB</div>
                            </td>
                            <td>
                                <div class="fw-bold text-dark font-monospace"><?= e($log['username']) ?></div>
                                <div>
                                    <?php if ($log['role'] === 'superadmin'): ?>
                                        <span class="badge bg-warning text-dark border border-warning-subtle" style="font-size: 9.5px;">SUPERADMIN</span>
                                    <?php elseif ($log['role'] === 'admin'): ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle" style="font-size: 9.5px;">ADMIN</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border" style="font-size: 9.5px;">GUEST</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php
                                $act = $log['action'];
                                if ($act === 'LOGIN_SUCCESS') {
                                    echo '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle me-1"></i>LOGIN SUKSES</span>';
                                } elseif ($act === 'LOGIN_FAILED') {
                                    echo '<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-x-circle me-1"></i>LOGIN GAGAL</span>';
                                } elseif ($act === 'LOGOUT') {
                                    echo '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><i class="bi bi-box-arrow-right me-1"></i>LOGOUT</span>';
                                } elseif (str_starts_with($act, 'CREATE_') || str_starts_with($act, 'ADD_')) {
                                    echo '<span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="bi bi-plus-circle me-1"></i>' . e($act) . '</span>';
                                } elseif (str_starts_with($act, 'UPDATE_') || str_starts_with($act, 'EDIT_')) {
                                    echo '<span class="badge bg-info-subtle text-info border border-info-subtle"><i class="bi bi-pencil me-1"></i>' . e($act) . '</span>';
                                } elseif (str_starts_with($act, 'DELETE_') || str_starts_with($act, 'REMOVE_')) {
                                    echo '<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-trash me-1"></i>' . e($act) . '</span>';
                                } elseif (str_contains($act, 'RESET')) {
                                    echo '<span class="badge bg-warning-subtle text-warning text-dark border border-warning-subtle"><i class="bi bi-arrow-counterclockwise me-1"></i>' . e($act) . '</span>';
                                } else {
                                    echo '<span class="badge bg-light text-dark border">' . e($act) . '</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <div class="text-dark" style="line-height: 1.5;"><?= e($log['details']) ?></div>
                            </td>
                            <td class="text-nowrap text-secondary">
                                <div class="font-monospace small fw-semibold text-dark"><i class="bi bi-hdd-network me-1"></i><?= e($log['ip_address']) ?></div>
                                <div class="small text-muted text-truncate" style="max-width: 150px;" title="<?= e($log['user_agent']) ?>">
                                    <?= e($log['user_agent']) ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Purge Logs -->
<div class="modal fade" id="modalPurge" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="purge_old">
            <div class="modal-header bg-light">
                <h5 class="modal-title h6 fw-bold text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Konfirmasi Pembersihan Log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-dark mb-2">
                    Aksi ini akan menghapus seluruh catatan riwayat log aktivitas yang telah <strong>berusia lebih dari 30 hari</strong>.
                </p>
                <div class="alert alert-warning py-2 px-3 small mb-0">
                    <i class="bi bi-info-circle me-1"></i> Log dalam kurun waktu 30 hari terakhir akan tetap dipertahankan secara utuh untuk kebutuhan audit.
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger btn-sm px-3">Hapus Log > 30 Hari</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/admin_footer.php'; ?>
