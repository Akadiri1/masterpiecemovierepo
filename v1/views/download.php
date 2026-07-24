<?php
// ==========================================
// DOWNLOAD HANDLER - Serves files from Wasabi S3
// ==========================================

// ==========================================
// 1. GET PARAMETERS
// ==========================================
$mediaId    = $_GET['id'] ?? null;
$mediaType  = $_GET['type'] ?? 'movie';
$seasonNum  = isset($_GET['season']) ? (int)$_GET['season'] : null;
$episodeNum = isset($_GET['episode']) ? (int)$_GET['episode'] : null;
$quality    = $_GET['quality'] ?? '720p';

if (!$mediaId) {
    http_response_code(400);
    die("Missing media ID.");
}

// ==========================================
// 2. FETCH TITLE FROM TMDB
// ==========================================
$title = '';
try {
    if (function_exists('fetchTmdbApi')) {
        if ($mediaType === 'movie') {
            $d = fetchTmdbApi("movie/{$mediaId}");
            $title = $d['title'] ?? '';
        } else {
            $d = fetchTmdbApi("tv/{$mediaId}");
            $title = $d['name'] ?? '';
        }
    }
} catch (Exception $e) {}

if (empty($title)) $title = "Media {$mediaId}";

// ==========================================
// 3. LOOK UP DOWNLOAD URL FROM DATABASE
// ==========================================
$downloadUrl = '';
$fileName = '';
$allLinks = [];

