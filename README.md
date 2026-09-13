# E-Pilketos v2.0 — SMK SIG (Sekolah Menengah Kejuruan SIG)

Aplikasi Web Sistem Pemilihan Ketua OSIS (**E-Pilketos v2.0**) resmi untuk **SMK SIG (SMKS Semen Gresik - Semen Indonesia Foundation)**. Didesain secara fungsional, berorientasi institusional (*clean, restrained, information-first*), cepat, nyaman diakses di smartphone oleh siswa, serta mudah dikelola secara penuh oleh admin/panitia sekolah.

---

## 🌟 Keunggulan & Fitur Utama

1. **Desain Institusional Khas SMK SIG**:
   - Skema warna resmi *Deep Maroon / Burgundy* (`#511524`, `#6e1f31`), *Slate/Charcoal* (`#25272c`), dan *Canvas* hangat (`#f6f7f8`).
   - Penempatan logo resmi SMK SIG pada beranda, bilik suara, dan panel login admin.
   - Bersih dari ornamen berlebihan: tanpa *glassmorphism*, tanpa *gradient neon*, tanpa *decorative blobs*.
   - Tipografi terstruktur dan tata letak *digital ballot* standar *Civic Design*.

2. **Alur Pemilihan Siswa yang Ringkas (5 Langkah Bebas Bingung)**:
   - **Langkah 1**: Pilih Kelas (Tingkat X, XI, XII dinamis dari basis data).
   - **Langkah 2**: Pilih Nomor Absen (Daftar siswa pada kelas yang dipilih, dilengkapi pencarian cepat).
   - **Langkah 3**: Surat Suara Digital (Foto resmi, nomor urut tegas, profil, serta pop-up visi & misi).
   - **Langkah 4**: Konfirmasi Pilihan Eksplisit (*"Anda akan memilih Paslon No. XX - Nama. Apakah Anda yakin?"*).
   - **Langkah 5**: Tanda Terima Partisipasi Digital (Status suara langsung terkunci dan tersimpan rahasia).

3. **Perlindungan Anti Double Voting Multi-Layer**:
   - **Lapisan UX / Frontend**: Nomor absen siswa yang telah memilih ditandai *"Sudah Memilih"* dan tombol pemilihan terkunci secara visual.
   - **Lapisan Validasi Backend**: Setiap request diperiksa status `has_voted` dan keberadaan suara di tabel database.
   - **Lapisan Integritas Database**: Indeks `UNIQUE (student_id)` pada tabel `votes` serta eksekusi transaksi atomik (`BEGIN TRANSACTION` -> `COMMIT`). Tidak ada peluang pemilihan ganda meskipun siswa mencoba menembus lewat URL atau request API berulang.

4. **Panel Administrator Sekolah Lengkap**:
   - **Dashboard Ringkasan**: Pemantauan langsung total DPT, suara masuk, partisipasi (%), belum hadir, dan diagram batang perolehan suara paslon.
   - **Kelola Kelas**: CRUD kelas, filter tingkat (X, XI, XII), jurusan (RPL, TOI, TKRO, TP, KI), status aktif, serta agregasi otomatis jumlah siswa per kelas.
   - **Kelola Siswa & Import CSV**: Manajemen DPT siswa, reset status hak suara, pencarian filter, dan fitur **Import CSV Massal** lengkap dengan unduhan template resmi.
   - **Kelola Kandidat**: CRUD kandidat dengan nomor urut unik, upload foto resmi dengan validasi MIME & sanitasi berkas, serta pengaturan visi-misi.
   - **Hasil & Cetak Berita Acara**: Laporan rekapitulasi resmi lengkap dengan format surat dinas sekolah dan kolom tanda tangan Kepala Sekolah, Pembina OSIS, dan Panitia Pilketos (siap cetak / PDF).
   - **Pengaturan Pemilihan**: Kontrol siklus pemilihan (`DRAFT` &rarr; `OPEN` &rarr; `CLOSED`), visibilitas hasil ke siswa, serta proteksi reset kotak suara darurat.

---

