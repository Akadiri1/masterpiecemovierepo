<?php
/**
 * Runs the AI tables migration.
 *
 *   php v1/db/migrate_ai.php up
 *   php v1/db/migrate_ai.php down
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Migrations are CLI-only.\n");
}

require_once __DIR__ . '/../../.env/config.php';
require_once __DIR__ . '/../models/model.php';

$action = $argv[1] ?? 'up';
require __DIR__ . '/migrations/2026_09_09_create_ai_tables.php';
