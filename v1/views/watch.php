<?php
// ==========================================
// 1. GET PARAMETERS
// ==========================================
$mediaId = $_GET['id'] ?? null;
$mediaType = $_GET['type'] ?? 'movie';
$seasonNum = $_GET['season'] ?? 0; // 0 for movies
$episodeNum = $_GET['episode'] ?? 0; // 0 for movies

if (!$mediaId) {
    header("Location: /");
    exit;
}

// ==========================================
// 2. CHECK FOR REAL VIDEO SOURCE (YOUR DB)
// ==========================================
$videoSrc = "";
$playerType = "youtube"; // Default fallback
$isEmbed = false;

if (isset($conn)) {
    // Check if we have a hosted file for this specific ID/Season/Episode
    $sql = "SELECT video_url, is_embed FROM media_sources 
            WHERE tmdb_id = ? AND media_type = ? 
            AND season = ? AND episode = ? LIMIT 1";
    
    // Adjust query for movies (where season/episode might be stored as 0 or NULL)
    $stmt = $conn->prepare($sql);
    $stmt->execute([$mediaId, $mediaType, $seasonNum, $episodeNum]);
    $source = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($source && !empty($source['video_url'])) {
        $videoSrc = $source['video_url'];
        $isEmbed = $source['is_embed'];
        $playerType = $isEmbed ? "embed" : "file"; // Switch to File Player
    }
}

// ==========================================
// 3. FETCH METADATA (Titles, Images) FROM API
// ==========================================
$videoTitle = "";
$videoSubTitle = "";
$posterImage = "";

if ($mediaType === 'movie') {
    $data = fetchTmdbApi("movie/{$mediaId}", ['append_to_response' => 'videos']);
    $videoTitle = $data['title'] ?? 'Unknown Movie';
    $videoSubTitle = date('Y', strtotime($data['release_date'] ?? 'now'));
    $posterImage = !empty($data['backdrop_path']) ? 'https://image.tmdb.org/t/p/original'.$data['backdrop_path'] : 'assets/images/media/placeholder.webp';
    
    // Only fetch trailer if we DON'T have a real source
    if ($playerType === 'youtube' && !empty($data['videos']['results'])) {
        foreach ($data['videos']['results'] as $vid) {
            if ($vid['site'] === 'YouTube') {
                $videoSrc = "https://www.youtube.com/watch?v={$vid['key']}";
                break;
            }
        }
    }
} else {
    $epData = fetchTmdbApi("tv/{$mediaId}/season/{$seasonNum}/episode/{$episodeNum}", ['append_to_response' => 'videos']);
    $showData = fetchTmdbApi("tv/{$mediaId}");
    
    $videoTitle = $epData['name'] ?? "Episode {$episodeNum}";
    $videoSubTitle = ($showData['name'] ?? 'TV Show') . " - S{$seasonNum}:E{$episodeNum}";
    $posterImage = !empty($epData['still_path']) ? 'https://image.tmdb.org/t/p/original'.$epData['still_path'] : 'assets/images/media/placeholder.webp';

    if ($playerType === 'youtube' && !empty($epData['videos']['results'])) {
        foreach ($epData['videos']['results'] as $vid) {
            if ($vid['site'] === 'YouTube') {
                $videoSrc = "https://www.youtube.com/watch?v={$vid['key']}";
                break;
            }
        }
    }
}

