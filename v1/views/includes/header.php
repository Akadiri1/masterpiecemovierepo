<?php
// --- 2. THEME LOGIC (with defaults) ---
// We get these from the session. If not set, we use defaults.
$current_plan = $_SESSION['plan_name'] ?? 'free'; // 'free', 'premium', 'pro'
$current_profile_is_kid = $_SESSION['is_kid'] ?? 0; // 0 = no, 1 = yes

$themeClass = 'theme-premium'; // Default
if ($current_plan === 'pro') {
    $themeClass = 'theme-pro';
}
if ($current_profile_is_kid) {
    $themeClass .= '-kid'; // e.g., 'theme-pro-kid'
}

// --- 3. Build base URL dynamically ---
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$domain = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . $domain;

// --- 4. DEFAULT AVATAR ---
$avatarPath = $baseUrl . '/assets/images/user/user6.jpg';

// --- 5. Check profile page user avatar ---
// This $user variable is ONLY set on profile.php
if (isset($user) && is_array($user) && !empty($user['avatar_url'])) {

    // If stored path DOES NOT contain domain → add it
    if (strpos($user['avatar_url'], 'http') === false) {
        $avatarPath = $baseUrl . $user['avatar_url'];
    } else {
        $avatarPath = $user['avatar_url'];
    }

}
// --- 6. Check SESSION avatar for all other pages ---
else if (isset($_SESSION['avatar_url']) && !empty($_SESSION['avatar_url'])) {

    if (strpos($_SESSION['avatar_url'], 'http') === false) {
        $avatarPath = $baseUrl . $_SESSION['avatar_url'];
    } else {
        $avatarPath = $_SESSION['avatar_url'];
    }

}

// --- 7. Display name ---
$displayName = 'Guest User';
$userEmail = ''; // Initialize email for consistent usage

if (isset($user) && is_array($user)) {
    // We are on profile.php, use the full name
    $firstName = $user['firstName'] ?? '';
    $lastName = $user['lastName'] ?? '';
    $fullName = trim($firstName . ' ' . $lastName);
    $displayName = !empty($fullName) ? $fullName : ($user['username'] ?? 'Guest User');
    $userEmail = $user['email'] ?? ''; // Get email from $user if available
}
else if (isset($_SESSION['username'])) {
    // We are on any other page, use the session username
    $displayName = $_SESSION['username'];
}
?>

<!doctype html>
<html lang="en" data-bs-theme="dark">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>StreamIT | Responsive Bootstrap 5 Template</title>
  <!-- Google Font Api KEY-->
  <meta name="google_font_api" content="AIzaSyBG58yNdAjc20_8jAvLNSVi9E4Xhwjau_k">

  <!-- Favicon -->
  <link rel="shortcut icon" href="assets/images/favicon.ico" />

  <!-- Library / Plugin Css Build -->
  <link rel="stylesheet" href="assets/css/core/libs.min.css" />

  <!-- font-awesome css -->
  <link rel="stylesheet" href="assets/vendor/font-awesome/css/all.min.css" />
  <link rel="stylesheet" href="assets/css/core/custom.min.cssv=5.4.0.css" />
  <link rel="stylesheet" href="assets/css/core/rtl.min.cssv=5.4.0.css" />
  <link rel="stylesheet" href="assets/css/core/streamit.min.css" />
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

