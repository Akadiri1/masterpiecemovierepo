<?php
/**
 * The site's sidebar menu. Kept in its own file because two different layouts
 * show it: the normal pages through includes/sidebar.php, and the watch page,
 * which has its own container. They used to carry separate hand-written
 * copies, so the watch page quietly fell behind.
 *
 * Expects nothing: everything it needs comes from the session.
 */
$isKidsMode = $isKidsMode ?? !empty($_SESSION['is_kids_mode']);
?>
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
        <?php if (!empty($_SESSION['user_id'])): ?>
        <div class="sidebar-section-label">You</div>
        <a href="/dashboard" class="sidebar-link <?php echo strtok($_SERVER['REQUEST_URI'], '?') === '/dashboard' ? 'active' : ''; ?>">
            <i class="ph ph-squares-four"></i><span>My Dashboard</span>
        </a>
        <?php endif; ?>
        <a href="/profile" class="sidebar-link">
            <i class="ph ph-heart"></i><span>Watchlist</span>
        </a>
        <?php if (!empty($_SESSION['user_id'])): ?>
        <a href="/profile#history" class="sidebar-link">
            <i class="ph ph-clock-counter-clockwise"></i><span>Watch History</span>
        </a>
        <a href="/profile" class="sidebar-link">
            <i class="ph ph-user-circle"></i><span>Profile</span>
        </a>
        <?php endif; ?>

        <?php if (!$isKidsMode): ?>
        <a href="/pricing-plan" class="sidebar-link">
            <i class="ph ph-crown"></i><span>Upgrade Plan</span>
        </a>
        <?php endif; ?>

        <a href="javascript:void(0)" onclick="if(typeof switchProfileMode==='function') switchProfileMode(); return false;" class="sidebar-link">
            <i class="ph <?php echo $isKidsMode ? 'ph-user-switch' : 'ph-smiley'; ?>"></i>
            <span><?php echo $isKidsMode ? 'Leave Kids Mode' : 'Kids Mode'; ?></span>
        </a>

        <?php
        // Admins reach the admin panel from here rather than typing the address.
        // Hidden in Kids Mode, where the site is locked to the child's catalogue.
        if (!empty($_SESSION['user_id']) && !$isKidsMode && isset($conn)) {
            try {
                $adminCheck = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
                $adminCheck->execute([(int) $_SESSION['user_id']]);
                $sidebarIsAdmin = (int) $adminCheck->fetchColumn() === 1;
            } catch (PDOException $e) {
                $sidebarIsAdmin = false;
            }
            if ($sidebarIsAdmin): ?>
        <div class="sidebar-section-label">Admin</div>
        <a href="/admin" class="sidebar-link">
            <i class="ph ph-shield-star"></i><span>Admin Panel</span>
        </a>
        <a href="/admin-free-films" class="sidebar-link">
            <i class="ph ph-film-script"></i><span>Free Films</span>
        </a>
        <a href="/admin-view-users" class="sidebar-link">
            <i class="ph ph-users-three"></i><span>Members</span>
        </a>
        <?php endif;
        } ?>
    </nav>
