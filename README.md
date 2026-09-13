# 🗳️ E-Pilketos v2.0 — SMK Semen Gresik

[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![MariaDB](https://img.shields.io/badge/Database-MariaDB%20%2F%20MySQL-003545?style=for-the-badge&logo=mariadb&logoColor=white)](https://mariadb.org)
[![Face-API](https://img.shields.io/badge/AI-face--api.js-E34F26?style=for-the-badge&logo=javascript&logoColor=white)](https://github.com/justadudewhohacks/face-api.js)
[![Platform](https://img.shields.io/badge/Platform-Mobile--First%20Web-success?style=for-the-badge)](https://smksemengresik.sch.id)

Platform E-Voting Pemilihan Ketua OSIS resmi **SMK Semen Gresik (Yayasan Semen Indonesia)**. Menggabungkan kemudahan surat suara digital di smartphone dengan verifikasi biometrik pengenalan wajah (*face recognition*) dan penjaminan asas kerahasiaan suara mutlak (*secret ballot*).

---

## 🎯 Mengapa E-Pilketos v2.0?

Pemilihan konvensional berbasis kertas memakan ribuan lembar fotokopi, rawan keliru hitung, dan butuh waktu berjam-jam untuk rekapitulasi. E-Pilketos v2.0 menghadirkan solusi modern yang efisien dan transparan:

- **⚡ Zero Build-Step, 100% Native:** Tanpa Node.js runtime atau framework berat (Laravel/React). Menggunakan Native PHP & Vanilla JS sehingga super ringan, hemat memori, dan bisa jalan di server lokal sekolah maupun *shared hosting* standar (cPanel / ProFreeHost).
- **📸 Biometrik Wajah Client-Side:** Verifikasi wajah pemilih diproses langsung di peramban HP/laptop menggunakan `face-api.js`. Tidak membebani CPU/GPU server dan tidak ada foto wajah mentah yang dikirim ke cloud pihak ketiga.
- **🔒 Kerahasiaan Suara Mutlak (*Secret Ballot*):** Tabel kotak suara (`votes`) dirancang steril dari identitas pemilih (`student_id` / `employee_id`). Pilihan suara tidak dapat dilacak kembali ke siapapun (bahkan oleh administrator database sekalipun).
- **👥 DPT Terpadu (Siswa, Guru & Karyawan):** Mengakomodasi hak suara seluruh warga sekolah: 18 rombel kelas siswa (X, XI, XII dari jurusan RPL, TOI, TKRO, TP, KI) serta guru dan tenaga kependidikan.
- **📑 Rekapitulasi & Berita Acara Siap Cetak:** Hasil suara dihitung otomatis secara real-time dan dapat langsung dicetak menjadi Berita Acara resmi berformat surat dinas sekolah lengkap dengan kolom tanda tangan Kepala Sekolah & Panitia.

---

## 🔄 Alur Pemilihan Suara

```text
[ Pemilih Datang ]
        │
        ├──► Jalur Siswa  : Pilih Kelas ➔ Pilih Absen ➔ Konfirmasi Identitas
        └──► Jalur Guru   : Pilih Kategori ➔ Masukkan NIP / Nama
        │
        ▼
[ Verifikasi Wajah (Face POC) ]
        │ (Kecocokan vektor biometrik 128-dimensi)
        ▼
[ Surat Suara Digital ]
        │ (Foto paslon, nomor urut, visi-misi)
        ▼
[ Konfirmasi Pilihan ]
        │ (Pilihan dikunci secara atomik)
        ▼
[ Kotak Suara Anonim ] ──► [ Status Pemilih: "Sudah Memilih" ]
```

---

## 🛠️ Tech Stack

| Komponen | Teknologi | Keterangan |
| :--- | :--- | :--- |
| **Backend** | Native PHP 8.1+ | Prepared Statements (PDO), arsitektur modular, atomic transactions |
| **Database** | MariaDB / MySQL | Relasi foreign key, transaksi ACID, dukungan `.env` |
| **Biometrik AI** | face-api.js | Tiny Face Detector + 68 Landmarks + ResNet-34 Face Descriptor |
| **Frontend** | HTML5, CSS3, Vanilla JS | Desain *mobile-first* dengan tema warna resmi SMK SIG (Deep Maroon) |
| **Keamanan** | Bcrypt & Anti-CSRF | Hashing password standar industri & token proteksi sesi |

---

## 🚀 Panduan Instalasi (Lokal)

### 1. Klon Repositori
```bash
git clone https://github.com/Nozella-Alexiee/e-pilketos.git
cd e-pilketos
```

### 2. Konfigurasi Environment & Database
Salin template konfigurasi:
```bash
cp .env.example .env
```
Buka file `.env` dan sesuaikan koneksi database MySQL lokalmu:
```ini
DB_HOST=127.0.0.1
DB_NAME=epilketos_sig
DB_USER=root
DB_PASS=
```
Import skema dan data awal:
```bash
mysql -u root -p epilketos_sig < database.sql
```

### 3. Jalankan Server
```bash
php -S 0.0.0.0:8080
```
Buka di browser:
* 🗳️ **Portal Pemilihan:** `http://localhost:8080/`
* 📸 **Bilik Wajah Biometrik:** `http://localhost:8080/face-poc/`
* 📊 **Hasil Suara Publik:** `http://localhost:8080/results.php`
* ⚙️ **Panel Admin:** `http://localhost:8080/admin/login.php`


---

## 📁 Struktur Direktori

```text
e-pilketos/
├── admin/                 # Modul kontrol panitia (DPT siswa/guru, paslon, berita acara)
├── assets/                # Styling CSS institusional, logo SMK SIG, & font ikon
├── config/                # Konfigurasi PDO, transaksi suara anonim, & keamanan
├── face-poc/              # Modul biometrik wajah (enrollment, kamera, & model AI)
│   └── assets/models/     # Bobot model neural network face-api.js
├── templates/             # Komponen layout antarmuka (header & footer)
├── uploads/               # Direktori berkas unggahan foto paslon kandidat
├── .env.example           # Template variabel lingkungan
├── database.sql           # Skema database resmi & data awal
├── index.php              # Beranda utama pemilihan
├── vote.php               # Bilik suara digital siswa & guru
└── results.php            # Tampilan rekapitulasi perolehan suara
```

---

## 📄 Lisensi & Hak Cipta

Dikembangkan untuk kebutuhan suksesi kepemimpinan OSIS di **SMKS Semen Gresik**.  
Didistribusikan di bawah lisensi terbuka [MIT License](LICENSE).
