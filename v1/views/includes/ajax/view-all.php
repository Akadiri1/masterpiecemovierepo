<?php
include APP_PATH . '/views/includes/header.php';

// ==========================================
// 1. HANDLE PARAMETERS & LOGIC
// ==========================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$searchQuery = $_GET['search'] ?? null;
$genreId = $_GET['genre_id'] ?? null;
$type = $_GET['type'] ?? 'movie';
$filterMode = ""; 
$isKidsMode = isset($_SESSION['is_kids_mode']) && $_SESSION['is_kids_mode'] === true;

$results = [];
$totalPages = 1;

// --- LOGIC CONTROLLER ---
$blockedSearchTerms = ['porn', 'xxx', 'sex', 'nude', 'nudity', 'erotic', 'pornography', 'adult', 'hardcore', 'xvideos', 'xhamster', 'doggy', 'masturbation', 'nsfw', 'onlyfans'];

if ($searchQuery) {
   // GLOBAL NSFW FILTER: Block the search entirely if it contains blocked words
   $qCheck = strtolower(trim($searchQuery));
   foreach ($blockedSearchTerms as $b) {
      if (strpos($qCheck, $b) !== false) {
         $filterMode = "Search blocked for explicit content";
         $data = ['results' => [], 'total_pages' => 0, 'total_results' => 0];
         goto SKIP_SEARCH;
      }
   }

    $filterMode = "Search: " . htmlspecialchars($searchQuery);
    $data = fetchTmdbApi("search/multi", ['query' => $searchQuery, 'page' => $page, 'include_adult' => 'false']);
    
   // Manual Filter for Kids Mode
   if ($isKidsMode && !empty($data['results'])) {
        $filteredResults = [];
        foreach ($data['results'] as $item) {
            if (isset($item['adult']) && $item['adult'] === true) continue;
         if (isset($item['genre_ids']) && is_array($item['genre_ids'])) {
            // Block mature genres: Horror(27), Thriller(53), Crime(80), War(10768)
            if (in_array(27, $item['genre_ids']) || in_array(80, $item['genre_ids']) || in_array(53, $item['genre_ids']) || in_array(10768, $item['genre_ids'])) continue;
         }
            $filteredResults[] = $item;
        }
        $data['results'] = $filteredResults;
    }
   SKIP_SEARCH:;
} else {
    // Browsing Logic
    if ($genreId) {
        $endpoint = 'discover/movie';
        $filterMode = "Genre Results";
        $params = [
            'with_genres' => $genreId,
            'page' => $page,
            'sort_by' => 'popularity.desc',
            'include_adult' => false
        ];
    }
    else {
        switch($type) {
            case 'upcoming': $endpoint = 'movie/upcoming'; $filterMode = "Upcoming"; $params = ['region' => 'US', 'page' => $page]; break;
            case 'toppicks': $endpoint = 'movie/top_rated'; $filterMode = "Top Picks"; $params = ['page' => $page]; break;
            case 'tv': $endpoint = 'tv/popular'; $filterMode = "TV Shows"; $params = ['page' => $page]; break;
            case 'fresh': $endpoint = 'trending/movie/week'; $filterMode = "Trending Now"; $params = ['page' => $page]; break;
            case 'discover': 
                $endpoint = 'discover/movie'; 
                $filterMode = "Explore"; 
                if(!empty($_GET['with_genres'])) {
                    $filterMode = "Genre Results";
                    // TMDB TV-exclusive genres (including 10759 used for Manga/Action&Adventure)
                    $tvGenres = ['10759', '10762', '10763', '10764', '10765', '10766', '10767', '10768'];
                    if (in_array($_GET['with_genres'], $tvGenres)) {
                        $endpoint = 'discover/tv';
                    }
                }
                if(!empty($_GET['with_origin_country'])) $filterMode = "International Content";
                $params = $_GET; 
                unset($params['type']); 
                $params['page'] = $page; 
                break;
            default: $endpoint = 'movie/popular'; $filterMode = "Popular Movies"; $params = ['page' => $page]; break;
        }
    }

   if ($isKidsMode) {
        $filterMode .= " (Kids)";
      $endpoint = ($type === 'tv') ? 'discover/tv' : 'discover/movie';
      $cert = ($type === 'tv') ? 'TV-14' : 'PG-13';
      $params = [
         'certification_country' => 'US',
         'certification.lte' => $cert,
         'sort_by' => 'popularity.desc',
         'without_genres' => '27,53,80,10768', // Block Horror, Thriller, Crime, War
         'page' => $page
      ];
        if ($type == 'upcoming') $params['primary_release_date.gte'] = date('Y-m-d');
    }
    
    if (!isset($data)) $data = fetchTmdbApi($endpoint, $params);
}

