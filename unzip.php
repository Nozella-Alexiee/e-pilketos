<?php
/**
 * E-Pilketos v2.0 - Standalone Deployment Extractor
 * SMK SIG (Sekolah Menengah Kejuruan SIG)
 *
 * File ini mandiri (standalone), tanpa dependensi file eksternal,
 * dirancang khusus agar aman dan berjalan sempurna di shared hosting (ProFreeHost).
 */

// Tampilkan error jika terjadi kendala runtime agar tidak memicu silent 500 error
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, private');

function safe_e(?string $str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Token default panitia
$validToken = 'SMKSIG2026';
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $envContent = (string)@file_get_contents($envFile);
    if (preg_match('/^DEPLOYMENT_TOKEN\s*=\s*(.+)$/m', $envContent, $matches)) {
        $t = trim($matches[1], " \t\n\r\0\x0B\"'");
        if (strlen($t) >= 8) {
            $validToken = $t;
        }
    }
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted = (string)($_POST['deployment_token'] ?? '');
    
    if (!hash_equals($validToken, $submitted)) {
        http_response_code(403);
        $error = 'Passcode deployment salah atau tidak valid.';
    } elseif (!class_exists('ZipArchive')) {
        $error = 'Ekstensi PHP ZipArchive tidak aktif pada server hosting ini.';
    } else {
        $zipFile = __DIR__ . '/e-pilketos-deploy.zip';
        if (!is_file($zipFile)) {
            $error = 'Berkas e-pilketos-deploy.zip tidak ditemukan di folder yang sama dengan unzip.php (htdocs).';
        } else {
            $zip = new ZipArchive();
            $res = $zip->open($zipFile);
            if ($res !== true) {
                $error = 'Gagal membuka e-pilketos-deploy.zip (Kode Error: ' . $res . ').';
            } elseif ($zip->numFiles > 10000) {
                $zip->close();
                $error = 'Arsip ditolak: jumlah berkas terlalu banyak (> 10.000).';
            } else {
                // Simpan konfigurasi database jika user sudah mengisi password vPanel
                $dbConfigFile = __DIR__ . '/config/database.php';
                $savedDbConfig = null;
                if (file_exists($dbConfigFile)) {
                    $cur = (string)@file_get_contents($dbConfigFile);
                    if ($cur !== '' && strpos($cur, 'MASUKKAN_PASSWORD_VPANEL_ANDA') === false) {
                        $savedDbConfig = $cur;
                    }
                }

                // Validasi integritas berkas di dalam zip (mencegah zip-slip & directory traversal)
                $totalSize = 0;
                $valid = true;
                $invalidReason = '';

                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $stat = $zip->statIndex($i);
                    $name = (string)($stat['name'] ?? '');
                    $totalSize += (int)($stat['size'] ?? 0);

                    // Tolak path traversal dan null bytes
                    if ($name === '' || str_contains($name, "\0") || str_starts_with($name, '/')
                        || str_contains($name, '../') || str_contains($name, '..\\')) {
                        $valid = false;
                        $invalidReason = 'Jalur berkas tidak aman: ' . $name;
                        break;
                    }

                    // Tolak jika mencoba menimpa berkas rahasia .env
                    if ($name === '.env') {
                        $valid = false;
                        $invalidReason = 'Berkas .env tidak diizinkan berada di dalam arsip.';
                        break;
                    }
                }

                if (!$valid) {
                    $zip->close();
                    $error = 'Validasi arsip gagal: ' . $invalidReason;
                } elseif ($totalSize > 150 * 1024 * 1024) {
                    $zip->close();
                    $error = 'Ukuran total ekstraksi melebihi batas 150 MB.';
                } else {
                    // Ekstraksi berkas ke direktori htdocs
                    $extractSuccess = $zip->extractTo(__DIR__);
                    $zip->close();

                    if (!$extractSuccess) {
                        $error = 'Gagal mengekstrak berkas. Pastikan folder hosting memiliki izin tulis (writable).';
                    } else {
                        // Kembalikan konfigurasi database sebelumnya jika sudah pernah diisi
                        if ($savedDbConfig !== null) {
                            @file_put_contents($dbConfigFile, $savedDbConfig);
                        }

                        $autoDelete = !empty($_POST['auto_delete']);
                        if ($autoDelete) {
                            @unlink($zipFile);
                            @unlink(__FILE__);
                        }

                        $message = 'Ekstraksi berhasil! Seluruh berkas sistem E-Pilketos v2.0 telah diperbarui.';
                        if ($autoDelete) {
                            $message .= ' Berkas zip dan unzip.php telah otomatis dihapus demi keamanan.';
                        }
                    }
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Deployment E-Pilketos v2.0 - SMK SIG</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #f8fafc; padding: 40px 20px; color: #1e293b; line-height: 1.5; }
    .box { max-width: 520px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 32px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; }
    h1 { font-size: 20px; color: #7b1113; margin: 0 0 8px 0; font-weight: 700; }
    p.sub { font-size: 14px; color: #64748b; margin: 0 0 20px 0; }
    .alert { padding: 14px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; line-height: 1.5; }
    .alert-danger { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }
    .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
    label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #475569; }
    input[type="password"] { width: 100%; box-sizing: border-box; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; margin-bottom: 16px; outline: none; transition: border-color .2s; }
    input[type="password"]:focus { border-color: #7b1113; }
    .checkbox-wrap { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #334155; margin-bottom: 20px; font-weight: normal; cursor: pointer; }
    button { width: 100%; padding: 12px; background: #7b1113; color: #fff; border: none; border-radius: 6px; font-size: 15px; font-weight: 600; cursor: pointer; transition: background .2s; }
    button:hover { background: #5a0b0d; }
    .link-home { display: inline-block; margin-top: 16px; font-size: 14px; color: #7b1113; text-decoration: none; font-weight: 600; }
    .link-home:hover { text-decoration: underline; }
    .hint-box { margin-top: 20px; padding-top: 16px; border-top: 1px solid #f1f5f9; font-size: 12px; color: #64748b; }
    code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 12px; color: #0f172a; }
  </style>
</head>
<body>
<div class="box">
  <h1>E-Pilketos v2.0 - Ekstraktor Deployment</h1>
  <p class="sub">SMK Semen Gresik (SMK SIG)</p>

  <?php if ($error): ?>
    <div class="alert alert-danger">
      <strong>Terjadi Kesalahan:</strong><br>
      <?= safe_e($error) ?>
    </div>
  <?php endif; ?>

  <?php if ($message): ?>
    <div class="alert alert-success">
      <strong>Sukses!</strong><br>
      <?= safe_e($message) ?>
    </div>
    <div style="text-align: center; margin-top: 24px;">
      <a href="index.php" class="link-home">&larr; Buka Beranda Utama E-Pilketos</a>
    </div>
  <?php else: ?>
    <form method="post" autocomplete="off">
      <label for="depToken">Passcode Deployment Panitia:</label>
      <input type="password" id="depToken" name="deployment_token" placeholder="Masukkan Passcode Panitia" required autofocus>
      
      <label class="checkbox-wrap">
        <input type="checkbox" name="auto_delete" value="1" checked>
        Hapus otomatis skrip <code>unzip.php</code> & <code>e-pilketos-deploy.zip</code> setelah ekstraksi
      </label>
      
      <button type="submit">Mulai Ekstraksi Berkas</button>
    </form>
    
    <div class="hint-box">
      Passcode default: <code>SMKSIG2026</code>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
