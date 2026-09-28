<?php
/**
 * Which streaming hosts are answering, for the watch page.
 *
 * Called after the page has rendered, never during it, so a slow or dead host
 * delays nothing the viewer is waiting on. The answer is shared and cached,
 * so this is usually a file read.
 */

require_once __DIR__ . '/../v1/lib/server_health.php';

header('Content-Type: application/json; charset=utf-8');
// Browsers may hold it briefly; the real caching happens on this side.
header('Cache-Control: public, max-age=120');

echo json_encode(['hosts' => serverHealthStatus()]);
