<?php
if (!isset($_SESSION['user_id'])) {
    $currentUrl = urlencode($_SERVER['REQUEST_URI']);
    header('Location: /login?next=' . $currentUrl);
    exit;
}

// ==========================================
// MEMBER DASHBOARD
// ==========================================
// Where a signed-in member lands: what they were watching, picks based on
// what they have watched, their watchlist, and the way in to everything
// their account can do.

require_once APP_PATH . '/lib/recommendations.php';

$userId = (int) $_SESSION['user_id'];
$displayName = $_SESSION['firstName'] ?? $_SESSION['username'] ?? 'there';
$isKidsMode = !empty($_SESSION['is_kids_mode']);

$one = function (string $sql, array $params = []) use ($conn) {
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        return null;
    }
};
$all = function (string $sql, array $params = []) use ($conn): array {
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
};

$member = $all("SELECT username, email, avatar_url, is_admin, created_at, current_plan_id FROM users WHERE id = ?", [$userId])[0] ?? [];
$isAdmin = (int) ($member['is_admin'] ?? 0) === 1;
$memberSince = !empty($member['created_at']) ? date('M Y', strtotime($member['created_at'])) : '';
$planName = ucfirst((string) ($_SESSION['plan_name'] ?? 'free'));

$watchedSeconds = (float) ($one("SELECT SUM(`current_time`) FROM watch_history WHERE user_id = ?", [$userId]) ?? 0);
$titlesWatched  = (int) ($one("SELECT COUNT(*) FROM watch_history WHERE user_id = ?", [$userId]) ?? 0);
$watchlistCount = (int) ($one("SELECT COUNT(*) FROM watchlist WHERE user_id = ?", [$userId]) ?? 0);
$watchTime = $watchedSeconds >= 3600
    ? floor($watchedSeconds / 3600) . 'h ' . (int) (($watchedSeconds % 3600) / 60) . 'm'
    : max(0, (int) round($watchedSeconds / 60)) . 'm';

