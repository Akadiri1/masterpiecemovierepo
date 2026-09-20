<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Basic Admin Auth Check
$isAdmin = false;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $is_admin_flag = $stmt->fetchColumn();
        if ($is_admin_flag == 1) {
            $isAdmin = true;
        }
    } catch (PDOException $e) {}
}

// If not admin, kick them out
if (!$isAdmin) {
    header('Location: /login');
    exit;
}

require_once APP_PATH . '/lib/site_settings.php';

// Every figure below is optional: a missing table or failed query shows 0
// rather than breaking the page.
$scalar = function (string $sql, array $params = []) use ($conn) {
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() ?: 0;
    } catch (PDOException $e) {
        return 0;
    }
};
$rows = function (string $sql, array $params = []) use ($conn): array {
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
};

// 2. Headline figures
$totalUsers    = (int) $scalar("SELECT COUNT(*) FROM users");
$newUsersWeek  = (int) $scalar("SELECT COUNT(*) FROM users WHERE created_at >= NOW() - INTERVAL 7 DAY");
$payingNow     = (int) $scalar("SELECT COUNT(DISTINCT user_id) FROM subscriptions WHERE status = 'active' AND expires_at > NOW()");
$paymentsTotal = (int) $scalar("SELECT COUNT(*) FROM subscriptions");
$revenueTotal  = (float) $scalar("SELECT SUM(p.price) FROM subscriptions s JOIN plans p ON LOWER(p.name) = LOWER(s.plan_name)");
$viewersWeek   = (int) $scalar("SELECT COUNT(DISTINCT user_id) FROM watch_history WHERE last_watched >= NOW() - INTERVAL 7 DAY");
$titlesWeek    = (int) $scalar("SELECT COUNT(*) FROM watch_history WHERE last_watched >= NOW() - INTERVAL 7 DAY");
$totalViews    = (int) $scalar("SELECT SUM(views) FROM content_views");
$totalWatchlist = (int) $scalar("SELECT COUNT(*) FROM watchlist");

// 3. The last 14 days, by the database's own calendar
$today = (string) ($scalar("SELECT CURDATE()") ?: date('Y-m-d'));
$days = [];
for ($i = 13; $i >= 0; $i--) {
    $days[date('Y-m-d', strtotime("$today -$i days"))] = ['signups' => 0, 'viewers' => 0];
}
foreach ($rows("SELECT DATE(created_at) d, COUNT(*) c FROM users WHERE created_at >= CURDATE() - INTERVAL 13 DAY GROUP BY d") as $r) {
    if (isset($days[$r['d']])) $days[$r['d']]['signups'] = (int) $r['c'];
}
foreach ($rows("SELECT DATE(last_watched) d, COUNT(DISTINCT user_id) c FROM watch_history WHERE last_watched >= CURDATE() - INTERVAL 13 DAY GROUP BY d") as $r) {
    if (isset($days[$r['d']])) $days[$r['d']]['viewers'] = (int) $r['c'];
}
$chartMax = max(1, ...array_values(array_map(fn($d) => max($d['signups'], $d['viewers']), $days)));
$signups14 = array_sum(array_column($days, 'signups'));

