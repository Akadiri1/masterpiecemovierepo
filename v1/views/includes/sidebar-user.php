<?php
/**
 * The signed-in member (or the invitation to sign in) at the foot of the
 * sidebar. Shared by every layout that shows the sidebar.
 */
$displayName = $displayName ?? ($_SESSION['firstName'] ?? $_SESSION['username'] ?? 'Guest');
$avatarPath = $avatarPath ?? ($_SESSION['avatar_url'] ?? 'assets/images/user/user.jpg');
$current_plan = $current_plan ?? ($_SESSION['plan_name'] ?? 'Free');
?>
    <div class="sidebar-footer">
        <?php if (empty($_SESSION['user_id'])): ?>
        <!-- Guests: sign in and come back to this page. -->
        <a href="<?php echo htmlspecialchars(signInUrl()); ?>" class="sidebar-user">
            <span class="sidebar-signin-icon"><i class="ph ph-sign-in"></i></span>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name">Sign in</div>
                <div class="sidebar-user-plan sidebar-user-hint">Save your watchlist and history</div>
            </div>
        </a>
        <?php else: ?>
        <a href="/profile" class="sidebar-user">
            <img src="<?php echo htmlspecialchars($avatarPath ?? 'assets/images/user/user6.jpg'); ?>" alt="Profile">
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?php echo htmlspecialchars($displayName); ?></div>
                <div class="sidebar-user-plan"><?php echo htmlspecialchars($current_plan); ?> plan</div>
            </div>
        </a>
        <?php endif; ?>
    </div>