// ==========================================
// 4. HISTORY TRACKING
// ==========================================
if (isset($_SESSION['user_id']) && isset($conn)) {
    $userId = $_SESSION['user_id'];
    // Insert/Update logic (simplified for brevity)
    $check = $conn->prepare("SELECT id FROM watch_history WHERE user_id = ? AND tmdb_movie_id = ?");
    $check->execute([$userId, $mediaId]);
    if ($check->rowCount() > 0) {
        $conn->prepare("UPDATE watch_history SET last_watched = NOW() WHERE user_id = ? AND tmdb_movie_id = ?")->execute([$userId, $mediaId]);
    } else {
        $conn->prepare("INSERT INTO watch_history (user_id, tmdb_movie_id, current_time, total_duration) VALUES (?, ?, 1, 120)")->execute([$userId, $mediaId]);
    }
}
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Watching: <?php echo htmlspecialchars($videoTitle); ?></title>
  <link rel="stylesheet" href="assets/css/streamit.min.css?v=5.4.0" />
  <link rel="stylesheet" href="assets/css/custom.min.css?v=5.4.0" />
  <link rel="stylesheet" href="assets/vendor/font-awesome/css/all.min.css" />
  <!-- Video.js CSS -->
  <link href="https://vjs.zencdn.net/7.20.3/video-js.css" rel="stylesheet" />
  
  <style>
      body { margin: 0; overflow: hidden; background: #000; }
      .video-container { width: 100vw; height: 100vh; display: flex; justify-content: center; align-items: center; }
      .back-btn { position: absolute; top: 20px; right: 20px; z-index: 1000; background: rgba(0,0,0,0.5); width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%; color: white; text-decoration: none; font-size: 20px; }
      .back-btn:hover { background: #e50914; color: white; }
      .video-overlay { position: absolute; top: 20px; left: 40px; z-index: 1000; pointer-events: none; }
      .video-js .vjs-big-play-button { top: 50%; left: 50%; transform: translate(-50%, -50%); }
  </style>
</head>
<body>

  <a href="/movie-detail?id=<?php echo $mediaId; ?>&type=<?php echo $mediaType; ?>" class="back-btn">
      <i class="fas fa-times"></i>
  </a>

  <div class="video-overlay">
      <h3 class="text-white mb-0"><?php echo htmlspecialchars($videoTitle); ?></h3>
      <p class="text-white-50 mb-0"><?php echo htmlspecialchars($videoSubTitle); ?></p>
  </div>

  <div class="video-container">
      <?php if ($playerType === 'embed'): ?>
          <!-- 1. IFRAME EMBED (For sources like 3rd party servers) -->
          <iframe src="<?php echo $videoSrc; ?>" width="100%" height="100%" frameborder="0" allowfullscreen></iframe>

      <?php elseif ($playerType === 'file'): ?>
          <!-- 2. REAL VIDEO FILE (MP4/MKV) -->
          <video id="my-video" class="video-js vjs-default-skin vjs-big-play-centered" controls preload="auto" width="100%" height="100%" poster="<?php echo $posterImage; ?>">
              <source src="<?php echo $videoSrc; ?>" type="video/mp4" />
              <p class="vjs-no-js">To view this video please enable JavaScript.</p>
          </video>

      <?php else: ?>
          <!-- 3. YOUTUBE FALLBACK (If no file in DB) -->
          <video id="my-video" class="video-js vjs-default-skin vjs-big-play-centered" controls autoplay width="100%" height="100%" poster="<?php echo $posterImage; ?>"
              data-setup='{ "techOrder": ["youtube"], "sources": [{ "type": "video/youtube", "src": "<?php echo $videoSrc; ?>" }] }'>
          </video>
      <?php endif; ?>
  </div>

  <script src="assets/js/core/libs.min.js"></script>
  <!-- Video.js & Youtube Plugin -->
  <script src="https://vjs.zencdn.net/7.20.3/video.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/videojs-youtube/2.6.1/Youtube.min.js"></script>
  <script>
      // Auto-focus video player
      var player = videojs('my-video');
      player.ready(function() {
          this.play();
      });
  </script>
</body>
</html>
```

### How to make it work:
1.  **Go to your Database** (phpMyAdmin).
2.  **Open** the `media_sources` table.
3.  **Insert a row** manually to test:
    * `tmdb_id`: (The ID of the movie you are testing, e.g., `939243` for Sonic 3)
    * `media_type`: `movie`
    * `video_url`: `https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4` (This is a free test video URL. Later, put your actual file path here, like `/uploads/movies/sonic3.mp4`).
4.  **Go to your website** and click "Play" on that movie. It will now play the Big Buck Bunny video instead of the YouTube trailer.