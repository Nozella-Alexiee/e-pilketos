<?php
/**
 * E-Pilketos v2.0 - Core Helper & Security Functions
 * SMK SIG (Sekolah Menengah Kejuruan SIG)
 */

if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** Send headers that are safe for the application's existing inline assets. */
function send_security_headers(): void {
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net 'wasm-unsafe-eval'; connect-src 'self' blob:; media-src 'self' blob:;");
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

send_security_headers();

/**
 * Escapes HTML output for XSS protection
 */
function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Generate or get CSRF token
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf_token(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check if admin is currently logged in
 */
function is_admin(): bool {
    if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        return false;
    }
    $lastActivity = (int)($_SESSION['admin_last_activity'] ?? 0);
    if ($lastActivity === 0 || (time() - $lastActivity) > 1800) {
        unset($_SESSION['admin_logged_in'], $_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_name'], $_SESSION['admin_role'], $_SESSION['admin_last_activity']);
        return false;
    }
    $_SESSION['admin_last_activity'] = time();
    return ($_SESSION['admin_role'] ?? '') === 'admin';
}

/**
 * Protect admin routes
 */
function require_admin(string $loginPath = 'login.php'): void {
    if (!is_admin()) {
        header('Location: ' . $loginPath);
        exit;
    }
}

/** JSON endpoints use this instead of redirects intended for HTML pages. */
function require_admin_api(): void {
    if (!is_admin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Akses panitia tidak sah atau sesi telah berakhir.']);
        exit;
    }
}

function request_csrf_token(): ?string {
    return $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;
}

function require_csrf_request(): void {
    if (!verify_csrf_token(request_csrf_token())) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token keamanan tidak valid atau telah kedaluwarsa.']);
        exit;
    }
}

function kiosk_is_authorized(): bool {
    return is_admin()
        && !empty($_SESSION['kiosk_authorized'])
        && (int)($_SESSION['kiosk_authorized_until'] ?? 0) >= time();
}

function require_kiosk_authorization_api(): void {
    if (!kiosk_is_authorized()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Bilik belum diotorisasi panitia atau sesi bilik telah berakhir.']);
        exit;
    }
}

function manual_booth_is_authorized(): bool {
    return is_admin() && (int)($_SESSION['manual_booth_authorized_until'] ?? 0) >= time();
}

/**
 * Flash message helper
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash_message'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function get_flash(): ?array {
    if (isset($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $msg;
    }
    return null;
}

/**
 * Get current election settings from database
 */
function get_election_settings(PDO $pdo): array {
    $defaults = [
        'id' => 1,
        'election_name' => 'Pemilihan Ketua OSIS',
        'election_period' => '2026/2027',
        'election_status' => 'DRAFT',
        'show_results_to_students' => 1,
        'headmaster_name' => 'Choirul Ichsan, S.Psi.',
        'headmaster_nip' => '-',
        'counselor_name' => 'Hajar Alia Rachmi, S.Pd.',
        'counselor_nip' => '-',
        'committee_name' => 'Panitia Pemilihan OSIS SMK SIG',
        'committee_title' => 'Ketua Panitia Pilketos',
        'letter_number' => 'BA.' . date('Y') . '/PILKETOS/SMK-SIG/' . date('m'),
        'letter_datetime' => '',
        'letter_sign_date' => '',
        'letter_city' => 'Gresik'
    ];

    try {
        $stmt = $pdo->query("SELECT * FROM settings WHERE id = 1 LIMIT 1");
        $settings = $stmt ? $stmt->fetch() : false;
        if (!$settings) {
            return $defaults;
        }
    } catch (Throwable $e) {
        error_log('get_election_settings fallback: ' . $e->getMessage());
        return $defaults;
    }

    // Pastikan key ada jika database lama
    $settings['headmaster_name'] = $settings['headmaster_name'] ?? 'Choirul Ichsan, S.Psi.';
    $settings['headmaster_nip'] = $settings['headmaster_nip'] ?? '-';
    $settings['counselor_name'] = $settings['counselor_name'] ?? 'Hajar Alia Rachmi, S.Pd.';
    $settings['counselor_nip'] = $settings['counselor_nip'] ?? '-';
    $settings['committee_name'] = $settings['committee_name'] ?? 'Panitia Pemilihan OSIS SMK SIG';
    $settings['committee_title'] = $settings['committee_title'] ?? 'Ketua Panitia Pilketos';
    $settings['letter_number'] = !empty($settings['letter_number']) ? $settings['letter_number'] : ('BA.' . date('Y') . '/PILKETOS/SMK-SIG/' . date('m'));
    $settings['letter_datetime'] = $settings['letter_datetime'] ?? '';
    $settings['letter_sign_date'] = $settings['letter_sign_date'] ?? '';
    $settings['letter_city'] = !empty($settings['letter_city']) ? $settings['letter_city'] : 'Gresik';
    return $settings;
}

/**
 * Format Indonesian date time with Day name
 */
function format_date_id(?string $datetime, bool $withDay = true): string {
    if (!$datetime) return '-';
    $time = strtotime($datetime);
    $hari = [
        'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
    ];
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $dayName = $hari[date('l', $time)] ?? '';
    $day = date('d', $time);
    $month = $bulan[(int)date('m', $time)];
    $year = date('Y', $time);
    $hour = date('H.i', $time);
    if ($withDay && $dayName) {
        return "$dayName, $day $month $year pukul $hour WIB";
    }
    return "$day $month $year, $hour WIB";
}

/**
 * Format Indonesian date only (e.g. 14 September 2026)
 */
function format_date_only_id(?string $date): string {
    if (!$date) return '-';
    $time = strtotime($date);
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $day = date('d', $time);
    $month = $bulan[(int)date('m', $time)];
    $year = date('Y', $time);
    return "$day $month $year";
}

/**
 * Sanitize filename for uploads
 */
function sanitize_filename(string $filename): string {
    $filename = preg_replace('/[^a-zA-Z0-9\._-]/', '_', $filename);
    return preg_replace('/_+/', '_', $filename);
}

/**
 * Parse an XLSX XML part without network/entity expansion.  XLSX is a ZIP of
 * XML documents, therefore blocking DTD/entity declarations is mandatory.
 */
function safe_xml(string $payload): ?SimpleXMLElement {
    if ($payload === '' || strlen($payload) > 10 * 1024 * 1024) {
        return null;
    }
    if (preg_match('/<!DOCTYPE|<!ENTITY/i', $payload)) {
        return null;
    }
    $previous = libxml_use_internal_errors(true);
    try {
        $xml = simplexml_load_string($payload, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA | LIBXML_NOERROR | LIBXML_NOWARNING);
        return $xml instanceof SimpleXMLElement ? $xml : null;
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

/**
 * Parse rows from an Excel (.xlsx) file using native ZipArchive and SimpleXML
 */
function parse_xlsx_file(string $filePath): ?array {
    if (!class_exists('ZipArchive') || !file_exists($filePath)) {
        return null;
    }

    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        return null;
    }

    // Read shared strings if present
    $sharedStrings = [];
    $sstXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sstXml) {
        $xml = safe_xml($sstXml);
        if ($xml && isset($xml->si)) {
            foreach ($xml->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string)$si->t;
                } elseif (isset($si->r)) {
                    $text = '';
                    foreach ($si->r as $r) {
                        $text .= (string)$r->t;
                    }
                    $sharedStrings[] = $text;
                } else {
                    $sharedStrings[] = '';
                }
            }
        }
    }

    // Read sheet1.xml
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if (!$sheetXml) {
        // Coba cari nama sheet pertama bila bukan sheet1.xml
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('/^xl\/worksheets\/sheet.*\.xml$/i', $name)) {
                $sheetXml = $zip->getFromIndex($i);
                break;
            }
        }
    }

    if (!$sheetXml) {
        $zip->close();
        return null;
    }

    $xml = safe_xml($sheetXml);
    $rows = [];
    if ($xml && isset($xml->sheetData->row)) {
        foreach ($xml->sheetData->row as $row) {
            $rowData = [];
            foreach ($row->c as $cell) {
                $cellRef = (string)$cell['r'];
                preg_match('/^([A-Z]+)(\d+)$/', $cellRef, $matches);
                $colLetters = $matches[1] ?? 'A';
                $colIndex = 0;
                for ($k = 0; $k < strlen($colLetters); $k++) {
                    $colIndex = $colIndex * 26 + (ord($colLetters[$k]) - ord('A') + 1);
                }
                $colIndex--;

                $type = (string)$cell['t'];
                $val = (string)$cell->v;

                if ($type === 's') {
                    $strIndex = (int)$val;
                    $cellValue = $sharedStrings[$strIndex] ?? '';
                } elseif ($type === 'inlineStr') {
                    $cellValue = (string)($cell->is->t ?? '');
                } else {
                    $cellValue = $val;
                }
                $rowData[$colIndex] = trim($cellValue);
            }
            if (!empty($rowData)) {
                $maxCol = max(array_keys($rowData));
                $norm = [];
                for ($c = 0; $c <= $maxCol; $c++) {
                    $norm[$c] = $rowData[$c] ?? '';
                }
                $rows[] = $norm;
            }
        }
    }
    $zip->close();
    return $rows;
}

