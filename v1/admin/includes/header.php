<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    // Check if they are logged in as a front-end user who is admin
    if (isset($_SESSION['user_id'])) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND is_admin = 1 LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($u) {
            $_SESSION['admin_id'] = $u['id'];
            $_SESSION['admin_name'] = ($u['firstName'] ?? $u['firstname'] ?? '') . ' ' . ($u['lastName'] ?? $u['lastname'] ?? '');
        }
    }
}

if (!isset($_SESSION['admin_id'])) {
    header("Location:/admin-login");
    die;
}

$whereAdmin['hash_id'] = $_SESSION['admin_id'];
$adminDetails = selectContent($conn, "admin", $whereAdmin);

// Fallback: If not found in admin table, check users table
if (empty($adminDetails)) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND is_admin = 1 LIMIT 1");
    $stmt->execute([$_SESSION['admin_id']]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($u) {
        $adminDetails = [[
            'id' => $u['id'],
            'firstname' => $u['firstName'] ?? $u['firstname'] ?? '',
            'lastname' => $u['lastName'] ?? $u['lastname'] ?? '',
            'email' => $u['email'],
            'level' => 'MASTER',
            'user_status' => 1
        ]];
    }
}

if (empty($adminDetails)) {
    header("Location:/admin-login?err=" . base64url_encode("Access Denied"));
    die;
}

$whereTable['TABLE_TYPE'] = "BASE TABLE";
$whereTable['TABLE_SCHEMA'] = $conn->query('SELECT DATABASE()')->fetchColumn();
$table_name['table_name'] = "table_name";
$tables = selectTableContent($conn, 'information_schema.tables', $table_name, $whereTable);

if ($adminDetails[0]['user_status'] == 2) {
    header("Location:/admin-login?err=" . base64url_encode("Your Account Has Been Suspended"));
    die;
}

if (!in_array($adminDetails[0]['level'], $level_check)) {
    unset($_SESSION['admin_id']);
    header("Location:/admin-login?err=" . base64url_encode("<p>You were logged out because your account cannot visit the page you visited</p>"));
    die;
}


// ------------------------------------------------------------------- menu --
// Every admin page shares this shell: a sidebar grouped by task and a top
// bar. $adminPageTitle (set before including this file) overrides the label
// shown in the top bar.
$adminPath = rtrim(strtok($_SERVER['REQUEST_URI'] ?? '/', '?'), '/') ?: '/';

$adminReviewCount = 0;
try {
    $adminReviewCount = (int) $conn->query("SELECT COUNT(*) FROM ingestion_jobs WHERE status = 'needs_review'")->fetchColumn();
} catch (PDOException $e) {
    // Ingestion tables not created yet.
}

// [href, icon, label, other paths that belong to it, badge]
$adminNav = [
    'Overview' => [
        ['/admin', 'ph-squares-four', 'Dashboard', ['/admin-dashboard'], 0],
    ],
    'Watching' => [
        ['/admin-playback', 'ph-play-circle', 'Playback & access', [], 0],
        ['/admin-free-films', 'ph-film-strip', 'Free films', [], 0],
        ['/admin-ingestion', 'ph-cloud-arrow-down', 'Ingestion queue', ['/admin-view-ingestion'], $adminReviewCount],
        ['/admin-view-downloads', 'ph-download-simple', 'Download links', ['/admin-downloads'], 0],
    ],
    'Members & AI' => [
        ['/admin-view-users', 'ph-users-three', 'Members', [], 0],
        ['/admin-ai', 'ph-sparkle', 'ZEN AI', ['/admin-view-ai'], 0],
    ],
];

// The admin template this project started from also listed generic CRUD pages
// for every panel_*/selection_* table (blogs, sliders, categories and so on).
// None of them belong to a film site, so the menu only carries the pages above.

$adminActiveLabel = 'Admin';
foreach ($adminNav as $items) {
    foreach ($items as [$href, , $label, $also]) {
        if ($adminPath === $href || in_array($adminPath, $also, true)) {
            $adminActiveLabel = $label;
        }
    }
}
$adminPageTitle = $adminPageTitle ?? $adminActiveLabel;

