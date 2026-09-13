<?php
/**
 * Face-POC: Registrasi Wajah Siswa (Enrollment)
 * E-Pilketos SMK SIG
 * 
 * Halaman pendaftaran biometrik wajah siswa ke database menggunakan face-api.js
 */
require_once __DIR__ . '/../config/functions.php';
require_admin('../admin/login.php');

// Enforce trailing slash on directory URL to ensure relative assets resolve properly
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($requestUri, PHP_URL_PATH) ?? '';
if (substr($path, -1) !== '/' && !preg_match('/\.php$/i', $path)) {
    $queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
    header('Location: ' . $path . '/' . $queryString, true, 301);
    exit;
}

header('Cache-Control: no-store, private');
$baseHref = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?= e($baseHref) ?>">
    <title>Registrasi Biometrik Wajah - E-Pilketos SMK SIG</title>
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <link rel="stylesheet" href="assets/css/face-poc.css">
    <link rel="icon" type="image/png" href="../assets/images/logo-smk-sig.png">

    <!-- PWA Settings & Manifest -->
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#511524">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Registrasi E-Pilketos">
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
                    <p class="kiosk-subtitle">Pendaftaran Biometrik Wajah Siswa</p>
                </div>
            </div>
            <nav class="nav-pills-custom" style="display: flex; gap: 8px;">
                <a href="index.php" class="nav-link">Bilik Kamera</a>
                <a href="enroll.php" class="nav-link active">Registrasi Wajah</a>
                <a href="../admin/students.php" class="nav-link" style="color:#7b1113; font-weight: bold;">← DPT Siswa</a>
                <a href="../admin/employees.php" class="nav-link" style="color:#0284c7; font-weight: bold;">← DPT Guru &amp; Karyawan</a>
            </nav>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="kiosk-container">
        <div class="kiosk-card">
            <div class="kiosk-card-header">
                <div>
                    <h2 style="margin: 0; font-size: 18px; color: var(--navy-color);">Registrasi &amp; Pemetaan Wajah DPT</h2>
                    <p style="margin: 4px 0 0 0; font-size: 13px; color: var(--text-muted);">
                        Posisikan wajah pemilih (Siswa / Guru / Karyawan) di depan kamera untuk mengekstrak vektor biometrik 128 dimensi.
                    </p>
                </div>
                <div>
                    <span class="badge badge-secondary" id="statusBadge">Menginisialisasi...</span>
                </div>
            </div>

            <div class="kiosk-card-body">
                <!-- Alert Box -->
                <div id="enrollAlert" class="kiosk-alert" style="display: none; margin: 0 auto 20px auto;"></div>

                <div class="enroll-grid">
                    <!-- Kolom Kiri: Form Pemilihan Pemilih -->
                    <div>
                        <div class="form-group mb-3">
                            <label class="form-label" style="font-weight: 700; color: #1e293b;">Kategori Pemilih:</label>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" id="btnCatStudent" class="btn-kiosk btn-kiosk-primary" style="flex: 1; padding: 7px 12px; font-size: 13px;">
                                    DPT Siswa
                                </button>
                                <button type="button" id="btnCatEmployee" class="btn-kiosk btn-kiosk-outline" style="flex: 1; padding: 7px 12px; font-size: 13px;">
                                    DPT Guru &amp; Karyawan
                                </button>
                            </div>
                        </div>

                        <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:10px 14px; margin-bottom:16px; font-size:12px; color:#1e40af; line-height: 1.5;" id="enrollNoteBox">
                            ℹ️ <strong>Catatan:</strong> Pemilih yang wajahnya sudah terdaftar akan otomatis disembunyikan. Untuk mendaftarkan ulang, klik tombol <strong>Reset Wajah</strong> di panel admin.
                        </div>

                        <div class="form-group" id="classFilterGroup">
                            <label for="classFilter" class="form-label">Saring Berdasarkan Kelas (Opsional):</label>
                            <select id="classFilter" class="form-select">
                                <option value="">-- Tampilkan Semua Kelas --</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="studentSelect" class="form-label" id="lblSelectVoter">Pilih Nama Pemilih:</label>
                            <select id="studentSelect" class="form-select">
                                <option value="">-- Memuat daftar... --</option>
                            </select>
                        </div>

                        <div id="studentMeta" style="display: none; margin-bottom: 18px;"></div>

                        <!-- Panduan Pengambilan Biometrik -->
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 18px; margin-top: 14px;">
                            <h4 style="margin: 0 0 8px 0; font-size: 13px; color: #334155; text-transform: uppercase; letter-spacing: 0.05em;">Panduan Operator:</h4>
                            <ul style="margin: 0; padding-left: 20px; font-size: 13px; color: #475569; line-height: 1.6;">
                                <li>Pastikan siswa menghadap tegak lurus ke arah webcam.</li>
                                <li>Pencahayaan ruangan cukup terang dan merata.</li>
                                <li>Wajah tidak tertutup masker, tangan, atau kacamata hitam.</li>
                                <li>Tombol pendaftaran akan aktif otomatis saat <strong>tepat 1 wajah</strong> terdeteksi.</li>
                            </ul>
                        </div>

                        <div style="margin-top: 20px;">
                            <button type="button" class="btn-kiosk btn-kiosk-primary" id="btnCapture" style="width: 100%;" disabled>
                                Daftarkan Wajah
                            </button>
                        </div>
                    </div>

                    <!-- Kolom Kanan: Webcam Stage -->
                    <div>
                        <div class="webcam-stage" id="webcamStage">
                            <video id="webcamVideo" autoplay muted playsinline></video>
                            <canvas id="webcamCanvas"></canvas>
                            <div class="webcam-overlay-guide"></div>
                        </div>
                        <div style="text-align: center; margin-top: 12px; font-size: 12px; color: #64748b;">
                            Tampilan kamera dibalik secara horizontal (mirror) untuk kenyamanan pengguna.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Card / Catatan Teknis -->
            <div style="background: #f8fafc; border-top: 1px solid var(--card-border); padding: 12px 24px; font-size: 12px; color: #64748b; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                <div>
                    <strong>Privasi & Keamanan:</strong> Foto mentah tidak disimpan ke disk. Hanya 128 angka koordinat fitur matematis (descriptor) yang disimpan di database.
                </div>
                <div>
                    Model: <strong>TinyFaceDetector + FaceRecognitionNet</strong> (Offline)
                </div>
            </div>
        </div>
    </main>

    <!-- Dependencies: Vanilla Face-API.js Bundle & Controller -->
    <script src="assets/js/face-api.js"></script>
    <script src="assets/js/face-poc-enroll.js"></script>
    <script src="../assets/js/pwa.js"></script>
</body>
</html>

