<p align="center">
  <img src="assets/images/logo-smk-sig.png" alt="Logo SMK Semen Gresik" width="110" />
</p>

<h1 align="center">🗳️ E-Pilketos v2.0</h1>

<p align="center">
  <b>Sistem Pemilihan Ketua OSIS Digital Berbasis Biometrik Wajah & Secret Ballot</b><br>
  <i>SMKS Semen Gresik — Yayasan Semen Indonesia</i>
</p>

<p align="center">
  <a href="https://php.net"><img src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.1+"></a>
  <a href="https://mariadb.org"><img src="https://img.shields.io/badge/Database-MariaDB%20%2F%20MySQL-003545?style=for-the-badge&logo=mariadb&logoColor=white" alt="MariaDB"></a>
  <a href="https://github.com/justadudewhohacks/face-api.js"><img src="https://img.shields.io/badge/AI-face--api.js-E34F26?style=for-the-badge&logo=javascript&logoColor=white" alt="Face-API.js"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/License-MIT-2ea44f?style=for-the-badge" alt="MIT License"></a>
  <img src="https://img.shields.io/badge/Architecture-Native%20Zero--Framework-blueviolet?style=for-the-badge" alt="Zero Framework">
</p>

<p align="center">
  <a href="#-komparasi-sistem">Komparasi</a> •
  <a href="#-fitur-unggulan">Fitur Unggulan</a> •
  <a href="#-arsitektur--alur-pemilihan">Arsitektur</a> •
  <a href="#-tech-stack">Tech Stack</a> •
  <a href="#-instalasi-cepat">Instalasi</a> •
  <a href="#-keamanan--integritas">Keamanan</a> •
  <a href="LICENSE">Lisensi</a>
</p>

---

## 📌 Sekilas Proyek

**E-Pilketos v2.0** adalah platform *e-voting* modern yang dirancang khusus untuk memenuhi standar demokrasi pemilihan ketua OSIS di **SMK Semen Gresik**. Dibangun dengan prinsip **kecepatan, kesederhanaan operasional, dan asas LUBER JURDIL**, aplikasi ini menggantikan kotak suara konvensional dengan sistem verifikasi biometrik pengenalan wajah (*face recognition*) dan protokol *secret ballot* murni.

---

## ⚖️ Komparasi Sistem

Mengapa beralih dari pemilihan konvensional berbasis kertas?

| Parameter | 📋 Pemilihan Kertas Konvensional | 🗳️ E-Pilketos v2.0 (Modern) |
| :--- | :--- | :--- |
| **Verifikasi Pemilih** | Tanda tangan di lembar presensi fisik | **AI Face Recognition biometrik real-time (< 1 detik)** |
| **Waktu Rekapitulasi** | 3 – 5 Jam (hitung manual di aula) | **Otomatis & Real-Time (0 detik setelah bilik ditutup)** |
| **Biaya Logistik** | Jutaan rupiah (cetak surat suara, tinta, bilik) | **Rp 0,- (100% Paperless & ramah lingkungan)** |
| **Kerahasiaan Suara** | Rawan terlihat saat kertas dilipat/dicoblos | **Tabel suara terpisah tanpa ID pemilih (*Zero-Trace*)** |
| **Keabsahan Suara** | Rawan suara rusak/coblos ganda | **100% Valid (Validasi atomik tingkat basis data)** |
| **Berita Acara** | Ditulis tangan, rawan selisih angka | **Auto-Generated format dinas resmi siap cetak/PDF** |

---

## ✨ Fitur Unggulan

### 📸 1. Bilik Suara Biometrik Wajah (AI Client-Side)
* Memanfaatkan pustaka **`face-api.js`** berbasis arsitektur *MobileNet SSD* dan *ResNet-34*.
* Ekstraksi 128-dimensi vektor wajah pemilih dilakukan **100% di browser pemilih (*client-side*)**.
* **Hemat Resource:** Server tidak memerlukan GPU khusus dan tidak ada foto wajah mentah yang dikirim ke cloud pihak ketiga.
* Dilengkapi *fallback mode* (verifikasi nomor absen / NIP) jika pencahayaan kamera ruangan kurang memadai.

