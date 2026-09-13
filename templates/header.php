<?php
/**
 * Header Template - Public & Student Facing
 * SMK SIG (Sekolah Menengah Kejuruan SIG)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

$pdo = getDb();
$settings = get_election_settings($pdo);
$pageTitle = $pageTitle ?? 'E-Pilketos v2.0 - SMK SIG';
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
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- SMK SIG Institutional Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="assets/images/logo-smk-sig.png">

    <!-- PWA Settings & Manifest -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#511524">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="E-Pilketos">
    <link rel="apple-touch-icon" href="assets/images/icons/apple-touch-icon.png">
</head>
<body>

<!-- Institutional Top Header -->
<header class="school-topbar">
    <div class="container d-flex align-items-center justify-content-between">
        <a href="index.php" class="school-brand-lockup">
            <img src="assets/images/logo-smk-sig.png" alt="Logo SMK SIG">
            <div class="school-brand-text">
                <div class="school-brand-title">SMK SIG</div>
                <div class="school-brand-subtitle">Sekolah Menengah Kejuruan SIG</div>
            </div>
        </a>
        <div class="d-flex align-items-center gap-2">
            <span class="d-none d-md-inline-block text-white-50 small">
                Periode <?= e($settings['election_period']) ?>
            </span>
            <!-- Tombol Pasang Aplikasi PWA (Muncul otomatis di Tablet/HP) -->
            <button id="pwaInstallBtn" type="button" class="btn btn-sm btn-warning py-1 px-2.5 d-none align-items-center gap-1 shadow-sm" style="font-size: 12px; font-weight: 600;">
                <i class="bi bi-download"></i> Pasang Aplikasi
            </button>
            <a href="admin/login.php" class="btn btn-sm btn-outline-light py-1 px-2.5" style="font-size: 12px;">
                <i class="bi bi-shield-lock me-1"></i>Portal Admin
            </a>
        </div>
    </div>
</header>

<!-- Sub Navigation for Quick Access -->
<nav class="navbar-institutional">
    <div class="container d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <a href="index.php" class="nav-link-inst <?= (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : '' ?>">
                <i class="bi bi-house-door me-1"></i>Beranda
            </a>
            <a href="vote.php" class="nav-link-inst <?= (basename($_SERVER['PHP_SELF']) == 'vote.php') ? 'active' : '' ?>">
                <i class="bi bi-check2-square me-1"></i>Bilik Suara
            </a>
            <?php if (!empty($settings['show_results_to_students'])): ?>
            <a href="results.php" class="nav-link-inst <?= (basename($_SERVER['PHP_SELF']) == 'results.php') ? 'active' : '' ?>">
                <i class="bi bi-bar-chart me-1"></i>Hasil Suara
            </a>
            <?php endif; ?>
        </div>
        <div>
            <?php if ($settings['election_status'] === 'OPEN'): ?>
                <span class="hero-badge badge-open mb-0 py-1 px-2" style="font-size: 11px;">
                    <i class="bi bi-circle-fill text-success" style="font-size: 8px;"></i> Pemilihan Dibuka
                </span>
            <?php elseif ($settings['election_status'] === 'DRAFT'): ?>
                <span class="hero-badge badge-draft mb-0 py-1 px-2" style="font-size: 11px;">
                    <i class="bi bi-clock-history"></i> Tahap Persiapan
                </span>
            <?php else: ?>
                <span class="hero-badge badge-closed mb-0 py-1 px-2" style="font-size: 11px;">
                    <i class="bi bi-lock-fill"></i> Pemilihan Ditutup
                </span>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Main Body Area -->
<main class="py-4">
    <div class="container">
        <?php $flash = get_flash(); if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show mb-4 shadow-sm" role="alert">
                <?= e($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
