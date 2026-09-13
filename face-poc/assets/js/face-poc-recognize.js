/**
 * Face-POC: Recognition Kiosk Controller
 * SMK SIG - E-Pilketos
 * Client-Side Face Recognition using face-api.js
 */

(function () {
    'use strict';

    // State
    let isModelLoaded = false;
    let isCameraActive = false;
    let isPaused = false;
    let detectionTimer = null;
    let enrolledData = [];
    let enrolledMap = {};
    let faceMatcher = null;
    let currentThreshold = 0.50; // Configurable threshold (default 0.50)
    let consecutiveMatches = 0;
    let candidateLabel = null;

    // DOM Elements
    const video = document.getElementById('webcamVideo');
    const canvas = document.getElementById('webcamCanvas');
    const stage = document.getElementById('webcamStage');
    const statusBox = document.getElementById('statusBox');
    const thresholdSlider = document.getElementById('thresholdSlider');
    const thresholdValue = document.getElementById('thresholdValue');
    const enrolledCountBadge = document.getElementById('enrolledCountBadge');
    const alertBox = document.getElementById('recognizeAlert');
    const modalContainer = document.getElementById('modalContainer');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const ctx = canvas ? canvas.getContext('2d') : null;

    /**
     * Tampilkan pesan notifikasi
     */
    function showAlert(message, type = 'info') {
        if (!alertBox) return;
        alertBox.className = `kiosk-alert kiosk-alert-${type === 'error' ? 'danger' : type}`;
        alertBox.style.display = 'block';
        alertBox.textContent = message;
    }

    function hideAlert() {
        if (!alertBox) return;
        alertBox.style.display = 'none';
    }

    /**
     * Set tampilan status di bawah kamera
     */
    function renderStatus(html) {
        if (statusBox) {
            statusBox.innerHTML = html;
        }
    }

    function resetStatusToSearching() {
        renderStatus(`
            <div class="status-searching">
                <span class="pulse-dot"></span> Mencari wajah...
            </div>
        `);
        if (stage) {
            stage.classList.remove('matched', 'warning', 'error');
        }
    }

    /**
     * 1. Ambil data biometrik wajah siswa dari backend
     */
    async function loadEnrolledFaces() {
        try {
            const res = await fetch('api/get_faces.php');
            const data = await res.json();

            if (!data.success) {
                showAlert('Gagal memuat data biometrik: ' + (data.message || 'Error'), 'error');
                return;
            }

            enrolledData = data.faces || [];
            enrolledMap = {};

            if (enrolledCountBadge) {
                enrolledCountBadge.textContent = `${enrolledData.length} Siswa Terdaftar`;
            }

            if (enrolledData.length === 0) {
                showAlert('Perhatian: belum ada wajah siswa yang terdaftar. Panitia dapat membuka halaman Registrasi Wajah terlebih dahulu.', 'warning');
                faceMatcher = null;
                return;
            }

            // Bangun LabeledFaceDescriptors untuk faceapi.FaceMatcher
            const labeledDescriptors = [];
            for (const face of enrolledData) {
                enrolledMap[face.id] = face;
                try {
                    const descFloat32 = new Float32Array(face.descriptor);
                    labeledDescriptors.push(
                        new faceapi.LabeledFaceDescriptors(face.id.toString(), [descFloat32])
                    );
                } catch (e) {
                    console.error('Error parsing descriptor for student ID:', face.id, e);
                }
            }

            if (labeledDescriptors.length > 0) {
                faceMatcher = new faceapi.FaceMatcher(labeledDescriptors, currentThreshold);
                console.log(`FaceMatcher ready with ${labeledDescriptors.length} students. Threshold: ${currentThreshold}`);
            }
        } catch (err) {
            showAlert('Kesalahan koneksi ke server saat memuat biometrik: ' + err.message, 'error');
        }
    }

    /**
     * Cache Proxy untuk Model AI menggunakan CacheStorage Browser.
     * Mengeliminasi re-download 6.4 MB model neural network pada kunjungan berikutnya.
     * File model otomatis tersimpan aman di browser client (laptop bilik).
     */
    function setupModelCacheProxy() {
        if (!('caches' in window)) return;
        const originalFetch = window.fetch;
        window.fetch = async function (resource, init) {
            const url = typeof resource === 'string' ? resource : (resource ? resource.url : '');
            if (url && (url.endsWith('.bin') || url.endsWith('.json') || url.includes('/assets/models/'))) {
                try {
                    const cache = await caches.open('epilketos-face-models-v1');
                    const cleanUrl = typeof resource === 'string' ? resource : resource.url;
                    const cachedResponse = await cache.match(cleanUrl);
                    if (cachedResponse) {
                        return cachedResponse.clone();
                    }
                    const networkResponse = await originalFetch(resource, init);
                    if (networkResponse && networkResponse.ok) {
                        cache.put(cleanUrl, networkResponse.clone()).catch(() => {});
                    }
                    return networkResponse;
                } catch (e) {
                    return originalFetch(resource, init);
                }
            }
            return originalFetch(resource, init);
        };
    }

    /**
     * Resolusi Path Model yang kebal terhadap URL tanpa trailing slash / WA WebView
     */
    function resolveModelPath() {
        let loc = window.location.href.split('?')[0].split('#')[0];
        if (loc.endsWith('.php')) {
            loc = loc.substring(0, loc.lastIndexOf('/') + 1);
        } else if (!loc.endsWith('/')) {
            loc += '/';
        }
        return new URL('assets/models', loc).href;
    }

    /**
     * 2. Load Model Offline
     */
    async function loadModels() {
        try {
            const MODEL_PATH = resolveModelPath();
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_PATH),
                faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_PATH),
                faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_PATH)
            ]);
            isModelLoaded = true;
            console.log('Face models loaded successfully from:', MODEL_PATH);
            if (isCameraActive) {
                resetStatusToSearching();
            }
        } catch (err) {
            console.error('Failed to load face models:', err);
            showAlert('Gagal memuat file model AI: ' + err.message, 'error');
            renderStatus('<div class="kiosk-alert kiosk-alert-danger">Gagal memuat model biometrik. Periksa folder assets/models.</div>');
        }
    }

    /**
     * 3. Start Webcam Stream (Langsung menyala cepat di awal)
     */
    async function startCamera() {
        try {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                showAlert('Browser tidak mendukung MediaDevices API atau tidak dalam konteks aman (HTTPS/localhost).', 'error');
                return;
            }

            const stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    width: { ideal: 640 },
                    height: { ideal: 480 },
                    facingMode: 'user'
                },
                audio: false
            });

            video.srcObject = stream;
            await video.play();

            isCameraActive = true;
            canvas.width = video.videoWidth || 640;
            canvas.height = video.videoHeight || 480;

            if (isModelLoaded) {
                resetStatusToSearching();
            } else {
                renderStatus(`
                    <div class="status-searching">
                        <span class="pulse-dot" style="background:#f59e0b;"></span> Kamera aktif. Menyiapkan model AI biometrik...
                    </div>
                `);
            }

            startRecognitionLoop();
        } catch (err) {
            console.error('Webcam init error:', err);
            showAlert('Tidak dapat mengaktifkan webcam: ' + err.message, 'error');
            renderStatus('<div class="kiosk-alert kiosk-alert-danger">Kamera tidak aktif. Harap izinkan akses webcam.</div>');
        }
    }

    /**
     * 4. Recognition Loop (200ms throttle)
     */
    function startRecognitionLoop() {
        if (detectionTimer) clearInterval(detectionTimer);

        const options = new faceapi.TinyFaceDetectorOptions({
            inputSize: 320,
            scoreThreshold: 0.5
        });

        detectionTimer = setInterval(async () => {
            if (!isModelLoaded || !isCameraActive || isPaused || video.paused || video.ended) {
                return;
            }

            try {
                const detections = await faceapi
                    .detectAllFaces(video, options)
                    .withFaceLandmarks()
                    .withFaceDescriptors();

                const displaySize = {
                    width: video.videoWidth || 640,
                    height: video.videoHeight || 480
                };

                if (canvas.width !== displaySize.width || canvas.height !== displaySize.height) {
                    faceapi.matchDimensions(canvas, displaySize);
                }

                const resized = faceapi.resizeResults(detections, displaySize);
                ctx.clearRect(0, 0, canvas.width, canvas.height);

                // Skenario 1: Tidak ada wajah di depan kamera
                if (resized.length === 0) {
                    consecutiveMatches = 0;
                    candidateLabel = null;
                    stage.classList.remove('matched', 'warning', 'error');
                    resetStatusToSearching();
                    return;
                }

                // Skenario 2: Terdeteksi lebih dari satu wajah
                if (resized.length > 1) {
                    consecutiveMatches = 0;
                    candidateLabel = null;
                    stage.classList.remove('matched', 'error');
                    stage.classList.add('warning');

                    // Gambar kotak kuning
                    ctx.strokeStyle = '#eab308';
                    ctx.lineWidth = 3;
                    resized.forEach(d => {
                        const { x, y, width, height } = d.detection.box;
                        ctx.strokeRect(x, y, width, height);
                    });

                    renderStatus(`
                        <div class="kiosk-alert kiosk-alert-warning">
                            ⚠️ Terdeteksi lebih dari satu wajah. Pastikan hanya satu siswa berada di depan kamera.
                        </div>
                    `);
                    return;
                }

                // Skenario 3: Tepat satu wajah terdeteksi
                const singleDetection = resized[0];
                const box = singleDetection.detection.box;

                if (!faceMatcher) {
                    // Kamera mendeteksi wajah, tapi belum ada siswa terdaftar di DB
                    ctx.strokeStyle = '#3b82f6';
                    ctx.lineWidth = 2;
                    ctx.strokeRect(box.x, box.y, box.width, box.height);
                    renderStatus(`
                        <div class="kiosk-alert kiosk-alert-warning">
                            Wajah terdeteksi, namun belum ada database siswa terdaftar. Silakan lakukan pendaftaran biometrik terlebih dahulu.
                        </div>
                    `);
                    return;
                }

                // Lakukan pencocokan biometrik
                const bestMatch = faceMatcher.findBestMatch(singleDetection.descriptor);

                // Skenario 3A: Wajah tidak cocok (Unknown)
                if (bestMatch.label === 'unknown') {
                    consecutiveMatches = 0;
                    candidateLabel = null;
                    stage.classList.remove('matched', 'warning');
                    stage.classList.add('error');

                    // Gambar kotak merah
                    ctx.strokeStyle = '#ef4444';
                    ctx.lineWidth = 3;
                    ctx.strokeRect(box.x, box.y, box.width, box.height);

                    renderStatus(`
                        <div class="kiosk-alert kiosk-alert-danger">
                            Wajah tidak dikenali. Silakan posisikan wajah dengan jelas dan coba lagi.
                            <div style="font-size:12px; margin-top:4px; opacity:0.85;">
                                Distance: ${bestMatch.distance.toFixed(3)} (Threshold: ${currentThreshold.toFixed(2)})
                            </div>
                        </div>
                    `);
                    return;
                }

                // Skenario 3B: Wajah cocok dengan salah satu pemilih terdaftar!
                const voter = enrolledMap[bestMatch.label];

                if (!voter) {
                    console.warn('Voter metadata not found for Label:', bestMatch.label);
                    return;
                }

                // Gambar kotak hijau
                ctx.strokeStyle = '#22c55e';
                ctx.lineWidth = 3;
                ctx.strokeRect(box.x, box.y, box.width, box.height);

                // Anti-jitter: konfirmasi selama 2 frame berturut-turut (~400ms)
                if (candidateLabel === bestMatch.label) {
                    consecutiveMatches++;
                } else {
                    candidateLabel = bestMatch.label;
                    consecutiveMatches = 1;
                }

                if (consecutiveMatches >= 2) {
                    // FIXATE / FREEZE DETEKSI
                    isPaused = true;
                    stage.classList.remove('warning', 'error');
                    stage.classList.add('matched');

                    displayMatchedVoter(voter, bestMatch.distance);
                }

            } catch (err) {
                console.error('Recognition frame loop error:', err);
            }
        }, 200);
    }

    /**
     * Tampilkan Kartu Pemilih Dikenali (Siswa atau Guru & Karyawan)
     */
    function displayMatchedVoter(voter, distance) {
        const similarity = Math.max(0, Math.min(100, Math.round((1 - distance) * 100)));
        let subtitleHtml = '';
        if (voter.type === 'employee') {
            const roleBadge = voter.employee_type === 'karyawan' 
                ? '<span style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; padding:2px 8px; border-radius:4px; font-weight:700; font-size:11px; margin-right:6px;">KARYAWAN</span>'
                : '<span style="background:#e0e7ff; color:#3730a3; border:1px solid #c7d2fe; padding:2px 8px; border-radius:4px; font-weight:700; font-size:11px; margin-right:6px;">GURU</span>';
            subtitleHtml = `${roleBadge} NIP: <strong style="color:#0f172a;">${escapeHtml(voter.nip || '-')}</strong>`;
        } else {
            const absenDisplay = voter.attendance_number ? `Absen ${String(voter.attendance_number).padStart(2, '0')}` : '';
            subtitleHtml = `${escapeHtml(voter.class_name)} ${absenDisplay ? '&bull; ' + escapeHtml(absenDisplay) : ''}`;
        }

        renderStatus(`
            <div class="matched-card">
                <div class="matched-badge">✓ Wajah dikenali</div>
                <h2 class="matched-name">${escapeHtml(voter.name)}</h2>
                <div class="matched-class">${subtitleHtml}</div>
                <div style="font-size:12.5px; color:#475569; margin-bottom:18px;">
                    Akurasi Biometrik: <strong>${similarity}%</strong> 
                    <span style="color:#94a3b8;">(Distance: ${distance.toFixed(3)})</span>
                </div>
                <div class="matched-actions">
                    <button type="button" class="btn-kiosk btn-kiosk-outline" id="btnRetry">
                        ↺ Ulang
                    </button>
                    <button type="button" class="btn-kiosk btn-kiosk-primary" id="btnProceed">
                        Lanjut ke Bilik Suara →
                    </button>
                </div>
            </div>
        `);

        // Event listener untuk tombol Ulang
        const btnRetry = document.getElementById('btnRetry');
        if (btnRetry) {
            btnRetry.addEventListener('click', handleRetry);
        }

        // Event listener untuk tombol Lanjut
        const btnProceed = document.getElementById('btnProceed');
        if (btnProceed) {
            btnProceed.addEventListener('click', () => handleProceed(voter));
        }
    }

    /**
     * Handler Tombol [ Ulang ]
     * Mereset UI, membersihkan canvas, dan melanjutkan deteksi kamera
     */
    function handleRetry() {
        isPaused = false;
        consecutiveMatches = 0;
        candidateLabel = null;
        if (ctx && canvas) {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
        resetStatusToSearching();
    }

    /**
     * Handler Tombol [ Lanjut ]
     * Memanggil API backend verify_voter.php untuk verifikasi kelayakan memilih dan membuat sesi
     */
    async function handleProceed(voter) {
        const btnProceed = document.getElementById('btnProceed');
        const btnRetry = document.getElementById('btnRetry');
        if (btnProceed) {
            btnProceed.disabled = true;
            btnProceed.textContent = 'Memverifikasi...';
        }
        if (btnRetry) {
            btnRetry.disabled = true;
        }

        try {
            const res = await fetch('api/verify_voter.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({
                    voter_type: voter.type || 'student',
                    voter_id: voter.raw_id || voter.id,
                    student_id: voter.raw_id || voter.id
                })
            });

            const data = await res.json();

            if (data.success && data.eligible) {
                // Berhasil & Layak Memilih -> Langsung alihkan ke surat suara bilik (vote.php)
                const greetingSub = voter.type === 'employee' 
                    ? (voter.employee_type === 'karyawan' ? 'Tenaga Kependidikan' : 'Tenaga Pendidik')
                    : voter.class_name;
                renderStatus(`
                    <div class="kiosk-alert kiosk-alert-success" style="padding: 22px; font-size: 15px; border-radius: 10px;">
                        <div style="font-size: 18px; font-weight: bold; margin-bottom: 6px;">✓ Identifikasi Berhasil!</div>
                        <div>Selamat datang, <strong>${escapeHtml(voter.name)}</strong> (${escapeHtml(greetingSub)}).</div>
                        <div style="margin-top: 10px; font-size: 13px; color: #15803d;">
                            <span class="pulse-dot" style="background:#16a34a;"></span> Mengalihkan ke surat suara digital...
                        </div>
                    </div>
                `);

                setTimeout(() => {
                    window.location.href = data.redirect_url || '../vote.php';
                }, 600);
            } else {
                // Tidak layak (sudah memilih / pemilihan belum dibuka / kelas tidak aktif)
                showConfirmationModal({
                    type: 'error',
                    title: 'Verifikasi Ditolak',
                    icon: '✕',
                    iconColor: '#dc2626',
                    studentName: voter.name,
                    className: voter.type === 'employee' ? 'Guru & Karyawan' : voter.class_name,
                    message: data.message || 'Pemilih tidak memenuhi syarat untuk memilih.',
                    submessage: 'Setiap pemilih hanya memiliki 1 hak suara. Hubungi panitia jika terdapat kekeliruan.',
                    actionText: 'Kembali / Pemilih Berikutnya'
                });
            }
        } catch (err) {
            console.error('Verification error:', err);
            showAlert('Terjadi kesalahan jaringan saat memverifikasi pemilih: ' + err.message, 'error');
            if (btnProceed) {
                btnProceed.disabled = false;
                btnProceed.textContent = 'Lanjut ke Bilik Suara →';
            }
            if (btnRetry) {
                btnRetry.disabled = false;
            }
        }
    }

    /**
     * Modal Dialog Konfirmasi
     */
    function showConfirmationModal({ type, title, icon, iconColor, studentName, className, message, submessage, actionText }) {
        if (!modalContainer) return;

        modalContainer.innerHTML = `
            <div class="kiosk-modal-backdrop" id="kioskModal">
                <div class="kiosk-modal">
                    <div class="kiosk-modal-icon" style="color:${iconColor}; font-weight:bold;">${icon}</div>
                    <h3 style="margin-bottom:8px;">${escapeHtml(title)}</h3>
                    <div style="font-size:18px; font-weight:700; color:#1e293b; margin-bottom:2px;">
                        ${escapeHtml(studentName)}
                    </div>
                    <div style="font-size:14px; font-weight:600; color:#64748b; margin-bottom:16px;">
                        ${escapeHtml(className)}
                    </div>
                    <div style="background:${type === 'success' ? '#f0fdf4' : '#fef2f2'}; border:1px solid ${type === 'success' ? '#bbf7d0' : '#fecaca'}; border-radius:8px; padding:12px 16px; margin-bottom:16px; font-size:14px; color:${type === 'success' ? '#15803d' : '#991b1b'};">
                        <strong>${escapeHtml(message)}</strong>
                        <div style="font-size:12px; margin-top:4px; opacity:0.9;">${escapeHtml(submessage)}</div>
                    </div>
                    <div class="kiosk-modal-actions">
                        <button type="button" class="btn-kiosk ${type === 'success' ? 'btn-kiosk-success' : 'btn-kiosk-outline'}" id="btnModalClose">
                            ${escapeHtml(actionText)}
                        </button>
                    </div>
                </div>
            </div>
        `;

        const btnClose = document.getElementById('btnModalClose');
        if (btnClose) {
            btnClose.addEventListener('click', () => {
                modalContainer.innerHTML = '';
                handleRetry();
            });
        }
    }

    /**
     * Helper Escape HTML
     */
    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /**
     * Update Threshold dari Slider
     */
    function handleThresholdChange(e) {
        currentThreshold = parseFloat(e.target.value);
        if (thresholdValue) {
            thresholdValue.textContent = currentThreshold.toFixed(2);
        }
        if (enrolledData.length > 0) {
            const labeledDescriptors = [];
            for (const face of enrolledData) {
                try {
                    const descFloat32 = new Float32Array(face.descriptor);
                    labeledDescriptors.push(
                        new faceapi.LabeledFaceDescriptors(face.id.toString(), [descFloat32])
                    );
                } catch (err) {}
            }
            if (labeledDescriptors.length > 0) {
                faceMatcher = new faceapi.FaceMatcher(labeledDescriptors, currentThreshold);
            }
        }
    }

    /**
     * Inisialisasi Kiosk
     */
    async function init() {
        if (thresholdSlider) {
            thresholdSlider.addEventListener('input', handleThresholdChange);
        }

        // 1. Pasang proxy cache browser
        setupModelCacheProxy();

        // 2. LANGSUNG NYALAKAN KAMERA (non-blocking)
        // Webcam langsung aktif dan muncul di layar tanpa menunggu download model AI
        const cameraPromise = startCamera();

        // 3. Tampilkan pesan status
        renderStatus(`
            <div class="status-searching">
                <span class="pulse-dot" style="background:#f59e0b;"></span> Mengaktifkan kamera &amp; menyiapkan AI...
            </div>
        `);

        // 4. Muat data siswa & model AI secara paralel bersamaan dengan kamera
        await Promise.all([
            cameraPromise,
            loadEnrolledFaces(),
            loadModels()
        ]);

        if (isModelLoaded && isCameraActive) {
            resetStatusToSearching();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