/**
 * Generate sample XLSX template for student import
 */
function generate_students_template_xlsx(): ?string {
    if (!class_exists('ZipArchive')) return null;
    $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return null;

    $zip->addFromString("[Content_Types].xml", '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
</Types>');

    $zip->addFromString("_rels/.rels", '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');

    $zip->addFromString("xl/_rels/workbook.xml.rels", '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>');

    $zip->addFromString("xl/workbook.xml", '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets><sheet name="Data Siswa" sheetId="1" r:id="rId1"/></sheets>
</workbook>');

    $sampleData = [
        ["No. Absen", "Nama Siswa", "Kelas"],
        // Kelompok Siswa Kelas XII RPL 1
        ["1", "Aditya Pratama", "XII RPL 1"],
        ["2", "Bima Satria", "XII RPL 1"],
        ["3", "Citra Dewi Anggraini", "XII RPL 1"],
        ["4", "Daffa Maulana", "XII RPL 1"],
        ["5", "Eka Saputra", "XII RPL 1"],
        // Kelompok Siswa Kelas XII RPL 2
        ["1", "Fajar Nugraha", "XII RPL 2"],
        ["2", "Gita Maharani", "XII RPL 2"],
        ["3", "Haryo Wicaksono", "XII RPL 2"],
        // Kelompok Siswa Kelas XI RPL 1
        ["1", "Indra Gunawan", "XI RPL 1"],
        ["2", "Jihan Farida", "XI RPL 1"],
        ["3", "Kevin Sanjaya", "XI RPL 1"],
    ];

    $strings = [];
    $stringIndex = [];
    foreach ($sampleData as $row) {
        foreach ($row as $val) {
            $val = (string)$val;
            if (!isset($stringIndex[$val])) {
                $stringIndex[$val] = count($strings);
                $strings[] = $val;
            }
        }
    }

    $sst = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n";
    $sst .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($strings).'" uniqueCount="'.count($strings).'">';
    foreach ($strings as $s) {
        $sst .= '<si><t>' . htmlspecialchars($s, ENT_XML1, 'UTF-8') . '</t></si>';
    }
    $sst .= '</sst>';
    $zip->addFromString("xl/sharedStrings.xml", $sst);

    $sheet = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n";
    $sheet .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
    foreach ($sampleData as $rIdx => $row) {
        $rNum = $rIdx + 1;
        $sheet .= '<row r="' . $rNum . '">';
        foreach ($row as $cIdx => $val) {
            $colLetter = chr(65 + $cIdx);
            $cellRef = $colLetter . $rNum;
            $sId = $stringIndex[(string)$val];
            $sheet .= '<c r="' . $cellRef . '" t="s"><v>' . $sId . '</v></c>';
        }
        $sheet .= '</row>';
    }
    $sheet .= '</sheetData></worksheet>';
    $zip->addFromString("xl/worksheets/sheet1.xml", $sheet);
    $zip->close();

    return $tmp;
}

/**
 * Generate sample XLSX template for employee import (Guru & Karyawan)
 */
function generate_employees_template_xlsx(): ?string {
    if (!class_exists('ZipArchive')) return null;
    $tmp = tempnam(sys_get_temp_dir(), 'xlsx_emp_');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return null;

    $zip->addFromString("[Content_Types].xml", '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
</Types>');

    $zip->addFromString("_rels/.rels", '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');

    $zip->addFromString("xl/_rels/workbook.xml.rels", '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>');

    $zip->addFromString("xl/workbook.xml", '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets><sheet name="Data Guru & Karyawan" sheetId="1" r:id="rId1"/></sheets>
</workbook>');

    $sampleData = [
        ["NIP", "Nama", "Tipe"],
        ["197508122005011004", "Choirul Ichsan, S.Psi.", "guru"],
        ["198804212011012015", "Hajar Alia Rachmi, S.Pd.", "guru"],
        ["198501012010011001", "Budi Santoso, S.Pd.", "guru"],
        ["199003152018021002", "Siti Aminah", "karyawan"],
        ["199507202022031003", "Ahmad Fauzi", "karyawan"],
    ];

    $strings = [];
    $stringIndex = [];
    foreach ($sampleData as $row) {
        foreach ($row as $val) {
            $val = (string)$val;
            if (!isset($stringIndex[$val])) {
                $stringIndex[$val] = count($strings);
                $strings[] = $val;
            }
        }
    }

    $sst = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n";
    $sst .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($strings).'" uniqueCount="'.count($strings).'">';
    foreach ($strings as $s) {
        $sst .= '<si><t>' . htmlspecialchars($s, ENT_XML1, 'UTF-8') . '</t></si>';
    }
    $sst .= '</sst>';
    $zip->addFromString("xl/sharedStrings.xml", $sst);

    $sheet = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n";
    $sheet .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
    foreach ($sampleData as $rIdx => $row) {
        $rNum = $rIdx + 1;
        $sheet .= '<row r="' . $rNum . '">';
        foreach ($row as $cIdx => $val) {
            $colLetter = chr(65 + $cIdx);
            $cellRef = $colLetter . $rNum;
            $sId = $stringIndex[(string)$val];
            $sheet .= '<c r="' . $cellRef . '" t="s"><v>' . $sId . '</v></c>';
        }
        $sheet .= '</row>';
    }
    $sheet .= '</sheetData></worksheet>';
    $zip->addFromString("xl/worksheets/sheet1.xml", $sheet);
    $zip->close();

    return $tmp;
}

