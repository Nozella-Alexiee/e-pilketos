<?php
/**
 * E-Pilketos v2.0 - Kelola Siswa & Import CSV
 * SMK SIG (Sekolah Menengah Kejuruan SIG)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

require_admin();
$pdo = getDb();

// Download Template Handler (Excel .xlsx / CSV)
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'download_template_xlsx') {
        $tmpXlsx = generate_students_template_xlsx();
        if ($tmpXlsx && file_exists($tmpXlsx)) {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="template_siswa_smk_sig.xlsx"');
            header('Content-Length: ' . filesize($tmpXlsx));
            readfile($tmpXlsx);
            unlink($tmpXlsx);
            exit;
        }
    } elseif ($_GET['action'] === 'download_template' || $_GET['action'] === 'download_template_csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="template_siswa_smk_sig.csv"');
        $output = fopen('php://output', 'w');
        // UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($output, ['No. Absen', 'Nama Siswa', 'Kelas']);
        // Kelompok Siswa Kelas XII RPL 1
        fputcsv($output, ['1', 'Aditya Pratama', 'XII RPL 1']);
        fputcsv($output, ['2', 'Bima Satria', 'XII RPL 1']);
        fputcsv($output, ['3', 'Citra Dewi Anggraini', 'XII RPL 1']);
        fputcsv($output, ['4', 'Daffa Maulana', 'XII RPL 1']);
        fputcsv($output, ['5', 'Eka Saputra', 'XII RPL 1']);
        // Kelompok Siswa Kelas XII RPL 2
        fputcsv($output, ['1', 'Fajar Nugraha', 'XII RPL 2']);
        fputcsv($output, ['2', 'Gita Maharani', 'XII RPL 2']);
        fputcsv($output, ['3', 'Haryo Wicaksono', 'XII RPL 2']);
        // Kelompok Siswa Kelas XI RPL 1
        fputcsv($output, ['1', 'Indra Gunawan', 'XI RPL 1']);
        fputcsv($output, ['2', 'Jihan Farida', 'XI RPL 1']);
        fputcsv($output, ['3', 'Kevin Sanjaya', 'XI RPL 1']);
        fclose($output);
        exit;
    }
}

// Handle Form Submissions (Add, Edit, Delete, Reset, Import CSV)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Sesi keamanan formulir tidak valid.');
        header('Location: students.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $classId = (int)($_POST['class_id'] ?? 0);
        $attendanceNumber = (int)($_POST['attendance_number'] ?? 0);

        if (empty($name) || $classId <= 0 || $attendanceNumber <= 0) {
            set_flash('danger', 'Nama, kelas, dan nomor absen valid wajib diisi.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO students (name, class_id, attendance_number, has_voted) VALUES (?, ?, ?, 0)");
                $stmt->execute([$name, $classId, $attendanceNumber]);
                $newId = (int)$pdo->lastInsertId();
                log_activity($pdo, 'ADD_STUDENT', "Menambahkan siswa baru: '$name' (Absen: $attendanceNumber, Kelas ID: $classId, ID: $newId)");
                set_flash('success', "Siswa '$name' berhasil ditambahkan.");
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'UNIQUE') !== false) {
                    set_flash('danger', "Nomor absen $attendanceNumber sudah terdaftar pada kelas tersebut.");
                } else {
                    set_flash('danger', 'Gagal menambahkan siswa: ' . $e->getMessage());
                }
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $classId = (int)($_POST['class_id'] ?? 0);
        $attendanceNumber = (int)($_POST['attendance_number'] ?? 0);
        if ($id > 0 && !empty($name) && $classId > 0 && $attendanceNumber > 0) {
            try {
                // Voting eligibility is changed only by the atomic vote claim.
                // Do not let an edit recreate a vote right after an anonymous ballot.
                $stmt = $pdo->prepare("UPDATE students SET name = ?, class_id = ?, attendance_number = ? WHERE id = ?");
                $stmt->execute([$name, $classId, $attendanceNumber, $id]);
                log_activity($pdo, 'EDIT_STUDENT', "Memperbarui data siswa ID $id: '$name' (Absen: $attendanceNumber, Kelas ID: $classId)");
                
                // Opsi reset face biometrik dari modal edit
                if (!empty($_POST['reset_face']) && (int)$_POST['reset_face'] === 1) {
                    $pdo->prepare("UPDATE students SET face_descriptor = NULL, face_enrolled_at = NULL WHERE id = ?")->execute([$id]);
                    log_activity($pdo, 'RESET_FACE_STUDENT', "Mereset biometrik wajah siswa ID $id ('$name') melalui modal edit");
                    set_flash('success', "Data siswa '$name' berhasil diperbarui & biometrik wajah direset (siswa muncul kembali di pendaftaran wajah).");
                } else {
                    set_flash('success', "Data siswa '$name' berhasil diperbarui.");
                }
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'UNIQUE') !== false) {
                    set_flash('danger', "Nomor absen $attendanceNumber sudah digunakan di kelas tersebut.");
                } else {
                    set_flash('danger', 'Gagal memperbarui siswa: ' . $e->getMessage());
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $check = $pdo->prepare("SELECT name, has_voted FROM students WHERE id = ?");
            $check->execute([$id]);
            $studentRow = $check->fetch(PDO::FETCH_ASSOC);
            if (!$studentRow) {
                throw new RuntimeException('Data siswa tidak ditemukan.');
            }
            if ((int)$studentRow['has_voted'] === 1) {
                throw new RuntimeException('Siswa yang sudah memilih tidak dapat dihapus sendiri karena surat suara bersifat anonim. Gunakan reset pemilihan global saat status DRAFT bila pemilihan memang harus diulang.');
            }
            $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$id]);
            log_activity($pdo, 'DELETE_STUDENT', "Menghapus siswa ID $id ('{$studentRow['name']}')");
            set_flash('success', 'Data siswa berhasil dihapus.');
        } catch (Throwable $e) {
            set_flash('danger', 'Gagal menghapus siswa: ' . $e->getMessage());
        }
    } elseif ($action === 'reset_vote') {
        set_flash('warning', 'Reset per siswa dinonaktifkan untuk menjaga kerahasiaan surat suara. Jika pemilihan harus diulang, gunakan reset pemilihan global hanya saat status DRAFT.');
    } elseif ($action === 'reset_face') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $stmtName = $pdo->prepare("SELECT name FROM students WHERE id = ?");
            $stmtName->execute([$id]);
            $sName = $stmtName->fetchColumn() ?: 'Siswa';

            $pdo->prepare("UPDATE students SET face_descriptor = NULL, face_enrolled_at = NULL WHERE id = ?")->execute([$id]);
            log_activity($pdo, 'RESET_FACE_STUDENT', "Mereset biometrik wajah siswa ID $id ('$sName')");
            set_flash('success', "Biometrik wajah <strong>" . e($sName) . "</strong> berhasil direset. Siswa kini muncul kembali di daftar pendaftaran wajah.");
        } catch (PDOException $e) {
            set_flash('danger', 'Gagal mereset biometrik wajah: ' . $e->getMessage());
        }
    } elseif ($action === 'import_csv' || $action === 'import_file') {
        $uploaded = $_FILES['student_file'] ?? $_FILES['csv_file'] ?? null;
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
                    header('Location: students.php');
                    exit;
                }
                $rawRows = $parsed;
            } else {
                // CSV or TXT fallback
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
                set_flash('warning', 'Berkas yang diunggah kosong atau tidak memiliki data siswa.');
                header('Location: students.php');
                exit;
            }

            // Fetch existing classes indexed by uppercase name
            $classesList = $pdo->query("SELECT id, UPPER(name) as uname, name FROM classes")->fetchAll();
            $classMap = [];
            foreach ($classesList as $c) {
                $classMap[$c['uname']] = (int)$c['id'];
            }

            // Detect column headers
            $headerIndex = -1;
            $colName = -1;
            $colClass = -1;
            $colAbsen = -1;

            // Check first 3 rows for potential header labels
            for ($r = 0; $r < min(3, count($rawRows)); $r++) {
                $testRow = array_map(function($v) { return strtolower(trim((string)$v)); }, $rawRows[$r]);
                foreach ($testRow as $cIdx => $cellText) {
                    if ($colName === -1 && (strpos($cellText, 'nama') !== false || strpos($cellText, 'name') !== false || strpos($cellText, 'siswa') !== false)) {
                        $colName = $cIdx;
                    }
                    if ($colClass === -1 && (strpos($cellText, 'kelas') !== false || strpos($cellText, 'class') !== false || strpos($cellText, 'rombel') !== false)) {
                        $colClass = $cIdx;
                    }
                    if ($colAbsen === -1 && (strpos($cellText, 'absen') !== false || strpos($cellText, 'nomor') !== false || $cellText === 'no' || $cellText === 'no.')) {
                        $colAbsen = $cIdx;
                    }
                }
                if ($colName !== -1 && $colClass !== -1) {
                    $headerIndex = $r;
                    break;
                }
            }

            // Fallback mapping if header was not detected
            if ($colName === -1 || $colClass === -1) {
                $headerIndex = -1;
                $firstRow = $rawRows[0] ?? [];
                $colCount = count($firstRow);
                if ($colCount === 2) {
                    $colName = 0;
                    $colClass = 1;
                    $colAbsen = -1;
                } elseif ($colCount >= 3) {
                    if (is_numeric(trim((string)($firstRow[0] ?? '')))) {
                        $colAbsen = 0;
                        $colName = 1;
                        $colClass = 2;
                    } else {
                        $colName = 0;
                        $colClass = 1;
                        $colAbsen = 2;
                    }
                }
            }

            $importedCount = 0;
            $updatedCount = 0;
            $skippedCount = 0;
            $newClassesCount = 0;
            $maxAbsenMap = [];

            $pdo->beginTransaction();
            try {
                $checkStmt = $pdo->prepare("SELECT id FROM students WHERE class_id = ? AND attendance_number = ?");
                $insertStmt = $pdo->prepare("INSERT INTO students (name, class_id, attendance_number, has_voted) VALUES (?, ?, ?, 0)");
                $updateStmt = $pdo->prepare("UPDATE students SET name = ? WHERE id = ?");

                for ($i = ($headerIndex + 1); $i < count($rawRows); $i++) {
                    $row = $rawRows[$i];
                    $sName = trim((string)($row[$colName] ?? ''));
                    $rawClassName = trim((string)($row[$colClass] ?? ''));
                    $sClassName = strtoupper($rawClassName);
                    $sAbsen = ($colAbsen !== -1 && isset($row[$colAbsen])) ? (int)trim((string)$row[$colAbsen]) : 0;

                    if (empty($sName) || empty($sClassName)) {
                        $skippedCount++;
                        continue;
                    }

                    // Otomatis buat kelas baru bila belum ada di database
                    if (!isset($classMap[$sClassName])) {
                        $parts = preg_split('/\s+/', $rawClassName);
                        $p0 = strtoupper($parts[0] ?? '');
                        $grade = in_array($p0, ['X', 'XI', 'XII', '10', '11', '12']) ? $p0 : 'X';
                        if ($grade === '10') $grade = 'X';
                        if ($grade === '11') $grade = 'XI';
                        if ($grade === '12') $grade = 'XII';

                        $major = strtoupper($parts[1] ?? 'UMUM');
                        if (in_array($major, ['RPL', 'PPLG'])) $major = 'RPL';
                        elseif (in_array($major, ['TKRO', 'TKR', 'OTO'])) $major = 'TKRO';
                        elseif (in_array($major, ['TOI', 'OI'])) $major = 'TOI';
                        elseif (in_array($major, ['TP', 'MESIN'])) $major = 'TP';
                        elseif (in_array($major, ['KI', 'KIMIA'])) $major = 'KI';

                        $createClass = $pdo->prepare("INSERT INTO classes (name, grade, major, is_active) VALUES (?, ?, ?, 1)");
                        $createClass->execute([$rawClassName, $grade, $major]);
                        $newClassId = (int)$pdo->lastInsertId();
                        $classMap[$sClassName] = $newClassId;
                        $newClassesCount++;
                    }

                    $targetClassId = $classMap[$sClassName];

                    // Auto-increment nomor absen jika di file tidak diisi
                    if ($sAbsen <= 0) {
                        if (!isset($maxAbsenMap[$targetClassId])) {
                            $stmtMax = $pdo->prepare("SELECT COALESCE(MAX(attendance_number), 0) FROM students WHERE class_id = ?");
                            $stmtMax->execute([$targetClassId]);
                            $maxAbsenMap[$targetClassId] = (int)$stmtMax->fetchColumn();
                        }
                        $maxAbsenMap[$targetClassId]++;
                        $sAbsen = $maxAbsenMap[$targetClassId];
                    } else {
                        if (!isset($maxAbsenMap[$targetClassId]) || $sAbsen > $maxAbsenMap[$targetClassId]) {
                            $maxAbsenMap[$targetClassId] = $sAbsen;
                        }
                    }

                    try {
                        $checkStmt->execute([$targetClassId, $sAbsen]);
                        $existingId = $checkStmt->fetchColumn();

                        if ($existingId) {
                            $updateStmt->execute([$sName, $existingId]);
                            $updatedCount++;
                        } else {
                            $insertStmt->execute([$sName, $targetClassId, $sAbsen]);
                            $importedCount++;
                        }
                    } catch (PDOException $e) {
                        $skippedCount++;
                    }
                }

                $pdo->commit();

                log_activity($pdo, 'IMPORT_STUDENTS', "Impor berkas siswa ($origName): $importedCount data baru, $updatedCount data diperbarui, $newClassesCount kelas baru.");

                $msg = "Import berhasil: <strong>$importedCount</strong> siswa baru tersimpan";
                if ($updatedCount > 0) $msg .= ", <strong>$updatedCount</strong> diperbarui";
                if ($newClassesCount > 0) $msg .= ", <strong>$newClassesCount</strong> kelas baru otomatis didaftarkan";
                if ($skippedCount > 0) $msg .= ", $skippedCount baris dilewati";
                $msg .= ".";

                set_flash('success', $msg);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                set_flash('danger', 'Gagal memproses impor berkas: ' . $e->getMessage());
            }
        }
    }

    header('Location: students.php');
    exit;
}

// Filter and Search Parameters
$classFilter = (int)($_GET['class_id'] ?? 0);
$statusFilter = $_GET['status'] ?? 'all';
$faceFilter = $_GET['face'] ?? 'all';
$searchQuery = trim($_GET['q'] ?? '');

$sql = "SELECT s.*, c.name as class_name FROM students s JOIN classes c ON s.class_id = c.id WHERE 1=1";
$params = [];

if ($classFilter > 0) {
    $sql .= " AND s.class_id = ?";
    $params[] = $classFilter;
}

if ($statusFilter === 'voted') {
    $sql .= " AND s.has_voted = 1";
} elseif ($statusFilter === 'unvoted') {
    $sql .= " AND s.has_voted = 0";
}

if ($faceFilter === 'enrolled') {
    $sql .= " AND (s.face_descriptor IS NOT NULL AND s.face_descriptor != '')";
} elseif ($faceFilter === 'not_enrolled') {
    $sql .= " AND (s.face_descriptor IS NULL OR s.face_descriptor = '')";
}

if (!empty($searchQuery)) {
    $sql .= " AND (s.name LIKE ? OR s.attendance_number = ?)";
    $params[] = "%$searchQuery%";
    $params[] = (int)$searchQuery;
}

$sql .= " ORDER BY c.grade ASC, c.name ASC, s.attendance_number ASC LIMIT 300";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Fetch all classes for select dropdowns
$allClasses = $pdo->query("SELECT id, name FROM classes ORDER BY grade ASC, name ASC")->fetchAll();

$pageTitle = 'Kelola Siswa - E-Pilketos v2.0 SMK SIG';
require_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 border-bottom pb-3">
    <div>
        <h1 class="h4 fw-bold text-dark mb-1">Kelola Data Siswa (Daftar Pemilih)</h1>
        <div class="text-muted small">Manajemen data pemilih tetap (DPT), status hak suara, dan biometrik wajah siswa</div>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="../face-poc/enroll.php" target="_blank" class="btn btn-sm btn-outline-primary fw-semibold" title="Buka Registrasi Wajah">
            <i class="bi bi-person-bounding-box me-1"></i> Registrasi Wajah
        </a>
        <a href="../face-poc/index.php" target="_blank" class="btn btn-sm btn-outline-success fw-semibold" title="Buka Bilik Identifikasi Wajah (POC)">
            <i class="bi bi-camera-video me-1"></i> Bilik Wajah (POC)
        </a>
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-file-earmark-arrow-down me-1"></i> Template Import
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li>
                    <a class="dropdown-item small" href="students.php?action=download_template_xlsx">
                        <i class="bi bi-file-earmark-excel text-success me-2"></i> Format Excel (.xlsx)
                    </a>
                </li>
                <li>
                    <a class="dropdown-item small" href="students.php?action=download_template_csv">
                        <i class="bi bi-file-earmark-text text-secondary me-2"></i> Format CSV (.csv)
                    </a>
                </li>
            </ul>
        </div>
        <button type="button" class="btn btn-sm btn-sig-outline fw-semibold" data-bs-toggle="modal" data-bs-target="#modalImportCSV">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Import Excel / CSV
        </button>
        <button type="button" class="btn btn-sm btn-danger px-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalTambahSiswa" style="background-color: var(--maroon-800); border-color: var(--maroon-900);">
            <i class="bi bi-person-plus me-1"></i> Tambah Siswa
        </button>
    </div>
</div>

<!-- Search & Filter Bar -->
<div class="card-inst p-3 mb-4 bg-white">
    <form action="students.php" method="GET" class="row g-2 align-items-center">
        <div class="col-md-3">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control" placeholder="Cari nama atau no. absen..." value="<?= e($searchQuery) ?>">
            </div>
        </div>
        <div class="col-md-3">
            <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="0">-- Semua Kelas --</option>
                <?php foreach ($allClasses as $ac): ?>
                    <option value="<?= (int)$ac['id'] ?>" <?= ($classFilter === (int)$ac['id']) ? 'selected' : '' ?>>
                        <?= e($ac['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="all" <?= ($statusFilter === 'all') ? 'selected' : '' ?>>-- Status Suara --</option>
                <option value="unvoted" <?= ($statusFilter === 'unvoted') ? 'selected' : '' ?>>Belum Memilih</option>
                <option value="voted" <?= ($statusFilter === 'voted') ? 'selected' : '' ?>>Sudah Memilih</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="face" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="all" <?= ($faceFilter === 'all') ? 'selected' : '' ?>>-- Status Biometrik --</option>
                <option value="enrolled" <?= ($faceFilter === 'enrolled') ? 'selected' : '' ?>>Wajah Terdaftar</option>
                <option value="not_enrolled" <?= ($faceFilter === 'not_enrolled') ? 'selected' : '' ?>>Belum Ada Wajah</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-sm btn-sig w-100 py-1">Filter</button>
            <?php if ($classFilter > 0 || $statusFilter !== 'all' || $faceFilter !== 'all' || !empty($searchQuery)): ?>
                <a href="students.php" class="btn btn-sm btn-light border" title="Reset Filter"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Student Table -->
<div class="card-inst">
    <div class="card-inst-header d-flex justify-content-between align-items-center">
        <h2 class="card-inst-title fs-6">Data Pemilih (Menampilkan <?= count($students) ?> Data)</h2>
        <span class="text-muted small">Status suara & biometrik wajah siswa</span>
    </div>
    <div class="card-inst-body p-0">
        <div class="table-responsive">
            <table class="table-inst">
                <thead>
                    <tr>
                        <th style="width: 50px;">No.</th>
                        <th style="width: 80px;">No. Absen</th>
                        <th>Nama Siswa</th>
                        <th>Kelas</th>
                        <th>Status Memilih</th>
                        <th>Biometrik Wajah</th>
                        <th class="text-end" style="width: 170px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-people fs-2 d-block mb-2 text-secondary"></i>
                                <?php if ($classFilter > 0 || $statusFilter !== 'all' || $faceFilter !== 'all' || !empty($searchQuery)): ?>
                                    Tidak ditemukan data siswa yang sesuai dengan filter pencarian.
                                <?php else: ?>
                                    Belum ada data siswa. Data siswa akan <strong>otomatis tercatat di sini</strong> begitu siswa memasukkan nama, kelas, dan nomor absen saat memilih di bilik suara.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($students as $s): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border fw-bold px-2 py-1">
                                        <?= sprintf('%02d', (int)$s['attendance_number']) ?>
                                    </span>
                                </td>
                                <td>
                                    <strong class="text-dark"><?= e($s['name']) ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary border"><?= e($s['class_name']) ?></span>
                                </td>
                                <td>
                                    <?php if ((int)$s['has_voted'] === 1): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            <i class="bi bi-check2-circle me-1"></i> Sudah Memilih
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">
                                            <i class="bi bi-hourglass-split me-1"></i> Belum Memilih
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($s['face_descriptor'])): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle" title="Terdaftar: <?= e($s['face_enrolled_at'] ?? '-') ?>">
                                            <i class="bi bi-check-circle-fill me-1"></i> Terdaftar
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">
                                            <i class="bi bi-dash-circle me-1"></i> Belum
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1 align-items-center">
                                        <!-- Jika Wajah Belum Terdaftar: Tombol Scan Wajah -->
                                        <?php if (empty($s['face_descriptor'])): ?>
                                            <a href="../face-poc/enroll.php?class_id=<?= (int)$s['class_id'] ?>&student_id=<?= (int)$s['id'] ?>" 
                                               target="_blank" 
                                               class="btn btn-sm btn-primary py-0 px-2" 
                                               title="Daftarkan Wajah Siswa di Kiosk Registrasi" 
                                               style="font-size: 11px; background-color: var(--maroon-700); border-color: var(--maroon-800);">
                                                <i class="bi bi-camera me-1"></i>Scan Wajah
                                            </a>
                                        <?php else: ?>
                                            <!-- Jika Wajah Sudah Terdaftar: Tombol Reset Face Recognition -->
                                            <form action="students.php" method="POST" class="d-inline" onsubmit="return confirm('Reset Face Recognition untuk <?= e($s['name']) ?>?\n\nBiometrik wajah lama akan dihapus sehingga siswa ini muncul kembali di daftar registrasi wajah.');">
                                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                <input type="hidden" name="action" value="reset_face">
                                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-warning text-dark py-0 px-2 fw-semibold" title="Reset Face Recognition (Scan Ulang Wajah)" style="font-size: 11px;">
                                                    <i class="bi bi-arrow-repeat text-danger me-1"></i>Reset Wajah
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Edit Modal Trigger -->
                                        <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalEditSiswa<?= (int)$s['id'] ?>" 
                                                style="font-size: 11px;" title="Edit Siswa">
                                            <i class="bi bi-pencil"></i>
                                        </button>

                                        <!-- Delete Student -->
                                        <?php if ((int)$s['has_voted'] !== 1): ?>
                                        <form action="students.php" method="POST" class="d-inline" onsubmit="return confirm('Hapus siswa <?= e($s['name']) ?>?');">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" style="font-size: 11px;" title="Hapus Siswa">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Modal Edit Siswa -->
                                    <div class="modal fade text-start" id="modalEditSiswa<?= (int)$s['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fs-6 fw-bold">Edit Siswa: <?= e($s['name']) ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form action="students.php" method="POST">
                                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                    <input type="hidden" name="action" value="edit">
                                                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">

                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label small fw-bold">Nama Lengkap</label>
                                                            <input type="text" name="name" class="form-control" value="<?= e($s['name']) ?>" required>
                                                        </div>
                                                        <div class="row g-2 mb-3">
                                                            <div class="col-8">
                                                                <label class="form-label small fw-bold">Kelas</label>
                                                                <select name="class_id" class="form-select" required>
                                                                    <?php foreach ($allClasses as $c): ?>
                                                                        <option value="<?= (int)$c['id'] ?>" <?= ((int)$s['class_id'] === (int)$c['id']) ? 'selected' : '' ?>>
                                                                            <?= e($c['name']) ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-4">
                                                                <label class="form-label small fw-bold">No. Absen</label>
                                                                <input type="number" name="attendance_number" class="form-control" value="<?= (int)$s['attendance_number'] ?>" min="1" required>
                                                            </div>
                                                        </div>
                                                        <div class="small text-muted mb-2">Status memilih: <strong><?= ((int)$s['has_voted'] === 1) ? 'Sudah memilih' : 'Belum memilih' ?></strong>. Status ini hanya dapat diubah oleh proses pemungutan suara anonim.</div>

                                                        <?php if (!empty($s['face_descriptor'])): ?>
                                                            <div class="mt-3 pt-2 border-top">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="reset_face" id="editResetFace<?= $s['id'] ?>" value="1">
                                                                    <label class="form-check-label small text-danger fw-semibold" for="editResetFace<?= $s['id'] ?>">
                                                                        <i class="bi bi-arrow-repeat me-1"></i> Reset Face Recognition
                                                                    </label>
                                                                    <div class="form-text text-muted" style="font-size: 11px;">Centang opsi ini untuk menghapus biometrik wajah siswa agar muncul kembali di daftar registrasi wajah.</div>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" class="btn btn-danger btn-sm">Simpan Perubahan</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah Siswa -->
<div class="modal fade" id="modalTambahSiswa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold">Tambah Data Siswa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="students.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="add">

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nama Lengkap Siswa</label>
                        <input type="text" name="name" class="form-control" placeholder="Masukkan nama siswa" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-8">
                            <label class="form-label small fw-bold">Kelas</label>
                            <select name="class_id" class="form-select" required>
                                <option value="">-- Pilih Kelas --</option>
                                <?php foreach ($allClasses as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-bold">No. Absen</label>
                            <input type="number" name="attendance_number" class="form-control" placeholder="1" min="1" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger btn-sm">Tambah Siswa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Import Excel / CSV -->
<div class="modal fade" id="modalImportCSV" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold">
                    <i class="bi bi-file-earmark-spreadsheet text-success me-1"></i> Import Data Siswa & Kelas
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="students.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="import_file">

                <div class="modal-body">
                    <p class="small text-secondary mb-3">
                        Unggah berkas <strong>Excel (.xlsx)</strong> atau <strong>CSV (.csv)</strong> berisi daftar nama dan kelas siswa.
                    </p>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Pilih Berkas Excel / CSV</label>
                        <input type="file" name="student_file" class="form-control" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required>
                    </div>

                    <div class="p-3 bg-light rounded border small text-muted mb-3">
                        <div class="fw-bold mb-1.5 text-dark"><i class="bi bi-magic me-1 text-primary"></i> Fitur Pintar Import:</div>
                        <ul class="ps-3 mb-0" style="line-height: 1.6;">
                            <li><strong>Auto-Daftar Kelas:</strong> Bila nama kelas di Excel belum ada di sistem, sistem akan <em>otomatis membuat kelas baru</em>.</li>
                            <li><strong>Fleksibel:</strong> Cukup sediakan kolom <code>Nama Siswa</code> dan <code>Kelas</code> (nomor absen otomatis diurutkan jika tidak diisi).</li>
                            <li><strong>Dukungan File:</strong> Mendukung format modern <strong>.xlsx</strong> (Microsoft Excel, Google Sheets) & <strong>.csv</strong>.</li>
                        </ul>
                    </div>

                    <div class="d-flex align-items-center justify-content-between p-2 bg-white rounded border flex-wrap gap-2">
                        <span class="small text-muted fw-semibold">Unduh Template:</span>
                        <div class="btn-group btn-group-sm">
                            <a href="students.php?action=download_template_xlsx" class="btn btn-outline-success">
                                <i class="bi bi-file-earmark-excel me-1"></i> Excel (.xlsx)
                            </a>
                            <a href="students.php?action=download_template_csv" class="btn btn-outline-secondary">
                                <i class="bi bi-file-earmark-text me-1"></i> CSV (.csv)
                            </a>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger btn-sm px-3" style="background-color: var(--maroon-800); border-color: var(--maroon-900);">
                        <i class="bi bi-upload me-1"></i> Mulai Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/admin_footer.php'; ?>