### 🔒 2. Kerahasiaan Suara Mutlak (*True Secret Ballot*)
* Berbeda dengan e-voting biasa yang menyimpan `user_id` di setiap suara, tabel `votes` pada E-Pilketos v2.0 **sama sekali tidak memiliki relasi ke identitas pemilih**.
* Pemilih hanya diberi *hak klaim* status `has_voted = 1`, sementara kertas suara digital masuk ke kotak suara anonim secara terpisah. Pilihan pemilih mustahil dilacak oleh siapapun, termasuk administrator database.

### 👥 3. DPT Terpadu (Siswa, Guru & Karyawan)
* **18 Rombel Kelas Siswa:** Mencakup seluruh tingkatan (X, XI, XII) dan kompetensi keahlian resmi SMK Semen Gresik: RPL, TOI, TKRO, TP, dan KI.
* **Hak Suara Pendidik & Tenaga Kependidikan:** Panel DPT khusus untuk Guru & Karyawan berbasis NIP/Kode Pegawai.

### 📊 4. Live Quick Count & Cetak Berita Acara Resmi
* Dashboard pemantauan grafik perolehan suara paslon dan persentase partisipasi kehadiran secara *real-time*.
* **Ekspor Berita Acara Dinas:** Sekali klik untuk mencetak Berita Acara resmi pemilihan lengkap dengan nomor surat, tanggal pengesahan, dan kolom tanda tangan Kepala Sekolah, Pembina OSIS, serta Ketua Panitia.

### ⚡ 5. Zero-Build, Zero-Framework (Pure Native Performance)
* Dibangun dengan **Pure PHP 8.1+ & Vanilla JavaScript**.
* Tanpa *node_modules*, tanpa proses *compilation*, dan tanpa framework berat.
* Dapat berjalan mulus di laptop lama, server intranet lokal sekolah, maupun *shared hosting* gratisan (seperti ProFreeHost / cPanel).

---

## 🔄 Arsitektur & Alur Pemilihan

```text
               ┌──────────────────────────────────────────────┐
               │              PEMILIH HADIR                   │
               └──────────────────────┬───────────────────────┘
                                      │
                   ┌──────────────────┴──────────────────┐
                   ▼                                     ▼
       [ Jalur Siswa / DPT ]                   [ Jalur Guru & Karyawan ]
    Pilih Kelas ➔ Absen ➔ Konfirmasi      Pilih Kategori ➔ Masukkan NIP
                   │                                     │
                   └──────────────────┬──────────────────┘
                                      │
                                      ▼
                        ┌───────────────────────────┐
                        │   VERIFIKASI BIOMETRIK    │
                        │  (face-api.js 128D Match) │
                        └─────────────┬─────────────┘
                                      │
                                      ▼
                        ┌───────────────────────────┐
                        │    SURAT SUARA DIGITAL    │
                        │ Foto, No. Urut, Visi-Misi │
                        └─────────────┬─────────────┘
                                      │
                                      ▼
                        ┌───────────────────────────┐
                        │    KONFIRMASI PILIHAN     │
                        │     (Modal Dialog Box)    │
                        └─────────────┬─────────────┘
                                      │
                    ┌─────────────────┴─────────────────┐
                    ▼                                   ▼
        [ Update Status Pemilih ]           [ Masuk Kotak Suara ]
        has_voted = 1 (Terkunci)            INSERT INTO votes (Anonim)
        (Waktu kehadiran tercatat)          (Tanpa ID / Asas Rahasia)
```

---

## 🛠️ Tech Stack

| Lapisan Sistem | Teknologi | Rationale / Kegunaan |
| :--- | :--- | :--- |
| **Runtime & Backend** | Native PHP 8.1+ | PDO Driver, Prepared Statements, Session Hardening |
| **Basis Data** | MariaDB 10.x / MySQL 8.x | Transaksi ACID atomik, integritas data referensial |
| **Model Biometrik AI** | face-api.js (TensorFlow.js) | *Tiny Face Detector*, *68 Face Landmarks*, *ResNet-34 Descriptors* |
| **Antarmuka (Frontend)** | HTML5, CSS3, Vanilla JS | Desain *mobile-first* bertema warna resmi SMK SIG (*Deep Burgundy*) |
| **Format Standar** | JSON, CSV | Kompatibilitas impor massal DPT dari Excel/Dapodik |