$adminName = trim($_SESSION['username'] ?? (($adminDetails[0]['firstname'] ?? '') . ' ' . ($adminDetails[0]['lastname'] ?? ''))) ?: 'Admin';
$adminEmail = $_SESSION['email'] ?? ($adminDetails[0]['email'] ?? '');
$adminAvatar = $_SESSION['avatar_url'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?php echo htmlspecialchars($adminPageTitle); ?> · ZEN Admin</title>
  <link rel="shortcut icon" href="/assets/images/favicon.ico" />

  <!-- Vendor admin styles: forms, tables, modals and the feather icons the pages use -->
  <link rel="stylesheet" href="/da/assets/fonts/fontawesome/css/fontawesome-all.min.css">
  <link rel="stylesheet" href="/da/assets/fonts/material/css/materialdesignicons.min.css">
  <link rel="stylesheet" href="/da/assets/plugins/animation/css/animate.min.css">
  <link rel="stylesheet" href="/da/assets/plugins/prism/css/prism.min.css">
  <link rel="stylesheet" href="/da/assets/css/style.css">
  <link rel="stylesheet" href="/da/assets/plugins/data-tables/css/datatables.min.css">
  <link rel="stylesheet" href="/da/assets/plugins/modal-window-effects/css/md-modal.css">
  <link rel="stylesheet" href="/da/assets/plugins/ekko-lightbox/css/ekko-lightbox.min.css">
  <link rel="stylesheet" href="/da/assets/plugins/lightbox2-master/css/lightbox.min.css">

  <!-- The site's icon set, for the shell -->
  <link rel="stylesheet" href="/assets/vendor/phosphor-icons/Fonts/regular/style.css">
  <link rel="stylesheet" href="/assets/vendor/phosphor-icons/Fonts/fill/style.css">

  <!-- Admin theme and shell. Loads last so it overrides the vendor styles. -->
  <link rel="stylesheet" href="/assets/css/core/admin-theme.css?v=<?php echo @filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/core/admin-theme.css') ?: time(); ?>">

  <style media="screen">
    .modal-backdrop { z-index: 3000; }
    .modal { z-index: 4000; }
  </style>
  <script src="/ajax/ajax.js"></script>
</head>

<body class="zadm-body">
  <div class="zadm-scrim" id="zadmScrim" hidden></div>

  <aside class="zadm-side" id="zadmSide" aria-label="Admin menu">
    <div class="zadm-brand">
      <a href="/admin" class="zadm-logo"><span>ZEN</span><small>Admin</small></a>
      <button type="button" class="zadm-icon-btn zadm-close" id="zadmClose" aria-label="Close menu"><i class="ph ph-x"></i></button>
    </div>

    <nav class="zadm-nav">
      <?php foreach ($adminNav as $group => $items): ?>
      <div class="zadm-group">
        <p class="zadm-group-label"><?php echo htmlspecialchars($group); ?></p>
        <?php foreach ($items as [$href, $icon, $label, $also, $badge]):
            $active = $adminPath === $href || in_array($adminPath, $also, true);
        ?>
        <a class="zadm-link<?php echo $active ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars($href); ?>"<?php echo $active ? ' aria-current="page"' : ''; ?>>
          <i class="ph <?php echo $icon; ?>" aria-hidden="true"></i>
          <span><?php echo htmlspecialchars($label); ?></span>
          <?php if ($badge): ?><span class="zadm-badge" title="<?php echo (int) $badge; ?> waiting for review"><?php echo (int) $badge; ?></span><?php endif; ?>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </nav>

    <div class="zadm-side-foot">
      <a class="zadm-link" href="/dashboard"><i class="ph ph-squares-four" aria-hidden="true"></i><span>My dashboard</span></a>
      <a class="zadm-link" href="/" target="_blank" rel="noopener"><i class="ph ph-arrow-square-out" aria-hidden="true"></i><span>View site</span></a>
      <a class="zadm-link" href="/logout"><i class="ph ph-sign-out" aria-hidden="true"></i><span>Sign out</span></a>
    </div>
  </aside>

  <header class="zadm-top">
    <button type="button" class="zadm-icon-btn zadm-burger" id="zadmOpen" aria-label="Open menu" aria-controls="zadmSide" aria-expanded="false"><i class="ph ph-list"></i></button>
    <div class="zadm-crumbs">
      <a href="/admin" class="zadm-crumb-root">Admin</a>
      <i class="ph ph-caret-right zadm-crumb-sep" aria-hidden="true"></i>
      <span><?php echo htmlspecialchars($adminPageTitle); ?></span>
    </div>
    <div class="zadm-top-actions">
      <a class="zadm-top-btn" href="/" target="_blank" rel="noopener" title="View site"><i class="ph ph-arrow-up-right"></i><span>View site</span></a>
      <div class="zadm-me">
        <?php if ($adminAvatar): ?>
        <img class="zadm-avatar" src="<?php echo htmlspecialchars($adminAvatar); ?>" alt="">
        <?php else: ?>
        <span class="zadm-avatar"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($adminName, 0, 1))); ?></span>
        <?php endif; ?>
        <span class="zadm-me-text">
          <strong><?php echo htmlspecialchars($adminName); ?></strong>
          <?php if ($adminEmail): ?><small><?php echo htmlspecialchars($adminEmail); ?></small><?php endif; ?>
        </span>
      </div>
    </div>
  </header>

  <script>
  // Phone menu: slides in over the page.
  (function () {
    var body = document.body, side = document.getElementById('zadmSide'),
        scrim = document.getElementById('zadmScrim'), openBtn = document.getElementById('zadmOpen'),
        closeBtn = document.getElementById('zadmClose');
    function setOpen(open) {
      body.classList.toggle('zadm-nav-open', open);
      scrim.hidden = !open;
      openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) { var first = side.querySelector('.zadm-link'); if (first) first.focus(); } else { openBtn.focus(); }
    }
    openBtn.addEventListener('click', function () { setOpen(true); });
    closeBtn.addEventListener('click', function () { setOpen(false); });
    scrim.addEventListener('click', function () { setOpen(false); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && body.classList.contains('zadm-nav-open')) setOpen(false);
    });
  })();
  </script>
