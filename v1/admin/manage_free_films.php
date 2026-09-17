<?php
ob_start();
$level_check = ['MASTER', 3, 2, 1];
$adminPageTitle = 'Free films';
include 'includes/header.php';
require_once APP_PATH . '/lib/free_films.php';

// ==========================================
// FREE FILMS
// ==========================================
// Paste a link to an official YouTube upload or an archive.org film, pick the
// title it belongs to, and it plays in full on that title's watch page (in
// both playback modes). See lib/free_films.php.

if (empty($_SESSION['free_films_csrf'])) {
    $_SESSION['free_films_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['free_films_csrf'];
$adminId = isset($_SESSION['admin_id']) && is_numeric($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;

try {
    ensureMediaSourcesTable($conn);
} catch (PDOException $e) {
    error_log('Free films table: ' . $e->getMessage());
}

$watchUrlFor = fn(array $s) => '/watch?id=' . (int) $s['tmdb_id'] . '&type=' . $s['media_type']
    . ($s['media_type'] === 'tv' ? '&season=' . (int) $s['season'] . '&episode=' . (int) $s['episode'] : '');

// ------------------------------------------------------------------ actions --
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $flash = ['danger', 'Your session expired. Please try again.'];
    $anchor = '';
    set_time_limit(120);

    if (hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        try {
            switch ($_POST['action']) {
                case 'add':
                    // Everything is checked again here rather than trusted from the preview.
                    $film = freeFilmLookup((string) ($_POST['link'] ?? ''));
                    $type = ($_POST['type'] ?? '') === 'tv' ? 'tv' : 'movie';
                    $pick = (string) ($_POST['tmdb_pick'] ?? '');
                    $tmdbId = $pick === 'manual' ? (int) ($_POST['tmdb_manual'] ?? 0) : (int) $pick;
                    $season = $type === 'tv' ? max(0, (int) ($_POST['season'] ?? 1)) : 0;
                    $episode = $type === 'tv' ? max(0, (int) ($_POST['episode'] ?? 1)) : 0;
                    $rights = trim((string) ($_POST['rights_note'] ?? ''));
                    $tmdb = $tmdbId > 0 ? fetchTmdbApi("$type/$tmdbId") : null;
                    $tmdbTitle = $tmdb['title'] ?? $tmdb['name'] ?? '';

                    if (!$film['ok']) {
                        $flash = ['danger', $film['error']];
                    } elseif ($tmdbId <= 0) {
                        $flash = ['danger', 'Pick the title this film is, or enter its TMDB ID.'];
                    } elseif (!$tmdb) {
                        $flash = ['danger', "TMDB has no " . ($type === 'tv' ? 'TV show' : 'movie') . " with ID $tmdbId."];
                    } elseif ($type === 'tv' && $episode < 1) {
                        $flash = ['danger', 'Enter the season and episode this video is.'];
                    } elseif (empty($_POST['confirm_rights'])) {
                        $flash = ['danger', 'Tick the box to confirm the film may be shown.'];
                    } elseif ($rights === '') {
                        $flash = ['danger', 'Say why the film may be shown (the rights note).'];
                    } else {
                        $dupe = $conn->prepare("SELECT id FROM media_sources WHERE tmdb_id = ? AND media_type = ? AND season = ? AND episode = ? AND video_url = ? LIMIT 1");
                        $dupe->execute([$tmdbId, $type, $season, $episode, $film['embed_url']]);
                        if ($existing = $dupe->fetchColumn()) {
                            $flash = ['info', "That video is already added to $tmdbTitle."];
                            $anchor = '#film-' . $existing;
                            break;
                        }
                        $conn->prepare("INSERT INTO media_sources
                                (tmdb_id, media_type, season, episode, video_url, is_embed, rights_note,
                                 source, source_url, source_title, source_owner, checked_at, check_status)
                                VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, NOW(), 'ok')")
                             ->execute([
                                 $tmdbId, $type, $season, $episode, $film['embed_url'], mb_substr($rights, 0, 255),
                                 $film['source'], mb_substr($film['watch_url'], 0, 500), mb_substr($film['title'], 0, 255), mb_substr($film['owner'], 0, 255),
                             ]);
                        $anchor = '#film-' . $conn->lastInsertId();
                        $flash = ['success', "Added. $tmdbTitle" . ($type === 'tv' ? " S{$season} E{$episode}" : '') . ' now plays in full on ZEN.'];
                    }
                    break;

                case 'remove':
                    $conn->prepare("DELETE FROM media_sources WHERE id = ?")->execute([(int) ($_POST['film_id'] ?? 0)]);
                    $flash = ['success', 'Removed. That title no longer plays on ZEN.'];
                    break;

                case 'check':
                case 'check_all':
                    $query = $_POST['action'] === 'check'
                        ? $conn->prepare("SELECT * FROM media_sources WHERE id = ?")
                        : $conn->prepare("SELECT * FROM media_sources ORDER BY checked_at IS NOT NULL, checked_at LIMIT 40");
                    $query->execute($_POST['action'] === 'check' ? [(int) ($_POST['film_id'] ?? 0)] : []);
                    $checked = $gone = $unknown = 0;
                    $started = time();
                    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        if (time() - $started > 60) break;
                        $available = freeFilmStillAvailable((string) ($row['source_url'] ?: $row['video_url']));
                        if ($available === null) {
                            $unknown++;
                            continue;
                        }
                        $conn->prepare("UPDATE media_sources SET checked_at = NOW(), check_status = ? WHERE id = ?")
                             ->execute([$available ? 'ok' : 'unavailable', $row['id']]);
                        $checked++;
                        $gone += $available ? 0 : 1;
                        $anchor = $_POST['action'] === 'check' ? '#film-' . $row['id'] : '';
                    }
                    if ($_POST['action'] === 'check') {
                        $flash = $checked
                            ? ($gone ? ['warning', "That video is no longer available, so it's hidden from the watch page."] : ['success', 'Still plays.'])
                            : ['warning', "Couldn't check that link right now."];
                    } else {
                        $flash = [$gone ? 'warning' : 'success', "Checked $checked " . ($checked === 1 ? 'film' : 'films')
                            . ($gone ? ": $gone no longer " . ($gone === 1 ? 'plays and is' : 'play and are') . ' hidden from the watch page.' : ': all still play.')
                            . ($unknown ? " $unknown couldn't be checked." : '')];
                    }
                    break;
            }
        } catch (PDOException $e) {
            error_log('Free films: ' . $e->getMessage());
            $flash = ['danger', "Couldn't save that change. Please try again."];
        }
    }

    $_SESSION['free_films_flash'] = $flash;
    header('Location: /admin-free-films' . $anchor);
    exit;
}

[$messageType, $message] = $_SESSION['free_films_flash'] ?? ['', ''];
unset($_SESSION['free_films_flash']);

// ------------------------------------------------------------------ preview --
$link = trim((string) ($_GET['link'] ?? ''));
$type = ($_GET['type'] ?? '') === 'tv' ? 'tv' : 'movie';
$search = trim((string) ($_GET['q'] ?? ''));
$film = $link !== '' ? freeFilmLookup($link) : null;
$candidates = [];
if ($film && $film['ok']) {
    $candidates = freeFilmCandidates($search !== '' ? $search : $film['title'], $search !== '' ? null : $film['year'], $type);
}
$previewQuery = fn(array $change) => '/admin-free-films?' . http_build_query(array_filter(array_merge(['link' => $link, 'type' => $type, 'q' => $search], $change), fn($v) => $v !== '' && $v !== 'movie'));

// ------------------------------------------------------------------ library --
$films = [];
try {
    $films = $conn->query("SELECT * FROM media_sources ORDER BY id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Free films list: ' . $e->getMessage());
}
if ($films && function_exists('prefetchTmdbApi')) {
    prefetchTmdbApi(array_map(fn($f) => ["{$f['media_type']}/{$f['tmdb_id']}", []], $films));
}
$playing = count(array_filter($films, fn($f) => ($f['check_status'] ?? '') !== 'unavailable'));
$unavailable = count($films) - $playing;
$sourceLabel = ['youtube' => 'YouTube', 'archive' => 'archive.org'];
?>

<style>
  .ff-steps { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin: 0 0 16px; padding: 0; list-style: none; }
  .ff-steps li { display: flex; gap: 10px; align-items: flex-start; padding: 12px 14px; border: 1px solid var(--adm-border); border-radius: 12px; background: var(--adm-surface); color: var(--adm-muted); font-size: .84rem; line-height: 1.45; }
  .ff-steps b { color: var(--adm-text); display: block; }
  .ff-steps i { font-size: 1.3rem; color: var(--primary); flex-shrink: 0; margin-top: 1px; }
  @media (max-width: 767.98px) { .ff-steps { grid-template-columns: 1fr; } }

  .ff-lookup { display: flex; gap: 10px; flex-wrap: wrap; margin: 0; }
  .ff-lookup input { flex: 1 1 320px; }
  .ff-hint { color: var(--adm-muted); font-size: .8rem; margin: 8px 0 0; line-height: 1.5; }

  .ff-preview { display: grid; grid-template-columns: minmax(0, 320px) minmax(0, 1fr); gap: 20px; align-items: start; }
  @media (max-width: 767.98px) { .ff-preview { grid-template-columns: 1fr; } }
  .ff-thumb { position: relative; aspect-ratio: 16 / 9; border-radius: 12px; overflow: hidden; background: #000; }
  .ff-thumb img { width: 100%; height: 100%; object-fit: cover; }
  .ff-source { position: absolute; left: 10px; top: 10px; padding: 3px 9px; border-radius: 999px; background: rgba(0,0,0,.72); color: #fff; font-size: .72rem; font-weight: 700; }
  .ff-title { margin: 0 0 6px; font-size: 1.1rem; font-weight: 700; color: var(--adm-text) !important; line-height: 1.35; }
  .ff-facts { color: var(--adm-muted); font-size: .84rem; margin: 0 0 10px; }
  .ff-facts a { color: var(--adm-cyan) !important; }
  .ff-rights { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: .78rem; font-weight: 700; margin-bottom: 10px; }
  .ff-rights.ok { color: var(--adm-green); background: rgba(52,211,153,.13); }
  .ff-rights.check { color: var(--adm-amber); background: rgba(251,191,36,.13); }
  .ff-warnings { margin: 0; padding-left: 18px; color: var(--adm-amber); font-size: .84rem; line-height: 1.55; }

  .ff-section { margin: 22px 0 10px; font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--adm-dim); }
  .ff-type { display: inline-flex; border: 1px solid var(--adm-border); border-radius: 999px; overflow: hidden; margin-bottom: 12px; }
  .ff-type a { padding: 6px 14px; font-size: .8rem; font-weight: 600; color: var(--adm-muted) !important; text-decoration: none !important; }
  .ff-type a.is-active { background: rgba(var(--primary-rgb), .16); color: #fff !important; }
  .ff-search { display: flex; gap: 8px; margin: 0 0 12px; max-width: 480px; }
  .ff-candidates { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px; }
  .ff-candidate { position: relative; display: flex; gap: 12px; align-items: center; margin: 0; padding: 10px; border: 1px solid var(--adm-border); border-radius: 12px; background: var(--adm-surface); cursor: pointer; transition: border-color .15s, background .15s; }
  .ff-candidate:hover { border-color: var(--adm-border-strong); }
  .ff-candidate:has(input:checked) { border-color: var(--primary); background: rgba(var(--primary-rgb), .08); }
  .ff-candidate input[type=radio] { position: absolute; opacity: 0; pointer-events: none; }
  .ff-candidate img, .ff-noposter { width: 46px; height: 69px; border-radius: 6px; object-fit: cover; flex-shrink: 0; background: rgba(255,255,255,.06); }
  .ff-candidate b { display: block; color: var(--adm-text); font-size: .88rem; line-height: 1.3; }
  .ff-candidate small { color: var(--adm-muted); font-size: .76rem; }
  .ff-best { display: inline-block; margin-top: 4px; padding: 1px 7px; border-radius: 999px; background: rgba(52,211,153,.13); color: var(--adm-green); font-size: .66rem; font-weight: 700; }
  .ff-manual input[type=number] { width: 120px; height: 30px; margin-top: 4px; padding: 2px 8px !important; }
  .ff-tv { display: flex; gap: 10px; margin-top: 12px; }
  .ff-tv .form-group { margin: 0; width: 120px; }
  .ff-confirm { display: flex; gap: 10px; align-items: flex-start; margin: 16px 0 4px; color: var(--adm-text); font-size: .9rem; cursor: pointer; }
  .ff-confirm input { width: 18px; height: 18px; margin-top: 2px; accent-color: var(--primary); flex-shrink: 0; }

  .ff-list { list-style: none; margin: 0; padding: 0; }
  .ff-row { display: grid; grid-template-columns: 46px minmax(0, 1.4fr) minmax(0, 1fr) auto; gap: 14px; align-items: center; padding: 12px 0; scroll-margin-top: 90px; }
  .ff-row + .ff-row { border-top: 1px solid var(--adm-border); }
  .ff-row:target { background: rgba(var(--primary-rgb), .06); }
  .ff-row img, .ff-row .ff-noposter { width: 46px; height: 69px; }
  .ff-row .ff-name { display: block; color: var(--adm-text) !important; font-weight: 600; font-size: .92rem; text-decoration: none !important; }
  .ff-row .ff-name:hover { text-decoration: underline !important; }
  .ff-row .ff-sub, .ff-row .ff-rights-note { display: block; color: var(--adm-muted); font-size: .78rem; line-height: 1.45; }
  .ff-status { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: .68rem; font-weight: 700; margin-top: 4px; }
  .ff-status.ok { color: var(--adm-green); background: rgba(52,211,153,.13); }
  .ff-status.gone { color: var(--adm-red); background: rgba(248,113,113,.14); }
  .ff-status.new { color: var(--adm-muted); background: rgba(255,255,255,.06); }
  .ff-actions { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
  .ff-actions form { margin: 0; }
  .ff-actions .btn { padding: 4px 10px; font-size: .76rem; }
  @media (max-width: 767.98px) {
    .ff-row { grid-template-columns: 46px minmax(0, 1fr); }
    .ff-row > .ff-rights-col, .ff-row > .ff-actions { grid-column: 2; justify-content: flex-start; }
  }
</style>

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
                    <div class="page-header-title"><h5>Free films</h5></div>
                    <ul class="breadcrumb">
                      <li class="breadcrumb-item"><a href="/admin"><i class="feather icon-home"></i></a></li>
                      <li class="breadcrumb-item"><a href="#!">Free films</a></li>
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

            <ul class="ff-steps">
              <li><i class="ph ph-link"></i><span><b>Paste a link</b>A full film on YouTube, uploaded by its studio, distributor or filmmaker, or a public-domain film on archive.org.</span></li>
              <li><i class="ph ph-magnifying-glass"></i><span><b>Pick the title</b>ZEN reads the video and suggests which movie or show it is.</span></li>
              <li><i class="ph ph-play-circle"></i><span><b>It plays on ZEN</b>The title's Play button plays it in full, for everyone, in both playback modes.</span></li>
            </ul>

            <!-- Step 1 -->
            <div class="card">
              <div class="card-header"><h5>Add a free film</h5></div>
              <div class="card-body">
                <form method="GET" action="/admin-free-films" class="ff-lookup">
                  <input type="url" name="link" class="form-control" placeholder="https://www.youtube.com/watch?v=…  or  https://archive.org/details/…" value="<?php echo htmlspecialchars($link); ?>" required aria-label="Video link">
                  <?php if ($type === 'tv'): ?><input type="hidden" name="type" value="tv"><?php endif; ?>
                  <button type="submit" class="btn btn-primary">Look up</button>
                </form>
                <p class="ff-hint">YouTube films play in YouTube's own player, so the channel keeps its views and ad money. Never add a film someone else re-uploaded; that's piracy even on YouTube.</p>

                <?php if ($film && !$film['ok']): ?>
                <div class="alert alert-danger mt-3 mb-0"><?php echo htmlspecialchars($film['error']); ?></div>
                <?php elseif ($film): ?>
                <hr style="border-color: var(--adm-border); margin: 20px 0;">

                <div class="ff-preview">
                  <div class="ff-thumb">
                    <img src="<?php echo htmlspecialchars($film['thumbnail']); ?>" alt="">
                    <span class="ff-source"><?php echo $sourceLabel[$film['source']]; ?></span>
                  </div>
                  <div>
                    <p class="ff-title"><?php echo htmlspecialchars($film['title']); ?></p>
                    <?php
                    $facts = [];
                    if ($film['owner']) {
                        $facts[] = ($film['source'] === 'youtube' ? 'Channel' : 'By') . ': <a href="' . htmlspecialchars($film['owner_url']) . '" target="_blank" rel="noopener">' . htmlspecialchars($film['owner']) . '</a>';
                    }
                    if ($film['minutes']) {
                        $facts[] = (intdiv($film['minutes'], 60) ? intdiv($film['minutes'], 60) . 'h ' : '') . ($film['minutes'] % 60) . 'm';
                    }
                    if ($film['year']) {
                        $facts[] = (int) $film['year'];
                    }
                    $facts[] = '<a href="' . htmlspecialchars($film['watch_url']) . '" target="_blank" rel="noopener">Open original</a>';
                    ?>
                    <p class="ff-facts"><?php echo implode(' · ', $facts); ?></p>
                    <span class="ff-rights <?php echo $film['rights']['status']; ?>">
                      <i class="ph <?php echo $film['rights']['status'] === 'ok' ? 'ph-check-circle' : 'ph-warning'; ?>"></i>
                      <?php echo $film['rights']['status'] === 'ok' ? htmlspecialchars($film['rights']['label']) : 'Check before adding'; ?>
                    </span>
                    <?php if ($film['warnings']): ?>
                    <ul class="ff-warnings">
                      <?php foreach ($film['warnings'] as $warning): ?><li><?php echo htmlspecialchars($warning); ?></li><?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                  </div>
                </div>

                <p class="ff-section">Which title is this?</p>
                <div class="ff-type" role="tablist">
                  <a href="<?php echo htmlspecialchars($previewQuery(['type' => 'movie', 'q' => $search])); ?>" class="<?php echo $type === 'movie' ? 'is-active' : ''; ?>">Movie</a>
                  <a href="<?php echo htmlspecialchars($previewQuery(['type' => 'tv', 'q' => $search])); ?>" class="<?php echo $type === 'tv' ? 'is-active' : ''; ?>">TV episode</a>
                </div>
                <form method="GET" action="/admin-free-films" class="ff-search">
                  <input type="hidden" name="link" value="<?php echo htmlspecialchars($link); ?>">
                  <?php if ($type === 'tv'): ?><input type="hidden" name="type" value="tv"><?php endif; ?>
                  <input type="search" name="q" class="form-control" placeholder="Not listed? Search TMDB by name" value="<?php echo htmlspecialchars($search); ?>">
                  <button type="submit" class="btn btn-outline-secondary">Search</button>
                </form>

                <form method="POST" action="/admin-free-films" id="addFilmForm">
                  <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                  <input type="hidden" name="action" value="add">
                  <input type="hidden" name="link" value="<?php echo htmlspecialchars($link); ?>">
                  <input type="hidden" name="type" value="<?php echo $type; ?>">

                  <div class="ff-candidates">
                    <?php foreach ($candidates as $i => $c): ?>
                    <label class="ff-candidate">
                      <input type="radio" name="tmdb_pick" value="<?php echo (int) $c['tmdb_id']; ?>"<?php echo $i === 0 && $c['score'] >= 0.6 ? ' checked' : ''; ?>>
                      <?php if ($c['poster']): ?><img src="https://image.tmdb.org/t/p/w92<?php echo htmlspecialchars($c['poster']); ?>" alt="" loading="lazy"><?php else: ?><span class="ff-noposter"></span><?php endif; ?>
                      <span>
                        <b><?php echo htmlspecialchars($c['title']); ?></b>
                        <small><?php echo htmlspecialchars($c['year'] ?: 'Year unknown'); ?> · TMDB <?php echo (int) $c['tmdb_id']; ?></small>
                        <?php if ($i === 0 && $c['score'] >= 0.6): ?><span class="ff-best">Best match</span><?php endif; ?>
                      </span>
                    </label>
                    <?php endforeach; ?>
                    <label class="ff-candidate ff-manual">
                      <input type="radio" name="tmdb_pick" value="manual"<?php echo !$candidates ? ' checked' : ''; ?>>
                      <span class="ff-noposter" style="display:inline-flex;align-items:center;justify-content:center;"><i class="ph ph-hash" style="font-size:1.3rem;color:var(--adm-muted);"></i></span>
                      <span>
                        <b>Another TMDB ID</b>
                        <input type="number" name="tmdb_manual" min="1" class="form-control" placeholder="e.g. 438631" onfocus="this.closest('label').querySelector('input[type=radio]').checked = true">
                      </span>
                    </label>
                  </div>
                  <?php if (!$candidates): ?>
                  <p class="ff-hint">No matches on TMDB. Try a shorter name above. If the film isn't on TMDB at all, anyone can add it at themoviedb.org, then come back with its ID.</p>
                  <?php endif; ?>

                  <?php if ($type === 'tv'): ?>
                  <div class="ff-tv">
                    <div class="form-group"><label for="ff_season">Season</label><input type="number" id="ff_season" name="season" min="0" value="1" class="form-control"></div>
                    <div class="form-group"><label for="ff_episode">Episode</label><input type="number" id="ff_episode" name="episode" min="1" value="1" class="form-control"></div>
                  </div>
                  <?php endif; ?>

                  <div class="form-group mt-3" style="max-width: 640px;">
                    <label for="ff_rights">Why it may be shown</label>
                    <input type="text" id="ff_rights" name="rights_note" maxlength="255" class="form-control" value="<?php echo htmlspecialchars($film['rights']['note']); ?>" required>
                  </div>
                  <label class="ff-confirm">
                    <input type="checkbox" name="confirm_rights" value="1"<?php echo $film['rights']['status'] === 'ok' ? ' checked' : ''; ?> required>
                    <span><?php echo $film['source'] === 'youtube'
                        ? htmlspecialchars($film['owner'] ?: 'This channel') . ' is the official studio, distributor or filmmaker of this film.'
                        : 'This film is in the public domain or its licence allows showing it.'; ?></span>
                  </label>
                  <button type="submit" class="btn btn-primary mt-2">Add to ZEN</button>
                </form>
                <?php endif; ?>
              </div>
            </div>

            <!-- Library -->
            <div class="card">
              <div class="card-header d-flex align-items-center justify-content-between flex-wrap" style="gap:10px;">
                <h5>Playing on ZEN (<?php echo number_format($playing); ?>)</h5>
                <?php if ($films): ?>
                <form method="POST" class="m-0">
                  <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                  <button type="submit" name="action" value="check_all" class="btn btn-sm btn-outline-secondary"><i class="ph ph-arrows-clockwise"></i> Check links</button>
                </form>
                <?php endif; ?>
              </div>
              <div class="card-body">
                <?php if ($unavailable): ?>
                <div class="alert alert-warning"><?php echo $unavailable; ?> <?php echo $unavailable === 1 ? "video doesn't" : "videos don't"; ?> play any more (removed, or embedding turned off) and <?php echo $unavailable === 1 ? 'is' : 'are'; ?> hidden from the watch page.</div>
                <?php endif; ?>
                <?php if (!$films): ?>
                <p class="text-muted mb-0">Nothing yet. Paste a link above to add the first film.</p>
                <?php else: ?>
                <ul class="ff-list">
                  <?php foreach ($films as $f):
                      $info = fetchTmdbApi("{$f['media_type']}/{$f['tmdb_id']}");
                      $name = $info['title'] ?? $info['name'] ?? 'TMDB ' . (int) $f['tmdb_id'];
                      $year = substr($info['release_date'] ?? $info['first_air_date'] ?? '', 0, 4);
                      $status = $f['check_status'] ?? null;
                  ?>
                  <li class="ff-row" id="film-<?php echo (int) $f['id']; ?>">
                    <?php if (!empty($info['poster_path'])): ?><img src="https://image.tmdb.org/t/p/w92<?php echo htmlspecialchars($info['poster_path']); ?>" alt="" loading="lazy"><?php else: ?><span class="ff-noposter"></span><?php endif; ?>
                    <div style="min-width:0;">
                      <a class="ff-name" href="<?php echo htmlspecialchars($watchUrlFor($f)); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($name); ?><?php echo $f['media_type'] === 'tv' ? ' · S' . (int) $f['season'] . ' E' . (int) $f['episode'] : ($year ? " ($year)" : ''); ?></a>
                      <span class="ff-sub">
                        <?php echo htmlspecialchars($sourceLabel[$f['source'] ?? ''] ?? 'Video link'); ?><?php echo !empty($f['source_owner']) ? ' · ' . htmlspecialchars($f['source_owner']) : ''; ?>
                        <?php if (!empty($f['source_url'])): ?> · <a href="<?php echo htmlspecialchars($f['source_url']); ?>" target="_blank" rel="noopener" style="color:var(--adm-cyan)!important;">original</a><?php endif; ?>
                      </span>
                      <?php if ($status === 'unavailable'): ?><span class="ff-status gone">Doesn't play · checked <?php echo htmlspecialchars(date('j M', strtotime($f['checked_at']))); ?></span>
                      <?php elseif ($status === 'ok'): ?><span class="ff-status ok">Plays · checked <?php echo htmlspecialchars(date('j M', strtotime($f['checked_at']))); ?></span>
                      <?php else: ?><span class="ff-status new">Not checked yet</span><?php endif; ?>
                    </div>
                    <div class="ff-rights-col" style="min-width:0;">
                      <span class="ff-rights-note"><?php echo htmlspecialchars($f['rights_note'] ?: 'No rights note'); ?></span>
                      <?php if (!empty($f['created_at'])): ?><span class="ff-rights-note">Added <?php echo htmlspecialchars(date('j M Y', strtotime($f['created_at']))); ?></span><?php endif; ?>
                    </div>
                    <div class="ff-actions">
                      <form method="POST"><input type="hidden" name="csrf" value="<?php echo $csrf; ?>"><input type="hidden" name="film_id" value="<?php echo (int) $f['id']; ?>"><button type="submit" name="action" value="check" class="btn btn-outline-secondary">Check</button></form>
                      <form method="POST" onsubmit="return confirm('Stop playing this video on ZEN?')"><input type="hidden" name="csrf" value="<?php echo $csrf; ?>"><input type="hidden" name="film_id" value="<?php echo (int) $f['id']; ?>"><button type="submit" name="action" value="remove" class="btn btn-outline-danger">Remove</button></form>
                    </div>
                  </li>
                  <?php endforeach; ?>
                </ul>
                <?php endif; ?>
              </div>
            </div>

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
