<?php
/**
 * Face-POC API: Ambil data biometrik wajah siswa terdaftar untuk matching client-side
 * E-Pilketos SMK SIG
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Hanya menerima request GET']);
    exit;
}

try {
    $pdo = getDb();
    
    // 1. Siswa terdaftar
    $stmtStudents = $pdo->query("
        SELECT s.id, s.name, c.name as class_name, s.attendance_number, s.has_voted, s.face_descriptor
        FROM students s
        JOIN classes c ON s.class_id = c.id
        WHERE s.face_descriptor IS NOT NULL AND s.face_descriptor != '' AND c.is_active = 1
        ORDER BY s.id ASC
    ");
    $rawStudents = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);

    // 2. Guru & Karyawan terdaftar
    $stmtEmployees = $pdo->query("
        SELECT id, nip, name, type, has_voted, face_descriptor
        FROM employees
        WHERE face_descriptor IS NOT NULL AND face_descriptor != ''
        ORDER BY id ASC
    ");
    $rawEmployees = $stmtEmployees->fetchAll(PDO::FETCH_ASSOC);
    
    $faces = [];
    foreach ($rawStudents as $row) {
        $desc = json_decode($row['face_descriptor'], true);
        if (is_array($desc) && count($desc) === 128) {
            $faces[] = [
                'id' => 'student:' . (int)$row['id'],
                'raw_id' => (int)$row['id'],
                'type' => 'student',
                'name' => $row['name'],
                'class_name' => $row['class_name'],
                'attendance_number' => (int)$row['attendance_number'],
                'nip' => '',
                'has_voted' => (int)$row['has_voted'],
                'descriptor' => $desc
            ];
        }
    }

    foreach ($rawEmployees as $row) {
        $desc = json_decode($row['face_descriptor'], true);
        if (is_array($desc) && count($desc) === 128) {
            $faces[] = [
                'id' => 'employee:' . (int)$row['id'],
                'raw_id' => (int)$row['id'],
                'type' => 'employee',
                'employee_type' => $row['type'],
                'name' => $row['name'],
                'class_name' => ($row['type'] === 'karyawan' ? 'Tenaga Kependidikan' : 'Tenaga Pendidik'),
                'attendance_number' => 0,
                'nip' => $row['nip'],
                'has_voted' => (int)$row['has_voted'],
                'descriptor' => $desc
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'count' => count($faces),
        'faces' => $faces
    ]);
} catch (Exception $e) {
    error_log('Face descriptor read failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal mengambil data biometrik.'
    ]);
}