try {
    if (isset($conn)) {
        // First: exact match (tmdb_id + type + season + episode + quality)
        if ($mediaType === 'tv' && $seasonNum && $episodeNum) {
            // Try exact quality match first
            $stmt = $conn->prepare(
                "SELECT download_url, file_name, quality FROM media_downloads 
                 WHERE tmdb_id = ? AND media_type = ? AND season = ? AND episode = ? AND quality = ? AND is_active = 1
                 ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute([$mediaId, $mediaType, $seasonNum, $episodeNum, $quality]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            // Fallback: any quality for this episode
            if (!$row) {
                $stmt = $conn->prepare(
                    "SELECT download_url, file_name, quality FROM media_downloads 
                     WHERE tmdb_id = ? AND media_type = ? AND season = ? AND episode = ? AND is_active = 1
                     ORDER BY id DESC LIMIT 1"
                );
                $stmt->execute([$mediaId, $mediaType, $seasonNum, $episodeNum]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } else {
            // Movie: exact quality
            $stmt = $conn->prepare(
                "SELECT download_url, file_name, quality FROM media_downloads 
                 WHERE tmdb_id = ? AND media_type = ? AND quality = ? AND is_active = 1
                 ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute([$mediaId, $mediaType, $quality]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            // Fallback: any quality for this movie
            if (!$row) {
                $stmt = $conn->prepare(
                    "SELECT download_url, file_name, quality FROM media_downloads 
                     WHERE tmdb_id = ? AND media_type = ? AND is_active = 1
                     ORDER BY id DESC LIMIT 1"
                );
                $stmt->execute([$mediaId, $mediaType]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }

        if ($row && !empty($row['download_url'])) {
            $downloadUrl = $row['download_url'];
            $fileName    = $row['file_name'] ?? '';
        }

        // Also fetch ALL available links for this media (for the "not found" page)
        if ($mediaType === 'tv' && $seasonNum) {
            $allStmt = $conn->prepare(
                "SELECT download_url, file_name, quality, season, episode FROM media_downloads 
                 WHERE tmdb_id = ? AND media_type = ? AND season = ? AND is_active = 1
                 ORDER BY episode ASC, quality ASC"
            );
            $allStmt->execute([$mediaId, $mediaType, $seasonNum]);
        } else {
            $allStmt = $conn->prepare(
                "SELECT download_url, file_name, quality, season, episode FROM media_downloads 
                 WHERE tmdb_id = ? AND media_type = ? AND is_active = 1
                 ORDER BY quality ASC"
            );
            $allStmt->execute([$mediaId, $mediaType]);
        }
        $allLinks = $allStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log('Download DB lookup error: ' . $e->getMessage());
}

// ==========================================
// 4. LOG DOWNLOAD (if we have a URL)
// ==========================================
if ($downloadUrl) {
    try {
        if (isset($conn)) {
            $userId = $_SESSION['user_id'] ?? null;
            $conn->prepare(
                "INSERT INTO download_logs (user_id, tmdb_id, media_type, season, episode, quality, downloaded_at) VALUES (?, ?, ?, ?, ?, ?, NOW())"
            )->execute([$userId, $mediaId, $mediaType, $seasonNum, $episodeNum, $quality]);
        }
    } catch (Exception $e) {
        error_log('Download log error: ' . $e->getMessage());
    }
}

// ==========================================
// 5. REDIRECT OR SHOW "NOT AVAILABLE" PAGE
// ==========================================

// AJAX request → return JSON
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode([
        'success'      => !empty($downloadUrl),
        'download_url' => $downloadUrl,
        'file_name'    => $fileName,
        'title'        => $title,
        'quality'      => $quality,
        'all_links'    => $allLinks
    ]);
    exit;
}

// If we found a download URL → redirect directly to S3
if ($downloadUrl) {
    // Use JavaScript redirect since headers may already be sent by the app framework
    echo '<html><head><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($downloadUrl) . '"></head>';
    echo '<body style="background:#000;color:#fff;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;">';
    echo '<div style="text-align:center;"><h2>Starting Download...</h2>';
    echo '<p style="color:#aaa;">If the download doesn\'t start, <a href="' . htmlspecialchars($downloadUrl) . '" style="color:#4cd137;">click here</a>.</p>';
    echo '</div></body></html>';
    echo '<script>window.location.href = ' . json_encode($downloadUrl) . ';</script>';
    exit;
}

// NO download URL found → show a helpful page
$subtitle = ($mediaType === 'tv' && $seasonNum && $episodeNum) 
    ? "Season {$seasonNum}, Episode {$episodeNum}" 
    : "Movie";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Download Not Available</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #0a0a0a; color: #fff; font-family: 'Helvetica Neue', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { text-align: center; max-width: 550px; padding: 40px 24px; }
        .icon { font-size: 4rem; color: #e50914; margin-bottom: 20px; }
        h1 { font-size: 1.8rem; margin-bottom: 10px; }
        .subtitle { color: #aaa; font-size: 1.1rem; margin-bottom: 30px; }
        .quality-tag { display: inline-block; background: #e50914; padding: 3px 10px; border-radius: 4px; font-size: 0.85rem; font-weight: 700; margin: 0 4px; }
        .available-links { text-align: left; margin-top: 30px; }
        .available-links h3 { font-size: 1rem; color: #888; margin-bottom: 12px; }
        .dl-link { display: flex; align-items: center; justify-content: space-between; background: #1a1a1a; padding: 12px 16px; border-radius: 8px; margin-bottom: 8px; text-decoration: none; color: #fff; border: 1px solid #333; transition: all 0.2s; }
        .dl-link:hover { background: #252525; border-color: #4cd137; }
        .dl-link .info { display: flex; align-items: center; gap: 10px; }
        .dl-link .badge { background: #e50914; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; }
        .dl-link .ep { color: #aaa; font-size: 0.85rem; }
        .dl-link .icon-dl { color: #4cd137; font-size: 1.2rem; }
        .back-btn { display: inline-block; margin-top: 24px; padding: 12px 28px; background: rgba(255,255,255,0.1); color: #fff; border-radius: 25px; text-decoration: none; font-weight: 600; transition: all 0.2s; }
        .back-btn:hover { background: #fff; color: #000; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon"><i class="fa-solid fa-circle-exclamation"></i></div>
        <h1><?php echo htmlspecialchars($title); ?></h1>
        <p class="subtitle">
            <?php echo $subtitle; ?> — <span class="quality-tag"><?php echo htmlspecialchars($quality); ?></span> not available yet.
        </p>

        <?php if (!empty($allLinks)): ?>
        <div class="available-links">
            <h3><i class="fa-solid fa-download"></i> Available Downloads:</h3>
            <?php foreach ($allLinks as $link): ?>
                <a href="<?php echo htmlspecialchars($link['download_url']); ?>" class="dl-link" target="_blank">
                    <div class="info">
                        <span class="badge"><?php echo htmlspecialchars($link['quality']); ?></span>
                        <?php if ($link['season'] && $link['episode']): ?>
                            <span class="ep">S<?php echo str_pad($link['season'],2,'0',STR_PAD_LEFT); ?>E<?php echo str_pad($link['episode'],2,'0',STR_PAD_LEFT); ?></span>
                        <?php endif; ?>
                        <span><?php echo htmlspecialchars($link['file_name'] ?: 'Download'); ?></span>
                    </div>
                    <span class="icon-dl"><i class="fa-solid fa-download"></i></span>
                </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <p style="color:#666; margin-top: 10px;">No download links have been added for this title yet.<br>Check back later!</p>
        <?php endif; ?>

        <a href="javascript:history.back()" class="back-btn"><i class="fa-solid fa-arrow-left"></i> Go Back</a>
    </div>
</body>
</html>