## 🚀 Cara Menjalankan Aplikasi

Aplikasi menggunakan **PHP Runtime** murni tanpa ketergantungan framework berat maupun build-step Node.js.

### 1. Jalankan PHP Built-in Server

Buka terminal pada folder proyek:
```bash
cd /home/alexie/project-alex/e-pilketos
php -S 0.0.0.0:8080
```

### 2. Buka di Browser

- **Halaman Pemilih Siswa**: [http://localhost:8080/index.php](http://localhost:8080/index.php)
- **Bilik Suara Langsung**: [http://localhost:8080/vote.php](http://localhost:8080/vote.php)
- **Hasil Suara Publik**: [http://localhost:8080/results.php](http://localhost:8080/results.php)
- **Portal Admin**: [http://localhost:8080/admin/login.php](http://localhost:8080/admin/login.php)

### 3. Kredensial Administrator Default

- **Username**: `admin`
- **Password**: `admin123`
*(Dapat diubah kapan saja melalui menu Pengaturan di panel admin).*

---

## 📁 Struktur Direktori

```
e-pilketos/
├── assets/
│   ├── css/
│   │   └── style.css           # Institutional design system SMK SIG
│   ├── js/
│   │   └── app.js              # Interaksi client-side & modal surat suara
│   └── images/
│       ├── logo-smk-sig.png    # Logo resmi SMK SIG
│       ├── campus-hero.jpg     # Foto pendukung institusional
│       └── candidates/         # Foto profil kandidat
├── config/
│   ├── database.php            # PDO driver (SQLite default / MySQL ready)
│   ├── functions.php           # Helper keamanan, sesi, CSRF & escaping
│   └── init_db.php             # Inisialisasi skema DB & data awal (seeding)
├── data/
│   └── pilketos.sqlite         # Database SQLite lokal (ACID compliant)
├── uploads/
│   └── candidates/             # Direktori penyimpanan unggahan foto kandidat
├── templates/
│   ├── header.php              # Header umum siswa & navigasi
│   ├── footer.php              # Footer institusional & modal visi-misi
│   ├── admin_header.php        # Header & menu panel administrator
│   └── admin_footer.php        # Footer panel administrator
├── admin/
│   ├── index.php               # Dashboard rekapitulasi & grafik real-time
│   ├── login.php               # Halaman login dengan logo SMK SIG
│   ├── logout.php              # Terminasi sesi admin
│   ├── classes.php             # Manajemen data kelas
│   ├── students.php            # Manajemen DPT & bulk import CSV
│   ├── candidates.php          # Manajemen pasangan calon
│   ├── results.php             # Hasil lengkap & cetak berita acara
│   └── settings.php            # Kontrol status pemilihan & reset suara
├── index.php                   # Beranda utama
├── vote.php                    # Bilik suara multi-langkah
├── vote_process.php            # Pemrosesan suara & anti-double voting
├── vote_success.php            # Tanda terima pemilihan berhasil
├── test_e2e.php                # Skrip verifikasi & pengujian otomatis
└── README.md                   # Dokumentasi panduan
```

---

## 🛡️ Aspek Keamanan Sistem

- **Hashing Password**: Menggunakan fungsi standar industri `password_hash()` dengan algoritma Bcrypt.
- **Perlindungan CSRF**: Token acak kriptografis (`csrf_token`) disisipkan dan divalidasi pada seluruh form perubahan data & pemilihan.
- **SQL Injection Prevention**: Menggunakan PDO Prepared Statements dengan parameter binding pada seluruh query database.
- **XSS Sanitization**: Seluruh output data pengguna diescape dengan fungsi `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- **Validasi Unggahan Berkas**: Pemeriksaan tipe MIME ketat (`image/jpeg`, `image/png`, `image/webp`), batas ukuran berkas maksimum 2MB, dan penggantian nama berkas acak yang disanitasi.
- **Database Unique Constraints**: Kolom `votes.student_id` memiliki aturan unik (*unique key*) di level basis data untuk menjamin 1 siswa hanya dapat memilih 1 kali.
