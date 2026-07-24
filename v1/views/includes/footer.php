<?php include APP_PATH . '/views/zen-ai.php'; ?>

<style>
    /* Modern Mobile Bottom Nav */
    .streamit-mobile-footer-menu {
        background: rgba(11, 12, 21, 0.95);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-top: 1px solid rgba(255,255,255,0.06);
        box-shadow: 0 -4px 20px rgba(0,0,0,0.4);
    }
    .footer-menu { padding: 8px 0; }
    .footer-menu-item { flex: 1; text-align: center; }
    .footer-menu-item .menu-link {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
        padding: 6px 0;
        color: #888;
        text-decoration: none;
        font-size: 11px;
        font-weight: 500;
        transition: color 0.2s ease, transform 0.2s ease;
        position: relative;
    }
    .footer-menu-item .menu-link i { font-size: 22px; }
    .footer-menu-item .menu-link:hover,
    .footer-menu-item .menu-link.active {
        color: var(--bs-primary, #e5163f);
        transform: translateY(-2px);
    }
    /* Active indicator dot */
    .footer-menu-item .menu-link.active::after {
        content: '';
        position: absolute;
        bottom: 0;
        width: 4px;
        height: 4px;
        border-radius: 50%;
        background: var(--bs-primary, #e5163f);
    }

    /* Mobile footer spacing for content above */
    @media (max-width: 991px) {
        .main-content { padding-bottom: 70px; }
    }
    /* Hide mobile footer on desktop (sidebar handles navigation) */
    @media (min-width: 1200px) {
        .streamit-mobile-footer-menu { display: none !important; }
    }
</style>

<div class="streamit-mobile-footer-menu" aria-label="Mobile Footer Navigation">
    <ul class="footer-menu list-inline d-flex align-items-center justify-content-between m-0">
        <li class="footer-menu-item">
            <a href="/view-all?type=movie" class="menu-link font-size-12">
                <i class="ph ph-film-reel d-block text-center"></i>
                Movies</a>
        </li>
        <li class="footer-menu-item">
            <a href="/view-all?type=fresh" class="menu-link font-size-12">
                <i class="ph ph-monitor-play d-block text-center"></i>
                Videos</a>
        </li>
        <li class="footer-menu-item">
            <a href="/" class="menu-link font-size-12">
                <i class="fa fa-home d-block text-center"></i>
                Home</a>
        </li>
        <li class="footer-menu-item">
            <a href="/view-all?type=tv" class="menu-link font-size-12">
                <i class="ph ph-television d-block text-center"></i>
                TV Shows</a>
        </li>
        <li class="footer-menu-item">
            <a href="/profile" class="menu-link font-size-12">
                <i class="ph ph-user d-block text-center"></i>
                Profile</a>
        </li>
    </ul>
</div>

<script>
// Global: Replace any broken images with placeholder
(function() {
    const portraitPlaceholder = '/assets/images/media/placeholder-portrait.svg';
    const landscapePlaceholder = '/assets/images/media/placeholder.svg';

    function handleBrokenImage(img) {
        if (img.dataset.placeholderSet) return; // Prevent infinite loop
        img.dataset.placeholderSet = 'true';
        // Determine if portrait or landscape based on parent aspect ratio
        const parent = img.parentElement;
        const isPortrait = parent && (parent.offsetHeight > parent.offsetWidth);
        img.src = isPortrait ? portraitPlaceholder : landscapePlaceholder;
        img.style.objectFit = 'cover';
        img.style.background = '#1a1a2e';
    }

    // Catch errors on existing images
    document.querySelectorAll('img').forEach(img => {
        if (img.complete && img.naturalWidth === 0) handleBrokenImage(img);
        img.addEventListener('error', () => handleBrokenImage(img));
    });

    // Catch errors on dynamically added images
    document.addEventListener('error', (e) => {
        if (e.target.tagName === 'IMG') handleBrokenImage(e.target);
    }, true);
})();

// --- Sidebar Logic ---
(function() {
    const sidebar = document.getElementById('appSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const collapseBtn = document.getElementById('sidebarCollapseBtn');
    const toggler = document.querySelector('.navbar-toggler');
    const genreToggle = document.getElementById('genreToggle');
    const genreSubmenu = document.getElementById('genreSubmenu');

    // Mobile: open sidebar from hamburger
    if (toggler && sidebar) {
        toggler.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            sidebar.classList.add('mobile-open');
            if (overlay) overlay.classList.add('active');
        });
    }

    // Close on overlay click
    if (overlay) {
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
        });
    }

    // Desktop: collapse/expand sidebar
    if (collapseBtn && sidebar) {
        // Load saved state
        if (localStorage.getItem('sidebar-collapsed') === '1') {
            sidebar.classList.add('collapsed');
            document.body.classList.add('sidebar-collapsed');
        }
        collapseBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            document.body.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebar-collapsed', sidebar.classList.contains('collapsed') ? '1' : '0');
        });
    }

    // Genre submenu toggle
    if (genreToggle && genreSubmenu) {
        genreToggle.addEventListener('click', () => {
            genreToggle.classList.toggle('expanded');
            genreSubmenu.classList.toggle('open');
        });
    }

    // Highlight active sidebar link based on URL
    const path = window.location.pathname + window.location.search;
    document.querySelectorAll('.sidebar-link').forEach(link => {
        const href = link.getAttribute('href');
        if (href && href !== 'javascript:void(0)' && href !== '#' && path.startsWith(href) && href !== '/') {
            link.classList.add('active');
        }
    });
})();