<body class=" <?php echo htmlspecialchars($themeClass); ?> ">
  <span class="screen-darken"></span>
  <!-- loader Start -->
   <!-- loader Start -->
  <div class="loader simple-loader">
     <div class="loader-body">
        <img src="assets/images/loader.gif" alt="loader" class="img-fluid " width="300">
      </div>
  </div>
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
                              <img class="img-fluid logo" src="assets/images/logo.png" loading="lazy" alt="streamit" />
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
                      <div>
                         <a href="/pricing-plan" 
                            class="subscribe-btn btn btn-warning-subtle py-1 py-md-2 px-2 px-ms-3">
                            <span class="d-flex align-items-center gap-2 text-warning">
                               <i class="ph-fill ph-crown align-middle fs-6"></i>
                               <span class="d-xl-block d-none">Subscribe</span>
                            </span>
                         </a>
                      </div>
                   </div>

                </div>
                <nav id="navbar_main"
                  class="offcanvas mobile-offcanvas nav navbar navbar-expand-xl hover-nav horizontal-nav mega-menu-content py-xl-0 w-100">
                  <div class="container-fluid p-lg-0">
                    <div class="offcanvas-header px-0">
                      <div class="navbar-brand ms-3">
                        <div class="logo-default">
                            <a class="navbar-brand text-primary me-0" href="/"> <!-- Updated to root path -->
                                <img class="img-fluid logo" src="assets/images/logo.png" loading="lazy" alt="streamit" />
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
                        </div>      </div>
                      <button type="button" class="btn-close float-end px-3" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                    </div>
                    <ul class="navbar-nav iq-nav-menu  list-unstyled" id="header-menu">
                      <li class="nav-item">
                        <a class="nav-link" href="/" role="button" aria-expanded="false"
                          aria-controls="homePages">
                          <div class="d-flex justify-content-between">
                            <span class="item-name">Home</span>
                          </div>
                        </a>
                      </li>
                      <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="collapse" href="#movies" role="button" aria-expanded="false"
                          aria-controls="homePages">
                          <div class="d-flex justify-content-between">
                            <span class="item-name">Movies</span>
                            <span class="menu-icon">
                              <i class="ph ph-caret-down align-middle"></i>
                            </span>
                          </div>
                        </a>
                        <ul class="sub-nav collapse  list-unstyled" id="movies">
                          <li class="nav-item">
                            <a class="nav-link "
                              href="/movies/chinese-drama"> 
                              <span>Chinese Drama</span> </a>
                          </li>
                          <li class="nav-item">
                            <a class="nav-link "
                              href="/movies/k-drama"> <span>K-Drama</span></a> 
                          </li>
                          <li class="nav-item">
                            <a class="nav-link"
                              href="/movies/tv-series"> <span>TV Series</span></a> 
                          </li>
                          <li class="nav-item">
                            <a class="nav-link " href="/movies/international"> <span>International</span> 
                            </a>
                          </li>
                          <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="collapse" href="#blog-grid" role="button" aria-expanded="false"
                              aria-controls="blog-grid">
                              <div class="d-flex justify-content-between">
                                <span class="item-name">Asian Movies</span>
                                <span class="menu-icon">
                                  <i class="ph ph-caret-down align-middle down-to-right"></i>
                                </span>
                              </div>
                            </a>
                            <ul class="sub-nav collapse  list-unstyled" id="blog-grid">
                              <li class="nav-item">
                                <a class="nav-link "
                                  href="/movies/asian/bollywood"> <span>Bollywood</span></a> 
                              </li>
                              <li class="nav-item">
                                <a class="nav-link "
                                  href="/movies/asian/korean"> <span>Korean Movies</span></a> 
                              </li>
                               <li class="nav-item">
                                <a class="nav-link "
                                  href="/movies/asian/japanese"> <span>Japanese Movies</span></a> 
                              </li>
                              <li class="nav-item">
                                <a class="nav-link "
                                  href="/movies/asian/philippine"> <span>Philippine Movies</span></a> 
                              </li>
                            </ul>
                          </li>
                        </ul>
                      </li>
                    <li class="nav-item">
                      <a class="nav-link" data-bs-toggle="collapse" href="#genre-menu" role="button" aria-expanded="false" aria-controls="genre-menu">
                        <div class="d-flex justify-content-between">
                          <span class="item-name">Genre</span>
                          <span class="menu-icon">
                            <i class="ph ph-caret-down align-middle"></i>
                          </span>
                        </div>
                      </a>

                      <ul class="sub-nav collapse list-unstyled" id="genre-menu">
                        <?php
                        // Example: You might fetch genres from a database or a configuration array
                        $genres = ['Action', 'Comedy', 'Anime', 'Drama', 'Sci-Fi', 'Horror', 'Thriller', 'Romance', 'Animation', 'Documentary', 'Fantasy', 'Family', 'Crime'];
                        foreach ($genres as $genre) {
                            echo '<li class="nav-item"><a class="nav-link" href="/genre/' . htmlspecialchars(strtolower(str_replace(' ', '-', $genre))) . '">' . htmlspecialchars($genre) . '</a></li>';
                        }
                        ?>
                      </ul>
                    </li>
                    </ul>
                  </div>
                </nav>            <div class="css_prefix-header-right d-flex align-items-center gap-2">
                   <ul
                      class="list-inline d-flex align-items-center gap-3 gap-md-4 mb-0 ps-0 justify-content-md-end justify-content-between">
                      <li class="nav-item dropdown iq-responsive-menu d-xl-block d-none">
                         <div class="search-box">
                            <a href="#search-drop" class="nav-link p-0 text-white" id="search-drop" data-bs-toggle="dropdown"> <!-- Changed href to a valid ID target -->
                               <div class="btn-icon btn-sm rounded-pill btn-action">
                                  <span class="btn-inner">
                                     <i class="ph ph-magnifying-glass p-0"></i>
                                  </span>
                               </div>
                            </a>
                            <ul class="dropdown-menu p-0 dropdown-search m-0 iq-search-bar" style="width: 20rem;">
                               <li class="p-0">
                                  <div class="form-group input-group mb-0">
                                     <input type="text" class="form-control border-0" placeholder="Search...">
                                     <button type="submit" class="search-submit">
                                        <i class="ph ph-magnifying-glass"></i>
                                     </button>
                                  </div>
                               </li>
                            </ul>
                         </div>
                      </li>

                      <li class="nav-item dropdown cust-itemdropdown1" id="itemdropdown1">
                         <a class="nav-link d-flex align-items-center p-0" href="#navbarDropdown" id="navbarDropdown" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="st-avatar style-1">
                               <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="Profile picture of <?php echo htmlspecialchars($displayName); ?>" 
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
                                     <img src="<?php echo htmlspecialchars($avatarPath); ?>"
                                        class="img-fluid rounded-3 dropdown-user-menu-image" alt="Profile picture of <?php echo htmlspecialchars($displayName); ?>"> <!-- Added alt text for accessibility -->
                                  </div>
                                  <div class="content">
                                     <h6 class="mb-1">
                                    <?php echo htmlspecialchars($displayName); ?>
                                 </h6>
                                 <?php if (!empty($userEmail)): // Use the initialized $userEmail variable ?>
                                    <p class="mb-0" style="font-size: 0.8rem;"><?php echo htmlspecialchars($userEmail); ?></p>
                                 <?php endif; ?>
                                  </div>
                               </div>

                               <!-- Menu Items -->
                               <ul class="d-flex flex-column gap-3 list-inline m-0 p-0">
                                  <li>
                                     <a href="/profile" 
                                        class="link-body-emphasis font-size-14 d-flex align-items-center gap-2">
                                        <i class="ph ph-user"></i>
                                        <span class="fw-medium">Profile</span>
                                     </a>
                                  </li>
                                  <li>
                                     <a href="/watchlist" 
                                        class="link-body-emphasis font-size-14 d-flex align-items-center gap-2">
                                        <i class="ph ph-plus"></i>
                                        <span class="fw-medium">Watch List</span>
                                     </a>
                                  </li>
                                  <li>
                                     <a href="/playlist" 
                                        class="link-body-emphasis font-size-14 d-flex align-items-center gap-2">
                                        <i class="ph ph-playlist"></i>
                                        <span class="fw-medium">Playlist</span>
                                     </a>
                                  </li>
                                  <li>
                                     <a href="/notifications" 
                                        class="link-body-emphasis font-size-14 d-flex align-items-center gap-2">
                                        <i class="ph ph-bell"></i>
                                        <span class="fw-medium">Notification</span>
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
             </div>
          </div>
       </nav>
    </header>

    <div
       class="offcanvas overflow-y-auto widget-shopping-cart-content offcanvas-end offcanvas-sidebar sidebar-container on-rtl end border-0"
       tabindex="-1" id="shoping-cart-toggle">
       <div class="offcanvas-header position-relative">
          <h5 class="offcanvas-title fw-500" id="offcanvasExampleLabel">
             Shopping cart ( <span class="streamit-cart-count" aria-live="polite">1</span> )
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
       </div>
       <div class="offcanvas-body">
          <div class="product-list-content">
             <ul class="list-unstyled mb-0">
                <li class="mini-cart-item d-flex align-items-start gap-3">
                   <div class="cart-img">
                      <a href="/shop/cart" aria-label="Bag Pack"> 
                         <img src="assets/images/shop/product/01.webp" class="img-fluid" width="300" height="400"
                            alt="Bag Pack">
                      </a>
                   </div>
                   <div class="cart-content flex-grow-1">
                      <div class="d-flex justify-content-between align-items-center">
                         <a class="d-block" href="/shop/product-detail" aria-label="Bag Pack"> 
                            <h6 class="fw-500">Bag Pack</h6>
                         </a>
                         <a href="javascript:void(0)" class="delete-btn">
                            <i class="ph ph-trash text-primary"></i>
                         </a>
                      </div>
                      <div class="product-price text-muted">
                         <span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-amount amount"><span
                                  class="woocommerce-Price-currencySymbol">₹</span>11.05</span></span>
                      </div>
                      <div class="btn-group iq-qty-btn custom-qty-btn rounded-3" data-qty="btn" role="group">
                         <button type="button" class="btn btn-sm btn-outline-light iq-quantity-minus text-white border-0">
                            <i class="ph ph-minus"></i>
                         </button>
                         <input type="text" class="btn btn-sm btn-outline-light input-display border-0" data-qty="input"
                            pattern="^(0|[1-9][0-9]*)$" minlength="1" maxlength="2" value="2" title="Qty">
                         <button type="button" class="btn btn-sm btn-outline-light iq-quantity-plus text-white border-0">
                            <i class="ph ph-plus"></i>
                         </button>
                      </div>
                   </div>
                </li>
             </ul>
          </div>
       </div>
       <div class="offcanvas-footer border-top py-3 px-3">
          <div class="d-flex align-items-center justify-content-between gap-3">
             <strong>Subtotal:</strong>
             <span class="st-woocommerce-Price-amount amount"><span class="woocommerce-Price-amount amount"><span
                      class="woocommerce-Price-currencySymbol">₹</span>11.05</span></span>
          </div>
          <div class="mini-cart-buttons d-flex flex-column align-items-center gap-3 mt-4">
             <div class="iq-button w-100">
                <a href="/shop/checkout" class="btn btn-primary text-capitalize w-100 rounded-3"> 
                   <span class="button-text">Checkout</span>
                </a>
             </div>
             <div class="w-100">
                <a href="/shop/cart" class="btn btn-secondary text-capitalize w-100 rounded-3"> 
                   <span class="button-text">View
                      Cart</span>
                </a>
             </div>
          </div>
       </div>
    </div>    <!--Nav End-->

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