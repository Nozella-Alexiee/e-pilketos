<?php
/**
 * Secret-ballot schema guard and one-time migration.
 *
 * The migration never drops a table: a legacy linked `votes` table is renamed
 * to `votes_legacy_identity_link`, copied into a new anonymous `votes` table,
 * and left intact for an offline, access-restricted audit archive.
 */

function secret_ballot_columns(PDO $pdo, string $table): array {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $rows = $pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
        return array_map(static fn(array $row): string => (string)$row['name'], $rows);
    }

    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$table]);
    return array_map(static fn(array $row): string => (string)$row['COLUMN_NAME'], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function secret_ballot_table_exists(PDO $pdo, string $table): bool {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    }

    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function secret_ballot_schema_ready(PDO $pdo): bool {
    if (!secret_ballot_table_exists($pdo, 'votes')) {
        return false;
    }
    $columns = secret_ballot_columns($pdo, 'votes');
    return in_array('candidate_id', $columns, true)
        && in_array('created_at', $columns, true)
        && !in_array('student_id', $columns, true)
        && !in_array('ip_address', $columns, true);
}

function require_secret_ballot_schema(PDO $pdo): void {
    if (!secret_ballot_schema_ready($pdo)) {
        throw new RuntimeException(
            'Skema kotak suara anonim belum dimigrasikan. Jalankan php config/migrate_secret_ballot.php saat pemilihan masih DRAFT.'
        );
    }
}

function create_anonymous_votes_table(PDO $pdo): void {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS votes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                candidate_id INTEGER NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE RESTRICT
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_votes_candidate_id ON votes(candidate_id)');
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS votes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            candidate_id INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_votes_candidate_id (candidate_id),
            CONSTRAINT fk_anonymous_vote_candidate
                FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function add_student_voted_at(PDO $pdo): void {
    $columns = secret_ballot_columns($pdo, 'students');
    if (in_array('voted_at', $columns, true)) {
        return;
    }

    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $pdo->exec('ALTER TABLE students ADD COLUMN voted_at DATETIME NULL');
        return;
    }

    $pdo->exec('ALTER TABLE students ADD COLUMN voted_at DATETIME NULL AFTER has_voted');
}

/**
 * One-time migration. Run through config/migrate_secret_ballot.php while the
 * election is DRAFT/CLOSED, with a verified backup in hand.
 */
function migrate_secret_ballot(PDO $pdo): void {
    add_student_voted_at($pdo);

    if (!secret_ballot_table_exists($pdo, 'votes')) {
        create_anonymous_votes_table($pdo);
        return;
    }

    $columns = secret_ballot_columns($pdo, 'votes');
    if (!in_array('student_id', $columns, true)) {
        create_anonymous_votes_table($pdo);
        return;
    }

    if (secret_ballot_table_exists($pdo, 'votes_legacy_identity_link')) {
        throw new RuntimeException(
            'Migrasi dihentikan: votes_legacy_identity_link sudah ada. Periksa backup dan jangan jalankan ulang secara buta.'
        );
    }

    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $legacyTimeColumn = in_array('voted_at', $columns, true) ? 'voted_at' : null;

    if ($driver === 'sqlite') {
        $pdo->beginTransaction();
        try {
            $pdo->exec('ALTER TABLE votes RENAME TO votes_legacy_identity_link');
            create_anonymous_votes_table($pdo);
            $createdAt = $legacyTimeColumn ? 'COALESCE(voted_at, CURRENT_TIMESTAMP)' : 'CURRENT_TIMESTAMP';
            $pdo->exec(
                "INSERT INTO votes (candidate_id, created_at)\n                 SELECT candidate_id, $createdAt FROM votes_legacy_identity_link"
            );
            $studentTime = $legacyTimeColumn ? 'COALESCE(l.voted_at, CURRENT_TIMESTAMP)' : 'CURRENT_TIMESTAMP';
            $pdo->exec(
                "UPDATE students\n                 SET has_voted = 1,\n                     voted_at = COALESCE(voted_at, (SELECT $studentTime FROM votes_legacy_identity_link l WHERE l.student_id = students.id))\n                 WHERE id IN (SELECT student_id FROM votes_legacy_identity_link)"
            );
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return;
    }

    // MySQL/MariaDB DDL commits implicitly. Each statement is deliberately
    // idempotence-protected above; do not run while election is OPEN.
    $pdo->exec('RENAME TABLE votes TO votes_legacy_identity_link');
    create_anonymous_votes_table($pdo);
    $createdAt = $legacyTimeColumn ? 'COALESCE(voted_at, CURRENT_TIMESTAMP)' : 'CURRENT_TIMESTAMP';
    $pdo->exec(
        "INSERT INTO votes (candidate_id, created_at)\n         SELECT candidate_id, $createdAt FROM votes_legacy_identity_link"
    );
    $studentTime = $legacyTimeColumn ? 'COALESCE(l.voted_at, CURRENT_TIMESTAMP)' : 'CURRENT_TIMESTAMP';
    $pdo->exec(
        "UPDATE students s\n         JOIN votes_legacy_identity_link l ON l.student_id = s.id\n         SET s.has_voted = 1, s.voted_at = COALESCE(s.voted_at, $studentTime)"
    );
}
