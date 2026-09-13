# Proof of Concept (POC) Face Recognition Pilketos - SMK SIG

Modul Proof of Concept (POC) pengenalan wajah (*face recognition*) berbasis browser untuk sistem E-Pilketos SMK SIG. Modul ini berjalan **100% di sisi klien (browser)** tanpa membutuhkan runtime Python, Node.js, atau server komputasi terpisah.

---

## 1. Arsitektur & Teknologi

| Komponen | Teknologi yang Digunakan | Penjelasan |
| :--- | :--- | :--- |
| **Face Engine** | `@vladmandic/face-api` (v1.7.15) | Bundle kompilasi JavaScript murni (Vanilla JS) berbasis TensorFlow.js. |
| **Model Deteksi** | `TinyFaceDetector` (Input: 320x320) | Ringan, cepat (~15–30 ms), optimal untuk webcam laptop standar. |
| **Model Landmark** | `FaceLandmark68Net` | Ekstraksi 68 titik kontur wajah untuk normalisasi rotasi & sudut. |
| **Model Recognition** | `FaceRecognitionNet` (ResNet-34) | Menghasilkan vektor biometrik 128 dimensi (*descriptor*). |
| **Model Weights** | Offline (`assets/models/`) | File bobot biner (total ~6.8 MB) dimuat lokal tanpa koneksi internet luar. |
| **Backend API** | PHP Native + PDO | Endpoint REST sederhana untuk penyimpanan dan verifikasi siswa. |
| **Database** | MariaDB / MySQL | Kolom `face_descriptor LONGTEXT` pada tabel `students`. |

> **Catatan Keamanan & Privasi Biometrik:**
> Sistem **tidak pernah** menyimpan foto mentah siswa ke server ataupun disk. Yang disimpan ke database hanyalah representasi matematis berupa array 128 angka float. Data ini tidak dapat direkonstruksi kembali menjadi foto wajah asli (*one-way mathematical embedding*).

---

## 2. Struktur Direktori

Seluruh modul POC diisolasi secara ketat di dalam folder `face-poc/` tanpa mengubah kode sistem voting utama:

```text
face-poc/
├── index.php                      # Halaman Bilik Identifikasi Kiosk (Live Recognition)
├── enroll.php                     # Halaman Registrasi / Pendaftaran Wajah Siswa
├── README.md                      # Dokumentasi teknis & panduan pengujian
├── api/
│   ├── get_students.php           # API daftar siswa & kelas untuk dropdown
│   ├── enroll_face.php            # API simpan vektor biometrik (128 float) ke DB
│   ├── get_faces.php              # API biometrik seluruh siswa untuk pencocokan browser
│   └── verify_voter.php           # API validasi status DPT, hak suara & status pemilihan
└── assets/
    ├── css/
    │   └── face-poc.css           # Desain Kiosk formal SMK SIG & overlay kamera
    ├── js/
    │   ├── face-api.js            # Library AI Face-API (Offline standalone)
    │   ├── face-poc-enroll.js     # Logika pendaftaran wajah & validasi kamera
    │   └── face-poc-recognize.js  # Logika deteksi, pencocokan & state machine kiosk
    └── models/                    # Bobot neural network lokal (manifest + shard)
```

---

## 3. Cara Menjalankan Aplikasi

### Persyaratan Lingkungan:
- PHP 8.0 atau lebih baru (dengan ekstensi `pdo_mysql` / `pdo_sqlite`).
- MySQL atau MariaDB (database `epilketos_sig`).
- Browser modern (Google Chrome, Microsoft Edge, Mozilla Firefox) dengan izin akses webcam.

### Menjalankan Server Lokal:
Jalankan perintah berikut di terminal dari root proyek:
```bash
cd /home/alexie/project-alex/e-pilketos
php -S 127.0.0.1:8080
```

Buka URL berikut di browser:
- **Bilik Identifikasi (Kiosk):** `http://localhost:8080/face-poc/index.php`
- **Registrasi Wajah (Enrollment):** `http://localhost:8080/face-poc/enroll.php`

> **Catatan Izin Kamera:**
> Browser modern membatasi akses webcam (`navigator.mediaDevices.getUserMedia`) hanya pada origin aman: `http://localhost`, `http://127.0.0.1`, atau via `https://`.

---

## 4. Panduan Pengujian Langkah demi Langkah (Test Scenarios)

