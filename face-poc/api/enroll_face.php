<?php
/**
 * Face-POC API: Simpan 128-d Face Descriptor ke database siswa
 * E-Pilketos SMK SIG
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Hanya menerima request POST']);
    exit;
}

require_admin_api();
require_csrf_request();

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Data JSON tidak valid']);
    exit;
}

$voterType = isset($data['voter_type']) ? $data['voter_type'] : 'student';
$voterId = isset($data['voter_id']) ? (int)$data['voter_id'] : (int)($data['student_id'] ?? 0);
$descriptor = isset($data['descriptor']) ? $data['descriptor'] : null;

if ($voterId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID pemilih wajib dipilih']);
    exit;
}

// Validasi biometrik: Wajib array float berukuran tepat 128 elemen
if (!is_array($descriptor) || count($descriptor) !== 128) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Format face descriptor tidak valid (harus berupa array 128 float)']);
    exit;
}

// Pastikan semua elemen berupa angka float
foreach ($descriptor as $val) {
    if (!is_numeric($val)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Elemen face descriptor mengandung nilai non-numerik']);
        exit;
    }
}

try {
    $pdo = getDb();
    $descriptorJson = json_encode(array_map('floatval', $descriptor));
    $now = date('Y-m-d H:i:s');

    if ($voterType === 'employee') {
        $stmt = $pdo->prepare("SELECT id, nip, name, type FROM employees WHERE id = ?");
        $stmt->execute([$voterId]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$employee) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Data Guru/Karyawan tidak ditemukan']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE employees SET face_descriptor = ?, face_enrolled_at = ? WHERE id = ?");
        $stmt->execute([$descriptorJson, $now, $voterId]);

        echo json_encode([
            'success' => true,
            'voter_type' => 'employee',
            'message' => 'Wajah berhasil didaftarkan untuk ' . ucfirst($employee['type']) . ' ' . $employee['name'] . ' (NIP: ' . $employee['nip'] . ')',
            'voter' => [
                'id' => $employee['id'],
                'name' => $employee['name'],
                'nip' => $employee['nip'],
                'type' => $employee['type']
            ]
        ]);
        exit;
    }
    
    // Cek siswa ada di DPT
    $stmt = $pdo->prepare("SELECT s.id, s.name, c.name as class_name FROM students s JOIN classes c ON s.class_id = c.id WHERE s.id = ?");
    $stmt->execute([$voterId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Data siswa tidak ditemukan']);
        exit;
    }
    
    $stmt = $pdo->prepare("UPDATE students SET face_descriptor = ?, face_enrolled_at = ? WHERE id = ?");
    $stmt->execute([$descriptorJson, $now, $voterId]);
    
    echo json_encode([
        'success' => true,
        'voter_type' => 'student',
        'message' => 'Wajah berhasil didaftarkan untuk ' . $student['name'] . ' (' . $student['class_name'] . ')',
        'student' => [
            'id' => $student['id'],
            'name' => $student['name'],
            'class_name' => $student['class_name'],
            'enrolled_at' => $now
        ],
        'voter' => [
            'id' => $student['id'],
            'name' => $student['name'],
            'class_name' => $student['class_name'],
            'type' => 'student'
        ]
    ]);
} catch (Exception $e) {
    error_log('Face enrollment failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan biometrik. Panitia dapat memeriksa log server.']);
}
