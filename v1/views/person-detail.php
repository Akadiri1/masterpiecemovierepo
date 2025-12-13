<?php
include APP_PATH . '/views/includes/header.php';

// ==========================================
// 1. INITIALIZE & FETCH DATA
// ==========================================
$personId = $_GET['id'] ?? null;

if (!$personId) {
    echo "<script>window.location.href = '/';</script>";
    exit;
}

// Fetch Person Details + Combined Credits
$person = fetchTmdbApi("person/{$personId}", [
    'append_to_response' => 'combined_credits,external_ids'
]);

if (!$person) {
    echo "<div class='container p-5 text-center text-white'><h2>Person not found.</h2></div>";
    include APP_PATH . '/views/includes/footer.php';
    exit;
}

// ==========================================
// 2. PROCESS VARIABLES
// ==========================================
$name = $person['name'];
$bio = !empty($person['biography']) ? $person['biography'] : "No biography information available.";
$birthday = isset($person['birthday']) ? date('F j, Y', strtotime($person['birthday'])) : 'N/A';
$placeOfBirth = $person['place_of_birth'] ?? '';
$department = $person['known_for_department'];

// High Quality Profile Image
$image = !empty($person['profile_path']) 
    ? 'https://image.tmdb.org/t/p/h632' . $person['profile_path'] 
    : 'assets/images/media/cast-placeholder.webp';

// ==========================================
// 3. PROCESS CREDITS (Filmography)
// ==========================================
$allCredits = $person['combined_credits']['cast'] ?? [];

// Sort by Date (Newest First)
usort($allCredits, function($a, $b) {
    $dateA = $a['release_date'] ?? $a['first_air_date'] ?? '0000-00-00';
    $dateB = $b['release_date'] ?? $b['first_air_date'] ?? '0000-00-00';
    return strtotime($dateB) - strtotime($dateA);
});

// Separate into Movies and TV Arrays
$movieCredits = [];
$tvCredits = [];

foreach($allCredits as $credit) {
    $item = [
        'id' => $credit['id'],
        'title' => $credit['title'] ?? $credit['name'],
        'poster' => !empty($credit['poster_path']) ? 'https://image.tmdb.org/t/p/w780'.$credit['poster_path'] : 'assets/images/media/robert.jpg',
        'type' => $credit['media_type'],
        'year' => isset($credit['release_date']) ? substr($credit['release_date'], 0, 4) : (isset($credit['first_air_date']) ? substr($credit['first_air_date'], 0, 4) : 'N/A'),
        'character' => $credit['character'] ?? '',
        'vote' => $credit['vote_average'] ?? 0
    ];

    if ($credit['media_type'] === 'movie') {
        $movieCredits[] = $item;
    } elseif ($credit['media_type'] === 'tv') {
        $tvCredits[] = $item;
    }
}
?>

<!-- ==========================================
     CSS: MIDNIGHT VELVET THEME (Person Page)
     ========================================== -->
