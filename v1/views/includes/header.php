<?php
// Ensure session is started (safe-guard when header is included directly)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- 2. THEME LOGIC (Using your specific code) ---
// Normalize kids-mode flags so older pages (is_kid) and new pages (is_kids_mode)
// both work. Keep session canonical by setting both keys when either exists.
if (isset($_SESSION['is_kids_mode']) && !array_key_exists('is_kid', $_SESSION)) {
    $_SESSION['is_kid'] = $_SESSION['is_kids_mode'] ? 1 : 0;
} elseif (!isset($_SESSION['is_kids_mode']) && array_key_exists('is_kid', $_SESSION)) {
    // normalize truthy values (could be 1/0)
    $_SESSION['is_kids_mode'] = ($_SESSION['is_kid'] == 1);
}

// Compute a single boolean that all template logic can use
$isKidsMode = (isset($_SESSION['is_kids_mode']) && $_SESSION['is_kids_mode'] === true) || (isset($_SESSION['is_kid']) && $_SESSION['is_kid'] == 1);

// Plan fallback: prefer plan_name but if only plan_id exists, and $conn is available
// we attempt to read the plan name. Default to 'free'.
$current_plan = null;
if (!empty($_SESSION['plan_name'])) {
    $current_plan = strtolower($_SESSION['plan_name']);
} elseif (!empty($_SESSION['plan_id'])) {
    // Try to resolve a plan name when DB connection ($conn) is available
    if (isset($conn)) {
        try {
            $pp = $conn->prepare("SELECT name FROM plans WHERE id = ? LIMIT 1");
            $pp->execute([ (int) $_SESSION['plan_id'] ]);
            $pr = $pp->fetch(PDO::FETCH_ASSOC);
            if (!empty($pr['name'])) $current_plan = strtolower($pr['name']);
        } catch (Exception $e) {
            // ignore DB lookup failure and fall back to free
        }
    }
}
if (empty($current_plan)) $current_plan = 'free';

$btnLink  = '/pricing-plan'; // Updated to match Router path
$btnText  = 'Subscribe';
$btnIcon  = 'ph-crown';
// 'btn-warning-subtle' is the default yellow outline style
$btnClass = 'btn-warning-subtle text-warning'; 

// Premium User (Silver)
if ($current_plan === 'premium') {
    $btnLink  = 'javascript:void(0)'; // Already subscribed
    $btnText  = 'Premium';
    $btnIcon  = 'ph-star'; // Use a star for Premium
    // 'btn-secondary' gives a grey/silver look
    $btnClass = 'btn-secondary text-white border-secondary'; 
} 
// Pro User (Gold)
elseif ($current_plan === 'pro') {
    $btnLink  = 'javascript:void(0)'; // Already subscribed
    $btnText  = 'Pro';
    $btnIcon  = 'ph-crown';
    // 'btn-warning' gives a solid gold/yellow look
    $btnClass = 'btn-warning text-dark border-warning fw-bold'; 
}

// --- 3. Build base URL dynamically ---
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$domain = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . $domain;

// --- 4. DEFAULT AVATAR ---
$avatarPath = 'assets/images/user/user6.jpg';

// --- 5. Check profile/session avatar ---
if (isset($user) && is_array($user) && !empty($user['avatar_url'])) {
    if (strpos($user['avatar_url'], 'http') === false) {
        $avatarPath = $baseUrl . $user['avatar_url'];
    } else {
        $avatarPath = $user['avatar_url'];
    }
}
else if (isset($_SESSION['avatar_url']) && !empty($_SESSION['avatar_url'])) {
    if (strpos($_SESSION['avatar_url'], 'http') === false) {
        $avatarPath = $baseUrl . $_SESSION['avatar_url'];
    } else {
        $avatarPath = $_SESSION['avatar_url'];
    }
}

// --- 7. Display name ---
$displayName = 'Guest User';
$userEmail = ''; 

if (isset($user) && is_array($user)) {
    $firstName = $user['firstName'] ?? '';
    $lastName = $user['lastName'] ?? '';
    $fullName = trim($firstName . ' ' . $lastName);
    $displayName = !empty($fullName) ? $fullName : ($user['username'] ?? 'Guest User');
    $userEmail = $user['email'] ?? ''; 
}
else if (isset($_SESSION['username'])) {
    $displayName = $_SESSION['username'];
    $userEmail = $_SESSION['email'] ?? '';
}

// Apply Kids Filter if Active
$kidsFilter = [];
if (isset($_SESSION['is_kids_mode']) && $_SESSION['is_kids_mode'] === true) {
    $kidsFilter = [
        'certification_country' => 'US',
        'certification.lte' => 'PG', // Only G and PG content
        'with_genres' => '16,10751', // Animation, Family
        'without_genres' => '27,53,80' // No Horror, Thriller, Crime
    ];
    
    // Merge this with your existing API params
    // Example: $params = array_merge($params, $kidsFilter);
}
// Change Avatar if in Kids Mode
if ($isKidsMode) {
    $avatarPath = 'assets/images/user/kids-avatar.png'; // You need to add a cute image here
    // If you don't have a specific image, use a default but we style it differently below
    if (!file_exists($avatarPath)) $avatarPath = 'assets/images/user/user6.jpg'; 
} else {
    $avatarPath = 'assets/images/user/user6.jpg'; // Default
    if (isset($_SESSION['avatar_url']) && !empty($_SESSION['avatar_url'])) {
        $avatarPath = (strpos($_SESSION['avatar_url'], 'http') === false) ? $baseUrl . $_SESSION['avatar_url'] : $_SESSION['avatar_url'];
    }
}

$displayName = $isKidsMode ? 'Kids Profile' : ($_SESSION['username'] ?? 'Guest User');
$userEmail = $_SESSION['email'] ?? '';

$pageThemeClass = $pageThemeClass ?? '';
?>


