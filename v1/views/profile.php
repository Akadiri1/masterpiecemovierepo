<?php
if (!isset($_SESSION['user_id'])) {
    $currentUrl = urlencode($_SERVER['REQUEST_URI']);
    header('Location: /login?next=' . $currentUrl);
    exit;
}

// ==========================================
// 2. FETCH USER DATA
// ==========================================
$user = null;
if (isset($conn)) {
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            session_destroy();
            $currentUrl = urlencode($_SERVER['REQUEST_URI']);
            header('Location: /login?next=' . $currentUrl);
            exit;
        }
    } catch (PDOException $e) {
        error_log("Profile DB Error: " . $e->getMessage());
        include 'includes/header.php';
        echo '<div class="container mt-5 text-center text-white"><h3>System Error</h3><p>Could not load profile.</p></div>';
        include 'includes/footer.php';
        exit;
    }
}

// Prepare Display Data
$user['avatar_path'] = !empty($user['avatar_url']) ? $user['avatar_url'] : 'assets/images/user/user6.jpg';
$user['fullName'] = trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''));
if (empty($user['fullName'])) {
    $user['fullName'] = $user['username'];
}

// ==========================================
// 3. FETCH WATCHLIST
// ==========================================
$watchlistItems = [];
if (isset($conn)) {
    try {
        $wSql = "SELECT tmdb_movie_id, media_type FROM watchlist WHERE user_id = ? ORDER BY id DESC";
        $wStmt = $conn->prepare($wSql);
        $wStmt->execute([$_SESSION['user_id']]);
        $savedIds = $wStmt->fetchAll(PDO::FETCH_ASSOC);

        if(function_exists('fetchTmdbApi')) {
            // Download every title's details in parallel first; the loop below
            // then reads them from the cache instead of one request at a time.
            if (function_exists('prefetchTmdbApi')) {
                prefetchTmdbApi(array_map(function ($item) {
                    return ["{$item['media_type']}/{$item['tmdb_movie_id']}", []];
                }, $savedIds));
            }

            foreach ($savedIds as $item) {
                $tId = $item['tmdb_movie_id'];
                $tType = $item['media_type'];

                // Fetch minimal details
                $data = fetchTmdbApi("{$tType}/{$tId}");

                if ($data) {
                    $title = $data['title'] ?? $data['name'] ?? 'Unknown';
                    // w342 is sharp at the grid's size on any screen.
                    $poster = !empty($data['poster_path']) ? 'https://image.tmdb.org/t/p/w342'.$data['poster_path'] : '/assets/images/media/placeholder-portrait.svg';
                    $date = $data['release_date'] ?? $data['first_air_date'] ?? '';
                    $year = $date ? substr($date, 0, 4) : 'N/A';
                    $vote = $data['vote_average'] ?? 0;

                    $watchlistItems[] = [
                        'id' => $tId,
                        'type' => $tType,
                        'title' => $title,
                        'poster' => $poster,
                        'year' => $year,
                        'vote' => $vote
                    ];
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Watchlist Error: " . $e->getMessage());
    }
}

// ==========================================
// 4. WATCH STATS
// ==========================================
$watchedCount = 0;
if (isset($conn)) {
    try {
        $hStmt = $conn->prepare("SELECT COUNT(DISTINCT tmdb_movie_id, media_type) FROM watch_history WHERE user_id = ?");
        $hStmt->execute([$_SESSION['user_id']]);
        $watchedCount = (int) $hStmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Watch history count error: " . $e->getMessage());
    }
}
$memberSince = !empty($user['created_at']) ? date('M Y', strtotime($user['created_at'])) : null;

// ==========================================
// 4b. WATCH HISTORY (the History tab)
// ==========================================
if (!function_exists('historyDateLabel')) {
    /** "Today", "Yesterday", "Sep 12", or "Sep 12, 2025" for earlier years. */
    function historyDateLabel(?string $datetime): string
    {
        if (!$datetime || !($time = strtotime($datetime))) {
            return '';
        }
        $day = date('Y-m-d', $time);
        if ($day === date('Y-m-d')) return 'Today';
        if ($day === date('Y-m-d', strtotime('-1 day'))) return 'Yesterday';
        return date(date('Y', $time) === date('Y') ? 'M j' : 'M j, Y', $time);
    }
}

$historyItems = [];
if (isset($conn)) {
    try {
        $hStmt = $conn->prepare("SELECT tmdb_movie_id, media_type, `current_time`, total_duration, last_watched FROM watch_history WHERE user_id = ? ORDER BY last_watched DESC LIMIT 100");
        $hStmt->execute([$_SESSION['user_id']]);
        $historyRows = $hStmt->fetchAll(PDO::FETCH_ASSOC);

        if ($historyRows && function_exists('fetchTmdbApi')) {
            // Details for every title in parallel first (same requests as the watchlist).
            if (function_exists('prefetchTmdbApi')) {
                prefetchTmdbApi(array_map(fn($r) => [($r['media_type'] ?: 'movie') . '/' . $r['tmdb_movie_id'], []], $historyRows));
            }
            foreach ($historyRows as $row) {
                $type = $row['media_type'] ?: 'movie';
                $data = fetchTmdbApi("{$type}/{$row['tmdb_movie_id']}");
                if (!$data) {
                    continue;
                }
                $watched = (float) $row['current_time'];
                $total = (float) $row['total_duration'];
                $percent = $total > 0 ? (int) min(100, round($watched / $total * 100)) : 0;
                $date = $data['release_date'] ?? $data['first_air_date'] ?? '';
                $historyItems[] = [
                    'id'       => (int) $row['tmdb_movie_id'],
                    'type'     => $type,
                    'title'    => $data['title'] ?? $data['name'] ?? 'Untitled',
                    'image'    => !empty($data['backdrop_path'])
                                  ? 'https://image.tmdb.org/t/p/w300' . $data['backdrop_path']
                                  : (!empty($data['poster_path']) ? 'https://image.tmdb.org/t/p/w185' . $data['poster_path'] : '/assets/images/media/placeholder.svg'),
                    'year'     => $date ? substr($date, 0, 4) : '',
                    'percent'  => $percent,
                    'progress' => $percent >= 95 ? 'Watched' : ($total > $watched && $watched > 0 ? ceil(($total - $watched) / 60) . ' min left' : 'Started'),
                    'when'     => historyDateLabel($row['last_watched']),
                ];
            }
        }
    } catch (PDOException $e) {
        error_log("Watch history list error: " . $e->getMessage());
    }
}

// ==========================================
// 5. FETCH SUBSCRIPTION
// ==========================================
$subscription = [];
$planLabel = 'Free Plan';
$isFree = true;
$planExpiry = 'N/A';

if (isset($conn)) {
    $subSql = "SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active' AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1";
    $subStmt = $conn->prepare($subSql);
    $subStmt->execute([$_SESSION['user_id']]);
    $subscription = $subStmt->fetch(PDO::FETCH_ASSOC);

    if ($subscription) {
        $planLabel = $subscription['plan_name'];
        $isFree = false;
        $planExpiry = date('F j, Y', strtotime($subscription['expires_at']));
    }
}

include 'includes/header.php';
?>

<!-- ==========================================
     PROFILE PAGE STYLES
     Mobile first: who you are and your numbers on top, then a tab bar that
     scrolls sideways on narrow screens, then the tab content.
     ========================================== -->
<style>
    /* The edit dialog lives outside .zp (Bootstrap modals sit at the end of
       the page), so it needs the same colour variables. */
    .zp,
    #edit-profile-modal {
        --zp-card: rgba(255, 255, 255, 0.035);
        --zp-line: rgba(255, 255, 255, 0.08);
        --zp-muted: #9a9aa8;
        --zp-accent: var(--primary, #e50914);
    }
    .zp {
        max-width: 1180px;
        margin: 0 auto;
        padding: 16px 16px 40px;
        color: #fff;
    }
    @media (min-width: 768px) { .zp { padding: 28px 24px 56px; } }

    /* 1. Identity card */
    .zp-hero { position: relative; overflow: hidden; border-radius: 20px; border: 1px solid var(--zp-line); background: #101018; }
    .zp-cover {
        height: 96px;
        background: linear-gradient(135deg, #3a0d12 0%, #1b1030 55%, #101018 100%);
        background:
            radial-gradient(120% 140% at 0% 0%, color-mix(in srgb, var(--zp-accent) 55%, transparent) 0%, transparent 60%),
            radial-gradient(100% 120% at 100% 0%, rgba(123, 44, 191, 0.45) 0%, transparent 55%),
            linear-gradient(180deg, #1a1a26, #101018);
    }
    .zp-identity { display: flex; flex-direction: column; align-items: center; gap: 12px; margin-top: -48px; padding: 0 20px 20px; text-align: center; }
    .zp-avatar-wrap { position: relative; flex-shrink: 0; }
    .zp-avatar { display: block; width: 96px; height: 96px; border-radius: 50%; object-fit: cover; border: 4px solid #101018; background: #1c1c26; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.45); }
    .zp-avatar-edit {
        position: absolute; right: 0; bottom: 2px;
        width: 32px; height: 32px; padding: 0;
        display: grid; place-items: center;
        border-radius: 50%; border: 3px solid #101018;
        background: var(--zp-accent); color: #fff; font-size: 0.95rem; cursor: pointer;
    }
    .zp-id-text { min-width: 0; }
    .zp-name { margin: 0; font-size: 1.5rem; font-weight: 800; letter-spacing: -0.3px; color: #fff; word-break: break-word; }
    .zp-email { margin: 2px 0 0; color: var(--zp-muted); font-size: 0.9rem; word-break: break-all; }
    .zp-badges { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; margin-top: 10px; }
    .zp-badge { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 999px; border: 1px solid var(--zp-line); background: rgba(255, 255, 255, 0.07); color: #ddd; font-size: 0.78rem; font-weight: 600; }
    .zp-badge.is-premium { background: linear-gradient(135deg, #f5c451, #e39b1b); border-color: transparent; color: #1b1300; }
    .zp-edit { display: inline-flex; align-items: center; gap: 8px; padding: 9px 18px; border-radius: 12px; border: 1px solid var(--zp-line); background: rgba(255, 255, 255, 0.06); color: #fff; font-size: 0.9rem; font-weight: 600; cursor: pointer; }
    .zp-edit:hover { background: rgba(255, 255, 255, 0.12); }

    .zp-stats { display: grid; grid-template-columns: repeat(3, 1fr); border-top: 1px solid var(--zp-line); }
    .zp-stat { padding: 14px 8px; text-align: center; }
    .zp-stat + .zp-stat { border-left: 1px solid var(--zp-line); }
    .zp-stat strong { display: block; font-size: 1.15rem; font-weight: 800; color: #fff; }
    .zp-stat span { color: var(--zp-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; }

    @media (min-width: 768px) {
        .zp-cover { height: 140px; }
        .zp-identity { flex-direction: row; align-items: flex-end; gap: 20px; margin-top: -56px; padding: 0 28px 24px; text-align: left; }
        .zp-avatar { width: 120px; height: 120px; }
        .zp-id-text { flex: 1; padding-bottom: 6px; }
        .zp-badges { justify-content: flex-start; }
        .zp-name { font-size: 1.9rem; }
        .zp-edit { margin-bottom: 8px; }
    }

    /* 2. Tabs */
    .zp-tabs { display: flex; flex-wrap: nowrap; gap: 6px; margin: 20px 0; padding: 4px; overflow-x: auto; scrollbar-width: none; border-radius: 14px; border: 1px solid var(--zp-line); background: var(--zp-card); }
    .zp-tabs::-webkit-scrollbar { display: none; }
    .zp-tabs .nav-link { flex: 1 0 auto; display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 0; padding: 10px 14px; border: 0; border-radius: 10px; background: transparent; color: var(--zp-muted); font-size: 0.88rem; font-weight: 600; white-space: nowrap; }
    .zp-tabs .nav-link:hover { color: #fff; }
    .zp-tabs .nav-link.active { background: rgba(255, 255, 255, 0.1); color: #fff; }
    .zp-tabs .nav-link i { font-size: 1.1rem; }
    /* Phones: equal tabs with the icon above a short label, so every tab is
       visible without scrolling the bar sideways. */
    .zp-tab-short { display: none; }
    @media (max-width: 575.98px) {
        .zp-tabs { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 4px; overflow: visible; }
        .zp-tabs .nav-link { flex-direction: column; gap: 3px; padding: 8px 2px; font-size: 0.7rem; }
        .zp-tabs .nav-link i { font-size: 1.25rem; }
        .zp-tab-long { display: none; }
        .zp-tab-short { display: inline; }
    }

    .zp-section-head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
    .zp-section-title { margin: 0; font-size: 1.15rem; font-weight: 700; color: #fff; }
    .zp-section-sub { color: var(--zp-muted); font-size: 0.85rem; }

    /* 3. Watchlist grid */
    .zp-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px 10px; }
    @media (min-width: 576px) { .zp-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 20px 14px; } }
    @media (min-width: 992px) { .zp-grid { grid-template-columns: repeat(6, minmax(0, 1fr)); } }
    .zp-card { position: relative; min-width: 0; transition: opacity 0.3s; }
    .zp a.zp-poster { position: relative; display: block; aspect-ratio: 2 / 3; min-height: 0; overflow: hidden; border-radius: 12px; background: #1a1a24; }
    .zp-poster img { display: block; width: 100%; height: 100%; object-fit: cover; transition: transform 0.35s; }
    .zp-card:hover .zp-poster img { transform: scale(1.04); }
    .zp-rating { position: absolute; left: 6px; top: 6px; display: inline-flex; align-items: center; gap: 3px; padding: 2px 6px; border-radius: 6px; background: rgba(0, 0, 0, 0.65); color: #ffd35c; font-size: 0.68rem; font-weight: 700; backdrop-filter: blur(4px); }
    .zp-remove { position: absolute; right: 6px; top: 6px; z-index: 2; width: 28px; height: 28px; padding: 0; display: grid; place-items: center; border: 0; border-radius: 50%; background: rgba(0, 0, 0, 0.6); color: #fff; font-size: 0.85rem; backdrop-filter: blur(4px); cursor: pointer; }
    .zp-remove:hover { background: var(--zp-accent); }
    .zp a.zp-card-title { display: block; min-height: 0; margin-top: 8px; overflow: hidden; color: #fff; font-size: 0.85rem; font-weight: 600; white-space: nowrap; text-overflow: ellipsis; text-decoration: none; }
    .zp-card-meta { color: var(--zp-muted); font-size: 0.75rem; }

    /* 4. Panels, empty states and buttons */
    .zp-panel { padding: 20px; border-radius: 18px; border: 1px solid var(--zp-line); background: var(--zp-card); }
    .zp-panel + .zp-panel { margin-top: 16px; }
    @media (min-width: 768px) { .zp-panel { padding: 28px; } }
    .zp-empty { padding: 36px 20px; text-align: center; }
    .zp-empty-icon { width: 60px; height: 60px; margin: 0 auto 14px; display: grid; place-items: center; border-radius: 50%; background: rgba(255, 255, 255, 0.06); color: var(--zp-accent); font-size: 1.7rem; }
    .zp-empty h4 { margin: 0 0 6px; color: #fff; font-size: 1.05rem; font-weight: 700; }
    .zp-empty p { max-width: 320px; margin: 0 auto 18px; color: var(--zp-muted); font-size: 0.9rem; }
    .zp-empty p:last-child { margin-bottom: 0; }
    .zp-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 11px 20px; border-radius: 12px; border: 1px solid transparent; font-size: 0.9rem; font-weight: 700; text-decoration: none; cursor: pointer; }
    .zp-btn-primary { background: var(--zp-accent); color: #fff; }
    .zp-btn-primary:hover { filter: brightness(1.08); color: #fff; }
    .zp-btn-ghost { border-color: var(--zp-line); background: transparent; color: #ddd; }
    .zp-btn-ghost:hover { background: rgba(255, 255, 255, 0.06); color: #fff; }
    .zp-btn-danger { border-color: rgba(255, 90, 90, 0.35); background: transparent; color: #ff8080; }
    .zp-btn-danger:hover { background: rgba(255, 90, 90, 0.1); color: #ffb3b3; }

    /* 5. Membership */
    .zp-plan-top { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 10px; }
    .zp-eyebrow { color: var(--zp-muted); font-size: 0.72rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; }
    .zp-status { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 999px; border: 1px solid rgba(74, 222, 128, 0.3); background: rgba(74, 222, 128, 0.1); color: #4ade80; font-size: 0.75rem; font-weight: 700; }
    .zp-status::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
    .zp-plan-name { margin: 0 0 6px; color: #fff; font-size: 1.7rem; font-weight: 800; letter-spacing: -0.5px; }
    .zp-plan-text { margin: 0 0 18px; color: var(--zp-muted); font-size: 0.92rem; }
    .zp-plan-text:last-child { margin-bottom: 0; }
    .zp-plan.is-premium { border-color: color-mix(in srgb, var(--zp-accent) 40%, transparent); background: linear-gradient(135deg, color-mix(in srgb, var(--zp-accent) 22%, #12121a), #12121a 70%); }
    .zp-list { margin: 0; padding: 0; list-style: none; }
    .zp-list li { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 0; border-bottom: 1px solid var(--zp-line); font-size: 0.9rem; }
    .zp-list li:last-child { border-bottom: 0; padding-bottom: 0; }
    .zp-list-main { color: #fff; font-weight: 600; }
    .zp-list-sub { display: block; color: var(--zp-muted); font-size: 0.8rem; font-weight: 400; }
    .zp-paid { display: inline-flex; align-items: center; gap: 4px; color: #4ade80; font-size: 0.82rem; font-weight: 600; }

    /* 6. Parental controls and forms */
    .zp-feature { display: flex; align-items: flex-start; gap: 14px; margin-bottom: 20px; }
    .zp-feature-icon { flex-shrink: 0; width: 44px; height: 44px; display: grid; place-items: center; border-radius: 12px; background: rgba(255, 255, 255, 0.06); color: var(--zp-accent); font-size: 1.3rem; }
    .zp-feature h5 { margin: 0 0 4px; color: #fff; font-size: 1rem; font-weight: 700; }
    .zp-feature p { margin: 0; color: var(--zp-muted); font-size: 0.88rem; }
    .zp-form-grid { display: grid; gap: 14px; }
    @media (min-width: 576px) { .zp-form-grid { grid-template-columns: 1fr 1fr; } }
    .zp-label, #edit-profile-modal .form-label { display: block; margin-bottom: 6px; color: #bbb; font-size: 0.8rem; font-weight: 600; }
    .zp-input, .form-control-dark { width: 100%; padding: 12px 14px; border-radius: 12px; border: 1px solid #2a2a36; background: #0c0c12; color: #fff; font-size: 1rem; }
    .zp-input:focus, .form-control-dark:focus { outline: none; border-color: var(--zp-accent); background: #0c0c12; color: #fff; box-shadow: 0 0 0 3px color-mix(in srgb, var(--zp-accent) 25%, transparent); }
    .zp-form-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
    .zp-form-row { margin-bottom: 14px; }
    .zp-pin-input { letter-spacing: 0.3em; font-size: 1.1rem; }
    .zp-pin-input::placeholder { color: #444450; }
    .zp-form-message { min-height: 1.3em; margin: 12px 0 0; font-size: 0.88rem; }
    .zp-form-message.is-ok { color: #86efac; }
    .zp-form-message.is-error { color: #ff8080; }
    .zp-form-message + .zp-form-actions { margin-top: 10px; }
    .zp-pin-state { display: inline-flex; align-items: center; gap: 4px; margin-left: 6px; padding: 1px 8px; border-radius: 999px; background: rgba(74, 222, 128, 0.12); color: #4ade80; font-size: 0.7rem; font-weight: 700; vertical-align: middle; }

    /* History */
    .zp-text-btn { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border: 0; border-radius: 10px; background: transparent; color: #ff8080; font-size: 0.85rem; font-weight: 600; }
    .zp-text-btn:hover { background: rgba(255, 90, 90, 0.1); }
    .zp-history { display: flex; flex-direction: column; gap: 10px; margin: 0; padding: 0; list-style: none; }
    .zp-history-item { display: flex; align-items: center; gap: 12px; padding: 8px; border-radius: 14px; border: 1px solid var(--zp-line); background: var(--zp-card); transition: opacity 0.25s, transform 0.25s; }
    .zp-history-item.is-removing { opacity: 0; transform: translateX(16px); }
    .zp a.zp-history-thumb { position: relative; flex-shrink: 0; display: block; width: 128px; min-height: 0; aspect-ratio: 16 / 9; overflow: hidden; border-radius: 10px; background: #1a1a24; }
    .zp-history-thumb img { display: block; width: 100%; height: 100%; object-fit: cover; }
    .zp-history-bar { position: absolute; left: 0; right: 0; bottom: 0; height: 3px; background: rgba(255, 255, 255, 0.22); }
    .zp-history-bar span { display: block; height: 100%; background: var(--zp-accent); }
    .zp-history-info { display: flex; flex: 1; flex-direction: column; gap: 2px; min-width: 0; }
    .zp a.zp-history-title { display: block; min-height: 0; overflow: hidden; color: #fff; font-size: 0.92rem; font-weight: 600; white-space: nowrap; text-overflow: ellipsis; text-decoration: none; }
    .zp-history-meta { overflow: hidden; color: var(--zp-muted); font-size: 0.76rem; white-space: nowrap; text-overflow: ellipsis; }
    .zp-history-remove { flex-shrink: 0; display: grid; place-items: center; width: 36px; height: 36px; padding: 0; border: 0; border-radius: 10px; background: transparent; color: var(--zp-muted); font-size: 1rem; }
    .zp-history-remove:hover { background: rgba(255, 255, 255, 0.08); color: #fff; }
    @media (max-width: 575.98px) { .zp a.zp-history-thumb { width: 104px; } }

    .zp-signout { display: flex; justify-content: center; margin-top: 28px; }

    /* 7. Edit profile dialog */
    #edit-profile-modal .modal-content { border-radius: 18px; border: 1px solid rgba(255, 255, 255, 0.08); background: #12121a; color: #fff; }
    #edit-profile-modal .modal-header { border-color: rgba(255, 255, 255, 0.08); }
    #edit-profile-modal .zp-avatar { border-color: #12121a; }
    #edit-profile-modal .zp-avatar-edit { border-color: #12121a; }
    .zp-modal-avatar { display: flex; flex-direction: column; align-items: center; gap: 8px; margin-bottom: 20px; }
    .zp-link-danger { padding: 4px; border: 0; background: none; color: #ff7b7b; font-size: 0.85rem; font-weight: 600; cursor: pointer; }

    .tab-pane { animation: zpFade 0.3s ease; }
    @keyframes zpFade { from { opacity: 0; } to { opacity: 1; } }
</style>

<!-- ==========================================
     HTML STRUCTURE
     ========================================== -->
<div class="zp">

    <!-- 1. IDENTITY -->
    <section class="zp-hero">
        <div class="zp-cover" aria-hidden="true"></div>
        <div class="zp-identity">
            <div class="zp-avatar-wrap">
                <img src="<?php echo htmlspecialchars($user['avatar_path']); ?>" alt="" class="zp-avatar" id="profile-header-avatar">
                <button type="button" class="zp-avatar-edit" data-bs-toggle="modal" data-bs-target="#edit-profile-modal" title="Change photo" aria-label="Change photo">
                    <i class="ph ph-camera"></i>
                </button>
            </div>

            <div class="zp-id-text">
                <h1 class="zp-name" id="profile-header-name"><?php echo htmlspecialchars($user['fullName']); ?></h1>
                <p class="zp-email" id="profile-header-email"><?php echo htmlspecialchars($user['email']); ?></p>
                <div class="zp-badges">
                    <span class="zp-badge <?php echo $isFree ? '' : 'is-premium'; ?>">
                        <i class="<?php echo $isFree ? 'ph ph-user' : 'ph-fill ph-crown'; ?>"></i>
                        <?php echo $isFree ? 'Free member' : 'Premium member'; ?>
                    </span>
                    <?php if ($memberSince): ?>
                    <span class="zp-badge"><i class="ph ph-calendar-blank"></i> Since <?php echo $memberSince; ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <button type="button" class="zp-edit" data-bs-toggle="modal" data-bs-target="#edit-profile-modal">
                <i class="ph ph-pencil-simple"></i> Edit profile
            </button>
        </div>

        <div class="zp-stats">
            <div class="zp-stat"><strong id="zp-watchlist-count"><?php echo count($watchlistItems); ?></strong><span>Watchlist</span></div>
            <div class="zp-stat"><strong id="zp-watched-count"><?php echo $watchedCount; ?></strong><span>Watched</span></div>
            <div class="zp-stat"><strong><?php echo $isFree ? 'Free' : 'Premium'; ?></strong><span>Plan</span></div>
        </div>
    </section>

    <!-- 2. TABS -->
    <nav class="nav zp-tabs" id="v-pills-tab" role="tablist">
        <a class="nav-link active" id="v-pills-watchlist-tab" data-bs-toggle="pill" href="#v-pills-watchlist" role="tab" aria-controls="v-pills-watchlist" aria-selected="true">
            <i class="ph ph-bookmark-simple"></i> Watchlist
        </a>
        <a class="nav-link" id="v-pills-history-tab" data-bs-toggle="pill" href="#v-pills-history" role="tab" aria-controls="v-pills-history" aria-selected="false">
            <i class="ph ph-clock-counter-clockwise"></i> History
        </a>
        <a class="nav-link" id="v-pills-membership-tab" data-bs-toggle="pill" href="#v-pills-membership" role="tab" aria-controls="v-pills-membership" aria-selected="false">
            <i class="ph ph-credit-card"></i> <span class="zp-tab-long">Membership</span><span class="zp-tab-short">Plan</span>
        </a>
        <a class="nav-link" id="v-pills-parental-tab" data-bs-toggle="pill" href="#v-pills-parental" role="tab" aria-controls="v-pills-parental" aria-selected="false">
            <i class="ph ph-shield-check"></i> <span class="zp-tab-long">Parental controls</span><span class="zp-tab-short">Parental</span>
        </a>
        <a class="nav-link" id="v-pills-playlist-tab" data-bs-toggle="pill" href="#v-pills-playlist" role="tab" aria-controls="v-pills-playlist" aria-selected="false">
            <i class="ph ph-playlist"></i> Playlists
        </a>
    </nav>

    <!-- 3. TAB CONTENT -->
    <div class="tab-content" id="v-pills-tabContent">

        <!-- WATCHLIST -->
        <div class="tab-pane fade show active" id="v-pills-watchlist" role="tabpanel" aria-labelledby="v-pills-watchlist-tab">
            <?php if (empty($watchlistItems)): ?>
                <div class="zp-panel zp-empty">
                    <div class="zp-empty-icon"><i class="ph ph-film-strip"></i></div>
                    <h4>Your watchlist is empty</h4>
                    <p>Save movies and shows you want to watch later and they'll show up here.</p>
                    <a href="/" class="zp-btn zp-btn-primary">Browse titles</a>
                </div>
            <?php else: ?>
                <div class="zp-section-head">
                    <h3 class="zp-section-title">My watchlist</h3>
                    <span class="zp-section-sub" id="zp-watchlist-total"><?php echo count($watchlistItems) === 1 ? '1 title' : count($watchlistItems) . ' titles'; ?></span>
                </div>
                <div class="zp-grid" id="zp-watchlist-grid">
                    <?php foreach ($watchlistItems as $item):
                        $link = htmlspecialchars('/' . $item['type'] . '/' . $item['id']);
                    ?>
                    <div class="zp-card">
                        <a href="<?php echo $link; ?>" class="zp-poster">
                            <img src="<?php echo htmlspecialchars($item['poster']); ?>" loading="lazy" decoding="async" alt="<?php echo htmlspecialchars($item['title']); ?>">
                            <?php if ($item['vote'] > 0): ?>
                            <span class="zp-rating"><i class="ph-fill ph-star"></i> <?php echo number_format($item['vote'], 1); ?></span>
                            <?php endif; ?>
                        </a>
                        <button type="button" class="zp-remove watchlist-remove-btn" data-id="<?php echo (int) $item['id']; ?>" data-type="<?php echo htmlspecialchars($item['type']); ?>" title="Remove from watchlist" aria-label="Remove <?php echo htmlspecialchars($item['title']); ?> from watchlist">
                            <i class="ph ph-x"></i>
                        </button>
                        <a href="<?php echo $link; ?>" class="zp-card-title"><?php echo htmlspecialchars($item['title']); ?></a>
                        <span class="zp-card-meta"><?php echo htmlspecialchars($item['year']); ?> · <?php echo $item['type'] === 'tv' ? 'Series' : 'Movie'; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- HISTORY -->
        <div class="tab-pane fade" id="v-pills-history" role="tabpanel" aria-labelledby="v-pills-history-tab">
            <div class="zp-panel zp-empty" id="zpHistoryEmpty"<?php echo $historyItems ? ' hidden' : ''; ?>>
                <div class="zp-empty-icon"><i class="ph ph-clock-counter-clockwise"></i></div>
                <h4>No watch history</h4>
                <p>Movies and episodes you start show up here, so you can pick up where you left off.</p>
                <a href="/" class="zp-btn zp-btn-primary">Browse titles</a>
            </div>
            <?php if ($historyItems): ?>
            <div class="zp-section-head" id="zpHistoryHead">
                <h3 class="zp-section-title">Watch history</h3>
                <button type="button" class="zp-text-btn" id="zpHistoryClear"><i class="ph ph-trash"></i> Clear all</button>
            </div>
            <ul class="zp-history" id="zpHistoryList">
                <?php foreach ($historyItems as $h):
                    $hLink = htmlspecialchars('/' . $h['type'] . '/' . $h['id']);
                ?>
                <li class="zp-history-item" data-id="<?php echo $h['id']; ?>" data-type="<?php echo htmlspecialchars($h['type']); ?>">
                    <a class="zp-history-thumb" href="<?php echo $hLink; ?>">
                        <img src="<?php echo htmlspecialchars($h['image']); ?>" alt="" loading="lazy" decoding="async">
                        <?php if ($h['percent'] > 0): ?>
                        <span class="zp-history-bar"><span style="width: <?php echo $h['percent']; ?>%;"></span></span>
                        <?php endif; ?>
                    </a>
                    <div class="zp-history-info">
                        <a class="zp-history-title" href="<?php echo $hLink; ?>"><?php echo htmlspecialchars($h['title']); ?></a>
                        <span class="zp-history-meta"><?php echo $h['type'] === 'tv' ? 'Series' : 'Movie'; ?><?php echo $h['year'] ? ' · ' . htmlspecialchars($h['year']) : ''; ?></span>
                        <span class="zp-history-meta"><?php echo htmlspecialchars($h['progress']); ?><?php echo $h['when'] ? ' · ' . htmlspecialchars($h['when']) : ''; ?></span>
                    </div>
                    <button type="button" class="zp-history-remove" aria-label="Remove <?php echo htmlspecialchars($h['title']); ?> from history" title="Remove from history">
                        <i class="ph ph-x"></i>
                    </button>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <!-- MEMBERSHIP -->
        <div class="tab-pane fade" id="v-pills-membership" role="tabpanel" aria-labelledby="v-pills-membership-tab">
            <div class="zp-panel zp-plan <?php echo $isFree ? '' : 'is-premium'; ?>">
                <div class="zp-plan-top">
                    <span class="zp-eyebrow">Current plan</span>
                    <?php if (!$isFree): ?><span class="zp-status">Active</span><?php endif; ?>
                </div>
                <h2 class="zp-plan-name"><?php echo htmlspecialchars($planLabel); ?></h2>
                <?php if ($isFree): ?>
                    <p class="zp-plan-text">Upgrade to Premium for 4K streaming, no ads and exclusive titles.</p>
                    <a href="/pricing-plan" class="zp-btn zp-btn-primary"><i class="ph-fill ph-crown"></i> Upgrade to Premium</a>
                <?php else: ?>
                    <p class="zp-plan-text">Your plan renews on <strong class="text-white"><?php echo $planExpiry; ?></strong>.</p>
                <?php endif; ?>
            </div>

            <div class="zp-panel">
                <h3 class="zp-section-title">Billing history</h3>
                <ul class="zp-list">
                    <?php if ($subscription): ?>
                    <li>
                        <span class="zp-list-main">
                            <?php echo htmlspecialchars($planLabel); ?> subscription
                            <span class="zp-list-sub"><?php echo date('M d, Y', strtotime($subscription['created_at'])); ?></span>
                        </span>
                        <span class="zp-paid"><i class="ph-fill ph-check-circle"></i> Paid</span>
                    </li>
                    <?php else: ?>
                    <li><span class="zp-list-sub">No payments yet.</span></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- PARENTAL CONTROLS -->
        <div class="tab-pane fade" id="v-pills-parental" role="tabpanel" aria-labelledby="v-pills-parental-tab">
            <div class="zp-panel">
                <?php $hasPin = !empty($user['parental_pin_hash']); ?>
                <div class="zp-feature">
                    <div class="zp-feature-icon"><i class="ph ph-lock-key"></i></div>
                    <div>
                        <h5>Parental PIN<?php if ($hasPin): ?> <span class="zp-pin-state"><i class="ph-fill ph-check-circle"></i> On</span><?php endif; ?></h5>
                        <p><?php echo $hasPin
                            ? 'Needed to leave Kids Mode. To change or remove it, enter the current PIN first.'
                            : 'Kids Mode needs a 4 to 8 digit PIN, so only a grown-up can switch back to this profile.'; ?></p>
                    </div>
                </div>

                <form id="set-parental-pin-form" novalidate>
                    <?php if ($hasPin): ?>
                    <div class="zp-form-row">
                        <label class="zp-label" for="current_pin">Current PIN</label>
                        <input type="password" name="current_pin" id="current_pin" class="zp-input zp-pin-input" inputmode="numeric" maxlength="8" autocomplete="off" placeholder="••••">
                    </div>
                    <?php endif; ?>
                    <div class="zp-form-grid">
                        <div>
                            <label class="zp-label" for="new_pin"><?php echo $hasPin ? 'New PIN' : 'PIN'; ?></label>
                            <input type="password" name="new_pin" id="new_pin" class="zp-input zp-pin-input" inputmode="numeric" maxlength="8" autocomplete="new-password" placeholder="••••">
                        </div>
                        <div>
                            <label class="zp-label" for="confirm_pin"><?php echo $hasPin ? 'Confirm new PIN' : 'Confirm PIN'; ?></label>
                            <input type="password" name="confirm_pin" id="confirm_pin" class="zp-input zp-pin-input" inputmode="numeric" maxlength="8" autocomplete="new-password" placeholder="••••">
                        </div>
                    </div>
                    <p class="zp-form-message" id="pinFormMessage" role="status" aria-live="polite"></p>
                    <div class="zp-form-actions">
                        <button type="submit" class="zp-btn zp-btn-primary"><?php echo $hasPin ? 'Change PIN' : 'Save PIN'; ?></button>
                        <?php if ($hasPin): ?>
                        <button type="button" id="clear-parental-pin" class="zp-btn zp-btn-danger">Remove PIN</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        <!-- PLAYLISTS -->
        <div class="tab-pane fade" id="v-pills-playlist" role="tabpanel" aria-labelledby="v-pills-playlist-tab">
            <div class="zp-panel zp-empty">
                <div class="zp-empty-icon"><i class="ph ph-playlist"></i></div>
                <h4>Playlists are coming soon</h4>
                <p>You'll be able to group titles into your own collections.</p>
            </div>
        </div>

    </div>

    <div class="zp-signout">
        <a href="/logout" class="zp-btn zp-btn-ghost" onclick="return confirm('Are you sure you want to log out?');">
            <i class="ph ph-sign-out"></i> Log out
        </a>
    </div>
</div>
</div>

<!-- ==========================================
     EDIT PROFILE MODAL
     ========================================== -->
<div class="modal fade" id="edit-profile-modal" tabindex="-1" aria-labelledby="edit-profile-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-white" id="edit-profile-title">Edit profile</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="edit-profile-form" enctype="multipart/form-data">
                    <div class="zp-modal-avatar">
                        <div class="zp-avatar-wrap">
                            <img src="<?php echo htmlspecialchars($user['avatar_path']); ?>" id="profile-picture-preview" class="zp-avatar" alt="">
                            <label for="avatar-upload-input" class="zp-avatar-edit" title="Choose a photo"><i class="ph ph-camera"></i></label>
                        </div>
                        <input type="file" id="avatar-upload-input" name="avatar" hidden accept="image/*">
                        <input type="hidden" id="is_remove_avatar" name="is_remove_avatar" value="0">
                        <button type="button" class="zp-link-danger" id="remove-profile-picture-btn">Remove photo</button>
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label" for="edit-first-name">First name</label>
                            <input type="text" class="form-control-dark" name="firstName" id="edit-first-name" value="<?php echo htmlspecialchars($user['firstName'] ?? ''); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="edit-last-name">Last name</label>
                            <input type="text" class="form-control-dark" name="lastName" id="edit-last-name" value="<?php echo htmlspecialchars($user['lastName'] ?? ''); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="edit-email">Email address</label>
                            <input type="email" class="form-control-dark" name="email" id="edit-email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="zp-form-actions justify-content-end">
                        <button type="button" class="zp-btn zp-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="save-profile-button" class="zp-btn zp-btn-primary">
                            <span class="button-text">Save changes</span>
                            <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- TOASTIFY LIB -->
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

<!-- PAGE SCRIPT -->
<script>
document.addEventListener("DOMContentLoaded", () => {
    // --- 1. Edit Profile Logic ---
    const editForm = document.querySelector("#edit-profile-form");
    if (editForm) {
        const saveBtn = document.querySelector("#save-profile-button");
        const btnText = saveBtn.querySelector(".button-text");
        const spinner = saveBtn.querySelector(".spinner-border");

        // Avatar Preview
        const avatarInput = document.getElementById("avatar-upload-input");
        const avatarPreview = document.getElementById("profile-picture-preview");
        const removeAvatarBtn = document.getElementById("remove-profile-picture-btn");
        const removeFlag = document.getElementById("is_remove_avatar");
        const defaultSrc = 'assets/images/user/user6.jpg';

        avatarInput.addEventListener("change", (e) => {
            const file = e.target.files[0];
            if(file) {
                const reader = new FileReader();
                reader.onload = (e) => avatarPreview.src = e.target.result;
                reader.readAsDataURL(file);
                removeFlag.value = "0";
            }
        });

        removeAvatarBtn.addEventListener("click", () => {
            avatarPreview.src = defaultSrc;
            avatarInput.value = "";
            removeFlag.value = "1";
        });

        // Form Submit
        editForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            saveBtn.disabled = true;
            btnText.classList.add("d-none");
            spinner.classList.remove("d-none");

            const formData = new FormData(editForm);
            try {
                const res = await fetch('/update-profile', { method: 'POST', body: formData });
                const data = await res.json();

                if (res.ok) {
                    Toastify({ text: "Profile Updated!", style: { background: "#00b09b" } }).showToast();
                    // Update header name/email immediately
                    document.getElementById('profile-header-name').textContent =
                        (document.getElementById('edit-first-name').value + ' ' + document.getElementById('edit-last-name').value).trim();
                    document.getElementById('profile-header-email').textContent = document.getElementById('edit-email').value;

                    // Update header avatar if changed
                    if(avatarPreview.src) document.getElementById('profile-header-avatar').src = avatarPreview.src;

                    setTimeout(() => bootstrap.Modal.getInstance(document.getElementById('edit-profile-modal')).hide(), 500);
                } else {
                    Toastify({ text: data.message || "Update Failed", style: { background: "#ff5f6d" } }).showToast();
                }
            } catch (err) {
                console.error(err);
                Toastify({ text: "Network Error", style: { background: "#ff5f6d" } }).showToast();
            } finally {
                saveBtn.disabled = false;
                btnText.classList.remove("d-none");
                spinner.classList.add("d-none");
            }
        });
    }

    // --- 2. Parental PIN: create, change (needs the current PIN) or remove ---
    const pinForm = document.getElementById('set-parental-pin-form');
    if (pinForm) {
        const pinMessage = document.getElementById('pinFormMessage');
        const say = (text, ok) => {
            pinMessage.textContent = text;
            pinMessage.className = 'zp-form-message ' + (ok ? 'is-ok' : 'is-error');
        };
        const sendPin = async fields => {
            const body = new FormData();
            Object.entries(fields).forEach(([key, value]) => body.append(key, value));
            const res = await fetch('/set-parental-pin', { method: 'POST', body, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            return res.json();
        };
        pinForm.querySelectorAll('.zp-pin-input').forEach(input => input.addEventListener('input', () => {
            input.value = input.value.replace(/\D/g, '').slice(0, 8);
        }));

        pinForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const current = document.getElementById('current_pin');
            const newPin = document.getElementById('new_pin').value;
            const confirmPin = document.getElementById('confirm_pin').value;
            if (current && !current.value) return say('Enter your current PIN first.', false);
            if (!/^\d{4,8}$/.test(newPin)) return say('Use 4 to 8 digits for the PIN.', false);
            if (newPin !== confirmPin) return say("The two PINs don't match.", false);
            try {
                const data = await sendPin({ current_pin: current ? current.value : '', new_pin: newPin, confirm_pin: confirmPin });
                say(data.message, data.status === 'success');
                if (data.status === 'success') setTimeout(() => location.reload(), 1000);
            } catch (err) {
                say('Connection problem. Please try again.', false);
            }
        });

        const clearBtn = document.getElementById('clear-parental-pin');
        if (clearBtn) {
            clearBtn.addEventListener('click', async () => {
                const current = document.getElementById('current_pin');
                if (!current.value) {
                    say('Enter your current PIN to remove it.', false);
                    current.focus();
                    return;
                }
                if (!confirm('Remove the parental PIN? Kids Mode will ask for a new one next time.')) return;
                try {
                    const data = await sendPin({ action: 'clear', current_pin: current.value });
                    say(data.message, data.status === 'success');
                    if (data.status === 'success') setTimeout(() => location.reload(), 1000);
                } catch (err) {
                    say('Connection problem. Please try again.', false);
                }
            });
        }
    }

    // --- History: remove one title, or clear it all ---
    const historyList = document.getElementById('zpHistoryList');
    if (historyList) {
        const watchedStat = document.getElementById('zp-watched-count');
        const sendHistory = async fields => {
            const body = new FormData();
            Object.entries(fields).forEach(([key, value]) => body.append(key, value));
            const res = await fetch('/remove-history', { method: 'POST', body, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            return res.json();
        };
        const showEmptyIfDone = () => {
            if (historyList.querySelector('.zp-history-item')) return;
            historyList.remove();
            document.getElementById('zpHistoryHead').remove();
            document.getElementById('zpHistoryEmpty').hidden = false;
        };

        historyList.addEventListener('click', async (e) => {
            const button = e.target.closest('.zp-history-remove');
            if (!button) return;
            const item = button.closest('.zp-history-item');
            button.disabled = true;
            try {
                const data = await sendHistory({ media_id: item.dataset.id, media_type: item.dataset.type });
                if (data.status !== 'success') throw new Error(data.message);
                item.classList.add('is-removing');
                if (watchedStat) watchedStat.textContent = Math.max(0, Number(watchedStat.textContent) - 1);
                setTimeout(() => { item.remove(); showEmptyIfDone(); }, 250);
            } catch (err) {
                button.disabled = false;
                Toastify({ text: "Couldn't remove it. Please try again.", style: { background: "#ff5f6d" } }).showToast();
            }
        });

        document.getElementById('zpHistoryClear').addEventListener('click', async () => {
            if (!confirm('Clear your whole watch history? Continue Watching on the home page will be empty too.')) return;
            try {
                const data = await sendHistory({ action: 'clear' });
                if (data.status !== 'success') throw new Error(data.message);
                historyList.querySelectorAll('.zp-history-item').forEach(item => item.remove());
                if (watchedStat) watchedStat.textContent = 0;
                showEmptyIfDone();
                Toastify({ text: "Watch history cleared", style: { background: "#555" } }).showToast();
            } catch (err) {
                Toastify({ text: "Couldn't clear it. Please try again.", style: { background: "#ff5f6d" } }).showToast();
            }
        });
    }

    // /profile#history opens the History tab.
    if (location.hash === '#history' && window.bootstrap) {
        bootstrap.Tab.getOrCreateInstance(document.getElementById('v-pills-history-tab')).show();
    }
    // --- 3. Watchlist Remove Logic ---
    document.querySelectorAll('.watchlist-remove-btn').forEach(btn => {
        btn.addEventListener('click', async function(e) {
            e.preventDefault();
            e.stopPropagation();
            if(!confirm("Remove from Watchlist?")) return;

            const card = this.closest('.zp-card');
            this.disabled = true;

            try {
                // The same endpoint the movie page uses. It toggles, so for a
                // title already in the watchlist this removes it.
                const res = await fetch('/add-watchlist', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new URLSearchParams({ id: this.dataset.id, type: this.dataset.type })
                });
                const data = await res.json();
                if (data.status !== 'success' || data.action !== 'removed') {
                    throw new Error(data.message || 'Not removed');
                }

                card.style.opacity = '0';
                setTimeout(() => {
                    card.remove();
                    const left = document.querySelectorAll('#zp-watchlist-grid .zp-card').length;
                    const count = document.getElementById('zp-watchlist-count');
                    const total = document.getElementById('zp-watchlist-total');
                    if (count) count.textContent = left;
                    if (total) total.textContent = left === 1 ? '1 title' : left + ' titles';
                    if (!left) location.reload(); // show the empty state
                }, 300);
                Toastify({ text: "Removed from watchlist", style: { background: "#555" } }).showToast();
            } catch(err) {
                console.error(err);
                this.disabled = false;
                Toastify({ text: "Couldn't remove it. Please try again.", style: { background: "#ff5f6d" } }).showToast();
            }
        });
    });
});
</script>
