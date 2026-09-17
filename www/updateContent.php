<?php
/**
 * Show/hide and status switches for the older admin pages (blog, discussions,
 * slider, admin accounts, panel_* tables), called through plain links.
 *
 * Only the columns and values listed below can change, and only from this
 * site's own pages. Member accounts are managed under Admin > Members.
 */
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: /admin-login");
    exit;
}

require_once dirname(__DIR__) . '/.env/config.php';
require_once dirname(__DIR__) . '/v1/models/model.php';
require_once dirname(__DIR__) . '/v1/lib/admin_request.php';

const UPDATE_FALLBACK = '/admin';

// table => [column => allowed values]
$visibility = ['visibility' => ['show', 'hide']];
$rules = [
    'admin'  => ['level' => ['1', '2', '3'], 'user_status' => ['1', '2'], 'verification' => ['1']],
    'blogs'  => $visibility,
    'topic'  => $visibility,
    'slider' => $visibility,
];

if (!adminRequestIsSameSite()) {
    adminRequestFail(403, 'For your security, open this from the admin panel and try again.', UPDATE_FALLBACK);
}

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$table = (string) ($_GET['data'] ?? '');
$allowed = $rules[$table] ?? (preg_match('/^panel_[a-z0-9_]+$/', $table) ? $visibility : null);

if (!$id || $allowed === null) {
    adminRequestFail(400, "That change isn't allowed.", UPDATE_FALLBACK);
}

$updates = [];
$values = [];
foreach ($_GET as $column => $value) {
    if ($column === 'id' || $column === 'data') {
        continue;
    }
    if (!isset($allowed[$column]) || !in_array((string) $value, $allowed[$column], true)) {
        adminRequestFail(400, "That change isn't allowed.", UPDATE_FALLBACK);
    }
    $updates[] = "`$column` = ?";
    $values[] = $value;
}

if ($updates) {
    try {
        $conn->prepare("UPDATE `$table` SET " . implode(', ', $updates) . " WHERE id = ?")->execute(array_merge($values, [$id]));
    } catch (PDOException $e) {
        error_log('updateContent ' . $table . ': ' . $e->getMessage());
        adminRequestFail(500, "Couldn't save that change. Please try again.", UPDATE_FALLBACK);
    }
}

header('Location: ' . adminReturnPath(UPDATE_FALLBACK));
exit;