if ($data && !empty($data['results'])) {
    $results = $data['results'];
    $totalPages = min($data['total_pages'], 500); 
}
?>

<!-- ==========================================
     CUSTOM CSS FOR MIDNIGHT VELVET THEME
     ========================================== -->
<style>
    :root {
        --bg-deep: var(--bs-body-bg, #0b0c15);
        --bg-card: var(--card-bg, #151621);
        --accent: var(--bs-primary, #00e0ff);
        --text-main: #ffffff;
        --text-sub: #a0a0a0;
    }

    /* 1. COMPACT HEADER (Fixed "Unwanted Building") */
    .page-header-banner {
        position: relative;
        /* Drastically reduced top padding to remove empty space */
        padding: 50px 0 15px; 
        background: transparent; /* Remove gradient block */
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    
    .filter-title {
        font-weight: 800;
        letter-spacing: -0.5px;
        font-size: 2rem;
        text-shadow: 0 0 20px rgba(0, 224, 255, 0.15);
    }

    /* 2. Grid System */
    .movie-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
        margin-top: 20px;
    }
    @media(min-width: 576px) { .movie-grid { grid-template-columns: repeat(3, 1fr); } }
    @media(min-width: 992px) { .movie-grid { grid-template-columns: repeat(4, 1fr); } }
    @media(min-width: 1400px) { .movie-grid { grid-template-columns: repeat(5, 1fr); gap: 20px; } }
    @media(min-width: 1920px) { .movie-grid { grid-template-columns: repeat(6, 1fr); gap: 24px; } }
    @media(min-width: 2560px) { .movie-grid { grid-template-columns: repeat(7, 1fr); gap: 28px; } }

    /* 3. Modern Movie Card */
    .vod-card {
        background-color: transparent;
        border-radius: 12px;
        overflow: hidden;
        transition: transform 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        position: relative;
        height: 100%;
        border: 1px solid rgba(255,255,255,0.02);
    }

    .vod-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.4);
        border-color: rgba(255,255,255,0.08);
        z-index: 2;
    }

    /* Poster Area */
    .poster-box {
        position: relative;
        padding-top: 150%; /* 2:3 Aspect Ratio */
        background: #1a1a1a;
        overflow: hidden;
    }

    .poster-box img {
        position: absolute;
        top: 0; left: 0;
        width: 100%; height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease, filter 0.3s ease;
    }

    .vod-card:hover .poster-box img {
        transform: scale(1.08);
        filter: brightness(0.4); 
    }

    /* Badges */
    .card-badge {
        position: absolute;
        top: 8px; right: 8px;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(8px);
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--accent);
        z-index: 5;
        border: 1px solid rgba(255,255,255,0.1);
    }

    /* Hover Actions (Fixed Icons) */
    .card-actions-overlay {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        gap: 12px;
        opacity: 0;
        transition: opacity 0.2s ease;
    }

    .vod-card:hover .card-actions-overlay {
        opacity: 1;
    }

    .action-btn {
        width: 48px; height: 48px;
        border-radius: 50%;
        display: flex;
        align-items: center; justify-content: center;
        background: rgba(255,255,255,0.1);
        backdrop-filter: blur(10px);
        color: #fff;
        font-size: 1.1rem;
        transition: all 0.2s;
        text-decoration: none;
        border: 1px solid rgba(255,255,255,0.2);
    }
    
    .action-btn:hover { transform: scale(1.1); }
    
    .action-btn.play { 
        background: var(--accent); 
        color: #000; 
        border-color: var(--accent);
        box-shadow: 0 0 20px rgba(0, 224, 255, 0.4);
    }
    
    .action-btn.add:hover { background: #fff; color: #000; }

    /* Text Content */
    .card-info {
        padding: 12px 5px 5px;
    }

    .card-title {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-main);
        margin-bottom: 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: block;
        text-decoration: none;
    }
    
    .vod-card:hover .card-title { color: var(--accent); }

    .card-meta {
        font-size: 0.8rem;
        color: var(--text-sub);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .hd-tag {
        border: 1px solid var(--text-sub);
        padding: 1px 4px;
        border-radius: 3px;
        font-size: 0.6rem;
        font-weight: 700;
        letter-spacing: 0.5px;
    }

    /* 4. Pagination
       One compact row at every screen size: previous, the first page, the
       pages either side of the current one, the last page, next. The old
       large round buttons wrapped onto a second line on phones. */
    .zen-pager { display: flex; flex-direction: column; align-items: center; gap: 10px; margin: 32px 0 8px; }
    .zen-pager-list { display: flex; flex-wrap: nowrap; align-items: center; justify-content: center; gap: 6px; margin: 0; padding: 0; list-style: none; }
    .zen-pager-link {
        display: inline-flex; align-items: center; justify-content: center; gap: 6px;
        min-width: 40px; height: 40px; min-height: 0; padding: 0 10px;
        border-radius: 12px; border: 1px solid rgba(255,255,255,0.08);
        background: rgba(255,255,255,0.04); color: #d6d6de;
        font-size: 0.9rem; font-weight: 600; font-variant-numeric: tabular-nums;
        text-decoration: none; transition: background 0.2s, border-color 0.2s, color 0.2s;
    }
    .zen-pager-link:hover { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.18); color: #fff; }
    .zen-pager-link.is-current { background: var(--accent); border-color: var(--accent); color: #fff; box-shadow: 0 8px 20px -10px var(--accent); pointer-events: none; }
    .zen-pager-link.is-disabled { opacity: 0.35; pointer-events: none; }
    .zen-pager-arrow { padding: 0 14px; }
    .zen-pager-arrow i { font-size: 1rem; }
    .zen-pager-gap { min-width: 18px; text-align: center; color: #6b6b76; font-weight: 700; user-select: none; }
    .zen-pager-status { color: #8a8a96; font-size: 0.8rem; }
    .zen-pager-status strong { color: #fff; font-weight: 600; }

    @media (max-width: 575.98px) {
        .zen-pager { margin-top: 24px; }
        .zen-pager-list { gap: 4px; }
        .zen-pager-link { min-width: 34px; height: 36px; padding: 0 6px; border-radius: 10px; font-size: 0.85rem; }
        .zen-pager-arrow { padding: 0 9px; }
        .zen-pager-arrow span { display: none; }
        .zen-pager-gap { min-width: 12px; }
    }

    .kids-indicator {
        background: linear-gradient(45deg, #00d2ff, #3a7bd5);
        color: white;
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: bold;
    }
</style>

<!-- ==========================================
     HTML DISPLAY
     ========================================== -->

<!-- Header -->
<div class="page-header-banner">
   <div class="container-fluid">
      <div class="d-flex justify-content-between align-items-end">
         <div>
            <h2 class="filter-title text-white mb-0"><?php echo $filterMode; ?></h2>
         </div>
         
         <div class="d-flex align-items-center gap-3">
             <?php if (!empty($is_kids_mode) || (!empty($_SESSION['is_kids_mode']) && $_SESSION['is_kids_mode'])): ?>
                <div class="kids-indicator d-flex align-items-center gap-2">
                    <i class="fa-solid fa-child"></i>
                    <span>Kids Mode</span>
                </div>
             <?php endif; ?>
             
             <div class="d-none d-md-block text-muted small">
                Page <span class="text-white fw-bold"><?php echo $page; ?></span> of <?php echo $totalPages; ?>
             </div>
         </div>
      </div>
   </div>
</div>

<main id="main" class="site-main section-padding pt-2">
   <div class="container-fluid">
      <div class="row">
         <div class="col-sm-12">
            
            <!-- RESULT GRID -->
            <?php if (!empty($results)): ?>
            <div class="movie-grid">
               
               <?php foreach ($results as $item): 
                   // Data Prep
                   if (isset($item['media_type']) && $item['media_type'] === 'person') continue;
                   $itemId = $item['id'];
                   $itemTitle = $item['title'] ?? $item['name'] ?? 'Unknown';
                   $itemPoster = !empty($item['poster_path']) ? 'https://image.tmdb.org/t/p/w500'.$item['poster_path'] : '/assets/images/media/placeholder-portrait.svg';
                   $itemType = $item['media_type'] ?? ((isset($item['title']) ? 'movie' : 'tv'));
                   $date = $item['release_date'] ?? $item['first_air_date'] ?? '';
                   $year = $date ? date('Y', strtotime($date)) : 'N/A';
                   $vote = $item['vote_average'] ?? 0;
                   $link = "/{$itemType}/{$itemId}";
               ?>
               
               <!-- Single Card -->
               <div class="vod-card">
                  <!-- Poster & Hover Actions -->
                  <div class="poster-box">
                      <img src="<?php echo $itemPoster; ?>" alt="<?php echo htmlspecialchars($itemTitle); ?>" loading="lazy">
                      
                      <!-- Rating Badge -->
                      <?php if($vote > 0): ?>
                         <div class="card-badge">
                            <i class="fa-solid fa-star"></i> <?php echo number_format($vote, 1); ?>
                         </div>
                      <?php endif; ?>

                      <!-- Hover Actions Overlay -->
                      <div class="card-actions-overlay">
                         <a href="<?php echo $link; ?>" class="action-btn play" title="Play Now">
                            <i class="fa-solid fa-play"></i>
                         </a>
                         <a href="/add-watchlist?id=<?php echo $itemId; ?>" class="action-btn add" title="Add to Watchlist">
                            <i class="fa-solid fa-plus"></i>
                         </a>
                      </div>
                      
                      <!-- Clickable Link covering image -->
                      <a href="<?php echo $link; ?>" class="position-absolute top-0 start-0 w-100 h-100 z-1"></a>
                  </div>

                  <!-- Text Details -->
                  <div class="card-info">
                      <a href="<?php echo $link; ?>" class="card-title" title="<?php echo htmlspecialchars($itemTitle); ?>">
                         <?php echo htmlspecialchars($itemTitle); ?>
                      </a>
                      <div class="card-meta">
                         <span><?php echo $year; ?></span>
                         <div class="d-flex align-items-center gap-2">
                             <span class="hd-tag text-uppercase">HD</span>
                             <span class="text-capitalize text-muted small"><?php echo $itemType; ?></span>
                         </div>
                      </div>
                  </div>
               </div>
               
               <?php endforeach; ?>
            </div>
            
            <?php else: ?>
               <!-- Empty State -->
               <div class="col-12 text-center py-5">
                  <div class="p-5" style="border: 2px dashed rgba(255,255,255,0.1); border-radius: 12px; background: rgba(255,255,255,0.02);">
                     <i class="fa-solid fa-film fs-1 text-muted mb-3" style="font-size: 4rem;"></i>
                     <?php if (strpos(strtolower($filterMode), 'blocked') !== false): ?>
                        <h3 class="text-white mt-3">Search Restricted</h3>
                        <p class="text-muted">This term is not allowed in Kids Mode.</p>
                     <?php else: ?>
                        <h3 class="text-white mt-3">No results found</h3>
                        <p class="text-muted">We couldn't find anything matching your criteria.</p>
                     <?php endif; ?>
                     <a href="/" class="btn btn-primary rounded-pill px-4 mt-3">Go Home</a>
                  </div>
               </div>
            <?php endif; ?>

            <!-- ==========================================
                 PAGINATION
                 ========================================== -->
            <?php if ($totalPages > 1):
                // Which page numbers to show: the first, the last, and one either
                // side of the current page. A gap of several pages becomes "…";
                // a gap of exactly one page shows that page instead.
                $pagerUrl = function ($p) { return '?' . http_build_query(array_merge($_GET, ['page' => $p])); };
                $pagerPages = array_unique(array_filter(
                    [1, $page - 1, $page, $page + 1, $totalPages],
                    function ($p) use ($totalPages) { return $p >= 1 && $p <= $totalPages; }
                ));
                sort($pagerPages);
                $pagerItems = [];
                $pagerLast = 0;
                foreach ($pagerPages as $p) {
                    if ($p - $pagerLast === 2) {
                        $pagerItems[] = $p - 1;
                    } elseif ($p - $pagerLast > 2) {
                        $pagerItems[] = null;
                    }
                    $pagerItems[] = $p;
                    $pagerLast = $p;
                }
                $onFirstPage = $page <= 1;
                $onLastPage = $page >= $totalPages;
            ?>
            <nav class="zen-pager" aria-label="Pages">
               <ul class="zen-pager-list">
                  <li>
                     <a class="zen-pager-link zen-pager-arrow<?php echo $onFirstPage ? ' is-disabled' : ''; ?>" href="<?php echo htmlspecialchars($pagerUrl(max(1, $page - 1))); ?>" aria-label="Previous page"<?php echo $onFirstPage ? ' aria-disabled="true" tabindex="-1"' : ''; ?>>
                        <i class="ph ph-caret-left"></i><span>Prev</span>
                     </a>
                  </li>
                  <?php foreach ($pagerItems as $p): ?>
                     <?php if ($p === null): ?>
                  <li class="zen-pager-gap" aria-hidden="true">…</li>
                     <?php else: ?>
                  <li>
                     <a class="zen-pager-link<?php echo $p == $page ? ' is-current' : ''; ?>" href="<?php echo htmlspecialchars($pagerUrl($p)); ?>"<?php echo $p == $page ? ' aria-current="page"' : ''; ?>><?php echo $p; ?></a>
                  </li>
                     <?php endif; ?>
                  <?php endforeach; ?>
                  <li>
                     <a class="zen-pager-link zen-pager-arrow<?php echo $onLastPage ? ' is-disabled' : ''; ?>" href="<?php echo htmlspecialchars($pagerUrl(min($totalPages, $page + 1))); ?>" aria-label="Next page"<?php echo $onLastPage ? ' aria-disabled="true" tabindex="-1"' : ''; ?>>
                        <span>Next</span><i class="ph ph-caret-right"></i>
                     </a>
                  </li>
               </ul>
               <p class="zen-pager-status m-0">Page <strong><?php echo number_format($page); ?></strong> of <?php echo number_format($totalPages); ?></p>
            </nav>
            <?php endif; ?>

         </div>
      </div>
   </div>
</main>

<?php include APP_PATH . '/views/includes/footer.php'; ?>