// Highlight active mobile footer item
(function() {
    const path = window.location.pathname;
    document.querySelectorAll('.footer-menu-item .menu-link').forEach(link => {
        const href = link.getAttribute('href');
        if (href === path || (href === '/' && path === '/home')) {
            link.classList.add('active');
        }
    });
})();
</script>
<!-- ==========================================
     MISSING DIV: BACK TO TOP BUTTON
     ========================================== -->
<div id="back-to-top" style="display: none;">
    <a class="p-0 btn bg-primary btn-sm position-fixed top border-0 rounded-circle" id="top" href="#top">
        <i class="fa-solid fa-chevron-up"></i>
    </a>
</div>
<!-- ========================================== -->

<!-- Parental PIN Bootstrap Modal (used by header switchProfileMode) -->
<div class="modal fade" id="parental-pin-modal-bs" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-white" style="border:1px solid rgba(255,255,255,0.06);">
      <div class="modal-header border-0">
        <h5 class="modal-title">Parental PIN required</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2 text-muted">Please enter your parental PIN to confirm switching Kids Mode.</p>
        <input id="parental-pin-input" type="password" inputmode="numeric" pattern="[0-9]*" maxlength="8" placeholder="Enter 4-8 digit PIN" class="form-control mb-3" style="background:#111; border:1px solid rgba(255,255,255,0.06); color:#fff; padding:10px;">
      </div>
      <div class="modal-footer border-0">
        <button id="parental-pin-cancel" type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
        <button id="parental-pin-submit" type="button" class="btn btn-primary">Confirm</button>
      </div>
    </div>
  </div>
      </div>

      <!-- Set Parental PIN Modal (shown when enabling Kids Mode but no PIN exists) -->
      <div class="modal fade" id="parental-pin-setup-modal-bs" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content bg-dark text-white" style="border:1px solid rgba(255,255,255,0.06);">
            <div class="modal-header border-0">
              <h5 class="modal-title">Set a Parental PIN</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <p class="mb-2 text-muted">To enable Kids Mode you must set a Parental PIN. This prevents kids from switching back to the parent profile.</p>
              <input id="parental-new-pin" type="password" inputmode="numeric" pattern="[0-9]*" maxlength="8" placeholder="Enter PIN (4-8 digits)" class="form-control mb-3" style="background:#111; border:1px solid rgba(255,255,255,0.06); color:#fff; padding:10px;">
              <input id="parental-confirm-pin" type="password" inputmode="numeric" pattern="[0-9]*" maxlength="8" placeholder="Confirm PIN" class="form-control mb-3" style="background:#111; border:1px solid rgba(255,255,255,0.06); color:#fff; padding:10px;">
            </div>
            <div class="modal-footer border-0">
              <button id="parental-pin-setup-cancel" type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
              <button id="parental-pin-setup-submit" type="button" class="btn btn-primary">Save & Enable Kids Mode</button>
            </div>
          </div>
        </div>
      </div>
</div>

  <!-- Library Bundle Script -->
  <script src="assets/js/core/libs.min.js"></script>
  <!-- Plugin Scripts -->
  <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

  <!-- Sweet-alert Script -->
  <script src="assets/vendor/sweetalert2/sweetalert2.min.js" async></script>
  <script src="assets/js/plugins/sweet-alert.js" defer></script>
  
  <!-- SwiperSlider Script -->
  <script src="assets/vendor/swiperSlider/swiper.min.js"></script>
  
  <!-- Lodash Utility -->
  <script src="assets/vendor/lodash/lodash.min.js"></script>
  <!-- External Library Bundle Script -->
  <script src="assets/js/core/external.min.js"></script>
  <!-- countdown Script -->
  <script src="assets/js/plugins/countdown.js"></script>
  <!-- utility Script -->
  <script src="assets/js/utility.js"></script>
  <!-- Setting Script -->
  <script src="assets/js/setting.js"></script>
  <script src="assets/js/setting-init.js" defer></script>
  <!-- Streamit Script -->
  <script src="assets/js/streamit.js" defer></script>
  <script src="assets/js/swiper.js" defer></script>

