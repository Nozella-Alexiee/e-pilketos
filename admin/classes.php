<?php
/**
 * E-Pilketos v2.0 - Kelola Kelas
 * SMK SIG (Sekolah Menengah Kejuruan SIG)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

require_admin();
$pdo = getDb();

// Handle Actions (Add, Edit, Delete, Toggle)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Sesi keamanan tidak valid.');
        header('Location: classes.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $grade = trim($_POST['grade'] ?? 'X');
        $major = trim($_POST['major'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (empty($name) || empty($major)) {
            set_flash('danger', 'Nama kelas dan jurusan wajib diisi.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO classes (name, grade, major, is_active) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $grade, $major, $isActive]);
                $newId = (int)$pdo->lastInsertId();
                log_activity($pdo, 'ADD_CLASS', "Menambahkan kelas baru: '$name' ($grade - $major, ID: $newId)");
                set_flash('success', "Kelas '$name' berhasil ditambahkan.");
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'UNIQUE') !== false) {
                    set_flash('danger', "Kelas dengan nama '$name' sudah terdaftar.");
                } else {
                    set_flash('danger', 'Gagal menambahkan kelas: ' . $e->getMessage());
                }
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $grade = trim($_POST['grade'] ?? 'X');
        $major = trim($_POST['major'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($id > 0 && !empty($name) && !empty($major)) {
            try {
                $stmt = $pdo->prepare("UPDATE classes SET name = ?, grade = ?, major = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$name, $grade, $major, $isActive, $id]);
                log_activity($pdo, 'EDIT_CLASS', "Memperbarui kelas ID $id: '$name' ($grade - $major)");
                set_flash('success', "Data kelas '$name' berhasil diperbarui.");
            } catch (PDOException $e) {
                set_flash('danger', 'Gagal memperbarui kelas: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'toggle_active') {
        $id = (int)($_POST['id'] ?? 0);
        $status = (int)($_POST['current_status'] ?? 1);
        $newStatus = ($status === 1) ? 0 : 1;
        $pdo->prepare("UPDATE classes SET is_active = ? WHERE id = ?")->execute([$newStatus, $id]);
        log_activity($pdo, 'TOGGLE_CLASS', "Mengubah status aktif kelas ID $id menjadi " . ($newStatus ? 'Aktif' : 'Non-aktif'));
        set_flash('success', 'Status kelas berhasil diperbarui.');
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        // Check student count first
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE class_id = ?");
        $stmt->execute([$id]);
        $studentCount = (int)$stmt->fetchColumn();

        if ($studentCount > 0) {
            set_flash('danger', "Kelas tidak dapat dihapus karena masih memiliki $studentCount data siswa terdaftar. Hapus data siswa terlebih dahulu atau nonaktifkan kelas.");
        } else {
            $className = $pdo->query("SELECT name FROM classes WHERE id = $id")->fetchColumn() ?: "ID $id";
            $pdo->prepare("DELETE FROM classes WHERE id = ?")->execute([$id]);
            log_activity($pdo, 'DELETE_CLASS', "Menghapus kelas ID $id: '$className'");
            set_flash('success', 'Kelas berhasil dihapus.');
        }
    }

    header('Location: classes.php');
    exit;
}

// Fetch classes with student counts
$stmt = $pdo->query("SELECT c.*, COUNT(s.id) as total_students,
    SUM(CASE WHEN s.has_voted = 1 THEN 1 ELSE 0 END) as voted_students
    FROM classes c
    LEFT JOIN students s ON c.id = s.class_id
    GROUP BY c.id
    ORDER BY c.grade ASC, c.name ASC");
$classes = $stmt->fetchAll();

$pageTitle = 'Kelola Kelas - E-Pilketos v2.0 SMK SIG';
require_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 border-bottom pb-3">
    <div>
        <h1 class="h4 fw-bold text-dark mb-1">Kelola Data Kelas</h1>
        <div class="text-muted small">Kelola daftar kelas aktif, tingkat, dan jurusan pemilih SMK SIG</div>
    </div>
    <div>
        <button type="button" class="btn btn-sm btn-danger px-3 py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalTambahKelas" style="background-color: var(--maroon-800); border-color: var(--maroon-900);">
            <i class="bi bi-plus-lg me-1"></i> Tambah Kelas Baru
        </button>
    </div>
</div>

<div class="card-inst">
    <div class="card-inst-header d-flex justify-content-between align-items-center">
        <h2 class="card-inst-title fs-6">Daftar Kelas (Total: <?= count($classes) ?> Kelas)</h2>
        <span class="text-muted small">Kelas yang berstatus Aktif otomatis muncul pada halaman pemilihan siswa</span>
    </div>
    <div class="card-inst-body p-0">
        <div class="table-responsive">
            <table class="table-inst">
                <thead>
                    <tr>
                        <th style="width: 50px;">No.</th>
                        <th>Nama Kelas</th>
                        <th>Tingkat</th>
                        <th>Kompetensi Keahlian (Jurusan)</th>
                        <th>Jumlah Siswa</th>
                        <th>Status</th>
                        <th class="text-end" style="width: 180px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($classes)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">Belum ada data kelas yang terdaftar.</td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($classes as $c): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td>
                                    <strong class="text-dark"><?= e($c['name']) ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary border">Tingkat <?= e($c['grade']) ?></span>
                                </td>
                                <td><?= e($c['major']) ?></td>
                                <td>
                                    <span class="fw-semibold text-dark"><?= (int)$c['total_students'] ?></span> siswa
                                    <small class="text-muted">(<?= (int)$c['voted_students'] ?> memilih)</small>
                                </td>
                                <td>
                                    <?php if ((int)$c['is_active'] === 1): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <!-- Toggle Status -->
                                        <form action="classes.php" method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="action" value="toggle_active">
                                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                            <input type="hidden" name="current_status" value="<?= (int)$c['is_active'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary py-0 px-2" title="<?= ($c['is_active'] ? 'Nonaktifkan' : 'Aktifkan') ?>" style="font-size: 11px;">
                                                <i class="bi <?= ($c['is_active'] ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted') ?>"></i>
                                            </button>
                                        </form>

                                        <!-- Edit Modal Trigger -->
                                        <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalEditKelas<?= (int)$c['id'] ?>"
                                                style="font-size: 11px;" title="Edit Kelas">
                                            <i class="bi bi-pencil"></i>
                                        </button>

                                        <!-- Delete Button -->
                                        <form action="classes.php" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus kelas <?= e($c['name']) ?>?');">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" style="font-size: 11px;" title="Hapus Kelas">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Modal Edit Kelas -->
                                    <div class="modal fade text-start" id="modalEditKelas<?= (int)$c['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fs-6 fw-bold">Edit Kelas: <?= e($c['name']) ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form action="classes.php" method="POST">
                                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                    <input type="hidden" name="action" value="edit">
                                                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">

                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label small fw-bold">Nama Kelas</label>
                                                            <input type="text" name="name" class="form-control" value="<?= e($c['name']) ?>" required>
                                                            <div class="form-text">Contoh: XI RPL 1, XII TKRO 2</div>
                                                        </div>
                                                        <div class="row g-2 mb-3">
                                                            <div class="col-6">
                                                                <label class="form-label small fw-bold">Tingkat</label>
                                                                <select name="grade" class="form-select" required>
                                                                    <option value="X" <?= ($c['grade'] === 'X') ? 'selected' : '' ?>>Tingkat X</option>
                                                                    <option value="XI" <?= ($c['grade'] === 'XI') ? 'selected' : '' ?>>Tingkat XI</option>
                                                                    <option value="XII" <?= ($c['grade'] === 'XII') ? 'selected' : '' ?>>Tingkat XII</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-6">
                                                                <label class="form-label small fw-bold">Jurusan</label>
                                                                <input type="text" name="major" class="form-control" value="<?= e($c['major']) ?>" placeholder="RPL, TOI, dll" required>
                                                            </div>
                                                        </div>
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" name="is_active" id="editActive<?= $c['id'] ?>" value="1" <?= ($c['is_active'] ? 'checked' : '') ?>>
                                                            <label class="form-check-label small" for="editActive<?= $c['id'] ?>">Status Kelas Aktif</label>
                                                        </div>
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

<!-- Modal Tambah Kelas -->
<div class="modal fade" id="modalTambahKelas" tabindex="-1" aria-labelledby="modalTambahKelasLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold" id="modalTambahKelasLabel">Tambah Kelas Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="classes.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="add">

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nama Kelas</label>
                        <input type="text" name="name" class="form-control" placeholder="Contoh: XI RPL 1" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Tingkat</label>
                            <select name="grade" class="form-select" required>
                                <option value="X">Tingkat X</option>
                                <option value="XI" selected>Tingkat XI</option>
                                <option value="XII">Tingkat XII</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Jurusan</label>
                            <input type="text" name="major" class="form-control" placeholder="RPL, TKRO, TOI, TP, KI" required>
                        </div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="addActive" value="1" checked>
                        <label class="form-check-label small" for="addActive">Status Aktif (Ditampilkan pada bilik suara)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger btn-sm">Tambah Kelas</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/admin_footer.php'; ?>
