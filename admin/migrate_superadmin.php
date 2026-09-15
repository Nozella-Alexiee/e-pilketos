<?php
/**
 * Safe Migration: Tambah Tabel Activity Logs & User Superadmin
 * E-Pilketos v2.0 - SMK SIG
 * 
 * 100% Non-destructive: Tidak menghapus atau mengubah tabel students,
 * employees, classes, candidates, atau votes.
 */
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDb();

    // 1. Buat tabel activity_logs jika belum ada
    $isMysql = (DB_DRIVER === 'mysql');
    if ($isMysql) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            username VARCHAR(50) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'admin',
            action VARCHAR(50) NOT NULL,
            details TEXT NOT NULL,
            ip_address VARCHAR(45) NOT NULL DEFAULT '',
            user_agent VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_log_user (username),
            INDEX idx_log_action (action),
            INDEX idx_log_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NULL,
            username VARCHAR(50) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'admin',
            action VARCHAR(50) NOT NULL,
            details TEXT NOT NULL,
            ip_address VARCHAR(45) NOT NULL DEFAULT '',
            user_agent VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    // 2. Tambahkan user superadmin jika belum ada
    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = 'superadmin' LIMIT 1");
    $stmtCheck->execute();
    $existing = $stmtCheck->fetchColumn();

    if (!$existing) {
        $passHash = password_hash('smksig123', PASSWORD_BCRYPT);
        $stmtInsert = $pdo->prepare("INSERT INTO users (username, password_hash, name, role) VALUES ('superadmin', ?, 'Super Administrator', 'superadmin')");
        $stmtInsert->execute([$passHash]);
        $statusMsg = "Tabel activity_logs dan Akun Superadmin berhasil dibuat!";
    } else {
        // Pastikan role-nya adalah superadmin
        $pdo->prepare("UPDATE users SET role = 'superadmin' WHERE username = 'superadmin'")->execute();
        $statusMsg = "Tabel activity_logs siap dan Akun Superadmin sudah aktif.";
    }

    if (php_sapi_name() === 'cli') {
        echo "[OK] $statusMsg\n";
    } else {
        echo "<div style='font-family:sans-serif;padding:20px;background:#ecfdf5;color:#047857;border-radius:8px;border:1px solid #a7f3d0;'>
                <h3>Sukses Migrasi Superadmin & Activity Logs</h3>
                <p>$statusMsg</p>
                <p>Username: <strong>superadmin</strong> | Password: <strong>smksig123</strong></p>
                <p><a href='../admin/login.php' style='color:#047857;font-weight:bold;'>&larr; Buka Panel Login Admin</a></p>
              </div>";
    }
} catch (Throwable $e) {
    if (php_sapi_name() === 'cli') {
        echo "[ERROR] Migrasi gagal: " . $e->getMessage() . "\n";
    } else {
        echo "<div style='font-family:sans-serif;padding:20px;background:#fef2f2;color:#b91c1c;border-radius:8px;border:1px solid #fecaca;'>
                <h3>Gagal Migrasi</h3>
                <p>" . htmlspecialchars($e->getMessage()) . "</p>
              </div>";
    }
}