<style>
    :root {
        --bg-deep: #0b0c15;
        --bg-card: #151621;
        --accent: #00e0ff;
        --text-main: #ffffff;
        --text-sub: #a0a0a0;
    }

    /* Banner */
    .person-banner {
        height: 50px;
        background: linear-gradient(to bottom, rgba(11,12,21,0.3) 0%, var(--bg-deep) 100%), url('assets/images/common/01.webp');
        background-size: cover; background-position: center;
        position: relative; margin-bottom: 20px;
    }

    /* Sidebar Info */
    .info-card {
        background: var(--bg-card);
        border: 1px solid rgba(255,255,255,0.05);
        border-radius: 12px;
        padding: 25px;
    }
    .info-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-sub); display: block; margin-bottom: 5px; }
    .info-value { font-size: 1rem; color: var(--text-main); font-weight: 500; }

    /* Tabs Styling */
    .nav-pills .nav-link {
        background: transparent;
        color: var(--text-sub);
        border: 1px solid rgba(255,255,255,0.1);
        padding: 10px 25px;
        border-radius: 50px;
        font-weight: 600;
        transition: all 0.3s;
    }
    
    /* UPDATED: Hover Text White */
    .nav-pills .nav-link:hover {
        border-color: var(--accent);
        color: #ffffff !important; /* Forces white text on hover */
        background: rgba(255,255,255,0.05);
    }
    
    .nav-pills .nav-link.active {
        background: var(--accent);
        border-color: var(--accent);
        color: #000 !important; /* Black text on neon background for readability */
        box-shadow: 0 0 15px rgba(0, 224, 255, 0.4);
    }

    /* Scrolling Container for Filmography */
    .filmography-container {
        max-height: 800px; /* Adjust height as needed */
        overflow-y: auto;
        overflow-x: hidden;
        padding-right: 10px; /* Space for scrollbar */
        padding-bottom: 20px;
    }
    
    /* Custom Scrollbar for the list */
    .filmography-container::-webkit-scrollbar { width: 6px; }
    .filmography-container::-webkit-scrollbar-track { background: rgba(255,255,255,0.02); border-radius: 4px; }
    .filmography-container::-webkit-scrollbar-thumb { background: #333; border-radius: 4px; }
    .filmography-container::-webkit-scrollbar-thumb:hover { background: var(--accent); }

    /* Movie Cards (Reused from view-all) */
    .vod-card {
        border-radius: 12px; overflow: hidden; position: relative;
        transition: transform 0.3s; border: 1px solid rgba(255,255,255,0.02);
    }
    .vod-card:hover { transform: translateY(-5px); border-color: rgba(0, 224, 255, 0.3); z-index: 2; }
    
    .poster-box { padding-top: 150%; position: relative; background: #1a1a1a; overflow: hidden; }
    .poster-box img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s; }
    .vod-card:hover .poster-box img { transform: scale(1.05); filter: brightness(0.6); }
    
    .card-badge {
        position: absolute; top: 8px; right: 8px;
        background: rgba(0,0,0,0.7); backdrop-filter: blur(5px);
        color: var(--accent); font-weight: bold; font-size: 0.7rem;
        padding: 2px 6px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.1);
    }
    
    .overlay-play {
        position: absolute; top:0; left:0; width:100%; height:100%;
        display: flex; align-items: center; justify-content: center;
        opacity: 0; transition: opacity 0.2s;
    }
    .vod-card:hover .overlay-play { opacity: 1; }
    
    .play-circle {
        width: 50px; height: 50px; border-radius: 50%;
        background: var(--accent); color: #000;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.2rem; box-shadow: 0 0 20px rgba(0, 224, 255, 0.5);
    }

    .card-desc { padding: 10px 5px; }
    .card-title { color: white; font-weight: 600; font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; text-decoration: none; }
    .vod-card:hover .card-title { color: var(--accent); }
    .card-role { font-size: 0.75rem; color: var(--text-sub); }
    
    /* Breadcrumb Styles */
    .breadcrumb-item + .breadcrumb-item::before { color: var(--text-sub); }
    .breadcrumb-item a { color: var(--text-sub); transition: color 0.2s; }
    .breadcrumb-item a:hover { color: var(--accent); }
    .breadcrumb-item.active { color: var(--text-main); font-weight: 600; }

    /* Layout Adjustments */
    .content-shift-up {
        margin-top: -80px; /* Pulls content up towards banner more aggressively */
    }
    @media (max-width: 991px) {
        .content-shift-up { margin-top: 20px; } /* Reset on mobile */
    }
</style>

<!-- ==========================================
     HTML CONTENT
     ========================================== -->

<div class="person-banner"></div>

