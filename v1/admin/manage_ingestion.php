<?php
ob_start();
$level_check = ['MASTER', 3, 2, 1];
include 'includes/header.php';

// ==========================================
// INGESTION REVIEW QUEUE
// ==========================================
// Items land here when the scraper could not clear them automatically:
// either the licence was ambiguous, or the TMDB match was below the
// confidence threshold. Approving an ambiguous licence is a human assertion
// that the title may be redistributed, so every decision is stamped with the
// admin id that made it.

$message = '';
$messageType = '';

$adminId = $_SESSION['admin_id'] ?? null;

// Guard: the tables only exist once the ingestion migration has been run.
$tablesReady = true;
try {
    $conn->query("SELECT 1 FROM ingestion_jobs LIMIT 1");
} catch (PDOException $e) {
    $tablesReady = false;
    $message = "Ingestion tables not found. Run: php v1/ingest/migrate.php up";
    $messageType = 'warning';
}

if ($tablesReady && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    $jobId = (int) ($_POST['job_id'] ?? 0);
    $note  = trim($_POST['review_note'] ?? '');

    // Load the job first so decisions can be validated against its state.
    $job = null;
    if ($jobId) {
        $stmt = $conn->prepare("SELECT * FROM ingestion_jobs WHERE id = ? LIMIT 1");
        $stmt->execute([$jobId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$job) {
        $message = "Job not found.";
        $messageType = 'danger';

    } elseif ($_POST['action'] === 'approve') {

        // A job cannot be published without a catalogue identity, otherwise
        // the transcode stage has nothing to attach the download to.
        if (empty($job['tmdb_id'])) {
            $message = "Set a TMDB ID before approving \"{$job['source_title']}\".";
            $messageType = 'danger';
        } else {
            $stmt = $conn->prepare(
                "UPDATE ingestion_jobs
                    SET status = 'matched', reviewed_by = ?, reviewed_at = NOW(), review_note = ?
                  WHERE id = ?"
            );
            $stmt->execute([$adminId, $note ?: 'Approved for transcoding', $jobId]);
            $message = "Approved: {$job['source_title']}";
            $messageType = 'success';
        }

    } elseif ($_POST['action'] === 'reject') {

        $stmt = $conn->prepare(
            "UPDATE ingestion_jobs
                SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), review_note = ?
              WHERE id = ?"
        );
        $stmt->execute([$adminId, $note ?: 'Rejected on review', $jobId]);
        $message = "Rejected: {$job['source_title']}";
        $messageType = 'success';

    } elseif ($_POST['action'] === 'set_tmdb') {

        $newTmdbId = (int) ($_POST['tmdb_id'] ?? 0);

        if (!$newTmdbId) {
            $message = "Enter a TMDB ID.";
            $messageType = 'danger';
        } else {
            // Confirm the id actually resolves before storing it.
            $tmdb = fetchTmdbApi("movie/{$newTmdbId}", [], 604800);

            if (!$tmdb || empty($tmdb['title'])) {
                $message = "TMDB ID {$newTmdbId} did not resolve to a movie.";
                $messageType = 'danger';
            } else {
                // Confidence 1.000 records that a human made this match.
                $stmt = $conn->prepare(
                    "UPDATE ingestion_jobs
                        SET tmdb_id = ?, tmdb_title = ?, tmdb_confidence = 1.000,
                            reviewed_by = ?, reviewed_at = NOW()
                      WHERE id = ?"
                );
                $stmt->execute([$newTmdbId, $tmdb['title'], $adminId, $jobId]);
                $message = "Matched \"{$job['source_title']}\" to {$tmdb['title']}. Approve it to queue for transcoding.";
                $messageType = 'success';
            }
        }

    } elseif ($_POST['action'] === 'requeue') {

        $stmt = $conn->prepare(
            "UPDATE ingestion_jobs
                SET status = 'needs_review', reviewed_by = ?, reviewed_at = NOW(), review_note = ?
              WHERE id = ?"
        );
        $stmt->execute([$adminId, $note ?: 'Reopened for review', $jobId]);
        $message = "Reopened: {$job['source_title']}";
        $messageType = 'success';
    }
}

// ==========================================
// LOAD DATA
// ==========================================

$counts = [
    'needs_review' => 0, 'matched' => 0, 'rejected' => 0,
    'published' => 0, 'failed' => 0, 'queued' => 0, 'transcoding' => 0, 'discovered' => 0,
];
$review = $matched = $rejected = [];
$runs = [];

if ($tablesReady) {
    foreach ($conn->query("SELECT status, COUNT(*) c FROM ingestion_jobs GROUP BY status") as $row) {
        $counts[$row['status']] = (int) $row['c'];
    }

    $review = $conn->query(
        "SELECT * FROM ingestion_jobs WHERE status = 'needs_review' ORDER BY tmdb_confidence DESC, source_title ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    $matched = $conn->query(
        "SELECT * FROM ingestion_jobs WHERE status = 'matched' ORDER BY tmdb_title ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    $rejected = $conn->query(
        "SELECT * FROM ingestion_jobs WHERE status = 'rejected' ORDER BY updated_at DESC LIMIT 50"
    )->fetchAll(PDO::FETCH_ASSOC);

    $runs = $conn->query(
        "SELECT * FROM ingestion_runs ORDER BY started_at DESC LIMIT 5"
    )->fetchAll(PDO::FETCH_ASSOC);
}

/** Licences we accept without a human decision. Anything else needs a look. */
function ingestion_licence_is_clear(?string $label): bool
{
    return in_array($label, ['public_domain', 'cc0', 'cc-by', 'cc-by-sa'], true);
}

function ingestion_size_mb($bytes): string
{
    if (!$bytes) return '—';
    $mb = $bytes / 1048576;
    return $mb >= 1024
        ? number_format($mb / 1024, 2) . ' GB'
        : number_format($mb) . ' MB';
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
                    <div class="page-header-title">
                      <h5>Ingestion Review Queue</h5>
                    </div>
                    <ul class="breadcrumb">
                      <li class="breadcrumb-item"><a href="/admin"><i class="feather icon-home"></i></a></li>
                      <li class="breadcrumb-item"><a href="#!">Ingestion</a></li>
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

            <!-- Stats -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="admin-stat <?php echo $counts['needs_review'] > 0 ? 't-amber' : 't-muted'; ?>">
                        <div class="admin-stat-icon"><i class="feather icon-clock"></i></div>
                        <div>
                            <h3 class="admin-stat-value"><?php echo number_format($counts['needs_review']); ?></h3>
                            <p class="admin-stat-label">Awaiting Review</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="admin-stat t-green">
                        <div class="admin-stat-icon"><i class="feather icon-check-circle"></i></div>
                        <div>
                            <h3 class="admin-stat-value"><?php echo number_format($counts['matched']); ?></h3>
                            <p class="admin-stat-label">Ready to Transcode</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="admin-stat t-cyan">
                        <div class="admin-stat-icon"><i class="feather icon-film"></i></div>
                        <div>
                            <h3 class="admin-stat-value"><?php echo number_format($counts['published']); ?></h3>
                            <p class="admin-stat-label">Published</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="admin-stat t-muted">
                        <div class="admin-stat-icon"><i class="feather icon-slash"></i></div>
                        <div>
                            <h3 class="admin-stat-value"><?php echo number_format($counts['rejected']); ?></h3>
                            <p class="admin-stat-label">Rejected</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================= REVIEW QUEUE ================= -->
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>Awaiting Review (<?php echo count($review); ?>)</h5>
                    <span class="text-muted d-block mt-2" style="font-size:0.85rem;">
                      Approving an item with an unclear licence records you as asserting
                      that it may be redistributed on a paid service.
                    </span>
                  </div>
                  <div class="card-body">
                    <?php if (empty($review)): ?>
                      <p class="text-muted mb-0">Nothing awaiting review.</p>
                    <?php else: ?>
                    <div class="dt-responsive table-responsive">
                      <table class="table table-striped table-bordered">
                        <thead>
                          <tr>
                            <th>Title</th>
                            <th>Year</th>
                            <th>Quality</th>
                            <th>Size</th>
                            <th>Licence</th>
                            <th>TMDB Match</th>
                            <th>Why Held</th>
                            <th style="min-width:230px;">Actions</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($review as $job): ?>
                            <tr>
                              <td>
                                <strong><?php echo htmlspecialchars($job['source_title']); ?></strong><br>
                                <a href="https://archive.org/details/<?php echo urlencode($job['source_identifier']); ?>"
                                   target="_blank" rel="noopener" style="font-size:0.8rem;">
                                   view source &nearr;
                                </a>
                              </td>
                              <td><?php echo $job['source_year'] ?: '—'; ?></td>
                              <td><span class="badge badge-danger"><?php echo htmlspecialchars($job['quality'] ?: '?'); ?></span></td>
                              <td><?php echo ingestion_size_mb($job['source_size']); ?></td>
                              <td>
                                <?php $clear = ingestion_licence_is_clear($job['license_label']); ?>
                                <span class="badge badge-<?php echo $clear ? 'success' : 'warning'; ?>">
                                  <?php echo htmlspecialchars($job['license_label'] ?: 'none'); ?>
                                </span>
                                <?php if (!empty($job['license_url'])): ?>
                                  <br><a href="<?php echo htmlspecialchars($job['license_url']); ?>"
                                         target="_blank" rel="noopener" style="font-size:0.75rem;">licence &nearr;</a>
                                <?php endif; ?>
                              </td>
                              <td>
                                <?php if ($job['tmdb_id']): ?>
                                  <a href="/movie-detail?id=<?php echo (int) $job['tmdb_id']; ?>&type=movie" target="_blank">
                                    <?php echo htmlspecialchars($job['tmdb_title'] ?: $job['tmdb_id']); ?>
                                  </a>
                                  <br><span class="text-muted" style="font-size:0.78rem;">
                                    confidence <?php echo number_format((float) $job['tmdb_confidence'], 2); ?>
                                  </span>
                                <?php else: ?>
                                  <span class="text-danger">unmatched</span>
                                <?php endif; ?>
                              </td>
                              <td style="font-size:0.8rem;"><?php echo htmlspecialchars($job['status_message'] ?: ''); ?></td>
                              <td>
                                <!-- set / correct the TMDB id -->
                                <form method="POST" class="form-inline mb-2">
                                  <input type="hidden" name="action" value="set_tmdb">
                                  <input type="hidden" name="job_id" value="<?php echo (int) $job['id']; ?>">
                                  <input type="number" name="tmdb_id" class="form-control form-control-sm mr-1"
                                         style="width:105px;" placeholder="TMDB ID"
                                         value="<?php echo $job['tmdb_id'] ? (int) $job['tmdb_id'] : ''; ?>" required>
                                  <button type="submit" class="btn btn-sm btn-info">Set</button>
                                </form>

                                <!-- approve -->
                                <form method="POST" class="d-inline"
                                      onsubmit="return confirm('<?php echo $clear
                                        ? 'Approve this title for transcoding?'
                                        : 'This licence is NOT clearly verified. Approving records you as asserting redistribution rights. Continue?'; ?>')">
                                  <input type="hidden" name="action" value="approve">
                                  <input type="hidden" name="job_id" value="<?php echo (int) $job['id']; ?>">
                                  <button type="submit" class="btn btn-sm btn-success"
                                          <?php echo $job['tmdb_id'] ? '' : 'disabled title="Set a TMDB ID first"'; ?>>
                                    Approve
                                  </button>
                                </form>

                                <!-- reject -->
                                <form method="POST" class="d-inline" onsubmit="return confirm('Reject this title?')">
                                  <input type="hidden" name="action" value="reject">
                                  <input type="hidden" name="job_id" value="<?php echo (int) $job['id']; ?>">
                                  <button type="submit" class="btn btn-sm btn-danger">Reject</button>
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

            <!-- ================= READY TO TRANSCODE ================= -->
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header"><h5>Ready to Transcode (<?php echo count($matched); ?>)</h5></div>
                  <div class="card-body">
                    <?php if (empty($matched)): ?>
                      <p class="text-muted mb-0">Nothing queued yet.</p>
                    <?php else: ?>
                    <div class="dt-responsive table-responsive">
                      <table class="table table-striped table-bordered">
                        <thead>
                          <tr>
                            <th>TMDB</th>
                            <th>Title</th>
                            <th>Year</th>
                            <th>Quality</th>
                            <th>Size</th>
                            <th>Licence</th>
                            <th>Reviewed</th>
                            <th>Actions</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($matched as $job): ?>
                            <tr>
                              <td><?php echo (int) $job['tmdb_id']; ?></td>
                              <td>
                                <a href="/movie-detail?id=<?php echo (int) $job['tmdb_id']; ?>&type=movie" target="_blank">
                                  <?php echo htmlspecialchars($job['tmdb_title'] ?: $job['source_title']); ?>
                                </a>
                              </td>
                              <td><?php echo $job['source_year'] ?: '—'; ?></td>
                              <td><span class="badge badge-danger"><?php echo htmlspecialchars($job['quality'] ?: '?'); ?></span></td>
                              <td><?php echo ingestion_size_mb($job['source_size']); ?></td>
                              <td>
                                <span class="badge badge-<?php echo ingestion_licence_is_clear($job['license_label']) ? 'success' : 'warning'; ?>">
                                  <?php echo htmlspecialchars($job['license_label'] ?: 'none'); ?>
                                </span>
                              </td>
                              <td style="font-size:0.8rem;">
                                <?php echo $job['reviewed_at']
                                    ? htmlspecialchars($job['reviewed_at']) . '<br>by admin #' . (int) $job['reviewed_by']
                                    : '<span class="text-muted">auto</span>'; ?>
                              </td>
                              <td>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Send back to the review queue?')">
                                  <input type="hidden" name="action" value="requeue">
                                  <input type="hidden" name="job_id" value="<?php echo (int) $job['id']; ?>">
                                  <button type="submit" class="btn btn-sm btn-secondary">Reopen</button>
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

            <!-- ================= REJECTED + RUNS ================= -->
            <div class="row">
              <div class="col-md-7">
                <div class="card">
                  <div class="card-header"><h5>Rejected (<?php echo count($rejected); ?>)</h5></div>
                  <div class="card-body">
                    <?php if (empty($rejected)): ?>
                      <p class="text-muted mb-0">Nothing rejected.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                      <table class="table table-sm table-bordered">
                        <thead><tr><th>Title</th><th>Reason</th><th></th></tr></thead>
                        <tbody>
                          <?php foreach ($rejected as $job): ?>
                            <tr>
                              <td style="font-size:0.85rem;"><?php echo htmlspecialchars($job['source_title']); ?></td>
                              <td style="font-size:0.8rem;"><?php echo htmlspecialchars($job['review_note'] ?: $job['status_message'] ?: ''); ?></td>
                              <td>
                                <form method="POST" onsubmit="return confirm('Reopen for review?')">
                                  <input type="hidden" name="action" value="requeue">
                                  <input type="hidden" name="job_id" value="<?php echo (int) $job['id']; ?>">
                                  <button type="submit" class="btn btn-sm btn-outline-secondary">Reopen</button>
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

              <div class="col-md-5">
                <div class="card">
                  <div class="card-header"><h5>Recent Scraper Runs</h5></div>
                  <div class="card-body">
                    <?php if (empty($runs)): ?>
                      <p class="text-muted mb-0">No runs recorded yet.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                      <table class="table table-sm">
                        <thead><tr><th>Collection</th><th>Seen</th><th>New</th><th>Matched</th><th>Review</th></tr></thead>
                        <tbody>
                          <?php foreach ($runs as $run): ?>
                            <tr>
                              <td style="font-size:0.82rem;"><?php echo htmlspecialchars($run['collection'] ?: '—'); ?></td>
                              <td><?php echo (int) $run['items_seen']; ?></td>
                              <td><?php echo (int) $run['items_new']; ?></td>
                              <td><?php echo (int) $run['items_matched']; ?></td>
                              <td><?php echo (int) $run['items_needs_review']; ?></td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                    <?php endif; ?>
                    <p class="text-muted mb-0" style="font-size:0.8rem;">
                      Add titles with:<br>
                      <code>php v1/ingest/scrape.php --collection=feature_films</code>
                    </p>
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

  <!-- Required Js -->
  <script src="/da/assets/js/vendor-all.min.js"></script>
  <script src="/da/assets/plugins/bootstrap/js/bootstrap.min.js"></script>
  <script src="/da/assets/js/pcoded.min.js"></script>
  <script src="/da/assets/js/horizontal-menu.js"></script>
</body>
</html>
