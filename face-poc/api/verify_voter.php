<?php
/** Server-side eligibility check after a committee-operated kiosk match. */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode request tidak diizinkan']);
    exit;
}

$data = json_decode((string)file_get_contents('php://input'), true);
$voterType = is_array($data) ? trim((string)($data['voter_type'] ?? 'student')) : 'student';
$voterId = is_array($data) ? (int)($data['voter_id'] ?? ($data['student_id'] ?? 0)) : 0;
if ($voterId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID pemilih tidak valid']);
    exit;
}

try {
    $pdo = getDb();
    $settings = get_election_settings($pdo);
    if (($settings['election_status'] ?? 'DRAFT') !== 'OPEN') {
        echo json_encode(['success' => false, 'eligible' => false, 'message' => 'Pemilihan belum dibuka atau sudah ditutup.']);
        exit;
    }

    if ($voterType === 'employee') {
        $stmt = $pdo->prepare(
            "SELECT id, nip, name, type, has_voted, face_descriptor
             FROM employees WHERE id = ?"
        );
        $stmt->execute([$voterId]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$employee || empty($employee['face_descriptor'])) {
            echo json_encode(['success' => false, 'eligible' => false, 'message' => 'Guru/Karyawan tidak terdaftar atau belum memiliki data biometrik.']);
            exit;
        }
        if ((int)$employee['has_voted'] === 1) {
            echo json_encode(['success' => false, 'eligible' => false, 'message' => 'Hak pilih Guru/Karyawan ini sudah digunakan.']);
            exit;
        }

        session_regenerate_id(true);
        $_SESSION['voter_type'] = 'employee';
        $_SESSION['voter_id'] = (int)$employee['id'];
        $_SESSION['voter_student_id'] = (int)$employee['id']; // fallback
        $_SESSION['voter_verified_at'] = time();

        echo json_encode([
            'success' => true,
            'eligible' => true,
            'voter_type' => 'employee',
            'message' => 'Identifikasi berhasil. Mengalihkan ke surat suara.',
            'redirect_url' => '../vote.php',
        ]);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT s.id, s.name, s.attendance_number, s.has_voted, s.face_descriptor,
                c.name AS class_name, c.is_active AS class_active
         FROM students s JOIN classes c ON c.id = s.class_id WHERE s.id = ?"
    );
    $stmt->execute([$voterId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student || empty($student['face_descriptor']) || (int)$student['class_active'] !== 1) {
        echo json_encode(['success' => false, 'eligible' => false, 'message' => 'Pemilih tidak terdaftar atau tidak aktif.']);
        exit;
    }
    if ((int)$student['has_voted'] === 1) {
        echo json_encode(['success' => false, 'eligible' => false, 'message' => 'Hak pilih siswa ini sudah digunakan.']);
        exit;
    }

    // Prevent session fixation at the voter-authentication boundary.
    session_regenerate_id(true);
    $_SESSION['voter_type'] = 'student';
    $_SESSION['voter_id'] = (int)$student['id'];
    $_SESSION['voter_student_id'] = (int)$student['id'];
    $_SESSION['voter_verified_at'] = time();

    echo json_encode([
        'success' => true,
        'eligible' => true,
        'voter_type' => 'student',
        'message' => 'Identifikasi berhasil. Mengalihkan ke surat suara.',
        'redirect_url' => '../vote.php',
    ]);
} catch (Throwable $e) {
    error_log('Voter verification failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'eligible' => false, 'message' => 'Verifikasi tidak dapat diproses.']);
}
