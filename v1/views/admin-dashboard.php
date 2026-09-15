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

// 2. Get Global Stats
$totalUsers = 0;
try {
    $stmt = $conn->query("SELECT COUNT(*) FROM users");
    $totalUsers = $stmt->fetchColumn();
} catch (PDOException $e) {}

$totalViews = 0;
try {
    $stmt = $conn->query("SELECT SUM(views) FROM content_views");
    $totalViews = $stmt->fetchColumn() ?: 0;
} catch (PDOException $e) {}

$totalWatchlist = 0;
try {
    $stmt = $conn->query("SELECT COUNT(*) FROM watchlist");
    $totalWatchlist = $stmt->fetchColumn();
} catch (PDOException $e) {}

// 2b. AI activity (tables may not exist yet -- see v1/db/migrate_ai.php)
$aiChatsToday = 0;
$aiHooksCached = 0;
$aiHooksUnverified = 0;
$aiFailuresToday = 0;
try {
    $aiChatsToday = (int) $conn->query(
        "SELECT COUNT(*) FROM zen_search_history WHERE created_at >= CURDATE()"
    )->fetchColumn();
} catch (PDOException $e) {}
try {
    $aiHooksCached = (int) $conn->query("SELECT COUNT(*) FROM ai_hooks")->fetchColumn();
    $aiHooksUnverified = (int) $conn->query(
        "SELECT COUNT(*) FROM ai_hooks WHERE model = 'imported-from-json'"
    )->fetchColumn();
} catch (PDOException $e) {}
try {
    $aiFailuresToday = (int) $conn->query(
        "SELECT COUNT(*) FROM ai_usage_log
          WHERE status IN ('error','blocked') AND created_at >= CURDATE()"
    )->fetchColumn();
} catch (PDOException $e) {}

