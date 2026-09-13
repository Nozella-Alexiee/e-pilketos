<?php
/**
 * Face-POC API: Ambil daftar siswa untuk dropdown enrollment
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
require_admin_api();

try {
    $pdo = getDb();
    
    $voterType = $_GET['voter_type'] ?? 'student';

    if ($voterType === 'employee') {
        $where = [];
        $params = [];
        if (!$all) {
            $where[] = "(face_descriptor IS NULL OR face_descriptor = '')";
        }
        $empType = $_GET['type'] ?? '';
        if (!empty($empType) && in_array($empType, ['guru', 'karyawan'], true)) {
            $where[] = "type = :type";
            $params[':type'] = $empType;
        }
        $sql = "SELECT id, nip, name, type,
                       (face_descriptor IS NOT NULL AND face_descriptor != '') as is_enrolled,
                       face_enrolled_at, has_voted
                FROM employees";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY type ASC, name ASC";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'voter_type' => 'employee',
            'classes' => [],
            'students' => $employees,
            'employees' => $employees
        ]);
        exit;
    }

    $classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
    $all = isset($_GET['all']) && $_GET['all'] == '1';
    
    $where = [];
    $params = [];

    // Otomatis hilangkan siswa yang sudah terdaftar biometrik wajahnya
    if (!$all) {
        $where[] = "(s.face_descriptor IS NULL OR s.face_descriptor = '')";
    }

    if ($classId > 0) {
        $where[] = "s.class_id = :class_id";
        $params[':class_id'] = $classId;
    }
    
    $sql = "SELECT s.id, s.name, s.attendance_number, s.class_id, c.name as class_name,
                   (s.face_descriptor IS NOT NULL AND s.face_descriptor != '') as is_enrolled,
                   s.face_enrolled_at, s.has_voted
            FROM students s
            JOIN classes c ON s.class_id = c.id";
            
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    
    $sql .= " ORDER BY c.grade ASC, c.name ASC, s.attendance_number ASC, s.name ASC";
    
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val, PDO::PARAM_INT);
    }
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Classes list
    $classes = $pdo->query("SELECT id, name, grade, major FROM classes WHERE is_active = 1 ORDER BY grade ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'classes' => $classes,
        'students' => $students
    ]);
} catch (Exception $e) {
    error_log('Enrollment roster read failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal mengambil data siswa.'
    ]);
}
