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
  <link rel="shortcut icon" href="/assets/images/favicon.ico" />
    <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#e50914">
  <link rel="apple-touch-icon" href="/assets/images/apple-touch-icon.png">
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
  <link rel="stylesheet" href="assets/vendor/swiperSlider/swiper.min.css">


  <!-- Sweetlaert2 css -->
  <link rel="stylesheet" href="assets/vendor/sweetalert2/sweetalert2.min.css" />

  <!-- Google Font -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <!-- Posters and backdrops come from TMDB's image server: connect early. -->
  <link rel="preconnect" href="https://image.tmdb.org">
  <!-- Loading placeholders for images and page changes -->
  <link rel="stylesheet" href="/assets/css/core/skeleton.css?v=1">
  <script src="/assets/js/skeleton.js?v=1" defer></script>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,300;0,400;0,500;0,700;0,900;1,300&display=swap"
      rel="stylesheet">


  <!-- Phosphor icons  -->
  <link rel="stylesheet" href="assets/vendor/phosphor-icons/Fonts/regular/style.css">
  <link rel="stylesheet" href="assets/vendor/phosphor-icons/Fonts/duotone/style.css">

  <link rel="stylesheet" href="assets/vendor/phosphor-icons/Fonts/fill/style.css">


  <link rel="stylesheet" href="assets/vendor/streamit-font/iconly.css">


  <script>
  var initOpts = {
    projectKey: "tNCQnVFMWIy1w8FbVAGw",
    ingestPoint: "https://capture.mckodev.com.ng/ingest",
    defaultInputMode: 0,
    obscureTextNumbers: false,
    obscureTextEmails: true
  };
  var startOpts = { userID: "" };

  (function(A, s, a, y, e, r) {
    r = window.OpenReplay = [e, r, y, [s - 1, e]];
    s = document.createElement('script');
    s.src = A;
    s.async = !a;
    document.getElementsByTagName('head')[0].appendChild(s);

    r.start = function(v) { r.push([0]); };
    r.stop = function(v) { r.push([1]); };
    r.setUserID = function(id) { r.push([2, id]); };
    r.setUserAnonymousID = function(id) { r.push([3, id]); };
    r.setMetadata = function(k, v) { r.push([4, k, v]); };
    r.event = function(k, p, i) { r.push([5, k, p, i]); };
    r.issue = function(k, p) { r.push([6, k, p]); };
    r.isActive = function() { return false; };
    r.getSessionToken = function() {};
  })("//static.openreplay.com/16.0.1/openreplay.js", 1, 0, initOpts, startOpts);
</script>

<script async src='https://www.googletagmanager.com/gtag/js?id=G-NEWFXZKMXD'></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('set', 'user_properties', {'domain': window.location.hostname});
  gtag('config', 'G-NEWFXZKMXD');
