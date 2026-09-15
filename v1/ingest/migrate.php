<?php
/**
 * Runs the ingestion table migration.
 *
 *   php v1/ingest/migrate.php up
 *   php v1/ingest/migrate.php down
 */

require_once __DIR__ . '/bootstrap.php';

$action = $argv[1] ?? 'up';
require __DIR__ . '/../db/migrations/2026_09_05_create_ingestion_tables.php';
