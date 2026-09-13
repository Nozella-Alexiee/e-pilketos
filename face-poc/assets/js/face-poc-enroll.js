/**
 * Face-POC: Enrollment Controller
 * SMK SIG - E-Pilketos
 * Client-Side Face Registration using face-api.js
 */

(function () {
    'use strict';

    // State
    let isModelLoaded = false;
    let isCameraActive = false;
    let detectionTimer = null;
    let currentDescriptor = null;
    let currentCategory = 'student';
    let enrolledStudents = [];

    // DOM Elements
    const video = document.getElementById('webcamVideo');
    const canvas = document.getElementById('webcamCanvas');
    const stage = document.getElementById('webcamStage');
    const classFilter = document.getElementById('classFilter');
    const classFilterGroup = document.getElementById('classFilterGroup');
    const studentSelect = document.getElementById('studentSelect');
    const lblSelectVoter = document.getElementById('lblSelectVoter');
    const btnCatStudent = document.getElementById('btnCatStudent');
    const btnCatEmployee = document.getElementById('btnCatEmployee');
    const btnCapture = document.getElementById('btnCapture');
    const statusBadge = document.getElementById('statusBadge');
    const alertBox = document.getElementById('enrollAlert');
    const studentMeta = document.getElementById('studentMeta');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // Canvas context
    const ctx = canvas ? canvas.getContext('2d') : null;

    /**
     * Tampilkan pesan notifikasi / alert
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
     * Update status badge kamera
     */
    function updateBadge(text, type = 'secondary') {
        if (!statusBadge) return;
        statusBadge.className = `badge badge-${type}`;
        statusBadge.textContent = text;
    }

    /**
     * 1. Load data pemilih (Siswa atau Guru/Karyawan) dari backend
     */
    async function loadVoters(classId = '') {
        try {
            let url = 'api/get_students.php?';
            if (currentCategory === 'employee') {
                url += 'voter_type=employee';
            } else {
                url += 'voter_type=student';
                if (classId) url += `&class_id=${encodeURIComponent(classId)}`;
            }

            const res = await fetch(url);
            const data = await res.json();

            if (!data.success) {
                showAlert('Gagal memuat data pemilih: ' + (data.message || 'Error'), 'error');
                return;
            }

            if (currentCategory === 'student') {
                if (classFilterGroup) classFilterGroup.style.display = 'block';
                if (lblSelectVoter) lblSelectVoter.textContent = 'Pilih Nama Siswa:';

                // Populate class filter jika baru pertama kali
                if (classFilter && classFilter.options.length <= 1 && data.classes) {
                    data.classes.forEach(c => {
                        const opt = document.createElement('option');
                        opt.value = c.id;
                        opt.textContent = `${c.name} (${c.grade} ${c.major})`;
                        classFilter.appendChild(opt);
                    });
                }

                enrolledStudents = data.students || [];
                studentSelect.innerHTML = '';

                if (enrolledStudents.length === 0) {
                    studentSelect.innerHTML = '<option value="">-- Semua siswa di kelas ini sudah terdaftar --</option>';
                    studentSelect.disabled = true;
                    if (studentMeta) {
                        studentMeta.style.display = 'block';
                        studentMeta.innerHTML = `
                            <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:12px 16px; margin-top:8px; color:#15803d; font-size:13px;">
                                <strong>✓ Seluruh siswa pada kelas ini sudah memiliki biometrik wajah!</strong>
                            </div>
                        `;
                    }
                } else {
                    studentSelect.disabled = false;
                    studentSelect.innerHTML = `<option value="">-- Pilih Siswa (${enrolledStudents.length} Belum Terdaftar) --</option>`;

                    enrolledStudents.forEach(s => {
                        const opt = document.createElement('option');
                        opt.value = s.id;
                        const absenStr = 'Absen ' + String(s.attendance_number).padStart(2, '0');
                        const classPrefix = (classFilter && classFilter.value) ? '' : `[${s.class_name}] `;
                        opt.textContent = `${classPrefix}${absenStr}: ${s.name}`;
                        opt.dataset.className = s.class_name;
                        opt.dataset.name = s.name;
                        opt.dataset.attendanceNumber = s.attendance_number;
                        opt.dataset.category = 'student';
                        studentSelect.appendChild(opt);
                    });

                    if (studentMeta) {
                        studentMeta.style.display = 'none';
                    }
                }
            } else {
                // Mode Guru & Karyawan
                if (classFilterGroup) classFilterGroup.style.display = 'none';
                if (lblSelectVoter) lblSelectVoter.textContent = 'Pilih Nama Guru / Karyawan:';

                enrolledStudents = data.employees || data.students || [];
                studentSelect.innerHTML = '';

                if (enrolledStudents.length === 0) {
                    studentSelect.innerHTML = '<option value="">-- Semua Guru &amp; Karyawan sudah terdaftar --</option>';
                    studentSelect.disabled = true;
                    if (studentMeta) {
                        studentMeta.style.display = 'block';
                        studentMeta.innerHTML = `
                            <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:12px 16px; margin-top:8px; color:#15803d; font-size:13px;">
                                <strong>✓ Seluruh Guru &amp; Karyawan sudah memiliki biometrik wajah!</strong>
                            </div>
                        `;
                    }
                } else {
                    studentSelect.disabled = false;
                    studentSelect.innerHTML = `<option value="">-- Pilih Guru / Karyawan (${enrolledStudents.length} Belum Terdaftar) --</option>`;

                    enrolledStudents.forEach(emp => {
                        const opt = document.createElement('option');
                        opt.value = emp.id;
                        const roleTag = emp.type === 'karyawan' ? '[KARYAWAN]' : '[GURU]';
                        opt.textContent = `${roleTag} NIP ${emp.nip}: ${emp.name}`;
                        opt.dataset.name = emp.name;
                        opt.dataset.nip = emp.nip;
                        opt.dataset.role = emp.type;
                        opt.dataset.category = 'employee';
                        studentSelect.appendChild(opt);
                    });

                    if (studentMeta) {
                        studentMeta.style.display = 'none';
                    }
                }
            }
        } catch (err) {
            showAlert('Kesalahan jaringan saat memuat data: ' + err.message, 'error');
        }
    }

    /**
     * Escape HTML helper
     */
    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /**
     * Tampilkan detail pemilih yang sedang dipilih
     */
    function updateStudentMeta() {
        if (!studentSelect || !studentMeta) return;
        const selectedOpt = studentSelect.options[studentSelect.selectedIndex];
        
        if (!studentSelect.value || !selectedOpt) {
            studentMeta.style.display = 'none';
            checkReadyToCapture();
            return;
        }

        studentMeta.style.display = 'block';
        if (currentCategory === 'employee') {
            const roleName = selectedOpt.dataset.role === 'karyawan' ? 'Tenaga Kependidikan (Karyawan)' : 'Tenaga Pendidik (Guru)';
            studentMeta.innerHTML = `
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 16px; margin-top:8px;">
                    <div style="font-size:14px; font-weight:700; color:#1e293b;">${escapeHtml(selectedOpt.dataset.name)}</div>
                    <div style="font-size:12.5px; color:#64748b; margin-top:2px;">
                        Kategori: <strong>${escapeHtml(roleName)}</strong> &bull; NIP: <strong>${escapeHtml(selectedOpt.dataset.nip || '-')}</strong>
                    </div>
                    <div style="margin-top:6px;">
                        <span class="badge badge-warning" style="font-size:11px;">Belum Terdaftar Biometrik</span>
                    </div>
                </div>
            `;
        } else {
            studentMeta.innerHTML = `
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 16px; margin-top:8px;">
                    <div style="font-size:14px; font-weight:700; color:#1e293b;">${escapeHtml(selectedOpt.dataset.name)}</div>
                    <div style="font-size:12.5px; color:#64748b; margin-top:2px;">
                        Kelas: <strong>${escapeHtml(selectedOpt.dataset.className)}</strong> &bull; Absen: <strong>${escapeHtml(String(selectedOpt.dataset.attendanceNumber || '').padStart(2, '0'))}</strong>
                    </div>
                    <div style="margin-top:6px;">
                        <span class="badge badge-warning" style="font-size:11px;">Belum Terdaftar Biometrik</span>
                    </div>
                </div>
            `;
        }

        checkReadyToCapture();
    }

    /**
     * Periksa apakah tombol capture boleh aktif
     */
    function checkReadyToCapture() {
        if (!btnCapture) return;
        const hasVoter = studentSelect && studentSelect.value !== '';
        const hasFace = currentDescriptor !== null;
        const ready = isModelLoaded && isCameraActive && hasVoter && hasFace;
        btnCapture.disabled = !ready;
    }

    /**
     * Cache Proxy untuk Model AI menggunakan CacheStorage Browser
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
     * Resolusi Path Model Dinamis
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
     * 2. Inisialisasi Model Face-API dari folder offline
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
            if (isCameraActive) {
                updateBadge('Kamera & AI Siap', 'secondary');
            } else {
                updateBadge('Model AI Siap', 'secondary');
            }
            checkReadyToCapture();
        } catch (err) {
            console.error('Error loading face-api models:', err);
            updateBadge('Gagal Memuat Model AI', 'danger');
            showAlert('Gagal memuat model neural network offline: ' + err.message, 'error');
        }
    }

    /**
     * 3. Start Webcam (Langsung aktif cepat)
     */
    async function startCamera() {
        try {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                showAlert('Browser ini tidak mendukung akses webcam (MediaDevices API). Pastikan menggunakan HTTPS atau localhost.', 'error');
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
            if (isModelLoaded) {
                updateBadge('Kamera & AI Siap', 'secondary');
            } else {
                updateBadge('Kamera Aktif (Menyiapkan AI...)', 'warning');
            }

            // Set ukuran canvas
            canvas.width = video.videoWidth || 640;
            canvas.height = video.videoHeight || 480;

            startDetectionLoop();
        } catch (err) {
            console.error('Webcam error:', err);
            showAlert('Tidak dapat mengakses webcam: ' + err.message + '. Pastikan izin webcam diberikan di browser.', 'error');
            updateBadge('Kamera Mati', 'danger');
        }
    }

    /**
     * 4. Throttled Detection Loop (200ms)
     */
    function startDetectionLoop() {
        if (detectionTimer) clearInterval(detectionTimer);

        const options = new faceapi.TinyFaceDetectorOptions({
            inputSize: 320,
            scoreThreshold: 0.5
        });

        detectionTimer = setInterval(async () => {
            if (!isModelLoaded || !isCameraActive || video.paused || video.ended) return;

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

                if (resized.length === 0) {
                    currentDescriptor = null;
                    stage.classList.remove('matched', 'warning', 'error');
                    updateBadge('Mencari wajah...', 'secondary');
                } else if (resized.length > 1) {
                    currentDescriptor = null;
                    stage.classList.remove('matched');
                    stage.classList.add('warning');
                    updateBadge(`Terdeteksi ${resized.length} wajah (Harus 1)`, 'warning');

                    // Gambar kotak kuning untuk setiap wajah
                    ctx.strokeStyle = '#eab308';
                    ctx.lineWidth = 3;
                    resized.forEach(d => {
                        const { x, y, width, height } = d.detection.box;
                        ctx.strokeRect(x, y, width, height);
                    });
                } else {
                    // Tepat 1 wajah
                    const d = resized[0];
                    currentDescriptor = d.descriptor;
                    stage.classList.remove('warning', 'error');
                    stage.classList.add('matched');
                    updateBadge('✓ Wajah Terdeteksi & Siap', 'success');

                    // Gambar bounding box hijau
                    ctx.strokeStyle = '#22c55e';
                    ctx.lineWidth = 3;
                    const { x, y, width, height } = d.detection.box;
                    ctx.strokeRect(x, y, width, height);
                }

                checkReadyToCapture();
            } catch (err) {
                console.error('Detection frame error:', err);
            }
        }, 200);
    }

    /**
     * 5. Capture dan Simpan Descriptor ke Backend
     */
    async function handleEnroll() {
        const voterId = studentSelect ? studentSelect.value : null;
        if (!voterId) {
            showAlert('Silakan pilih pemilih terlebih dahulu dari dropdown.', 'warning');
            return;
        }

        if (!currentDescriptor || currentDescriptor.length !== 128) {
            showAlert('Wajah belum terdeteksi dengan jelas. Harap posisikan wajah di depan kamera.', 'warning');
            return;
        }

        const selectedOpt = studentSelect.options[studentSelect.selectedIndex];
        const voterName = selectedOpt ? selectedOpt.dataset.name : `ID ${voterId}`;

        btnCapture.disabled = true;
        btnCapture.textContent = 'Menyimpan Biometrik...';

        try {
            const descriptorArray = Array.from(currentDescriptor);
            const res = await fetch('api/enroll_face.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({
                    voter_type: currentCategory,
                    voter_id: parseInt(voterId, 10),
                    student_id: parseInt(voterId, 10),
                    descriptor: descriptorArray
                })
            });

            const data = await res.json();

            if (data.success) {
                const labelSuccess = currentCategory === 'employee'
                    ? `Wajah ${(data.voter && data.voter.name) || voterName} telah tersimpan.`
                    : `Wajah siswa ${(data.student && data.student.name) || (data.voter && data.voter.name) || voterName} telah tersimpan.`;
                showAlert(`Pendaftaran berhasil. ${labelSuccess} Pemilih dikeluarkan dari antrean pendaftaran.`, 'success');
                // Refresh list pemilih: pemilih yang baru didaftarkan akan otomatis hilang dari dropdown
                const selectedClass = classFilter ? classFilter.value : '';
                await loadVoters(selectedClass);
                studentSelect.value = '';
                updateStudentMeta();
                currentDescriptor = null;
                if (ctx && canvas) {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                }
            } else {
                showAlert(`Gagal mendaftarkan wajah: ${data.message || 'Error tidak diketahui'}`, 'error');
            }
        } catch (err) {
            console.error('Enrollment error:', err);
            showAlert('Terjadi kesalahan saat mengirim data ke server: ' + err.message, 'error');
        } finally {
            btnCapture.textContent = 'Daftarkan Wajah';
            checkReadyToCapture();
        }
    }

    /**
     * Init Listener
     */
    async function init() {
        if (btnCatStudent) {
            btnCatStudent.addEventListener('click', async () => {
                if (currentCategory === 'student') return;
                currentCategory = 'student';
                btnCatStudent.className = 'btn-kiosk btn-kiosk-primary';
                btnCatEmployee.className = 'btn-kiosk btn-kiosk-outline';
                await loadVoters(classFilter ? classFilter.value : '');
                studentSelect.value = '';
                updateStudentMeta();
            });
        }

        if (btnCatEmployee) {
            btnCatEmployee.addEventListener('click', async () => {
                if (currentCategory === 'employee') return;
                currentCategory = 'employee';
                btnCatEmployee.className = 'btn-kiosk btn-kiosk-primary';
                btnCatStudent.className = 'btn-kiosk btn-kiosk-outline';
                await loadVoters();
                studentSelect.value = '';
                updateStudentMeta();
            });
        }

        if (classFilter) {
            classFilter.addEventListener('change', (e) => {
                loadVoters(e.target.value);
            });
        }

        if (studentSelect) {
            studentSelect.addEventListener('change', updateStudentMeta);
        }

        if (btnCapture) {
            btnCapture.addEventListener('click', handleEnroll);
        }

        const urlParams = new URLSearchParams(window.location.search);
        const preClassId = urlParams.get('class_id') || '';
        const preStudentId = urlParams.get('student_id') || '';
        const preType = urlParams.get('type') || urlParams.get('voter_type') || '';

        if (preType === 'employee') {
            currentCategory = 'employee';
            if (btnCatEmployee && btnCatStudent) {
                btnCatEmployee.className = 'btn-kiosk btn-kiosk-primary';
                btnCatStudent.className = 'btn-kiosk btn-kiosk-outline';
            }
        }

        // 1. Pasang proxy cache browser
        setupModelCacheProxy();

        // 2. Langsung jalankan kamera tanpa menunggu download model AI
        const cameraPromise = startCamera();

        // 3. Jalankan loading data dan model secara paralel bersamaan dengan kamera
        await Promise.all([
            cameraPromise,
            loadVoters(preClassId),
            loadModels()
        ]);

        if (currentCategory === 'student' && preClassId && classFilter) {
            classFilter.value = preClassId;
        }

        if (preStudentId && studentSelect) {
            studentSelect.value = preStudentId;
            updateStudentMeta();
        }
    }

    // Jalankan saat DOM siap
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