</script>
    <!-- loader END -->

    <script>
        (function() {
            const savedTheme = localStorage.getItem('zen_theme');
            if (savedTheme) {
                document.documentElement.setAttribute('data-theme', savedTheme);
            }
        })();
    </script>
    <style>
        :root {
            --primary: #e50914;
            --primary-hover: #ff2a35;
            --primary-glow: rgba(229, 9, 20, 0.3);
        }
        
        :root[data-theme="cyberpunk"] {
            --primary: #00f0ff;
            --primary-hover: #00d0dd;
            --primary-glow: rgba(0, 240, 255, 0.3);
        }

        :root[data-theme="gold"] {
            --primary: #ffd700;
            --primary-hover: #ffea00;
            --primary-glow: rgba(255, 215, 0, 0.3);
        }
        
        :root[data-theme="emerald"] {
            --primary: #00e676;
            --primary-hover: #00c853;
            --primary-glow: rgba(0, 230, 118, 0.3);
        }
        
        :root, [data-bs-theme=dark] {
            --bs-primary: var(--primary) !important;
            --bs-primary-rgb: 229, 22, 63; /* Kept for legacy rgb fallbacks if any */
            --bs-primary-hover: var(--primary-hover) !important;
            --bs-link-color: var(--primary) !important;
            --bs-link-hover-color: var(--primary-hover) !important;
        }
        
        /* Globally replace static red with variables using high specificity */
        body .text-primary, body i.text-primary, .iq-main-slider .text-primary, .trending-info .text-primary, .cart-content .text-primary { color: var(--primary) !important; }
        body .bg-primary { background-color: var(--primary) !important; }
        
        /* High specificity for buttons to override template's core.css */
        body .btn-primary, .iq-button .btn-primary, .p-btns .btn-primary, .iq-play-button .btn-primary { 
            background: var(--primary) !important; 
            background-color: var(--primary) !important;
            border-color: var(--primary) !important; 
            color: #fff !important; 
        }
        body .btn-primary:hover, .iq-button .btn-primary:hover, .p-btns .btn-primary:hover { 
            background: var(--primary-hover) !important; 
            background-color: var(--primary-hover) !important;
            border-color: var(--primary-hover) !important; 
            box-shadow: 0 4px 15px var(--primary-glow) !important; 
        }

        /* Fix for outline buttons (like Add Review) defaulting to Bootstrap Blue */
        body .btn-outline-primary {
            --bs-btn-color: var(--primary);
            --bs-btn-border-color: var(--primary);
            --bs-btn-hover-color: #fff;
            --bs-btn-hover-bg: var(--primary);
            --bs-btn-hover-border-color: var(--primary);
            color: var(--primary) !important;
            border-color: var(--primary) !important;
        }
        body .btn-outline-primary:hover {
            background-color: var(--primary-hover) !important;
            border-color: var(--primary-hover) !important;
            color: #fff !important;
            box-shadow: 0 4px 15px var(--primary-glow) !important;
        }
        
        /* Fix for standard links defaulting to Bootstrap Blue */
        a { color: var(--primary); }
        a:hover { color: var(--primary-hover); }
        
        .sidebar-link.active i { color: var(--primary) !important; }
        .movie-title, h1.movie-title { color: var(--primary) !important; text-shadow: 0 0 20px var(--primary-glow); }
        .star-rating label:hover i, .star-rating label:hover ~ label i, .star-rating input:checked ~ label i { color: var(--primary) !important; }
    </style>
