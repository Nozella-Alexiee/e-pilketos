<?php
/**
 * E-Pilketos v2.0 - Manajemen Pengguna Admin
 * SMK SIG (Sekolah Menengah Kejuruan SIG)
 * Khusus Super Administrator
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

require_superadmin();
$pdo = getDb();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Sesi formulir kedaluwarsa. Silakan coba kembali.');
        header('Location: users.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $username = strtolower(trim($_POST['username'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $role = in_array($_POST['role'] ?? '', ['admin', 'superadmin'], true) ? $_POST['role'] : 'admin';

        if (empty($username) || empty($name) || empty($password)) {
            set_flash('danger', 'Semua kolom formulir wajib diisi.');
        } elseif (!preg_match('/^[a-z0-9_]{3,30}$/', $username)) {
            set_flash('danger', 'Username hanya boleh berisi huruf kecil, angka, dan underscore (3-30 karakter).');
        } elseif (strlen($password) < 6) {
            set_flash('danger', 'Kata sandi minimal terdiri dari 6 karakter.');
        } else {
            try {
                $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $check->execute([$username]);
                if ($check->fetchColumn()) {
                    set_flash('danger', "Username '$username' sudah terdaftar.");
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, name, role) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$username, $hash, $name, $role]);

                    log_activity($pdo, 'CREATE_USER', "Menambahkan akun baru: '$username' sebagai " . ucfirst($role));
                    set_flash('success', "Akun " . ucfirst($role) . " '<strong>$username</strong>' berhasil dibuat.");
                }
            } catch (PDOException $e) {
                set_flash('danger', 'Gagal membuat user: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $role = in_array($_POST['role'] ?? '', ['admin', 'superadmin'], true) ? $_POST['role'] : 'admin';
        $newPassword = (string)($_POST['new_password'] ?? '');

        if ($id <= 0 || empty($name)) {
            set_flash('danger', 'Data pengeditan tidak valid.');
        } else {
            try {
                $stmtGet = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
                $stmtGet->execute([$id]);
                $targetUser = $stmtGet->fetch();

                if (!$targetUser) {
                    set_flash('danger', 'User tidak ditemukan.');
                } else {
                    // Prevent downgrading self if currently logged in
                    if ($id === (int)$_SESSION['admin_id'] && $role !== 'superadmin') {
                        set_flash('danger', 'Anda tidak dapat menurunkan role akun Anda sendiri saat sedang login.');
                    } else {
                        if (!empty($newPassword)) {
                            if (strlen($newPassword) < 6) {
                                set_flash('danger', 'Kata sandi baru minimal 6 karakter.');
                                header('Location: users.php');
                                exit;
                            }
                            $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                            $stmt = $pdo->prepare("UPDATE users SET name = ?, role = ?, password_hash = ? WHERE id = ?");
                            $stmt->execute([$name, $role, $hash, $id]);
                            log_activity($pdo, 'UPDATE_USER', "Mengubah data dan reset kata sandi user '{$targetUser['username']}'");
                        } else {
                            $stmt = $pdo->prepare("UPDATE users SET name = ?, role = ? WHERE id = ?");
                            $stmt->execute([$name, $role, $id]);
                            log_activity($pdo, 'UPDATE_USER', "Mengubah data profil user '{$targetUser['username']}' ($role)");
                        }
                        set_flash('success', "Data user '<strong>{$targetUser['username']}</strong>' berhasil diperbarui.");
                    }
                }
            } catch (PDOException $e) {
                set_flash('danger', 'Gagal memperbarui user: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id === (int)$_SESSION['admin_id']) {
            set_flash('danger', 'Anda tidak dapat menghapus akun Anda sendiri.');
        } else {
            try {
                $stmtGet = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
                $stmtGet->execute([$id]);
                $targetUser = $stmtGet->fetch();

                if ($targetUser) {
                    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
                    log_activity($pdo, 'DELETE_USER', "Menghapus akun user '{$targetUser['username']}' (" . $targetUser['role'] . ")");
                    set_flash('success', "Akun '<strong>{$targetUser['username']}</strong>' berhasil dihapus.");
                } else {
                    set_flash('danger', 'User tidak ditemukan.');
                }
            } catch (PDOException $e) {
                set_flash('danger', 'Gagal menghapus user: ' . $e->getMessage());
            }
        }
    }

    header('Location: users.php');
    exit;
}

// Fetch all users
$stmt = $pdo->query("SELECT id, username, name, role, created_at FROM users ORDER BY (role = 'superadmin') DESC, id ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalUsers = count($users);
$totalSuperadmin = count(array_filter($users, fn($u) => $u['role'] === 'superadmin'));
$totalAdminBiasa = count(array_filter($users, fn($u) => $u['role'] === 'admin'));

$pageTitle = 'Kelola Pengguna Admin - E-Pilketos v2.0';
require_once __DIR__ . '/../templates/admin_header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h1 class="h4 fw-bold text-dark mb-1">
            <i class="bi bi-people-fill text-danger me-2"></i>Kelola Pengguna Admin
        </h1>
        <div class="text-muted small">Manajemen akun administrator dan pembagian hak akses sistem E-Pilketos SMK SIG.</div>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-sig btn-sm px-3" data-bs-toggle="modal" data-bs-target="#modalAddUser">
            <i class="bi bi-person-plus-fill me-1"></i> Tambah Pengguna Baru
        </button>
        <a href="logs.php" class="btn btn-outline-secondary btn-sm px-3">
            <i class="bi bi-clock-history me-1"></i> Lihat Log Aktivitas
        </a>
    </div>
</div>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card-inst p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Total Pengguna Admin</div>
                    <div class="fs-4 fw-bold text-dark mt-1"><?= $totalUsers ?></div>
                    <div class="small text-muted mt-0.5">Akun terdaftar di sistem</div>
                </div>
                <div class="rounded-circle bg-primary-subtle text-primary p-2.5 d-inline-flex">
                    <i class="bi bi-person-badge-fill fs-5"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card-inst p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Super Administrator</div>
                    <div class="fs-4 fw-bold text-warning mt-1"><?= $totalSuperadmin ?></div>
                    <div class="small text-muted mt-0.5">Akses penuh + Audit Log</div>
                </div>
                <div class="rounded-circle bg-warning-subtle text-warning p-2.5 d-inline-flex">
                    <i class="bi bi-shield-shaded fs-5"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card-inst p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Admin Pemilihan</div>
                    <div class="fs-4 fw-bold text-secondary mt-1"><?= $totalAdminBiasa ?></div>
                    <div class="small text-muted mt-0.5">Akses operasional data</div>
                </div>
                <div class="rounded-circle bg-secondary-subtle text-secondary p-2.5 d-inline-flex">
                    <i class="bi bi-person-gear fs-5"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Users Table -->
<div class="card-inst bg-white">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 50px;" class="text-center">No</th>
                    <th>Nama Pengguna (Username)</th>
                    <th>Nama Lengkap</th>
                    <th>Tingkat Hak Akses (Role)</th>
                    <th>Terdaftar Pada</th>
                    <th style="width: 130px;" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $idx => $u): ?>
                    <tr>
                        <td class="text-center text-muted small"><?= $idx + 1 ?></td>
                        <td>
                            <span class="font-monospace fw-bold text-dark"><?= e($u['username']) ?></span>
                            <?php if ($u['id'] == $_SESSION['admin_id']): ?>
                                <span class="badge bg-primary-subtle text-primary ms-1" style="font-size: 10px;">Anda</span>
                            <?php endif; ?>
                        </td>
                        <td class="fw-semibold text-dark"><?= e($u['name']) ?></td>
                        <td>
                            <?php if ($u['role'] === 'superadmin'): ?>
                                <span class="badge bg-warning text-dark border border-warning-subtle fw-bold">
                                    <i class="bi bi-shield-fill-check me-1"></i> SUPERADMIN
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                    <i class="bi bi-shield me-1"></i> ADMIN BIASA
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= e(format_date_id($u['created_at'])) ?></td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary" title="Edit Akun"
                                    onclick="openEditUserModal(<?= htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8') ?>)">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <?php if ($u['id'] != $_SESSION['admin_id']): ?>
                                    <button type="button" class="btn btn-outline-danger" title="Hapus Akun"
                                        onclick="confirmDeleteUser(<?= (int)$u['id'] ?>, '<?= e(addslashes($u['username'])) ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-outline-secondary disabled" title="Tidak dapat menghapus akun sendiri">
                                        <i class="bi bi-slash-circle"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Add User -->
<div class="modal fade" id="modalAddUser" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add">
            <div class="modal-header">
                <h5 class="modal-title h6 fw-bold">Tambah Pengguna Admin Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Nama Pengguna (Username) <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control form-control-sm font-monospace" placeholder="Contoh: panitia_osis" required pattern="[a-z0-9_]{3,30}">
                    <div class="form-text small">Hanya huruf kecil, angka, dan underscore (3-30 karakter).</div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Nama Lengkap Petugas <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control form-control-sm" placeholder="Contoh: Budi Santoso, S.Kom." required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Tingkat Akses (Role) <span class="text-danger">*</span></label>
                    <select name="role" class="form-select form-select-sm" required>
                        <option value="admin">Admin Biasa (Kelola Pemilihan &amp; DPT)</option>
                        <option value="superadmin">Superadmin (Akses Penuh + Log Aktivitas + Kelola Admin)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Kata Sandi Awal <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control form-control-sm" placeholder="Minimal 6 karakter" required minlength="6">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sig btn-sm px-3">Simpan Pengguna</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit User -->
<div class="modal fade" id="modalEditUser" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editUserId">
            <div class="modal-header">
                <h5 class="modal-title h6 fw-bold">Edit Data Admin: <span id="editUserUsernameLabel" class="text-danger font-monospace"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="editUserName" class="form-control form-control-sm" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Tingkat Akses (Role) <span class="text-danger">*</span></label>
                    <select name="role" id="editUserRole" class="form-select form-select-sm" required>
                        <option value="admin">Admin Biasa</option>
                        <option value="superadmin">Superadmin</option>
                    </select>
                </div>
                <div class="mb-3 border-top pt-3">
                    <label class="form-label small fw-semibold">Ubah Kata Sandi (Opsional)</label>
                    <input type="password" name="new_password" class="form-control form-control-sm" placeholder="Kosongkan jika tidak ingin mengubah sandi" minlength="6">
                    <div class="form-text small">Hanya diisi jika ingin mereset kata sandi admin ini (minimal 6 karakter).</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sig btn-sm px-3">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form -->
<form id="formDeleteUser" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteUserId">
</form>

<script>
function openEditUserModal(user) {
    document.getElementById('editUserId').value = user.id;
    document.getElementById('editUserUsernameLabel').textContent = user.username;
    document.getElementById('editUserName').value = user.name;
    document.getElementById('editUserRole').value = user.role;
    new bootstrap.Modal(document.getElementById('modalEditUser')).show();
}

function confirmDeleteUser(id, username) {
    if (confirm('Yakin ingin menghapus akun admin "' + username + '"? Akses masuk untuk user ini akan dicabut permanen.')) {
        document.getElementById('deleteUserId').value = id;
        document.getElementById('formDeleteUser').submit();
    }
}
</script>

<?php require_once __DIR__ . '/../templates/admin_footer.php'; ?>
