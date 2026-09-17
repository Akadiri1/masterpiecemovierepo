<?php
ob_start();
$level_check = ['MASTER'];
$adminPageTitle = 'Members';
include 'includes/header.php';
require_once APP_PATH . '/lib/site_settings.php';

// ==========================================
// MEMBERS
// ==========================================
// Every change is a POST carrying this page's token, so another website
// can't make an admin's browser change accounts. (The old page used plain
// links to /updateContent.php, which any site could trigger.)
//
// users.user_status: NULL not verified, 1 verified, 2 suspended. Suspended
// members can't sign in, and anyone already signed in is signed out within
// five minutes (lib/auth_remember.php).

const MEMBER_VERIFIED = 1;
const MEMBER_SUSPENDED = 2;
const MEMBERS_PER_PAGE = 20;

if (empty($_SESSION['members_csrf'])) {
    $_SESSION['members_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['members_csrf'];
$myId = (int) ($_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 0);

// ------------------------------------------------------------------ actions --
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $flash = ['danger', 'Your session expired. Please try again.'];
    $memberId = (int) ($_POST['member_id'] ?? 0);

    if (hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        try {
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$memberId]);
            $member = $stmt->fetch(PDO::FETCH_ASSOC);
            $name = $member ? $member['username'] : '';
            $isMe = $member && (int) $member['id'] === $myId;
            $setUser = function (string $sql, array $params) use ($conn, $memberId) {
                $conn->prepare("UPDATE users SET $sql WHERE id = ?")->execute(array_merge($params, [$memberId]));
            };

            if (!$member) {
                $flash = ['danger', 'That member no longer exists.'];
            } else {
                switch ($_POST['action']) {
                    case 'verify':
                        $setUser('user_status = ?', [MEMBER_VERIFIED]);
                        $flash = ['success', "$name is verified."];
                        break;

                    case 'suspend':
                        if ($isMe) {
                            $flash = ['danger', "You can't suspend your own account."];
                            break;
                        }
                        $setUser('user_status = ?', [MEMBER_SUSPENDED]);
                        auth_remember_revoke_all($conn, $memberId);
                        $flash = ['success', "$name is suspended. They can't sign in, and they'll be signed out within 5 minutes."];
                        break;

                    case 'reactivate':
                        $setUser('user_status = ?', [MEMBER_VERIFIED]);
                        $flash = ['success', "$name can sign in again."];
                        break;

                    case 'make_admin':
                        $setUser("is_admin = 1, role = 'admin'", []);
                        $flash = ['success', "$name is now an admin."];
                        break;

                    case 'revoke_admin':
                        if ($isMe) {
                            $flash = ['danger', "You can't remove your own admin access."];
                            break;
                        }
                        $setUser("is_admin = 0, role = 'user'", []);
                        $flash = ['success', "$name is no longer an admin."];
                        break;

                    case 'ai_limit':
                        $limit = max(-1, (int) ($_POST['ai_tokens_limit'] ?? 10));
                        $setUser('ai_tokens_limit = ?', [$limit]);
                        $flash = ['success', "$name's daily ZEN AI limit is now " . ($limit === -1 ? 'unlimited' : $limit) . '.'];
                        break;

                    case 'kids_mode':
                        $on = ($_POST['value'] ?? '') === '1';
                        $setUser('is_kids_mode = ?', [$on ? '1' : '0']);
                        if ($isMe) {
                            $_SESSION['is_kids_mode'] = $on;
                            $_SESSION['is_kid'] = $on ? 1 : 0;
                            $flash = ['success', 'Kids Mode is ' . ($on ? 'on' : 'off') . ' for your account. Open the site to see it.'];
                        } else {
                            $flash = ['success', "Kids Mode " . ($on ? 'on' : 'off') . " for $name. If they're signed in, it applies within a minute."];
                        }
                        break;

                    case 'streaming_access':
                        $email = strtolower(trim($member['email']));
                        ensurePlaybackAccessTable($conn);
                        $on = ($_POST['value'] ?? '') === '1';
                        if ($on) {
                            $conn->prepare("INSERT IGNORE INTO playback_access (email, note, granted_by) VALUES (?, ?, ?)")
                                 ->execute([$email, 'Given from Members', $myId ?: null]);
                            $flash = ['success', "$name now gets Streaming servers on the watch page."];
                        } else {
                            $conn->prepare("DELETE FROM playback_access WHERE email = ?")->execute([$email]);
                            $flash = ['success', "$name is back on what everyone else gets."];
                        }
                        // An admin's own preview (Playback & access) would hide
                        // the change from them, so it gives way.
                        if ($isMe && isset($_SESSION['playback_preview'])) {
                            unset($_SESSION['playback_preview']);
                            $flash[1] .= ' Your Preview was turned off so you see the result.';
                        }
                        break;

                    case 'delete':
                        if ($isMe) {
                            $flash = ['danger', "You can't delete your own account."];
                            break;
                        }
                        // Their personal data goes with them; payment records
                        // (subscriptions) and usage logs stay for the books.
                        foreach (['watchlist', 'watch_history', 'reviews', 'zen_search_history', 'user_remember_tokens', 'profiles'] as $table) {
                            try {
                                $conn->prepare("DELETE FROM `$table` WHERE user_id = ?")->execute([$memberId]);
                            } catch (PDOException $e) {
                                // Table not on this database.
                            }
                        }
                        try {
                            $conn->prepare("DELETE FROM playback_access WHERE email = ?")->execute([strtolower($member['email'])]);
                        } catch (PDOException $e) {}
                        $conn->prepare("DELETE FROM users WHERE id = ?")->execute([$memberId]);
                        $flash = ['success', "$name's account was deleted."];
                        break;

                    default:
                        $flash = ['danger', 'Unknown action.'];
                }
            }
        } catch (PDOException $e) {
            error_log('Admin members: ' . $e->getMessage());
            $flash = ['danger', "Couldn't save that change. Please try again."];
        }
    }

    $_SESSION['members_flash'] = $flash;
    $back = '/admin-view-users';
    if (!empty($_POST['return']) && preg_match('~^\?[\w=&%.+-]*$~', $_POST['return'])) {
        $back .= $_POST['return'];
    }
    header('Location: ' . $back . ($memberId ? '#member-' . $memberId : ''));
    exit;
}

