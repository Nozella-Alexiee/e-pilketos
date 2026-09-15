<?php
/**
 * E-Pilketos v2.0 - Kelola Kandidat Ketua OSIS
 * SMK SIG (Sekolah Menengah Kejuruan SIG)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

require_admin();
$pdo = getDb();

// Handle Image Upload Helper
function handle_candidate_photo_upload(?array $file): ?string {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    $fileMime = mime_content_type($file['tmp_name']);
    if (!in_array($fileMime, $allowedMimes)) {
        throw new Exception('Format file harus berupa JPG, PNG, atau WEBP.');
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        throw new Exception('Ukuran foto maksimal 2MB.');
    }

    $ext = match ($fileMime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => 'jpg'
    };

    $targetDir = __DIR__ . '/../uploads/candidates/';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $filename = 'kandidat_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $destPath = $targetDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new Exception('Gagal memindahkan file upload.');
    }

    return 'uploads/candidates/' . $filename;
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Sesi keamanan formulir kedaluwarsa.');
        header('Location: candidates.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $number = trim($_POST['number'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $class = trim($_POST['class'] ?? '');
        $vision = trim($_POST['vision'] ?? '');
        $mission = trim($_POST['mission'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (empty($number) || empty($name) || empty($vision) || empty($mission)) {
            set_flash('danger', 'Nomor urut, nama, visi, dan misi wajib diisi.');
        } else {
            try {
                $photoPath = '';
                if (!empty($_FILES['photo']['tmp_name'])) {
                    $photoPath = handle_candidate_photo_upload($_FILES['photo']) ?? '';
                }

                $stmt = $pdo->prepare("INSERT INTO candidates (number, name, class, photo, vision, mission, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$number, $name, $class, $photoPath, $vision, $mission, $isActive]);
                $newId = (int)$pdo->lastInsertId();
                log_activity($pdo, 'ADD_CANDIDATE', "Menambahkan kandidat nomor urut $number: '$name' ($class, ID: $newId)");
                set_flash('success', "Kandidat nomor urut $number ($name) berhasil ditambahkan.");
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'UNIQUE') !== false) {
                    set_flash('danger', "Nomor urut $number sudah digunakan oleh kandidat lain.");
                } else {
                    set_flash('danger', 'Gagal menambahkan kandidat: ' . $e->getMessage());
                }
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $number = trim($_POST['number'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $class = trim($_POST['class'] ?? '');
        $vision = trim($_POST['vision'] ?? '');
        $mission = trim($_POST['mission'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($id > 0 && !empty($number) && !empty($name)) {
            try {
                // Fetch existing photo
                $curr = $pdo->prepare("SELECT photo FROM candidates WHERE id = ?");
                $curr->execute([$id]);
                $oldPhoto = $curr->fetchColumn() ?: '';

                $photoPath = $oldPhoto;
                if (!empty($_FILES['photo']['tmp_name'])) {
                    $newPhoto = handle_candidate_photo_upload($_FILES['photo']);
                    if ($newPhoto) {
                        $photoPath = $newPhoto;
                    }
                }

                $stmt = $pdo->prepare("UPDATE candidates SET number = ?, name = ?, class = ?, photo = ?, vision = ?, mission = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$number, $name, $class, $photoPath, $vision, $mission, $isActive, $id]);
                log_activity($pdo, 'EDIT_CANDIDATE', "Memperbarui data kandidat ID $id: No. $number - '$name' ($class)");
                set_flash('success', "Data kandidat nomor $number berhasil diperbarui.");
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'UNIQUE') !== false) {
                    set_flash('danger', "Nomor urut $number sudah digunakan kandidat lain.");
                } else {
                    set_flash('danger', 'Gagal memperbarui kandidat: ' . $e->getMessage());
                }
            }
        }
    } elseif ($action === 'toggle_active') {
        $id = (int)($_POST['id'] ?? 0);
        $currStatus = (int)($_POST['current_status'] ?? 1);
        $newStatus = ($currStatus === 1) ? 0 : 1;
        $pdo->prepare("UPDATE candidates SET is_active = ? WHERE id = ?")->execute([$newStatus, $id]);
        log_activity($pdo, 'TOGGLE_CANDIDATE', "Mengubah status aktif kandidat ID $id menjadi " . ($newStatus ? 'Aktif' : 'Non-aktif'));
        set_flash('success', 'Status keaktifan kandidat berhasil diubah.');
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        // Check votes
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE candidate_id = ?");
        $stmt->execute([$id]);
        $votesCount = (int)$stmt->fetchColumn();

        if ($votesCount > 0) {
            set_flash('danger', "Kandidat tidak dapat dihapus karena sudah memiliki $votesCount suara masuk. Anda dapat menonaktifkan status kandidat.");
        } else {
            $candCheck = $pdo->prepare("SELECT number, name FROM candidates WHERE id = ?");
            $candCheck->execute([$id]);
            $candRow = $candCheck->fetch(PDO::FETCH_ASSOC);

            $pdo->prepare("DELETE FROM candidates WHERE id = ?")->execute([$id]);
            $candName = $candRow ? "No. {$candRow['number']} ({$candRow['name']})" : "ID $id";
            log_activity($pdo, 'DELETE_CANDIDATE', "Menghapus kandidat: $candName");
            set_flash('success', 'Data kandidat berhasil dihapus.');
        }
    }

    header('Location: candidates.php');
    exit;
}

// Fetch all candidates
$stmt = $pdo->query("SELECT c.*, COUNT(v.id) as vote_count 
    FROM candidates c 
    LEFT JOIN votes v ON c.id = v.candidate_id 
    GROUP BY c.id 
    ORDER BY c.number ASC");
$candidates = $stmt->fetchAll();

$pageTitle = 'Kelola Kandidat - E-Pilketos v2.0 SMK SIG';
require_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 border-bottom pb-3">
    <div>
        <h1 class="h4 fw-bold text-dark mb-1">Kelola Data Kandidat Ketua OSIS</h1>
        <div class="text-muted small">Manajemen nomor urut, foto resmi, profil, serta visi dan misi pasangan calon</div>
    </div>
    <div>
        <button type="button" class="btn btn-sm btn-danger px-3 py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalTambahKandidat" style="background-color: var(--maroon-800); border-color: var(--maroon-900);">
            <i class="bi bi-person-plus-fill me-1"></i> Tambah Kandidat Baru
        </button>
    </div>
</div>

<div class="card-inst">
    <div class="card-inst-header d-flex justify-content-between align-items-center">
        <h2 class="card-inst-title fs-6">Daftar Pasangan Calon (Total: <?= count($candidates) ?> Kandidat)</h2>
        <span class="text-muted small">Nomor urut unik digunakan sebagai identifikasi pada surat suara digital</span>
    </div>
    <div class="card-inst-body p-0">
        <div class="table-responsive">
            <table class="table-inst">
                <thead>
                    <tr>
                        <th style="width: 80px;">No. Urut</th>
                        <th style="width: 70px;">Foto</th>
                        <th>Nama Calon</th>
                        <th>Kelas</th>
                        <th>Visi & Misi</th>
                        <th>Suara</th>
                        <th>Status</th>
                        <th class="text-end" style="width: 170px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($candidates)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">Belum ada kandidat yang didaftarkan.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($candidates as $cand): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-danger text-white fs-6 px-2.5 py-1">
                                        <?= e($cand['number']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="rounded border bg-light" style="width: 48px; height: 48px; overflow: hidden;">
                                        <?php if (!empty($cand['photo']) && file_exists(__DIR__ . '/../' . $cand['photo'])): ?>
                                            <img src="../<?= e($cand['photo']) ?>" class="w-100 h-100 object-fit-cover" alt="Foto">
                                        <?php else: ?>
                                            <div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted">
                                                <i class="bi bi-person"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <strong class="text-dark d-block"><?= e($cand['name']) ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary border"><?= e($cand['class']) ?></span>
                                </td>
                                <td>
                                    <div class="small text-muted" style="max-width: 280px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <strong>Visi:</strong> <?= e($cand['vision']) ?>
                                    </div>
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" style="font-size: 11px;"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalPreviewCand<?= $cand['id'] ?>">
                                        Lihat Visi-Misi &raquo;
                                    </button>
                                </td>
                                <td>
                                    <strong class="text-dark"><?= (int)$cand['vote_count'] ?></strong> suara
                                </td>
                                <td>
                                    <?php if ((int)$cand['is_active'] === 1): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <!-- Toggle status -->
                                        <form action="candidates.php" method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="action" value="toggle_active">
                                            <input type="hidden" name="id" value="<?= (int)$cand['id'] ?>">
                                            <input type="hidden" name="current_status" value="<?= (int)$cand['is_active'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 11px;" title="<?= ($cand['is_active'] ? 'Nonaktifkan' : 'Aktifkan') ?>">
                                                <i class="bi <?= ($cand['is_active'] ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted') ?>"></i>
                                            </button>
                                        </form>

                                        <!-- Edit Trigger -->
                                        <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 11px;"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalEditCand<?= (int)$cand['id'] ?>" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>

                                        <!-- Delete Candidate -->
                                        <form action="candidates.php" method="POST" class="d-inline" onsubmit="return confirm('Hapus kandidat <?= e($cand['name']) ?>?');">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$cand['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" style="font-size: 11px;" title="Hapus">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Modal Detail Preview -->
                                    <div class="modal fade text-start" id="modalPreviewCand<?= $cand['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fs-6 fw-bold">Profil Paslon <?= e($cand['number']) ?>: <?= e($cand['name']) ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <span class="small fw-bold text-muted text-uppercase">Visi:</span>
                                                        <p class="bg-light p-2.5 rounded border small mb-0"><?= nl2br(e($cand['vision'])) ?></p>
                                                    </div>
                                                    <div>
                                                        <span class="small fw-bold text-muted text-uppercase">Misi:</span>
                                                        <p class="bg-light p-2.5 rounded border small mb-0"><?= nl2br(e($cand['mission'])) ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Modal Edit Candidate -->
                                    <div class="modal fade text-start" id="modalEditCand<?= (int)$cand['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fs-6 fw-bold">Edit Paslon Nomor <?= e($cand['number']) ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form action="candidates.php" method="POST" enctype="multipart/form-data">
                                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                    <input type="hidden" name="action" value="edit">
                                                    <input type="hidden" name="id" value="<?= (int)$cand['id'] ?>">

                                                    <div class="modal-body">
                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-3">
                                                                <label class="form-label small fw-bold">Nomor Urut</label>
                                                                <input type="text" name="number" class="form-control" value="<?= e($cand['number']) ?>" required>
                                                            </div>
                                                            <div class="col-md-5">
                                                                <label class="form-label small fw-bold">Nama Lengkap Calon</label>
                                                                <input type="text" name="name" class="form-control" value="<?= e($cand['name']) ?>" required>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label small fw-bold">Kelas</label>
                                                                <input type="text" name="class" class="form-control" value="<?= e($cand['class']) ?>" placeholder="XI RPL 1" required>
                                                            </div>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label small fw-bold">Ganti Foto Resmi (Opsional)</label>
                                                            <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png,image/webp">
                                                            <div class="form-text">Biarkan kosong jika tidak ingin mengubah foto saat ini. Format JPG/PNG/WEBP (Maks 2MB).</div>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label small fw-bold">Visi</label>
                                                            <textarea name="vision" class="form-control" rows="3" required><?= e($cand['vision']) ?></textarea>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label small fw-bold">Misi</label>
                                                            <textarea name="mission" class="form-control" rows="4" required><?= e($cand['mission']) ?></textarea>
                                                        </div>

                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" name="is_active" id="editCandAct<?= $cand['id'] ?>" value="1" <?= ($cand['is_active'] ? 'checked' : '') ?>>
                                                            <label class="form-check-label small" for="editCandAct<?= $cand['id'] ?>">Status Paslon Aktif (Ditampilkan pada bilik suara)</label>
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

<!-- Modal Tambah Kandidat -->
<div class="modal fade" id="modalTambahKandidat" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold">Tambah Paslon Kandidat Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="candidates.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="add">

                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Nomor Urut</label>
                            <input type="text" name="number" class="form-control" placeholder="04" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-bold">Nama Lengkap</label>
                            <input type="text" name="name" class="form-control" placeholder="Nama kandidat ketua OSIS" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Kelas</label>
                            <input type="text" name="class" class="form-control" placeholder="XI TKRO 1" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Foto Resmi Calon</label>
                        <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text">Unggah foto berseragam atau jas almamater (Maks 2MB, JPG/PNG/WEBP).</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Visi</label>
                        <textarea name="vision" class="form-control" rows="3" placeholder="Tuliskan visi calon ketua OSIS..." required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Misi</label>
                        <textarea name="mission" class="form-control" rows="4" placeholder="1. Misi pertama&#10;2. Misi kedua&#10;3. Misi ketiga..." required></textarea>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="addCandAct" value="1" checked>
                        <label class="form-check-label small" for="addCandAct">Status Aktif (Tampil pada surat suara digital)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger btn-sm">Tambah Kandidat</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/admin_footer.php'; ?>
