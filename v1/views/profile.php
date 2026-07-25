<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

// ==========================================
// 2. FETCH USER DATA
// ==========================================
$user = null;
if (isset($conn)) {
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            session_destroy();
            header('Location: /login');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Profile DB Error: " . $e->getMessage());
        include 'includes/header.php';
        echo '<div class="container mt-5 text-center text-white"><h3>System Error</h3><p>Could not load profile.</p></div>';
        include 'includes/footer.php';
        exit;
    }
}

// Prepare Display Data
$user['avatar_path'] = !empty($user['avatar_url']) ? $user['avatar_url'] : 'assets/images/user/user6.jpg';
$user['fullName'] = trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''));
if (empty($user['fullName'])) {
    $user['fullName'] = $user['username'];
}

// ==========================================
// 3. FETCH WATCHLIST
// ==========================================
$watchlistItems = [];
if (isset($conn)) {
    try {
        $wSql = "SELECT tmdb_movie_id, media_type FROM watchlist WHERE user_id = ? ORDER BY id DESC";
        $wStmt = $conn->prepare($wSql);
        $wStmt->execute([$_SESSION['user_id']]);
        $savedIds = $wStmt->fetchAll(PDO::FETCH_ASSOC);

        if(function_exists('fetchTmdbApi')) {
            foreach ($savedIds as $item) {
                $tId = $item['tmdb_movie_id'];
                $tType = $item['media_type']; 

                // Fetch minimal details
                $data = fetchTmdbApi("{$tType}/{$tId}");
                
                if ($data) {
                    $title = $data['title'] ?? $data['name'] ?? 'Unknown';
                    $poster = !empty($data['poster_path']) ? 'https://image.tmdb.org/t/p/w1280'.$data['poster_path'] : '/assets/images/media/placeholder-portrait.svg';
                    $date = $data['release_date'] ?? $data['first_air_date'] ?? '';
                    $year = $date ? substr($date, 0, 4) : 'N/A';
                    $vote = $data['vote_average'] ?? 0;

                    $watchlistItems[] = [
                        'id' => $tId,
                        'type' => $tType,
                        'title' => $title,
                        'poster' => $poster,
                        'year' => $year,
                        'vote' => $vote
                    ];
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Watchlist Error: " . $e->getMessage());
    }
}

// ==========================================
// 4. FETCH SUBSCRIPTION
// ==========================================
$subscription = [];
$planLabel = 'Free Plan';
$isFree = true;
$planExpiry = 'N/A';

if (isset($conn)) {
    $subSql = "SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active' AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1";
    $subStmt = $conn->prepare($subSql);
    $subStmt->execute([$_SESSION['user_id']]);
    $subscription = $subStmt->fetch(PDO::FETCH_ASSOC);

    if ($subscription) {
        $planLabel = $subscription['plan_name'];
        $isFree = false;
        $planExpiry = date('F j, Y', strtotime($subscription['expires_at']));
    }
}

include 'includes/header.php';
?>

<!-- ==========================================
     CUSTOM CSS FOR REDESIGN
     ========================================== -->
<style>
    :root {
        --profile-bg: #141414;
        --sidebar-bg: #1a1a1a;
        --card-bg: #1f1f1f;
        --primary: var(--primary);
        --text-muted: #a3a3a3;
        --border-color: rgba(255,255,255,0.1);
    }

    /* 0. Full Width Page Override */
    @media (min-width: 992px) {
        /* .app-sidebar { display: none !important; } */
        /* .iq-navbar { display: flex !important; } */
        /* .main-content { margin-left: 0 !important; } */
        .container-fluid { padding-left: 20px !important; padding-right: 20px !important; max-width: 100% !important; margin: 0 !important; }
    }

    #profile-header-name {
        font-size: 2.2rem !important; /* Reduced font size */
    }

    /* 1. Header Banner */
    .profile-cover {
        height: 70px;
        background: linear-gradient(to bottom, rgba(20,20,20,0.2) 0%, #141414 100%), url('assets/images/pages/profile-bg.jpg') no-repeat center center/cover;
        position: relative;
        margin-bottom: 20px;
    }

    .profile-info-card {
        margin-top: -80px; /* Pull up over banner */
        padding: 0 40px;
        position: relative;
        z-index: 10;
    }

    .user-avatar-lg {
        width: 140px; height: 130px;
        border-radius: 50%;
        border: 4px solid #141414;
        object-fit: cover;
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    }

    /* 2. Sidebar Navigation */
    .profile-sidebar {
        background: var(--sidebar-bg);
        border-radius: 12px;
        padding: 20px 0;
        border: 1px solid var(--border-color);
        height: 100%;
    }

    .nav-profile .nav-link {
        color: var(--text-muted);
        padding: 15px 25px;
        font-weight: 500;
        border-left: 3px solid transparent;
        border-radius: 0;
        transition: all 0.3s;
        display: flex; align-items: center; gap: 12px;
    }

    .nav-profile .nav-link:hover {
        background: rgba(255,255,255,0.03);
        color: #fff;
    }

    .nav-profile .nav-link.active {
        background: linear-gradient(90deg, rgba(229, 9, 20, 0.1) 0%, transparent 100%);
        color: var(--primary);
        border-left-color: var(--primary);
    }
    
    .nav-profile i { font-size: 1.2rem; width: 24px; text-align: center; }

    /* 3. Content Area */
    .profile-content {
        padding: 20px 0;
    }
    
    .content-card {
        background: var(--sidebar-bg); /* Use sidebar color for unity or transparent */
        border-radius: 12px;
        /* border: 1px solid var(--border-color); */
        /* padding: 30px; */
    }

    .section-title {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 25px;
        color: #fff;
        border-left: 4px solid var(--primary);
        padding-left: 15px;
    }

    /* 4. Plan Card */
    .plan-card {
        background: linear-gradient(135deg, #2b2b2b 0%, #1f1f1f 100%);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 16px;
        overflow: hidden;
        position: relative;
    }
    
    .plan-card.premium {
        background: linear-gradient(135deg, #531214 0%, #1f1f1f 100%);
        border-color: var(--primary);
    }

    .table-dark-custom,
    .table-dark-custom thead,
    .table-dark-custom tbody,
    .table-dark-custom tr,
    .table-dark-custom th, 
    .table-dark-custom td {
        background: transparent !important;
        background-color: transparent !important;
        color: #fff !important;
        border-bottom: 1px solid rgba(255,255,255,0.05);
        padding: 15px;
        box-shadow: none !important;
    }
    
    .table-dark-custom { border-collapse: collapse; }

    /* 5. Movie Card (Reused from view-all) */
    .vod-card {
        position: relative; border-radius: 8px; overflow: hidden;
        transition: transform 0.3s; background: transparent;
    }
    .vod-card:hover { transform: translateY(-5px); z-index: 2; }
    .poster-box {
        position: relative; padding-top: 150%; background: #222;
        border-radius: 8px; overflow: hidden;
    }
    .poster-box img {
        position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover;
    }
    .card-overlay {
        position: absolute; top:0; left:0; width:100%; height:100%;
        background: rgba(0,0,0,0.6); opacity: 0; transition: opacity 0.3s;
        display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 10px;
    }
    .vod-card:hover .card-overlay { opacity: 1; }
    .remove-btn {
        width: 40px; height: 40px; border-radius: 50%;
        background: rgba(255,255,255,0.2); color: #fff;
        display: flex; align-items: center; justify-content: center;
        border: none; cursor: pointer; transition: 0.2s;
    }
    .remove-btn:hover { background: var(--primary); }

    /* Form Styles */
    .form-control-dark {
        background: #0f0f0f;
        border: 1px solid #333;
        color: #fff;
        padding: 12px;
    }
    .form-control-dark:focus {
        background: #0f0f0f;
        border-color: var(--primary);
        color: #fff;
        box-shadow: none;
    }
    .form-label { color: #ccc; font-size: 0.9rem; margin-bottom: 8px; }

    /* Mobile stability: prevent sideways dragging/overflow and make content responsive */
    @media (max-width: 767.98px) {
        html, body { overflow-x: hidden; touch-action: pan-y; -webkit-overflow-scrolling: touch; }
        .container-fluid, .profile-info-card, .profile-content { padding-left: 12px !important; padding-right: 12px !important; }
        .user-avatar-lg { width: 96px; height: 96px; }
        .profile-info-card { margin-top: -60px; }
        .profile-sidebar { padding: 12px 8px; }
        .nav-profile .nav-link { padding: 10px 12px; font-size: 0.95rem; }
        .poster-box { padding-top: 140%; }
        .card, .content-card { padding-left: 10px; padding-right: 10px; }
        .profile-cover { height: 60px; }
        img { max-width: 100%; height: auto; }
        .tab-content { overflow-x: hidden; }
    }

    /* Smooth page load */
    .main-content { animation: fadeIn 0.4s ease; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

    /* Card hover effects */
    .vod-card { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s ease; }
    .vod-card:hover { transform: translateY(-8px); box-shadow: 0 12px 30px rgba(0,0,0,0.5); }

    /* Tab transitions */
    .tab-pane { animation: fadeIn 0.3s ease; }

    /* Poster images - 1080p quality */
    .poster-box img { image-rendering: auto; }

    /* Desktop 1080p+ */
    @media (min-width: 1080px) {
        .profile-cover { height: 120px; }
        .user-avatar-lg { width: 160px; height: 150px; }
        .section-title { font-size: 1.7rem; }
    }

    /* Plan card glow */
    .plan-card.premium { box-shadow: 0 0 30px rgba(229, 9, 20, 0.1); }
    .plan-card.premium:hover { box-shadow: 0 0 40px rgba(229, 9, 20, 0.2); transition: box-shadow 0.3s ease; }
</style>

<!-- ==========================================
     HTML STRUCTURE
     ========================================== -->
    
    <div class="container-fluid px-0" style="padding-top: 60px;">
        <!-- 1. USER INFO HEADER (GLASSMORPHISM) -->
        <div class="row m-0 mb-5">
            <div class="col-12 px-0">
                <div class="profile-info-card" style="background: rgba(20, 20, 25, 0.4); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.05); border-radius: 20px; padding: 40px; margin-top: 0; display: flex; align-items: center; gap: 30px; box-shadow: 0 20px 40px rgba(0,0,0,0.3); width: 100%;">
                    
                    <div class="position-relative" style="flex-shrink: 0;">
                        <img src="<?php echo htmlspecialchars($user['avatar_path']); ?>" alt="Profile" 
                             class="user-avatar-lg" id="profile-header-avatar" style="border: 4px solid rgba(255,255,255,0.1); width: 150px; height: 150px; box-shadow: 0 0 30px rgba(229, 9, 20, 0.2);">
                        <button class="btn btn-primary position-absolute bottom-0 end-0 rounded-circle d-flex align-items-center justify-content-center"
                                style="width: 40px; height: 40px; border: 2px solid #141414;"
                                data-bs-toggle="modal" data-bs-target="#edit-profile-modal" title="Change Avatar">
                            <i class="fa fa-camera"></i>
                        </button>
                    </div>
                    
                    <div class="d-flex flex-column text-start flex-grow-1">
                        <h1 class="text-white fw-bold mb-1" id="profile-header-name" style="letter-spacing: -0.5px;"><?php echo htmlspecialchars($user['fullName']); ?></h1>
                        <p class="text-muted mb-3" id="profile-header-email"><?php echo htmlspecialchars($user['email']); ?></p>
                        
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <span class="badge" style="background: <?php echo $isFree ? 'rgba(255,255,255,0.1)' : 'linear-gradient(45deg, var(--primary), #ff4b2b)'; ?>; color: #fff; padding: 8px 16px; font-size: 0.85rem; border-radius: 30px;">
                                <i class="fa-solid <?php echo $isFree ? 'fa-user' : 'fa-crown'; ?> me-2"></i>
                                <?php echo $isFree ? 'Free Member' : 'Premium Member'; ?>
                            </span>
                            <button class="btn btn-outline-light rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#edit-profile-modal">
                                <i class="fa fa-pencil me-2"></i> Edit Profile
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 px-4">
            <!-- 2. SIDEBAR NAVIGATION -->
            <div class="col-12 col-md-4 col-lg-3 mb-4">
                <div class="profile-sidebar" style="background: rgba(20, 20, 25, 0.4); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.05); overflow: hidden;">
                    <div class="nav flex-column nav-profile" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                        <a class="nav-link active" id="v-pills-watchlist-tab" data-bs-toggle="pill" href="#v-pills-watchlist" role="tab">
                            <i class="fa-regular fa-bookmark"></i> Watchlist
                        </a>
                        <a class="nav-link" id="v-pills-playlist-tab" data-bs-toggle="pill" href="#v-pills-playlist" role="tab">
                            <i class="fa-solid fa-list-ul"></i> Playlists
                        </a>
                        <a class="nav-link" id="v-pills-membership-tab" data-bs-toggle="pill" href="#v-pills-membership" role="tab">
                            <i class="fa-regular fa-credit-card"></i> Membership
                        </a>
                        <a class="nav-link" id="v-pills-parental-tab" data-bs-toggle="pill" href="#v-pills-parental" role="tab">
                            <i class="fa-solid fa-shield-halved"></i> Parental Controls
                        </a>
                        <hr class="border-secondary my-2 mx-3">
                        <a class="nav-link text-danger" href="/logout" onclick="return confirm('Are you sure you want to log out?');">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
                        </a>
                    </div>
                </div>
            </div>

            <!-- 3. MAIN CONTENT AREA -->
            <div class="col-12 col-md-8 col-lg-9 profile-content" style="padding-top: 0;">
                <div class="tab-content" id="v-pills-tabContent">
                    
                    <!-- TAB: WATCHLIST -->
                    <div class="tab-pane fade show active" id="v-pills-watchlist" role="tabpanel">
                        <h3 class="section-title">My Watchlist</h3>
                        
                        <?php if (empty($watchlistItems)): ?>
                            <div class="text-center p-5 rounded-4" style="background: rgba(20, 20, 25, 0.4); backdrop-filter: blur(20px); border: 1px dashed rgba(255,255,255,0.2); box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                                <div style="width: 80px; height: 80px; background: rgba(229, 9, 20, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                                    <i class="fa-solid fa-film text-primary" style="font-size: 2.5rem; filter: drop-shadow(0 0 10px rgba(229,9,20,0.5));"></i>
                                </div>
                                <h4 class="text-white fw-bold mb-2">Your watchlist is empty</h4>
                                <p class="text-muted mb-4">Save your favorite movies and shows here to watch them later.</p>
                                <a href="/" class="btn btn-primary rounded-pill px-5 py-2 fw-bold shadow">Browse Content</a>
                            </div>
                        <?php else: ?>
                            <div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-4">
                                <?php foreach ($watchlistItems as $item): ?>
                                <div class="col">
                                    <div class="vod-card h-100">
                                        <div class="poster-box">
                                            <img src="<?php echo $item['poster']; ?>" loading="lazy" alt="<?php echo htmlspecialchars($item['title']); ?>">
                                            <div class="card-overlay">
                                                <a href="/<?php echo $item['type']; ?>/<?php echo $item['id']; ?>" class="btn btn-primary rounded-circle" style="width:45px; height:45px; display:flex; align-items:center; justify-content:center;">
                                                    <i class="fa-solid fa-play"></i>
                                                </a>
                                                <button class="remove-btn watchlist-remove-btn" data-id="<?php echo $item['id']; ?>" data-type="<?php echo $item['type']; ?>" title="Remove">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <h6 class="text-white text-truncate mb-1"><?php echo htmlspecialchars($item['title']); ?></h6>
                                            <small class="text-muted"><?php echo $item['year']; ?></small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- TAB: PLAYLISTS -->
                    <div class="tab-pane fade" id="v-pills-playlist" role="tabpanel">
                        <h3 class="section-title">My Playlists</h3>
                        <p class="text-muted">Custom playlists feature coming soon.</p>
                    </div>

                    <!-- TAB: MEMBERSHIP -->
                    <div class="tab-pane fade" id="v-pills-membership" role="tabpanel">
                        <h3 class="section-title">Membership & Billing</h3>
                        
                        <div class="row">
                            <div class="col-12">
                                <!-- Plan Card -->
                                <div class="plan-card p-4 p-md-5 mb-4 <?php echo !$isFree ? 'premium' : ''; ?>" style="background: rgba(20, 20, 25, 0.6); backdrop-filter: blur(20px); border-radius: 20px; border: 1px solid rgba(255,255,255,0.05); position: relative; overflow: hidden; box-shadow: 0 15px 35px rgba(0,0,0,0.3);">
                                    <!-- Decorative Glow -->
                                    <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: <?php echo !$isFree ? 'var(--primary)' : 'rgba(255,255,255,0.1)'; ?>; filter: blur(60px); border-radius: 50%;"></div>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-4 position-relative z-1">
                                        <h5 class="text-uppercase text-muted fw-bold mb-0" style="letter-spacing: 1px; font-size: 0.85rem;">Current Plan</h5>
                                        <?php if (!$isFree): ?>
                                            <span class="badge bg-success bg-opacity-25 text-success border border-success rounded-pill px-3 py-2"><i class="fa fa-circle me-1" style="font-size:8px; vertical-align:middle;"></i> Active</span>
                                        <?php endif; ?>
                                    </div>
                                    <h1 class="text-white fw-bold mb-2 position-relative z-1" style="font-size: 2.5rem; letter-spacing:-1px;"><?php echo htmlspecialchars($planLabel); ?></h1>
                                    
                                    <?php if ($isFree): ?>
                                        <p class="text-muted position-relative z-1 mb-4">Upgrade to Premium for 4K streaming, zero ads, and exclusive content.</p>
                                        <a href="/pricing-plan" class="btn btn-primary rounded-pill px-4 py-2 fw-bold position-relative z-1 shadow">Upgrade to Premium</a>
                                    <?php else: ?>
                                        <p class="text-white-50 position-relative z-1 mb-4">Your plan auto-renews on <span class="text-white fw-bold border-bottom border-secondary pb-1"><?php echo $planExpiry; ?></span>.</p>
                                        <div class="d-flex gap-2 position-relative z-1">
                                            <!-- <button class="btn btn-outline-light rounded-pill px-4">Manage Subscription</button> -->
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- History Table -->
                                <h5 class="text-white mb-3 mt-5">Billing History</h5>
                                <div class="table-responsive">
                                    <table class="table table-dark-custom">
                                        <thead>
                                            <tr>
                                                <th scope="col">Date</th>
                                                <th scope="col">Description</th>
                                                <th scope="col">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($subscription): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($subscription['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($planLabel); ?> Subscription</td>
                                                <td><span class="text-success"><i class="fa fa-check-circle me-1"></i> Paid</span></td>
                                            </tr>
                                            <?php else: ?>
                                            <tr>
                                                <td colspan="3" class="text-center text-muted">No billing history available.</td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB: PARENTAL CONTROLS -->
                    <div class="tab-pane fade" id="v-pills-parental" role="tabpanel">
                        <h3 class="section-title">Parental Controls</h3>
                        <div class="row">
                            <div class="col-12">
                                <div class="content-card p-4 p-md-5 mb-4" style="background: rgba(20, 20, 25, 0.6); backdrop-filter: blur(20px); border-radius: 20px; border: 1px solid rgba(255,255,255,0.05); box-shadow: 0 15px 35px rgba(0,0,0,0.3);">
                                    <div class="d-flex align-items-start gap-3 mb-4">
                                        <div class="bg-dark p-3 rounded-circle border border-secondary" style="background: rgba(0,0,0,0.5) !important;">
                                            <i class="fa-solid fa-lock text-primary fs-4"></i>
                                        </div>
                                        <div>
                                            <h5 class="text-white mb-1">Profile PIN</h5>
                                            <p class="text-muted small mb-0">Set a 4-digit PIN to prevent access to the Kids Mode switching or restricted content.</p>
                                        </div>
                                    </div>

                                    <?php $hasPin = !empty($user['parental_pin_hash']); ?>
                                    <form id="set-parental-pin-form">
                                        <div class="mb-3">
                                            <label class="form-label">New PIN</label>
                                            <input type="password" name="new_pin" id="new_pin" class="form-control form-control-dark" 
                                                   pattern="[0-9]{4,8}" inputmode="numeric" placeholder="Enter 4-8 digits" required>
                                        </div>
                                        <div class="mb-4">
                                            <label class="form-label">Confirm PIN</label>
                                            <input type="password" name="confirm_pin" id="confirm_pin" class="form-control form-control-dark" 
                                                   inputmode="numeric" placeholder="Re-enter PIN" required>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-primary px-4">Save PIN</button>
                                            <?php if($hasPin): ?>
                                                <button type="button" id="clear-parental-pin" class="btn btn-outline-danger">Remove PIN</button>
                                            <?php endif; ?>
                                        </div>
                                    </form>
                                    
                                    <?php if($hasPin): ?>
                                        <div class="alert alert-success bg-opacity-10 mt-4 border-0 d-flex align-items-center gap-2">
                                            <i class="fa-solid fa-check-circle"></i> PIN is currently set and active.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================
     EDIT PROFILE MODAL
     ========================================== -->
<div class="modal fade" id="edit-profile-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border border-secondary">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title text-white">Edit Profile</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="edit-profile-form" enctype="multipart/form-data">
                    <div class="text-center mb-4">
                        <div class="d-inline-block position-relative">
                            <img src="<?php echo htmlspecialchars($user['avatar_path']); ?>" id="profile-picture-preview" 
                                 class="rounded-circle border border-secondary" style="width:100px; height:100px; object-fit:cover;">
                            <label for="avatar-upload-input" class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-2 cursor-pointer" style="cursor:pointer;">
                                <i class="fa fa-camera"></i>
                            </label>
                        </div>
                        <input type="file" id="avatar-upload-input" name="avatar" hidden accept="image/*">
                        <input type="hidden" id="is_remove_avatar" name="is_remove_avatar" value="0">
                        <div class="mt-2">
                            <small class="text-danger cursor-pointer" id="remove-profile-picture-btn">Remove Photo</small>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control form-control-dark" name="firstName" id="edit-first-name" value="<?php echo htmlspecialchars($user['firstName'] ?? ''); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control form-control-dark" name="lastName" id="edit-last-name" value="<?php echo htmlspecialchars($user['lastName'] ?? ''); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-control form-control-dark" name="email" id="edit-email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="mt-4 text-end">
                        <button type="button" class="btn btn-outline-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="save-profile-button" class="btn btn-primary">
                            <span class="button-text">Save Changes</span>
                            <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- TOASTIFY LIB -->
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

<!-- PAGE SCRIPT -->
<script>
document.addEventListener("DOMContentLoaded", () => {
    // --- 1. Edit Profile Logic ---
    const editForm = document.querySelector("#edit-profile-form");
    if (editForm) {
        const saveBtn = document.querySelector("#save-profile-button");
        const btnText = saveBtn.querySelector(".button-text");
        const spinner = saveBtn.querySelector(".spinner-border");
        
        // Avatar Preview
        const avatarInput = document.getElementById("avatar-upload-input");
        const avatarPreview = document.getElementById("profile-picture-preview");
        const removeAvatarBtn = document.getElementById("remove-profile-picture-btn");
        const removeFlag = document.getElementById("is_remove_avatar");
        const defaultSrc = 'assets/images/user/user6.jpg';

        avatarInput.addEventListener("change", (e) => {
            const file = e.target.files[0];
            if(file) {
                const reader = new FileReader();
                reader.onload = (e) => avatarPreview.src = e.target.result;
                reader.readAsDataURL(file);
                removeFlag.value = "0";
            }
        });

        removeAvatarBtn.addEventListener("click", () => {
            avatarPreview.src = defaultSrc;
            avatarInput.value = "";
            removeFlag.value = "1";
        });

        // Form Submit
        editForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            saveBtn.disabled = true;
            btnText.classList.add("d-none");
            spinner.classList.remove("d-none");

            const formData = new FormData(editForm);
            try {
                const res = await fetch('/update-profile', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (res.ok) {
                    Toastify({ text: "Profile Updated!", style: { background: "#00b09b" } }).showToast();
                    // Update header name/email immediately
                    document.getElementById('profile-header-name').textContent = 
                        (document.getElementById('edit-first-name').value + ' ' + document.getElementById('edit-last-name').value).trim();
                    
                    // Update header avatar if changed
                    if(avatarPreview.src) document.getElementById('profile-header-avatar').src = avatarPreview.src;

                    setTimeout(() => bootstrap.Modal.getInstance(document.getElementById('edit-profile-modal')).hide(), 500);
                } else {
                    Toastify({ text: data.message || "Update Failed", style: { background: "#ff5f6d" } }).showToast();
                }
            } catch (err) {
                console.error(err);
                Toastify({ text: "Network Error", style: { background: "#ff5f6d" } }).showToast();
            } finally {
                saveBtn.disabled = false;
                btnText.classList.remove("d-none");
                spinner.classList.add("d-none");
            }
        });
    }

    // --- 2. Parental PIN Logic ---
    const pinForm = document.getElementById('set-parental-pin-form');
    if (pinForm) {
        pinForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const newPin = document.getElementById('new_pin').value;
            const confirmPin = document.getElementById('confirm_pin').value;

            if(newPin !== confirmPin) {
                Toastify({ text: "PINs do not match", style: { background: "#ff5f6d" } }).showToast();
                return;
            }

            const formData = new FormData(pinForm);
            try {
                const res = await fetch('/set-parental-pin', { method: 'POST', body: formData });
                const data = await res.json();
                if(data.status === 'success') {
                    Toastify({ text: "PIN Saved", style: { background: "#00b09b" } }).showToast();
                    pinForm.reset();
                } else {
                    Toastify({ text: data.message, style: { background: "#ff5f6d" } }).showToast();
                }
            } catch (err) {
                Toastify({ text: "Network Error", style: { background: "#ff5f6d" } }).showToast();
            }
        });
        
        const clearBtn = document.getElementById('clear-parental-pin');
        if(clearBtn) {
            clearBtn.addEventListener('click', async () => {
                if(!confirm("Remove PIN?")) return;
                const fd = new FormData(); fd.append('action', 'clear');
                await fetch('/set-parental-pin', { method: 'POST', body: fd });
                location.reload();
            });
        }
    }

    // --- 3. Watchlist Remove Logic ---
    document.querySelectorAll('.watchlist-remove-btn').forEach(btn => {
        btn.addEventListener('click', async function(e) {
            e.preventDefault(); // prevent link click if inside a tag
            e.stopPropagation();
            if(!confirm("Remove from Watchlist?")) return;

            const id = this.dataset.id;
            const type = this.dataset.type;
            const cardCol = this.closest('.col'); // To remove the element

            try {
                // Assuming you have an endpoint like this. If not, create 'remove-watchlist.php'
                const res = await fetch('/add-watchlist?id='+id+'&type='+type+'&action=remove'); 
                // Or if your add-watchlist toggles, just calling it might work depending on logic.
                
                // Visual removal
                cardCol.style.opacity = '0';
                setTimeout(() => cardCol.remove(), 300);
                Toastify({ text: "Removed", style: { background: "#555" } }).showToast();
                
            } catch(err) {
                console.error(err);
            }
        });
    });
});
</script>