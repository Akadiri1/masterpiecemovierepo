<?php
ob_start();
$level_check = ['MASTER', 3, 2, 1];
include 'includes/header.php';

// ==========================================
// HANDLE FORM ACTIONS
// ==========================================

// Auto-create table if it doesn't exist
try {
    $conn->query("SELECT 1 FROM media_downloads LIMIT 1");
} catch (PDOException $e) {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS media_downloads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tmdb_id INT NOT NULL,
            media_type ENUM('movie','tv') NOT NULL DEFAULT 'movie',
            season INT DEFAULT NULL,
            episode INT DEFAULT NULL,
            quality VARCHAR(20) NOT NULL DEFAULT '720p',
            language VARCHAR(50) NOT NULL DEFAULT 'English',
            file_size VARCHAR(50) DEFAULT NULL,
            file_name VARCHAR(500) DEFAULT NULL,
            download_url TEXT NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tmdb_lookup (tmdb_id, media_type),
            INDEX idx_episode_lookup (tmdb_id, media_type, season, episode)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

$message = '';
$messageType = '';

// --- ADD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'add') {
        $tmdbId     = (int) ($_POST['tmdb_id'] ?? 0);
        $mediaType  = $_POST['media_type'] ?? 'movie';
        $season     = !empty($_POST['season']) ? (int) $_POST['season'] : null;
        $episode    = !empty($_POST['episode']) ? (int) $_POST['episode'] : null;
        $quality    = trim($_POST['quality'] ?? '720p');
        $language   = trim($_POST['language'] ?? 'English');
        $fileSize   = trim($_POST['file_size'] ?? '');
        $fileName   = trim($_POST['file_name'] ?? '');
        $downloadUrl = trim($_POST['download_url'] ?? '');

        if ($tmdbId && $downloadUrl) {
            $stmt = $conn->prepare("INSERT INTO media_downloads 
                (tmdb_id, media_type, season, episode, quality, language, file_size, file_name, download_url) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$tmdbId, $mediaType, $season, $episode, $quality, $language, $fileSize, $fileName, $downloadUrl]);
            $message = "Download link added successfully!";
            $messageType = 'success';
        } else {
            $message = "TMDB ID and Download URL are required.";
            $messageType = 'danger';
        }
    }

    if ($_POST['action'] === 'bulk_add') {
        $tmdbId     = (int) ($_POST['tmdb_id'] ?? 0);
        $mediaType  = $_POST['media_type'] ?? 'tv';
        $language   = trim($_POST['language'] ?? 'English');
        $bulkUrls   = trim($_POST['bulk_urls'] ?? '');

        if ($tmdbId && $bulkUrls) {
            $lines = array_filter(array_map('trim', explode("\n", $bulkUrls)));
            $addedCount = 0;

            foreach ($lines as $line) {
                // Try to auto-parse season/episode from URL or filename
                // Pattern: S01E01, S1E1, s01e01, etc.
                $season = null; $episode = null; $quality = '720p'; $fileSize = '';
                
                if (preg_match('/S(\d+)E(\d+)/i', $line, $m)) {
                    $season  = (int) $m[1];
                    $episode = (int) $m[2];
                }
                if (preg_match('/(480p|720p|1080p|2160p|4K)/i', $line, $qm)) {
                    $quality = $qm[1];
                }

                $stmt = $conn->prepare("INSERT INTO media_downloads 
                    (tmdb_id, media_type, season, episode, quality, language, file_size, file_name, download_url) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $fileName = basename(urldecode(parse_url($line, PHP_URL_PATH)));
                $stmt->execute([$tmdbId, $mediaType, $season, $episode, $quality, $language, $fileSize, $fileName, $line]);
                $addedCount++;
            }
            $message = "{$addedCount} download links added successfully!";
            $messageType = 'success';
        } else {
            $message = "TMDB ID and URLs are required.";
            $messageType = 'danger';
        }
    }

    if ($_POST['action'] === 'delete' && isset($_POST['delete_id'])) {
        $conn->prepare("DELETE FROM media_downloads WHERE id = ?")->execute([(int) $_POST['delete_id']]);
        $message = "Download link deleted.";
        $messageType = 'warning';
    }

    if ($_POST['action'] === 'toggle' && isset($_POST['toggle_id'])) {
        $conn->prepare("UPDATE media_downloads SET is_active = NOT is_active WHERE id = ?")->execute([(int) $_POST['toggle_id']]);
        $message = "Download link status toggled.";
        $messageType = 'info';
    }
}

