<?php
/**
 * E-Pilketos v2.0 - Database Connection
 * SMK SIG (Sekolah Menengah Kejuruan SIG)
 */

if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}

// Load .env file if present
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || str_starts_with($line, '#')) continue;
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, " \t\n\r\0\x0B\"'");
            putenv("$key=$val");
            $_ENV[$key] = $val;
            $_SERVER[$key] = $val;
        }
    }
}

// Database configuration
define('DB_DRIVER', getenv('DB_DRIVER') ?: 'mysql');
define('DB_SQLITE_PATH', __DIR__ . '/../data/pilketos.sqlite');

// Konfigurasi Database ProFreeHost (Otomatis aktif di hosting)
// Catatan: Jika di localhost, sistem tetap membaca file .env lokal (127.0.0.1)
define('DB_HOST', getenv('DB_HOST') ?: 'sql201.ezyro.com');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'ezyro_42902664_pilketos');
define('DB_USER', getenv('DB_USER') ?: 'ezyro_42902664');
define('DB_PASS', getenv('DB_PASS') ?: 'MASUKKAN_PASSWORD_VPANEL_ANDA');

function getDb(): PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    try {
        if (DB_DRIVER === 'sqlite') {
            $dir = dirname(DB_SQLITE_PATH);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $pdo = new PDO('sqlite:' . DB_SQLITE_PATH);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec('PRAGMA foreign_keys = ON;');
            $pdo->exec('PRAGMA journal_mode = WAL;');
        } else {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // Non-destructive compatibility columns only.  The secret-ballot
            // migration is deliberately explicit (config/migrate_secret_ballot.php)
            // so an election is never altered silently during a request.
            static $migrated = false;
            if (!$migrated) {
                $migrated = true;
                try {
                    $chk = $pdo->query("SHOW COLUMNS FROM students LIKE 'face_descriptor'")->fetch();
                    if (!$chk) {
                        $pdo->exec("ALTER TABLE students ADD COLUMN face_descriptor LONGTEXT NULL AFTER attendance_number");
                    }
                    $chk = $pdo->query("SHOW COLUMNS FROM students LIKE 'face_enrolled_at'")->fetch();
                    if (!$chk) {
                        $pdo->exec("ALTER TABLE students ADD COLUMN face_enrolled_at DATETIME NULL AFTER face_descriptor");
                    }
                    $chk = $pdo->query("SHOW COLUMNS FROM students LIKE 'voted_at'")->fetch();
                    if (!$chk) {
                        $pdo->exec("ALTER TABLE students ADD COLUMN voted_at DATETIME NULL AFTER has_voted");
                    }
                } catch (Exception $migrationError) {
                    error_log('Non-destructive student schema check failed: ' . $migrationError->getMessage());
                }
            }
        }
        return $pdo;
    } catch (PDOException $e) {
        error_log('Database connection error: ' . $e->getMessage());
        $isDefaultPass = (DB_PASS === 'MASUKKAN_PASSWORD_VPANEL_ANDA');
        $msg = $isDefaultPass
            ? 'Koneksi database belum dikonfigurasi. Harap buka file <code>config/database.php</code> dan ganti nilai <code>DB_PASS</code> dengan password akun vPanel hosting Anda.'
            : 'Koneksi database gagal (' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '). Harap periksa kembali nama database, user, dan password di <code>config/database.php</code> agar sesuai dengan MySQL Database vPanel Anda.';
        die("<div style='font-family:sans-serif;padding:30px;max-width:600px;margin:50px auto;background:#fff;border-radius:10px;border:1px solid #fecaca;box-shadow:0 4px 20px rgba(0,0,0,0.08);color:#991b1b;'><h2 style='margin-top:0;color:#7b1113;'>Koneksi Database Gagal</h2><p style='color:#334155;line-height:1.6;'>{$msg}</p></div>");
    }
}