// Carry on watching: started, not finished.
$continueRows = $all("SELECT tmdb_movie_id, media_type, `current_time`, total_duration, last_watched
                        FROM watch_history WHERE user_id = ? AND total_duration > 0
                         AND `current_time` / total_duration < 0.95
                      ORDER BY last_watched DESC LIMIT 6", [$userId]);
$watchlistRows = $all("SELECT tmdb_movie_id, media_type FROM watchlist WHERE user_id = ?
                        ORDER BY date_added DESC LIMIT 6", [$userId]);

// Every poster on this page, fetched together.
if (function_exists('prefetchTmdbApi')) {
    $wanted = [];
    foreach (array_merge($continueRows, $watchlistRows) as $row) {
        $type = ($row['media_type'] ?? 'movie') === 'tv' ? 'tv' : 'movie';
        $wanted[] = ["$type/{$row['tmdb_movie_id']}", []];
    }
    if ($wanted) {
        prefetchTmdbApi($wanted);
    }
}
$titleCard = function (array $row): ?array {
    $type = ($row['media_type'] ?? 'movie') === 'tv' ? 'tv' : 'movie';
    $details = function_exists('fetchTmdbApi') ? fetchTmdbApi("$type/{$row['tmdb_movie_id']}") : null;
    if (!$details) {
        return null;
    }
    return [
        'id' => (int) $row['tmdb_movie_id'],
        'type' => $type,
        'title' => $details['title'] ?? $details['name'] ?? 'Untitled',
        'poster' => !empty($details['poster_path'])
            ? 'https://image.tmdb.org/t/p/w342' . $details['poster_path']
            : '/assets/images/media/placeholder-portrait.svg',
        'year' => substr($details['release_date'] ?? $details['first_air_date'] ?? '', 0, 4),
    ];
};

$continueItems = [];
foreach ($continueRows as $row) {
    $card = $titleCard($row);
    if (!$card) continue;
    $card['progress'] = max(2, min(99, (int) round(($row['current_time'] / max($row['total_duration'], 1)) * 100)));
    $card['resume'] = '/watch?id=' . $card['id'] . '&type=' . $card['type'];
    $continueItems[] = $card;
}
$watchlistItems = array_values(array_filter(array_map($titleCard, $watchlistRows)));

$picks = personalPicks($conn, $userId, $isKidsMode, 6);

include __DIR__ . '/includes/header.php';
?>

<style>
    .zd {
        --zd-line: rgba(255, 255, 255, 0.08);
        --zd-card: rgba(255, 255, 255, 0.03);
        --zd-muted: #9297a1;
        max-width: 1240px;
        margin: 0 auto;
        padding: 16px 16px 48px;
        color: #fff;
    }
    @media (min-width: 768px) { .zd { padding: 28px 24px 64px; } }
    .zd a { text-decoration: none; }

    /* Greeting */
    .zd-hero { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; justify-content: space-between; padding: 20px; margin-bottom: 18px; border: 1px solid var(--zd-line); border-radius: 20px;
        background: radial-gradient(120% 140% at 0% 0%, rgba(var(--primary-rgb), 0.18) 0%, transparent 55%), rgba(255, 255, 255, 0.025); }
    .zd-who { display: flex; align-items: center; gap: 14px; min-width: 0; }
    .zd-avatar { width: 56px; height: 56px; border-radius: 50%; object-fit: cover; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; background: rgba(var(--primary-rgb), .2); color: #fff; font-size: 1.4rem; font-weight: 700; }
    .zd-hello { margin: 0; font-size: clamp(1.25rem, 3.4vw, 1.75rem); font-weight: 800; letter-spacing: -.02em; }
    .zd-sub { margin: 4px 0 0; color: var(--zd-muted); font-size: .86rem; }
    .zd-hero-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .zd-btn { display: inline-flex; align-items: center; gap: 8px; padding: 9px 16px; border-radius: 999px; border: 1px solid var(--zd-line); background: rgba(255,255,255,.04); color: #fff !important; font-size: .85rem; font-weight: 600; white-space: nowrap; }
    .zd-btn:hover { background: rgba(255,255,255,.09); }
    .zd-btn.primary { background: var(--primary); border-color: var(--primary); }
    .zd-btn.primary:hover { background: var(--primary-hover, #ff2a35); }

    /* Figures */
    .zd-stats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-bottom: 22px; }
    @media (min-width: 992px) { .zd-stats { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .zd-stat { padding: 16px; border: 1px solid var(--zd-line); border-radius: 16px; background: var(--zd-card); }
    .zd-stat i { font-size: 1.2rem; color: var(--primary); }
    .zd-stat b { display: block; margin-top: 8px; font-size: clamp(1.3rem, 3.6vw, 1.7rem); font-weight: 800; line-height: 1.1; }
    .zd-stat span { color: var(--zd-muted); font-size: .78rem; font-weight: 600; }
    .zd-stat a { color: var(--primary) !important; font-size: .76rem; font-weight: 600; }

    /* Sections */
    .zd-head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin: 26px 0 12px; }
    .zd-head h2 { margin: 0; font-size: 1.05rem; font-weight: 700; }
    .zd-head a { color: var(--zd-muted) !important; font-size: .8rem; font-weight: 600; }
    .zd-head a:hover { color: #fff !important; }

    .zd-row { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
    @media (min-width: 768px) { .zd-row { grid-template-columns: repeat(6, minmax(0, 1fr)); } }
    .zd-card { display: block; color: #fff !important; min-width: 0; }
    /* display:block matters: these are spans, and an inline box ignores the
       poster shape, so the images spill over the row below. */
    .zd-poster { display: block; position: relative; aspect-ratio: 2 / 3; border-radius: 12px; overflow: hidden; background: #14141c; border: 1px solid var(--zd-line); }
    .zd-poster img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .25s ease; }
    .zd-card:hover .zd-poster img { transform: scale(1.05); }
    .zd-play { position: absolute; inset: auto 0 0 0; display: flex; align-items: center; justify-content: center; gap: 6px; padding: 8px; background: linear-gradient(transparent, rgba(0,0,0,.85)); font-size: .78rem; font-weight: 600; opacity: 0; transition: opacity .2s; }
    .zd-card:hover .zd-play { opacity: 1; }
    .zd-bar { position: absolute; left: 0; right: 0; bottom: 0; height: 4px; background: rgba(255,255,255,.18); }
    .zd-bar span { display: block; height: 100%; background: var(--primary); }
    .zd-name { display: block; margin-top: 8px; font-size: .84rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .zd-year { display: block; color: var(--zd-muted); font-size: .74rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .zd-empty { padding: 26px 18px; border: 1px dashed var(--zd-line); border-radius: 16px; text-align: center; color: var(--zd-muted); font-size: .88rem; }
    .zd-empty a { color: var(--primary) !important; font-weight: 600; }

    /* Everything your account can do */
    .zd-links { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    @media (min-width: 768px) { .zd-links { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (min-width: 1200px) { .zd-links { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .zd-link { display: flex; align-items: center; gap: 12px; padding: 14px; border: 1px solid var(--zd-line); border-radius: 14px; background: var(--zd-card); color: #fff !important; transition: background .15s, border-color .15s, transform .15s; }
    .zd-link:hover { background: rgba(255,255,255,.07); border-color: rgba(255,255,255,.16); transform: translateY(-2px); }
    .zd-link i { font-size: 1.35rem; color: var(--primary); flex-shrink: 0; }
    .zd-link > span { display: block; min-width: 0; }
    .zd-link strong { display: block; font-size: .9rem; font-weight: 600; }
    .zd-link small { display: block; color: var(--zd-muted); font-size: .76rem; line-height: 1.35; }
    .zd-link.admin i { color: #ffc400; }
</style>

<div class="zd">

    <header class="zd-hero">
        <div class="zd-who">
            <?php if (!empty($member['avatar_url'])): ?>
            <img class="zd-avatar" src="<?php echo htmlspecialchars($member['avatar_url']); ?>" alt="">
            <?php else: ?>
            <span class="zd-avatar"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($displayName, 0, 1))); ?></span>
            <?php endif; ?>
            <div style="min-width:0;">
                <h1 class="zd-hello">Welcome back, <?php echo htmlspecialchars($displayName); ?></h1>
                <p class="zd-sub">
                    <?php echo htmlspecialchars($planName); ?> plan<?php echo $memberSince ? ' · member since ' . htmlspecialchars($memberSince) : ''; ?><?php echo $isKidsMode ? ' · Kids Mode on' : ''; ?>
                </p>
            </div>
        </div>
        <div class="zd-hero-actions">
            <a class="zd-btn primary" href="/"><i class="ph ph-play"></i> Browse titles</a>
            <a class="zd-btn" href="/profile"><i class="ph ph-user"></i> Profile</a>
            <?php if ($isAdmin): ?>
            <a class="zd-btn" href="/admin"><i class="ph ph-shield-check"></i> Admin panel</a>
            <?php endif; ?>
        </div>
    </header>

    <section class="zd-stats" aria-label="Your viewing">
        <div class="zd-stat"><i class="ph ph-clock"></i><b><?php echo htmlspecialchars($watchTime); ?></b><span>Time watched</span></div>
        <div class="zd-stat"><i class="ph ph-film-strip"></i><b><?php echo number_format($titlesWatched); ?></b><span>Titles watched</span></div>
        <div class="zd-stat"><i class="ph ph-heart"></i><b><?php echo number_format($watchlistCount); ?></b><span>On your watchlist</span></div>
        <div class="zd-stat">
            <i class="ph ph-crown"></i><b><?php echo htmlspecialchars($planName); ?></b>
            <span>Your plan</span><br><a href="/pricing-plan">See plans</a>
        </div>
    </section>

    <?php if ($continueItems): ?>
    <div class="zd-head">
        <h2>Carry on watching</h2>
        <a href="/profile#history">Full history</a>
    </div>
    <div class="zd-row">
        <?php foreach ($continueItems as $item): ?>
        <a class="zd-card" href="<?php echo htmlspecialchars($item['resume']); ?>">
            <span class="zd-poster">
                <img src="<?php echo htmlspecialchars($item['poster']); ?>" alt="" loading="lazy">
                <span class="zd-play"><i class="ph-fill ph-play"></i> Resume</span>
                <span class="zd-bar"><span style="width: <?php echo (int) $item['progress']; ?>%;"></span></span>
            </span>
            <span class="zd-name"><?php echo htmlspecialchars($item['title']); ?></span>
            <span class="zd-year"><?php echo (int) $item['progress']; ?>% watched</span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($picks['items'])): ?>
    <div class="zd-head">
        <h2><?php echo $picks['seed_title'] !== '' ? 'Because you watched ' . htmlspecialchars($picks['seed_title']) : 'Picked for you'; ?></h2>
    </div>
    <div class="zd-row">
        <?php foreach ($picks['items'] as $item): ?>
        <a class="zd-card" href="<?php echo htmlspecialchars($item['link']); ?>">
            <span class="zd-poster">
                <img src="<?php echo htmlspecialchars($item['poster_url']); ?>" alt="" loading="lazy">
                <span class="zd-play"><i class="ph ph-info"></i> Details</span>
            </span>
            <span class="zd-name"><?php echo htmlspecialchars($item['title']); ?></span>
            <span class="zd-year"><?php echo htmlspecialchars($item['genre'] . ($item['year'] ? ' · ' . $item['year'] : '')); ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php elseif (!$continueItems): ?>
    <div class="zd-empty">
        You haven't watched anything yet. <a href="/">Find something to watch</a> and your picks will appear here.
    </div>
    <?php endif; ?>

    <div class="zd-head">
        <h2>Your watchlist</h2>
        <a href="/profile">See all<?php echo $watchlistCount ? ' (' . number_format($watchlistCount) . ')' : ''; ?></a>
    </div>
    <?php if ($watchlistItems): ?>
    <div class="zd-row">
        <?php foreach ($watchlistItems as $item): ?>
        <a class="zd-card" href="/<?php echo $item['type']; ?>/<?php echo $item['id']; ?>">
            <span class="zd-poster">
                <img src="<?php echo htmlspecialchars($item['poster']); ?>" alt="" loading="lazy">
                <span class="zd-play"><i class="ph ph-info"></i> Details</span>
            </span>
            <span class="zd-name"><?php echo htmlspecialchars($item['title']); ?></span>
            <span class="zd-year"><?php echo htmlspecialchars($item['year']); ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="zd-empty">Nothing saved yet. Tap the <i class="ph ph-plus"></i> on any title to keep it here for later.</div>
    <?php endif; ?>

    <div class="zd-head"><h2>Your account</h2></div>
    <nav class="zd-links" aria-label="Account">
        <a class="zd-link" href="/profile"><i class="ph ph-user-circle"></i><span><strong>Profile</strong><small>Name, photo and email</small></span></a>
        <a class="zd-link" href="/profile"><i class="ph ph-heart"></i><span><strong>Watchlist</strong><small><?php echo number_format($watchlistCount); ?> saved</small></span></a>
        <a class="zd-link" href="/profile#history"><i class="ph ph-clock-counter-clockwise"></i><span><strong>Watch history</strong><small>See it or clear it</small></span></a>
        <a class="zd-link" href="/pricing-plan"><i class="ph ph-crown"></i><span><strong>Plan &amp; billing</strong><small><?php echo htmlspecialchars($planName); ?> plan</small></span></a>
        <a class="zd-link" href="/profile"><i class="ph ph-shield-check"></i><span><strong>Kids Mode &amp; PIN</strong><small><?php echo $isKidsMode ? 'Kids Mode is on' : 'Set up parental controls'; ?></small></span></a>
        <a class="zd-link" href="javascript:void(0)" onclick="if (typeof triggerZenAI === 'function') { triggerZenAI(); } else { window.location.href = '/'; } return false;"><i class="ph ph-sparkle"></i><span><strong>Ask ZEN AI</strong><small>Find something to watch</small></span></a>
        <?php if ($isAdmin): ?>
        <a class="zd-link admin" href="/admin"><i class="ph ph-shield-star"></i><span><strong>Admin panel</strong><small>Run the site</small></span></a>
        <?php endif; ?>
        <a class="zd-link" href="/logout"><i class="ph ph-sign-out"></i><span><strong>Sign out</strong><small>On this device</small></span></a>
    </nav>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