// --- FETCH ALL LINKS ---
$filterTmdb = $_GET['filter_tmdb'] ?? '';
$filterType = $_GET['filter_type'] ?? '';

$sql = "SELECT * FROM media_downloads WHERE 1=1";
$params = [];
if ($filterTmdb) { $sql .= " AND tmdb_id = ?"; $params[] = (int) $filterTmdb; }
if ($filterType) { $sql .= " AND media_type = ?"; $params[] = $filterType; }
$sql .= " ORDER BY tmdb_id ASC, season ASC, episode ASC, quality ASC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$downloads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count stats
$totalLinks = count($downloads);
$activeLinks = count(array_filter($downloads, fn($d) => $d['is_active']));
$movieLinks = count(array_filter($downloads, fn($d) => $d['media_type'] === 'movie'));
$tvLinks = count(array_filter($downloads, fn($d) => $d['media_type'] === 'tv'));
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
                      <h5>Manage Download Links</h5>
                    </div>
                    <ul class="breadcrumb">
                      <li class="breadcrumb-item"><a href="/admin"><i class="feather icon-home"></i></a></li>
                      <li class="breadcrumb-item"><a href="#!">Download Links</a></li>
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

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h3><?php echo $totalLinks; ?></h3>
                            <p class="mb-0">Total Links</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h3><?php echo $activeLinks; ?></h3>
                            <p class="mb-0">Active</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h3><?php echo $movieLinks; ?></h3>
                            <p class="mb-0">Movies</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <h3><?php echo $tvLinks; ?></h3>
                            <p class="mb-0">TV Shows</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- ADD SINGLE LINK -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header"><h5><i class="feather icon-plus-circle"></i> Add Download Link</h5></div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="add">
                                <div class="row">
                                    <div class="col-md-6 form-group">
                                        <label>TMDB ID <span class="text-danger">*</span></label>
                                        <input type="number" name="tmdb_id" class="form-control" required placeholder="e.g. 7704">
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label>Media Type</label>
                                        <select name="media_type" class="form-control" id="mediaTypeSelect">
                                            <option value="movie">Movie</option>
                                            <option value="tv" selected>TV Show</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row" id="episodeFields">
                                    <div class="col-md-6 form-group">
                                        <label>Season</label>
                                        <input type="number" name="season" class="form-control" placeholder="e.g. 2">
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label>Episode</label>
                                        <input type="number" name="episode" class="form-control" placeholder="e.g. 1">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 form-group">
                                        <label>Quality</label>
                                        <select name="quality" class="form-control">
                                            <option value="480p">480p</option>
                                            <option value="720p" selected>720p</option>
                                            <option value="1080p">1080p</option>
                                            <option value="2160p">4K / 2160p</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label>Language</label>
                                        <input type="text" name="language" class="form-control" value="English">
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label>File Size</label>
                                        <input type="text" name="file_size" class="form-control" placeholder="e.g. 800 MB">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Download URL <span class="text-danger">*</span></label>
                                    <input type="url" name="download_url" class="form-control" required 
                                           placeholder="https://s3.ap-southeast-1.wasabisys.com/streamflix/...">
                                </div>
                                <div class="form-group">
                                    <label>File Name (optional)</label>
                                    <input type="text" name="file_name" class="form-control" placeholder="Auto-detected from URL">
                                </div>
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="feather icon-plus"></i> Add Download Link
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- BULK ADD (for TV Series) -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header"><h5><i class="feather icon-layers"></i> Bulk Add (TV Series)</h5></div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="bulk_add">
                                <div class="row">
                                    <div class="col-md-6 form-group">
                                        <label>TMDB ID <span class="text-danger">*</span></label>
                                        <input type="number" name="tmdb_id" class="form-control" required placeholder="e.g. 7704">
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label>Media Type</label>
                                        <select name="media_type" class="form-control">
                                            <option value="tv" selected>TV Show</option>
                                            <option value="movie">Movie</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Language</label>
                                    <input type="text" name="language" class="form-control" value="English">
                                </div>
                                <div class="form-group">
                                    <label>Paste URLs (one per line) <span class="text-danger">*</span></label>
                                    <textarea name="bulk_urls" class="form-control" rows="10" required 
                                              placeholder="https://s3.ap-southeast-1.wasabisys.com/streamflix/tv/legendoftheseeker/s2/Legend of the Seeker S02E01 {English} 720p WEB-DL ESub [BollyFlix].mkv
