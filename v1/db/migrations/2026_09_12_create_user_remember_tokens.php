<?php
/**
 * Migration: user_remember_tokens
 *
 * Backs the "remember me" login in v1/lib/auth_remember.php. The library also
 * creates this table on first use, so running this is optional; it exists so
 * the schema is recorded alongside the others.
 *
 * The legacy users.remember_token / remember_token_expires_at columns are no
 * longer written or read. They are left in place rather than dropped.
 *
 * Usage (from the project root):
 *   php v1/db/migrations/2026_09_12_create_user_remember_tokens.php up
 *   php v1/db/migrations/2026_09_12_create_user_remember_tokens.php down
 */

require_once __DIR__ . '/../../../.env/config.php';
require_once __DIR__ . '/../../models/model.php';
require_once __DIR__ . '/../../lib/auth_remember.php';

$action = $argv[1] ?? 'up';

try {
    if ($action === 'up') {
        auth_remember_ensure_table($conn);
        echo "user_remember_tokens table ready.\n";
        exit(0);
    }

    if ($action === 'down') {
        $conn->exec("DROP TABLE IF EXISTS user_remember_tokens");
        echo "Dropped user_remember_tokens.\n";
        exit(0);
    }

    echo "Unknown action: {$action}\n";
    exit(1);
} catch (PDOException $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(2);
}
