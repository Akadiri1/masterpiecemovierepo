<?php
ob_start();
$level_check = ['MASTER', 3, 2, 1];
include 'includes/header.php';
require_once APP_PATH . '/lib/site_settings.php';

// ==========================================
// PLAYBACK
// ==========================================
// What the watch page offers visitors (the two modes are explained in
// lib/site_settings.php), the "Where to watch" options, and the videos the
// site has the rights to play in full.

$adminId = isset($_SESSION['admin_id']) && is_numeric($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;

if (empty($_SESSION['playback_csrf'])) {
    $_SESSION['playback_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['playback_csrf'];

function playbackSiteUrl(): string
{
    if ($url = getenv('SITE_URL')) {
        return rtrim($url, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

/**
 * The invite for someone on the access list: sign in when they already have
 * an account with that email, otherwise create one with it.
 */
function playbackInvite(string $email, bool $hasAccount): array
{
    $url = playbackSiteUrl() . ($hasAccount ? '/login' : '/register');
    $step = $hasAccount ? 'Sign in' : 'Create your account';
    $text = "You've been given access to streaming on ZEN.\n\n"
          . "$step with this email address ($email) and every title plays:\n$url";

    $e = fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $html = '<div style="background:#0d0a10;padding:32px 16px;font-family:Arial,Helvetica,sans-serif;">'
          . '<div style="max-width:480px;margin:0 auto;background:#16121c;border:1px solid #2a2433;border-radius:16px;padding:32px;color:#e9eaee;">'
          . '<div style="font-size:26px;font-weight:900;color:#e50914;letter-spacing:-1px;margin-bottom:20px;">ZEN</div>'
          . '<h1 style="margin:0 0 12px;font-size:20px;color:#ffffff;">You\'re in</h1>'
          . '<p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#c9cbd1;">You\'ve been given access to streaming on ZEN. '
          . $e($step) . ' with <strong style="color:#ffffff;">' . $e($email) . '</strong> and every title plays.</p>'
          . '<a href="' . $e($url) . '" style="display:inline-block;background:#e50914;color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 24px;border-radius:10px;">'
          . $e($step) . '</a>'
          . '<p style="margin:24px 0 0;font-size:12px;color:#8b929c;">Use this same email address, or access won\'t apply.</p>'
          . '</div></div>';

    return ['url' => $url, 'text' => $text, 'subject' => "You're invited to watch on ZEN", 'html' => $html];
}

function playbackHasAccount(PDO $conn, string $email): bool
{
    $stmt = $conn->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    return (bool) $stmt->fetchColumn();
}

// ------------------------------------------------------------------ actions --
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $flash = ['danger', 'Your session expired. Please try again.'];

    if (hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        try {
            switch ($_POST['action']) {
                case 'save_mode':
                    $mode = $_POST['mode'] ?? '';
                    if (!in_array($mode, [PLAYBACK_DISCOVER, PLAYBACK_SERVERS], true)) {
                        $flash = ['danger', 'Pick a mode.'];
                        break;
                    }
                    saveSiteSetting($conn, 'playback_mode', $mode, $adminId);
                    $flash = ['success', $mode === PLAYBACK_SERVERS
                        ? 'Streaming servers are now on for every visitor.'
                        : 'Discover mode is now on for every visitor.'];
                    break;

                case 'preview':
                    $preview = $_POST['preview'] ?? '';
                    if (in_array($preview, [PLAYBACK_DISCOVER, PLAYBACK_SERVERS], true)) {
                        $_SESSION['playback_preview'] = $preview;
                        $flash = ['success', 'You now see ' . ($preview === PLAYBACK_SERVERS ? 'Streaming servers' : 'Discover')
                            . ' mode. Nobody else is affected.'];
                    } else {
                        unset($_SESSION['playback_preview']);
                        $flash = ['success', 'Preview off. You see what visitors see.'];
                    }
                    break;

                case 'save_wtw':
                    $region = strtoupper(trim($_POST['default_region'] ?? ''));
                    $tag = trim($_POST['amazon_tag'] ?? '');
                    if (!preg_match('/^[A-Z]{2}$/', $region)) {
                        $flash = ['danger', 'Pick a default country.'];
                    } elseif ($tag !== '' && !preg_match('/^[A-Za-z0-9-]{2,64}$/', $tag)) {
                        $flash = ['danger', 'An Amazon Associates tag only has letters, numbers and dashes (like zenmovies-20).'];
                    } else {
                        saveSiteSetting($conn, 'wtw_default_region', $region, $adminId);
                        saveSiteSetting($conn, 'amazon_tag', $tag, $adminId);
                        $flash = ['success', 'Where to watch settings saved.'];
                    }
                    break;

                case 'grant_access':
                    $email = strtolower(trim($_POST['email'] ?? ''));
                    $note = trim($_POST['note'] ?? '');
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 191) {
                        $flash = ['danger', 'Enter a valid email address.'];
                        break;
                    }
                    ensurePlaybackAccessTable($conn);
                    $conn->prepare("INSERT INTO playback_access (email, note, granted_by) VALUES (?, ?, ?)
                                    ON DUPLICATE KEY UPDATE note = VALUES(note)")
                         ->execute([$email, $note !== '' ? mb_substr($note, 0, 255) : null, $adminId]);
                    $flash = ['success', "Access given to $email. They get Streaming servers whenever they're signed in with that email."];

                    if (!empty($_POST['send_invite'])) {
                        require_once APP_PATH . '/lib/mailer.php';
                        $invite = playbackInvite($email, playbackHasAccount($conn, $email));
                        $mailError = null;
                        $flash = sendSiteMail($email, $invite['subject'], $invite['html'], $invite['text'], $mailError)
                            ? ['success', "Access given to $email, and the invite email has been sent."]
                            : ['warning', "Access given to $email, but the invite email couldn't be sent: " . rtrim((string) $mailError, '. ') . '. Use Copy invite below to send it yourself.'];
                    }
                    break;

                case 'revoke_access':
                    ensurePlaybackAccessTable($conn);
                    $conn->prepare("DELETE FROM playback_access WHERE email = ?")->execute([strtolower(trim($_POST['email'] ?? ''))]);
                    $flash = ['success', 'Access removed. They get Discover again, like everyone else.'];
                    break;

            }
        } catch (PDOException $e) {
            error_log('Admin playback: ' . $e->getMessage());
            $flash = ['danger', 'Could not save: ' . $e->getMessage()];
        }
    }

    // Back to the page with a GET, so a refresh doesn't resubmit the form.
    $_SESSION['playback_flash'] = $flash;
    header('Location: /admin-playback');
    exit;
}

[$messageType, $message] = $_SESSION['playback_flash'] ?? ['', ''];
unset($_SESSION['playback_flash']);

// -------------------------------------------------------------------- data --
$mode = siteSetting('playback_mode') === PLAYBACK_SERVERS ? PLAYBACK_SERVERS : PLAYBACK_DISCOVER;
$preview = $_SESSION['playback_preview'] ?? '';
$defaultRegion = strtoupper(siteSetting('wtw_default_region', 'US'));
$amazonTag = siteSetting('amazon_tag', '');

$modeChanged = null;
try {
    $stmt = $conn->prepare("SELECT s.updated_at, u.username FROM site_settings s
                             LEFT JOIN users u ON u.id = s.updated_by WHERE s.setting_key = 'playback_mode'");
    $stmt->execute();
    $modeChanged = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (PDOException $e) {
    // Never saved yet.
}

$accessList = [];
$accessAccounts = [];
try {
    ensurePlaybackAccessTable($conn);
    $accessList = $conn->query("SELECT * FROM playback_access ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    if ($accessList) {
        $in = implode(',', array_fill(0, count($accessList), '?'));
        $stmt = $conn->prepare("SELECT email, username FROM users WHERE email IN ($in)");
        $stmt->execute(array_column($accessList, 'email'));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $account) {
            $accessAccounts[strtolower($account['email'])] = $account['username'];
        }
    }
} catch (PDOException $e) {
    error_log('Admin playback access: ' . $e->getMessage());
}
require_once APP_PATH . '/lib/mailer.php';
$canEmail = mailConfigured();

$playableCount = 0;
try {
    $playableCount = (int) $conn->query("SELECT COUNT(*) FROM media_sources WHERE COALESCE(check_status, '') <> 'unavailable'")->fetchColumn();
} catch (PDOException $e) {}

$regions = [];
$regionData = function_exists('fetchTmdbApi') ? fetchTmdbApi('watch/providers/regions', [], 604800) : null;
foreach ($regionData['results'] ?? [] as $r) {
    $regions[$r['iso_3166_1']] = $r['english_name'];
}
asort($regions);
if (!isset($regions[$defaultRegion])) {
    $regions = [$defaultRegion => $defaultRegion] + $regions;
}
?>

<style>
  .pb-modes { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px; }
  .pb-mode { position: relative; display: block; margin: 0; padding: 18px 18px 16px 52px; border: 1px solid var(--adm-border-strong, rgba(255,255,255,.14)); border-radius: var(--adm-radius, 14px); background: var(--adm-surface, rgba(255,255,255,.035)); cursor: pointer; transition: border-color .15s, background .15s; }
  .pb-mode:hover { border-color: rgba(255, 255, 255, .28); }
  .pb-mode input { position: absolute; left: 18px; top: 21px; width: 18px; height: 18px; accent-color: var(--primary, #e50914); }
  .pb-mode.is-selected { border-color: var(--primary, #e50914); background: rgba(var(--primary-rgb, 229, 9, 20), .07); box-shadow: 0 0 0 1px var(--primary, #e50914) inset; }
  .pb-mode h6 { margin: 0 0 6px; font-size: 1rem; font-weight: 700; color: var(--adm-text, #e9eaee) !important; }
  .pb-mode p { margin: 0; color: var(--adm-muted, #8b929c); font-size: .86rem; line-height: 1.55; }
  .pb-mode .pb-tag { display: inline-block; margin-left: 6px; padding: 1px 8px; border-radius: 999px; font-size: .7rem; font-weight: 600; vertical-align: middle; }
  .pb-tag.live { background: rgba(52, 211, 153, .16); color: var(--adm-green, #34d399); }
  .pb-tag.safe { background: rgba(34, 211, 238, .12); color: var(--adm-cyan, #22d3ee); }
  .pb-status { margin: 14px 0 0; color: var(--adm-muted, #8b929c); font-size: .84rem; }
  .pb-only-some { display: flex; align-items: baseline; gap: 8px; margin: 14px 0 0; padding: 10px 14px; border-radius: 10px; background: rgba(34, 211, 238, .07); border: 1px solid rgba(34, 211, 238, .22); color: var(--adm-text, #e9eaee); font-size: .86rem; line-height: 1.5; }
  .pb-only-some i { color: var(--adm-cyan, #22d3ee); }
  .pb-only-some a { color: var(--adm-cyan, #22d3ee) !important; font-weight: 600; }
  .pb-preview { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
  .pb-preview .btn.active { box-shadow: inset 0 0 0 2px var(--primary, #e50914); }
  .pb-sources td { vertical-align: middle; font-size: .86rem; }
  .pb-hint { color: var(--adm-muted, #8b929c); font-size: .8rem; margin-top: 4px; line-height: 1.5; }
  .pb-check { display: inline-flex; align-items: center; gap: 10px; margin: 0 0 4px; cursor: pointer; color: var(--adm-text, #e9eaee); font-size: .9rem; }
  .pb-check input { width: 17px; height: 17px; accent-color: var(--primary, #e50914); }
  .pb-check.is-disabled { opacity: .55; cursor: not-allowed; }
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
                    <div class="page-header-title"><h5>Playback &amp; access</h5></div>
                    <ul class="breadcrumb">
                      <li class="breadcrumb-item"><a href="/admin"><i class="feather icon-home"></i></a></li>
                      <li class="breadcrumb-item"><a href="#!">Playback</a></li>
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

            <!-- Mode -->
            <div class="card">
              <div class="card-header"><h5>What visitors see on the watch page</h5></div>
              <div class="card-body">
                <form method="POST" id="modeForm">
                  <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                  <input type="hidden" name="action" value="save_mode">
                  <div class="pb-modes">
                    <label class="pb-mode<?php echo $mode === PLAYBACK_DISCOVER ? ' is-selected' : ''; ?>">
                      <input type="radio" name="mode" value="<?php echo PLAYBACK_DISCOVER; ?>"<?php echo $mode === PLAYBACK_DISCOVER ? ' checked' : ''; ?>>
                      <h6>Discover
                        <?php if ($mode === PLAYBACK_DISCOVER): ?><span class="pb-tag live">Live</span><?php endif; ?>
                        <span class="pb-tag safe">Safe for payments &amp; ads</span>
                      </h6>
                      <p>The trailer and "Where to watch" links to Netflix, Prime Video and the other services that carry the title.
                         Free films (Admin &gt; Free films) play in full.</p>
                    </label>
                    <label class="pb-mode<?php echo $mode === PLAYBACK_SERVERS ? ' is-selected' : ''; ?>">
                      <input type="radio" name="mode" value="<?php echo PLAYBACK_SERVERS; ?>"<?php echo $mode === PLAYBACK_SERVERS ? ' checked' : ''; ?>>
                      <h6>Streaming servers
                        <?php if ($mode === PLAYBACK_SERVERS): ?><span class="pb-tag live">Live</span><?php endif; ?>
                      </h6>
                      <p>The third-party servers (Vidsrc, Vidlink, AutoEmbed, Vidbinge, Smashy) for every title, as before.
                         They show films without the studios' permission, so Paystack and ad networks can close your accounts over it.</p>
                    </label>
                  </div>
                  <p class="pb-only-some">
                    <i class="feather icon-users"></i>
                    Only for specific accounts? Keep <strong>Discover</strong> and add them under
                    <a href="#access">Access by email</a>, or switch on <strong>Streaming access</strong> for a member in <a href="/admin-view-users">Members</a>.
                  </p>
                  <p class="pb-status">
                    <?php if ($modeChanged): ?>
                      Last changed <?php echo htmlspecialchars(date('j M Y, H:i', strtotime($modeChanged['updated_at']))); ?><?php echo $modeChanged['username'] ? ' by ' . htmlspecialchars($modeChanged['username']) : ''; ?>.
                    <?php else: ?>
                      Never changed: visitors get Discover.
                    <?php endif; ?>
                  </p>
                  <button type="submit" class="btn btn-primary mt-2">Save for everyone</button>
                </form>
              </div>
            </div>

            <!-- Preview -->
            <div class="card">
              <div class="card-header"><h5>Preview (only you)</h5></div>
              <div class="card-body">
                <p class="text-muted mb-3">Try a mode on the watch page before visitors get it. Only your browser changes, and a yellow "Preview" label shows on the watch page while it's on.</p>
                <form method="POST" class="pb-preview">
                  <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                  <input type="hidden" name="action" value="preview">
                  <button type="submit" name="preview" value="" class="btn btn-outline-secondary<?php echo $preview === '' ? ' active' : ''; ?>">Same as visitors</button>
                  <button type="submit" name="preview" value="<?php echo PLAYBACK_DISCOVER; ?>" class="btn btn-outline-secondary<?php echo $preview === PLAYBACK_DISCOVER ? ' active' : ''; ?>">Discover</button>
                  <button type="submit" name="preview" value="<?php echo PLAYBACK_SERVERS; ?>" class="btn btn-outline-secondary<?php echo $preview === PLAYBACK_SERVERS ? ' active' : ''; ?>">Streaming servers</button>
                  <a href="/watch?id=603&amp;type=movie" target="_blank" rel="noopener" class="btn btn-link">Open a watch page <i class="feather icon-external-link"></i></a>
                </form>
              </div>
            </div>

            <!-- Access by email -->
            <div class="card" id="access">
              <div class="card-header"><h5>Access by email</h5></div>
              <div class="card-body">
                <p class="text-muted mb-3">People on this list get Streaming servers whenever they're signed in with that email. Everyone else keeps what the site is set to.</p>
                <?php if ($mode === PLAYBACK_SERVERS): ?>
                <div class="alert alert-info">Streaming servers are on for everyone right now, so this list changes nothing until the site is back on Discover.</div>
                <?php endif; ?>

                <form method="POST" class="row" id="accessForm">
                  <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                  <input type="hidden" name="action" value="grant_access">
                  <div class="col-md-5 form-group">
                    <label for="access_email">Email address</label>
                    <input type="email" id="access_email" name="email" maxlength="191" class="form-control" placeholder="friend@example.com" required>
                  </div>
                  <div class="col-md-4 form-group">
                    <label for="access_note">Note <span class="text-muted">(optional)</span></label>
                    <input type="text" id="access_note" name="note" maxlength="255" class="form-control" placeholder="Beta tester">
                  </div>
                  <div class="col-md-3 form-group">
                    <label class="d-none d-md-block">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">Give access</button>
                  </div>
                  <div class="col-12">
                    <label class="pb-check<?php echo $canEmail ? '' : ' is-disabled'; ?>">
                      <input type="checkbox" name="send_invite" value="1"<?php echo $canEmail ? ' checked' : ' disabled'; ?>>
                      <span>Email them an invite</span>
                    </label>
                    <div class="pb-hint">
                      <?php if ($canEmail): ?>
                        Sent from <?php echo htmlspecialchars(getenv('MAIL_FROM')); ?>. It tells them to sign in, or create an account, with this email.
                      <?php else: ?>
                        Email isn't set up on the server yet, so use <strong>Copy invite</strong> and send it yourself (WhatsApp, email…).
                        To send invites from here, add <code>MAIL_FROM</code> (e.g. a Gmail address) and <code>EMAIL_PASSWORD</code> (a Gmail App Password) under Render &gt; Environment.
                      <?php endif; ?>
                    </div>
                  </div>
                </form>

                <?php if ($accessList): ?>
                <div class="table-responsive mt-3">
                  <table class="table table-hover pb-sources">
                    <thead><tr><th>Email</th><th>Account</th><th>Added</th><th>Last used</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($accessList as $a):
                        $account = $accessAccounts[strtolower($a['email'])] ?? null;
                        $invite = playbackInvite($a['email'], $account !== null);
                    ?>
                      <tr>
                        <td>
                          <?php echo htmlspecialchars($a['email']); ?>
                          <?php if (!empty($a['note'])): ?><div class="text-muted" style="font-size:.76rem;"><?php echo htmlspecialchars($a['note']); ?></div><?php endif; ?>
                        </td>
                        <td><?php echo $account !== null
                            ? '<span class="badge badge-success">' . htmlspecialchars($account) . '</span>'
                            : '<span class="badge badge-secondary">No account yet</span>'; ?></td>
                        <td class="text-muted"><?php echo htmlspecialchars(date('j M Y', strtotime($a['created_at']))); ?></td>
                        <td class="text-muted"><?php echo $a['last_used_at'] ? htmlspecialchars(date('j M Y, H:i', strtotime($a['last_used_at']))) : 'Not yet'; ?></td>
                        <td class="text-right" style="white-space:nowrap;">
                          <button type="button" class="btn btn-sm btn-outline-secondary pb-copy" data-invite="<?php echo htmlspecialchars($invite['text']); ?>">Copy invite</button>
                          <form method="POST" class="d-inline" onsubmit="return confirm('Remove access for <?php echo htmlspecialchars(addslashes($a['email'])); ?>?')">
                            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                            <input type="hidden" name="action" value="revoke_access">
                            <input type="hidden" name="email" value="<?php echo htmlspecialchars($a['email']); ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                <?php else: ?>
                <p class="text-muted mb-0 mt-2">Nobody yet.</p>
                <?php endif; ?>
                <p class="pb-hint mt-3 mb-0">Sign-up doesn't confirm email addresses, so whoever creates an account with a listed address first gets access. It's safest for people who already have an account.</p>
              </div>
            </div>

            <!-- Where to watch -->
            <div class="card">
              <div class="card-header"><h5>Where to watch</h5></div>
              <div class="card-body">
                <form method="POST" class="row">
                  <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                  <input type="hidden" name="action" value="save_wtw">
                  <div class="col-md-5 form-group">
                    <label for="default_region">Default country</label>
                    <select id="default_region" name="default_region" class="form-control">
                      <?php foreach ($regions as $code => $name): ?>
                      <option value="<?php echo htmlspecialchars($code); ?>"<?php echo $code === $defaultRegion ? ' selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
                      <?php endforeach; ?>
                    </select>
                    <div class="pb-hint">Visitors see services in their own country (from their browser's language, or the country they pick on the page).
                      When nothing is listed there, this country is shown instead. Coverage is thin for some countries, including Nigeria.</div>
                  </div>
                  <div class="col-md-5 form-group">
                    <label for="amazon_tag">Amazon Associates tag <span class="text-muted">(optional)</span></label>
                    <input type="text" id="amazon_tag" name="amazon_tag" class="form-control" placeholder="yourname-20" value="<?php echo htmlspecialchars($amazonTag); ?>">
                    <div class="pb-hint">Added to Prime Video and Amazon links for US visitors, so rentals and purchases earn you a commission.
                      Sign up at affiliate-program.amazon.com.</div>
                  </div>
                  <div class="col-md-2 form-group">
                    <label class="d-none d-md-block">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">Save</button>
                  </div>
                </form>
                <p class="text-muted mb-0" style="font-size:.8rem;">The service data comes from JustWatch through TMDB, and the page credits JustWatch as their terms require.
                  Earning from TMDB data needs a commercial agreement with TMDB (themoviedb.org/api-terms-of-use).</p>
              </div>
            </div>

            <!-- Free films -->
            <div class="card">
              <div class="card-header"><h5>Films that play in full</h5></div>
              <div class="card-body d-flex flex-wrap align-items-center justify-content-between" style="gap:12px;">
                <p class="text-muted mb-0">
                  <?php echo $playableCount
                      ? number_format($playableCount) . ' ' . ($playableCount === 1 ? 'film plays' : 'films play') . ' in full on ZEN, in both modes.'
                      : 'No films play in full yet.'; ?>
                  Add official YouTube uploads and public-domain films from archive.org under Free films.
                </p>
                <a href="/admin-free-films" class="btn btn-primary">Open Free films</a>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  // Highlight the chosen mode, and ask before turning the servers on.
  (function () {
    var form = document.getElementById('modeForm');
    var live = <?php echo json_encode($mode); ?>;
    form.querySelectorAll('input[name="mode"]').forEach(function (input) {
      input.addEventListener('change', function () {
        form.querySelectorAll('.pb-mode').forEach(function (card) {
          card.classList.toggle('is-selected', card.contains(input));
        });
      });
    });
    form.addEventListener('submit', function (e) {
      var picked = form.querySelector('input[name="mode"]:checked');
      if (picked && picked.value === 'servers' && live !== 'servers'
          && !confirm('Turn the streaming servers on for every visitor?\n\nThey show films without the studios\' permission. Paystack and ad networks can close your accounts over it.')) {
        e.preventDefault();
      }
    });

    // Copy an invite to paste into WhatsApp, email and so on.
    document.querySelectorAll('.pb-copy').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var text = btn.dataset.invite;
        var done = function () {
          var label = btn.textContent;
          btn.textContent = 'Copied';
          setTimeout(function () { btn.textContent = label; }, 1800);
        };
        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(text).then(done, function () { window.prompt('Copy the invite:', text); });
        } else {
          window.prompt('Copy the invite:', text);
        }
      });
    });
  })();
</script>

  <script src="/da/assets/js/vendor-all.min.js"></script>
  <script src="/da/assets/plugins/bootstrap/js/bootstrap.min.js"></script>
  <script src="/da/assets/js/pcoded.min.js"></script>
  <script src="/da/assets/js/horizontal-menu.js"></script>
</body>
</html>
