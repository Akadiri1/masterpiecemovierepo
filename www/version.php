<?php
/**
 * What the installed app asks to find out whether a newer build is live.
 *
 * Deliberately tiny: no session, no database, no config. It is polled in the
 * background by every open tab, so it has to stay cheap and must never be
 * cached by the browser or the service worker.
 */

require_once __DIR__ . '/../v1/lib/app_version.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

echo json_encode(['version' => appVersion()]);
