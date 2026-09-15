<?php
/**
 * Admin Header Template
 * SMK SIG (Sekolah Menengah Kejuruan SIG)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

require_admin();

$pdo = getDb();
$settings = get_election_settings($pdo);
$pageTitle = $pageTitle ?? 'Admin Panel - E-Pilketos v2.0';
$currentScript = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons (Local first + CDN fallback) -->
    <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- SMK SIG Institutional CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/logo-smk-sig.png">

    <!-- PWA Settings & Manifest -->
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#511524">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="E-Pilketos Admin">
    <link rel="apple-touch-icon" href="../assets/images/icons/apple-touch-icon.png">
</head>
<body class="bg-light">

<!-- Admin Top Header -->
<header class="school-topbar">
    <div class="container-fluid px-4 d-flex align-items-center justify-content-between">
        <a href="index.php" class="school-brand-lockup">
            <img src="../assets/images/logo-smk-sig.png" alt="Logo SMK SIG">
            <div class="school-brand-text">
                <div class="school-brand-title">E-Pilketos v2.0 — Admin Panel</div>
                <div class="school-brand-subtitle">SMK SIG (Sekolah Menengah Kejuruan SIG)</div>
            </div>
        </a>
        <div class="d-flex align-items-center gap-3">
            <div class="d-none d-sm-block text-end">
                <div class="d-flex align-items-center justify-content-end gap-1.5">
                    <span class="text-white small fw-bold"><?= e($_SESSION['admin_name'] ?? 'Administrator') ?></span>
                    <?php if (is_superadmin()): ?>
                        <span class="badge bg-warning text-dark px-1.5 py-0.5" style="font-size: 10px; font-weight: 700;"><i class="bi bi-shield-shaded me-0.5"></i>SUPERADMIN</span>
                    <?php else: ?>
                        <span class="badge bg-secondary-subtle text-light px-1.5 py-0.5" style="font-size: 10px;">ADMIN</span>
                    <?php endif; ?>
                </div>
                <div class="text-white-50" style="font-size: 11px;"><?= is_superadmin() ? 'Super Administrator' : 'Operator Pilketos' ?></div>
            </div>
            <a href="../face-poc/index.php" target="_blank" class="btn btn-sm btn-outline-warning py-1 px-2" title="Buka Bilik Identifikasi Wajah (POC)">
                <i class="bi bi-camera-video me-1"></i>Bilik Wajah (POC)
            </a>
            <a href="../index.php" target="_blank" class="btn btn-sm btn-outline-light py-1 px-2" title="Lihat Halaman Pemilih">
                <i class="bi bi-box-arrow-up-right me-1"></i>Halaman Siswa
            </a>
            <a href="logout.php" class="btn btn-sm btn-danger py-1 px-2.5" onclick="return confirm('Apakah Anda yakin ingin keluar dari panel admin?');">
                <i class="bi bi-box-arrow-right me-1"></i>Logout
            </a>
        </div>
    </div>
</header>

<!-- Admin Navigation Menu -->
<nav class="navbar-institutional shadow-sm">
    <div class="container-fluid px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-1 flex-wrap">
            <a href="index.php" class="nav-link-inst <?= ($currentScript == 'index.php') ? 'active' : '' ?>">
                <i class="bi bi-speedometer2 me-1"></i>Dashboard
            </a>
            <a href="classes.php" class="nav-link-inst <?= ($currentScript == 'classes.php') ? 'active' : '' ?>">
                <i class="bi bi-mortarboard me-1"></i>Kelola Kelas
            </a>
            <a href="students.php" class="nav-link-inst <?= ($currentScript == 'students.php') ? 'active' : '' ?>">
                <i class="bi bi-people me-1"></i>DPT Siswa
            </a>
            <a href="employees.php" class="nav-link-inst <?= ($currentScript == 'employees.php') ? 'active' : '' ?>">
                <i class="bi bi-briefcase me-1"></i>DPT Guru &amp; Karyawan
            </a>
            <a href="candidates.php" class="nav-link-inst <?= ($currentScript == 'candidates.php') ? 'active' : '' ?>">
                <i class="bi bi-person-badge me-1"></i>Kelola Kandidat
            </a>
            <a href="results.php" class="nav-link-inst <?= ($currentScript == 'results.php') ? 'active' : '' ?>">
                <i class="bi bi-pie-chart me-1"></i>Hasil & Laporan
            </a>
            <a href="settings.php" class="nav-link-inst <?= ($currentScript == 'settings.php') ? 'active' : '' ?>">
                <i class="bi bi-gear me-1"></i>Pengaturan
            </a>
            <a href="password.php" class="nav-link-inst <?= ($currentScript == 'password.php') ? 'active' : '' ?>">
                <i class="bi bi-key me-1"></i>Ganti Password
            </a>
            <?php if (is_superadmin()): ?>
                <a href="users.php" class="nav-link-inst <?= ($currentScript == 'users.php') ? 'active' : '' ?>">
                    <i class="bi bi-people-fill me-1 text-warning"></i>Kelola Admin
                </a>
                <a href="logs.php" class="nav-link-inst <?= ($currentScript == 'logs.php') ? 'active' : '' ?>">
                    <i class="bi bi-shield-check me-1 text-warning"></i>Log Aktivitas
                </a>
            <?php endif; ?>
            <a href="../face-poc/index.php" target="_blank" class="nav-link-inst text-primary fw-semibold" title="Bilik Pengenalan Wajah Berbasis AI">
                <i class="bi bi-camera-video me-1"></i>Bilik Wajah (POC)
            </a>
            <a href="../face-poc/enroll.php" target="_blank" class="nav-link-inst text-primary fw-semibold" title="Registrasi Wajah Pemilih (Siswa, Guru &amp; Karyawan)">
                <i class="bi bi-person-bounding-box me-1"></i>Registrasi Wajah
            </a>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small">Status Pemilihan:</span>
            <?php if ($settings['election_status'] === 'OPEN'): ?>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 fw-semibold">
                    <i class="bi bi-circle-fill text-success" style="font-size: 8px;"></i> OPEN (Aktif)
                </span>
            <?php elseif ($settings['election_status'] === 'DRAFT'): ?>
                <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2.5 py-1.5 fw-semibold">
                    <i class="bi bi-clock"></i> DRAFT (Persiapan)
                </span>
            <?php else: ?>
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1.5 fw-semibold">
                    <i class="bi bi-lock-fill"></i> CLOSED (Ditutup)
                </span>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Admin Main Container -->
<main class="py-4">
    <div class="container-fluid px-4">
        <?php $flash = get_flash(); if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show mb-4 shadow-sm" role="alert">
                <?= e($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