<!-- Fullscreen Search Overlay -->
<style>
    #searchOverlay {
        position: fixed !important; top: 0; left: 0; width: 100vw; height: 100vh;
        background: rgba(8, 8, 12, 0.97); backdrop-filter: blur(20px);
        z-index: 999999; display: none !important; align-items: center; justify-content: center;
        flex-direction: column;
    }
    #searchOverlay.active { display: flex !important; }
    #searchOverlay .search-close-btn {
        position: absolute; top: 25px; right: 30px; background: rgba(255,255,255,0.06);
        border: 1px solid rgba(255,255,255,0.1); width: 48px; height: 48px;
        border-radius: 50%; color: #aaa; font-size: 1.5rem; cursor: pointer;
        transition: 0.25s; display: flex; align-items: center; justify-content: center;
    }
    #searchOverlay .search-close-btn:hover { color: #fff; background: rgba(255,255,255,0.15); transform: rotate(90deg); }
    #searchOverlay .search-overlay-content { width: 100%; max-width: 700px; padding: 20px; text-align: center; }
    #searchOverlay .search-overlay-title { font-size: 1.8rem; font-weight: 700; color: #fff; margin-bottom: 30px; letter-spacing: -0.5px; }
    #searchOverlay .search-overlay-form {
        display: flex; align-items: center; background: rgba(255,255,255,0.05);
        border-radius: 16px; padding: 14px 22px; border: 1px solid rgba(255,255,255,0.08);
        transition: all 0.3s ease; box-shadow: 0 8px 30px rgba(0,0,0,0.4);
    }
    #searchOverlay .search-overlay-form:focus-within { border-color: var(--primary); background: rgba(255,255,255,0.08); box-shadow: 0 12px 40px rgba(229, 9, 20, 0.12); }
    #searchOverlay .search-icon { font-size: 1.6rem; color: #666; margin-right: 15px; flex-shrink: 0; }
    #searchOverlay .search-overlay-form:focus-within .search-icon { color: var(--primary); }
    #searchOverlay .search-overlay-form input {
        flex: 1; background: transparent !important; border: none !important; color: #fff !important;
        font-size: 1.3rem; font-weight: 400; outline: none !important; width: 100%;
        box-shadow: none !important; padding: 8px 0 !important;
    }
    #searchOverlay .search-overlay-form input::placeholder { color: #555; }
    #searchOverlay .search-submit-btn {
        background: var(--primary); color: #fff; border: none;
        padding: 12px 28px; border-radius: 10px; font-size: 1rem; font-weight: 700;
        cursor: pointer; transition: 0.2s; margin-left: 12px; flex-shrink: 0;
    }
    #searchOverlay .search-submit-btn:hover { background: #ff2a35; }
    #searchOverlay .search-overlay-hint { margin-top: 20px; color: #444; font-size: 0.85rem; }
    #searchOverlay .search-overlay-hint kbd { background: rgba(255,255,255,0.08); padding: 3px 8px; border-radius: 4px; font-size: 0.8rem; color: #888; border: 1px solid rgba(255,255,255,0.1); }
</style>

<div id="searchOverlay" class="search-overlay">
    <button class="search-close-btn" onclick="closeSearchModal()"><i class="ph ph-x"></i></button>
    <div class="search-overlay-content">
        <div class="search-overlay-title">What do you want to watch?</div>
        <form action="/view-all" method="GET" class="search-overlay-form">
            <i class="ph ph-magnifying-glass search-icon"></i>
            <input type="text" name="search" id="overlaySearchInput" placeholder="Search movies, TV shows, actors..." autocomplete="off" required>
            <button type="submit" class="search-submit-btn">Search</button>
        </form>
        <div class="search-overlay-hint">Press <kbd>Esc</kbd> to close</div>
    </div>
</div>

<script>
    // Premium Search Modal
    function openSearchModal() {
        document.getElementById('searchOverlay').classList.add('active');
        setTimeout(function(){ document.getElementById('overlaySearchInput').focus(); }, 50);
    }
    function closeSearchModal() {
        document.getElementById('searchOverlay').classList.remove('active');
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeSearchModal();
    });

    // Sidebar Close Button
    var sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
    if (sidebarCloseBtn) {
        sidebarCloseBtn.addEventListener('click', function() {
            var sidebar = document.getElementById('appSidebar');
            var overlay = document.getElementById('sidebarOverlay');
            if (sidebar) {
                sidebar.classList.remove('mobile-open');
                sidebar.classList.toggle('collapsed');
            }
            if (overlay) overlay.classList.remove('active');
        });
    }