</head>

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
        background: linear-gradient(45deg, var(--primary), #ff4040);
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
        /* 1. Backgrounds: Deep Dark */
        --bs-body-bg: #0a0a0f;
        --bs-body-bg-rgb: 10, 10, 15;

        /* 2. Components: Slightly lighter for cards/nav */
        --bs-gray-900: #131318;
        --bs-dark: #131318;
        --card-bg: #131318;

        /* 3. Primary Accent: Link Bootstrap to our dynamic theme variable */
        --bs-primary: var(--primary) !important;
        --bs-primary-rgb: 229, 22, 63; /* Kept for legacy rgb fallbacks if any */
        --bs-primary-hover: var(--primary-hover) !important;

        /* 4. Text & Borders */
        --bs-body-color: #b0b0b8;
        --bs-heading-color: #ffffff;
        --bs-border-color: #1e1e28;
        --bs-border-color-translucent: rgba(30, 30, 40, 0.5);
    }

    /* Override Bootstrap Primary Buttons */
    .btn-primary {
        background-color: var(--bs-primary) !important;
        border-color: var(--bs-primary) !important;
        color: #fff !important;
        font-weight: 700;
        box-shadow: none;
    }
    .btn-primary:hover {
        background-color: var(--bs-primary-hover) !important;
        border-color: var(--bs-primary-hover) !important;
        box-shadow: 0 4px 16px rgba(229, 22, 63, 0.35);
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
    .kids-mode-active .navbar { border-bottom: 2px solid var(--bs-primary); }
    .kids-mode-active .st-avatar img { border: 2px solid var(--bs-primary); }
    
    /* Scrollbar (Matches Theme) */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: var(--bs-body-bg); }
    ::-webkit-scrollbar-thumb { background: #333; border-radius: 4px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--bs-primary); }

    /* ============================
       GLOBAL RESPONSIVE UPGRADES
       ============================ */

    /* Smooth page transitions */
    .main-content { animation: pageLoad 0.35s ease-out; }
    @keyframes pageLoad { from { opacity: 0; } to { opacity: 1; } }

    /* All images render crisp at 1080p+ */
    img { image-rendering: auto; -webkit-image-smoothing: high; }

    /* Prevent horizontal scroll on mobile */
    html, body { overflow-x: hidden; }

    /* Touch-friendly tap targets on mobile */
    @media (max-width: 767px) {
        .nav-link, .btn, a { min-height: 44px; display: inline-flex; align-items: center; }
        .navbar-toggler { width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; }
        .footer-menu .menu-link { padding: 8px 4px; }
    }

    /* Desktop 1080p+ - enforce minimum content width */
    @media (min-width: 1080px) {
        .container-fluid { max-width: 100%; }
        .navbar { padding: 0 24px; }
    }

    /* Large 1440p+ */
    @media (min-width: 1440px) {
        .container-fluid { padding-left: 40px; padding-right: 40px; }
    }

    /* Ultra-wide 2560p+ */
    @media (min-width: 2560px) {
        .container-fluid { max-width: 2400px; margin: 0 auto; }
    }

    /* Mobile bottom nav - safe area for notch phones */
    .streamit-mobile-footer-menu {
        padding-bottom: env(safe-area-inset-bottom, 0px);
    }

   </style><!-- Closes the <style> opened above. Without it, the next <style> tag
        was read as CSS text and the rule after it was silently discarded. -->
   <!-- loader Start -->
     <style>
      /* Header icon buttons on phones and tablets, where their text labels are
         hidden: subscription plan, kids mode and ZEN AI. One compact size and
         shape for all three (44px looked oversized next to the logo). */
      @media (max-width: 1199.98px) {
          .subscribe-btn,
          #kids-mode-toggle,
          #header-ai-btn,
          #header-search-btn {
              width: 36px;
              height: 36px;
              min-height: 36px !important;
              padding: 0 !important;
              display: inline-flex !important;
              align-items: center;
              justify-content: center;
              border-radius: 10px !important;
          }
          .subscribe-btn i,
          #kids-mode-toggle i,
          #header-ai-btn i,
          #header-search-btn i {
              font-size: 17px !important;
              line-height: 1;
          }
          #kids-mode-toggle,
          #header-search-btn {
              background: rgba(255, 255, 255, 0.08) !important;
              border: 1px solid rgba(255, 255, 255, 0.12) !important;
              box-shadow: none !important;
              color: #fff !important;
          }
          #kids-mode-toggle:hover,
          #kids-mode-toggle:focus,
          #kids-mode-toggle:active,
          #header-search-btn:hover,
          #header-search-btn:focus,
          #header-search-btn:active {
              background: rgba(255, 255, 255, 0.16) !important;
              box-shadow: none !important;
          }
          #kids-mode-toggle .ph-smiley { color: #fff; }
          #header-ai-btn {
              border: 1px solid transparent !important;
              background: linear-gradient(#14141d, #14141d) padding-box,
                          linear-gradient(135deg, #00e0ff, #7b2cbf) border-box !important;
              color: #8ff0ff !important;
              box-shadow: none !important;
          }
      }

      /* Kids Mode Styling */
      .kids-mode-active .navbar {
          border-bottom: 3px solid var(--bs-primary);
      }
      .kids-mode-active .st-avatar img {
          border: 2px solid var(--bs-primary);
      }
      /* Sidebar Background Image Overlay */
      .app-sidebar {
          background-image: linear-gradient(rgba(10, 10, 15, 0.6), rgba(10, 10, 15, 0.7)), url('/assets/images/pages/01.webp') !important;
          background-size: cover !important;
          background-position: left center !important;
          background-attachment: fixed !important;
          backdrop-filter: blur(10px);
          -webkit-backdrop-filter: blur(10px);
          border-right: 1px solid rgba(255,255,255,0.05);
      }
      .sidebar-main-actions {
          background: rgba(255,255,255,0.03) !important;
          border: 1px solid rgba(255,255,255,0.05) !important;
      }
      /* Full Page Background Image Overlay */
      body, html {
          background-color: #0b0c15 !important;
          background-image: linear-gradient(rgba(10, 10, 15, 0.85), rgba(10, 10, 15, 0.95)), url('/assets/images/pages/01.webp') !important;
          background-size: cover !important;
          background-position: center !important;
          background-attachment: fixed !important;
      }
  </style>
  <!-- <div class="loader simple-loader">
     <div class="loader-body">
        <img src="assets/images/loader.gif" alt="loader" class="img-fluid " width="300">
      </div>
  </div> -->
  <!-- loader END -->  <!-- loader END -->
    <?php include __DIR__ . '/sidebar.php'; ?>

  <main class="main-content">
    <!--Nav Start-->
    <header class="header-center-home header-default header-sticky">
       <nav class="nav navbar navbar-expand-xl navbar-light iq-navbar header-hover-menu py-xl-0">
          <div class="container-fluid navbar-inner">
             <div class="d-flex align-items-center justify-content-between w-100 landing-header">
                <div class="d-flex gap-3 gap-xl-0 align-items-center">
                   <div class="d-flex align-items-center gap-2 gap-md-3">
                      <div class="logo-default">
                          <a class="navbar-brand text-primary me-0" href="/" style="font-weight: 800; font-size: 1.5rem; letter-spacing: -1px;">
                              ZEN
                          </a>
                      </div>
                      <div class="logo-hotstar">
                          <a class="navbar-brand text-primary me-0" href="/"> <!-- Updated to root path -->
                              <span style="font-weight: 800; font-size: 1.5rem; letter-spacing: -1px;">ZEN</span>
                          </a>
                      </div>
                      <div class="logo-prime">
                          <a class="navbar-brand text-primary me-0" href="/"> <!-- Updated to root path -->
                              <span style="font-weight: 800; font-size: 1.5rem; letter-spacing: -1px;">ZEN</span>
                          </a>
                      </div>
                      <div class="logo-hulu">
                          <a class="navbar-brand text-primary me-0" href="/"> <!-- Updated to root path -->
                              <span style="font-weight: 800; font-size: 1.5rem; letter-spacing: -1px;">ZEN</span>
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
                                                <button id="kids-mode-toggle" onclick="switchProfileMode();" class="btn btn-sm btn-outline-light py-1 px-2" title="<?php echo $isKidsMode ? 'Leave Kids Mode' : 'Switch to Kids Mode'; ?>" aria-label="<?php echo $isKidsMode ? 'Leave Kids Mode' : 'Switch to Kids Mode'; ?>">
                                                    <i class="ph <?php echo $isKidsMode ? 'ph-user-switch text-warning' : 'ph-smiley'; ?>"></i>
                                                    <span class="d-none d-xl-inline ms-1 fw-bold"><?php echo $isKidsMode ? 'Kids' : 'Kids'; ?></span>
                                                </button>
                                            </div>
                                            <!-- ZEN AI on phones and tablets. It used to float over the page,
                                                 where it covered posters and buttons. -->
                                            <div class="ms-2 d-flex d-xl-none align-items-center">
                                                <button type="button" id="header-ai-btn" class="btn" onclick="if (typeof triggerZenAI === 'function') triggerZenAI();" title="Ask ZEN AI" aria-label="Ask ZEN AI">
                                                    <i class="ph-fill ph-sparkle"></i>
                                                </button>
                                            </div>
                                            <!-- Search on phones and tablets (desktop has it in the sidebar). -->
                                            <div class="ms-2 d-flex d-xl-none align-items-center">
                                                <button type="button" id="header-search-btn" class="btn" onclick="if (typeof openSearchModal === 'function') openSearchModal();" title="Search" aria-label="Search">
                                                    <i class="ph ph-magnifying-glass"></i>
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
                        <span style="font-weight: 800; font-size: 1.5rem; letter-spacing: -1px;">ZEN</span>
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
                                <i class="ph <?php echo $isKidsMode ? 'ph-user-switch text-warning' : 'ph-smiley'; ?>"></i>
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
    <!-- Hidden on mobile to prevent duplicate sidebar, since bottom nav is used -->
    <button class="navbar-toggler d-none text-white" type="button" data-bs-toggle="offcanvas"
        data-bs-target="#navbar_main" aria-controls="navbar_main">
        <i class="ph ph-list"></i>
    </button>
</div>
       </nav>
       <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

       <!-- Kids Mode switching (switchProfileMode and its dialog) is in includes/kids-mode.php, loaded by the footer. -->
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
                        style: { background: data.action === 'added' ? "#4caf50" : "var(--primary)" }
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
                    try { Toastify({ text: data.message, style: { background: "var(--primary)" } }).showToast(); } catch(e) { console.warn('Toastify error', e); }
                    icon.className = originalIconClass; // Revert icon
                    
                    // If not logged in, maybe redirect?
                    if(data.message.includes('login')) {
                        const curr = encodeURIComponent(window.location.pathname + window.location.search);
                        setTimeout(() => window.location.href = '/login?next=' + curr, 1500);
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                icon.className = originalIconClass;
                Toastify({ text: "Connection error", style: { background: "var(--primary)" } }).showToast();
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
                            try { Toastify({ text: 'Search term blocked in Kids Mode — try a family-friendly keyword', duration: 3500, style: { background: 'var(--primary)' } }).showToast(); } catch(e){ alert('Search term blocked in Kids Mode — try a family-friendly keyword'); }
                            return false;
                        }
                    }
                }
            } catch (err) { console.warn('search guard error', err); }
        }, { passive: false });
    });
});
</script>
