<?php include APP_PATH . '/views/zen-ai.php'; ?>

<?php include APP_PATH . '/views/includes/mobile-footer.php'; ?>

<!-- toggleMobileSidebar is now defined in sidebar.php -->

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
<?php include APP_PATH . '/views/includes/theme-modal.php'; ?>
<!-- Driver.js Library -->
<script src="https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.js.iife.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.css"/>

<style>
/* Custom Driver.js Styling for Premium Dark Mode */
.driver-popover {
    background: #14141d !important;
    color: #fff !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    border-radius: 12px !important;
    box-shadow: 0 15px 40px rgba(0,0,0,0.6) !important;
    font-family: inherit !important;
}
.driver-popover-title {
    color: var(--primary, #e50914) !important;
    font-size: 1.2rem !important;
    font-weight: 700 !important;
}
.driver-popover-description {
    color: #bbb !important;
    font-size: 0.95rem !important;
    line-height: 1.5 !important;
}
.driver-popover-footer button {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: #fff !important;
    text-shadow: none !important;
    border-radius: 6px !important;
    transition: 0.2s ease !important;
}
.driver-popover-footer button:hover {
    background: var(--primary, #e50914) !important;
    border-color: var(--primary, #e50914) !important;
}
.driver-popover-progress-text {
    color: #666 !important;
}
.driver-popover-arrow {
    border-color: #14141d !important;
}
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Only run if not in an iframe and tour hasn't been completed
    if (window.self === window.top && !localStorage.getItem('zen_tour_completed')) {
        setTimeout(() => {
            const driver = window.driver.js.driver;
            const tour = driver({
                showProgress: true,
                animate: true,
                allowClose: true,
                steps: [
                    {
                        popover: {
                            title: 'Welcome to Masterpiece Movie! 🍿',
                            description: 'Let\'s take a quick tour of your new premium streaming hub. It will only take a few seconds!',
                            side: "over",
                            align: 'center'
                        }
                    },
                    {
                        element: '.search-box', 
                        popover: {
                            title: 'Global Search',
                            description: 'Instantly find your favorite movies, actors, or directors from anywhere on the site.',
                            side: "bottom",
                            align: 'center'
                        }
                    },
                    {
                        element: '#zen-ai-toggle-btn', 
                        popover: {
                            title: 'Meet ZEN AI ✨',
                            description: 'Your personal AI assistant. Ask for recommendations, movie facts, or just have a chat!',
                            side: "left",
                            align: 'center'
                        }
                    },
                    {
                        element: '#movies', 
                        popover: {
                            title: 'Explore Categories',
                            description: 'Browse through thousands of titles across diverse genres and international categories.',
                            side: "bottom",
                            align: 'start'
                        }
                    },
                    {
                        element: '#itemdropdown1', 
                        popover: {
                            title: 'Profile & Kids Mode',
                            description: 'Manage your profile or switch to a strict Kids Mode to ensure a safe browsing environment.',
                            side: "left",
                            align: 'start'
                        }
                    }
                ],
                onDestroyStarted: () => {
                    if (tour.hasNextStep() || !tour.hasNextStep()) {
                        localStorage.setItem('zen_tour_completed', 'true');
                        tour.destroy();
                    }
                }
            });
            
            tour.drive();
        }, 1500); // 1.5s delay to let animations finish loading
    }
});
</script>
</body>
</html>