<!doctype html>
<html lang="en" data-bs-theme="dark">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>ZEN-AI <?php echo $isKidsMode ? '- Kids Mode Active' : ''; ?></title>
  <!-- Google Font Api KEY-->
  <meta name="google_font_api" content="AIzaSyBG58yNdAjc20_8jAvLNSVi9E4Xhwjau_k">

  <!-- Favicon -->
  <!-- <link rel="shortcut icon" href="assets/images/favicon.ico" /> -->
    <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#e50914">
  <link rel="apple-touch-icon" href="assets/images/logo.png">
  <!-- Library / Plugin Css Build -->
  <link rel="stylesheet" href="assets/css/core/libs.min.css" />

  <!-- font-awesome css -->
  <link rel="stylesheet" href="assets/vendor/font-awesome/css/all.min.css" />
  <link rel="stylesheet" href="assets/css/core/custom.min.cssv=5.4.0.css" />
  <link rel="stylesheet" href="assets/css/core/rtl.min.cssv=5.4.0.css" />
  <link rel="stylesheet" href="assets/css/core/zen.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastify-js/1.6.1/toastify.css" integrity="sha512-VSD3lcSci0foeRFRHWdYX4FaLvec89irh5+QAGc00j5AOdow2r5MFPhoPEYBUQdyarXwbzyJEO7Iko7+PnPuBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <!-- Iconly css -->
  <link rel="stylesheet" href="assets/vendor/iconly/css/style.css" />

  <!-- Animate css -->
  <link rel="stylesheet" href="assets/vendor/animate.min.css" />

  <!-- SwiperSlider css -->
  <link rel="stylesheet" href="https://templates.iqonic.design/streamit-dist/frontend/html//assets/vendor/swiperSlider/swiper.min.css">


  <!-- Sweetlaert2 css -->
  <link rel="stylesheet" href="assets/vendor/sweetalert2/sweetalert2.min.css" />

  <!-- Google Font -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,300;0,400;0,500;0,700;0,900;1,300&display=swap"
      rel="stylesheet">


  <!-- Phosphor icons  -->
  <link rel="stylesheet" href="assets/vendor/phosphor-icons/Fonts/regular/style.css">
  <link rel="stylesheet" href="assets/vendor/phosphor-icons/Fonts/duotone/style.css">

  <link rel="stylesheet" href="assets/vendor/phosphor-icons/Fonts/fill/style.css">


  <link rel="stylesheet" href="assets/vendor/streamit-font/iconly.css"></head>

<body class=" <?php echo htmlspecialchars($pageThemeClass); ?>  <?php echo $isKidsMode ? 'kids-mode-active' : ''; ?>">
<!-- PWA Install Modal -->
<div id="pwa-install-modal" class="pwa-modal">
    <div class="pwa-content">
        <div class="pwa-header">
            <h5 style="margin:0; color:#fff;">Install App</h5>
            <button id="pwa-close-btn" type="button">&times;</button>
        </div>
        <div class="pwa-body">
            <p>Install <strong>Zen Movies</strong> for the best experience, faster load times, and full screen viewing!</p>
        </div>
        <div class="pwa-footer">
            <button id="pwa-install-btn" type="button">Install Application</button>
        </div>
    </div>
</div>

