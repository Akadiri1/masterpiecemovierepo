<?php
/**
 * Delete links on the older admin pages (blog, discussions, categories,
 * slider, admin accounts, panel_* tables). Only from this site's own pages.
 * Member accounts are deleted under Admin > Members, which also removes
 * their personal data.
 */
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: /admin-login");
    exit;
}

require_once dirname(__DIR__) . '/.env/config.php';
require_once dirname(__DIR__) . '/v1/models/model.php';
require_once dirname(__DIR__) . '/v1/lib/admin_request.php';

const DELETE_FALLBACK = '/admin';

if (!adminRequestIsSameSite()) {
    adminRequestFail(403, 'For your security, open this from the admin panel and try again.', DELETE_FALLBACK);
}

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$table = (string) ($_GET['data'] ?? '');
$allowedTables = ['admin', 'blogs', 'topic', 'slider', 'discussion_category'];

if (!$id || !(in_array($table, $allowedTables, true) || preg_match('/^panel_[a-z0-9_]+$/', $table))) {
    adminRequestFail(400, $table === 'users'
        ? 'Delete members from Admin > Members.'
        : "That can't be deleted from here.", DELETE_FALLBACK);
}

try {
    $conn->prepare("DELETE FROM `$table` WHERE id = ?")->execute([$id]);
} catch (PDOException $e) {
    error_log('deleteContent ' . $table . ': ' . $e->getMessage());
    adminRequestFail(500, "Couldn't delete that. Please try again.", DELETE_FALLBACK);
}

header('Location: ' . adminReturnPath(DELETE_FALLBACK));
exit;
