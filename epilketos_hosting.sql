-- =============================================================================
-- E-PILKETOS v2.0 - SKEMA BASIS DATA RESMI UNTUK WEB HOSTING (ProFreeHost / cPanel)
-- SMK SEMEN GRESIK (SMKS SEMEN GRESIK - YAYASAN SEMEN INDONESIA)
-- =============================================================================
-- BASIS DATA KOSONGAN (CLEAN SLATE):
-- - Candidates : KOSONG (0 baris, bebas data palsu / dummy)
-- - Students   : KOSONG (0 baris, siap diisi DPT resmi via panel admin / bilik suara)
-- - Votes      : KOSONG (0 baris, siap untuk hari pemilihan)
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Bersihkan seluruh tabel lama jika ada
DROP TABLE IF EXISTS `votes`;
DROP TABLE IF EXISTS `students`;
DROP TABLE IF EXISTS `employees`;
DROP TABLE IF EXISTS `candidates`;
DROP TABLE IF EXISTS `classes`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
-- 1. TABEL: users (Administrator Utama & Panitia)
-- -----------------------------------------------------------------------------
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `role` VARCHAR(20) DEFAULT 'admin',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. TABEL: classes (18 Kelas Resmi SMK Semen Gresik)
-- -----------------------------------------------------------------------------
CREATE TABLE `classes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE,
    `grade` VARCHAR(10) NOT NULL,
    `major` VARCHAR(50) NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. TABEL: candidates (Pasangan Calon Ketua OSIS - KOSONGAN)
-- -----------------------------------------------------------------------------
CREATE TABLE `candidates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `number` VARCHAR(10) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `class` VARCHAR(50) NOT NULL,
    `photo` VARCHAR(255) DEFAULT '',
    `vision` TEXT NOT NULL,
    `mission` TEXT NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. TABEL: students (Daftar Pemilih / DPT Siswa - KOSONGAN)
-- -----------------------------------------------------------------------------
CREATE TABLE `students` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `class_id` INT NOT NULL,
    `attendance_number` INT NOT NULL,
    `face_descriptor` LONGTEXT DEFAULT NULL,
    `face_enrolled_at` DATETIME DEFAULT NULL,
    `has_voted` TINYINT(1) DEFAULT 0,
    `voted_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_class_absen` (`class_id`, `attendance_number`),
    CONSTRAINT `fk_student_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. TABEL: employees (Daftar Pemilih / DPT Guru & Karyawan - KOSONGAN)
-- -----------------------------------------------------------------------------
CREATE TABLE `employees` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nip` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `type` ENUM('guru', 'karyawan') NOT NULL DEFAULT 'guru',
    `face_descriptor` LONGTEXT DEFAULT NULL,
    `face_enrolled_at` DATETIME DEFAULT NULL,
    `has_voted` TINYINT(1) DEFAULT 0,
    `voted_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. TABEL: votes (Kotak Suara Anonim / Secret Ballot - KOSONGAN)
-- -----------------------------------------------------------------------------
CREATE TABLE `votes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `candidate_id` INT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_votes_candidate_id` (`candidate_id`),
    CONSTRAINT `fk_vote_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. TABEL: settings (Pengaturan Sistem Pemilihan & Berita Acara)
-- -----------------------------------------------------------------------------
CREATE TABLE `settings` (
    `id` INT PRIMARY KEY,
    `election_name` VARCHAR(150) NOT NULL,
    `election_period` VARCHAR(50) NOT NULL,
    `election_status` VARCHAR(20) DEFAULT 'OPEN',
    `show_results_to_students` TINYINT(1) DEFAULT 0,
    `headmaster_name` VARCHAR(100) DEFAULT '',
    `headmaster_nip` VARCHAR(50) DEFAULT '',
    `counselor_name` VARCHAR(100) DEFAULT '',
    `counselor_nip` VARCHAR(50) DEFAULT '',
    `committee_name` VARCHAR(100) DEFAULT '',
    `committee_title` VARCHAR(100) DEFAULT 'Ketua Panitia Pemilihan',
    `letter_number` VARCHAR(100) DEFAULT '',
    `letter_datetime` VARCHAR(50) DEFAULT '',
    `letter_sign_date` VARCHAR(50) DEFAULT '',
    `letter_city` VARCHAR(100) DEFAULT 'Gresik',
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- DATA AWAL RESMI (SEED DATA RESMI - TANPA KANDIDAT DUMMY / PALSU)
-- =============================================================================

-- 1. Akun Admin Default (Username: admin | Password: smksig123)
INSERT INTO `users` (`id`, `username`, `password_hash`, `name`, `role`) VALUES
(1, 'admin', '$2y$12$HxWYS48sHlapdViEaSutd.AsRsQQ265czJ.IJFtOEqsQiU3o/yKv6', 'Administrator Utama', 'admin');

-- 2. 18 Kelas Resmi SMK Semen Gresik
INSERT INTO `classes` (`id`, `name`, `grade`, `major`, `is_active`) VALUES
(1, 'X RPL 1', 'X', 'RPL', 1),
(2, 'X RPL 2', 'X', 'RPL', 1),
(3, 'X TOI 1', 'X', 'TOI', 1),
(4, 'X TKRO 1', 'X', 'TKRO', 1),
(5, 'X TP 1', 'X', 'TP', 1),
(6, 'X KI 1', 'X', 'KI', 1),
(7, 'XI RPL 1', 'XI', 'RPL', 1),
(8, 'XI RPL 2', 'XI', 'RPL', 1),
(9, 'XI TOI 1', 'XI', 'TOI', 1),
(10, 'XI TKRO 1', 'XI', 'TKRO', 1),
(11, 'XI TP 1', 'XI', 'TP', 1),
(12, 'XI KI 1', 'XI', 'KI', 1),
(13, 'XII RPL 1', 'XII', 'RPL', 1),
(14, 'XII RPL 2', 'XII', 'RPL', 1),
(15, 'XII TOI 1', 'XII', 'TOI', 1),
(16, 'XII TKRO 1', 'XII', 'TKRO', 1),
(17, 'XII TP 1', 'XII', 'TP', 1),
(18, 'XII KI 1', 'XII', 'KI', 1);

-- 3. Pengaturan Resmi SMK SIG (Status OPEN, Periode 2026/2027)
INSERT INTO `settings` (
    `id`, 
    `election_name`, 
    `election_period`, 
    `election_status`, 
    `show_results_to_students`,
    `headmaster_name`,
    `headmaster_nip`,
    `counselor_name`,
    `counselor_nip`,
    `committee_name`,
    `committee_title`,
    `letter_number`,
    `letter_city`
) VALUES (
    1, 
    'Pemilihan Ketua OSIS SMK SIG', 
    '2026/2027', 
    'OPEN', 
    0,
    'Choirul Ichsan, S.Psi.',
    '19750812 200501 1 004',
    'Hajar Alia Rachmi, S.Pd.',
    '19880421 201101 2 015',
    'Panitia Pemilihan Siswa (KPU OSIS)',
    'Ketua Panitia Pemilihan',
    'BA.2026/PILKETOS/SMK-SIG/09',
    'Gresik'
);