https://s3.ap-southeast-1.wasabisys.com/streamflix/tv/legendoftheseeker/s2/Legend of the Seeker S02E02 {English} 720p WEB-DL ESub [BollyFlix].mkv
https://s3.ap-southeast-1.wasabisys.com/streamflix/tv/legendoftheseeker/s2/Legend of the Seeker S02E03 {English} 720p WEB-DL ESub [BollyFlix].mkv"></textarea>
                                    <small class="text-muted">Season, Episode, and Quality are auto-detected from the URL (e.g. S02E01, 720p)</small>
                                </div>
                                <button type="submit" class="btn btn-success btn-block">
                                    <i class="feather icon-upload"></i> Bulk Add Links
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- EXISTING LINKS TABLE -->
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>Download Links (<?php echo $totalLinks; ?>)</h5>
                    <!-- Filters -->
                    <form method="GET" class="float-right d-inline-flex gap-2" style="gap:10px;">
                        <input type="number" name="filter_tmdb" class="form-control form-control-sm" placeholder="TMDB ID" value="<?php echo htmlspecialchars($filterTmdb); ?>" style="width:120px;">
                        <select name="filter_type" class="form-control form-control-sm" style="width:100px;">
                            <option value="">All</option>
                            <option value="movie" <?php echo $filterType === 'movie' ? 'selected' : ''; ?>>Movie</option>
                            <option value="tv" <?php echo $filterType === 'tv' ? 'selected' : ''; ?>>TV</option>
                        </select>
                        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                        <a href="/admin/manage_downloads.php" class="btn btn-sm btn-secondary">Clear</a>
                    </form>
                  </div>

                  <div class="card-body">
                    <div class="dt-responsive table-responsive">
                      <table id="simpletable" class="table table-striped table-bordered nowrap">
                        <thead>
                          <tr>
                            <th>ID</th>
                            <th>TMDB ID</th>
                            <th>Type</th>
                            <th>S/E</th>
                            <th>Quality</th>
                            <th>Language</th>
                            <th>Size</th>
                            <th>URL</th>
                            <th>Active</th>
                            <th>Actions</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($downloads as $dl): ?>
                            <tr>
                              <td><?php echo $dl['id']; ?></td>
                              <td>
                                <a href="/movie-detail?id=<?php echo $dl['tmdb_id']; ?>&type=<?php echo $dl['media_type']; ?>" target="_blank">
                                    <?php echo $dl['tmdb_id']; ?>
                                </a>
                              </td>
                              <td>
                                <span class="badge badge-<?php echo $dl['media_type'] === 'movie' ? 'info' : 'warning'; ?>">
                                    <?php echo strtoupper($dl['media_type']); ?>
                                </span>
                              </td>
                              <td>
                                <?php if ($dl['season']): ?>
                                    S<?php echo str_pad($dl['season'], 2, '0', STR_PAD_LEFT); ?>E<?php echo str_pad($dl['episode'], 2, '0', STR_PAD_LEFT); ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                              </td>
                              <td><span class="badge badge-danger"><?php echo $dl['quality']; ?></span></td>
                              <td><?php echo htmlspecialchars($dl['language']); ?></td>
                              <td><?php echo htmlspecialchars($dl['file_size'] ?: '—'); ?></td>
                              <td>
                                <a href="<?php echo htmlspecialchars($dl['download_url']); ?>" target="_blank" title="<?php echo htmlspecialchars($dl['download_url']); ?>">
                                    <?php echo htmlspecialchars(substr($dl['download_url'], 0, 50)); ?>...
                                </a>
                              </td>
                              <td>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="toggle_id" value="<?php echo $dl['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-<?php echo $dl['is_active'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $dl['is_active'] ? 'ON' : 'OFF'; ?>
                                    </button>
                                </form>
                              </td>
                              <td>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this link?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="delete_id" value="<?php echo $dl['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="feather icon-trash-2"></i>
                                    </button>
                                </form>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
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
  <script src="/da/assets/plugins/prism/js/prism.min.js"></script>
  <script src="/da/assets/js/horizontal-menu.js"></script>
  <script src="/da/assets/plugins/data-tables/js/datatables.min.js"></script>
  <script src="/da/assets/js/pages/data-basic-custom.js"></script>

  <script>
    // Toggle episode fields based on media type
    const mediaTypeSelect = document.getElementById('mediaTypeSelect');
    const episodeFields = document.getElementById('episodeFields');
    
    function toggleEpisodeFields() {
        episodeFields.style.display = mediaTypeSelect.value === 'tv' ? 'flex' : 'none';
    }
    
    if (mediaTypeSelect) {
        mediaTypeSelect.addEventListener('change', toggleEpisodeFields);
        toggleEpisodeFields();
    }
  </script>
</body>
</html>
