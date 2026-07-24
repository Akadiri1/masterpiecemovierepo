import sys

path = r'c:\wamp64\www\masterpiecemovie\v1\views\movie-detail.php'

with open(path, 'r', encoding='utf-8') as f:
    lines = f.readlines()

# The first 320 lines contain the PHP fetching logic, down to the closing } of the site loop
kept_lines = lines[:320]

new_html = """

<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no, viewport-fit=cover">
  <title><?php echo htmlspecialchars($title); ?> - Details</title>
  <link rel="manifest" href="/manifest.json">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://unpkg.com/@phosphor-icons/web"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  
  <!-- Add core styles for modal/badges -->
  <link rel="stylesheet" href="/assets/css/core/libs.min.css" />
  <link rel="stylesheet" href="/assets/css/core/custom.min.css?v=5.4.0.css" />
  <link rel="stylesheet" href="/assets/css/core/watch-theme.css">

  <style>
      body, html { background-color: #0a0a0f !important; color: #b0b0b8 !important; overflow: hidden; margin:0; padding:0; height: 100vh;}
      .watch-app { display: flex; height: 100vh; overflow: hidden; width: 100%; }
      .detail-stage {
          position: relative; width: 100%; border-radius: 12px; overflow: hidden; background: #000;
          display: flex; flex-direction: column; aspect-ratio: 16/9; min-height: 400px;
          margin-bottom: 25px; border: 1px solid rgba(255,255,255,0.05);
      }
      .detail-stage-bg { position: absolute; inset: 0; background-size: cover; background-position: center; opacity: 0.3; filter: blur(10px); z-index: 1; }
      .detail-stage-content { position: relative; z-index: 10; display: flex; flex-direction: column; height: 100%; }
      .detail-stage-trailer { flex: 1; width: 100%; position: relative; }
      .detail-stage-trailer iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: none; }
      .detail-stage-meta { padding: 25px; background: linear-gradient(to top, rgba(0,0,0,0.95) 0%, rgba(0,0,0,0.4) 100%); }
      
      .meta-title { font-size: 2.2rem; font-weight: 800; color: #fff; line-height: 1.1; margin-bottom: 10px; letter-spacing: -0.5px; }
      .meta-tags { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 15px; }
      .meta-tag { background: rgba(255,255,255,0.1); backdrop-filter: blur(4px); padding: 5px 12px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; color: #eee; text-transform: uppercase; border: 1px solid rgba(255,255,255,0.05); }
      .meta-desc { font-size: 0.95rem; color: #ccc; max-width: 800px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; margin-bottom: 20px; line-height: 1.6; }
      .meta-actions { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
      
      .btn-play-now { background: #e50914; color: #fff; border: none; padding: 12px 28px; border-radius: 8px; font-weight: 700; font-size: 1.1rem; display: flex; align-items: center; gap: 8px; text-decoration: none; transition: 0.2s; box-shadow: 0 4px 15px rgba(229, 9, 20, 0.3); }
      .btn-play-now:hover { background: #ff2a35; color: #fff; transform: translateY(-2px); }
      .btn-circle-action { width: 45px; height: 45px; border-radius: 50%; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; transition: 0.2s; cursor: pointer; text-decoration: none; }
      .btn-circle-action:hover { background: #fff; color: #000; transform: translateY(-2px); }

      /* Modals inside watch-app fix */
      .dl-modal-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.8); backdrop-filter: blur(8px); display: none; z-index: 10000; align-items: center; justify-content: center; opacity: 0; transition: 0.3s; }
      .dl-modal-overlay.active { display: flex; opacity: 1; }
      .dl-modal { background: #111; border: 1px solid #333; border-radius: 16px; width: 90%; max-width: 500px; padding: 25px; transform: translateY(20px); transition: 0.3s; }
      .dl-modal-overlay.active .dl-modal { transform: translateY(0); }
      .dl-modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #222; padding-bottom: 15px; margin-bottom: 20px; }
      .dl-modal-header h3 { margin: 0; font-size: 1.5rem; color: #fff; font-weight: 700; }
      .dl-modal-close { background: none; border: none; color: #888; font-size: 1.5rem; cursor: pointer; }
      .dl-modal-close:hover { color: #fff; }
      .dl-quality-item { display: flex; justify-content: space-between; align-items: center; background: #1a1a1a; padding: 15px; border-radius: 12px; text-decoration: none; border: 1px solid #222; margin-bottom: 10px; transition: 0.2s; }
      .dl-quality-item:hover { background: #222; border-color: #4cd137; }
      .dl-quality-badge { margin-bottom: 5px; }
      .badge-res { background: #e50914; color: #fff; padding: 2px 6px; border-radius: 4px; font-weight: 800; font-size: 0.75rem; }
      .badge-format { background: rgba(255,255,255,0.1); color: #fff; padding: 2px 6px; border-radius: 4px; font-weight: 600; font-size: 0.75rem; margin-left: 5px; }
      .dl-quality-label { display: block; color: #fff; font-size: 1rem; font-weight: 600; }
      .dl-quality-meta { font-size: 0.8rem; color: #888; }
      .dl-quality-icon { font-size: 1.2rem; color: #4cd137; }
  </style>
</head>
<body>

<div class="watch-app">
    <!-- 1. Left Sidebar -->
    <aside class="watch-sidebar">
        <div class="sidebar-brand">
            <a href="/" class="logo-text">Streame<span style="font-weight: 800;">X</span></a>
        </div>
        <nav class="sidebar-nav">
            <div class="sidebar-main-actions">
                <a href="/" class="sidebar-link"><i class="ph ph-house"></i><span>Home</span></a>
                <a href="javascript:void(0)" class="sidebar-link"><i class="ph ph-magnifying-glass"></i><span>Search</span></a>
            </div>
            <div class="sidebar-section-label">Media</div>
            <a href="/view-all?type=movie" class="sidebar-link"><i class="ph ph-film-strip"></i><span>Movies</span></a>
            <a href="/view-all?type=tv" class="sidebar-link"><i class="ph ph-monitor-play"></i><span>TV Shows</span></a>
            <a href="/view-all?type=discover&with_genres=16" class="sidebar-link"><i class="ph ph-sparkle"></i><span>Anime</span></a>
            <a href="/view-all?type=discover&with_genres=10759" class="sidebar-link"><i class="ph ph-book-open"></i><span>Manga</span></a>
            <a href="/view-all?type=discover&with_genres=10402" class="sidebar-link"><i class="ph ph-music-note"></i><span>Music</span></a>
            <a href="/view-all?type=discover&with_genres=99" class="sidebar-link"><i class="ph ph-broadcast"></i><span>Live Sports</span></a>
            <div style="height: 12px;"></div>
            <a href="/profile" class="sidebar-link"><i class="ph ph-heart"></i><span>Watchlist</span></a>
        </nav>
    </aside>

    <!-- 2. Center Content -->
    <main class="watch-center custom-scrollbar" style="padding-top: 20px;">
        <div class="center-top-bar" style="position: relative; background: transparent; padding: 0 0 15px 0;">
            <a href="javascript:history.back()" class="back-btn" style="background: rgba(255,255,255,0.05); border-radius: 8px;"><i class="ph-bold ph-arrow-left"></i></a>
        </div>

        <div class="detail-stage">
            <div class="detail-stage-bg" style="background-image: url('<?php echo htmlspecialchars($backdrop); ?>');"></div>
            
            <div class="detail-stage-content">
                <?php if ($trailerEmbed): ?>
                <div class="detail-stage-trailer">
                    <iframe src="<?php echo htmlspecialchars($trailerEmbed); ?>" allowfullscreen></iframe>
                </div>
                <?php else: ?>
                <div class="detail-stage-trailer" style="background-image: url('<?php echo htmlspecialchars($backdrop); ?>'); background-size: cover; background-position: center;"></div>
                <?php endif; ?>

                <div class="detail-stage-meta">
                    <?php if (!empty($details['genres'])): ?>
                    <div class="meta-tags">
                        <?php foreach (array_slice($details['genres'], 0, 3) as $genre): ?>
                        <span class="meta-tag"><?php echo htmlspecialchars($genre['name']); ?></span>
                        <?php endforeach; ?>
                        <span class="meta-tag text-warning" style="background: transparent; border-color: rgba(255,193,7,0.3);"><i class="fa-solid fa-star"></i> <?php echo $rating; ?></span>
                        <span class="meta-tag" style="background: transparent;"><?php echo $year; ?></span>
                        <span class="meta-tag" style="background: transparent;"><?php echo $ageRating; ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <h1 class="meta-title"><?php echo htmlspecialchars($title); ?></h1>
                    <p class="meta-desc"><?php echo htmlspecialchars($overview); ?></p>
                    
                    <div class="meta-actions">
                        <a href="/watch?id=<?php echo $mediaId; ?>&type=<?php echo $mediaType; ?>" class="btn-play-now">
                            <i class="ph-fill ph-play-circle" style="font-size: 1.5rem;"></i> Play Now
                        </a>
                        
                        <a href="#" class="btn-circle-action watchlist-btn" data-id="<?php echo $mediaId; ?>" data-type="<?php echo $mediaType; ?>" title="Add to Watchlist">
                            <i class="<?php echo $isInWatchlist ? 'ph-bold ph-check text-success' : 'ph-bold ph-plus'; ?>"></i>
                        </a>
                        
                        <?php if (!$isUpcoming): ?>
                        <button class="btn-circle-action" data-bs-target="#downloadModal" title="Download">
                            <i class="fa-solid fa-download"></i>
                        </button>
                        <?php endif; ?>

                        <button class="btn-circle-action" onclick="navigator.share({title: document.title, url: window.location.href})" title="Share">
                            <i class="ph-bold ph-share-network"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recommended Section -->
        <?php if (!empty($relatedList)): ?>
        <div class="recommended-section mx-0 px-0 mt-4 mb-5">
            <h4 class="mb-3" style="font-weight: 700; font-size: 1.4rem;">Related Content</h4>
            <div class="rec-cards">
                <?php foreach ($relatedList as $rec): ?>
                <a href="/movie/<?php echo $rec['id']; ?>" class="rec-card text-decoration-none">
                    <img src="<?php echo !empty($rec['poster_path']) ? 'https://image.tmdb.org/t/p/w300'.$rec['poster_path'] : '/assets/images/user/default.png'; ?>" loading="lazy" alt="Poster">
                    <div class="rec-rating"><i class="ph-fill ph-star text-warning"></i> <?php echo round($rec['vote_average'], 1); ?></div>
                    <p class="rec-card-title text-truncate text-white"><?php echo htmlspecialchars($rec['title'] ?? $rec['name'] ?? 'Untitled'); ?></p>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- 3. Right Panel -->
    <aside class="watch-right custom-scrollbar" id="sidebar">
       <!-- Info Card -->
       <div class="info-card">
           <img src="<?php echo !empty($details['poster_path']) ? 'https://image.tmdb.org/t/p/w300'.$details['poster_path'] : $posterPlaceholder; ?>" class="info-poster" alt="Poster" loading="lazy">
           <div class="info-details">
               <div><span class="label">Status</span><span class="val fw-bold text-white"><?php echo htmlspecialchars($details['status'] ?? 'Released'); ?></span></div>
               <div><span class="label">Aired</span><span class="val fw-bold text-white"><?php echo htmlspecialchars($year); ?></span></div>
               <div><span class="label">Duration</span><span class="val fw-bold text-white"><?php echo htmlspecialchars($duration); ?></span></div>
               <div><span class="label">Language</span><span class="val fw-bold text-white"><?php echo htmlspecialchars($originalLang); ?></span></div>
               <div><span class="label">Views</span><span class="val fw-bold text-white"><?php echo number_format($viewCount); ?></span></div>
           </div>
       </div>

       <!-- Cast -->
       <?php if (!empty($castList)): ?>
       <div class="section-head mt-4">
           <h5>Characters</h5>
           <span style="font-size: 0.8rem; color: #888; cursor: pointer;">view all</span>
       </div>
       <div class="cast-list">
           <?php foreach (array_slice($castList, 0, 6) as $actor): ?>
           <div class="cast-row">
               <img src="<?php echo !empty($actor['profile_path']) ? 'https://image.tmdb.org/t/p/w185'.$actor['profile_path'] : '/assets/images/user/default.png'; ?>" loading="lazy" alt="Actor">
               <div>
                   <h6><?php echo htmlspecialchars($actor['name']); ?></h6>
                   <span><?php echo htmlspecialchars($actor['character'] ?? ''); ?></span>
               </div>
           </div>
           <?php endforeach; ?>
       </div>
       <?php endif; ?>

       <!-- Crew -->
       <?php if (!empty($crewList)): ?>
       <div class="section-head mt-4">
           <h5>Crew</h5>
       </div>
       <div class="cast-list">
           <?php foreach ($crewList as $crew): ?>
           <div class="cast-row">
               <img src="<?php echo !empty($crew['profile_path']) ? 'https://image.tmdb.org/t/p/w185'.$crew['profile_path'] : '/assets/images/user/default.png'; ?>" loading="lazy" alt="Crew">
               <div>
                   <h6><?php echo htmlspecialchars($crew['name']); ?></h6>
                   <span><?php echo htmlspecialchars($crew['job'] ?? 'Crew'); ?></span>
               </div>
           </div>
           <?php endforeach; ?>
       </div>
       <?php endif; ?>

    </aside>
</div>

<!-- DOWNLOAD MODAL -->
<?php if (!$isUpcoming): ?>
<div class="dl-modal-overlay" id="downloadModal">
    <div class="dl-modal">
        <div class="dl-modal-header">
            <h3><i class="fa-solid fa-download" style="color:#4cd137;margin-right:8px;"></i> Sources</h3>
            <button class="dl-modal-close" data-bs-dismiss="modal" id="dlModalClose"><i class="fa-solid fa-times"></i></button>
        </div>
        <div class="dl-modal-body">
            <p>External sources for <strong style="color:#fff;"><?php echo htmlspecialchars($title); ?></strong></p>

            <?php if(!empty($downloadLinks)): ?>
                <div class="d-flex flex-column gap-2" style="max-height: 400px; overflow-y:auto; overflow-x:hidden;">
                    <?php foreach ($downloadLinks as $link): 
                        $isMagnet = (strpos($link['download_url'], 'magnet:') !== false);
                        $icon = $isMagnet ? 'fa-magnet' : 'fa-external-link-alt';
                    ?>
                        <a href="<?php echo htmlspecialchars($link['download_url']); ?>" class="dl-quality-item" target="_blank" rel="noopener">
                            <div class="dl-quality-info">
                                <div class="dl-quality-badge">
                                    <span class="badge-res"><?php echo htmlspecialchars($link['quality']); ?></span>
                                    <span class="badge-format"><?php echo htmlspecialchars($link['language']); ?></span>
                                </div>
                                <span class="dl-quality-label"><?php echo htmlspecialchars($link['file_size']); ?></span>
                                <span class="dl-quality-meta">Extrnal Link</span>
                            </div>
                            <div class="dl-quality-icon">
                                <i class="fa-solid <?php echo $icon; ?>"></i>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center p-4">
                    <i class="fa-solid fa-exclamation-triangle fa-2x mb-3 text-warning"></i>
                    <p style="color:#888;">No external download links are available right now. Please check back later.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    // --- DOWNLOAD MODAL LOGIC ---
    (function() {
        const dlBtnColl = document.querySelectorAll('[data-bs-target="#downloadModal"]');
        const dlModal = document.getElementById('downloadModal');
        const dlClose = document.getElementById('dlModalClose');

        if (dlModal) {
            dlBtnColl.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    dlModal.classList.add('active');
                });
            });

            if (dlClose) {
                dlClose.addEventListener('click', function() {
                    dlModal.classList.remove('active');
                });
            }

            dlModal.addEventListener('click', function(e) {
                if (e.target === dlModal) {
                    dlModal.classList.remove('active');
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && dlModal.classList.contains('active')) {
                    dlModal.classList.remove('active');
                }
            });
        }
    })();
</script>

</body>
</html>
"""

with open(path, 'w', encoding='utf-8') as f:
    f.writelines(kept_lines)
    f.write(new_html)