<style>
    /* PWA Modal Styling */
    .pwa-modal {
        display: none; /* Hidden by default */
        position: fixed;
        z-index: 10000; /* Extremely high to sit over everything */
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        align-items: center;
        justify-content: center;
    }

    .pwa-modal.show {
        display: flex;
    }

    .pwa-content {
        background-color: #141414;
        border: 1px solid #333;
        color: #ffffff;
        width: 90%;
        max-width: 380px;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.8);
        overflow: hidden;
        animation: pwaSlideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .pwa-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 20px;
        border-bottom: 1px solid #2a2a2a;
        background: #1a1a1a;
    }

    #pwa-close-btn {
        background: none;
        border: none;
        color: #aaa;
        font-size: 28px;
        line-height: 1;
        cursor: pointer;
        padding: 0;
    }
    #pwa-close-btn:hover { color: #fff; }

    .pwa-body {
        padding: 24px 20px;
        text-align: center;
        font-size: 0.95rem;
        color: #ddd;
        line-height: 1.5;
    }

    .pwa-footer {
        padding: 0 20px 20px;
        display: flex;
        justify-content: center;
    }

    #pwa-install-btn {
        background: linear-gradient(45deg, #e50914, #ff4040);
        color: white;
        border: none;
        padding: 12px 25px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        font-size: 1rem;
        width: 100%;
        box-shadow: 0 4px 15px rgba(229, 9, 20, 0.4);
        transition: transform 0.2s;
    }

    #pwa-install-btn:active { transform: scale(0.98); }

    @keyframes pwaSlideUp {
        from { transform: translateY(40px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    
    // 1. Register Service Worker
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js')
            .then(reg => console.log('✅ Service Worker Registered'))
            .catch(err => console.error('❌ Service Worker Error:', err));
    }

    // 2. Variables
    let deferredPrompt;
    const installModal = document.getElementById('pwa-install-modal');
    const installBtn = document.getElementById('pwa-install-btn');
    const closeBtn = document.getElementById('pwa-close-btn');

    // 3. Check if elements exist to prevent errors
    if (!installModal || !installBtn || !closeBtn) {
        console.error("❌ PWA Modal elements not found in HTML.");
        return;
    }

    // 4. Listen for the 'beforeinstallprompt' event
    window.addEventListener('beforeinstallprompt', (e) => {
        console.log("🚀 PWA Install Event Triggered!");
        
        // Prevent Chrome 67 and earlier from automatically showing the prompt
        e.preventDefault();
        
        // Stash the event so it can be triggered later.
        deferredPrompt = e;
        
        // Show the modal
        installModal.classList.add('show');
    });

    // 5. Handle Install Button Click
    installBtn.addEventListener('click', async () => {
        if (!deferredPrompt) {
            console.log("⚠️ No install prompt available (Already installed or not supported)");
            return;
        }

        // Hide the modal
        installModal.classList.remove('show');
        
        // Show the install prompt
        deferredPrompt.prompt();
        
        // Wait for the user to respond to the prompt
        const { outcome } = await deferredPrompt.userChoice;
        console.log(`User response to the install prompt: ${outcome}`);
        
        // We've used the prompt, so clear it
        deferredPrompt = null;
    });

    // 6. Handle Close Button
    closeBtn.addEventListener('click', () => {
        installModal.classList.remove('show');
    });

    // 7. Check if app was successfully installed
    window.addEventListener('appinstalled', () => {
        installModal.classList.remove('show');
        deferredPrompt = null;
        console.log('✅ PWA was installed');
    });
});
</script>   
<!-- Global server status banner (populated dynamically) -->
    <div id="server-status-banner" style="display:none; position:relative; z-index:1200;">
        <div id="server-status-inner" style="display:flex; align-items:center; justify-content:space-between; padding:12px 18px; border-radius:6px; margin:10px auto; max-width:1200px; box-shadow:0 6px 20px rgba(0,0,0,0.25);">
            <div id="server-status-message" style="color:#fff; font-weight:600; font-size:0.95rem;"></div>
            <div style="display:flex; gap:8px; align-items:center;">
                <a id="server-status-cta" href="#" style="display:none; padding:6px 10px; border-radius:4px; background:#ffd54f; color:#000; font-weight:700; text-decoration:none; font-size:0.85rem;">Upgrade</a>
                <button id="server-status-close" style="background:transparent;border:none;color:#fff;font-weight:700;font-size:18px;cursor:pointer;">&times;</button>
            </div>
        </div>
    </div>
  <span class="screen-darken"></span>
  <!-- loader Start -->
     <style>
    :root, [data-bs-theme=dark] {
        /* 1. Backgrounds: Deep Midnight Blue/Charcoal */
        --bs-body-bg: #0b0c15; 
        --bs-body-bg-rgb: 11, 12, 21;
        
        /* 2. Components: Slightly lighter midnight for cards/nav */
        --bs-gray-900: #151621;
        --bs-dark: #151621;
        --card-bg: #151621;
        
        /* 3. Primary Accent: Neon Cyan */
        --bs-primary: #00e0ff;
        --bs-primary-rgb: 0, 224, 255;
        --bs-primary-hover: #66fcf1;
        
        /* 4. Text & Borders */
        --bs-body-color: #c5c6c7;
        --bs-heading-color: #ffffff;
        --bs-border-color: #2a2d3e;
        --bs-border-color-translucent: rgba(42, 45, 62, 0.5);
    }

    /* Override Bootstrap Primary Buttons for Neon Look */
    .btn-primary {
        background-color: var(--bs-primary) !important;
        border-color: var(--bs-primary) !important;
        color: #000 !important; /* Black text on Cyan/Neon */
        font-weight: 700;
        box-shadow: 0 0 15px rgba(0, 224, 255, 0.3); /* Glow Effect */
    }
    .btn-primary:hover {
        background-color: var(--bs-primary-hover) !important;
        border-color: var(--bs-primary-hover) !important;
        box-shadow: 0 0 25px rgba(0, 224, 255, 0.5);
    }
    
    /* Text Links & Icons */
    .text-primary, a.text-primary { color: var(--bs-primary) !important; }
    .nav-link.active { color: var(--bs-primary) !important; }
    
    /* Selection Color */
    ::selection {
        background: var(--bs-primary);
        color: #000;
    }

    /* Kids Mode Specifics */
    .kids-mode-active .navbar { border-bottom: 2px solid #00d2ff; }
    .kids-mode-active .st-avatar img { border: 2px solid #00d2ff; }
    
    /* Scrollbar (Matches Theme) */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: var(--bs-body-bg); }
    ::-webkit-scrollbar-thumb { background: #333; border-radius: 4px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--bs-primary); }
  </style>
   <!-- loader Start -->
     <style>
      /* Kids Mode Styling */
      .kids-mode-active .navbar {
          border-bottom: 3px solid #00d2ff; /* Blue line for Kids */
      }
      .kids-mode-active .st-avatar img {
          border: 2px solid #00d2ff;
      }
  </style>
  <!-- <div class="loader simple-loader">
     <div class="loader-body">
        <img src="assets/images/loader.gif" alt="loader" class="img-fluid " width="300">
      </div>
  </div> -->
  <!-- loader END -->  <!-- loader END -->
  <main class="main-content">
    <!--Nav Start-->
    <header class="header-center-home header-default header-sticky">
       <nav class="nav navbar navbar-expand-xl navbar-light iq-navbar header-hover-menu py-xl-0">
          <div class="container-fluid navbar-inner">
             <div class="d-flex align-items-center justify-content-between w-100 landing-header">
                <div class="d-flex gap-3 gap-xl-0 align-items-center">
                   <div class="d-flex align-items-center gap-2 gap-md-3">
                      <div class="logo-default">
                          <a class="navbar-brand text-primary me-0" href="/"> <!-- Updated to root path -->
                              <!-- <img class="img-fluid logo" src="assets/images/logo.png" loading="lazy" alt="streamit" /> -->
                          </a>
                      </div>
                      <div class="logo-hotstar">
                          <a class="navbar-brand text-primary me-0" href="/"> <!-- Updated to root path -->
                              <img class="img-fluid logo" src="assets/images/logo-hotstar.webp" loading="lazy" alt="streamit" />
                          </a>
                      </div>
                      <div class="logo-prime">
                          <a class="navbar-brand text-primary me-0" href="/"> <!-- Updated to root path -->
                              <img class="img-fluid logo" src="assets/images/logo-prime.webp" loading="lazy" alt="streamit" />
                          </a>
                      </div>
                      <div class="logo-hulu">
                          <a class="navbar-brand text-primary me-0" href="/"> <!-- Updated to root path -->
                              <img class="img-fluid logo" src="assets/images/logo-hulu.webp" loading="lazy" alt="streamit" />
                          </a>
                      </div>                  
                         <?php if (!$isKidsMode): ?>
                      <div>
                        <a href="<?php echo $btnLink; ?>" class="subscribe-btn btn py-1 py-md-2 px-2 px-ms-3 <?php echo $btnClass; ?>">
                            <span class="d-flex align-items-center gap-2">
                                <i class="ph-fill <?php echo $btnIcon; ?> align-middle fs-6"></i>
                                <span class="d-xl-block d-none"><?php echo $btnText; ?></span>
                            </span>
                        </a>
                      </div>
                                            <?php endif; ?>
                                            <!-- Small header toggle for Kids Mode (also available in profile dropdown) -->
                                            <div class="ms-2 d-flex align-items-center">
                                                <button id="kids-mode-toggle" onclick="switchProfileMode();" class="btn btn-sm btn-outline-light py-1 px-2" title="Switch to Kids Mode">
                                                    <i class="ph <?php echo $isKidsMode ? 'ph-user-switch text-warning' : 'ph-smiley text-info'; ?>"></i>
                                                    <span class="d-none d-xl-inline ms-1 fw-bold"><?php echo $isKidsMode ? 'Kids' : 'Kids'; ?></span>
                                                </button>
                                            </div>
                   </div>

              </div>
<nav id="navbar_main" class="offcanvas mobile-offcanvas nav navbar navbar-expand-xl hover-nav horizontal-nav mega-menu-content py-xl-0 w-100">
    <div class="container-fluid p-lg-0">
        <div class="offcanvas-header px-0">
            <div class="navbar-brand ms-3">
                <div class="logo-default">
                    <a class="navbar-brand text-primary me-0" href="/"> 
                        <img class="img-fluid logo" src="assets/images/logo.png" loading="lazy" alt="streamit" />
                    </a>
                </div>
                <!-- Other logos hidden for brevity but structure preserved -->
            </div>
            <button type="button" class="btn-close float-end px-3" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <ul class="navbar-nav iq-nav-menu list-unstyled" id="header-menu">
            <li class="nav-item">
                <a class="nav-link" href="/" role="button" aria-expanded="false" aria-controls="homePages">
                    <div class="d-flex justify-content-between">
                        <span class="item-name">Home</span>
                    </div>
                </a>
            </li>
            
            <!-- MOVIES DROPDOWN -->
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#movies" role="button" aria-expanded="false" aria-controls="homePages">
                    <div class="d-flex justify-content-between">
                        <span class="item-name">Movies</span>
                        <span class="menu-icon">
                            <i class="ph ph-caret-down align-middle"></i>
                        </span>
                    </div>
                </a>
                <ul class="sub-nav collapse list-unstyled" id="movies">
                    <li class="nav-item">
                        <a class="nav-link" href="/view-all?type=discover&with_original_language=zh"><span>Chinese Drama</span></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/view-all?type=discover&with_original_language=ko"><span>K-Drama</span></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/view-all?type=tv"><span>TV Series</span></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/view-all?type=discover"><span>International</span></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="collapse" href="#blog-grid" role="button" aria-expanded="false" aria-controls="blog-grid">
                            <div class="d-flex justify-content-between">
                                <span class="item-name">Asian Movies</span>
                                <span class="menu-icon"><i class="ph ph-caret-down align-middle down-to-right"></i></span>
                            </div>
                        </a>
                        <ul class="sub-nav collapse list-unstyled" id="blog-grid">
                            <li class="nav-item"><a class="nav-link" href="/view-all?type=discover&with_origin_country=IN"><span>Bollywood</span></a></li>
                            <li class="nav-item"><a class="nav-link" href="/view-all?type=discover&with_original_language=ko"><span>Korean Movies</span></a></li>
                            <li class="nav-item"><a class="nav-link" href="/view-all?type=discover&with_original_language=ja"><span>Japanese Movies</span></a></li>
                            <li class="nav-item"><a class="nav-link" href="/view-all?type=discover&with_original_language=tl"><span>Philippine Movies</span></a></li>
                        </ul>
                    </li>
                </ul>
            </li>
            
            <!-- GENRE DROPDOWN (Dynamic) -->
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#genre-menu" role="button" aria-expanded="false" aria-controls="genre-menu">
                    <div class="d-flex justify-content-between">
                        <span class="item-name">Genre</span>
                        <span class="menu-icon">
                            <i class="ph ph-caret-down align-middle"></i>
                        </span>
                    </div>
                </a>

                <ul class="sub-nav collapse list-unstyled" id="genre-menu" style="max-height: 400px; overflow-y: auto;">
                   <?php
                   if (function_exists('fetchTmdbApi')) {
    $genreData = fetchTmdbApi('genre/movie/list');
    if ($genreData && !empty($genreData['genres'])) {
        foreach ($genreData['genres'] as $genre) {
            // UPDATED: Now uses type=discover&with_genres=...
            echo '<li class="nav-item">
                    <a class="nav-link" href="/view-all?type=discover&with_genres=' . $genre['id'] . '">
                        ' . htmlspecialchars($genre['name']) . '
                    </a>
                  </li>';
        }
    }
}
                    ?>
                </ul>
            </li>
        </ul>
    </div>
</nav>
<div class="css_prefix-header-right d-flex align-items-center gap-2">
    <ul class="list-inline d-flex align-items-center gap-3 gap-md-4 mb-0 ps-0 justify-content-md-end justify-content-between">
    <li class="nav-item dropdown iq-responsive-menu d-xl-block d-none">
            <div class="search-box">
                <!-- YOUR EXACT HTML STRUCTURE -->
                <a href="#search-drop" class="nav-link p-0 text-white" id="search-drop" data-bs-toggle="dropdown"> 
                    <div class="btn-icon btn-sm rounded-pill btn-action">
                        <span class="btn-inner">
                            <i class="ph ph-magnifying-glass p-0"></i>
                        </span>
                    </div>
                </a>
                <ul class="dropdown-menu p-0 dropdown-search m-0 iq-search-bar" style="width: 20rem;">
                    <li class="p-0">
                        <form action="/view-all" method="GET" class="site-search-form" data-mobile="0">
                            <div class="form-group input-group mb-0">
                                <input type="text" name="search" class="form-control border-0" placeholder="Search...">
                                <button type="submit" class="search-submit">
                                    <i class="ph ph-magnifying-glass"></i>
                                </button>
                            </div>
                        </form>
                    </li>
                </ul>
            </div>
        </li>

        <!-- MOBILE SEARCH: visible only on small screens -->
        <!-- <li class="nav-item dropdown iq-responsive-menu d-block d-xl-none">
            <div class="search-box-mobile w-100 px-3 py-2">
                <a href="#search-drop-mobile" class="nav-link p-0 text-white d-flex align-items-center" id="search-drop-mobile" data-bs-toggle="dropdown" aria-expanded="false"> 
                    <div class="btn-icon btn-sm rounded-pill btn-action">
                        <span class="btn-inner">
                            <i class="ph ph-magnifying-glass p-0"></i>
                        </span>
                    </div>
                    <span class="ms-2 d-inline-block small text-muted">Search</span>
                </a>
                <ul class="dropdown-menu p-3 dropdown-search m-0 iq-search-bar" style="width:100vw; left:0; right:0;">
                    <li class="p-0">
                        <form action="/view-all" method="GET" class="site-search-form" data-mobile="1">
                            <div class="form-group input-group mb-0">
                                <input type="text" name="search" class="form-control border-0" placeholder="Search..." autocomplete="off">
                                <button type="submit" class="search-submit btn btn-primary ms-2">
                                    <i class="ph ph-magnifying-glass"></i>
                                </button>
                            </div>
                        </form>
                    </li>
                </ul>
            </div>
        </li> -->

        <li class="nav-item dropdown cust-itemdropdown1" id="itemdropdown1">
            <a class="nav-link d-flex align-items-center p-0" href="#navbarDropdown" id="navbarDropdown" role="button"
                data-bs-toggle="dropdown" aria-expanded="false">
                <div class="st-avatar style-1">
                    <img src="<?php echo htmlspecialchars($avatarPath ?? 'assets/images/user/user.jpg'); ?>" alt="Profile picture"
                        class="img-fluid rounded-circle dropdown-user-menu-image header-user-image">
                </div>
            </a>
            <div class="dropdown-menu dropdown-user-menu dropdown-menu-end border border-gray-900 rounded-3"
                data-popper-placement="bottom-end"
                style="position: absolute; inset: 0px 0px auto auto; margin: 0px; transform: translate(0px, 74px);">
                <div class="user-dropdown-inner">
                    <!-- User Info -->
                    <div class="d-flex align-items-center gap-3 rounded mb-4">
                        <div class="image flex-shrink-0">
                            <img src="<?php echo htmlspecialchars($avatarPath ?? 'assets/images/user/user.jpg'); ?>"
                                class="img-fluid rounded-3 dropdown-user-menu-image" alt="Profile picture">
                        </div>
                        <div class="content">
                            <h6 class="mb-1"><?php echo htmlspecialchars($displayName); ?></h6>
                            <?php if (!$isKidsMode && !empty($userEmail)): ?>
                                <p class="mb-0" style="font-size: 0.8rem;"><?php echo htmlspecialchars($userEmail); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Menu Items -->
                   <ul class="d-flex flex-column gap-3 list-inline m-0 p-0">
                        <?php if (!$isKidsMode): ?>
                        <li><a href="/profile" class="link-body-emphasis font-size-14 d-flex align-items-center gap-2"><i class="ph ph-user"></i><span class="fw-medium">Profile</span></a></li>
                        <!-- <li><a href="/watchlist" class="link-body-emphasis font-size-14 d-flex align-items-center gap-2"><i class="ph ph-plus"></i><span class="fw-medium">Watch List</span></a></li> -->
                        <?php endif; ?>
                        
                        <!-- SWITCH PROFILE BUTTON: Visible toggle to enable/disable Kids mode -->
                        <li class="border-top border-bottom py-2 my-2">
                            <a href="" id="kids-mode-dropdown-toggle" onclick="switchProfileMode(); return false;" class="link-body-emphasis font-size-14 d-flex align-items-center gap-2">
                                <i class="ph <?php echo $isKidsMode ? 'ph-user-switch text-warning' : 'ph-smiley text-info'; ?>"></i>
                                <span class="fw-bold"><?php echo $isKidsMode ? 'Exit Kids Mode' : 'Switch to Kids'; ?></span>
                            </a>
                        </li>

                    </ul>
                </div>

                <!-- Logout -->
                <a href="/logout"
                    class="btn btn-link p-3 d-block font-size-14 text-center text-decoration-none border-top">
                    <span class="d-flex align-items-center justify-content-center gap-2 fw-medium">
                        <i class="ph ph-sign-out"></i>
                        Logout
                    </span>
                </a>
            </div>

        </li>
    </ul>
    <button class="navbar-toggler d-block d-xl-none text-white" type="button" data-bs-toggle="offcanvas"
        data-bs-target="#navbar_main" aria-controls="navbar_main">
        <i class="ph ph-list"></i>
    </button>
</div>
       </nav>
       <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

       <script>
   function switchProfileMode() {
    fetch('/switch-mode', { credentials: 'same-origin' })
       .then(async res => {
           // Try to parse JSON and if invalid, read the raw text and include it in the message for debugging
           try {
               return await res.json();
           } catch (e) {
               let txt = '';
               try { txt = await res.text(); } catch (_) { txt = ''; }
               console.warn('switchProfileMode: non-JSON response', res.status, txt);
               return { status: 'error', message: 'Server returned invalid response' + (txt ? ': ' + txt.replace(/\s+/g, ' ').slice(0, 400) : '') };
           }
       })
       .then(data => {
          // Helper: show a visible server status banner (keeps toast as well)
          function showServerBanner(status, message) {
              try {
                  const banner = document.getElementById('server-status-banner');
                  const inner = document.getElementById('server-status-inner');
                  const msg = document.getElementById('server-status-message');
                  const cta = document.getElementById('server-status-cta');
                  const closeBtn = document.getElementById('server-status-close');

                  if (!banner || !inner || !msg || !closeBtn) return;

                  // Style background based on status
                  if (status === 'success') {
                      inner.style.background = 'linear-gradient(90deg,#2ecc71,#27ae60)';
                      msg.style.color = '#0a0a0a';
                      cta.style.display = 'none';
                  } else {
                      // error / other
                      inner.style.background = 'linear-gradient(90deg,#e53935,#b71c1c)';
                      msg.style.color = '#ffffff';

                      // show CTA to pricing if looks like an upgrade prompt
                      if (message && /upgrade|subscribe|premium/i.test(message)) {
                          cta.href = '/pricing-plan';
                          cta.style.display = 'inline-block';
                      } else {
                          cta.style.display = 'none';
                      }
                  }

                  msg.textContent = message || '';

                  banner.style.display = 'block';
                  // make it visible (set inner size)
                  inner.style.maxWidth = '1200px';

                  // Let close button hide it
                  closeBtn.onclick = (ev) => {
                      ev.preventDefault();
                      banner.style.display = 'none';
                  };
              } catch (err) {
                  console.warn('showServerBanner error', err);
              }
          }
          // hide any previous banner when successful or when moving to other flows
          if (typeof showServerBanner === 'function') {
              // hide only if success, otherwise let subsequent logic show it
          }

          if(data.status === 'success') {
               // remove any persistent server banner
               const prev = document.getElementById('server-status-banner');
               if (prev) prev.style.display = 'none';
               // Immediate UI feedback — wrap toasts so they can't stop reload
               try { Toastify({ text: data.message, style: { background: "#4caf50" } }).showToast(); } catch (e) { console.warn('Toastify error', e); }

               // Toggle body class so user sees the change immediately
               const isKids = !!(data.is_kid || data.is_kids_mode || (data.mode && data.mode === 'kid'));
               document.body.classList.toggle('kids-mode-active', isKids);

               // Update small header button icon and dropdown label if present
               const headerBtn = document.getElementById('kids-mode-toggle');
               const dropdownToggle = document.getElementById('kids-mode-dropdown-toggle');
               if (headerBtn) {
                   headerBtn.querySelector('i').className = 'ph ' + (isKids ? 'ph-user-switch text-warning' : 'ph-smiley text-info');
                   const smallLabel = headerBtn.querySelector('span'); if (smallLabel) smallLabel.textContent = isKids ? 'Exit Kids' : 'Kids';
               }
               if (dropdownToggle) {
                   const ddIcon = dropdownToggle.querySelector('i'); if (ddIcon) ddIcon.className = 'ph ' + (isKids ? 'ph-user-switch text-warning' : 'ph-smiley text-info');
                   const ddText = dropdownToggle.querySelector('span'); if (ddText) ddText.textContent = isKids ? 'Exit Kids Mode' : 'Switch to Kids';
               }

               // Reload immediately (small delay so the toast renders) so current listings apply Kids filters
               setTimeout(() => { window.location.reload(); }, 200);
           } else if (data.status === 'need_pin') {
               // Show modal to ask for parental PIN (modal is now in footer)
               // Use a robust flow: verify DOM, clear input, and show Bootstrap modal if available
               const pinInput = document.getElementById('parental-pin-input');
               const submitBtn = document.getElementById('parental-pin-submit');
               const cancelBtn = document.getElementById('parental-pin-cancel');

               if (pinInput && submitBtn) {
                   pinInput.value = '';
                   pinInput.focus();

                   // show bootstrap modal if available, fallback to inline display
                   const bsModalEl = document.getElementById('parental-pin-modal-bs');
                   if (bsModalEl && window.bootstrap && window.bootstrap.Modal) {
                       let bsModal = bootstrap.Modal.getInstance(bsModalEl);
                       if (!bsModal) bsModal = new bootstrap.Modal(bsModalEl);
                       bsModal.show();
                   } else {
                       const inlineModal = document.getElementById('parental-pin-modal');
                       if (inlineModal) inlineModal.style.display = 'flex';
                   }

                   // Prepare submit behavior
                   submitBtn.onclick = async () => {
                       const pin = pinInput.value.trim();
                       if (!pin) {
                           Toastify({ text: 'Enter parental PIN', style: { background: '#e50914' } }).showToast();
                           return;
                       }
                       submitBtn.disabled = true;
                       try {
                           const fd = new FormData();
                           fd.append('parent_pin', pin);
                           const resp = await fetch('/switch-mode', { method: 'POST', body: fd, credentials: 'same-origin' });
                           let json = null;
                           try { json = await resp.json(); } catch(e) { json = { status: 'error', message: 'Invalid server response' }; }
                           submitBtn.disabled = false;
                           if (json && json.status === 'success') {
                               // hide bootstrap modal or inline
                               if (bsModalEl && window.bootstrap && window.bootstrap.Modal) {
                                   const bsModal = bootstrap.Modal.getInstance(bsModalEl);
                                   if (bsModal) bsModal.hide();
                               } else {
                                   const inlineModal = document.getElementById('parental-pin-modal');
                                   if (inlineModal) inlineModal.style.display = 'none';
                               }
                               try { Toastify({ text: json.message, style: { background: '#4caf50' } }).showToast(); } catch (e) { console.warn('Toastify error', e); }
                               // Reload quickly so the switch takes effect (always run even if toast fails)
                               setTimeout(() => { window.location.reload(); }, 200);
                           } else {
                               Toastify({ text: json.message || 'Invalid PIN', style: { background: '#e50914' } }).showToast();
                           }
                       } catch(err) {
                           submitBtn.disabled = false;
                           console.error('PIN submit error', err);
                           Toastify({ text: 'Connection error', style: { background: '#e50914' } }).showToast();
                       }
                   };

                   if (cancelBtn) cancelBtn.onclick = () => {
                       if (bsModalEl && window.bootstrap && window.bootstrap.Modal) {
                           const bsModal = bootstrap.Modal.getInstance(bsModalEl);
                           if (bsModal) bsModal.hide();
                       } else {
                           const inlineModal = document.getElementById('parental-pin-modal');
                           if (inlineModal) inlineModal.style.display = 'none';
                       }
                   };
            } else {
                   console.warn('Parental PIN elements missing in DOM');
               }
            // Show the server message too so it's visible on-page
            if (data.message) {
                showServerBanner(data.status, data.message);
                // Pop-up a modal so it can't be missed (use SweetAlert2 if available)
                if (window.Swal) {
                    Swal.fire({ icon: 'warning', title: 'Parental PIN required', text: data.message, confirmButtonText: 'OK' });
                }
            }
           } else if (data.status === 'no_pin') {
            // If server indicates there's no parental PIN, prompt parent to create one inline
            try { Toastify({ text: data.message, style: { background: "#e50914" } }).showToast(); } catch(e) { console.warn('Toastify error', e); }
            showServerBanner(data.status, data.message);

            const setupModalEl = document.getElementById('parental-pin-setup-modal-bs');
            if (setupModalEl && window.bootstrap && window.bootstrap.Modal) {
                // prepare inputs
                const newPinInput = document.getElementById('parental-new-pin');
                const confirmPinInput = document.getElementById('parental-confirm-pin');
                const submitBtn = document.getElementById('parental-pin-setup-submit');
                const cancelBtn = document.getElementById('parental-pin-setup-cancel');

                if (newPinInput) newPinInput.value = '';
                if (confirmPinInput) confirmPinInput.value = '';

                let setupModal = bootstrap.Modal.getInstance(setupModalEl);
                if (!setupModal) setupModal = new bootstrap.Modal(setupModalEl);
                setupModal.show();

                // Remove previous handlers to avoid duplicate bindings
                if (submitBtn) submitBtn.replaceWith(submitBtn.cloneNode(true));
                // re-select
                const btn = document.getElementById('parental-pin-setup-submit');

                if (btn) btn.addEventListener('click', async () => {
                    const newPin = (document.getElementById('parental-new-pin') || {}).value || '';
                    const confirmPin = (document.getElementById('parental-confirm-pin') || {}).value || '';

                    // Basic validation
                    if (!newPin || !confirmPin) { Toastify({ text: 'Please enter and confirm your PIN', style: { background: '#e50914' } }).showToast(); return; }
                    if (newPin !== confirmPin) { Toastify({ text: 'PINs do not match', style: { background: '#e50914' } }).showToast(); return; }
                    if (!/^[0-9]{4,8}$/.test(newPin)) { Toastify({ text: 'PIN must be 4-8 digits', style: { background: '#e50914' } }).showToast(); return; }

                    btn.disabled = true;
                    try {
                        const fd = new FormData();
                        fd.append('new_pin', newPin);
                        fd.append('confirm_pin', confirmPin);

                        const resp = await fetch('/set-parental-pin', { method: 'POST', body: fd, credentials: 'same-origin' });
                        const json = await resp.json();
                        btn.disabled = false;

                        if (json && json.status === 'success') {
                            try { Toastify({ text: json.message, style: { background: '#4caf50' } }).showToast(); } catch(e){}
                            // hide modal
                            const m = bootstrap.Modal.getInstance(setupModalEl);
                            if (m) m.hide();

                            // after successfully setting the PIN, attempt to enable Kids Mode again
                            setTimeout(() => { switchProfileMode(); }, 200);
                        } else {
                            Toastify({ text: json.message || 'Could not save PIN', style: { background: '#e50914' } }).showToast();
                        }
                    } catch (err) {
                        btn.disabled = false;
                        console.error('set-pin error', err);
                        Toastify({ text: 'Connection error saving PIN', style: { background: '#e50914' } }).showToast();
                    }
                });

                if (cancelBtn) cancelBtn.onclick = () => { setupModal.hide(); };
            } else {
                // fallback: redirect user to profile page to create a PIN
                try { Toastify({ text: data.message, style: { background: '#e50914' } }).showToast(); } catch(e){}
                setTimeout(() => { window.location.href = '/profile'; }, 1200);
            }

           } else {
            try { Toastify({ text: data.message, style: { background: "#e50914" } }).showToast(); } catch(e) { console.warn('Toastify error', e); }
            // If the server returned an error or upgrade prompt, show it as a banner
            if (data.message) showServerBanner(data.status, data.message);
               // make sure this message is visible as a focused popup as well
               if (window.Swal) {
                   // if the message talks about an upgrade, give a clear title
                   const title = /upgrade|subscribe|premium/i.test(data.message) ? 'Upgrade required' : 'Notice';
                   Swal.fire({ icon: 'error', title: title, text: data.message, confirmButtonText: 'OK' });
               }
               // If not premium, redirect to pricing
               if(data.message && data.message.toLowerCase().includes('upgrade')) {
                   setTimeout(() => { window.location.href = '/pricing-plan'; }, 1500);
               }
               // If not logged-in, redirect to login for convenience
               else if (data.message && data.message.toLowerCase().includes('login')) {
                   setTimeout(() => { window.location.href = '/login'; }, 1200);
               }
           }
       })
       .catch(err => {
           console.error('switchProfileMode error', err);
           Toastify({ text: 'Connection error', style: { background: "#e50914" } }).showToast();
       });
   }
   </script>
    <!-- Parental PIN Modal moved to footer for consistent visibility -->
    </header>
<style>
   /*
 * Target only the swiper buttons inside your 'top-ten' block
 */
.iq-top-ten-block-slider .swiper-button-next,
.iq-top-ten-block-slider .swiper-button-prev {
  /* Adjust the button container size.
    The default is often 44px.
  */
  width: 30px;
  height: 30px;
}

/*
 * Target the arrow icon *inside* the buttons
 */
.iq-top-ten-block-slider .swiper-button-next::after,
.iq-top-ten-block-slider .swiper-button-prev::after {
  /* Adjust the icon's font size.
    The default is often 27px or 44px.
  */
  font-size: 18px; /* <-- Change this value to make the arrow smaller */
}

/*
 * Target swiper-card sliders, but NOT the top-ten slider
 * This will apply to your new 'watching' slider.
 */
.swiper-card:not(.iq-top-ten-block-slider) .swiper-button-next,
.swiper-card:not(.iq-top-ten-block-slider) .swiper-button-prev {
  /* Adjust the button container size */
  width: 30px;
  height: 30px;
}

/*
 * Target the arrow icon *inside* those buttons
 */
.swiper-card:not(.iq-top-ten-block-slider) .swiper-button-next::after,
.swiper-card:not(.iq-top-ten-block-slider) .swiper-button-prev::after {
  /* Adjust the icon's font size */
  font-size: 18px; /* <-- Change this value as needed */
}
</style>
<!-- Watchlist AJAX Script -->
  <script>
document.addEventListener('DOMContentLoaded', function() {
    const watchlistBtns = document.querySelectorAll('.watchlist-btn');

    watchlistBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();

            // Get Data
            const mediaId = this.getAttribute('data-id');
            const mediaType = this.getAttribute('data-type');
            const icon = this.querySelector('.icon-status');
            const text = this.querySelector('.text-status');

            // UI Feedback (Loading)
            const originalIconClass = icon.className;
            icon.className = 'spinner-border spinner-border-sm';

            // Send Request
            const formData = new FormData();
            formData.append('media_id', mediaId);
            formData.append('media_type', mediaType);

            fetch('/add-watchlist', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Success Notification
                    Toastify({
                        text: data.message,
                        duration: 3000,
                        style: { background: data.action === 'added' ? "#4caf50" : "#e50914" }
                    }).showToast();

                    // Update UI Button
                    if (data.action === 'added') {
                        icon.className = 'ph ph-check text-success fw-bold icon-status';
                        text.textContent = 'Saved';
                        this.setAttribute('data-bs-original-title', 'Remove from Watchlist');
                    } else {
                        icon.className = 'ph ph-plus fw-bold icon-status';
                        text.textContent = 'Watch List';
                        this.setAttribute('data-bs-original-title', 'Add to Watchlist');
                    }
                } else {
                    // Error handling (e.g. not logged in)
                    try { Toastify({ text: data.message, style: { background: "#e50914" } }).showToast(); } catch(e) { console.warn('Toastify error', e); }
                    icon.className = originalIconClass; // Revert icon
                    
                    // If not logged in, maybe redirect?
                    if(data.message.includes('login')) {
                        setTimeout(() => window.location.href = '/login', 1500);
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                icon.className = originalIconClass;
                Toastify({ text: "Connection error", style: { background: "#e50914" } }).showToast();
            });
        });
    });
});