</script>
<!-- Theme Modal (Color House) -->
<div id="themeModal" class="theme-overlay" style="display: none;">
    <div class="theme-modal-content">
        <div class="theme-modal-header">
            <h3><i class="ph ph-sparkle text-primary"></i> Color House</h3>
            <button class="theme-close-btn" onclick="closeThemeModal()"><i class="ph ph-x"></i></button>
        </div>
        <div class="theme-grid">
            <div class="theme-card" onclick="setTheme('')" data-theme="">
                <div class="theme-color-preview" style="background: #e50914;"></div>
                <span>Ruby Cinematic</span>
            </div>
            <div class="theme-card" onclick="setTheme('cyberpunk')" data-theme="cyberpunk">
                <div class="theme-color-preview" style="background: #00f0ff;"></div>
                <span>Neon Cyberpunk</span>
            </div>
            <div class="theme-card" onclick="setTheme('gold')" data-theme="gold">
                <div class="theme-color-preview" style="background: #ffd700;"></div>
                <span>Midnight Gold</span>
            </div>
            <div class="theme-card" onclick="setTheme('emerald')" data-theme="emerald">
                <div class="theme-color-preview" style="background: #00e676;"></div>
                <span>Emerald Aurora</span>
            </div>
        </div>
    </div>
</div>

<!-- Floating Theme Switcher Button -->
<div class="theme-switcher-float" onclick="openThemeModal()">
    <i class="ph ph-palette text-primary"></i>
</div>

<style>
/* Floating Button */
.theme-switcher-float {
    position: fixed; bottom: 100px; right: 30px; width: 50px; height: 50px;
    z-index: 99999999 !important; cursor: pointer; pointer-events: auto;
    display: flex; align-items: center; justify-content: center;
    background: rgba(11, 12, 21, 0.85); backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 50%;
    box-shadow: 0 8px 25px rgba(0,0,0,0.5);
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.theme-switcher-float:hover {
    transform: scale(1.15) rotate(15deg);
    border-color: var(--primary);
    box-shadow: 0 10px 30px var(--primary-glow);
}
.theme-switcher-float i { font-size: 22px; transition: 0.3s; pointer-events: none; }

/* Theme Modal Styles */
.theme-overlay {
    position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(8, 8, 12, 0.85); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px);
    z-index: 99999999 !important; display: flex; align-items: center; justify-content: center;
}
.theme-modal-content {
    background: rgba(20, 20, 25, 0.95); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 20px; width: 90%; max-width: 500px; padding: 30px;
    box-shadow: 0 20px 50px rgba(0,0,0,0.5);
    animation: themeModalIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
@keyframes themeModalIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.theme-modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 25px; }
.theme-modal-header h3 { margin: 0; font-size: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }
.theme-close-btn { background: rgba(255,255,255,0.05); border: none; color: #aaa; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
.theme-close-btn:hover { background: rgba(255,255,255,0.1); color: #fff; transform: rotate(90deg); }

.theme-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
.theme-card {
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);
    padding: 20px; border-radius: 16px; cursor: pointer; transition: 0.2s;
    display: flex; flex-direction: column; align-items: center; gap: 12px;
}
.theme-card:hover { background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.15); transform: translateY(-3px); }
.theme-card.active { border-color: var(--primary); background: rgba(255,255,255,0.05); box-shadow: 0 0 20px var(--primary-glow); }
.theme-color-preview { width: 40px; height: 40px; border-radius: 50%; box-shadow: 0 4px 10px rgba(0,0,0,0.5); }
.theme-card span { font-weight: 600; font-size: 0.95rem; color: #eee; }
</style>

<script>
function openThemeModal() {
    document.getElementById('themeModal').style.display = 'flex';
    updateActiveThemeCard();
}

function closeThemeModal() {
    document.getElementById('themeModal').style.display = 'none';
}

function setTheme(themeName) {
    if (themeName) {
        document.documentElement.setAttribute('data-theme', themeName);
        localStorage.setItem('zen_theme', themeName);
    } else {
        document.documentElement.removeAttribute('data-theme');
        localStorage.removeItem('zen_theme');
    }
    updateActiveThemeCard();
}

function updateActiveThemeCard() {
    const currentTheme = localStorage.getItem('zen_theme') || '';
    document.querySelectorAll('.theme-card').forEach(card => {
        if (card.getAttribute('data-theme') === currentTheme) {
            card.classList.add('active');
        } else {
            card.classList.remove('active');
        }
    });
}
</script>
</body>
</html>