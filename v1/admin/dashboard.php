<?php
ob_start();
$level_check = ['MASTER'];
include 'includes/header.php';

// 1. Get Global Stats
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

// 2. Recent Signups
$recentUsers = [];
try {
    $stmt = $conn->query("SELECT id, username, email, is_admin, date_created FROM users ORDER BY id DESC LIMIT 5");
    $recentUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

// 3. Top Content
$topContent = [];
try {
    $stmt = $conn->query("SELECT tmdb_id, media_type, views FROM content_views ORDER BY views DESC LIMIT 5");
    $topContent = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

?>

<!-- DataTables css -->
<link href="/da/assets/plugins/datatables/dataTables.bootstrap4.min.css" rel="stylesheet" type="text/css" />
<link href="/da/assets/plugins/datatables/buttons.bootstrap4.min.css" rel="stylesheet" type="text/css" />

<style>
    .admin-dash-bg {
        background: #0f1015; /* Deep premium dark */
        color: #e0e0e0;
    }
    .k-card {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(255,255,255,0.05);
        border-radius: 12px;
        backdrop-filter: blur(10px);
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        transition: transform 0.3s;
    }
    .k-card:hover {
        transform: translateY(-5px);
        border-color: rgba(var(--primary-rgb, 0, 123, 255), 0.5);
    }
    .k-icon-box {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        background: rgba(var(--primary-rgb, 0, 123, 255), 0.1);
        color: #0d6efd; /* Primary fallback */
    }
    .k-value {
        font-size: 2rem;
        font-weight: 700;
        color: #fff;
        margin: 0;
    }
    .k-title {
        color: #9aa0ac;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .k-table-card {
        background: rgba(255, 255, 255, 0.02);
        border-radius: 12px;
        border: 1px solid rgba(255,255,255,0.05);
    }
    .k-table thead th {
        background: rgba(255, 255, 255, 0.03);
        border-bottom: 1px solid rgba(255,255,255,0.1);
        color: #fff;
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 0.5px;
        font-weight: 600;
    }
    .k-table tbody td {
        border-bottom: 1px solid rgba(255,255,255,0.05);
        color: #d1d5db;
        vertical-align: middle;
    }
    .badge-soft-success {
        background: rgba(40, 167, 69, 0.1);
        color: #28a745;
    }
    .badge-soft-primary {
        background: rgba(0, 123, 255, 0.1);
        color: #0d6efd;
    }
</style>

<div class="page-content admin-dash-bg" style="min-height: 100vh; padding-top: 20px;">
    <div class="container-fluid">
        <!-- Page-Title -->
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title text-white font-weight-bold">Platform Overview</h4>
                    <p class="text-muted">Real-time statistics and analytics for Masterpiece Movie.</p>
                </div>
            </div>
        </div>
        <!-- end page title end breadcrumb -->
        
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="k-card p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="k-title mb-1">Total Users</p>
                            <h3 class="k-value"><?php echo number_format($totalUsers); ?></h3>
                        </div>
                        <div class="k-icon-box" style="color: #28a745; background: rgba(40,167,69,0.1);">
                            <i class="mdi mdi-account-multiple"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="k-card p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="k-title mb-1">Total Platform Views</p>
                            <h3 class="k-value"><?php echo number_format($totalViews); ?></h3>
                        </div>
                        <div class="k-icon-box" style="color: #ffc107; background: rgba(255,193,7,0.1);">
                            <i class="mdi mdi-eye"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="k-card p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="k-title mb-1">Total Watchlist Saves</p>
                            <h3 class="k-value"><?php echo number_format($totalWatchlist); ?></h3>
                        </div>
                        <div class="k-icon-box" style="color: #dc3545; background: rgba(220,53,69,0.1);">
                            <i class="mdi mdi-heart"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- AI activity -->
        <div class="row mb-4">
            <div class="col-md-4">
                <a href="/admin-ai" style="text-decoration:none;">
                <div class="k-card p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="k-title mb-1">ZEN AI Chats Today</p>
                            <h3 class="k-value"><?php echo number_format($aiChatsToday); ?></h3>
                        </div>
                        <div class="k-icon-box" style="color: #00e0ff; background: rgba(0,224,255,0.1);">
                            <i class="mdi mdi-robot"></i>
                        </div>
                    </div>
                </div>
                </a>
            </div>

            <div class="col-md-4">
                <a href="/admin-ai" style="text-decoration:none;">
                <div class="k-card p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="k-title mb-1">AI Pitches Cached</p>
                            <h3 class="k-value"><?php echo number_format($aiHooksCached); ?></h3>
                            <?php if ($aiHooksUnverified > 0): ?>
                                <small style="color:#ffc107;">
                                    <?php echo number_format($aiHooksUnverified); ?> need review
                                </small>
                            <?php endif; ?>
                        </div>
                        <div class="k-icon-box" style="color: #7b2cbf; background: rgba(123,44,191,0.1);">
                            <i class="mdi mdi-message-text"></i>
                        </div>
                    </div>
                </div>
                </a>
            </div>

            <div class="col-md-4">
                <a href="/admin-ai" style="text-decoration:none;">
                <div class="k-card p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="k-title mb-1">AI Errors / Blocked Today</p>
                            <h3 class="k-value"><?php echo number_format($aiFailuresToday); ?></h3>
                        </div>
                        <div class="k-icon-box"
                             style="color: <?php echo $aiFailuresToday > 0 ? '#dc3545' : '#6c757d'; ?>;
                                    background: rgba(<?php echo $aiFailuresToday > 0 ? '220,53,69' : '108,117,125'; ?>,0.1);">
                            <i class="mdi mdi-alert-circle-outline"></i>
                        </div>
                    </div>
                </div>
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-6">
                <div class="k-table-card p-4 mb-4">
                    <h5 class="text-white mb-4 mt-0"><i class="mdi mdi-account-plus text-primary mr-2"></i>Recent Signups</h5>
                    <div class="table-responsive">
                        <table class="table k-table mb-0">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Joined</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recentUsers as $u): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-xs mr-3">
                                                <span class="avatar-title rounded-circle bg-soft-primary text-primary">
                                                    <?php echo strtoupper(substr($u['username'] ?? 'U', 0, 1)); ?>
                                                </span>
                                            </div>
                                            <p class="mb-0 font-weight-bold text-white"><?php echo htmlspecialchars($u['username'] ?? 'User'); ?></p>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td>
                                        <?php if($u['is_admin'] == 1): ?>
                                            <span class="badge badge-soft-primary">Admin</span>
                                        <?php else: ?>
                                            <span class="badge badge-soft-success">User</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo isset($u['date_created']) ? date('M j, Y', strtotime($u['date_created'])) : 'Recently'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-6">
                <div class="k-table-card p-4 mb-4">
                    <h5 class="text-white mb-4 mt-0"><i class="mdi mdi-trending-up text-warning mr-2"></i>Top Performing Content</h5>
                    <div class="table-responsive">
                        <table class="table k-table mb-0">
                            <thead>
                                <tr>
                                    <th>TMDB ID</th>
                                    <th>Type</th>
                                    <th>Total Views</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($topContent as $tc): ?>
                                <tr>
                                    <td><span class="font-weight-bold text-white">#<?php echo $tc['tmdb_id']; ?></span></td>
                                    <td><span class="badge badge-soft-primary text-uppercase"><?php echo $tc['media_type']; ?></span></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="mdi mdi-eye text-muted mr-2"></i>
                                            <span class="font-weight-bold text-white"><?php echo number_format($tc['views']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="/watch?id=<?php echo $tc['tmdb_id']; ?>&type=<?php echo $tc['media_type']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($topContent)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No content views recorded yet.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- container -->
</div><!-- page-content -->

<?php include 'includes/footer.php'; ?>
