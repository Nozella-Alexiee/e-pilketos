<?php
/**
 * E-Pilketos v2.0 - anonymous ballot submission.
 * Eligibility is claimed atomically on students; votes never store voter data.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/secret_ballot.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('danger', 'Sesi pemilihan Anda telah kedaluwarsa. Silakan coba kembali.');
    header('Location: vote.php');
    exit;
}

$voterType = $_POST['voter_type'] ?? ($_SESSION['voter_type'] ?? 'student');
$voterId = (int)($_POST['voter_id'] ?? ($_POST['student_id'] ?? 0));
$candidateId = (int)($_POST['candidate_id'] ?? 0);

$sessionVoterType = $_SESSION['voter_type'] ?? 'student';
$sessionVoterId = (int)($_SESSION['voter_id'] ?? ($_SESSION['voter_student_id'] ?? 0));

if ($voterId <= 0 || $candidateId <= 0 || $sessionVoterId !== $voterId || $sessionVoterType !== $voterType) {
    set_flash('danger', 'Sesi pemilih atau pilihan kandidat tidak valid.');
    header('Location: face-poc/index.php');
    exit;
}

$pdo = getDb();
if (!secret_ballot_schema_ready($pdo)) {
    error_log('Vote rejected: anonymous ballot migration has not been completed.');
    set_flash('danger', 'Sistem kotak suara belum siap. Panitia harus menjalankan migrasi secret ballot sebelum pemilihan dibuka.');
    header('Location: index.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $settingsStmt = $pdo->query('SELECT election_status, election_period FROM settings WHERE id = 1 LIMIT 1');
    $settings = $settingsStmt->fetch();
    if (!$settings || ($settings['election_status'] ?? 'DRAFT') !== 'OPEN') {
        throw new RuntimeException('ELECTION_CLOSED');
    }

    $candidateStmt = $pdo->prepare('SELECT id FROM candidates WHERE id = ? AND is_active = 1');
    $candidateStmt->execute([$candidateId]);
    if (!$candidateStmt->fetch()) {
        throw new RuntimeException('INVALID_CANDIDATE');
    }

    $now = date('Y-m-d H:i:s');

    if ($voterType === 'employee') {
        $empStmt = $pdo->prepare('SELECT id, nip, name, type, has_voted FROM employees WHERE id = ?');
        $empStmt->execute([$voterId]);
        $employee = $empStmt->fetch();
        if (!$employee) {
            throw new RuntimeException('INVALID_VOTER');
        }

        // Atomic Claim Pattern: only one concurrent request can change 0 to 1.
        $claim = $pdo->prepare('UPDATE employees SET has_voted = 1, voted_at = ? WHERE id = ? AND has_voted = 0');
        $claim->execute([$now, $voterId]);
        if ($claim->rowCount() !== 1) {
            throw new RuntimeException('DUPLICATE_VOTE');
        }

        $receiptData = [
            'voter_type' => 'employee',
            'name' => $employee['name'],
            'student_name' => $employee['name'],
            'nip' => $employee['nip'],
            'type' => $employee['type'],
            'participated_at' => $now,
            'election_period' => $settings['election_period'],
        ];
    } else {
        $studentStmt = $pdo->prepare(
            'SELECT s.id, s.name, s.attendance_number, s.has_voted, c.name AS class_name, c.is_active AS class_active
             FROM students s JOIN classes c ON c.id = s.class_id WHERE s.id = ?'
        );
        $studentStmt->execute([$voterId]);
        $student = $studentStmt->fetch();
        if (!$student || (int)$student['class_active'] !== 1) {
            throw new RuntimeException('INVALID_VOTER');
        }

        // Atomic Claim Pattern: only one concurrent request can change 0 to 1.
        $claim = $pdo->prepare('UPDATE students SET has_voted = 1, voted_at = ? WHERE id = ? AND has_voted = 0');
        $claim->execute([$now, $voterId]);
        if ($claim->rowCount() !== 1) {
            throw new RuntimeException('DUPLICATE_VOTE');
        }

        $receiptData = [
            'voter_type' => 'student',
            'name' => $student['name'],
            'student_name' => $student['name'],
            'class_name' => $student['class_name'],
            'attendance_number' => $student['attendance_number'],
            'participated_at' => $now,
            'election_period' => $settings['election_period'],
        ];
    }

    // Intentionally anonymous: no student ID, employee ID, IP address, or voter session data.
    $insert = $pdo->prepare('INSERT INTO votes (candidate_id, created_at) VALUES (?, ?)');
    $insert->execute([$candidateId, $now]);

    $pdo->commit();

    // A receipt proves participation only; it never repeats the selected candidate.
    $_SESSION['vote_receipt'] = $receiptData;
    unset(
        $_SESSION['voter_student_id'],
        $_SESSION['voter_id'],
        $_SESSION['voter_type'],
        $_SESSION['voter_verified_at'],
        $_SESSION['csrf_token']
    );
    header('Location: vote_success.php');
    exit;
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $messages = [
        'ELECTION_CLOSED' => 'Pemilihan sudah ditutup atau belum dibuka.',
        'INVALID_VOTER' => 'Data pemilih tidak valid atau kelas tidak aktif.',
        'INVALID_CANDIDATE' => 'Kandidat yang dipilih tidak valid atau tidak aktif.',
        'DUPLICATE_VOTE' => 'Hak pilih sudah digunakan. Sistem menolak suara kedua.',
    ];
    set_flash('danger', $messages[$e->getMessage()] ?? 'Permintaan pemilihan tidak dapat diproses.');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Anonymous ballot transaction failed: ' . $e->getMessage());
    set_flash('danger', 'Terjadi kesalahan saat menyimpan suara. Panitia dapat memeriksa log server.');
}

header('Location: index.php');
exit;
