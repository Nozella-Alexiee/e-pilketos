<?php
/**
 * One-time CLI migration: create `employees` table for DPT Guru & Karyawan.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/database.php';

try {
    $pdo = getDb();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `employees` (
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
    ");
    fwrite(STDOUT, "Employees table migration completed successfully.\n");
} catch (Throwable $e) {
    fwrite(STDERR, "Employees table migration failed: " . $e->getMessage() . "\n");
    exit(1);
}