<div class="section-padding">
  <div class="container-fluid">
    <div class="row">
      
      <!-- LEFT: PROFILE -->
      <div class="col-lg-3 col-md-4" style="margin-top: -100px;">
        <div class="position-relative mb-4">
          <img src="<?php echo $image; ?>" class="img-fluid w-100 rounded-3 shadow-lg" 
               alt="<?php echo htmlspecialchars($name); ?>" 
               style="border: 4px solid var(--bg-card); box-shadow: 0 10px 40px rgba(0,0,0,0.6);">
        </div>
        
        <div class="info-card">
            <h5 class="mb-4 text-white fw-bold border-bottom border-secondary pb-2">Personal Info</h5>
            <ul class="list-unstyled d-flex flex-column gap-3 mb-0">
              <li>
                <span class="info-label">Known For</span>
                <span class="info-value"><?php echo htmlspecialchars($department); ?></span>
              </li>
              <li>
                <span class="info-label">Born</span>
                <span class="info-value"><?php echo $birthday; ?></span>
              </li>
              <?php if($placeOfBirth): ?>
              <li>
                <span class="info-label">Place of Birth</span>
                <span class="info-value"><?php echo htmlspecialchars($placeOfBirth); ?></span>
              </li>
              <?php endif; ?>
              <li>
                <span class="info-label">Total Credits</span>
                <span class="info-value"><?php echo count($allCredits); ?></span>
              </li>
            </ul>
        </div>
      </div>

      <!-- RIGHT: BIO & TABS (Shifted Up) -->
      <div class="col-lg-9 col-md-8 ps-lg-5 content-shift-up">
        
        <!-- Breadcrumb to fill space professionally -->
        <nav aria-label="breadcrumb" class="mb-2">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="/" class="text-decoration-none">Home</a></li>
                <li class="breadcrumb-item"><a href="#" class="text-decoration-none">Person</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($name); ?></li>
            </ol>
        </nav>

        <h1 class="display-4 fw-bolder text-white mb-3" style="text-shadow: 0 4px 15px rgba(0,0,0,0.5); letter-spacing: -1px;"><?php echo htmlspecialchars($name); ?></h1>
        
        <!-- Bio -->
        <div class="mb-5">
            <h5 class="text-primary fw-bold mb-3 text-uppercase small letter-spacing-2" style="color: var(--accent) !important;">Biography</h5>
            <div class="text-white text-opacity-75" style="line-height: 1.8; font-size: 1.05rem;">
                <?php 
                    if (strlen($bio) > 800) {
                        echo nl2br(htmlspecialchars(substr($bio, 0, 800))) . "...";
                    } else {
                        echo nl2br(htmlspecialchars($bio));
                    }
                ?>
            </div>
        </div>

        <!-- TABS HEADER -->
        <div class="d-flex align-items-center justify-content-between mb-4 border-bottom border-secondary pb-3">
            <h4 class="fw-bold text-white m-0">Filmography</h4>
            <ul class="nav nav-pills gap-2" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="pill" href="#movies" role="tab">Movies</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="pill" href="#tvshows" role="tab">TV Shows</a>
                </li>
            </ul>
        </div>

        <!-- TABS CONTENT (With Internal Scrolling) -->
        <div class="tab-content filmography-container">
            
            <!-- MOVIES TAB -->
            <div id="movies" class="tab-pane fade show active" role="tabpanel">
              <?php if (!empty($movieCredits)): ?>
              <div class="row row-cols-xl-5 row-cols-lg-4 row-cols-md-3 row-cols-2 g-4">
                  <?php foreach ($movieCredits as $item): ?>
                  <div class="col">
                     <div class="vod-card h-100">
                        <div class="poster-box">
                           <img src="<?php echo $item['poster']; ?>" alt="poster" loading="lazy">
                           <div class="card-badge"><?php echo $item['year']; ?></div>
                           <div class="overlay-play">
                               <a href="/movie-detail?id=<?php echo $item['id']; ?>&type=movie" class="play-circle">
                                   <i class="fa-solid fa-play"></i>
                               </a>
                           </div>
                           <a href="/movie-detail?id=<?php echo $item['id']; ?>&type=movie" class="position-absolute top-0 start-0 w-100 h-100"></a>
                        </div>
                        <div class="card-desc">
                           <a href="/movie-detail?id=<?php echo $item['id']; ?>&type=movie" class="card-title"><?php echo htmlspecialchars($item['title']); ?></a>
                           <div class="card-role">as <?php echo htmlspecialchars($item['character'] ?: 'Actor'); ?></div>
                        </div>
                     </div>
                  </div>
                  <?php endforeach; ?>
              </div>
              <?php else: ?>
                  <p class="text-muted py-4">No movie credits found.</p>
              <?php endif; ?>
            </div>

            <!-- TV SHOWS TAB -->
            <div id="tvshows" class="tab-pane fade" role="tabpanel">
              <?php if (!empty($tvCredits)): ?>
              <div class="row row-cols-xl-5 row-cols-lg-4 row-cols-md-3 row-cols-2 g-4">
                  <?php foreach ($tvCredits as $item): ?>
                  <div class="col">
                     <div class="vod-card h-100">
                        <div class="poster-box">
                           <img src="<?php echo $item['poster']; ?>" alt="poster" loading="lazy">
                           <div class="card-badge"><?php echo $item['year']; ?></div>
                           <div class="overlay-play">
                               <a href="/movie-detail?id=<?php echo $item['id']; ?>&type=tv" class="play-circle">
                                   <i class="fa-solid fa-play"></i>
                               </a>
                           </div>
                           <a href="/movie-detail?id=<?php echo $item['id']; ?>&type=tv" class="position-absolute top-0 start-0 w-100 h-100"></a>
                        </div>
                        <div class="card-desc">
                           <a href="/movie-detail?id=<?php echo $item['id']; ?>&type=tv" class="card-title"><?php echo htmlspecialchars($item['title']); ?></a>
                           <div class="card-role">as <?php echo htmlspecialchars($item['character'] ?: 'Actor'); ?></div>
                        </div>
                     </div>
                  </div>
                  <?php endforeach; ?>
              </div>
              <?php else: ?>
                  <p class="text-muted py-4">No TV show credits found.</p>
              <?php endif; ?>
            </div>

        </div>
      </div>
    </div>
  </div>
</div>

<?php include APP_PATH . '/views/includes/footer.php'; ?>