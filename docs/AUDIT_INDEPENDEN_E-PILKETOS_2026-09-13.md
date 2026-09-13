# Audit Independen Keamanan E-Pilketos v2.0

Tanggal: 2026-09-13
Target: e-pilketos/
Status: CRITICAL RISK — tidak production-ready.

Metode: review source PHP/JS/SQL, schema, PHP lint, konfigurasi, dan uji parser XML lokal. Tidak ada data produksi yang diubah.

## Executive verdict

Unique constraint pada votes.student_id dan transaksi InnoDB cukup kuat mencegah dua row vote dari dua POST concurrent. Namun kontrol identitas dapat dilewati sepenuhnya. Penyerang dapat memilih sebagai siswa lain, membuat pemilih baru, menulis ulang biometrik, mengambil descriptor biometrik, dan mengambil alih admin melalui file reset publik.

Election harus ditahan sampai temuan P1 ditutup dan diuji ulang.

## Temuan P1 — Critical

### 1. Face ID tidak menjadi bukti identitas server-side

File: face-poc/api/verify_voter.php:11-20,42-104; face-poc/assets/js/face-poc-recognize.js:384-400.

verify_voter.php menerima student_id dari JSON publik dan hanya mengecek record tersebut. Tidak ada bukti server bahwa wajah di browser cocok dengan ID. Semua JS/payload browser dapat dimodifikasi.

Proof of concept, bila siswa 1 eligible:

~~~bash
curl -c /tmp/epil.cookie -H 'Content-Type: application/json' \
  --data '{"student_id":1}' \
  https://HOST/e-pilketos/face-poc/api/verify_voter.php
~~~

Request ini langsung membuat voter_student_id=1; tidak diperlukan face match, liveness, PIN, atau operator. Face recognition client-side hanya boleh menjadi UX hint.

### 2. Mode manual publik dapat voting sebagai siapa pun dan membuat DPT baru

File: vote.php:27-93.

vote.php?manual=1 tidak membutuhkan login admin. CSRF token bukan autentikasi. POST dapat memilih class_id/attendance_number siswa lain, memasukkan nama arbitrer, membuat students baru bila kombinasi kelas/absen belum ada, lalu mengisi voter_student_id yang dipercaya vote_process.php.

Immediate mitigation: hapus mode manual publik, atau lindungi GET dan POST manual dengan login operator/admin plus re-authentication.

### 3. Enrollment biometrik tidak dilindungi

File: face-poc/api/enroll_face.php:11-70 dan face-poc/enroll.php.

Endpoint menerima POST tanpa require_admin dan tanpa CSRF. Siapa pun dapat menulis descriptor 128 angka untuk ID siswa mana pun dan menimpa descriptor lama. get_students.php juga publik dan membocorkan roster/status voting.

### 4. Descriptor biometrik dieksfiltrasi publik

File: face-poc/api/get_faces.php:11-43.

Endpoint tanpa autentikasi mengembalikan descriptor 128 dimensi, ID, nama, kelas, nomor absen, dan has_voted. Descriptor biometrik tidak dapat dirotasi seperti password. Matching harus dipindahkan ke server/trusted kiosk atau memakai token satu kali plus faktor kedua.

### 5. Backdoor reset admin

File: reset_admin.php:1-13.

GET publik mereset password admin ke password tetap yang juga ada di source/SQL dan menampilkannya. Hapus file dari webroot, rotate password, dan invalidasi session.

### 6. Updater/unzip publik

File: unzip.php:31-75.

Jika e-pilketos-deploy.zip tertinggal, siapa pun dapat memicu ekstraksi/overwrite aplikasi tanpa login. Hapus updater setelah deploy; jangan deploy dengan endpoint publik.

### 7. Surat suara tidak rahasia

File: epilketos_hosting.sql:69-77; vote_process.php:89-124.

votes menyimpan student_id dan candidate_id pada row yang sama, ditambah ip_address/voted_at. Admin/database auditor dapat langsung menghubungkan siswa, pilihan, waktu, dan IP. Ini gagal secret ballot/LUBER JURDIL.

Election berikutnya memerlukan tabel eligibility terpisah (student_id, consumed_at) dan anonymous ballot (random ballot ID, candidate_id, waktu minimum). Jangan simpan IP pada ballot. Data lama tidak dapat dianonimkan retroaktif.

## Temuan P2 — High

- Session: config/functions.php:7-13 hanya mengatur HttpOnly, use_only_cookies, dan path; Secure, SameSite, strict mode tidak dipaksa. verify_voter.php tidak regenerate session saat identitas voter diberikan.
- Admin auth: admin/login.php:18-48 tidak memverifikasi role sebelum admin_logged_in=true dan tidak memiliki throttling/lockout.
- Fail-open: config/functions.php:81-97 mengembalikan election_status OPEN jika settings row hilang. Harus DRAFT/fail closed.
- Migration/duplicate: database.php:67-86 menelan error migration; vote_process.php:91-111,133-147 memakai substring UNIQUE dan update has_voted di luar transaksi pada jalur duplicate.
- Stored XSS: face-poc/assets/js/face-poc-enroll.js:34-39,348-350 memakai innerHTML untuk data.message dari API; API menyisipkan nama siswa dari database. Gunakan textContent/escape.
- Anti-spoof: recognize.js:191-317 hanya mengonfirmasi dua frame, bukan liveness. Foto/layar/video replay dapat lolos.
- Reset race: admin reset/close tidak memakai global election lock; request yang dimulai saat OPEN dapat commit setelah close atau berinteraksi buruk dengan reset.

