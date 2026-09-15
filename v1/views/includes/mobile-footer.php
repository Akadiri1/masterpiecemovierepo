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
        .watch-app { padding-bottom: 70px !important; }
    }
    /* Hide mobile footer on desktop (sidebar handles navigation) */
    @media (min-width: 1200px) {
        .streamit-mobile-footer-menu { display: none !important; }
    }
</style>

<div class="streamit-mobile-footer-menu" aria-label="Mobile Footer Navigation" style="position: fixed; bottom: 0; left: 0; width: 100%; z-index: 999999;">
    <ul class="footer-menu list-inline d-flex align-items-center justify-content-between m-0">
        <li class="footer-menu-item">
            <a href="/" class="menu-link font-size-12">
                <i class="ph ph-house d-block text-center"></i>
                Home</a>
        </li>
        <li class="footer-menu-item">
            <a href="/view-all?type=movie" class="menu-link font-size-12">
                <i class="ph ph-film-reel d-block text-center"></i>
                Movies</a>
        </li>
        <li class="footer-menu-item">
            <a href="javascript:void(0)" class="menu-link font-size-12" onclick="toggleMobileSidebar()">
                <i class="ph ph-list d-block text-center"></i>
                <span>Menu</span>
            </a>
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
// Fix mobile scrolling on pages that use watch-theme.css (which sets body overflow:hidden)
(function() {
    if (window.innerWidth <= 800) {
        document.documentElement.style.cssText += 'overflow:auto!important; height:auto!important;';
        document.body.style.cssText += 'overflow:auto!important; height:auto!important;';
        var watchApp = document.querySelector('.watch-app');
        if (watchApp) {
            watchApp.style.cssText += 'height:auto!important; overflow:visible!important;';
        }
    }
})();
</script>
