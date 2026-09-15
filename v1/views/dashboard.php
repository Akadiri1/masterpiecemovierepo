<?php
if (!isset($_SESSION['user_id'])) {
    $currentUrl = urlencode($_SERVER['REQUEST_URI']);
    header('Location: /login?next=' . $currentUrl);
    exit;
}

$user_id = $_SESSION['user_id'];
$current_plan = $_SESSION['plan_name'] ?? 'free';
$displayName = $_SESSION['username'] ?? 'User';

// 1. Get Watchlist Count
$watchlistCount = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM watchlist WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $watchlistCount = $stmt->fetchColumn();
} catch (PDOException $e) {}

// 2. Get Total Watch Time (sum of current_time or total_duration depending on how it's tracked)
$totalWatchMinutes = 0;
try {
    $stmt = $conn->prepare("SELECT SUM(total_duration) FROM watch_history WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $totalWatchMinutes = (float)$stmt->fetchColumn();
} catch (PDOException $e) {}

$hours = floor($totalWatchMinutes / 60);
$minutes = $totalWatchMinutes % 60;
$watchTimeString = "{$hours}h {$minutes}m";

// 3. Get Recent Watch History
$recentHistory = [];
try {
    $stmt = $conn->prepare("SELECT tmdb_movie_id, media_type, last_watched FROM watch_history WHERE user_id = ? ORDER BY last_watched DESC LIMIT 8");
    $stmt->execute([$user_id]);
    $recentHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Include Header (this also includes the sidebar)
include __DIR__ . '/includes/header.php';
?>

<style>
    .dashboard-hero {
        background: linear-gradient(135deg, rgba(20,20,30,0.8) 0%, rgba(10,10,15,0.95) 100%);
        border-radius: 20px;
        padding: 40px;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(255,255,255,0.05);
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    }
    .dashboard-hero::before {
        content: '';
        position: absolute;
        top: -50%; left: -50%;
        width: 200%; height: 200%;
        background: radial-gradient(circle, rgba(var(--primary-rgb), 0.15) 0%, transparent 60%);
        pointer-events: none;
        z-index: 0;
    }
    .dashboard-hero-content {
        position: relative;
        z-index: 1;
    }
    .stat-card {
        background: rgba(255, 255, 255, 0.03);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 16px;
        padding: 24px;
        transition: transform 0.3s ease, background 0.3s ease, border-color 0.3s ease;
        display: flex;
        align-items: center;
        gap: 20px;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        background: rgba(255, 255, 255, 0.05);
        border-color: rgba(var(--primary-rgb), 0.5);
    }
    .stat-icon {
        width: 60px; height: 60px;
        border-radius: 12px;
        background: rgba(var(--primary-rgb), 0.1);
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
    }
    .stat-value {
        font-size: 2rem;
        font-weight: 800;
        margin: 0;
        color: #fff;
    }
    .stat-label {
        color: #aaa;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin: 0;
    }
    
    .history-card {
        background: #111;
        border-radius: 12px;
        overflow: hidden;
        position: relative;
        transition: transform 0.3s, box-shadow 0.3s;
    }
    .history-card:hover {
        transform: scale(1.05);
        box-shadow: 0 10px 25px rgba(0,0,0,0.8);
        z-index: 2;
    }
    .history-img {
        width: 100%;
        aspect-ratio: 2/3;
        object-fit: cover;
    }
    .history-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(to top, rgba(0,0,0,0.9), transparent);
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        padding: 15px;
        opacity: 0;
        transition: opacity 0.3s;
    }
    .history-card:hover .history-overlay {
        opacity: 1;
    }
    .history-title {
        color: #fff;
        font-weight: 600;
        font-size: 0.9rem;
        margin-bottom: 5px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .history-date {
        color: var(--primary);
        font-size: 0.75rem;
    }
</style>

<div class="content-inner container-fluid pb-0" id="page_layout">
    
    <div class="row mb-5">
        <div class="col-12">
            <div class="dashboard-hero">
                <div class="dashboard-hero-content d-flex align-items-center justify-content-between flex-wrap gap-4">
                    <div>
                        <h1 class="display-4 fw-bolder text-white mb-2" style="letter-spacing: -1px;">Welcome back, <span class="text-primary"><?php echo htmlspecialchars($displayName); ?></span></h1>
                        <p class="lead text-muted mb-0">Ready to dive back into your cinematic universe?</p>
                    </div>
                    <div>
                        <div class="d-inline-flex align-items-center gap-3 px-4 py-2 rounded-pill" style="background: rgba(var(--primary-rgb), 0.15); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                            <i class="ph ph-crown text-primary fs-4"></i>
                            <span class="text-white fw-bold text-uppercase"><?php echo htmlspecialchars($current_plan); ?> PLAN</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon"><i class="ph ph-clock"></i></div>
                <div>
                    <h3 class="stat-value"><?php echo $watchTimeString; ?></h3>
                    <p class="stat-label">Total Watch Time</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon"><i class="ph ph-heart"></i></div>
                <div>
                    <h3 class="stat-value"><?php echo $watchlistCount; ?></h3>
                    <p class="stat-label">Saved to Watchlist</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon"><i class="ph ph-film-strip"></i></div>
                <div>
                    <h3 class="stat-value"><?php echo count($recentHistory); ?></h3>
                    <p class="stat-label">Recently Watched</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-end mb-4">
                <h3 class="text-white fw-bold m-0">Continue Watching</h3>
                <a href="/profile" class="text-primary text-decoration-none fw-bold hover-glow">View Full History <i class="ph ph-arrow-right ms-1"></i></a>
            </div>
            
            <?php if (empty($recentHistory)): ?>
                <div class="p-5 text-center" style="background: rgba(255,255,255,0.02); border-radius: 16px; border: 1px dashed rgba(255,255,255,0.1);">
                    <i class="ph ph-popcorn text-muted" style="font-size: 4rem;"></i>
                    <h4 class="text-white mt-3">No history yet!</h4>
                    <p class="text-muted">Start watching movies and shows to see them appear here.</p>
                    <a href="/" class="btn btn-primary mt-2">Explore Content</a>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php 
                    // Make sure fetchTmdbApi is available
                    if (!function_exists('fetchTmdbApi') && file_exists(APP_PATH . '/controllers/controller.php')) {
                        // It should already be included via header.php or index.php, but just in case
                    }
                    
                    foreach ($recentHistory as $item): 
                        $media_type = $item['media_type'] ?? 'movie';
                        $details = function_exists('fetchTmdbApi') ? fetchTmdbApi("{$media_type}/{$item['tmdb_movie_id']}") : null;
                        
                        if ($details):
                            $title = $details['title'] ?? $details['name'] ?? 'Unknown';
                            $poster = isset($details['poster_path']) ? 'https://image.tmdb.org/t/p/w300' . $details['poster_path'] : '/assets/images/media/placeholder.svg';
                            $date = date('M j, Y', strtotime($item['last_watched']));
                    ?>
                        <div class="col-6 col-sm-4 col-md-3 col-xl-2">
                            <a href="/watch?id=<?php echo $item['tmdb_movie_id']; ?>&type=<?php echo $media_type; ?>" class="d-block text-decoration-none">
                                <div class="history-card">
                                    <img src="<?php echo htmlspecialchars($poster); ?>" class="history-img" alt="<?php echo htmlspecialchars($title); ?>" loading="lazy">
                                    <div class="history-overlay">
                                        <div class="history-title"><?php echo htmlspecialchars($title); ?></div>
                                        <div class="history-date"><i class="ph ph-calendar-blank me-1"></i><?php echo $date; ?></div>
                                    </div>
                                    <!-- Play Button Hover Icon -->
                                    <div class="position-absolute top-50 start-50 translate-middle" style="opacity: 0; transition: opacity 0.3s; z-index: 3; pointer-events: none;">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background: rgba(var(--primary-rgb), 0.9); box-shadow: 0 0 20px rgba(var(--primary-rgb), 0.5);">
                                            <i class="ph-fill ph-play text-white fs-4"></i>
                                        </div>
                                    </div>
                                    <style>
                                        .history-card:hover .translate-middle { opacity: 1 !important; }
                                    </style>
                                </div>
                            </a>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
