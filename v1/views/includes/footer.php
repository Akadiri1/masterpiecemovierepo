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

<!-- Kids Mode: the switching dialog (PIN, first PIN, forgotten PIN, upgrade) -->
<?php include APP_PATH . '/views/includes/kids-mode.php'; ?>
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
  <!-- Theme script -->
  <script src="assets/js/streamit.js" defer></script>
  <script src="assets/js/swiper.js" defer></script>

<!-- Fullscreen Search Overlay -->
<style>
    #searchOverlay {
        position: fixed !important; top: 0; left: 0; width: 100vw; height: 100vh;
        background: rgba(8, 8, 12, 0.97); backdrop-filter: blur(20px);
        z-index: 2147483600; display: none !important; align-items: center; justify-content: center;
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
        // Close the menu first. It is stacked above everything else, so on
        // phones search used to open underneath it and looked broken.
        var menu = document.getElementById('appSidebar');
        if (menu) menu.classList.remove('mobile-open');
        var menuShade = document.getElementById('sidebarOverlay');
        if (menuShade) menuShade.classList.remove('active');
        var watchMenu = document.querySelector('.watch-sidebar.open');
        if (watchMenu) watchMenu.classList.remove('open');
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
<!-- Welcome tour for first-time visitors, built on Driver.js. The library is
     only downloaded for visitors who haven't seen the tour yet. -->
