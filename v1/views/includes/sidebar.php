<?php
$isKidsMode = $_SESSION['is_kids_mode'] ?? false;
$displayName = $_SESSION['firstName'] ?? $_SESSION['username'] ?? 'Guest';
$avatarPath = $_SESSION['avatar_url'] ?? 'assets/images/user/user.jpg';
$current_plan = $_SESSION['plan_name'] ?? 'Free';
?>
<style>
    /* ============================================
       SIDEBAR NAVIGATION SYSTEM
       ============================================ */

    .app-sidebar {
        position: fixed; top: 0; left: 0; bottom: 0;
        width: 240px; 
        background-color: #0b0c15;
        background-image: linear-gradient(rgba(10, 10, 15, 0.85), rgba(10, 10, 15, 0.95)), url('/assets/images/pages/01.webp');
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
        border-right: 1px solid rgba(255,255,255,0.05);
        z-index: 1040; display: flex; flex-direction: column;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
    }

    .sidebar-brand {
        padding: 24px 20px; display: flex; align-items: center;
        justify-content: flex-start;
        flex-shrink: 0;
    }
    .sidebar-brand .logo-text {
        font-size: 1.6rem; font-weight: 800; color: #fff;
        letter-spacing: -0.5px; text-decoration: none;
        display: flex; align-items: center;
    }
    .sidebar-brand .logo-text span { color: #fff; font-weight: 400; }
    
    .sidebar-collapse-btn {
        background: none; border: none; color: #666; font-size: 1.1rem;
        cursor: pointer; padding: 6px; border-radius: 6px;
        transition: all 0.2s; display: none; margin-left: auto;
    }
    .sidebar-collapse-btn:hover { color: #fff; background: rgba(255,255,255,0.05); }
    @media (min-width: 1200px) { .sidebar-collapse-btn { display: flex; } }

    .sidebar-nav {
        flex: 1; overflow-y: auto; padding: 0 12px 12px;
        scrollbar-width: none;
    }
    .sidebar-nav::-webkit-scrollbar { display: none; }

    .sidebar-section-label {
        font-size: 0.65rem; font-weight: 600; text-transform: uppercase;
        letter-spacing: 0.5px; color: #555; padding: 18px 20px 8px;
        user-select: none;
    }

    .sidebar-link {
        display: flex; align-items: center; gap: 14px;
        padding: 12px 16px; color: #aaa; text-decoration: none;
        font-size: 0.95rem; font-weight: 500;
        border-radius: 10px;
        transition: all 0.2s ease; margin-bottom: 4px;
        white-space: nowrap;
    }
    .sidebar-link i { font-size: 1.25rem; width: 22px; text-align: center; flex-shrink: 0; color: #aaa;}
    .sidebar-link:hover { color: #fff; background: rgba(255,255,255,0.05); }
    .sidebar-link:hover i { color: #fff; }
    .sidebar-link.active {
        color: #fff; background: #222222; font-weight: 600;
    }
    .sidebar-link.active i { color: #fff; }

    .sidebar-main-actions {
        background: #1c1c1c; border-radius: 12px; padding: 8px; margin-bottom: 10px;
    }

    .sidebar-submenu { max-height: 0; overflow: hidden; transition: max-height 0.3s ease; }
    .sidebar-submenu.open { max-height: 500px; }
    .sidebar-submenu .sidebar-link { padding-left: 48px; font-size: 0.85rem; }
    .sidebar-link .chevron { margin-left: auto; font-size: 0.7rem; transition: transform 0.2s; }
    .sidebar-link.expanded .chevron { transform: rotate(180deg); }

    .sidebar-footer {
        padding: 12px; border-top: 1px solid rgba(255,255,255,0.02);
        flex-shrink: 0;
    }
    .sidebar-user {
        display: flex; align-items: center; gap: 12px; padding: 8px 12px;
        border-radius: 10px; cursor: pointer; transition: background 0.2s;
        text-decoration: none;
    }
    .sidebar-user:hover { background: rgba(255,255,255,0.05); }
    .sidebar-user img { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; }
    .sidebar-user-info { overflow: hidden; }
    .sidebar-user-name { color: #ddd; font-size: 0.85rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .sidebar-user-plan { color: #666; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; }

    /* Push main content right on desktop */
    @media (min-width: 1200px) {
        .main-content { margin-left: 240px; transition: margin-left 0.3s ease; }
        .iq-navbar { display: none !important; }
        
        .app-sidebar.collapsed { width: 80px; }
        .app-sidebar.collapsed .sidebar-link span,
        .app-sidebar.collapsed .sidebar-section-label,
        .app-sidebar.collapsed .sidebar-user-info,
        .app-sidebar.collapsed .sidebar-brand .logo-text,
        .app-sidebar.collapsed .sidebar-submenu,
        .app-sidebar.collapsed .chevron { display: none; }
        .app-sidebar.collapsed .sidebar-main-actions { padding: 0; background: transparent; }
        .app-sidebar.collapsed .sidebar-link { justify-content: center; padding: 12px 0; border-radius: 12px;}
        .app-sidebar.collapsed .sidebar-brand { justify-content: center; padding: 24px 8px; }
        .app-sidebar.collapsed .sidebar-user { justify-content: center; }
        .app-sidebar.collapsed ~ .main-content,
        body.sidebar-collapsed .main-content { margin-left: 80px !important; }
    }

    /* Mobile: sidebar slides in from left */
    @media (max-width: 1199px) {
        .app-sidebar {
            transform: translateX(-100%);
            width: 100vw; max-width: 100vw;
            box-shadow: none;
            z-index: 999999999 !important;
            background-color: #0b0c15 !important;
            background-image: linear-gradient(rgba(10, 10, 15, 0.85), rgba(10, 10, 15, 0.95)), url('/assets/images/pages/01.webp') !important;
            background-size: cover !important;
            background-position: center !important;
            background-attachment: fixed !important;
        }
        .app-sidebar.mobile-open { transform: translateX(0); }
        .main-content { margin-left: 0 !important; }
    }

    /* Broken image fallback styling */
    img[data-placeholder] { background: #1a1a2e; }

    /* Smooth hover transitions everywhere */
    a, .btn, .nav-link, .iq-card, img {
        transition-property: transform, opacity, box-shadow, color, background-color, border-color;
        transition-duration: 0.25s;
        transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    }
</style>

<!-- ========== SIDEBAR NAVIGATION ========== -->
<div id="sidebarOverlay"></div>
<aside class="app-sidebar" id="appSidebar">
    <div class="sidebar-brand" style="display:flex; align-items:center; justify-content:space-between;">
        <a href="/" class="logo-text">ZEN</a>
        <button id="sidebarCloseBtn" style="background:transparent; border:none; color:#aaa; font-size:1.5rem; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:0.2s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#aaa'"><i class="ph ph-x"></i></button>
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-main-actions">
            <a href="/" class="sidebar-link <?php echo ($_SERVER['REQUEST_URI'] === '/' || $_SERVER['REQUEST_URI'] === '/home') ? 'active' : ''; ?>">
                <i class="ph ph-house"></i><span>Home</span>
            </a>
            <a href="javascript:void(0)" class="sidebar-link" onclick="openSearchModal();">
                <i class="ph ph-magnifying-glass"></i><span>Search</span>
            </a>
            
            <!-- ALL CATEGORIES / GENRES DROPDOWN -->
            <div class="sidebar-dropdown">
                <a href="javascript:void(0)" class="sidebar-link" data-bs-toggle="collapse" data-bs-target="#sidebarCategories" aria-expanded="false">
                    <i class="ph ph-list-dashes"></i><span>All Categories</span>
                    <i class="ph ph-caret-down chevron ms-auto" style="font-size: 0.8rem;"></i>
                </a>
                <div class="collapse" id="sidebarCategories">
                    <div class="sidebar-submenu" style="font-size: 0.9rem; max-height: 300px; overflow-y: auto; text-align: center;">
                        <a href="/view-all?type=discover&with_original_language=ko" class="sidebar-link d-block py-2 text-decoration-none text-warning fw-bold" style="transition: 0.2s; justify-content: center; padding-left: 0;">
                            Korean (K-Drama)
                        </a>
                        <?php
                        $tmdb_genres = [
                            ['id' => 28, 'name' => 'Action'],
                            ['id' => 12, 'name' => 'Adventure'],
                            ['id' => 16, 'name' => 'Animation'],
                            ['id' => 35, 'name' => 'Comedy'],
                            ['id' => 80, 'name' => 'Crime'],
                            ['id' => 99, 'name' => 'Documentary'],
                            ['id' => 18, 'name' => 'Drama'],
                            ['id' => 10751, 'name' => 'Family'],
                            ['id' => 14, 'name' => 'Fantasy'],
                            ['id' => 36, 'name' => 'History'],
                            ['id' => 27, 'name' => 'Horror'],
                            ['id' => 10402, 'name' => 'Music'],
                            ['id' => 9648, 'name' => 'Mystery'],
                            ['id' => 10749, 'name' => 'Romance'],
                            ['id' => 878, 'name' => 'Science Fiction'],
                            ['id' => 10770, 'name' => 'TV Movie'],
                            ['id' => 53, 'name' => 'Thriller'],
                            ['id' => 10752, 'name' => 'War'],
                            ['id' => 37, 'name' => 'Western']
                        ];
                        
                        foreach ($tmdb_genres as $genre) {
                            echo '<a href="/view-all?type=discover&with_genres=' . $genre['id'] . '" class="sidebar-link d-block py-2 text-decoration-none" style="color: var(--text-sub); transition: 0.2s; justify-content: center; padding-left: 0;">
                                    ' . htmlspecialchars($genre['name']) . '
                                  </a>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="sidebar-section-label">Media</div>
        
        <a href="/view-all?type=movie" class="sidebar-link">
            <i class="ph ph-film-strip"></i><span>Movies</span>
        </a>
        <a href="/view-all?type=tv" class="sidebar-link">
            <i class="ph ph-monitor-play"></i><span>TV Shows</span>
        </a>
        <a href="/view-all?type=discover&with_genres=16" class="sidebar-link">
            <i class="ph ph-sparkle"></i><span>Animation</span>
        </a>
        <a href="/view-all?type=discover&with_genres=16&with_original_language=ja" class="sidebar-link">
            <i class="ph ph-book-open"></i><span>Anime & Manga</span>
        </a>
        <a href="/view-all?type=discover&with_genres=10402" class="sidebar-link">
            <i class="ph ph-music-note"></i><span>Music</span>
        </a>
        <a href="/view-all?type=discover&with_genres=99" class="sidebar-link">
            <i class="ph ph-video-camera"></i><span>Documentaries</span>
        </a>
        
        
        <div style="height: 12px;"></div>
        <a href="javascript:void(0)" onclick="if(typeof openThemeModal==='function') openThemeModal(); return false;" class="sidebar-link">
            <i class="ph ph-sparkle text-primary"></i><span>Color House</span>
        </a>
        <a href="/profile" class="sidebar-link">
            <i class="ph ph-heart"></i><span>Watchlist</span>
        </a>

        <?php if (!$isKidsMode): ?>
        <a href="/pricing-plan" class="sidebar-link">
            <i class="ph ph-crown"></i><span>Upgrade Plan</span>
        </a>
        <?php endif; ?>

        <a href="javascript:void(0)" onclick="if(typeof switchProfileMode==='function') switchProfileMode(); return false;" class="sidebar-link">
            <i class="ph <?php echo $isKidsMode ? 'ph-user-switch' : 'ph-smiley'; ?>"></i>
            <span><?php echo $isKidsMode ? 'Exit Kids' : 'Kids Mode'; ?></span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="/profile" class="sidebar-user">
            <img src="<?php echo htmlspecialchars($avatarPath ?? 'assets/images/user/user6.jpg'); ?>" alt="Profile">
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?php echo htmlspecialchars($displayName); ?></div>
                <div class="sidebar-user-plan"><?php echo htmlspecialchars($current_plan); ?> plan</div>
            </div>
        </a>
    </div>
</aside>

<script>
// ========== SIDEBAR TOGGLE LOGIC ==========
(function() {
    var sidebar = document.getElementById('appSidebar');
    var overlay = document.getElementById('sidebarOverlay');
    
    // Global toggle function
    window.toggleMobileSidebar = function() {
        if (!sidebar || !overlay) return;
        var isOpen = sidebar.classList.contains('mobile-open');
        if (isOpen) {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
        } else {
            sidebar.classList.add('mobile-open');
            overlay.classList.add('active');
        }
    };

    // Close button
    var closeBtn = document.getElementById('sidebarCloseBtn');
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            if (window.innerWidth >= 1200) {
                sidebar.classList.toggle('collapsed');
                document.body.classList.toggle('sidebar-collapsed');
                // Toggle the icon between X and list/hamburger
                var icon = closeBtn.querySelector('i');
                if (sidebar.classList.contains('collapsed')) {
                    icon.classList.remove('ph-x');
                    icon.classList.add('ph-list');
                } else {
                    icon.classList.remove('ph-list');
                    icon.classList.add('ph-x');
                }
            } else {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
            }
        });
    }

    // Close on overlay tap
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
        });
    }
})();
</script>
