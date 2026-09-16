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

/** media_sources predates this page; make sure it exists with a column for the rights. */
function playbackEnsureSourcesTable(PDO $conn): void
{
    $conn->exec("CREATE TABLE IF NOT EXISTS media_sources (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tmdb_id INT NOT NULL,
        media_type ENUM('movie','tv') NOT NULL DEFAULT 'movie',
        season INT DEFAULT 0,
        episode INT DEFAULT 0,
        video_url TEXT NOT NULL,
        is_embed TINYINT(1) DEFAULT 0,
        INDEX idx_media_sources_lookup (tmdb_id, media_type, season, episode)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $columns = $conn->query("SHOW COLUMNS FROM media_sources")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('rights_note', $columns, true)) {
        $conn->exec("ALTER TABLE media_sources ADD COLUMN rights_note VARCHAR(255) NULL");
    }
    if (!in_array('created_at', $columns, true)) {
        $conn->exec("ALTER TABLE media_sources ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP");
    }
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

                case 'add_source':
                    $tmdbId = (int) ($_POST['tmdb_id'] ?? 0);
                    $type = ($_POST['media_type'] ?? '') === 'tv' ? 'tv' : 'movie';
                    $season = $type === 'tv' ? max(0, (int) ($_POST['season'] ?? 0)) : 0;
                    $episode = $type === 'tv' ? max(0, (int) ($_POST['episode'] ?? 0)) : 0;
                    $url = trim($_POST['video_url'] ?? '');
                    $rights = trim($_POST['rights_note'] ?? '');

                    if ($tmdbId <= 0) {
                        $flash = ['danger', 'Enter the TMDB ID (the number in the title\'s TMDB address).'];
                    } elseif ($type === 'tv' && $episode < 1) {
                        $flash = ['danger', 'Enter the season and episode number.'];
                    } elseif (!filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https') {
                        $flash = ['danger', 'The video address must be a full https:// link.'];
                    } elseif ($rights === '') {
                        $flash = ['danger', 'Say what gives you the right to show it, e.g. "Public domain" or "Licence from the producer, 2026".'];
                    } else {
                        playbackEnsureSourcesTable($conn);
                        $conn->prepare("INSERT INTO media_sources (tmdb_id, media_type, season, episode, video_url, is_embed, rights_note)
                                        VALUES (?, ?, ?, ?, ?, ?, ?)")
                             ->execute([$tmdbId, $type, $season, $episode, $url, isDirectVideoUrl($url) ? 0 : 1, mb_substr($rights, 0, 255)]);
                        $flash = ['success', 'Added. It now plays on its watch page in both modes.'];
                    }
                    break;

                case 'delete_source':
                    playbackEnsureSourcesTable($conn);
                    $conn->prepare("DELETE FROM media_sources WHERE id = ?")->execute([(int) ($_POST['source_id'] ?? 0)]);
                    $flash = ['success', 'Removed.'];
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

$sources = [];
try {
    playbackEnsureSourcesTable($conn);
    $sources = $conn->query("SELECT * FROM media_sources ORDER BY id DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Admin playback sources: ' . $e->getMessage());
}

$fileCount = 0;
try {
    $fileCount = (int) $conn->query("SELECT COUNT(*) FROM media_downloads WHERE is_active = 1
                                      AND download_url REGEXP '\\\\.(mp4|mkv|webm|m3u8)(\\\\?|$)'")->fetchColumn();
} catch (PDOException $e) {}

// Titles for the list, fetched together (and cached) from TMDB.
if ($sources && function_exists('prefetchTmdbApi')) {
    prefetchTmdbApi(array_map(fn($s) => ["{$s['media_type']}/{$s['tmdb_id']}", []], $sources));
}
$titleOf = function (array $s): string {
    $d = function_exists('fetchTmdbApi') ? fetchTmdbApi("{$s['media_type']}/{$s['tmdb_id']}") : null;
    return $d['title'] ?? $d['name'] ?? ('TMDB ' . $s['tmdb_id']);
};

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
  .pb-preview { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
  .pb-preview .btn.active { box-shadow: inset 0 0 0 2px var(--primary, #e50914); }
  .pb-sources td { vertical-align: middle; font-size: .86rem; }
  .pb-url { max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: block; }
  .pb-hint { color: var(--adm-muted, #8b929c); font-size: .8rem; margin-top: 4px; line-height: 1.5; }
  .pb-tv-only[hidden] { display: none !important; }
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
                    <div class="page-header-title"><h5>Playback &amp; Where to Watch</h5></div>
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
                         Titles you have the rights to (below) play in full.</p>
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

            <!-- Titles you can play -->
            <div class="card">
              <div class="card-header"><h5>Titles you can play</h5></div>
              <div class="card-body">
                <p class="text-muted">Videos you have the rights to show: public-domain and Creative Commons films, your own productions,
                  or films a producer has licensed to you. They play in full on their watch page in both modes.
                  <?php if ($fileCount): ?>
                    <?php echo number_format($fileCount); ?> video file<?php echo $fileCount === 1 ? '' : 's'; ?> from <a href="/admin-view-downloads">Download Links</a> and the <a href="/admin-ingestion">Ingestion Queue</a> also play.
                  <?php else: ?>
                    Video files published through <a href="/admin-ingestion">the Ingestion Queue</a> and <a href="/admin-view-downloads">Download Links</a> play automatically too.
                  <?php endif; ?>
                </p>

                <form method="POST" class="row" id="addSourceForm">
                  <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                  <input type="hidden" name="action" value="add_source">
                  <div class="col-md-3 form-group">
                    <label for="src_type">Type</label>
                    <select id="src_type" name="media_type" class="form-control">
                      <option value="movie">Movie</option>
                      <option value="tv">TV episode</option>
                    </select>
                  </div>
                  <div class="col-md-3 form-group">
                    <label for="src_tmdb">TMDB ID</label>
                    <input type="number" min="1" id="src_tmdb" name="tmdb_id" class="form-control" placeholder="3085" required>
                  </div>
                  <div class="col-md-3 form-group pb-tv-only" hidden>
                    <label for="src_season">Season</label>
                    <input type="number" min="0" id="src_season" name="season" class="form-control" value="1">
                  </div>
                  <div class="col-md-3 form-group pb-tv-only" hidden>
                    <label for="src_episode">Episode</label>
                    <input type="number" min="1" id="src_episode" name="episode" class="form-control" value="1">
                  </div>
                  <div class="w-100"></div>
                  <div class="col-md-5 form-group">
                    <label for="src_url">Video address</label>
                    <input type="url" id="src_url" name="video_url" class="form-control" placeholder="https://archive.org/embed/his_girl_friday" required>
                    <div class="pb-hint">A video file (.mp4, .webm, .m3u8) or an embed page such as archive.org/embed/… or an official YouTube embed.</div>
                  </div>
                  <div class="col-md-5 form-group">
                    <label for="src_rights">Your right to show it</label>
                    <input type="text" id="src_rights" name="rights_note" maxlength="255" class="form-control" placeholder="Public domain (1940)" required>
                  </div>
                  <div class="col-md-2 form-group">
                    <label class="d-none d-md-block">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">Add title</button>
                  </div>
                </form>

                <?php if ($sources): ?>
                <div class="table-responsive mt-3">
                  <table class="table table-hover pb-sources">
                    <thead><tr><th>Title</th><th>Video</th><th>Rights</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($sources as $s):
                        $watchUrl = '/watch?id=' . (int) $s['tmdb_id'] . '&type=' . $s['media_type']
                            . ($s['media_type'] === 'tv' ? '&season=' . (int) $s['season'] . '&episode=' . (int) $s['episode'] : '');
                    ?>
                      <tr>
                        <td>
                          <a href="<?php echo htmlspecialchars($watchUrl); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($titleOf($s)); ?></a>
                          <div class="text-muted" style="font-size:.76rem;">
                            <?php echo $s['media_type'] === 'tv' ? 'S' . (int) $s['season'] . ' E' . (int) $s['episode'] . ' · ' : 'Movie · '; ?>TMDB <?php echo (int) $s['tmdb_id']; ?>
                          </div>
                        </td>
                        <td><span class="pb-url" title="<?php echo htmlspecialchars($s['video_url']); ?>"><?php echo htmlspecialchars($s['video_url']); ?></span></td>
                        <td><?php echo htmlspecialchars($s['rights_note'] ?? '') ?: '<span class="text-danger">Not recorded</span>'; ?></td>
                        <td class="text-right">
                          <form method="POST" class="d-inline" onsubmit="return confirm('Stop playing this video on the site?')">
                            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                            <input type="hidden" name="action" value="delete_source">
                            <input type="hidden" name="source_id" value="<?php echo (int) $s['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                <?php else: ?>
                <p class="text-muted mb-0 mt-2">None yet.</p>
                <?php endif; ?>
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

    // Season and episode only apply to TV.
    var type = document.getElementById('src_type');
    function syncType() {
      document.querySelectorAll('.pb-tv-only').forEach(function (el) { el.hidden = type.value !== 'tv'; });
    }
    type.addEventListener('change', syncType);
    syncType();
  })();
</script>

  <script src="/da/assets/js/vendor-all.min.js"></script>
  <script src="/da/assets/plugins/bootstrap/js/bootstrap.min.js"></script>
  <script src="/da/assets/js/pcoded.min.js"></script>
  <script src="/da/assets/js/horizontal-menu.js"></script>
</body>
</html>
