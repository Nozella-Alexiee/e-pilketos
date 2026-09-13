<?php
/**
 * Face-POC: Bilik Identifikasi Wajah (Recognition Kiosk)
 * E-Pilketos SMK SIG
 * 
 * Halaman kiosk pengenalan wajah siswa secara real-time di browser
 */
require_once __DIR__ . '/../config/functions.php';

// Enforce trailing slash on directory URL to ensure relative assets resolve properly
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($requestUri, PHP_URL_PATH) ?? '';
if (substr($path, -1) !== '/' && !preg_match('/\.php$/i', $path)) {
    $queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
    header('Location: ' . $path . '/' . $queryString, true, 301);
    exit;
}

$_SESSION['kiosk_authorized'] = true;
$_SESSION['kiosk_authorized_until'] = time() + 43200;
header('Cache-Control: no-store, private');
$baseHref = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?= e($baseHref) ?>">
    <title>Bilik Suara (Face Recognition) - E-Pilketos SMK SIG</title>
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <link rel="stylesheet" href="assets/css/face-poc.css">
    <link rel="icon" type="image/png" href="../assets/images/logo-smk-sig.png">

    <!-- PWA Settings & Manifest -->
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#511524">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Bilik E-Pilketos">
    <link rel="apple-touch-icon" href="../assets/images/icons/apple-touch-icon.png">

    <!-- Preload Model Weights agar diunduh lebih awal oleh browser -->
    <link rel="preload" href="assets/models/tiny_face_detector_model-weights_manifest.json" as="fetch" crossorigin="anonymous">
    <link rel="preload" href="assets/models/tiny_face_detector_model.bin" as="fetch" crossorigin="anonymous">
    <link rel="preload" href="assets/models/face_landmark_68_model-weights_manifest.json" as="fetch" crossorigin="anonymous">
    <link rel="preload" href="assets/models/face_landmark_68_model.bin" as="fetch" crossorigin="anonymous">
    <link rel="preload" href="assets/models/face_recognition_model-weights_manifest.json" as="fetch" crossorigin="anonymous">
    <link rel="preload" href="assets/models/face_recognition_model.bin" as="fetch" crossorigin="anonymous">
</head>
<body>

    <!-- Header Kiosk -->
    <header class="kiosk-header">
        <div style="max-width: 960px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
            <div class="kiosk-brand">
                <img src="../assets/images/logo-smk-sig.png" alt="Logo SMK SIG" style="height: 48px; width: auto; object-fit: contain;">
                <div>
                    <h1 class="kiosk-title">E-PILKETOS SMK SIG</h1>
                    <p class="kiosk-subtitle">Bilik Suara Digital Berbasis Face Recognition</p>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 13px; background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; padding: 4px 12px; border-radius: 9999px; font-weight: 600;">
                    ✓ Bilik Resmi Aktif
                </span>
                <a href="../admin/index.php" class="nav-link" style="color:#64748b; font-size:12px;" onclick="return confirm('Keluar dari bilik kamera dan buka panel admin?');">
                    Panel Admin
                </a>
            </div>
        </div>
    </header>

    <!-- Main Kiosk Container -->
    <main class="kiosk-container">
        <div class="kiosk-card">
            <div class="kiosk-card-header">
                <div>
                    <h2 style="margin: 0; font-size: 18px; color: var(--navy-color);">Bilik Identifikasi Pemilih</h2>
                    <p style="margin: 4px 0 0 0; font-size: 13px; color: var(--text-muted);">
                        Silakan berdiri tegak dan posisikan wajah Anda di dalam panduan kamera.
                    </p>
                </div>
                <div>
                    <span class="badge badge-success" id="enrolledCountBadge">Memuat Data...</span>
                </div>
            </div>

            <div class="kiosk-card-body" style="padding: 28px 24px;">
                <!-- Alert Box untuk error / warning global -->
                <div id="recognizeAlert" class="kiosk-alert" style="display: none; margin: 0 auto 20px auto;"></div>

                <!-- Webcam Stage -->
                <div class="webcam-stage" id="webcamStage">
                    <video id="webcamVideo" autoplay muted playsinline></video>
                    <canvas id="webcamCanvas"></canvas>
                    <div class="webcam-overlay-guide"></div>
                </div>

                <!-- Status & Result Box -->
                <div class="status-box" id="statusBox">
                    <div class="status-searching">
                        <span class="pulse-dot"></span> Mencari wajah...
                    </div>
                </div>

                <!-- Kiosk Settings & Calibration Bar -->
                <div class="kiosk-settings-bar">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span>Kalibrasi Threshold:</span>
                        <input type="range" id="thresholdSlider" min="0.35" max="0.65" step="0.01" value="0.50">
                        <strong><span id="thresholdValue">0.50</span></strong>
                        <span style="font-size: 11px; color: #94a3b8;">(Default: 0.50. Semakin kecil = semakin ketat)</span>
                    </div>
                    <div>
                        <span>Mesin Biometrik: <strong>Client-Side TinyFace + Dlib ResNet</strong></span>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Container untuk popup konfirmasi -->
    <div id="modalContainer"></div>

    <!-- Dependencies: Vanilla Face-API.js Bundle & Recognition Controller -->
    <script src="assets/js/face-api.js"></script>
    <script src="assets/js/face-poc-recognize.js"></script>
    <script src="../assets/js/pwa.js"></script>
</body>
</html>