## Temuan P3 — Medium

- data/pilketos.sqlite dan config/init_db.php tidak memiliki face_descriptor/face_enrolled_at, walaupun API face mengaksesnya; mode SQLite lokal tidak kompatibel.
- parse_xlsx_file di config/functions.php:165-254 tidak memakai LIBXML_NONET/penolakan DOCTYPE dan tidak membatasi ZIP entries/uncompressed size. Uji lokal PHP 8.5 tidak menunjukkan classic XXE saat ini, tetapi XLSX bomb dapat menyebabkan resource exhaustion.
- Tidak ada CSP, HSTS, X-Content-Type-Options, X-Frame-Options, atau Permissions Policy; CDN Bootstrap tidak memakai SRI.
- e-pilketos/.env berisi credential DB plaintext; SQL/README memiliki known default credential yang tidak konsisten. Pindahkan secret ke luar webroot dan rotate.
- mime_content_type dapat fatal jika fileinfo disabled; runtime ALTER TABLE dapat mengunci tabel; ZipArchive/SimpleXML/open_basedir/memory limit/permissions berbeda pada shared hosting.
- Tidak ditemukan utf8_encode, mysql_*, create_function, atau dynamic properties deprecated. Source memerlukan PHP 8+.

## Concurrency answer

Dengan epilketos_hosting.sql, InnoDB, dan UNIQUE(student_id), dua POST concurrent tidak menghasilkan dua row vote: satu insert menang, yang lain duplicate-key lalu rollback. Insert dan update has_voted pemenang atomik.

Jaminan ini bergantung pada schema tepat dan tidak mengatasi impersonasi. Manual/verify publik tetap memungkinkan attacker memilih sebagai ID yang belum voted. Reset dan close election belum diserialisasi.

## Patch minimum

### Session bootstrap sebelum session_start

~~~php
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') === '443');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', $isHttps ? '1' : '0');
ini_set('session.cookie_samesite', 'Lax');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'secure' => $isHttps,
    'httponly' => true, 'samesite' => 'Lax',
]);
session_start();
~~~

### Manual gate

Tambahkan pada GET dan POST manual, bukan hanya query string:

~~~php
if (isset($_GET['manual']) && $_GET['manual'] === '1' && !is_admin()) {
    http_response_code(403);
    exit('Mode manual hanya untuk panitia yang sudah login.');
}
~~~

### Atomic vote claim

~~~php
$pdo->beginTransaction();
try {
    $claim = $pdo->prepare(
        'UPDATE students SET has_voted = 1 WHERE id = ? AND has_voted = 0'
    );
    $claim->execute([$studentId]);
    if ($claim->rowCount() !== 1) throw new RuntimeException('duplicate_vote');

    $insert = $pdo->prepare(
        'INSERT INTO votes (student_id, candidate_id, voted_at) VALUES (?, ?, ?)'
    );
    $insert->execute([$studentId, $candidateId, date('Y-m-d H:i:s')]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}
~~~

Status election/candidate harus divalidasi di dalam transaksi. Klasifikasi duplicate gunakan SQLSTATE 23000, bukan substring pesan. Untuk secret ballot, schema votes harus diganti seperti di atas, bukan sekadar menghapus IP.

### Safe XML

~~~php
function safe_xml(string $payload): ?SimpleXMLElement {
    if (strlen($payload) > 20 * 1024 * 1024) return null;
    if (preg_match('/<!DOCTYPE|<!ENTITY/i', $payload)) return null;
    $old = libxml_use_internal_errors(true);
    try {
        return simplexml_load_string(
            $payload, SimpleXMLElement::class,
            LIBXML_NONET | LIBXML_NOCDATA | LIBXML_NOERROR | LIBXML_NOWARNING
        ) ?: null;
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($old);
    }
}
~~~

Batasi juga ZIP entries dan total uncompressed bytes.

## Election-day checklist

1. Jangan OPEN sebelum P1 ditutup atau face/manual paths dimatikan.
2. Hapus reset_admin.php, unzip.php, test scripts, .env, SQL, dan SQLite dari document root.
3. Rotate admin/DB/session secrets dan semua cookies.
4. Paksa HTTPS + Secure/HttpOnly/SameSite cookie.
5. Cek SHOW CREATE TABLE votes: InnoDB dan UNIQUE(student_id).
6. Backup offline/read-only dan uji restore.
7. Disable reset selama OPEN; koreksi hanya saat CLOSED dengan berita acara.
8. Jangan expose roster/descriptor; tutup public results sampai disetujui.
9. Gunakan operator/PIN/QR sebagai second factor; face-api.js bukan authenticator.
10. Monitor error log, login failure, DB lock, disk, memory, dan latency.
11. Clear browser state dan stop camera stream setelah setiap voter.
12. Uji dua POST concurrent pada schema production: tepat satu ballot.

## Kesimpulan

Prepared statements, escaping mayoritas template PHP, password hashing, dan unique constraint adalah fondasi yang baik. Namun kontrol akses identitas dan backdoor deployment membuat sistem belum layak dibuka. Prioritas pertama adalah menutup bypass identitas, menghapus reset/deploy backdoors, dan mendesain ulang ballot agar anonim.
