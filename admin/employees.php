<?php
/**
 * E-Pilketos v2.0 - Kelola DPT Guru & Karyawan
 * SMK SIG (Sekolah Menengah Kejuruan SIG)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

require_admin();
$pdo = getDb();

// Download Template Handler (Universal Compatible: LibreOffice, Excel & Mobile WPS)
if (isset($_GET['action'])) {
    if (in_array($_GET['action'], ['download_template_xlsx', 'download_template', 'download_template_csv'], true)) {
        // Hapus total semua isi output buffer agar file bersih dari spasi/HTML leak
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="template_guru_karyawan_smk_sig.csv"');
        header('Cache-Control: max-age=0, no-cache, must-revalidate');
        header('Pragma: public');
        
        $output = fopen('php://output', 'w');
        
        // UTF-8 BOM agar LibreOffice Calc, MS Excel & WPS Office langsung baca ber-kolom rapi
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Header Kolom
        fputcsv($output, ['NIP', 'Nama', 'Tipe']);
        
        // Data Sampel/Contoh
        fputcsv($output, ['197508122005011004', 'Choirul Ichsan, S.Psi.', 'guru']);
        fputcsv($output, ['198804212011012015', 'Hajar Alia Rachmi, S.Pd.', 'guru']);
        fputcsv($output, ['198501012010011001', 'Budi Santoso, S.Pd.', 'guru']);
        fputcsv($output, ['199003152018021002', 'Siti Aminah', 'karyawan']);
        fputcsv($output, ['199507202022031003', 'Ahmad Fauzi', 'karyawan']);
        
        fclose($output);
        exit;
    }
}

// Handle Form Submissions (Add, Edit, Delete, Reset Face, Import)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Sesi keamanan formulir tidak valid.');
        header('Location: employees.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $nip = trim($_POST['nip'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $type = trim($_POST['type'] ?? 'guru');
        if (!in_array($type, ['guru', 'karyawan'], true)) {
            $type = 'guru';
        }

        if (empty($nip) || empty($name)) {
            set_flash('danger', 'NIP dan Nama Guru/Karyawan wajib diisi.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO employees (nip, name, type, has_voted) VALUES (?, ?, ?, 0)");
                $stmt->execute([$nip, $name, $type]);
                $newId = (int)$pdo->lastInsertId();
                log_activity($pdo, 'ADD_EMPLOYEE', "Menambahkan " . ucfirst($type) . " baru: '$name' (NIP: $nip, ID: $newId)");
                set_flash('success', "Data " . ucfirst($type) . " '$name' (NIP: $nip) berhasil ditambahkan.");
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'UNIQUE') !== false || strpos($e->getMessage(), '1062') !== false) {
                    set_flash('danger', "NIP '$nip' sudah terdaftar di sistem.");
                } else {
                    set_flash('danger', 'Gagal menambahkan data: ' . $e->getMessage());
                }
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $nip = trim($_POST['nip'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $type = trim($_POST['type'] ?? 'guru');
        if (!in_array($type, ['guru', 'karyawan'], true)) {
            $type = 'guru';
        }

        if ($id > 0 && !empty($nip) && !empty($name)) {
            try {
                $stmt = $pdo->prepare("UPDATE employees SET nip = ?, name = ?, type = ? WHERE id = ?");
                $stmt->execute([$nip, $name, $type, $id]);
                log_activity($pdo, 'EDIT_EMPLOYEE', "Memperbarui data " . ucfirst($type) . " ID $id: '$name' (NIP: $nip)");

                if (!empty($_POST['reset_face']) && (int)$_POST['reset_face'] === 1) {
                    $pdo->prepare("UPDATE employees SET face_descriptor = NULL, face_enrolled_at = NULL WHERE id = ?")->execute([$id]);
                    log_activity($pdo, 'RESET_FACE_EMPLOYEE', "Mereset biometrik wajah " . ucfirst($type) . " ID $id ('$name') via modal edit");
                    set_flash('success', "Data '$name' berhasil diperbarui & biometrik wajah direset.");
                } else {
                    set_flash('success', "Data '$name' berhasil diperbarui.");
                }
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'UNIQUE') !== false || strpos($e->getMessage(), '1062') !== false) {
                    set_flash('danger', "NIP '$nip' sudah digunakan oleh orang lain.");
                } else {
                    set_flash('danger', 'Gagal memperbarui data: ' . $e->getMessage());
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $check = $pdo->prepare("SELECT name, nip, has_voted FROM employees WHERE id = ?");
            $check->execute([$id]);
            $empRow = $check->fetch(PDO::FETCH_ASSOC);
            if (!$empRow) {
                throw new RuntimeException('Data tidak ditemukan.');
            }
            if ((int)$empRow['has_voted'] === 1) {
                throw new RuntimeException('Guru/Karyawan yang sudah memilih tidak dapat dihapus karena surat suara bersifat anonim.');
            }
            $pdo->prepare("DELETE FROM employees WHERE id = ?")->execute([$id]);
            log_activity($pdo, 'DELETE_EMPLOYEE', "Menghapus data Guru/Karyawan ID $id ('{$empRow['name']}', NIP: {$empRow['nip']})");
            set_flash('success', 'Data Guru/Karyawan berhasil dihapus.');
        } catch (Throwable $e) {
            set_flash('danger', 'Gagal menghapus data: ' . $e->getMessage());
        }
    } elseif ($action === 'reset_face') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $stmtName = $pdo->prepare("SELECT name FROM employees WHERE id = ?");
            $stmtName->execute([$id]);
            $eName = $stmtName->fetchColumn() ?: 'Guru/Karyawan';

            $pdo->prepare("UPDATE employees SET face_descriptor = NULL, face_enrolled_at = NULL WHERE id = ?")->execute([$id]);
            log_activity($pdo, 'RESET_FACE_EMPLOYEE', "Mereset biometrik wajah Guru/Karyawan ID $id ('$eName')");
            set_flash('success', "Biometrik wajah <strong>" . e($eName) . "</strong> berhasil direset. Kini muncul kembali di pendaftaran wajah.");
        } catch (PDOException $e) {
            set_flash('danger', 'Gagal mereset biometrik: ' . $e->getMessage());
        }
    } elseif ($action === 'import_file') {
        $uploaded = $_FILES['employee_file'] ?? null;
        if (!$uploaded || $uploaded['error'] !== UPLOAD_ERR_OK) {
            set_flash('danger', 'Harap pilih berkas Excel (.xlsx) atau CSV (.csv) yang valid.');
        } else {
            $filePath = $uploaded['tmp_name'];
            $origName = $uploaded['name'] ?? '';
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            $rawRows = [];
            if ($ext === 'xlsx') {
                $parsed = parse_xlsx_file($filePath);
                if ($parsed === null) {
                    set_flash('danger', 'Gagal membaca berkas Excel (.xlsx). Pastikan berkas tidak rusak atau terenkripsi.');
                    header('Location: employees.php');
                    exit;
                }
                $rawRows = $parsed;
            } else {
                $handle = fopen($filePath, 'r');
                if ($handle !== false) {
                    while (($row = fgetcsv($handle, 2000, ',')) !== false) {
                        if (count($row) === 1 && strpos($row[0], ';') !== false) {
                            $row = str_getcsv($row[0], ';');
                        }
                        $rawRows[] = $row;
                    }
                    fclose($handle);
                }
            }

            if (empty($rawRows)) {
                set_flash('warning', 'Berkas yang diunggah kosong atau tidak memiliki data.');
                header('Location: employees.php');
                exit;
            }

            // Detect column headers
            $headerIndex = -1;
            $colNip = -1;
            $colName = -1;
            $colType = -1;

            for ($r = 0; $r < min(3, count($rawRows)); $r++) {
                $testRow = array_map(function($v) { return strtolower(trim((string)$v)); }, $rawRows[$r]);
                foreach ($testRow as $cIdx => $cellText) {
                    if ($colNip === -1 && (strpos($cellText, 'nip') !== false || strpos($cellText, 'nik') !== false || strpos($cellText, 'id') !== false || strpos($cellText, 'no') !== false)) {
                        $colNip = $cIdx;
                    }
                    if ($colName === -1 && (strpos($cellText, 'nama') !== false || strpos($cellText, 'name') !== false)) {
                        $colName = $cIdx;
                    }
                    if ($colType === -1 && (strpos($cellText, 'tipe') !== false || strpos($cellText, 'type') !== false || strpos($cellText, 'jabatan') !== false || strpos($cellText, 'kategori') !== false || strpos($cellText, 'status') !== false)) {
                        $colType = $cIdx;
                    }
                }
                if ($colNip !== -1 && $colName !== -1) {
                    $headerIndex = $r;
                    break;
                }
            }

            if ($colNip === -1) $colNip = 0;
            if ($colName === -1) $colName = 1;
            if ($colType === -1) $colType = 2;

            $dataRows = ($headerIndex !== -1) ? array_slice($rawRows, $headerIndex + 1) : $rawRows;

            $inserted = 0;
            $updated = 0;
            $errors = [];

            $stmtCheck = $pdo->prepare("SELECT id FROM employees WHERE nip = ?");
            $stmtInsert = $pdo->prepare("INSERT INTO employees (nip, name, type, has_voted) VALUES (?, ?, ?, 0)");
            $stmtUpdate = $pdo->prepare("UPDATE employees SET name = ?, type = ? WHERE id = ?");

            foreach ($dataRows as $idx => $row) {
                $nip = trim((string)($row[$colNip] ?? ''));
                $name = trim((string)($row[$colName] ?? ''));
                $rawType = strtolower(trim((string)($row[$colType] ?? 'guru')));
                $type = (strpos($rawType, 'karyawan') !== false || strpos($rawType, 'staff') !== false) ? 'karyawan' : 'guru';

                if (empty($nip) || empty($name)) {
                    continue;
                }

                try {
                    $stmtCheck->execute([$nip]);
                    $existingId = $stmtCheck->fetchColumn();
                    if ($existingId) {
                        $stmtUpdate->execute([$name, $type, $existingId]);
                        $updated++;
                    } else {
                        $stmtInsert->execute([$nip, $name, $type]);
                        $inserted++;
                    }
                } catch (PDOException $e) {
                    $errors[] = "Baris " . ($idx + 2) . " ($name): " . $e->getMessage();
                }
            }

            if ($inserted > 0 || $updated > 0) {
                log_activity($pdo, 'IMPORT_EMPLOYEES', "Impor berkas Guru/Karyawan ($origName): $inserted data baru, $updated data diperbarui.");
                $msg = "Import berhasil: <strong>$inserted data baru ditambahkan</strong>";
                if ($updated > 0) {
                    $msg .= ", <strong>$updated data diperbarui</strong>";
                }
                set_flash('success', $msg . '.');
            } else {
                set_flash('warning', 'Tidak ada data baru yang diimpor.');
            }

            if (!empty($errors)) {
                set_flash('danger', 'Beberapa baris gagal diimpor:<br>' . implode('<br>', array_slice($errors, 0, 5)));
            }
        }
    }

    header('Location: employees.php');
    exit;
}

// Filter and Search Parameters
$search = trim($_GET['q'] ?? '');
$filterType = $_GET['type'] ?? '';
$filterFace = $_GET['face'] ?? '';
$filterVoted = $_GET['voted'] ?? '';

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(nip LIKE ? OR name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filterType) && in_array($filterType, ['guru', 'karyawan'], true)) {
    $where[] = "type = ?";
    $params[] = $filterType;
}

if ($filterFace === 'enrolled') {
    $where[] = "(face_descriptor IS NOT NULL AND face_descriptor != '')";
} elseif ($filterFace === 'not_enrolled') {
    $where[] = "(face_descriptor IS NULL OR face_descriptor = '')";
}

if ($filterVoted === 'voted') {
    $where[] = "has_voted = 1";
} elseif ($filterVoted === 'not_voted') {
    $where[] = "has_voted = 0";
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Count stats
$totalEmployees = (int)$pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
$totalGuru = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE type = 'guru'")->fetchColumn();
$totalKaryawan = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE type = 'karyawan'")->fetchColumn();
$totalEnrolled = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE face_descriptor IS NOT NULL AND face_descriptor != ''")->fetchColumn();
$totalVoted = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE has_voted = 1")->fetchColumn();

// Fetch list
$stmt = $pdo->prepare("SELECT * FROM employees $whereSql ORDER BY type ASC, name ASC");
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Kelola DPT Guru & Karyawan - E-Pilketos v2.0';
require_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold text-dark mb-1">DPT Guru &amp; Karyawan</h1>
        <div class="text-muted small">Kelola daftar pemilih tetap dari unsur pendidik dan tenaga kependidikan SMK SIG.</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" class="btn btn-sig btn-sm px-3" data-bs-toggle="modal" data-bs-target="#modalAdd">
            <i class="bi bi-person-plus-fill me-1"></i> Tambah Data
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#modalImport">
            <i class="bi bi-file-earmark-arrow-up-fill me-1"></i> Import Excel / CSV
        </button>
        <a href="../face-poc/enroll.php?voter_type=employee" class="btn btn-outline-danger btn-sm px-3">
            <i class="bi bi-camera-fill me-1"></i> Registrasi Wajah
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card-inst p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Total DPT Pegawai</div>
                    <div class="fs-4 fw-bold text-dark mt-1"><?= number_format($totalEmployees) ?></div>
                    <div class="small text-muted mt-0.5"><?= $totalGuru ?> Guru &bull; <?= $totalKaryawan ?> Karyawan</div>
                </div>
                <div class="rounded-circle bg-primary-subtle text-primary p-2.5 d-inline-flex">
                    <i class="bi bi-briefcase-fill fs-5"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card-inst p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Biometrik Wajah</div>
                    <div class="fs-4 fw-bold text-success mt-1"><?= number_format($totalEnrolled) ?></div>
                    <div class="small text-muted mt-0.5"><?= $totalEmployees > 0 ? round(($totalEnrolled / $totalEmployees) * 100, 1) : 0 ?>% Terdaftar</div>
                </div>
                <div class="rounded-circle bg-success-subtle text-success p-2.5 d-inline-flex">
                    <i class="bi bi-person-check-fill fs-5"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card-inst p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Sudah Memilih</div>
                    <div class="fs-4 fw-bold text-danger mt-1"><?= number_format($totalVoted) ?></div>
                    <div class="small text-muted mt-0.5"><?= $totalEmployees > 0 ? round(($totalVoted / $totalEmployees) * 100, 1) : 0 ?>% Partisipasi</div>
                </div>
                <div class="rounded-circle bg-danger-subtle text-danger p-2.5 d-inline-flex">
                    <i class="bi bi-check2-circle fs-5"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card-inst p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Belum Memilih</div>
                    <div class="fs-4 fw-bold text-secondary mt-1"><?= number_format(max(0, $totalEmployees - $totalVoted)) ?></div>
                    <div class="small text-muted mt-0.5">Menunggu Hak Suara</div>
                </div>
                <div class="rounded-circle bg-secondary-subtle text-secondary p-2.5 d-inline-flex">
                    <i class="bi bi-clock-history fs-5"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card-inst p-3 bg-white mb-4">
    <form method="GET" class="row g-2 align-items-center">
        <div class="col-md-4">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control" placeholder="Cari NIP atau nama..." value="<?= e($search) ?>">
            </div>
        </div>
        <div class="col-md-2">
            <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Semua Tipe</option>
                <option value="guru" <?= $filterType === 'guru' ? 'selected' : '' ?>>Guru</option>
                <option value="karyawan" <?= $filterType === 'karyawan' ? 'selected' : '' ?>>Karyawan</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="face" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Status Wajah (Semua)</option>
                <option value="enrolled" <?= $filterFace === 'enrolled' ? 'selected' : '' ?>>Sudah Terdaftar Wajah</option>
                <option value="not_enrolled" <?= $filterFace === 'not_enrolled' ? 'selected' : '' ?>>Belum Terdaftar</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="voted" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Status Memilih (Semua)</option>
                <option value="voted" <?= $filterVoted === 'voted' ? 'selected' : '' ?>>Sudah Memilih</option>
                <option value="not_voted" <?= $filterVoted === 'not_voted' ? 'selected' : '' ?>>Belum Memilih</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-sm btn-sig w-100">Filter</button>
            <?php if (!empty($search) || !empty($filterType) || !empty($filterFace) || !empty($filterVoted)): ?>
                <a href="employees.php" class="btn btn-sm btn-outline-secondary" title="Reset Filter"><i class="bi bi-x-circle"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Table Card -->
<div class="card-inst bg-white">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 50px;" class="text-center">No</th>
                    <th>NIP / Identitas</th>
                    <th>Nama Lengkap</th>
                    <th>Kategori</th>
                    <th>Status Biometrik</th>
                    <th>Hak Suara</th>
                    <th style="width: 140px;" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($employees)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                            Belum ada data Guru &amp; Karyawan yang sesuai kriteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($employees as $idx => $emp): ?>
                        <tr>
                            <td class="text-center text-muted small"><?= $idx + 1 ?></td>
                            <td><span class="font-monospace fw-semibold text-dark"><?= e($emp['nip']) ?></span></td>
                            <td class="fw-bold text-dark"><?= e($emp['name']) ?></td>
                            <td>
                                <?php if ($emp['type'] === 'guru'): ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">GURU</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">KARYAWAN</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($emp['face_descriptor'])): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="bi bi-check-circle-fill me-1"></i> Terdaftar
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle text-dark">
                                        <i class="bi bi-exclamation-circle me-1"></i> Belum
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int)$emp['has_voted'] === 1): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Sudah Memilih</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">Belum</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary" title="Edit"
                                        onclick="openEditModal(<?= htmlspecialchars(json_encode($emp), ENT_QUOTES, 'UTF-8') ?>)">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <?php if (!empty($emp['face_descriptor'])): ?>
                                        <button type="button" class="btn btn-outline-warning" title="Reset Biometrik Wajah"
                                            onclick="confirmResetFace(<?= (int)$emp['id'] ?>, '<?= e(addslashes($emp['name'])) ?>')">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ((int)$emp['has_voted'] === 0): ?>
                                        <button type="button" class="btn btn-outline-danger" title="Hapus"
                                            onclick="confirmDelete(<?= (int)$emp['id'] ?>, '<?= e(addslashes($emp['name'])) ?>')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Add -->
<div class="modal fade" id="modalAdd" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add">
            <div class="modal-header">
                <h5 class="modal-title h6 fw-bold">Tambah Guru &amp; Karyawan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">NIP / Identifier Internal <span class="text-danger">*</span></label>
                    <input type="text" name="nip" class="form-control form-control-sm" placeholder="Contoh: 197508122005011004" required>
                    <div class="form-text small">Nomor Induk Pegawai atau kode ID unik resmi sekolah.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Nama Lengkap beserta Gelar <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control form-control-sm" placeholder="Contoh: Choirul Ichsan, S.Psi." required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Kategori <span class="text-danger">*</span></label>
                    <select name="type" class="form-select form-select-sm" required>
                        <option value="guru">Tenaga Pendidik (Guru)</option>
                        <option value="karyawan">Tenaga Kependidikan (Karyawan / Tata Usaha)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sig btn-sm px-3">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editId">
            <div class="modal-header">
                <h5 class="modal-title h6 fw-bold">Edit Guru &amp; Karyawan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">NIP / Identifier Internal <span class="text-danger">*</span></label>
                    <input type="text" name="nip" id="editNip" class="form-control form-control-sm" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="editName" class="form-control form-control-sm" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Kategori <span class="text-danger">*</span></label>
                    <select name="type" id="editType" class="form-select form-select-sm" required>
                        <option value="guru">Tenaga Pendidik (Guru)</option>
                        <option value="karyawan">Tenaga Kependidikan (Karyawan / Tata Usaha)</option>
                    </select>
                </div>
                <div class="form-check" id="editResetFaceCheck">
                    <input class="form-check-input" type="checkbox" name="reset_face" value="1" id="checkResetFace">
                    <label class="form-check-label small text-danger fw-semibold" for="checkResetFace">
                        Reset biometrik wajah (guru/karyawan harus mendaftarkan wajah ulang)
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sig btn-sm px-3">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Import -->
<div class="modal fade" id="modalImport" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" enctype="multipart/form-data" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="import_file">
            <div class="modal-header">
                <h5 class="modal-title h6 fw-bold">Import Data Guru &amp; Karyawan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-secondary mb-3">
                    Unggah daftar resmi Guru &amp; Karyawan menggunakan berkas Excel (.xlsx) atau CSV (.csv). Sistem akan otomatis mendeteksi kolom NIP, Nama, dan Kategori.
                </p>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Pilih Berkas (.xlsx / .csv)</label>
                    <input type="file" name="employee_file" class="form-control form-control-sm" accept=".xlsx, .csv, .txt" required>
                </div>
                <div class="bg-light p-2.5 rounded border small mb-2">
                    <div class="fw-semibold text-dark mb-1">Unduh Format Resmi:</div>
                    <div class="d-flex gap-2">
                        <a href="employees.php?action=download_template_xlsx" class="btn btn-xs btn-outline-success">
                            <i class="bi bi-file-earmark-excel me-1"></i> Template Excel (.xlsx)
                        </a>
                        <a href="employees.php?action=download_template_csv" class="btn btn-xs btn-outline-secondary">
                            <i class="bi bi-file-earmark-text me-1"></i> Template CSV (.csv)
                        </a>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sig btn-sm px-3">Upload &amp; Import</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Action Forms -->
<form id="formDelete" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<form id="formResetFace" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="reset_face">
    <input type="hidden" name="id" id="resetFaceId">
</form>

<script>
function openEditModal(emp) {
    document.getElementById('editId').value = emp.id;
    document.getElementById('editNip').value = emp.nip;
    document.getElementById('editName').value = emp.name;
    document.getElementById('editType').value = emp.type || 'guru';
    const faceCheck = document.getElementById('editResetFaceCheck');
    if (emp.face_descriptor) {
        faceCheck.style.display = 'block';
    } else {
        faceCheck.style.display = 'none';
    }
    const modal = new bootstrap.Modal(document.getElementById('modalEdit'));
    modal.show();
}

function confirmDelete(id, name) {
    if (confirm('Yakin ingin menghapus Guru/Karyawan: ' + name + '?')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('formDelete').submit();
    }
}

function confirmResetFace(id, name) {
    if (confirm('Reset biometrik wajah ' + name + '? Orang yang bersangkutan harus mendaftarkan wajah kembali sebelum memilih.')) {
        document.getElementById('resetFaceId').value = id;
        document.getElementById('formResetFace').submit();
    }
}
</script>

<?php require_once __DIR__ . '/../templates/admin_footer.php'; ?>