// 3. Recent Signups
$recentUsers = [];
try {
    $stmt = $conn->query("SELECT id, username, email, is_admin, created_at FROM users ORDER BY id DESC LIMIT 5");
    $recentUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// 4. Top Content
$topContent = [];
try {
    $stmt = $conn->query("SELECT tmdb_id, media_type, views FROM content_views ORDER BY views DESC LIMIT 5");
    $topContent = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Include Header (this also includes the sidebar)
include __DIR__ . '/includes/header.php';
?>

<style>
    .admin-hero {
        background: linear-gradient(135deg, rgba(30,20,40,0.8) 0%, rgba(15,10,20,0.95) 100%);
        border-radius: 20px;
        padding: 40px;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(255,255,255,0.05);
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    }
    .admin-hero::before {
        content: '';
        position: absolute;
        top: -50%; left: -50%;
        width: 200%; height: 200%;
        background: radial-gradient(circle, rgba(var(--primary-rgb), 0.15) 0%, transparent 60%);
        pointer-events: none;
        z-index: 0;
    }
    .admin-hero-content {
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
    
    .admin-table-container {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(255,255,255,0.05);
        border-radius: 16px;
        padding: 24px;
    }
    .admin-table {
        width: 100%;
        color: #fff;
    }
    .admin-table th {
        color: #aaa;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        padding-bottom: 15px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .admin-table td {
        padding: 15px 0;
        border-bottom: 1px solid rgba(255,255,255,0.05);
        vertical-align: middle;
    }
    .admin-badge {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: bold;
        text-transform: uppercase;
    }
    .admin-badge-user {
        background: rgba(40, 167, 69, 0.15);
        color: #4ade80;
    }
    .admin-badge-admin {
        background: rgba(var(--primary-rgb), 0.15);
        color: var(--primary);
    }
</style>

<div class="container-fluid pb-0 mt-4" id="page_layout">
    
    <div class="row mb-5">
        <div class="col-12">
            <div class="admin-hero">
                <div class="admin-hero-content d-flex align-items-center justify-content-between flex-wrap gap-4">
                    <div>
                        <h1 class="display-4 fw-bolder text-white mb-2" style="letter-spacing: -1px;">Admin <span class="text-primary">Dashboard</span></h1>
                        <p class="lead text-muted mb-0">Platform analytics and management overview.</p>
                    </div>
                    <div>
                        <div class="d-inline-flex align-items-center gap-3 px-4 py-2 rounded-pill" style="background: rgba(var(--primary-rgb), 0.15); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                            <i class="ph ph-shield-check text-primary fs-4"></i>
                            <span class="text-white fw-bold text-uppercase">MASTER ACCESS</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon" style="color: #4ade80; background: rgba(74, 222, 128, 0.1);"><i class="ph ph-users"></i></div>
                <div>
                    <h3 class="stat-value"><?php echo number_format($totalUsers); ?></h3>
                    <p class="stat-label">Total Users</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon" style="color: #fbbf24; background: rgba(251, 191, 36, 0.1);"><i class="ph ph-eye"></i></div>
                <div>
                    <h3 class="stat-value"><?php echo number_format($totalViews); ?></h3>
                    <p class="stat-label">Total Views</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon" style="color: #f87171; background: rgba(248, 113, 113, 0.1);"><i class="ph ph-heart"></i></div>
                <div>
                    <h3 class="stat-value"><?php echo number_format($totalWatchlist); ?></h3>
                    <p class="stat-label">Watchlist Saves</p>
                </div>
            </div>
        </div>
    </div>

    <!-- AI activity: each card opens the AI Control Panel -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <a href="/admin-ai" style="text-decoration:none; color:inherit;">
                <div class="stat-card">
                    <div class="stat-icon" style="color: #00e0ff; background: rgba(0, 224, 255, 0.1);"><i class="ph ph-robot"></i></div>
                    <div>
                        <h3 class="stat-value"><?php echo number_format($aiChatsToday); ?></h3>
                        <p class="stat-label">ZEN AI Chats Today</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="/admin-ai" style="text-decoration:none; color:inherit;">
                <div class="stat-card">
                    <div class="stat-icon" style="color: #a78bfa; background: rgba(167, 139, 250, 0.1);"><i class="ph ph-chat-centered-text"></i></div>
                    <div>
                        <h3 class="stat-value"><?php echo number_format($aiHooksCached); ?></h3>
                        <p class="stat-label">
                            AI Pitches Cached
                            <?php if ($aiHooksUnverified > 0): ?>
                                <span style="color:#fbbf24;">&middot; <?php echo number_format($aiHooksUnverified); ?> need review</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="/admin-ai" style="text-decoration:none; color:inherit;">
                <div class="stat-card">
                    <div class="stat-icon"
                         style="color: <?php echo $aiFailuresToday > 0 ? '#f87171' : '#9ca3af'; ?>;
                                background: rgba(<?php echo $aiFailuresToday > 0 ? '248, 113, 113' : '156, 163, 175'; ?>, 0.1);">
                        <i class="ph ph-warning-circle"></i>
                    </div>
                    <div>
                        <h3 class="stat-value"><?php echo number_format($aiFailuresToday); ?></h3>
                        <p class="stat-label">AI Errors / Blocked Today</p>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-xl-6">
            <div class="admin-table-container h-100">
                <h4 class="text-white fw-bold mb-4"><i class="ph ph-user-plus text-primary me-2"></i>Recent Signups</h4>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th colspan="2">User</th>
                                <th>Role</th>
                                <th class="text-end">Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recentUsers as $u): ?>
                            <tr>
                                <td style="width: 50px; padding-right: 0;">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background: rgba(var(--primary-rgb), 0.15); color: var(--primary); font-weight: bold;">
                                        <?php echo strtoupper(substr($u['username'] ?? 'U', 0, 1)); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold text-white"><?php echo htmlspecialchars($u['username'] ?? 'User'); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($u['email']); ?></div>
                                </td>
                                <td>
                                    <?php if($u['is_admin'] == 1): ?>
                                        <span class="admin-badge admin-badge-admin">Admin</span>
                                    <?php else: ?>
                                        <span class="admin-badge admin-badge-user">User</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-muted small">
                                    <?php echo isset($u['created_at']) ? date('M j, Y', strtotime($u['created_at'])) : 'Recently'; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-xl-6">
            <div class="admin-table-container h-100">
                <h4 class="text-white fw-bold mb-4"><i class="ph ph-trend-up text-warning me-2"></i>Top Performing Content</h4>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Content</th>
                                <th>Type</th>
                                <th class="text-end">Total Views</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($topContent as $tc): 
                                // Fetch title if possible
                                $title = "Item #" . $tc['tmdb_id'];
                                if (function_exists('fetchTmdbApi')) {
                                    $details = fetchTmdbApi("{$tc['media_type']}/{$tc['tmdb_id']}");
                                    if ($details) {
                                        $title = $details['title'] ?? $details['name'] ?? $title;
                                    }
                                }
                            ?>
                            <tr>
                                <td>
                                    <a href="/watch?id=<?php echo $tc['tmdb_id']; ?>&type=<?php echo $tc['media_type']; ?>" class="fw-bold text-white text-decoration-none hover-glow">
                                        <?php echo htmlspecialchars($title); ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="admin-badge admin-badge-user text-uppercase" style="background: rgba(255,255,255,0.1); color: #fff;"><?php echo htmlspecialchars($tc['media_type']); ?></span>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex align-items-center gap-2">
                                        <i class="ph ph-eye text-muted"></i>
                                        <span class="fw-bold text-white fs-5"><?php echo number_format($tc['views']); ?></span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($topContent)): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">No content views recorded yet.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