<style>
.driver-popover.zen-tour {
    background: #12121a !important;
    color: #fff !important;
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
    border-radius: 16px !important;
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.55) !important;
    padding: 20px !important;
    min-width: 260px !important;
    max-width: min(340px, calc(100vw - 32px)) !important;
    font-family: inherit !important;
}
.driver-popover.zen-tour .driver-popover-title {
    margin: 0 28px 6px 0 !important;
    color: #fff !important;
    font-family: inherit !important;
    font-size: 1.05rem !important;
    font-weight: 700 !important;
    line-height: 1.3 !important;
}
.driver-popover.zen-tour .driver-popover-description {
    margin: 0 !important;
    color: #a9a9b6 !important;
    font-family: inherit !important;
    font-size: 0.9rem !important;
    line-height: 1.55 !important;
}
.driver-popover.zen-tour .driver-popover-close-btn {
    top: 10px !important;
    right: 10px !important;
    width: 30px !important;
    height: 30px !important;
    border-radius: 8px !important;
    color: #7a7a86 !important;
    font-size: 20px !important;
}
.driver-popover.zen-tour .driver-popover-close-btn:hover { color: #fff !important; background: rgba(255, 255, 255, 0.08) !important; }
.driver-popover.zen-tour .driver-popover-footer { margin-top: 18px !important; gap: 12px; }
.driver-popover.zen-tour .driver-popover-progress-text { color: #71717d !important; font-size: 0.78rem !important; font-weight: 600 !important; }
.driver-popover.zen-tour .driver-popover-navigation-btns { gap: 8px !important; }
.driver-popover.zen-tour .driver-popover-footer button {
    margin: 0 !important;
    padding: 10px 16px !important;
    border-radius: 10px !important;
    font-family: inherit !important;
    font-size: 0.85rem !important;
    font-weight: 600 !important;
    line-height: 1 !important;
    text-shadow: none !important;
    transition: background 0.2s, border-color 0.2s, color 0.2s !important;
}
.driver-popover.zen-tour .driver-popover-prev-btn { background: transparent !important; border: 1px solid rgba(255, 255, 255, 0.14) !important; color: #d4d4dc !important; }
.driver-popover.zen-tour .driver-popover-prev-btn:hover { background: rgba(255, 255, 255, 0.06) !important; color: #fff !important; }
.driver-popover.zen-tour .driver-popover-next-btn { background: var(--primary, #e50914) !important; border: 1px solid var(--primary, #e50914) !important; color: #fff !important; }
.driver-popover.zen-tour .driver-popover-next-btn:hover { filter: brightness(1.1); }
.driver-popover.zen-tour .driver-popover-arrow-side-left.driver-popover-arrow { border-left-color: #12121a !important; }
.driver-popover.zen-tour .driver-popover-arrow-side-right.driver-popover-arrow { border-right-color: #12121a !important; }
.driver-popover.zen-tour .driver-popover-arrow-side-top.driver-popover-arrow { border-top-color: #12121a !important; }
.driver-popover.zen-tour .driver-popover-arrow-side-bottom.driver-popover-arrow { border-bottom-color: #12121a !important; }
.zen-tour-mark {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    margin-bottom: 14px;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--primary, #e50914), #7b2cbf);
    box-shadow: 0 10px 24px -10px var(--primary, #e50914);
    color: #fff;
    font-size: 0.9rem;
    font-weight: 900;
    letter-spacing: -0.5px;
}
.zen-tour-heading { display: block; font-size: 1.2rem; }
</style>

<script>
(function () {
    var seen;
    try { seen = localStorage.getItem('zen_tour_completed'); } catch (e) { seen = 'unavailable'; }
    // Pages can opt out with $suppressSiteTour (e.g. the admin dashboard).
    if (seen || window.self !== window.top || <?php echo !empty($suppressSiteTour) ? 'true' : 'false'; ?>) return;

    // Each stop points at the first of its elements that is actually on
    // screen, so one tour fits the phone layout (header buttons, bottom bar)
    // and the desktop one (sidebar). A stop with nothing to point at is
    // skipped instead of floating over nothing.
    var STOPS = [
        { el: ['.streamit-mobile-footer-menu a[href="/view-all?type=movie"]', '#appSidebar a[href="/view-all?type=movie"]'],
          title: 'Movies and TV shows', text: 'Browse everything from here, and narrow any list down by genre.' },
        { el: ['.streamit-mobile-footer-menu [onclick*="toggleMobileSidebar"]', '#appSidebar [onclick*="openSearchModal"]'],
          title: 'Search and menu', text: 'Find any movie, show or actor. Categories and colour themes are in the menu too.' },
        { el: ['#header-ai-btn', '.zen-ai-float'],
          title: 'Ask ZEN AI', text: 'Say what you are in the mood for and get picks, trivia or titles like the ones you love.' },
        { el: ['#kids-mode-toggle'],
          title: 'Kids Mode', text: 'Switch to a family-friendly catalogue whenever children are watching.' },
        { el: ['.streamit-mobile-footer-menu a[href="/profile"]', '#appSidebar .sidebar-user'],
          title: 'Your profile', text: 'Your watchlist, membership and parental controls live here.' }
    ];

    function onScreen(selectors) {
        for (var i = 0; i < selectors.length; i++) {
            var found = document.querySelectorAll(selectors[i]);
            for (var j = 0; j < found.length; j++) {
                var box = found[j].getBoundingClientRect();
                if (box.width > 0 && box.height > 0 && box.bottom > 0 && box.right > 0 &&
                    box.top < window.innerHeight && box.left < window.innerWidth &&
                    getComputedStyle(found[j]).visibility !== 'hidden') {
                    return found[j];
                }
            }
        }
        return null;
    }

    function start() {
        if (!window.driver || !window.driver.js) return;

        var steps = [{
            popover: {
                title: '<span class="zen-tour-mark">ZEN</span><span class="zen-tour-heading">Welcome to ZEN</span>',
                description: 'Movies and shows, all in one place. Here is a quick look around; it takes about 20 seconds.'
            }
        }];
        STOPS.forEach(function (stop) {
            var target = onScreen(stop.el);
            if (target) steps.push({ element: target, popover: { title: stop.title, description: stop.text } });
        });

        var tour = window.driver.js.driver({
            steps: steps,
            popoverClass: 'zen-tour',
            showProgress: true,
            progressText: '{{current}} of {{total}}',
            nextBtnText: 'Next',
            prevBtnText: 'Back',
            doneBtnText: 'Start watching',
            overlayColor: '#05050a',
            overlayOpacity: 0.7,
            stagePadding: 6,
            stageRadius: 12,
            smoothScroll: true,
            allowClose: true,
            onDestroyed: function () {
                try { localStorage.setItem('zen_tour_completed', 'true'); } catch (e) {}
            }
        });
        tour.drive();
    }

    function load() {
        var css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = 'https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.css';
        document.head.appendChild(css);

        var script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.js.iife.js';
        script.onload = start;
        document.head.appendChild(script);
    }

    // Start once the page has settled, so each stop is in its final place.
    if (document.readyState === 'complete') {
        setTimeout(load, 1200);
    } else {
        window.addEventListener('load', function () { setTimeout(load, 1200); });
    }
})();
</script>
</body>
</html>