<?php
/**
 * E-Pilketos v2.0 - Portal Masuk Utama
 * SMK SIG (Sekolah Menengah Kejuruan SIG)
 * 2 Pintu Masuk: Siswa (Pemilih) & Admin (Pengelola)
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/functions.php';

$pdo = getDb();
$settings = get_election_settings($pdo);

$pageTitle = 'E-Pilketos v2.0 - SMK SIG';
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

    <style>
        .portal-wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: var(--canvas);
        }
        .portal-header-box {
            background: #ffffff;
            border-bottom: 2px solid var(--maroon-700);
            padding: 30px 20px 25px;
            text-align: center;
        }
        .portal-school-logo {
            height: 90px;
            width: auto;
            object-fit: contain;
            margin-bottom: 12px;
        }
        .portal-choice-card {
            background: #ffffff;
            border: 2px solid var(--line);
            border-radius: var(--radius-lg);
            padding: 32px 24px;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-subtle);
        }
        .portal-choice-card:hover {
            border-color: var(--maroon-800);
            box-shadow: 0 8px 24px rgba(81, 21, 36, 0.08);
            transform: translateY(-2px);
        }
        .portal-choice-icon {
            width: 64px;
            height: 64px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin-bottom: 20px;
        }
        .icon-student {
            background: var(--maroon-050);
            color: var(--maroon-800);
            border: 1px solid var(--maroon-100);
        }
        .icon-admin {
            background: #f0f4f8;
            color: #1e3a8a;
            border: 1px solid #dbeafe;
        }
    </style>
</head>
<body class="portal-wrapper">

<!-- Institutional Header with SMK SIG Logo -->
<header class="portal-header-box">
    <div class="container text-center">
        <img src="assets/images/logo-smk-sig.png" alt="Logo SMK SIG" class="portal-school-logo">
        <div class="text-uppercase fw-bold text-muted small" style="letter-spacing: 0.08em;">
            Yayasan Semen Indonesia &bull; SMKS Semen Gresik
        </div>
        <h1 class="h3 fw-bold text-dark mb-1 mt-1">E-PILKETOS v2.0</h1>
        <div class="text-danger fw-semibold" style="font-size: 15px;">
            Sistem Pemilihan Ketua OSIS SMK SIG &bull; Periode <?= e($settings['election_period']) ?>
        </div>

        <div class="mt-3">
            <?php if ($settings['election_status'] === 'OPEN'): ?>
                <span class="hero-badge badge-open py-1 px-3 fs-6">
                    <i class="bi bi-circle-fill text-success" style="font-size: 10px;"></i> Pemilihan Sedang Dibuka
                </span>
            <?php elseif ($settings['election_status'] === 'DRAFT'): ?>
                <span class="hero-badge badge-draft py-1 px-3 fs-6">
                    <i class="bi bi-clock-history"></i> Tahap Persiapan (Belum Dibuka)
                </span>
            <?php else: ?>
                <span class="hero-badge badge-closed py-1 px-3 fs-6">
                    <i class="bi bi-lock-fill"></i> Pemilihan Telah Ditutup
                </span>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- Main Choice Section: Siswa vs Admin -->
<main class="flex-grow-1 py-5">
    <div class="container" style="max-width: 860px;">
        <?php $flash = get_flash(); if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show mb-4 shadow-sm" role="alert">
                <?= e($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="text-center mb-4">
            <h2 class="h5 fw-bold text-dark">Pilih Pintu Masuk Pengguna</h2>
            <p class="text-muted small">Silakan pilih akses Anda untuk masuk ke dalam sistem pemilihan</p>
        </div>

        <div class="row g-4">
            <!-- PINTU 1: SISWA (PEMILIH) -->
            <div class="col-md-6">
                <div class="portal-choice-card">
                    <div>
                        <div class="portal-choice-icon icon-student">
                            <i class="bi bi-camera-video-fill"></i>
                        </div>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle mb-2">Bilik Suara Face Recognition</span>
                        <h3 class="h5 fw-bold text-dark mb-2">Masuk Bilik Suara</h3>
                        <p class="text-secondary small mb-4" style="line-height: 1.6;">
                            Bagi seluruh pemilih sah (Siswa, Guru &amp; Karyawan) SMK SIG. Cukup hadap ke kamera bilik suara untuk identifikasi wajah otomatis (<strong>Face Recognition</strong>), lalu langsung tentukan pilihan calon ketua OSIS tanpa repot isi formulir manual.
                        </p>
                    </div>

                    <div>
                        <?php if ($settings['election_status'] === 'OPEN'): ?>
                            <a href="face-poc/index.php" class="btn-sig w-100 py-2.5 text-center fw-bold fs-6">
                                <i class="bi bi-camera-fill me-1"></i> Masuk Bilik Suara (Kamera)
                            </a>
                        <?php elseif ($settings['election_status'] === 'DRAFT'): ?>
                            <button class="btn btn-secondary w-100 py-2.5 disabled" disabled>
                                <i class="bi bi-clock me-1"></i> Pemilihan Belum Dimulai
                            </button>
                        <?php else: ?>
                            <button class="btn btn-danger w-100 py-2.5 disabled" disabled>
                                <i class="bi bi-lock-fill me-1"></i> Pemilihan Sudah Ditutup
                            </button>
                        <?php endif; ?>
                        
                        <div class="text-center mt-2">
                            <small class="text-muted" style="font-size: 11px;">1 Pemilih = 1 Hak Suara (Anti Double-Voting)</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PINTU 2: ADMINISTRATOR (PANITIA) -->
            <div class="col-md-6">
                <div class="portal-choice-card">
                    <div>
                        <div class="portal-choice-icon icon-admin">
                            <i class="bi bi-shield-lock-fill"></i>
                        </div>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle mb-2">Akses Panitia / Guru</span>
                        <h3 class="h5 fw-bold text-dark mb-2">Masuk Sebagai Admin</h3>
                        <p class="text-secondary small mb-4" style="line-height: 1.6;">
                            Khusus panitia pemilihan, pembina OSIS, dan operator sekolah. Kelola data kelas, data pemilih (DPT), kandidat, pantau perolehan suara real-time, dan cetak Berita Acara resmi.
                        </p>
                    </div>

                    <div>
                        <a href="admin/login.php" class="btn btn-outline-dark w-100 py-2.5 text-center fw-bold fs-6">
                            <i class="bi bi-lock-fill me-1"></i> Masuk Sebagai Admin
                        </a>
                        <div class="text-center mt-2">
                            <small class="text-muted" style="font-size: 11px;">Otorisasi Username &amp; Kata Sandi Terenkripsi</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($settings['show_results_to_students'])): ?>
            <div class="text-center mt-4 pt-2">
                <a href="results.php" class="text-decoration-none small text-muted">
                    <i class="bi bi-bar-chart-fill me-1"></i> Lihat Hasil Perolehan Suara Terkini &raquo;
                </a>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Footer -->
<footer class="bg-white border-top py-3 text-center text-muted small mt-auto">
    <div class="container">
        <strong>SMK SIG (Sekolah Menengah Kejuruan SIG)</strong> &bull; E-Pilketos v2.0 &bull; Asas LUBER JURDIL
    </div>
</footer>

<!-- Floating PWA Install Banner for Mobile & Tablet -->
<div id="pwaInstallBanner" class="pwa-floating-banner" style="display: none;">
    <div class="pwa-banner-content">
        <img src="assets/images/icons/icon-192x192.png" alt="E-Pilketos" class="pwa-banner-icon">
        <div>
            <div class="pwa-banner-title">Pasang E-Pilketos di Layar Utama</div>
            <div class="pwa-banner-sub">Akses instan bilik suara full-screen tanpa browser bar</div>
        </div>
    </div>
    <div class="pwa-banner-actions">
        <button type="button" class="btn btn-sm btn-primary pwa-btn-action" style="background: var(--maroon-900); border: none;">
            <i class="bi bi-download me-1"></i>Pasang
        </button>
        <button type="button" class="btn-close pwa-banner-close" onclick="document.getElementById('pwaInstallBanner').style.display='none'" aria-label="Close"></button>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- PWA Service Worker & Installer JS -->
<script src="assets/js/pwa.js"></script>
</body>
</html>

