<?php
/**
 * E-Pilketos v2.0 - Database Initialization & Seed Script
 * SMK SIG (Sekolah Menengah Kejuruan SIG)
 */

require_once __DIR__ . '/database.php';

function initDatabase(PDO $pdo): void {
    // 1. Table users (Admin & Panitia)
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        name VARCHAR(100) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'admin',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Table classes (Data Kelas)
    $pdo->exec("CREATE TABLE IF NOT EXISTS classes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(50) NOT NULL UNIQUE,
        grade VARCHAR(10) NOT NULL,
        major VARCHAR(50) NOT NULL,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 3. Table students (Data Siswa/DPT)
    $pdo->exec("CREATE TABLE IF NOT EXISTS students (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(100) NOT NULL,
        class_id INTEGER NOT NULL,
        attendance_number INTEGER NOT NULL,
        face_descriptor LONGTEXT NULL,
        face_enrolled_at DATETIME NULL,
        has_voted INTEGER NOT NULL DEFAULT 0,
        voted_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
        UNIQUE(class_id, attendance_number)
    )");

    // 4. Table candidates (Kandidat Ketua OSIS)
    $pdo->exec("CREATE TABLE IF NOT EXISTS candidates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        number VARCHAR(10) NOT NULL UNIQUE,
        name VARCHAR(100) NOT NULL,
        class VARCHAR(50) NOT NULL,
        photo VARCHAR(255) DEFAULT '',
        vision TEXT NOT NULL,
        mission TEXT NOT NULL,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 5. Anonymous ballot box.  Voter eligibility lives only in students.
    $pdo->exec("CREATE TABLE IF NOT EXISTS votes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        candidate_id INTEGER NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE RESTRICT
    )");

    // 6. Table settings (Pengaturan Sistem & Pejabat Berita Acara)
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY,
        election_name VARCHAR(150) NOT NULL DEFAULT 'Pemilihan Ketua OSIS SMK SIG',
        election_period VARCHAR(50) NOT NULL DEFAULT '2026/2027',
        election_status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
        show_results_to_students INTEGER NOT NULL DEFAULT 1,
        headmaster_name VARCHAR(150) NOT NULL DEFAULT 'Choirul Ichsan, S.Psi.',
        headmaster_nip VARCHAR(50) NOT NULL DEFAULT '-',
        counselor_name VARCHAR(150) NOT NULL DEFAULT 'Hajar Alia Rachmi, S.Pd.',
        counselor_nip VARCHAR(50) NOT NULL DEFAULT '-',
        committee_name VARCHAR(150) NOT NULL DEFAULT 'Panitia Pemilihan OSIS SMK SIG',
        committee_title VARCHAR(100) NOT NULL DEFAULT 'Ketua Panitia Pilketos',
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Auto-migration check: tambahkan kolom jika belum ada
    $cols = $pdo->query("PRAGMA table_info(settings)")->fetchAll();
    $existingCols = array_column($cols, 'name');
    if (!in_array('headmaster_name', $existingCols)) {
        $pdo->exec("ALTER TABLE settings ADD COLUMN headmaster_name VARCHAR(150) DEFAULT 'Choirul Ichsan, S.Psi.'");
        $pdo->exec("ALTER TABLE settings ADD COLUMN headmaster_nip VARCHAR(50) DEFAULT '-'");
        $pdo->exec("ALTER TABLE settings ADD COLUMN counselor_name VARCHAR(150) DEFAULT 'Hajar Alia Rachmi, S.Pd.'");
        $pdo->exec("ALTER TABLE settings ADD COLUMN counselor_nip VARCHAR(50) DEFAULT '-'");
        $pdo->exec("ALTER TABLE settings ADD COLUMN committee_name VARCHAR(150) DEFAULT 'Panitia Pemilihan OSIS SMK SIG'");
        $pdo->exec("ALTER TABLE settings ADD COLUMN committee_title VARCHAR(100) DEFAULT 'Ketua Panitia Pilketos'");
    }

    // Add non-destructive columns to an existing SQLite installation.  The
    // identity-linked votes table is intentionally not rewritten here: run
    // config/migrate_secret_ballot.php once, with a database backup, for that
    // explicit irreversible schema change.
    $studentCols = array_column($pdo->query("PRAGMA table_info(students)")->fetchAll(), 'name');
    if (!in_array('face_descriptor', $studentCols, true)) {
        $pdo->exec("ALTER TABLE students ADD COLUMN face_descriptor LONGTEXT NULL");
    }
    if (!in_array('face_enrolled_at', $studentCols, true)) {
        $pdo->exec("ALTER TABLE students ADD COLUMN face_enrolled_at DATETIME NULL");
    }
    if (!in_array('voted_at', $studentCols, true)) {
        $pdo->exec("ALTER TABLE students ADD COLUMN voted_at DATETIME NULL");
    }

    // Seed default settings if not exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM settings WHERE id = 1");
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO settings (id, election_name, election_period, election_status, show_results_to_students, headmaster_name, headmaster_nip, counselor_name, counselor_nip, committee_name, committee_title) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(['Pemilihan Ketua OSIS', '2026/2027', 'OPEN', 1, 'Choirul Ichsan, S.Psi.', '-', 'Hajar Alia Rachmi, S.Pd.', '-', 'Panitia Pemilihan OSIS SMK SIG', 'Ketua Panitia Pilketos']);
    }

    // Seed default admin user (admin / smksig123)
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    if ($stmt->fetchColumn() == 0) {
        $passHash = password_hash('smksig123', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, name, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['admin', $passHash, 'Administrator Pilketos', 'admin']);
    }

    // Seed default SMK SIG Classes if empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM classes");
    if ($stmt->fetchColumn() == 0) {
        $classes = [
            // Kelas X
            ['X RPL 1', 'X', 'RPL', 1],
            ['X RPL 2', 'X', 'RPL', 1],
            ['X TOI 1', 'X', 'TOI', 1],
            ['X TKRO 1', 'X', 'TKRO', 1],
            ['X TP 1', 'X', 'TP', 1],
            ['X KI 1', 'X', 'KI', 1],
            // Kelas XI
            ['XI RPL 1', 'XI', 'RPL', 1],
            ['XI RPL 2', 'XI', 'RPL', 1],
            ['XI TOI 1', 'XI', 'TOI', 1],
            ['XI TKRO 1', 'XI', 'TKRO', 1],
            ['XI TP 1', 'XI', 'TP', 1],
            ['XI KI 1', 'XI', 'KI', 1],
            // Kelas XII
            ['XII RPL 1', 'XII', 'RPL', 1],
            ['XII RPL 2', 'XII', 'RPL', 1],
            ['XII TOI 1', 'XII', 'TOI', 1],
            ['XII TKRO 1', 'XII', 'TKRO', 1],
            ['XII TP 1', 'XII', 'TP', 1],
            ['XII KI 1', 'XII', 'KI', 1],
        ];

        $insClass = $pdo->prepare("INSERT INTO classes (name, grade, major, is_active) VALUES (?, ?, ?, ?)");
        foreach ($classes as $c) {
            $insClass->execute($c);
        }
    }

    // Catatan: Tabel candidates dan students dibiarkan kosong agar diisi data resmi sekolah
}

// Auto-run initialization when file is executed directly or included
$pdo = getDb();
initDatabase($pdo);

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo "Database initialized and seeded successfully!\n";
}