// duplicate toggle removed — switchProfileMode() (above) handles all kids-mode flows
</script>

<!-- Kids-safe search guard: prevent blocked queries for Kids Mode -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const blocked = [ 'porn', 'xxx', 'sex', 'nude', 'nudity', 'erotic', 'pornography', 'adult', 'hardcore', 
    'xvideos', 'xhamster', 'dick', 'pussy', 'vagina', 'fuck', 'shit', 'bitch', 'ass', 
    'tits', 'boobs', 'cock', 'slut', 'whore', 'rape', 'incest', 'bdsm', 'fetish', 
    'hentai', 'milf', 'anal', 'orgasm', 'masturbat', 'penis', 'blowjob', 'handjob',
    'gangbang', 'threesome', 'escort', 'camgirl', 'naked', 'strip', '18+', 'masturbating','masturbation'];
    const isKids = <?php echo json_encode($isKidsMode); ?>;

    document.querySelectorAll('.site-search-form').forEach(form => {
        form.addEventListener('submit', function(ev) {
            try {
                const input = this.querySelector('input[name="search"]');
                if (!input) return; // nothing to check
                const q = (input.value || '').toLowerCase().trim();
                if (!q) return; // let empty submissions through (listings)

                // If kids mode is active, block any query containing a blocked word
                if (isKids) {
                    for (let b of blocked) {
                        if (q.includes(b)) {
                            ev.preventDefault();
                            try { Toastify({ text: 'Search term blocked in Kids Mode — try a family-friendly keyword', duration: 3500, style: { background: '#e50914' } }).showToast(); } catch(e){ alert('Search term blocked in Kids Mode — try a family-friendly keyword'); }
                            return false;
                        }
                    }
                }
            } catch (err) { console.warn('search guard error', err); }
        }, { passive: false });
    });
});
</script>
