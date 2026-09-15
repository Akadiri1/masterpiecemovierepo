<?php
ob_start();
$level_check = ['MASTER', 3, 2, 1];
include 'includes/header.php';

// ==========================================
// AI CONTROL PANEL
// ==========================================
// Covers both AI surfaces: the ZEN AI chat (ask-ai.php, logged in
// zen_search_history) and the movie-page pitch generator (ai-hook.php, cached
// in ai_hooks and logged in ai_usage_log).

$message = '';
$messageType = '';
$adminId = $_SESSION['admin_id'] ?? null;

$tablesReady = true;
try {
    $conn->query("SELECT 1 FROM ai_hooks LIMIT 1");
    $conn->query("SELECT 1 FROM ai_usage_log LIMIT 1");
} catch (PDOException $e) {
    $tablesReady = false;
    $message = "AI tables not found. Run: php v1/db/migrate_ai.php up";
    $messageType = 'warning';
}

// ------------------------------------------------------------------ actions --
if ($tablesReady && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    $action = $_POST['action'];
    $hookId = (int) ($_POST['hook_id'] ?? 0);

    try {
        if ($action === 'toggle_hook' && $hookId) {
            $conn->prepare("UPDATE ai_hooks SET is_active = 1 - is_active WHERE id = ?")->execute([$hookId]);
            $message = 'Hook visibility updated.';
            $messageType = 'success';

        } elseif ($action === 'delete_hook' && $hookId) {
            // Deleting simply lets it regenerate from TMDB on the next view.
            $conn->prepare("DELETE FROM ai_hooks WHERE id = ?")->execute([$hookId]);
            $message = 'Hook deleted. It will regenerate next time that page is opened.';
            $messageType = 'success';

        } elseif ($action === 'purge_imported') {
            // These came from the old JSON cache, where the pitch was generated
            // from a title supplied by the caller rather than looked up from
            // TMDB. They cannot be trusted, so clearing them forces a clean
            // regeneration through the hardened endpoint.
            $stmt = $conn->prepare("DELETE FROM ai_hooks WHERE model = 'imported-from-json'");
            $stmt->execute();
            $message = 'Purged ' . $stmt->rowCount() . ' unverified imported hooks. They will regenerate on demand.';
            $messageType = 'success';

        } elseif ($action === 'set_limit') {
            $targetUser = (int) ($_POST['user_id'] ?? 0);
            $limit = (int) ($_POST['ai_tokens_limit'] ?? 10);
            if ($limit < -1) { $limit = -1; }
            if ($targetUser) {
                $conn->prepare("UPDATE users SET ai_tokens_limit = ? WHERE id = ?")->execute([$limit, $targetUser]);
                $message = 'Daily AI limit updated.';
                $messageType = 'success';
            }
        }
    } catch (PDOException $e) {
        $message = 'Action failed: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// -------------------------------------------------------------------- data --
$stats = [
    'chat_total' => 0, 'chat_today' => 0,
    'hooks' => 0, 'hooks_imported' => 0, 'hooks_hidden' => 0,
    'gen_today' => 0, 'blocked_today' => 0, 'errors_today' => 0, 'hit_today' => 0,
];
$daily = $topUsers = $recentQueries = $hooks = $recentErrors = [];

if ($tablesReady) {
    $q = function (string $sql, array $p = []) use ($conn) {
        $s = $conn->prepare($sql); $s->execute($p); return $s;
    };

    try {
        $stats['chat_total'] = (int) $conn->query("SELECT COUNT(*) FROM zen_search_history")->fetchColumn();
        $stats['chat_today'] = (int) $conn->query(
            "SELECT COUNT(*) FROM zen_search_history WHERE created_at >= CURDATE()"
        )->fetchColumn();
    } catch (PDOException $e) {}

    $stats['hooks']          = (int) $conn->query("SELECT COUNT(*) FROM ai_hooks")->fetchColumn();
    $stats['hooks_imported'] = (int) $conn->query("SELECT COUNT(*) FROM ai_hooks WHERE model = 'imported-from-json'")->fetchColumn();
    $stats['hooks_hidden']   = (int) $conn->query("SELECT COUNT(*) FROM ai_hooks WHERE is_active = 0")->fetchColumn();

    foreach ($conn->query(
        "SELECT status, COUNT(*) c FROM ai_usage_log WHERE created_at >= CURDATE() GROUP BY status"
    ) as $row) {
        $key = $row['status'] . '_today';
        if ($row['status'] === 'generated') $key = 'gen_today';
        if ($row['status'] === 'hit')       $key = 'hit_today';
        if (array_key_exists($key, $stats)) {
            $stats[$key] = (int) $row['c'];
        }
    }

    // 14-day activity, chat and hooks side by side.
    $daily = $conn->query("
        SELECT d.day,
               COALESCE(z.c, 0) AS chats,
               COALESCE(g.c, 0) AS generations
          FROM (
                SELECT DATE(created_at) AS day FROM zen_search_history
                 WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
                 UNION
                SELECT DATE(created_at) FROM ai_usage_log
                 WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
               ) d
          LEFT JOIN (
                SELECT DATE(created_at) day, COUNT(*) c FROM zen_search_history GROUP BY 1
               ) z ON z.day = d.day
          LEFT JOIN (
                SELECT DATE(created_at) day, COUNT(*) c FROM ai_usage_log
                 WHERE status = 'generated' GROUP BY 1
               ) g ON g.day = d.day
         ORDER BY d.day ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $topUsers = $conn->query("
        SELECT u.id, u.username, u.email, u.is_admin, u.ai_tokens_limit,
               COUNT(z.id) AS total,
               SUM(z.created_at >= CURDATE()) AS today
          FROM users u
          JOIN zen_search_history z ON z.user_id = u.id
      GROUP BY u.id, u.username, u.email, u.is_admin, u.ai_tokens_limit
      ORDER BY total DESC
         LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    $recentQueries = $conn->query("
        SELECT z.id, z.query, z.created_at, u.username
          FROM zen_search_history z
     LEFT JOIN users u ON u.id = z.user_id
      ORDER BY z.created_at DESC
         LIMIT 25
    ")->fetchAll(PDO::FETCH_ASSOC);

    $hooks = $conn->query("
        SELECT * FROM ai_hooks ORDER BY updated_at DESC LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC);

    $recentErrors = $conn->query("
        SELECT tmdb_id, status, detail, created_at
          FROM ai_usage_log
         WHERE status IN ('error', 'blocked')
      ORDER BY created_at DESC
         LIMIT 15
    ")->fetchAll(PDO::FETCH_ASSOC);
}

$maxDaily = 1;
foreach ($daily as $d) {
    $maxDaily = max($maxDaily, (int) $d['chats'] + (int) $d['generations']);
}
?>

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
                    <div class="page-header-title"><h5>AI Control Panel</h5></div>
                    <ul class="breadcrumb">
                      <li class="breadcrumb-item"><a href="/admin"><i class="feather icon-home"></i></a></li>
                      <li class="breadcrumb-item"><a href="#!">AI</a></li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
            <?php endif; ?>

            <?php if ($stats['hooks_imported'] > 0): ?>
            <div class="alert alert-warning">
              <strong><?php echo $stats['hooks_imported']; ?> pitches need review.</strong>
              These were imported from the old JSON cache, which generated text from a title
              supplied by the caller rather than looked up from TMDB — so their contents were
              never verified. Clearing them regenerates each one safely on next view.
              <form method="POST" class="d-inline"
                    onsubmit="return confirm('Delete all imported hooks? They regenerate automatically when those pages are next opened.')">
                <input type="hidden" name="action" value="purge_imported">
                <button type="submit" class="btn btn-sm btn-warning ml-2">Purge &amp; regenerate</button>
              </form>
            </div>
            <?php endif; ?>

            <!-- Stats -->
            <?php $failures = $stats['errors_today'] + $stats['blocked_today']; ?>
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="admin-stat t-primary">
                        <div class="admin-stat-icon"><i class="feather icon-message-circle"></i></div>
                        <div>
                            <h3 class="admin-stat-value"><?php echo number_format($stats['chat_today']); ?></h3>
                            <p class="admin-stat-label">Chats Today</p>
                            <span class="admin-stat-note"><?php echo number_format($stats['chat_total']); ?> all time</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="admin-stat t-green">
                        <div class="admin-stat-icon"><i class="feather icon-cpu"></i></div>
                        <div>
                            <h3 class="admin-stat-value"><?php echo number_format($stats['gen_today']); ?></h3>
                            <p class="admin-stat-label">Pitches Generated</p>
                            <span class="admin-stat-note"><?php echo number_format($stats['hit_today']); ?> served from cache</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="admin-stat t-violet">
                        <div class="admin-stat-icon"><i class="feather icon-layers"></i></div>
                        <div>
                            <h3 class="admin-stat-value"><?php echo number_format($stats['hooks']); ?></h3>
                            <p class="admin-stat-label">Pitches Cached</p>
                            <span class="admin-stat-note<?php echo $stats['hooks_imported'] > 0 ? ' warn' : ''; ?>">
                                <?php echo $stats['hooks_imported'] > 0
                                    ? number_format($stats['hooks_imported']) . ' need review'
                                    : number_format($stats['hooks_hidden']) . ' hidden'; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="admin-stat <?php echo $failures > 0 ? 't-red' : 't-muted'; ?>">
                        <div class="admin-stat-icon"><i class="feather icon-alert-circle"></i></div>
                        <div>
                            <h3 class="admin-stat-value"><?php echo number_format($failures); ?></h3>
                            <p class="admin-stat-label">Errors / Blocked</p>
                            <span class="admin-stat-note">today</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activity -->
            <div class="row">
              <div class="col-md-8">
                <div class="card">
                  <div class="card-header"><h5>Activity (last 14 days)</h5></div>
                  <div class="card-body">
                    <?php if (empty($daily)): ?>
                      <p class="text-muted mb-0">No AI activity recorded yet.</p>
                    <?php else: ?>
                      <div style="display:flex; align-items:flex-end; gap:6px; height:170px;">
                        <?php foreach ($daily as $d):
                            $chats = (int) $d['chats']; $gens = (int) $d['generations'];
                            $ch = round(($chats / $maxDaily) * 140);
                            $gh = round(($gens  / $maxDaily) * 140);
                        ?>
                          <div style="flex:1; display:flex; flex-direction:column; justify-content:flex-end; align-items:center;"
                               title="<?php echo htmlspecialchars($d['day']); ?> — <?php echo $chats; ?> chats, <?php echo $gens; ?> pitches">
                            <div style="width:100%; background:#1dd1a1; height:<?php echo $gh; ?>px; border-radius:3px 3px 0 0;"></div>
                            <div style="width:100%; background:#4680ff; height:<?php echo $ch; ?>px;"></div>
                            <small style="font-size:0.62rem; color:#888; margin-top:4px;">
                              <?php echo date('j/n', strtotime($d['day'])); ?>
                            </small>
                          </div>
                        <?php endforeach; ?>
                      </div>
                      <div class="mt-3" style="font-size:0.8rem;">
                        <span style="display:inline-block;width:10px;height:10px;background:#4680ff;"></span> Chats
                        <span style="display:inline-block;width:10px;height:10px;background:#1dd1a1;margin-left:14px;"></span> Pitches generated
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              <div class="col-md-4">
                <div class="card">
                  <div class="card-header"><h5>Recent Failures</h5></div>
                  <div class="card-body" style="max-height:260px; overflow:auto;">
                    <?php if (empty($recentErrors)): ?>
                      <p class="text-muted mb-0">No errors or blocks recorded.</p>
                    <?php else: ?>
                      <table class="table table-sm">
                        <tbody>
                        <?php foreach ($recentErrors as $e): ?>
                          <tr>
                            <td>
                              <span class="badge badge-<?php echo $e['status'] === 'error' ? 'danger' : 'warning'; ?>">
                                <?php echo htmlspecialchars($e['status']); ?>
                              </span>
                            </td>
                            <td style="font-size:0.76rem;">
                              <?php echo htmlspecialchars($e['detail'] ?: '—'); ?><br>
                              <span class="text-muted"><?php echo htmlspecialchars($e['created_at']); ?></span>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                        </tbody>
                      </table>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>

            <!-- Top users -->
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header"><h5>Heaviest AI Users</h5></div>
                  <div class="card-body">
                    <?php if (empty($topUsers)): ?>
                      <p class="text-muted mb-0">Nobody has used ZEN AI yet.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                      <table class="table table-striped table-bordered">
                        <thead><tr>
                          <th>User</th><th>Email</th><th>Today</th><th>All time</th>
                          <th>Daily limit</th><th>Update</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($topUsers as $u): ?>
                          <tr>
                            <td>
                              <?php echo htmlspecialchars($u['username'] ?: ('#' . $u['id'])); ?>
                              <?php if ($u['is_admin']): ?><span class="badge badge-dark">admin</span><?php endif; ?>
                            </td>
                            <td style="font-size:0.82rem;"><?php echo htmlspecialchars($u['email']); ?></td>
                            <td><?php echo (int) $u['today']; ?></td>
                            <td><?php echo (int) $u['total']; ?></td>
                            <td>
                              <?php echo ((int) $u['ai_tokens_limit'] === -1)
                                  ? '<span class="text-success">Unlimited</span>'
                                  : (int) $u['ai_tokens_limit'] . ' / day'; ?>
                            </td>
                            <td>
                              <form method="POST" class="form-inline">
                                <input type="hidden" name="action" value="set_limit">
                                <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                                <input type="number" name="ai_tokens_limit" min="-1"
                                       value="<?php echo (int) $u['ai_tokens_limit']; ?>"
                                       class="form-control form-control-sm mr-1" style="width:80px;">
                                <button class="btn btn-sm btn-primary">Save</button>
                              </form>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                        </tbody>
                      </table>
                      <small class="text-muted">Use <code>-1</code> for unlimited.</small>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>

            <!-- Cached pitches -->
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header"><h5>Cached Pitches (<?php echo count($hooks); ?> shown)</h5></div>
                  <div class="card-body">
                    <?php if (empty($hooks)): ?>
                      <p class="text-muted mb-0">No pitches cached yet.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                      <table class="table table-striped table-bordered">
                        <thead><tr>
                          <th>TMDB</th><th>Title</th><th>Pitch</th>
                          <th>Source</th><th>Shown</th><th>Actions</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($hooks as $h):
                            $imported = $h['model'] === 'imported-from-json'; ?>
                          <tr<?php echo $imported ? ' style="background:rgba(255,193,7,0.08);"' : ''; ?>>
                            <td>
                              <a href="/movie-detail?id=<?php echo (int) $h['tmdb_id']; ?>&type=<?php echo htmlspecialchars($h['media_type']); ?>" target="_blank">
                                <?php echo (int) $h['tmdb_id']; ?>
                              </a>
                            </td>
                            <td style="font-size:0.83rem;"><?php echo htmlspecialchars($h['title'] ?: '—'); ?></td>
                            <td style="font-size:0.8rem; max-width:420px;"><?php echo htmlspecialchars($h['hook']); ?></td>
                            <td>
                              <?php if ($imported): ?>
                                <span class="badge badge-warning">unverified</span>
                              <?php else: ?>
                                <span class="badge badge-success">generated</span>
                              <?php endif; ?>
                            </td>
                            <td>
                              <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="toggle_hook">
                                <input type="hidden" name="hook_id" value="<?php echo (int) $h['id']; ?>">
                                <button class="btn btn-sm btn-<?php echo $h['is_active'] ? 'success' : 'secondary'; ?>">
                                  <?php echo $h['is_active'] ? 'ON' : 'OFF'; ?>
                                </button>
                              </form>
                            </td>
                            <td>
                              <form method="POST" class="d-inline" onsubmit="return confirm('Delete this pitch? It regenerates on next view.')">
                                <input type="hidden" name="action" value="delete_hook">
                                <input type="hidden" name="hook_id" value="<?php echo (int) $h['id']; ?>">
                                <button class="btn btn-sm btn-danger"><i class="feather icon-trash-2"></i></button>
                              </form>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>

            <!-- Recent queries -->
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>Recent ZEN AI Queries</h5>
                    <span class="text-muted d-block mt-2" style="font-size:0.85rem;">
                      What people actually ask is the best signal for what to add to the catalogue.
                    </span>
                  </div>
                  <div class="card-body">
                    <?php if (empty($recentQueries)): ?>
                      <p class="text-muted mb-0">No queries yet.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                      <table class="table table-sm table-striped">
                        <thead><tr><th>User</th><th>Query</th><th>When</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentQueries as $q2): ?>
                          <tr>
                            <td style="font-size:0.8rem;"><?php echo htmlspecialchars($q2['username'] ?: 'guest'); ?></td>
                            <td style="font-size:0.83rem;"><?php echo htmlspecialchars($q2['query']); ?></td>
                            <td style="font-size:0.76rem;" class="text-muted"><?php echo htmlspecialchars($q2['created_at']); ?></td>
                          </tr>
                        <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
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
  <script src="/da/assets/js/pcoded.min.js"></script>
  <script src="/da/assets/js/horizontal-menu.js"></script>
</body>
</html>
