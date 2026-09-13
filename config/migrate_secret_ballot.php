<?php
/** One-time CLI migration for anonymous ballots. Never exposed as a web route. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/secret_ballot.php';

try {
    $pdo = getDb();
    migrate_secret_ballot($pdo);
    if (!secret_ballot_schema_ready($pdo)) {
        throw new RuntimeException('Validasi skema anonymous ballot gagal.');
    }
    fwrite(STDOUT, "Secret-ballot migration completed successfully.\n");
} catch (Throwable $e) {
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}