[$messageType, $message] = $_SESSION['members_flash'] ?? ['', ''];
unset($_SESSION['members_flash']);

// -------------------------------------------------------------------- data --
$q = trim((string) ($_GET['q'] ?? ''));
$filters = [
    'all' => 'All',
    'admins' => 'Admins',
    'verified' => 'Verified',
    'unverified' => 'Not verified',
    'suspended' => 'Suspended',
    'streaming' => 'Streaming access',
];
$filter = isset($filters[$_GET['filter'] ?? '']) ? $_GET['filter'] : 'all';
$page = max(1, (int) ($_GET['page'] ?? 1));

$accessEmails = [];
try {
    ensurePlaybackAccessTable($conn);
    $accessEmails = array_flip($conn->query("SELECT email FROM playback_access")->fetchAll(PDO::FETCH_COLUMN));
} catch (PDOException $e) {}
$serversForEveryone = siteSetting('playback_mode') === PLAYBACK_SERVERS;

$where = [];
$params = [];
if ($q !== '') {
    $where[] = "(u.username LIKE ? OR u.email LIKE ? OR CONCAT_WS(' ', u.firstName, u.lastName) LIKE ?)";
    $like = '%' . addcslashes($q, '%_\\') . '%';
    array_push($params, $like, $like, $like);
}
switch ($filter) {
    case 'admins':     $where[] = 'u.is_admin = 1'; break;
    case 'verified':   $where[] = 'u.user_status = ' . MEMBER_VERIFIED; break;
    case 'unverified': $where[] = 'u.user_status IS NULL'; break;
    case 'suspended':  $where[] = 'u.user_status = ' . MEMBER_SUSPENDED; break;
    case 'streaming':
        if ($accessEmails) {
            $where[] = 'LOWER(u.email) IN (' . implode(',', array_fill(0, count($accessEmails), '?')) . ')';
            array_push($params, ...array_keys($accessEmails));
        } else {
            $where[] = '1 = 0';
        }
        break;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$counts = ['total' => 0, 'verified' => 0, 'suspended' => 0, 'admins' => 0];
$members = [];
$matching = 0;
try {
    $counts = $conn->query("SELECT COUNT(*) total, SUM(user_status = 1) verified, SUM(user_status = 2) suspended, SUM(is_admin = 1) admins FROM users")->fetch(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("SELECT COUNT(*) FROM users u $whereSql");
    $stmt->execute($params);
    $matching = (int) $stmt->fetchColumn();

    $pages = max(1, (int) ceil($matching / MEMBERS_PER_PAGE));
    $page = min($page, $pages);
    $offset = ($page - 1) * MEMBERS_PER_PAGE;

    $stmt = $conn->prepare("SELECT u.id, u.username, u.email, u.firstName, u.lastName, u.avatar_url, u.is_admin,
            u.is_kids_mode, u.ai_tokens_limit, u.user_status, u.created_at,
            (SELECT s.plan_name FROM subscriptions s WHERE s.user_id = u.id AND s.status = 'active' AND s.expires_at > NOW()
              ORDER BY s.expires_at DESC LIMIT 1) AS plan,
            (SELECT MAX(w.last_watched) FROM watch_history w WHERE w.user_id = u.id) AS last_watched
        FROM users u $whereSql ORDER BY u.id DESC LIMIT " . MEMBERS_PER_PAGE . " OFFSET $offset");
    $stmt->execute($params);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Admin members list: ' . $e->getMessage());
    $message = "Couldn't load members. Please refresh.";
    $messageType = 'danger';
}
$pages = max(1, (int) ceil($matching / MEMBERS_PER_PAGE));

$query = fn(array $change) => '?' . http_build_query(array_filter(array_merge(['q' => $q, 'filter' => $filter, 'page' => $page], $change), fn($v) => $v !== '' && $v !== 'all' && $v !== 1));
$returnQuery = $query([]);
$dateLabel = fn($when) => $when ? date(strtotime($when) > strtotime('-300 days') ? 'j M' : 'j M Y', strtotime($when)) : null;
?>

<style>
  .mb-stats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
  @media (min-width: 992px) { .mb-stats { grid-template-columns: repeat(5, minmax(0, 1fr)); } }
  .mb-stat { padding: 14px 16px; border: 1px solid var(--adm-border); border-radius: var(--adm-radius); background: var(--adm-surface); }
  .mb-stat strong { display: block; font-size: 1.5rem; font-weight: 800; color: var(--adm-text); line-height: 1.2; }
  .mb-stat span { color: var(--adm-muted); font-size: .78rem; font-weight: 600; }

  .mb-toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; margin-bottom: 14px; }
  .mb-search { position: relative; flex: 1 1 260px; max-width: 420px; margin: 0; }
  .mb-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--adm-muted); pointer-events: none; }
  .mb-search input { padding-left: 36px !important; }
  .mb-filters { display: flex; flex-wrap: wrap; gap: 6px; }
  .mb-filter { padding: 6px 12px; border-radius: 999px; border: 1px solid var(--adm-border); color: var(--adm-muted) !important; font-size: .8rem; font-weight: 600; text-decoration: none !important; white-space: nowrap; }
  .mb-filter:hover { color: var(--adm-text) !important; background: var(--adm-surface-2); }
  .mb-filter.is-active { color: #fff !important; background: rgba(var(--primary-rgb), .16); border-color: rgba(var(--primary-rgb), .5); }

  .mb-list { border: 1px solid var(--adm-border); border-radius: var(--adm-radius); background: var(--adm-surface); overflow: hidden; }
  .mb-row { display: grid; grid-template-columns: minmax(0, 2.2fr) minmax(0, 1fr) minmax(0, 1.25fr) minmax(0, 1.3fr) auto; gap: 16px; align-items: center; padding: 16px 18px; scroll-margin-top: 90px; }
  .mb-row + .mb-row { border-top: 1px solid var(--adm-border); }
  .mb-row:target { background: rgba(var(--primary-rgb), .06); }
  .mb-row.is-suspended { background: rgba(248, 113, 113, .04); }
  .mb-who { display: flex; align-items: center; gap: 12px; min-width: 0; }
  .mb-avatar { width: 42px; height: 42px; border-radius: 50%; flex-shrink: 0; object-fit: cover; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; }
  .mb-name { display: block; color: var(--adm-text); font-weight: 600; font-size: .92rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .mb-email { display: block; color: var(--adm-muted); font-size: .78rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .mb-badges { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 5px; }
  .mb-badge { padding: 1px 8px; border-radius: 999px; font-size: .68rem; font-weight: 700; line-height: 1.6; }
  .mb-badge.admin { color: #ff8b93; background: rgba(var(--primary-rgb), .15); }
  .mb-badge.verified { color: var(--adm-green); background: rgba(52, 211, 153, .13); }
  .mb-badge.unverified { color: var(--adm-muted); background: rgba(255, 255, 255, .06); }
  .mb-badge.suspended { color: var(--adm-red); background: rgba(248, 113, 113, .14); }
  .mb-badge.plan { color: var(--adm-amber); background: rgba(251, 191, 36, .12); }
  .mb-badge.you { color: var(--adm-cyan); background: rgba(34, 211, 238, .12); }
  .mb-meta { color: var(--adm-muted); font-size: .78rem; line-height: 1.6; }
  .mb-meta b { color: var(--adm-text); font-weight: 600; }
  .mb-label { display: block; color: var(--adm-dim); font-size: .66rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; margin-bottom: 4px; }
  .mb-ai { display: flex; gap: 6px; align-items: center; margin: 0; }
  .mb-ai input { width: 72px !important; height: 32px; padding: 4px 8px !important; text-align: center; }
  .mb-ai .btn { height: 32px; padding: 0 10px; font-size: .78rem; }
  .mb-ai-note { display: block; color: var(--adm-muted); font-size: .72rem; margin-top: 3px; }
  .mb-switches { display: flex; flex-direction: column; gap: 6px; }
  .mb-switches form { margin: 0; }
  .mb-switch { display: inline-flex; align-items: center; gap: 8px; padding: 0; border: 0; background: none; color: var(--adm-muted); font-size: .8rem; cursor: pointer; }
  .mb-switch:disabled { cursor: not-allowed; opacity: .5; }
  .mb-switch .track { position: relative; width: 32px; height: 18px; border-radius: 999px; background: rgba(255, 255, 255, .14); transition: background .15s; flex-shrink: 0; }
  .mb-switch .track::after { content: ""; position: absolute; top: 2px; left: 2px; width: 14px; height: 14px; border-radius: 50%; background: #fff; transition: transform .15s; }
  .mb-switch[aria-pressed="true"] { color: var(--adm-text); }
  .mb-switch[aria-pressed="true"] .track { background: var(--primary); }
  .mb-switch[aria-pressed="true"] .track::after { transform: translateX(14px); }
  .mb-actions { display: flex; flex-wrap: wrap; gap: 6px; justify-content: flex-end; }
  .mb-actions form { margin: 0; }
  .mb-actions .btn { padding: 4px 10px; font-size: .76rem; white-space: nowrap; }
  .mb-empty { padding: 40px 20px; text-align: center; color: var(--adm-muted); }
  .mb-pager { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 14px; color: var(--adm-muted); font-size: .82rem; }
  .mb-pager .btn[aria-disabled="true"] { pointer-events: none; opacity: .4; }

  @media (max-width: 1199.98px) {
    .mb-row { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
    .mb-who { grid-column: 1 / -1; }
    .mb-actions { grid-column: 1 / -1; justify-content: flex-start; }
  }
  @media (max-width: 575.98px) {
    .mb-row { grid-template-columns: minmax(0, 1fr); gap: 12px; padding: 14px; }
  }
</style>

<!-- [ Header ] end -->
<div class="container">
  <div class="wrapper">
    <div class="content">
      <div class="content">
        <div class="main-body">
          <div class="page-wrapper">

            <div class="page-header">
              <div class="page-block">
                <div class="row align-items-center">
                  <div class="col-md-12">
                    <div class="page-header-title"><h5>Members</h5></div>
                    <ul class="breadcrumb">
                      <li class="breadcrumb-item"><a href="/admin"><i class="feather icon-home"></i></a></li>
                      <li class="breadcrumb-item"><a href="#!">Members</a></li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($messageType); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
            <?php endif; ?>

            <div class="mb-stats">
              <div class="mb-stat"><strong><?php echo number_format((int) $counts['total']); ?></strong><span>Members</span></div>
              <div class="mb-stat"><strong><?php echo number_format((int) $counts['verified']); ?></strong><span>Verified</span></div>
              <div class="mb-stat"><strong><?php echo number_format((int) $counts['suspended']); ?></strong><span>Suspended</span></div>
              <div class="mb-stat"><strong><?php echo number_format((int) $counts['admins']); ?></strong><span>Admins</span></div>
              <div class="mb-stat"><strong><?php echo number_format(count($accessEmails)); ?></strong><span>Streaming access</span></div>
            </div>

            <div class="mb-toolbar">
              <form method="GET" action="/admin-view-users" class="mb-search" role="search">
                <i class="ph ph-magnifying-glass" aria-hidden="true"></i>
                <?php if ($filter !== 'all'): ?><input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>"><?php endif; ?>
                <input type="search" name="q" class="form-control" placeholder="Search name, username or email" value="<?php echo htmlspecialchars($q); ?>" aria-label="Search members">
              </form>
              <nav class="mb-filters" aria-label="Filter members">
                <?php foreach ($filters as $key => $label): ?>
                <a class="mb-filter<?php echo $filter === $key ? ' is-active' : ''; ?>" href="/admin-view-users<?php echo htmlspecialchars($query(['filter' => $key, 'page' => 1])); ?>"><?php echo $label; ?></a>
                <?php endforeach; ?>
              </nav>
            </div>

            <?php if ($serversForEveryone): ?>
            <div class="alert alert-info">Streaming servers are on for everyone (Playback &amp; access), so the Streaming access switches change nothing right now.</div>
            <?php endif; ?>

            <div class="mb-list">
              <?php if (!$members): ?>
              <div class="mb-empty"><?php echo $q !== '' || $filter !== 'all' ? 'No members match.' : 'No members yet.'; ?></div>
              <?php endif; ?>

              <?php foreach ($members as $m):
                  $id = (int) $m['id'];
                  $status = $m['user_status'] === null ? null : (int) $m['user_status'];
                  $isMe = $id === $myId;
                  $fullName = trim(($m['firstName'] ?? '') . ' ' . ($m['lastName'] ?? ''));
                  $initial = mb_strtoupper(mb_substr($m['username'] ?: $m['email'], 0, 1));
                  $hue = crc32((string) $m['username']) % 360;
                  $limit = $m['ai_tokens_limit'] === null ? 10 : (int) $m['ai_tokens_limit'];
                  $kids = (int) $m['is_kids_mode'] === 1;
                  $streaming = isset($accessEmails[strtolower($m['email'])]);
                  $hidden = '<input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="member_id" value="' . $id . '"><input type="hidden" name="return" value="' . htmlspecialchars($returnQuery) . '">';
              ?>
              <div class="mb-row<?php echo $status === MEMBER_SUSPENDED ? ' is-suspended' : ''; ?>" id="member-<?php echo $id; ?>">
                <div class="mb-who">
                  <?php if (!empty($m['avatar_url'])): ?>
                  <img class="mb-avatar" src="<?php echo htmlspecialchars($m['avatar_url']); ?>" alt="" loading="lazy">
                  <?php else: ?>
                  <span class="mb-avatar" style="background: hsla(<?php echo $hue; ?>, 70%, 55%, .18); color: hsl(<?php echo $hue; ?>, 80%, 72%);"><?php echo htmlspecialchars($initial); ?></span>
                  <?php endif; ?>
                  <div style="min-width:0;">
                    <span class="mb-name"><?php echo htmlspecialchars($m['username']); ?><?php if ($fullName !== ''): ?> <span style="color:var(--adm-muted);font-weight:400;">· <?php echo htmlspecialchars($fullName); ?></span><?php endif; ?></span>
                    <span class="mb-email"><?php echo htmlspecialchars($m['email']); ?></span>
                    <div class="mb-badges">
                      <?php if ($isMe): ?><span class="mb-badge you">You</span><?php endif; ?>
                      <?php if ((int) $m['is_admin'] === 1): ?><span class="mb-badge admin">Admin</span><?php endif; ?>
                      <?php if ($status === MEMBER_SUSPENDED): ?><span class="mb-badge suspended">Suspended</span>
                      <?php elseif ($status === MEMBER_VERIFIED): ?><span class="mb-badge verified">Verified</span>
                      <?php else: ?><span class="mb-badge unverified">Not verified</span><?php endif; ?>
                      <?php if (!empty($m['plan'])): ?><span class="mb-badge plan"><?php echo htmlspecialchars(ucfirst($m['plan'])); ?></span><?php endif; ?>
                    </div>
                  </div>
                </div>

                <div class="mb-meta">
                  Joined <b><?php echo htmlspecialchars($dateLabel($m['created_at']) ?? '—'); ?></b><br>
                  Last watched <b><?php echo htmlspecialchars($dateLabel($m['last_watched']) ?? 'never'); ?></b>
                </div>

                <div>
                  <span class="mb-label">ZEN AI per day</span>
                  <form method="POST" class="mb-ai">
                    <?php echo $hidden; ?>
                    <input type="hidden" name="action" value="ai_limit">
                    <input type="number" name="ai_tokens_limit" min="-1" value="<?php echo $limit; ?>" class="form-control" aria-label="Daily ZEN AI limit for <?php echo htmlspecialchars($m['username']); ?>">
                    <button type="submit" class="btn btn-outline-secondary">Set</button>
                  </form>
                  <span class="mb-ai-note"><?php echo $limit === -1 ? 'Unlimited' : $limit . ' a day'; ?> · -1 for unlimited</span>
                </div>

                <div class="mb-switches">
                  <form method="POST">
                    <?php echo $hidden; ?>
                    <input type="hidden" name="action" value="kids_mode">
                    <button type="submit" name="value" value="<?php echo $kids ? '0' : '1'; ?>" class="mb-switch" aria-pressed="<?php echo $kids ? 'true' : 'false'; ?>">
                      <span class="track"></span> Kids Mode
                    </button>
                  </form>
                  <form method="POST">
                    <?php echo $hidden; ?>
                    <input type="hidden" name="action" value="streaming_access">
                    <button type="submit" name="value" value="<?php echo $streaming ? '0' : '1'; ?>" class="mb-switch" aria-pressed="<?php echo $streaming ? 'true' : 'false'; ?>" title="Streaming servers for this member, even while everyone else gets Discover">
                      <span class="track"></span> Streaming access
                    </button>
                  </form>
                </div>

                <div class="mb-actions">
                  <?php if ($status === MEMBER_SUSPENDED): ?>
                  <form method="POST"><?php echo $hidden; ?><button type="submit" name="action" value="reactivate" class="btn btn-success">Reactivate</button></form>
                  <?php else: ?>
                    <?php if ($status !== MEMBER_VERIFIED): ?>
                    <form method="POST"><?php echo $hidden; ?><button type="submit" name="action" value="verify" class="btn btn-success">Verify</button></form>
                    <?php endif; ?>
                    <?php if (!$isMe): ?>
                    <form method="POST" onsubmit="return confirm('Suspend <?php echo htmlspecialchars(addslashes($m['username'])); ?>? They won\'t be able to sign in.')"><?php echo $hidden; ?><button type="submit" name="action" value="suspend" class="btn btn-warning">Suspend</button></form>
                    <?php endif; ?>
                  <?php endif; ?>
                  <?php if ((int) $m['is_admin'] === 1): ?>
                    <?php if (!$isMe): ?>
                    <form method="POST" onsubmit="return confirm('Remove admin access from <?php echo htmlspecialchars(addslashes($m['username'])); ?>?')"><?php echo $hidden; ?><button type="submit" name="action" value="revoke_admin" class="btn btn-outline-secondary">Remove admin</button></form>
                    <?php endif; ?>
                  <?php else: ?>
                  <form method="POST" onsubmit="return confirm('Make <?php echo htmlspecialchars(addslashes($m['username'])); ?> an admin? They\'ll be able to change everything here.')"><?php echo $hidden; ?><button type="submit" name="action" value="make_admin" class="btn btn-outline-secondary">Make admin</button></form>
                  <?php endif; ?>
                  <?php if (!$isMe): ?>
                  <form method="POST" onsubmit="return confirm('Delete <?php echo htmlspecialchars(addslashes($m['username'])); ?>\'s account? Their watchlist, history and reviews are deleted too. This can\'t be undone.')"><?php echo $hidden; ?><button type="submit" name="action" value="delete" class="btn btn-danger">Delete</button></form>
                  <?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>
            </div>

            <?php if ($pages > 1): ?>
            <div class="mb-pager">
              <a class="btn btn-outline-secondary btn-sm" href="/admin-view-users<?php echo htmlspecialchars($query(['page' => $page - 1])); ?>"<?php echo $page <= 1 ? ' aria-disabled="true"' : ''; ?>>Previous</a>
              <span>Page <?php echo $page; ?> of <?php echo $pages; ?> · <?php echo number_format($matching); ?> members</span>
              <a class="btn btn-outline-secondary btn-sm" href="/admin-view-users<?php echo htmlspecialchars($query(['page' => $page + 1])); ?>"<?php echo $page >= $pages ? ' aria-disabled="true"' : ''; ?>>Next</a>
            </div>
            <?php endif; ?>

          </div>
        </div>
      </div>
    </div>
  </div>
</div>

  <script src="/da/assets/js/vendor-all.min.js"></script>
  <script src="/da/assets/plugins/bootstrap/js/bootstrap.min.js"></script>
</body>
</html>