---

## 🚀 Panduan Instalasi Cepat

### Prasyarat:
* PHP versi 8.1 atau lebih baru (dengan ekstensi `pdo_mysql` aktif).
* Database MariaDB atau MySQL.

### Langkah 1: Klon Repositori
```bash
git clone https://github.com/Nozella-Alexiee/e-pilketos.git
cd e-pilketos
```

### Langkah 2: Konfigurasi Database
Salin berkas template environment:
```bash
cp .env.example .env
```
Buka file `.env` dan atur koneksi database MySQL lokalmu:
```ini
DB_HOST=127.0.0.1
DB_NAME=epilketos_sig
DB_USER=root
DB_PASS=
```
Import skema dan data awal resmi:
```bash
mysql -u root -p epilketos_sig < database.sql
```

### Langkah 3: Jalankan Web Server
```bash
php -S 0.0.0.0:8080
```

Akses portal melalui peramban:
* 🗳️ **Portal Bilik Suara:** [http://localhost:8080/](http://localhost:8080/)
* 📸 **Bilik Suara Wajah (Face POC):** [http://localhost:8080/face-poc/](http://localhost:8080/face-poc/)
* 📊 **Rekapitulasi Suara Publik:** [http://localhost:8080/results.php](http://localhost:8080/results.php)
* ⚙️ **Panel Administrator:** [http://localhost:8080/admin/login.php](http://localhost:8080/admin/login.php)

---

## 🛡️ Keamanan & Integritas Data

* **Anti Double Voting:** Proteksi berlapis mulai dari penguncian antarmuka, pengecekan backend `has_voted`, hingga transaksi basis data `BEGIN ... COMMIT` untuk mencegah serangan *race condition*.
* **Perlindungan SQL Injection:** Seluruh kueri interaksi basis data menggunakan *PDO Prepared Statements* dengan *parameter binding*.
* **Mitigasi CSRF & XSS:** Setiap formulir aksi dilindungi token acak sesi kriptografis dan seluruh luaran teks disanitasi menggunakan `htmlspecialchars()`.
* **Sanitasi Unggahan Foto:** Pengecekan *magic bytes MIME type*, batasan ukuran maksimal 2MB, dan pengacakan nama berkas foto paslon.

---

## 📁 Struktur Berkas Proyek

```text
e-pilketos/
├── admin/                     # Modul panel kontrol administrator & panitia
│   ├── index.php              # Dashboard analitik & diagram perolehan suara
│   ├── students.php           # Manajemen DPT siswa & fitur import CSV
│   ├── employees.php          # Manajemen DPT guru & karyawan
│   ├── candidates.php         # Manajemen paslon & upload foto resmi
│   └── results.php            # Cetak Berita Acara resmi format dinas
├── assets/                    # Identitas visual, CSS tema SMK SIG, & font ikon
├── config/                    # Koneksi PDO, migrasi skema, & transaksi suara anonim
├── face-poc/                  # Modul biometrik wajah mandiri (kamera & AI)
│   ├── assets/models/         # Bobot bobot neural network face-api.js
│   ├── enroll.php             # Portal registrasi/perekaman vektor wajah
│   └── index.php              # Bilik suara berbasis pengenalan wajah
├── templates/                 # Header & footer standar antarmuka
├── .env.example               # Cetak biru variabel lingkungan
├── database.sql               # Skema basis data resmi (clean slate)
├── index.php                  # Halaman gerbang utama pemilihan
└── vote.php                   # Alur bilik suara digital
```

---

## 👨‍💻 Kontributor & Lisensi

Didesain dan dikembangkan dengan dedikasi untuk kemajuan teknologi dan demokrasi di lingkungan **SMKS Semen Gresik**.

Didistribusikan secara bebas dan terbuka di bawah lisensi resmi [MIT License](LICENSE).