// 4. Tools and what needs attention
$playbackMode   = siteSetting('playback_mode') === PLAYBACK_SERVERS ? PLAYBACK_SERVERS : PLAYBACK_DISCOVER;
// A film kept in several sizes is one film, so count titles, not files.
$playableTitles = (int) $scalar("SELECT COUNT(DISTINCT CONCAT_WS(':', tmdb_id, media_type, season, episode))
                                   FROM media_sources WHERE COALESCE(check_status, '') <> 'unavailable'")
                + (int) $scalar("SELECT COUNT(DISTINCT CONCAT_WS(':', tmdb_id, media_type, season, episode))
                                   FROM media_downloads WHERE is_active = 1 AND download_url REGEXP '\\\\.(mp4|mkv|webm|m3u8)(\\\\?|$)'");
$aiChatsToday   = (int) $scalar("SELECT COUNT(*) FROM zen_search_history WHERE created_at >= CURDATE()");
$aiUnverified   = (int) $scalar("SELECT COUNT(*) FROM ai_hooks WHERE model = 'imported-from-json'");
$aiFailures     = (int) $scalar("SELECT COUNT(*) FROM ai_usage_log WHERE status IN ('error','blocked') AND created_at >= CURDATE()");
$ingestReview   = (int) $scalar("SELECT COUNT(*) FROM ingestion_jobs WHERE status = 'needs_review'");
$ingestFailed   = (int) $scalar("SELECT COUNT(*) FROM ingestion_jobs WHERE status = 'failed'");
$activeDownloads = (int) $scalar("SELECT COUNT(*) FROM media_downloads WHERE is_active = 1");

$attention = [];
if ($playbackMode === PLAYBACK_SERVERS) {
    $attention[] = ['warning', 'ph-warning', 'Streaming servers are on for every visitor.', '/admin-playback', 'Review'];
}
if ($ingestReview) {
    $attention[] = ['info', 'ph-cloud-arrow-down', $ingestReview . ' ' . ($ingestReview === 1 ? 'film waits' : 'films wait') . ' for a licence review.', '/admin-ingestion', 'Review'];
}
if ($ingestFailed) {
    $attention[] = ['danger', 'ph-warning-circle', $ingestFailed . ' ingestion ' . ($ingestFailed === 1 ? 'job' : 'jobs') . ' failed.', '/admin-ingestion', 'Open'];
}
if ($aiFailures) {
    $attention[] = ['danger', 'ph-warning-circle', $aiFailures . ' AI ' . ($aiFailures === 1 ? 'request' : 'requests') . ' failed or were blocked today.', '/admin-ai', 'Open'];
}
if ($aiUnverified) {
    $attention[] = ['info', 'ph-sparkle', $aiUnverified . ' imported AI ' . ($aiUnverified === 1 ? 'pitch needs' : 'pitches need') . ' checking.', '/admin-ai', 'Review'];
}
if ($playbackMode === PLAYBACK_DISCOVER && !$playableTitles) {
    $attention[] = ['info', 'ph-film-slate', 'No films play in full yet. Add free films from YouTube or archive.org.', '/admin-free-films', 'Add'];
}

$tools = [
    ['/admin-playback', 'ph-play-circle', 'Playback', $playbackMode === PLAYBACK_SERVERS ? 'Streaming servers on' : 'Discover mode', $playbackMode === PLAYBACK_SERVERS ? 'warning' : 'ok'],
    ['/admin-ai', 'ph-sparkle', 'ZEN AI', number_format($aiChatsToday) . ' ' . ($aiChatsToday === 1 ? 'chat' : 'chats') . ' today', ''],
    ['/admin-ingestion', 'ph-cloud-arrow-down', 'Ingestion', $ingestReview ? number_format($ingestReview) . ' to review' : 'Queue clear', $ingestReview ? 'info' : ''],
    ['/admin-free-films', 'ph-film-strip', 'Free films', $playableTitles ? number_format($playableTitles) . ' playing' : 'None yet', $playableTitles ? 'ok' : ''],
    ['/admin-view-downloads', 'ph-download-simple', 'Downloads', number_format($activeDownloads) . ' active ' . ($activeDownloads === 1 ? 'link' : 'links'), ''],
    ['/admin-view-users', 'ph-users-three', 'Members', number_format($totalUsers) . ' total', ''],
];

// 5. Lists
$recentUsers = $rows("SELECT u.id, u.username, u.email, u.is_admin, u.avatar_url, u.created_at,
        (SELECT s.plan_name FROM subscriptions s WHERE s.user_id = u.id AND s.status = 'active' AND s.expires_at > NOW()
          ORDER BY s.expires_at DESC LIMIT 1) AS plan
    FROM users u ORDER BY u.id DESC LIMIT 6");

$topContent = $rows("SELECT tmdb_id, media_type, views FROM content_views ORDER BY views DESC LIMIT 6");
if ($topContent && function_exists('prefetchTmdbApi')) {
    prefetchTmdbApi(array_map(fn($t) => ["{$t['media_type']}/{$t['tmdb_id']}", []], $topContent));
}
$topMax = max(1, ...array_map(fn($t) => (int) $t['views'], $topContent ?: [['views' => 1]]));

function admTimeAgo(?string $when): string
{
    if (!$when || !($ts = strtotime($when))) return '';
    $diff = time() - $ts;
    if ($diff < 3600) return max(1, (int) floor($diff / 60)) . 'm ago';
    if ($diff < 86400) return (int) floor($diff / 3600) . 'h ago';
    if ($diff < 7 * 86400) return (int) floor($diff / 86400) . 'd ago';
    return date($diff < 300 * 86400 ? 'j M' : 'j M Y', $ts);
}

$adminName = $_SESSION['username'] ?? 'Admin';

// The admin panel's sidebar and top bar, shared by every admin page.
$level_check = ['MASTER', 3, 2, 1];
$adminPageTitle = 'Dashboard';
include APP_PATH . '/admin/includes/header.php';
?>

<style>
    .adm {
        --adm-line: rgba(255, 255, 255, 0.07);
        --adm-card: rgba(255, 255, 255, 0.03);
        --adm-muted: #8d919b;
        --adm-green: #34d399;
        --adm-amber: #fbbf24;
        --adm-red: #f87171;
        --adm-cyan: #22d3ee;
        --adm-violet: #a78bfa;
        max-width: 1320px;
        margin: 0 auto;
        color: #fff;
    }
    .adm a { text-decoration: none; }
    .adm-card { background: var(--adm-card); border: 1px solid var(--adm-line); border-radius: 18px; }
    .adm-card-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 18px 20px 0; }
    .adm-card-head h2 { margin: 0; font-size: 1rem; font-weight: 700; color: #fff; }
    .adm-card-head .adm-sub { color: var(--adm-muted); font-size: .8rem; }
    .adm-link { color: var(--adm-muted); font-size: .8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; }
    .adm-link:hover { color: #fff; }

    /* Header */
    .adm-top { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 22px; }
    .adm-eyebrow { color: var(--primary); font-size: .72rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; margin: 0 0 6px; }
    .adm-top h1 { margin: 0; font-size: clamp(1.5rem, 3.4vw, 2.1rem); font-weight: 800; letter-spacing: -.02em; color: #fff; }
    .adm-top p { margin: 6px 0 0; color: var(--adm-muted); font-size: .92rem; }
    .adm-top-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .adm-chip { display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; border-radius: 999px; border: 1px solid var(--adm-line); background: var(--adm-card); color: #e7e8ec; font-size: .82rem; font-weight: 600; white-space: nowrap; transition: background .15s, border-color .15s; }
    .adm-chip:hover { background: rgba(255,255,255,.07); border-color: rgba(255,255,255,.16); color: #fff; }
    .adm-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--adm-green); box-shadow: 0 0 0 3px rgba(52,211,153,.18); }
    .adm-dot.warning { background: var(--adm-amber); box-shadow: 0 0 0 3px rgba(251,191,36,.18); }

    /* Headline figures */
    .adm-kpis { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
    @media (min-width: 992px) { .adm-kpis { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; } }
    .adm-kpi { padding: 16px; min-width: 0; }
    @media (min-width: 768px) { .adm-kpi { padding: 20px; } }
    .adm-kpi-icon { width: 38px; height: 38px; border-radius: 11px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-bottom: 14px; }
    .adm-kpi-label { color: var(--adm-muted); font-size: .78rem; font-weight: 600; margin: 0; }
    .adm-kpi-value { margin: 2px 0 0; font-size: clamp(1.5rem, 4vw, 2rem); font-weight: 800; letter-spacing: -.02em; line-height: 1.15; color: #fff; }
    .adm-kpi-note { display: block; margin-top: 6px; color: var(--adm-muted); font-size: .76rem; line-height: 1.35; }
    .adm-kpi-note.up { color: var(--adm-green); }
    .tone-red    { color: #ff6b73; background: rgba(var(--primary-rgb), .14); }
    .tone-green  { color: var(--adm-green); background: rgba(52,211,153,.12); }
    .tone-cyan   { color: var(--adm-cyan); background: rgba(34,211,238,.12); }
    .tone-violet { color: var(--adm-violet); background: rgba(167,139,250,.13); }
    .tone-amber  { color: var(--adm-amber); background: rgba(251,191,36,.12); }

    /* Grids */
    .adm-grid { display: grid; gap: 16px; margin-bottom: 16px; }
    @media (min-width: 992px) {
        .adm-grid.split { grid-template-columns: minmax(0, 1.7fr) minmax(0, 1fr); }
        .adm-grid.halves { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    /* Chart */
    .adm-legend { display: flex; gap: 14px; color: var(--adm-muted); font-size: .76rem; }
    .adm-legend span { display: inline-flex; align-items: center; gap: 6px; }
    .adm-legend i { width: 9px; height: 9px; border-radius: 3px; display: inline-block; }
    .adm-chart { display: grid; grid-template-columns: repeat(14, minmax(0, 1fr)); gap: 4px; align-items: end; height: 170px; padding: 22px 20px 0; }
    .adm-day { display: flex; align-items: flex-end; justify-content: center; gap: 2px; height: 100%; }
    .adm-bar { width: min(12px, 42%); border-radius: 4px 4px 1px 1px; min-height: 2px; transition: opacity .15s; }
    .adm-bar.signups { background: var(--primary); }
    .adm-bar.viewers { background: var(--adm-cyan); }
    .adm-bar.zero { opacity: .18; }
    .adm-day:hover .adm-bar { opacity: .75; }
    .adm-days { display: grid; grid-template-columns: repeat(14, minmax(0, 1fr)); gap: 4px; padding: 8px 20px 18px; color: #62666f; font-size: .66rem; text-align: center; }
    @media (max-width: 575.98px) { .adm-days span:nth-child(even) { visibility: hidden; } .adm-chart { height: 140px; } }

    /* Needs attention */
    .adm-attn { list-style: none; margin: 0; padding: 12px 12px 14px; }
    .adm-attn li { display: flex; align-items: center; gap: 12px; padding: 10px 8px; border-radius: 12px; }
    .adm-attn li + li { border-top: 1px solid var(--adm-line); border-radius: 0; }
    .adm-attn .adm-attn-icon { width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; font-size: 1.05rem; }
    .adm-attn .adm-attn-text { flex: 1; min-width: 0; font-size: .86rem; line-height: 1.4; color: #dfe1e6; }
    .adm-attn .adm-attn-go { flex-shrink: 0; padding: 5px 12px; border-radius: 999px; border: 1px solid var(--adm-line); color: #fff; font-size: .76rem; font-weight: 600; }
    .adm-attn .adm-attn-go:hover { background: rgba(255,255,255,.08); }
    .adm-clear { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; min-height: 180px; padding: 20px; text-align: center; color: var(--adm-muted); font-size: .88rem; }
    .adm-clear i { font-size: 2rem; color: var(--adm-green); }

    /* Tools */
    .adm-tools { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
    @media (min-width: 768px) { .adm-tools { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (min-width: 1200px) { .adm-tools { grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 16px; } }
    .adm-tool { display: flex; flex-direction: column; align-items: stretch; gap: 12px; padding: 16px; color: #fff; text-align: left; min-width: 0; transition: background .15s, border-color .15s, transform .15s; }
    /* Two columns on phones: a fifth tool spans the row instead of sitting alone. */
    @media (max-width: 767.98px) { .adm-tool:last-child:nth-child(odd) { grid-column: 1 / -1; } }
    .adm-tool:hover { background: rgba(255,255,255,.06); border-color: rgba(255,255,255,.15); color: #fff; transform: translateY(-2px); }
    .adm-tool-top { display: flex; align-items: center; justify-content: space-between; }
    .adm-tool-top > i { font-size: 1.35rem; color: #fff; }
    .adm-tool-top .ph-arrow-up-right { font-size: .95rem; color: #5d616a; }
    .adm-tool strong { display: block; font-size: .95rem; font-weight: 700; }
    .adm-tool small { display: flex; align-items: center; gap: 6px; color: var(--adm-muted); font-size: .76rem; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .adm-tool small .adm-dot { width: 6px; height: 6px; box-shadow: none; flex-shrink: 0; }
    .adm-tool small .adm-dot.info { background: var(--adm-cyan); }

    /* Lists */
    .adm-list { list-style: none; margin: 0; padding: 8px 12px 12px; }
    .adm-row { display: flex; align-items: center; gap: 12px; padding: 10px 8px; border-radius: 12px; min-width: 0; color: #fff; }
    a.adm-row:hover { background: rgba(255,255,255,.04); color: #fff; }
    .adm-list li + li { border-top: 1px solid var(--adm-line); }
    .adm-avatar { width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0; object-fit: cover; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: .95rem; }
    .adm-row-main { flex: 1; min-width: 0; }
    .adm-row-title { display: block; font-weight: 600; font-size: .9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .adm-row-sub { display: block; color: var(--adm-muted); font-size: .76rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .adm-row-end { flex-shrink: 0; display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
    .adm-pill { padding: 2px 9px; border-radius: 999px; font-size: .68rem; font-weight: 700; letter-spacing: .02em; }
    .adm-pill.admin { color: #ff6b73; background: rgba(var(--primary-rgb), .15); }
    .adm-pill.paid { color: var(--adm-amber); background: rgba(251,191,36,.12); }
    .adm-pill.free { color: var(--adm-muted); background: rgba(255,255,255,.06); }
    .adm-when { color: #62666f; font-size: .72rem; }
    .adm-poster { width: 40px; height: 60px; border-radius: 8px; flex-shrink: 0; object-fit: cover; background: rgba(255,255,255,.06); }
    .adm-rank { width: 18px; flex-shrink: 0; color: #62666f; font-weight: 700; font-size: .85rem; text-align: center; }
    .adm-meter { display: block; height: 4px; margin-top: 8px; border-radius: 4px; background: rgba(255,255,255,.06); overflow: hidden; }
    .adm-meter span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, var(--primary), #ff7a59); }
    .adm-views { font-weight: 700; font-size: .9rem; display: inline-flex; align-items: center; gap: 5px; }
    .adm-views i { color: var(--adm-muted); font-weight: 400; }
    .adm-empty { padding: 28px 20px; text-align: center; color: var(--adm-muted); font-size: .88rem; }
</style>

<div class="container">
<div class="adm">

    <!-- Header -->
    <header class="adm-top">
        <div>
            <p class="adm-eyebrow">Admin dashboard</p>
            <h1>Welcome back, <?php echo htmlspecialchars($adminName); ?></h1>
            <p><?php echo date('l, j F Y'); ?> &middot; here's how ZEN is doing.</p>
        </div>
        <div class="adm-top-actions">
            <a class="adm-chip" href="/admin-playback" title="What visitors see on the watch page">
                <span class="adm-dot<?php echo $playbackMode === PLAYBACK_SERVERS ? ' warning' : ''; ?>"></span>
                <?php echo $playbackMode === PLAYBACK_SERVERS ? 'Streaming servers on' : 'Discover mode'; ?>
            </a>
        </div>
    </header>

    <!-- Headline figures -->
    <section class="adm-kpis" aria-label="Headline figures">
        <div class="adm-card adm-kpi">
            <span class="adm-kpi-icon tone-green"><i class="ph ph-users-three"></i></span>
            <p class="adm-kpi-label">Members</p>
            <p class="adm-kpi-value"><?php echo number_format($totalUsers); ?></p>
            <span class="adm-kpi-note<?php echo $newUsersWeek ? ' up' : ''; ?>"><?php echo $newUsersWeek ? '+' . number_format($newUsersWeek) . ' this week' : 'No new members this week'; ?></span>
        </div>
        <div class="adm-card adm-kpi">
            <span class="adm-kpi-icon tone-amber"><i class="ph ph-crown"></i></span>
            <p class="adm-kpi-label">Paying members</p>
            <p class="adm-kpi-value"><?php echo number_format($payingNow); ?></p>
            <span class="adm-kpi-note"><?php echo $paymentsTotal ? number_format($paymentsTotal) . ' ' . ($paymentsTotal === 1 ? 'payment' : 'payments') . ' · $' . number_format($revenueTotal, 2) . ' all time' : 'No payments yet'; ?></span>
        </div>
        <div class="adm-card adm-kpi">
            <span class="adm-kpi-icon tone-cyan"><i class="ph ph-television-simple"></i></span>
            <p class="adm-kpi-label">Watching this week</p>
            <p class="adm-kpi-value"><?php echo number_format($viewersWeek); ?></p>
            <span class="adm-kpi-note"><?php echo number_format($titlesWeek); ?> <?php echo $titlesWeek === 1 ? 'title' : 'titles'; ?> watched</span>
        </div>
        <div class="adm-card adm-kpi">
            <span class="adm-kpi-icon tone-red"><i class="ph ph-eye"></i></span>
            <p class="adm-kpi-label">Title page views</p>
            <p class="adm-kpi-value"><?php echo number_format($totalViews); ?></p>
            <span class="adm-kpi-note"><?php echo number_format($totalWatchlist); ?> saved to watchlists</span>
        </div>
    </section>

    <!-- Activity and attention -->
    <div class="adm-grid split">
        <section class="adm-card" aria-labelledby="admActivity">
            <div class="adm-card-head">
                <div>
                    <h2 id="admActivity">Last 14 days</h2>
                    <span class="adm-sub"><?php echo number_format($signups14); ?> new <?php echo $signups14 === 1 ? 'member' : 'members'; ?></span>
                </div>
                <div class="adm-legend" aria-hidden="true">
                    <span><i style="background: var(--primary);"></i>Sign-ups</span>
                    <span><i style="background: var(--adm-cyan);"></i>Viewers</span>
                </div>
            </div>
            <div class="adm-chart" role="img" aria-label="Sign-ups and viewers per day over the last 14 days">
                <?php foreach ($days as $day => $d):
                    $label = date('D j M', strtotime($day)) . ': ' . $d['signups'] . ' sign-up' . ($d['signups'] === 1 ? '' : 's') . ', ' . $d['viewers'] . ' viewer' . ($d['viewers'] === 1 ? '' : 's');
                ?>
                <div class="adm-day" title="<?php echo htmlspecialchars($label); ?>">
                    <span class="adm-bar signups<?php echo $d['signups'] ? '' : ' zero'; ?>" style="height: <?php echo round($d['signups'] / $chartMax * 100, 1); ?>%;"></span>
                    <span class="adm-bar viewers<?php echo $d['viewers'] ? '' : ' zero'; ?>" style="height: <?php echo round($d['viewers'] / $chartMax * 100, 1); ?>%;"></span>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="adm-days" aria-hidden="true">
                <?php foreach (array_keys($days) as $i => $day): ?>
                <span><?php echo $i === 13 ? 'Today' : date('j', strtotime($day)); ?></span>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="adm-card" aria-labelledby="admAttention">
            <div class="adm-card-head">
                <h2 id="admAttention">Needs attention</h2>
                <?php if ($attention): ?><span class="adm-sub"><?php echo count($attention); ?></span><?php endif; ?>
            </div>
            <?php if ($attention): ?>
            <ul class="adm-attn">
                <?php foreach ($attention as [$tone, $icon, $text, $href, $action]):
                    $toneClass = ['warning' => 'tone-amber', 'danger' => 'tone-red', 'info' => 'tone-cyan'][$tone];
                ?>
                <li>
                    <span class="adm-attn-icon <?php echo $toneClass; ?>"><i class="ph <?php echo $icon; ?>"></i></span>
                    <span class="adm-attn-text"><?php echo htmlspecialchars($text); ?></span>
                    <a class="adm-attn-go" href="<?php echo $href; ?>"><?php echo $action; ?></a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <div class="adm-clear">
                <i class="ph ph-check-circle"></i>
                All clear. Nothing needs you right now.
            </div>
            <?php endif; ?>
        </section>
    </div>

    <!-- Tools -->
    <nav class="adm-tools" aria-label="Admin tools">
        <?php foreach ($tools as [$href, $icon, $name, $status, $state]): ?>
        <a class="adm-card adm-tool" href="<?php echo $href; ?>">
            <span class="adm-tool-top"><i class="ph <?php echo $icon; ?>"></i><i class="ph ph-arrow-up-right"></i></span>
            <span>
                <strong><?php echo $name; ?></strong>
                <small><?php if ($state): ?><span class="adm-dot <?php echo $state; ?>"></span><?php endif; ?><?php echo htmlspecialchars($status); ?></small>
            </span>
        </a>
        <?php endforeach; ?>
    </nav>

    <!-- Lists -->
    <div class="adm-grid halves">
        <section class="adm-card" aria-labelledby="admSignups">
            <div class="adm-card-head">
                <h2 id="admSignups">Newest members</h2>
                <a class="adm-link" href="/admin-view-users">All members <i class="ph ph-caret-right"></i></a>
            </div>
            <?php if ($recentUsers): ?>
            <ul class="adm-list">
                <?php foreach ($recentUsers as $u):
                    $name = $u['username'] ?: 'Member';
                    $hue = crc32($name) % 360;
                ?>
                <li class="adm-row">
                    <?php if (!empty($u['avatar_url'])): ?>
                    <img class="adm-avatar" src="<?php echo htmlspecialchars($u['avatar_url']); ?>" alt="" loading="lazy">
                    <?php else: ?>
                    <span class="adm-avatar" style="background: hsla(<?php echo $hue; ?>, 70%, 55%, .18); color: hsl(<?php echo $hue; ?>, 80%, 72%);"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($name, 0, 1))); ?></span>
                    <?php endif; ?>
                    <span class="adm-row-main">
                        <span class="adm-row-title"><?php echo htmlspecialchars($name); ?></span>
                        <span class="adm-row-sub"><?php echo htmlspecialchars($u['email']); ?></span>
                    </span>
                    <span class="adm-row-end">
                        <?php if ((int) $u['is_admin'] === 1): ?>
                        <span class="adm-pill admin">Admin</span>
                        <?php elseif (!empty($u['plan'])): ?>
                        <span class="adm-pill paid"><?php echo htmlspecialchars(ucfirst($u['plan'])); ?></span>
                        <?php else: ?>
                        <span class="adm-pill free">Free</span>
                        <?php endif; ?>
                        <span class="adm-when"><?php echo htmlspecialchars(admTimeAgo($u['created_at'])); ?></span>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="adm-empty">No members yet.</p>
            <?php endif; ?>
        </section>

        <section class="adm-card" aria-labelledby="admTop">
            <div class="adm-card-head">
                <h2 id="admTop">Most viewed titles</h2>
                <span class="adm-sub">Title page views</span>
            </div>
            <?php if ($topContent): ?>
            <ul class="adm-list">
                <?php foreach ($topContent as $rank => $tc):
                    $type = $tc['media_type'] === 'tv' ? 'tv' : 'movie';
                    $info = function_exists('fetchTmdbApi') ? fetchTmdbApi("{$type}/{$tc['tmdb_id']}") : null;
                    $title = $info['title'] ?? $info['name'] ?? 'Title #' . (int) $tc['tmdb_id'];
                    $year = substr($info['release_date'] ?? $info['first_air_date'] ?? '', 0, 4);
                ?>
                <li>
                    <a class="adm-row" href="/<?php echo $type; ?>/<?php echo (int) $tc['tmdb_id']; ?>">
                        <span class="adm-rank"><?php echo $rank + 1; ?></span>
                        <?php if (!empty($info['poster_path'])): ?>
                        <img class="adm-poster" src="https://image.tmdb.org/t/p/w92<?php echo htmlspecialchars($info['poster_path']); ?>" alt="" loading="lazy">
                        <?php else: ?>
                        <span class="adm-poster"></span>
                        <?php endif; ?>
                        <span class="adm-row-main">
                            <span class="adm-row-title"><?php echo htmlspecialchars($title); ?></span>
                            <span class="adm-row-sub"><?php echo $type === 'tv' ? 'TV show' : 'Movie'; ?><?php echo $year ? ' · ' . htmlspecialchars($year) : ''; ?></span>
                            <span class="adm-meter"><span style="width: <?php echo round((int) $tc['views'] / $topMax * 100, 1); ?>%;"></span></span>
                        </span>
                        <span class="adm-row-end">
                            <span class="adm-views"><i class="ph ph-eye"></i><?php echo number_format((int) $tc['views']); ?></span>
                        </span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="adm-empty">No title views recorded yet.</p>
            <?php endif; ?>
        </section>
    </div>

</div>
</div>

  <script src="/da/assets/js/vendor-all.min.js"></script>
  <script src="/da/assets/plugins/bootstrap/js/bootstrap.min.js"></script>
</body>
</html>