### Skenario 1: Pendaftaran Wajah Siswa (Enrollment)
1. Buka browser ke alamat `http://localhost:8080/face-poc/enroll.php`.
2. Berikan izin saat browser meminta akses kamera (*Allow Camera*).
3. Pada dropdown kelas, pilih **XII RPL 1**.
4. Pada dropdown siswa, pilih **Nozella Alexander - XII RPL 1 (Absen 1)**.
5. Posisikan wajah Anda di dalam bingkai oval panduan webcam.
6. Perhatikan indikator berubah menjadi warna hijau: **"✓ Wajah Terdeteksi & Siap"**.
7. Klik tombol **[ Daftarkan Wajah ]**.
8. Banner sukses akan muncul: *"✓ Pendaftaran Berhasil! Wajah siswa Nozella Alexander (XII RPL 1) telah berhasil tersimpan ke sistem."*

---

### Skenario 2: Identifikasi Wajah di Bilik Suara (Match Success)
1. Buka browser ke alamat `http://localhost:8080/face-poc/index.php`.
2. Berdiri di depan webcam dengan pencahayaan yang jelas.
3. Begitu wajah Anda terdeteksi stabil selama ~400ms (anti-jitter), kamera akan membekukan frame deteksi dan menampilkan kartu identitas siswa:
   ```text
   ┌──────────────────────────────────────────┐
   │ ✓ Wajah dikenali                         │
   │                                          │
   │ Nozella Alexander                        │
   │ XII RPL 1                                │
   │                                          │
   │ Akurasi Biometrik: 93% (Distance: 0.380) │
   │                                          │
   │ [ Ulang ]             [ Lanjut ]         │
   └──────────────────────────────────────────┘
   ```

---

### Skenario 3: Tombol [ Ulang ]
1. Saat kartu identitas muncul, klik tombol **[ Ulang ]**.
2. Kartu identitas hilang, status kamera kembali menjadi **"Mencari wajah..."**, dan loop deteksi aktif kembali secara mulus.

---

### Skenario 4: Tombol [ Lanjut ] (Verifikasi Pemilih Server-Side)
1. Saat kartu identitas muncul kembali, klik tombol **[ Lanjut ]**.
2. Tombol akan menampilkan status *"Memverifikasi..."*.
3. Sistem memanggil backend `api/verify_voter.php` untuk memvalidasi:
   - Apakah siswa terdaftar di DPT?
   - Apakah kelas aktif?
   - Apakah pemilihan berstatus OPEN?
   - Apakah siswa sudah pernah memberikan suara sebelumnya (`has_voted === 0`)?
4. Modal dialog muncul mengonfirmasi: *"Identifikasi Berhasil. Siswa terverifikasi dan berhak memberikan suara."*
5. Klik **[ Selesai & Siswa Berikutnya ]** untuk mereset bilik bagi pemilih berikutnya.

---

### Skenario 5: Deteksi Lebih Dari Satu Wajah (Multiple Faces)
1. Ajak rekan berdiri di sebelah Anda di depan webcam.
2. Ketika terdeteksi 2 wajah atau lebih, kotak penanda berubah warna menjadi kuning.
3. Kiosk menampilkan peringatan:
   ```text
   ⚠️ Terdeteksi lebih dari satu wajah. Pastikan hanya satu siswa berada di depan kamera.
   ```
4. Sistem mengunci proses pencocokan hingga hanya tersisa 1 orang di depan kamera.

---

### Skenario 6: Wajah Tidak Dikenali (Unknown Face)
1. Arahkan kamera ke wajah orang lain yang belum didaftarkan di sistem.
2. Sistem akan menampilkan kotak merah dengan peringatan:
   ```text
   Wajah tidak dikenali. Silakan posisikan wajah dengan jelas dan coba lagi.
   (Distance: 0.620 | Threshold: 0.50)
   ```

---

## 5. Panduan Kalibrasi Threshold (*Distance Threshold*)

Di bagian bawah Bilik Identifikasi (`index.php`), terdapat slider pengatur **Kalibrasi Threshold** (rentang 0.35 s/d 0.65):

- **Default Rekomendasi: `0.50`**
  - Keseimbangan optimal antara keamanan (*False Acceptance Rate / FAR*) dan kemudahan pengenalan (*False Rejection Rate / FRR*) pada pencahayaan dalam ruangan kelas.
- **Threshold Lebih Ketat (`0.40 - 0.45`):**
  - Gunakan jika pencahayaan ruangan sangat terang dan stabil. Mengurangi kemungkinan wajah yang mirip keliru dikenali.
- **Threshold Lebih Longgar (`0.55 - 0.60`):**
  - Gunakan jika webcam memiliki resolusi rendah atau pencahayaan ruangan agak redup. Membantu agar siswa tetap terdeteksi tanpa harus berulang kali mencoba.
