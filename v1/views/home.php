<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: /login'); // Redirect to login
    exit;
}
$current_plan = $_SESSION['plan_name'] ?? 'free'; 
$current_profile_is_kid = $_SESSION['is_kid'] ?? 0;

$pageThemeClass = 'theme-premium'; // Default
if ($current_plan === 'pro') {
    $pageThemeClass = 'theme-pro';
}
if ($current_profile_is_kid) {
    $pageThemeClass .= '-kid'; // e.g., 'theme-pro-kid'
}

// --- 5. FETCH DATA FOR *THIS* PAGE ---
// (Example: Get "New Release" movies)
$newReleases = [];
include 'includes/header.php';
?>

<section>
   <div class="overflow-hidden">
      <div id="home-banner-slider" class="iq-main-slider p-0 swiper banner-home-swiper" data-swiper="home-banner-slider"
         data-pagination="true" data-loop="true">
         <div class="slider m-0 p-0 swiper-wrapper home-slider">
            <div class="swiper-slide slide s-bg-1 p-0">
               <div class="banner-home-swiper-image"
                  style="background-image: url(/assets/images/media/krishna.webp)">
                  <div class="container-fluid position-relative">
                     <div class="row align-items-center iq-ltr-direction h-100 slider-content-full-height">
                        <div class="col-lg-6 col-md-12 col-xl-5">
                           <h2 class="texture-text big-font letter-spacing-1 line-count-1 text-capitalize RightAnimate">
                              Krishna
                           </h2>
                           <div class="d-flex flex-wrap align-items-center gap-3 r-mb-23 RightAnimate-two">
                              <div class="slider-ratting d-flex align-items-center">
                                 <ul
                                    class="ratting-start p-0 m-0 list-inline text-warning d-flex align-items-center justify-content-left">
                                    <li>
                                       <i class="ph-fill ph-star" aria-hidden="true"></i>
                                    </li>
                                    <li>
                                       <i class="ph-fill ph-star" aria-hidden="true"></i>
                                    </li>
                                    <li>
                                       <i class="ph-fill ph-star" aria-hidden="true"></i>
                                    </li>
                                    <li>
                                       <i class="ph-fill ph-star" aria-hidden="true"></i>
                                    </li>
                                    <li>
                                       <i class="ph-fill ph-star-half" aria-hidden="true"></i>
                                    </li>
                                 </ul>
                              </div>
                              <span class="d-flex align-items-center gap-1">
                                 <span class="">5</span><img src="/assets/images/pages/imdb-logo.svg"
                                    alt="imdb logo" class="img-fluid imdb-img">
                              </span>
                              <span class="badge rounded-2 text-white bg-secondary font-size-12">NC-17</span>
                              <div class="d-flex align-items-center gap-1">
                                 <i class="ph ph-clock"></i>
                                 <span class="time">2h : 45m</span>
                              </div>
                           </div>
                           <p class="line-count-3 my-3 RightAnimate-two">Krishna is a mythological fantasy film blending ancient legend and modern warfare. The rebirth of a divine warrior promises to tip the scales in a cosmic battle.
                           </p>
                           <div class="trending-list RightAnimate-three">
                              <div class="text-primary genres font-size-14 mb-1"> Starring:
                                 <a href="view-all-movie.html" class="fw-normal text-decoration-none text-body">Olivia
                                    Foster, </a>
                                 <a href="view-all-movie.html" class="fw-normal text-decoration-none text-body">Leena
                                    Burton, </a>
                                 <a href="view-all-movie.html" class="fw-normal text-decoration-none text-body">Ryan
                                    Pierce </a>
                              </div>
                              <div class="text-primary genres font-size-14 mb-1"> Genres:
                                 <a href="view-all-movie.html" class="fw-normal text-decoration-none text-body">
                                    Action,
                                 </a>
                                 <a href="view-all-movie.html" class="fw-normal text-decoration-none text-body">
                                    Animation,
                                 </a>
                                 <a href="view-all-movie.html" class="fw-normal text-decoration-none text-body">
                                    Mystery
                                 </a>
                              </div>
                              <div class="text-primary tag font-size-14"> Tag:
                                 <a href="view-all-movie.html" class="fw-normal text-decoration-none text-body">
                                    Action,
                                 </a>
                                 <a href="view-all-movie.html" class="fw-normal text-decoration-none text-body ">
                                    Adventure, </a>
                                 <a href="view-all-movie.html" class="fw-normal text-decoration-none text-body ">
                                    Horror
                                 </a>
                              </div>
                           </div>
                           <div class="RightAnimate-four">
                              <a href="/movie-detail.html" class="btn btn-primary text-capitalize position-relative rounded-3">
                                  <span class="d-flex align-items-center gap-2">
                                      <span class="button-text">play
                                                            now</span>
                                      <i class="ph-fill ph-play fs-6"></i>
                                  </span>
                              </a>
                           </div>
                        </div>
                        <div class="col-xl-7 col-lg-6 col-md-12 trailor-video iq-slider d-none d-lg-block">
                           <a data-fslightbox="html5-video"
                              href="https://www.youtube.com/embed/_PhvjbegfBI?autoplay=1&mute=1&loop=1&playlist=_PhvjbegfBI"
                              class="video-open playbtn text-decoration-none" tabindex="0">
                              <svg version="1.1" xmlns="http://www.w3.org/2000/svg"
                                 xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="80px" height="80px"
                                 viewBox="0 0 213.7 213.7" enable-background="new 0 0 213.7 213.7" xml:space="preserve">
                                 <polygon class="triangle" fill="none" stroke-width="7" stroke-linecap="round"
                                    stroke-linejoin="round" stroke-miterlimit="10"
                                    points="73.5,62.5 148.5,105.8 73.5,149.1 ">
                                 </polygon>
                                 <circle class="circle" fill="none" stroke-width="7" stroke-linecap="round"
                                    stroke-linejoin="round" stroke-miterlimit="10" cx="106.8" cy="106.8" r="103.3">
                                 </circle>
                              </svg>
                              <span class="w-trailor text-uppercase"> Watch Trailer </span>
                           </a>
                           <video id="my-video" class="my-video video-js vjs-big-play-centered w-100 d-none" loop
                              autoplay muted preload="auto" data-setup='{
      "techOrder": ["youtube"],
      "sources": [{
        "type": "video/youtube",
        "src": "https://www.youtube.com/embed/_PhvjbegfBI?autoplay=1&mute=1&loop=1&playlist=_PhvjbegfBI"
      }],
      "youtube": {         
        "modestbranding": 1,
        "rel": 0,
        "showinfo": 0,
        "autoplay": 1
      },
      "fullscreen": true
    }'>
                           </video>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
            <div class="swiper-slide slide s-bg-1 p-0">
               <div class="banner-home-swiper-image"
                  style="background-image: url(/assets/images/media/breaking-bad.webp);">
                  <div class="container-fluid position-relative ">
                     <div class="row align-items-center iq-ltr-direction h-100 slider-content-full-height">
                        <div class="col-lg-6 col-md-12 col-xl-5">
                           <h2
                              class="texture-text big-font letter-spacing-1 line-count-1 text-capitalize  RightAnimate">
                              Breaking Bad </h2>
                           <div class="d-flex flex-wrap align-items-center gap-3 r-mb-23 RightAnimate-two">
                              <div class="slider-ratting d-flex align-items-center">
                                 <ul
                                    class="ratting-start p-0 m-0 list-inline text-warning d-flex align-items-center justify-content-left">
                                    <li>
                                       <i class="ph-fill ph-star" aria-hidden="true"></i>
                                    </li>
                                    <li>
                                       <i class="ph-fill ph-star" aria-hidden="true"></i>
                                    </li>
                                    <li>
                                       <i class="ph-fill ph-star" aria-hidden="true"></i>
                                    </li>
                                    <li>
                                       <i class="ph-fill ph-star" aria-hidden="true"></i>
                                    </li>
                                    <li>
                                       <i class="ph-fill ph-star-half" aria-hidden="true"></i>
                                    </li>
                                 </ul>
                              </div>
                              <span class="d-flex align-items-center gap-1">
                                 <span class="">6</span>
                                 <img src="/assets/images/pages/imdb-logo.svg" alt="imdb logo"
                                    class="img-fluid imdb-img">
                              </span>
                              <div class="d-flex align-items-center gap-1">
                                 <span class="time"> 3 Seasons </span>
                              </div>
                           </div>
                           <p class="line-count-3 my-3 RightAnimate-two">Breaking Bad" transcended the confines of
                              television, becoming a symbol of narrative excellence and pushing the boundaries of
                              storytelling. Its impact resonated far beyond the screen, leaving an indelible imprint on
                              popular culture and forever altering the landscape of modern television dramas.
                           </p>
                           <div class="trending-list RightAnimate-three">
                              <div class="text-primary genres font-size-14 mb-1"> Starring:
                                 <a href="view-all-movie.html" class="fw-normal text-decoration-none text-body">Leena
                                    Burton,
                                 </a>
                                 <a href="view-all-movie.html" class="fw-normal text-decoration-none text-body">Michael
                                    Fox,
                                 </a>
                                 <a href="view-all-movie.html" class="fw-normal text-decoration-none text-body">Maxwell
                                    Carter
                                 </a>
                              </div>
                              <div class="text-primary genres font-size-14 mb-1"> Genres:
                                 <a href="view-all-movie.html" class="fw-normal text-decoration-none text-body ">
                                    Action,
                                 </a>
                                 <a href="view-all-movie.html" class="fw-normal text-decoration-none text-body ">
                                    Animation,
                                 </a>
                                 <a href="view-all-movie.html" class="fw-normal text-decoration-none text-body ">
                                    Crime
                                 </a>
                              </div>
                              <div class="text-primary tag font-size-14"> Tag:
                                 <a href="view-all-movie.html" class="fw-normal text-decoration-none text-body ">
                                    Brother,
                                 </a>
                                 <a href="view-all-movie.html" class="fw-normal text-decoration-none text-body ">
                                    Cricket, </a>
                                 <a href="view-all-movie.html" class="fw-normal text-decoration-none text-body ">
                                    Detective
                                 </a>
                              </div>
                           </div>
                           <div class="RightAnimate-four">
                              <a href="/movie-detail.html" class="btn btn-primary text-capitalize position-relative rounded-3">
                                  <span class="d-flex align-items-center gap-2">
                                      <span class="button-text">play
                                                            now</span>
                                      <i class="ph-fill ph-play fs-6"></i>
                                  </span>
                              </a>
                           </div>
                        </div>
                        <div class="col-xl-7 col-lg-6 col-md-12 trailor-video iq-slider d-none d-lg-block">
                           <a data-fslightbox="html5-video"
                              href="https://www.youtube.com/embed/-oOoTIuoL8M?autoplay=1&mute=1&loop=1&playlist=-oOoTIuoL8M"
                              class="video-open playbtn text-decoration-none" tabindex="0">
                              <svg version="1.1" xmlns="http://www.w3.org/2000/svg"
                                 xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="80px" height="80px"
                                 viewBox="0 0 213.7 213.7" enable-background="new 0 0 213.7 213.7" xml:space="preserve">
                                 <polygon class="triangle" fill="none" stroke-width="7" stroke-linecap="round"
                                    stroke-linejoin="round" stroke-miterlimit="10"
                                    points="73.5,62.5 148.5,105.8 73.5,149.1 ">
                                 </polygon>
                                 <circle class="circle" fill="none" stroke-width="7" stroke-linecap="round"
                                    stroke-linejoin="round" stroke-miterlimit="10" cx="106.8" cy="106.8" r="103.3">
                                 </circle>
                              </svg>
                              <span class="w-trailor text-uppercase"> Watch Trailer </span>
                           </a>
                           <video id="my-video2" class=" my-video video-js vjs-big-play-centered w-100 d-none" loop
                              autoplay muted preload="auto" data-setup='{
      "techOrder": ["youtube"],
      "sources": [{
        "type": "video/youtube",
        "src": "https://www.youtube.com/embed/-oOoTIuoL8M?autoplay=1&mute=1&loop=1&playlist=-oOoTIuoL8M"
      }],
      "youtube": {         
        "modestbranding": 1,
        "rel": 0,
        "showinfo": 0,
        "autoplay": 1
      },
      "fullscreen": true
    }'>
                           </video>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
            <div class="swiper-slide slide s-bg-1 p-0">
               <div class="banner-home-swiper-image"
                  style="background-image: url(/assets/images/media/charlie-chaplin.webp);">
                  <div class="container-fluid position-relative">
                     <div class="row align-items-center iq-ltr-direction h-100 slider-content-full-height">
                        <div class="col-lg-6 col-md-12 col-xl-5">
                           <h2 class="texture-text big-font letter-spacing-1 line-count-1 text-capitalize RightAnimate">
                              Charlie Chaplin
                           </h2>
                           <div class="d-flex flex-wrap align-items-center gap-3 r-mb-23 RightAnimate-two">
                              <div class="slider-ratting d-flex align-items-center">
                                 <ul
                                    class="ratting-start p-0 m-0 list-inline text-warning d-flex align-items-center justify-content-left">
                                    <li>
                                       <i class="ph-fill ph-star" aria-hidden="true"></i>
                                    </li>
                                    <li>
                                       <i class="ph-fill ph-star" aria-hidden="true"></i>
                                    </li>
                                    <li>
                                       <i class="ph-fill ph-star" aria-hidden="true"></i>
                                    </li>
                                    <li>
                                       <i class="ph-fill ph-star" aria-hidden="true"></i>
                                    </li>
                                    <li>
                                       <i class="ph-fill ph-star-half" aria-hidden="true"></i>
                                    </li>
                                 </ul>
                              </div>
                              <span class="d-flex align-items-center gap-1">
                                 <span class="">247</span>
                                 <img src="/assets/images/pages/imdb-logo.svg" alt="imdb logo"
                                    class="img-fluid imdb-img">
                              </span>
                              <span class="badge rounded-2 text-white bg-secondary font-size-12">80</span>

                           </div>
                           <p class="line-count-3 my-3 RightAnimate-two"> The Timeless Legacy of Charlie Chaplin" is a
                              captivating documentary that celebrates the life and career of one of cinema's most iconic
                              figures, Sir Charles Spencer Chaplin. This video delves into the genius of the silent film
                              era, exploring his pioneering contributions to comedy, drama, and cinematic storytelling.
                              Viewers will embark on a journey through Chaplin's extraordinary life, witnessing his
                              remarkable journey from a struggling artist to a global superstar.
                           </p>
                           <div class="trending-list RightAnimate-three">
                              <div class="text-primary genres font-size-14 mb-1"> Genres:
                                 <a href="view-all-movie.html" class="fw-normal text-decoration-none text-body ">
                                    Action
                                 </a>
                              </div>
                              <div class="text-primary tag font-size-14"> Tag:
                                 <a href="view-all-movie.html" class="fw-normal text-decoration-none text-body ">
                                    Action,
                                 </a>
                                 <a href="view-all-movie.html" class="fw-normal text-decoration-none text-body ">
                                    Adventure, </a>
                                 <a href="view-all-movie.html" class="fw-normal text-decoration-none text-body ">
                                    Horror
                                 </a>
                              </div>
                           </div>
                           <div class="RightAnimate-four">
                              <a href="/movie-detail.html" class="btn btn-primary text-capitalize position-relative rounded-3">
                                  <span class="d-flex align-items-center gap-2">
                                      <span class="button-text">play
                                                            now</span>
                                      <i class="ph-fill ph-play fs-6"></i>
                                  </span>
                              </a>
                           </div>
                        </div>
                        <div class="col-xl-7 col-lg-6 col-md-12 trailor-video iq-slider d-none d-lg-block">
                           <a data-fslightbox="html5-video"
                              href="https://www.youtube.com/embed/-oOoTIuoL8M?autoplay=1&mute=1&loop=1&playlist=-oOoTIuoL8M"
                              class="video-open playbtn text-decoration-none" tabindex="0">
                              <svg version="1.1" xmlns="http://www.w3.org/2000/svg"
                                 xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="80px" height="80px"
                                 viewBox="0 0 213.7 213.7" enable-background="new 0 0 213.7 213.7" xml:space="preserve">
                                 <polygon class="triangle" fill="none" stroke-width="7" stroke-linecap="round"
                                    stroke-linejoin="round" stroke-miterlimit="10"
                                    points="73.5,62.5 148.5,105.8 73.5,149.1 ">
                                 </polygon>
                                 <circle class="circle" fill="none" stroke-width="7" stroke-linecap="round"
                                    stroke-linejoin="round" stroke-miterlimit="10" cx="106.8" cy="106.8" r="103.3">
                                 </circle>
                              </svg>
                              <span class="w-trailor text-uppercase"> Watch Trailer </span>
                           </a>
                           <video id="my-video3" class="my-video video-js vjs-big-play-centered w-100 d-none" loop
                              autoplay muted preload="auto" data-setup='{
      "techOrder": ["youtube"],
      "sources": [{
        "type": "video/youtube",
        "src": "https://www.youtube.com/embed/-oOoTIuoL8M?autoplay=1&mute=1&loop=1&playlist=-oOoTIuoL8M"
      }],
      "youtube": {         
        "modestbranding": 1,
        "rel": 0,
        "showinfo": 0,
        "autoplay": 1
      },
      "fullscreen": true
    }'>
                           </video>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
         </div>
         <button class="PreArrow-two swiper-arrows d-flex align-items-center justify-content-center d-xl-block d-none"
            style="" id="home-banner-slider-prev"><i class="ph ph-caret-left"></i></button>
         <button class="NextArrow-two swiper-arrows d-flex align-items-center justify-content-center d-xl-block d-none"
            style="" id="home-banner-slider-next"><i class="ph ph-caret-right"></i></button>
         <div class="swiper-pagination d-block d-lg-none"></div>
         <svg xmlns="http://www.w3.org/2000/svg" style="display: none;">
            <symbol xmlns="http://www.w3.org/2000/svg" viewBox="0 0 44 44" width="44px" height="44px" id="circle"
               fill="none" stroke="currentColor">
               <circle r="20" cy="22" cx="22" id="test"></circle>
            </symbol>
         </svg>
      </div>
   </div>
</section>

<div class="container-fluid">
   <div class="overflow-hidden">
      <div class="continue-watching-block home-continue-watch section-padding-top">
         <div class="d-flex align-items-center justify-content-between px-1 mb-2 pb-1 mb-md-4 pb-md-0">
            <h4 class="main-title text-capitalize mb-0 fw-medium">Continue Watch Movies</h4>
         </div>
         <div class="position-relative swiper swiper-card" data-slide="6" data-laptop="3" data-tab="3" data-mobile="2"
            data-mobile-sm="2" data-autoplay="false" data-loop="true" data-navigation="true" data-pagination="false">
            <ul class="p-0 swiper-wrapper m-0  list-inline">
               <li class="swiper-slide">
                  <div class="iq-watching-block">
                      <div class="block-images position-relative">
                          <div class="iq-image-box overly-images">
                              <a href="/movie-detail.html" class="d-block">
                                  <img src="/assets/images/media/gameofhero.webp" alt="movie-card"
                                      class="w-100 d-block border-0 rounded-3 continue-image">
                              </a>
                          </div>
                          <div class="iq-preogress">
                              <span class="px-2 text-white fw-semibold font-size-14 iq-progress-left-data">70 m
                                  Left</span>
                              <div class="d-flex align-items-center justify-content-between px-2 mb-1">
                                  <ul class="list-inline m-0 p-0 d-flex row-gap-1 column-gap-3 flex-wrap movie-list-item">
                                      <li class="iq-preogress-movie-title position-relative font-size-14"><span
                                              class="text-capitalize fw-semibold ">Game of hero</span></li>
                                      <li class="flex-shrink-0 fw-semibold font-size-14">
                                          <span>
                                              Jun-2025
                                          </span>
                                      </li>
                                  </ul>
                                  <a>
                                      <i class="ph-fill ph-play iq-preogress-play-btn fs-6"></i>
                                  </a>
                              </div>
                              <div class="progress" role="progressbar" aria-label="Example 2px high" aria-valuenow="25" aria-valuemin="0"
                                  aria-valuemax="100" style="height: 2px">
                                  <div class="progress-bar" style="width: 50%"></div>
                              </div>
                          </div>
                          <div class="close-icon-section">
                              <div class="position-absolute d-flex align-items-center justify-content-center iq-watching-close-icon" data-bs-toggle="tooltip" data-bs-placement="left" aria-label="Remove from list" data-bs-original-title="Remove from list">
                                  <i class="ph ph-x font-size-14 fw-bold align-middle"></i>
                              </div>
                          </div>
                      </div>
                  </div>               </li>
               <li class="swiper-slide">
                  <div class="iq-watching-block">
                      <div class="block-images position-relative">
                          <div class="iq-image-box overly-images">
                              <a href="/movie-detail.html" class="d-block">
                                  <img src="/assets/images/media/the-first-of-us.webp" alt="movie-card"
                                      class="w-100 d-block border-0 rounded-3 continue-image">
                              </a>
                          </div>
                          <div class="iq-preogress">
                              <span class="px-2 text-white fw-semibold font-size-14 iq-progress-left-data">120 m
                                  Left</span>
                              <div class="d-flex align-items-center justify-content-between px-2 mb-1">
                                  <ul class="list-inline m-0 p-0 d-flex row-gap-1 column-gap-3 flex-wrap movie-list-item">
                                      <li class="iq-preogress-movie-title position-relative font-size-14"><span
                                              class="text-capitalize fw-semibold ">The First of Us</span></li>
                                      <li class="flex-shrink-0 fw-semibold font-size-14">
                                          <span>
                                              Jun-2025
                                          </span>
                                      </li>
                                  </ul>
                                  <a>
                                      <i class="ph-fill ph-play iq-preogress-play-btn fs-6"></i>
                                  </a>
                              </div>
                              <div class="progress" role="progressbar" aria-label="Example 2px high" aria-valuenow="25" aria-valuemin="0"
                                  aria-valuemax="100" style="height: 2px">
                                  <div class="progress-bar" style="width: 30%"></div>
                              </div>
                          </div>
                          <div class="close-icon-section">
                              <div class="position-absolute d-flex align-items-center justify-content-center iq-watching-close-icon" data-bs-toggle="tooltip" data-bs-placement="left" aria-label="Remove from list" data-bs-original-title="Remove from list">
                                  <i class="ph ph-x font-size-14 fw-bold align-middle"></i>
                              </div>
                          </div>
                      </div>
                  </div>               </li>
               <li class="swiper-slide">
                  <div class="iq-watching-block">
                      <div class="block-images position-relative">
                          <div class="iq-image-box overly-images">
                              <a href="/movie-detail.html" class="d-block">
                                  <img src="/assets/images/media/the-co-noueriing.webp" alt="movie-card"
                                      class="w-100 d-block border-0 rounded-3 continue-image">
                              </a>
                          </div>
                          <div class="iq-preogress">
                              <span class="px-2 text-white fw-semibold font-size-14 iq-progress-left-data">60 m
                                  Left</span>
                              <div class="d-flex align-items-center justify-content-between px-2 mb-1">
                                  <ul class="list-inline m-0 p-0 d-flex row-gap-1 column-gap-3 flex-wrap movie-list-item">
                                      <li class="iq-preogress-movie-title position-relative font-size-14"><span
                                              class="text-capitalize fw-semibold ">hunter</span></li>
                                      <li class="flex-shrink-0 fw-semibold font-size-14">
                                          <span>
                                              Dec-2024
                                          </span>
                                      </li>
                                  </ul>
                                  <a>
                                      <i class="ph-fill ph-play iq-preogress-play-btn fs-6"></i>
                                  </a>
                              </div>
                              <div class="progress" role="progressbar" aria-label="Example 2px high" aria-valuenow="25" aria-valuemin="0"
                                  aria-valuemax="100" style="height: 2px">
                                  <div class="progress-bar" style="width: 20%"></div>
                              </div>
                          </div>
                          <div class="close-icon-section">
                              <div class="position-absolute d-flex align-items-center justify-content-center iq-watching-close-icon" data-bs-toggle="tooltip" data-bs-placement="left" aria-label="Remove from list" data-bs-original-title="Remove from list">
                                  <i class="ph ph-x font-size-14 fw-bold align-middle"></i>
                              </div>
                          </div>
                      </div>
                  </div>               </li>
               <li class="swiper-slide">
                  <div class="iq-watching-block">
                      <div class="block-images position-relative">
                          <div class="iq-image-box overly-images">
                              <a href="/movie-detail.html" class="d-block">
                                  <img src="/assets/images/media/giirek.webp" alt="movie-card"
                                      class="w-100 d-block border-0 rounded-3 continue-image">
                              </a>
                          </div>
                          <div class="iq-preogress">
                              <span class="px-2 text-white fw-semibold font-size-14 iq-progress-left-data">45 m
                                  Left</span>
                              <div class="d-flex align-items-center justify-content-between px-2 mb-1">
                                  <ul class="list-inline m-0 p-0 d-flex row-gap-1 column-gap-3 flex-wrap movie-list-item">
                                      <li class="iq-preogress-movie-title position-relative font-size-14"><span
                                              class="text-capitalize fw-semibold ">Giikre</span></li>
                                      <li class="flex-shrink-0 fw-semibold font-size-14">
                                          <span>
                                              Dec-2024
                                          </span>
                                      </li>
                                  </ul>
                                  <a>
                                      <i class="ph-fill ph-play iq-preogress-play-btn fs-6"></i>
                                  </a>
                              </div>
                              <div class="progress" role="progressbar" aria-label="Example 2px high" aria-valuenow="25" aria-valuemin="0"
                                  aria-valuemax="100" style="height: 2px">
                                  <div class="progress-bar" style="width: 20%"></div>
                              </div>
                          </div>
                          <div class="close-icon-section">
                              <div class="position-absolute d-flex align-items-center justify-content-center iq-watching-close-icon" data-bs-toggle="tooltip" data-bs-placement="left" aria-label="Remove from list" data-bs-original-title="Remove from list">
                                  <i class="ph ph-x font-size-14 fw-bold align-middle"></i>
                              </div>
                          </div>
                      </div>
                  </div>               </li>
               <li class="swiper-slide">
                  <div class="iq-watching-block">
                      <div class="block-images position-relative">
                          <div class="iq-image-box overly-images">
                              <a href="/movie-detail.html" class="d-block">
                                  <img src="/assets/images/media/rabbit.webp" alt="movie-card"
                                      class="w-100 d-block border-0 rounded-3 continue-image">
                              </a>
                          </div>
                          <div class="iq-preogress">
                              <span class="px-2 text-white fw-semibold font-size-14 iq-progress-left-data">70 m
                                  Left</span>
                              <div class="d-flex align-items-center justify-content-between px-2 mb-1">
                                  <ul class="list-inline m-0 p-0 d-flex row-gap-1 column-gap-3 flex-wrap movie-list-item">
                                      <li class="iq-preogress-movie-title position-relative font-size-14"><span
                                              class="text-capitalize fw-semibold ">Rabbit</span></li>
                                      <li class="flex-shrink-0 fw-semibold font-size-14">
                                          <span>
                                              May-2025
                                          </span>
                                      </li>
                                  </ul>
                                  <a>
                                      <i class="ph-fill ph-play iq-preogress-play-btn fs-6"></i>
                                  </a>
                              </div>
                              <div class="progress" role="progressbar" aria-label="Example 2px high" aria-valuenow="25" aria-valuemin="0"
                                  aria-valuemax="100" style="height: 2px">
                                  <div class="progress-bar" style="width: 50%"></div>
                              </div>
                          </div>
                          <div class="close-icon-section">
                              <div class="position-absolute d-flex align-items-center justify-content-center iq-watching-close-icon" data-bs-toggle="tooltip" data-bs-placement="left" aria-label="Remove from list" data-bs-original-title="Remove from list">
                                  <i class="ph ph-x font-size-14 fw-bold align-middle"></i>
                              </div>
                          </div>
                      </div>
                  </div>               </li>
               <li class="swiper-slide">
                  <div class="iq-watching-block">
                      <div class="block-images position-relative">
                          <div class="iq-image-box overly-images">
                              <a href="/movie-detail.html" class="d-block">
                                  <img src="/assets/images/media/jumanjj.webp" alt="movie-card"
                                      class="w-100 d-block border-0 rounded-3 continue-image">
                              </a>
                          </div>
                          <div class="iq-preogress">
                              <span class="px-2 text-white fw-semibold font-size-14 iq-progress-left-data">20 m
                                  Left</span>
                              <div class="d-flex align-items-center justify-content-between px-2 mb-1">
                                  <ul class="list-inline m-0 p-0 d-flex row-gap-1 column-gap-3 flex-wrap movie-list-item">
                                      <li class="iq-preogress-movie-title position-relative font-size-14"><span
                                              class="text-capitalize fw-semibold ">Jumanjj</span></li>
                                      <li class="flex-shrink-0 fw-semibold font-size-14">
                                          <span>
                                              March-2025
                                          </span>
                                      </li>
                                  </ul>
                                  <a>
                                      <i class="ph-fill ph-play iq-preogress-play-btn fs-6"></i>
                                  </a>
                              </div>
                              <div class="progress" role="progressbar" aria-label="Example 2px high" aria-valuenow="25" aria-valuemin="0"
                                  aria-valuemax="100" style="height: 2px">
                                  <div class="progress-bar" style="width: 80%"></div>
                              </div>
                          </div>
                          <div class="close-icon-section">
                              <div class="position-absolute d-flex align-items-center justify-content-center iq-watching-close-icon" data-bs-toggle="tooltip" data-bs-placement="left" aria-label="Remove from list" data-bs-original-title="Remove from list">
                                  <i class="ph ph-x font-size-14 fw-bold align-middle"></i>
                              </div>
                          </div>
                      </div>
                  </div>               </li>
               <li class="swiper-slide">
                  <div class="iq-watching-block">
                      <div class="block-images position-relative">
                          <div class="iq-image-box overly-images">
                              <a href="/movie-detail.html" class="d-block">
                                  <img src="/assets/images/media/chosfies.webp" alt="movie-card"
                                      class="w-100 d-block border-0 rounded-3 continue-image">
                              </a>
                          </div>
                          <div class="iq-preogress">
                              <span class="px-2 text-white fw-semibold font-size-14 iq-progress-left-data">80 m
                                  Left</span>
                              <div class="d-flex align-items-center justify-content-between px-2 mb-1">
                                  <ul class="list-inline m-0 p-0 d-flex row-gap-1 column-gap-3 flex-wrap movie-list-item">
                                      <li class="iq-preogress-movie-title position-relative font-size-14"><span
                                              class="text-capitalize fw-semibold ">Chosfies</span></li>
                                      <li class="flex-shrink-0 fw-semibold font-size-14">
                                          <span>
                                              May-2025
                                          </span>
                                      </li>
                                  </ul>
                                  <a>
                                      <i class="ph-fill ph-play iq-preogress-play-btn fs-6"></i>
                                  </a>
                              </div>
                              <div class="progress" role="progressbar" aria-label="Example 2px high" aria-valuenow="25" aria-valuemin="0"
                                  aria-valuemax="100" style="height: 2px">
                                  <div class="progress-bar" style="width: 10%"></div>
                              </div>
                          </div>
                          <div class="close-icon-section">
                              <div class="position-absolute d-flex align-items-center justify-content-center iq-watching-close-icon" data-bs-toggle="tooltip" data-bs-placement="left" aria-label="Remove from list" data-bs-original-title="Remove from list">
                                  <i class="ph ph-x font-size-14 fw-bold align-middle"></i>
                              </div>
                          </div>
                      </div>
                  </div>               </li>
               <li class="swiper-slide">
                  <div class="iq-watching-block">
                      <div class="block-images position-relative">
                          <div class="iq-image-box overly-images">
                              <a href="/movie-detail.html" class="d-block">
                                  <img src="/assets/images/media/john-wick.webp" alt="movie-card"
                                      class="w-100 d-block border-0 rounded-3 continue-image">
                              </a>
                          </div>
                          <div class="iq-preogress">
                              <span class="px-2 text-white fw-semibold font-size-14 iq-progress-left-data">60 m
                                  Left</span>
                              <div class="d-flex align-items-center justify-content-between px-2 mb-1">
                                  <ul class="list-inline m-0 p-0 d-flex row-gap-1 column-gap-3 flex-wrap movie-list-item">
                                      <li class="iq-preogress-movie-title position-relative font-size-14"><span
                                              class="text-capitalize fw-semibold ">John Wick</span></li>
                                      <li class="flex-shrink-0 fw-semibold font-size-14">
                                          <span>
                                              Jan-2025
                                          </span>
                                      </li>
                                  </ul>
                                  <a>
                                      <i class="ph-fill ph-play iq-preogress-play-btn fs-6"></i>
                                  </a>
                              </div>
                              <div class="progress" role="progressbar" aria-label="Example 2px high" aria-valuenow="25" aria-valuemin="0"
                                  aria-valuemax="100" style="height: 2px">
                                  <div class="progress-bar" style="width: 10%"></div>
                              </div>
                          </div>
                          <div class="close-icon-section">
                              <div class="position-absolute d-flex align-items-center justify-content-center iq-watching-close-icon" data-bs-toggle="tooltip" data-bs-placement="left" aria-label="Remove from list" data-bs-original-title="Remove from list">
                                  <i class="ph ph-x font-size-14 fw-bold align-middle"></i>
                              </div>
                          </div>
                      </div>
                  </div>               </li>
               <li class="swiper-slide">
                  <div class="iq-watching-block">
                      <div class="block-images position-relative">
                          <div class="iq-image-box overly-images">
                              <a href="/movie-detail.html" class="d-block">
                                  <img src="/assets/images/media/fast-furious.webp" alt="movie-card"
                                      class="w-100 d-block border-0 rounded-3 continue-image">
                              </a>
                          </div>
                          <div class="iq-preogress">
                              <span class="px-2 text-white fw-semibold font-size-14 iq-progress-left-data">10 m
                                  Left</span>
                              <div class="d-flex align-items-center justify-content-between px-2 mb-1">
                                  <ul class="list-inline m-0 p-0 d-flex row-gap-1 column-gap-3 flex-wrap movie-list-item">
                                      <li class="iq-preogress-movie-title position-relative font-size-14"><span
                                              class="text-capitalize fw-semibold ">Fast &amp; Furious</span></li>
                                      <li class="flex-shrink-0 fw-semibold font-size-14">
                                          <span>
                                              Feb-2025
                                          </span>
                                      </li>
                                  </ul>
                                  <a>
                                      <i class="ph-fill ph-play iq-preogress-play-btn fs-6"></i>
                                  </a>
                              </div>
                              <div class="progress" role="progressbar" aria-label="Example 2px high" aria-valuenow="25" aria-valuemin="0"
                                  aria-valuemax="100" style="height: 2px">
                                  <div class="progress-bar" style="width: 10%"></div>
                              </div>
                          </div>
                          <div class="close-icon-section">
                              <div class="position-absolute d-flex align-items-center justify-content-center iq-watching-close-icon" data-bs-toggle="tooltip" data-bs-placement="left" aria-label="Remove from list" data-bs-original-title="Remove from list">
                                  <i class="ph ph-x font-size-14 fw-bold align-middle"></i>
                              </div>
                          </div>
                      </div>
                  </div>               </li>
               <li class="swiper-slide">
                  <div class="iq-watching-block">
                      <div class="block-images position-relative">
                          <div class="iq-image-box overly-images">
                              <a href="/movie-detail.html" class="d-block">
                                  <img src="/assets/images/media/venom.webp" alt="movie-card"
                                      class="w-100 d-block border-0 rounded-3 continue-image">
                              </a>
                          </div>
                          <div class="iq-preogress">
                              <span class="px-2 text-white fw-semibold font-size-14 iq-progress-left-data">10 m
                                  Left</span>
                              <div class="d-flex align-items-center justify-content-between px-2 mb-1">
                                  <ul class="list-inline m-0 p-0 d-flex row-gap-1 column-gap-3 flex-wrap movie-list-item">
                                      <li class="iq-preogress-movie-title position-relative font-size-14"><span
                                              class="text-capitalize fw-semibold ">Venom</span></li>
                                      <li class="flex-shrink-0 fw-semibold font-size-14">
                                          <span>
                                              Dec-2024
                                          </span>
                                      </li>
                                  </ul>
                                  <a>
                                      <i class="ph-fill ph-play iq-preogress-play-btn fs-6"></i>
                                  </a>
                              </div>
                              <div class="progress" role="progressbar" aria-label="Example 2px high" aria-valuenow="25" aria-valuemin="0"
                                  aria-valuemax="100" style="height: 2px">
                                  <div class="progress-bar" style="width: 10%"></div>
                              </div>
                          </div>
                          <div class="close-icon-section">
                              <div class="position-absolute d-flex align-items-center justify-content-center iq-watching-close-icon" data-bs-toggle="tooltip" data-bs-placement="left" aria-label="Remove from list" data-bs-original-title="Remove from list">
                                  <i class="ph ph-x font-size-14 fw-bold align-middle"></i>
                              </div>
                          </div>
                      </div>
                  </div>               </li>
               <li class="swiper-slide">
                  <div class="iq-watching-block">
                      <div class="block-images position-relative">
                          <div class="iq-image-box overly-images">
                              <a href="/movie-detail.html" class="d-block">
                                  <img src="/assets/images/media/krishna.webp" alt="movie-card"
                                      class="w-100 d-block border-0 rounded-3 continue-image">
                              </a>
                          </div>
                          <div class="iq-preogress">
                              <span class="px-2 text-white fw-semibold font-size-14 iq-progress-left-data">30 m
                                  Left</span>
                              <div class="d-flex align-items-center justify-content-between px-2 mb-1">
                                  <ul class="list-inline m-0 p-0 d-flex row-gap-1 column-gap-3 flex-wrap movie-list-item">
                                      <li class="iq-preogress-movie-title position-relative font-size-14"><span
                                              class="text-capitalize fw-semibold ">Krishna</span></li>
                                      <li class="flex-shrink-0 fw-semibold font-size-14">
                                          <span>
                                              May-2025
                                          </span>
                                      </li>
                                  </ul>
                                  <a>
                                      <i class="ph-fill ph-play iq-preogress-play-btn fs-6"></i>
                                  </a>
                              </div>
                              <div class="progress" role="progressbar" aria-label="Example 2px high" aria-valuenow="25" aria-valuemin="0"
                                  aria-valuemax="100" style="height: 2px">
                                  <div class="progress-bar" style="width: 10%"></div>
                              </div>
                          </div>
                          <div class="close-icon-section">
                              <div class="position-absolute d-flex align-items-center justify-content-center iq-watching-close-icon" data-bs-toggle="tooltip" data-bs-placement="left" aria-label="Remove from list" data-bs-original-title="Remove from list">
                                  <i class="ph ph-x font-size-14 fw-bold align-middle"></i>
                              </div>
                          </div>
                      </div>
                  </div>               </li>
               <li class="swiper-slide">
                  <div class="iq-watching-block">
                      <div class="block-images position-relative">
                          <div class="iq-image-box overly-images">
                              <a href="/movie-detail.html" class="d-block">
                                  <img src="/assets/images/media/deadpool.webp" alt="movie-card"
                                      class="w-100 d-block border-0 rounded-3 continue-image">
                              </a>
                          </div>
                          <div class="iq-preogress">
                              <span class="px-2 text-white fw-semibold font-size-14 iq-progress-left-data">50 m
                                  Left</span>
                              <div class="d-flex align-items-center justify-content-between px-2 mb-1">
                                  <ul class="list-inline m-0 p-0 d-flex row-gap-1 column-gap-3 flex-wrap movie-list-item">
                                      <li class="iq-preogress-movie-title position-relative font-size-14"><span
                                              class="text-capitalize fw-semibold ">Deadpool</span></li>
                                      <li class="flex-shrink-0 fw-semibold font-size-14">
                                          <span>
                                              Jul-2025
                                          </span>
                                      </li>
                                  </ul>
                                  <a>
                                      <i class="ph-fill ph-play iq-preogress-play-btn fs-6"></i>
                                  </a>
                              </div>
                              <div class="progress" role="progressbar" aria-label="Example 2px high" aria-valuenow="25" aria-valuemin="0"
                                  aria-valuemax="100" style="height: 2px">
                                  <div class="progress-bar" style="width: 10%"></div>
                              </div>
                          </div>
                          <div class="close-icon-section">
                              <div class="position-absolute d-flex align-items-center justify-content-center iq-watching-close-icon" data-bs-toggle="tooltip" data-bs-placement="left" aria-label="Remove from list" data-bs-original-title="Remove from list">
                                  <i class="ph ph-x font-size-14 fw-bold align-middle"></i>
                              </div>
                          </div>
                      </div>
                  </div>               </li>
            </ul>
            <div class="d-none d-lg-block">
               <div class="swiper-button swiper-button-next"></div>
               <div class="swiper-button swiper-button-prev"></div>
            </div>
         </div>
      </div>

      <div class="upcomimg-block section-wraper">
         <div class="d-flex align-items-center justify-content-between px-1 mb-2 pb-1 mb-md-4 pb-md-0">
            <h4 class="main-title text-capitalize mb-0 fw-medium">Upcoming Movies</h4>
         </div>
         <div class="card-style-slider">
            <div class="position-relative swiper swiper-card" data-slide="6" data-laptop="4" data-tab="3"
               data-mobile="2" data-mobile-sm="2" data-autoplay="false" data-loop="true" data-navigation="true"
               data-pagination="true">
               <ul class="p-0 swiper-wrapper m-0  list-inline">
                  <li class="swiper-slide">
                     <div class="iq-card card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/movie-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/the-first-of-us-portrait.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize">
                                 <a href="/movie-detail.html">The First of Us</a>
                               </h5>
                               <div class="d-flex align-items-center gap-3">
                                 <div class="d-flex align-items-center gap-2">
                                   <i class="ph ph-translate"></i>
                                   <small class="font-size-12">English</small>
                                 </div>
                               </div>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/movie-detail.html" class="btn btn-primary w-100">Play Now
                               </a>
                             </div>
                           </div>
                     
                         </div>
                         <div class="position-absolute z-1 primium-product d-flex align-items-center justify-content-center"
                           data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Premium" data-bs-original-title="Premium">
                           <i class="ph-fill ph-crown "></i>
                         </div>
                       </div>
                     
                     </div>
                  </li>
                  <li class="swiper-slide">
                     <div class="iq-card card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/movie-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/the-co-noueriing-portrait.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize">
                                 <a href="/movie-detail.html">The Co-noueriing</a>
                               </h5>
                               <div class="d-flex align-items-center gap-3">
                                 <div class="d-flex align-items-center gap-2">
                                   <i class="ph ph-translate"></i>
                                   <small class="font-size-12">English</small>
                                 </div>
                               </div>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/movie-detail.html" class="btn btn-primary w-100">Play Now
                               </a>
                             </div>
                           </div>
                     
                         </div>
                       </div>
                     
                     </div>
                  </li>
                  <li class="swiper-slide">
                     <div class="iq-card card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/movie-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/giirek-portrait.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize">
                                 <a href="/movie-detail.html">Giirek</a>
                               </h5>
                               <div class="d-flex align-items-center gap-3">
                                 <div class="d-flex align-items-center gap-2">
                                   <i class="ph ph-translate"></i>
                                   <small class="font-size-12">English</small>
                                 </div>
                               </div>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/movie-detail.html" class="btn btn-primary w-100">Play Now
                               </a>
                             </div>
                           </div>
                     
                         </div>
                         <div class="position-absolute z-1 primium-product d-flex align-items-center justify-content-center"
                           data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Premium" data-bs-original-title="Premium">
                           <i class="ph-fill ph-crown "></i>
                         </div>
                       </div>
                     
                     </div>
                  </li>
                  <li class="swiper-slide">
                     <div class="iq-card card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/movie-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/another-danger-portrait.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize">
                                 <a href="/movie-detail.html">Another Danger</a>
                               </h5>
                               <div class="d-flex align-items-center gap-3">
                                 <div class="d-flex align-items-center gap-2">
                                   <i class="ph ph-translate"></i>
                                   <small class="font-size-12">English</small>
                                 </div>
                               </div>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/movie-detail.html" class="btn btn-primary w-100">Play Now
                               </a>
                             </div>
                           </div>
                     
                         </div>
                       </div>
                     
                     </div>
                  </li>
                  <li class="swiper-slide">
                     <div class="iq-card card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/movie-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/kung-fu-panda-portrait.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize">
                                 <a href="/movie-detail.html">Kung fu
                                          Panda</a>
                               </h5>
                               <div class="d-flex align-items-center gap-3">
                                 <div class="d-flex align-items-center gap-2">
                                   <i class="ph ph-translate"></i>
                                   <small class="font-size-12">English</small>
                                 </div>
                               </div>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/movie-detail.html" class="btn btn-primary w-100">Play Now
                               </a>
                             </div>
                           </div>
                     
                         </div>
                       </div>
                     
                     </div>
                  </li>
                  <li class="swiper-slide">
                     <div class="iq-card card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/movie-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/john-wick-portrait.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize">
                                 <a href="/movie-detail.html">John Wick</a>
                               </h5>
                               <div class="d-flex align-items-center gap-3">
                                 <div class="d-flex align-items-center gap-2">
                                   <i class="ph ph-translate"></i>
                                   <small class="font-size-12">English</small>
                                 </div>
                               </div>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/movie-detail.html" class="btn btn-primary w-100">Play Now
                               </a>
                             </div>
                           </div>
                     
                         </div>
                         <div class="position-absolute z-1 primium-product d-flex align-items-center justify-content-center"
                           data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Premium" data-bs-original-title="Premium">
                           <i class="ph-fill ph-crown "></i>
                         </div>
                       </div>
                     
                     </div>
                  </li>
                  <li class="swiper-slide">
                     <div class="iq-card card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/movie-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/spiderman-portrait.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize">
                                 <a href="/movie-detail.html">Spiderman</a>
                               </h5>
                               <div class="d-flex align-items-center gap-3">
                                 <div class="d-flex align-items-center gap-2">
                                   <i class="ph ph-translate"></i>
                                   <small class="font-size-12">English</small>
                                 </div>
                               </div>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/movie-detail.html" class="btn btn-primary w-100">Play Now
                               </a>
                             </div>
                           </div>
                     
                         </div>
                       </div>
                     
                     </div>
                  </li>
               </ul>
               <div class="d-none d-lg-block">
                  <div class="swiper-button swiper-button-next"></div>
                  <div class="swiper-button swiper-button-prev"></div>
               </div>
            </div>
         </div>
      </div>

      <div class="streamit-card-height-block">
         <div class="d-flex align-items-center justify-content-between  px-1 mb-4">
            <h4 class="main-title text-capitalize mb-0 fw-medium">Best in TV</h4>
            <a href="view-all-movie.html" class="text-primary iq-view-all text-decoration-none flex-none">View
               All</a>
         </div>
         <div class="card-style-slider">
            <div class="position-relative swiper swiper-card" data-slide="5" data-laptop="3" data-tab="3"
               data-mobile="2" data-mobile-sm="2" data-autoplay="false" data-loop="false" data-navigation="true"
               data-pagination="true">
               <ul class="p-0 swiper-wrapper m-0  list-inline">
                  <li class="swiper-slide">
                     <div class="iq-card card-hover landscape-card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/movie-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/migration-portrait.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize mb-0">
                                 <a href="/movie-detail.html">Migration</a>
                               </h5>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/movie-detail.html" class="btn btn-primary w-100">Play Now</a>
                             </div>
                           </div>
                     
                         </div>
                         <div class="position-absolute z-1 premium-product d-flex align-items-center justify-content-center"
                           data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Premium" data-bs-original-title="Premium">
                           <i class="ph-fill ph-crown "></i>
                         </div>
                       </div>
                     
                     </div>
                  </li>
                  <li class="swiper-slide">
                     <div class="iq-card card-hover landscape-card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/movie-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/giirek.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize mb-0">
                                 <a href="/movie-detail.html">Giirek</a>
                               </h5>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/movie-detail.html" class="btn btn-primary w-100">Play Now</a>
                             </div>
                           </div>
                     
                         </div>
                       </div>
                     
                     </div>
                  </li>
                  <li class="swiper-slide">
                     <div class="iq-card card-hover landscape-card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/movie-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/john-wick.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize mb-0">
                                 <a href="/movie-detail.html">John Wick</a>
                               </h5>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/movie-detail.html" class="btn btn-primary w-100">Play Now</a>
                             </div>
                           </div>
                     
                         </div>
                         <div class="position-absolute z-1 premium-product d-flex align-items-center justify-content-center"
                           data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Premium" data-bs-original-title="Premium">
                           <i class="ph-fill ph-crown "></i>
                         </div>
                       </div>
                     
                     </div>
                  </li>
                  <li class="swiper-slide">
                     <div class="iq-card card-hover landscape-card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/movie-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/war-for-the-planet.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize mb-0">
                                 <a href="/movie-detail.html">War for the Planet</a>
                               </h5>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/movie-detail.html" class="btn btn-primary w-100">Play Now</a>
                             </div>
                           </div>
                     
                         </div>
                       </div>
                     
                     </div>
                  </li>
                  <li class="swiper-slide">
                     <div class="iq-card card-hover landscape-card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/movie-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/dinoosaur.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize mb-0">
                                 <a href="/movie-detail.html">Dinoosaur</a>
                               </h5>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/movie-detail.html" class="btn btn-primary w-100">Play Now</a>
                             </div>
                           </div>
                     
                         </div>
                       </div>
                     
                     </div>
                  </li>
                  <li class="swiper-slide">
                     <div class="iq-card card-hover landscape-card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/movie-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/Bumblebee.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize mb-0">
                                 <a href="/movie-detail.html">Bumblebee</a>
                               </h5>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/movie-detail.html" class="btn btn-primary w-100">Play Now</a>
                             </div>
                           </div>
                     
                         </div>
                       </div>
                     
                     </div>
                  </li>
               </ul>
               <div class="swiper-button swiper-button-next"></div>
               <div class="swiper-button swiper-button-prev"></div>
            </div>
         </div>
      </div>

      <div class="latest-block section-wraper">
         <div class="d-flex align-items-center justify-content-between px-1 mb-2 pb-1 mb-md-4 pb-md-0">
            <h4 class="main-title text-capitalize mb-0 fw-medium">latest Movies</h4>
         </div>
         <div class="card-style-slider">
            <div class="position-relative swiper swiper-card" data-slide="6" data-laptop="4" data-tab="3"
               data-mobile="2" data-mobile-sm="2" data-autoplay="false" data-loop="true" data-navigation="true"
               data-pagination="true">
               <ul class="p-0 swiper-wrapper m-0  list-inline">
                  <li class="swiper-slide">
                     <div class="iq-card card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/movie-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/deadpool-portrait.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize">
                                 <a href="/movie-detail.html">Deadpool</a>
                               </h5>
                               <div class="d-flex align-items-center gap-3">
                                 <div class="d-flex align-items-center gap-2">
                                   <i class="ph ph-translate"></i>
                                   <small class="font-size-12">English</small>
                                 </div>
                               </div>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/movie-detail.html" class="btn btn-primary w-100">Play Now
                               </a>
                             </div>
                           </div>
                     
                         </div>
                       </div>
                     
                     </div>
                  </li>
                  <li class="swiper-slide">
                     <div class="iq-card card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/movie-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/the-hunter-portrait.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize">
                                 <a href="/movie-detail.html">The
                                          Hunter</a>
                               </h5>
                               <div class="d-flex align-items-center gap-3">
                                 <div class="d-flex align-items-center gap-2">
                                   <i class="ph ph-translate"></i>
                                   <small class="font-size-12">English</small>
                                 </div>
                               </div>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/movie-detail.html" class="btn btn-primary w-100">Play Now
                               </a>
                             </div>
                           </div>
                     
                         </div>
                       </div>
                     
                     </div>
                  </li>
                  <li class="swiper-slide">
                     <div class="iq-card card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/movie-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/gameofhero-portrait.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize">
                                 <a href="/movie-detail.html">Game of
                                          Hero</a>
                               </h5>
                               <div class="d-flex align-items-center gap-3">
                                 <div class="d-flex align-items-center gap-2">
                                   <i class="ph ph-translate"></i>
                                   <small class="font-size-12">English</small>
                                 </div>
                               </div>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/movie-detail.html" class="btn btn-primary w-100">Play Now
                               </a>
                             </div>
                           </div>
                     
                         </div>
                       </div>
                     
                     </div>
                  </li>
                  <li class="swiper-slide">
                     <div class="iq-card card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/movie-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/the-first-of-us-portrait.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize">
                                 <a href="/movie-detail.html">ariivaal</a>
                               </h5>
                               <div class="d-flex align-items-center gap-3">
                                 <div class="d-flex align-items-center gap-2">
                                   <i class="ph ph-translate"></i>
                                   <small class="font-size-12">English</small>
                                 </div>
                               </div>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/movie-detail.html" class="btn btn-primary w-100">Play Now
                               </a>
                             </div>
                           </div>
                     
                         </div>
                       </div>
                     
                     </div>
                  </li>
                  <li class="swiper-slide">
                     <div class="iq-card card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/movie-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/rabbit-portrait.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize">
                                 <a href="/movie-detail.html">Rabbit</a>
                               </h5>
                               <div class="d-flex align-items-center gap-3">
                                 <div class="d-flex align-items-center gap-2">
                                   <i class="ph ph-translate"></i>
                                   <small class="font-size-12">English</small>
                                 </div>
                               </div>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/movie-detail.html" class="btn btn-primary w-100">Play Now
                               </a>
                             </div>
                           </div>
                     
                         </div>
                       </div>
                     
                     </div>
                  </li>
                  <li class="swiper-slide">
                     <div class="iq-card card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/movie-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/migration-portrait.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize">
                                 <a href="/movie-detail.html">Migration</a>
                               </h5>
                               <div class="d-flex align-items-center gap-3">
                                 <div class="d-flex align-items-center gap-2">
                                   <i class="ph ph-translate"></i>
                                   <small class="font-size-12">English</small>
                                 </div>
                               </div>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/movie-detail.html" class="btn btn-primary w-100">Play Now
                               </a>
                             </div>
                           </div>
                     
                         </div>
                       </div>
                     
                     </div>
                  </li>
                  <li class="swiper-slide">
                     <div class="iq-card card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/movie-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/chosfies-portrait.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize">
                                 <a href="/movie-detail.html">Chosfies</a>
                               </h5>
                               <div class="d-flex align-items-center gap-3">
                                 <div class="d-flex align-items-center gap-2">
                                   <i class="ph ph-translate"></i>
                                   <small class="font-size-12">English</small>
                                 </div>
                               </div>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/movie-detail.html" class="btn btn-primary w-100">Play Now
                               </a>
                             </div>
                           </div>
                     
                         </div>
                       </div>
                     
                     </div>
                  </li>
               </ul>
               <div class="d-none d-lg-block">
                  <div class="swiper-button swiper-button-next"></div>
                  <div class="swiper-button swiper-button-prev"></div>
               </div>
            </div>
         </div>
      </div>
   </div>
</div>

<div class="verticle-slider section-padding-bottom">
   <div class="slider">
      <div class="slider-flex position-relative">
         <div class="slider--col position-relative">
            <div class="vertical-slider-prev swiper-button"><i class="iconly-Arrow-Up-2 icli"></i></div>
            <div class="slider-thumbs" data-swiper="slider-thumbs">
               <div class="swiper-container " data-swiper="slider-thumbs-inner">
                  <div class="swiper-wrapper top-ten-slider-nav">
                     <div class="swiper-slide swiper-bg">
                        <div class="block-images position-relative ">
                           <div class="img-box slider--image">
                              <img src="/assets/images/media/the-first-of-us.webp" class="w-100 rounded-3" alt="img"
                                 loading="lazy">
                           </div>
                           <div class="block-description">
                              <h6 class="iq-title">The First Of Us</h6>
                              <div class="movie-time d-flex align-items-center my-2">
                                 <div class="d-flex align-items-center gap-1 font-size-12">
                                    <i class="ph ph-clock "></i>
                                    <span class="text-body">2hr : 59m</span>
                                 </div>
                              </div>
                           </div>
                        </div>
                     </div>
                     <div class="swiper-slide swiper-bg">
                        <div class="block-images position-relative">
                           <div class="img-box slider--image">
                              <img src="/assets/images/media/gameofhero.webp" class="w-100 rounded-3" alt="img"
                                 loading="lazy">
                           </div>
                           <div class="block-description">
                              <h6 class="iq-title">Game of Heros</h6>
                              <div class="movie-time d-flex align-items-center my-2">
                                 <div class="d-flex align-items-center gap-1 font-size-12">
                                    <i class="ph ph-clock"></i>
                                    <span class="text-body"> 2hr : 30m</span>
                                 </div>
                              </div>
                           </div>
                        </div>
                     </div>
                     <div class="swiper-slide swiper-bg">
                        <div class="block-images position-relative ">
                           <div class="img-box slider--image">
                              <img src="/assets/images/media/venom.webp" class="w-100 rounded-3" alt="img"
                                 loading="lazy">
                           </div>
                           <div class="block-description">
                              <h6 class="iq-title">Venom</h6>
                              <div class="movie-time d-flex align-items-center my-2">
                                 <div class="d-flex align-items-center gap-1 font-size-12">
                                    <i class="ph ph-clock"></i>
                                    <span class="text-body">2hr : 3m</span>
                                 </div>
                              </div>
                           </div>
                        </div>
                     </div>
                     <div class="swiper-slide swiper-bg">
                        <div class="block-images position-relative">
                           <div class="img-box slider--image">
                              <img src="/assets/images/media/rabbit.webp" class="w-100 rounded-3" alt="img"
                                 loading="lazy">
                           </div>
                           <div class="block-description">
                              <h6 class="iq-title">Rabbit</h6>
                              <div class="movie-time d-flex align-items-center my-2">
                                 <div class="d-flex align-items-center gap-1 font-size-12">
                                    <i class="ph ph-clock"></i>
                                    <span class="text-body">2hr : 30m</span>
                                 </div>
                              </div>
                           </div>
                        </div>
                     </div>
                     <div class="swiper-slide swiper-bg">
                        <div class="block-images position-relative">
                           <div class="img-box slider--image">
                              <img src="/assets/images/media/krishna.webp" class="w-100 rounded-3" alt="img"
                                 loading="lazy">
                           </div>
                           <div class="block-description">
                              <h6 class="iq-title">Krishna</h6>
                              <div class="movie-time d-flex align-items-center my-2">
                                 <div class="d-flex align-items-center gap-1 font-size-12">
                                    <i class="ph ph-clock"></i>
                                    <span class="text-body">1hr : 22m</span>
                                 </div>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
            <div class="vertical-slider-next swiper-button"><i class="iconly-Arrow-Down-2 icli"></i></div>
         </div>
         <div class="slider-images" data-swiper="slider-images">
            <div class="swiper-container " data-swiper="slider-images-inner">
               <div class="swiper-wrapper ">
                  <div class="swiper-slide">
                     <div class="slider--image block-images"><img src="/assets/images/media/the-first-of-us.webp"
                           loading="lazy" alt="img" /></div>
                     <div class="description">
                        <div class="block-description">
                           <ul
                              class="ps-0 mb-2 pb-1 list-inline d-flex flex-wrap align-items-center movie-tag justify-content-center justify-content-lg-start genres-list gap-1 gap-sm-0">
                              <li class="text-capitalize font-size-14 letter-spacing-1">
                                 <a href="view-all-movie.html" class="text-decoration-none">Action</a>
                              </li>
                              <li class="text-capitalize font-size-14 letter-spacing-1">
                                 <a href="view-all-movie.html" class="text-decoration-none">Adventure </a>
                              </li>
                              <li class="text-capitalize font-size-14 letter-spacing-1">
                                 <a href="view-all-movie.html" class="text-decoration-none">Crime</a>
                              </li>
                              <li class="text-capitalize font-size-14 letter-spacing-1">
                                 <a href="view-all-movie.html" class="text-decoration-none">Horror</a>
                              </li>
                              <li class="text-capitalize font-size-14 letter-spacing-1">
                                 <a href="view-all-movie.html" class="text-decoration-none">Mystery</a>
                              </li>
                           </ul>
                           <h2 class="iq-title m-0 line-count-2"><a href="/tv-show-detail.html">The First Of
                                 Us</a></h2>
                           <div
                              class="d-flex align-items-center gap-3 py-2 justify-content-center justify-content-lg-start flex-wrap">
                              <div class="slider-ratting d-flex align-items-center gap-1">
                                 <ul
                                    class="ratting-start p-0 m-0 list-inline text-warning d-flex align-items-center justify-content-left">
                                    <li>
                                       <i class="ph-fill ph-star" aria-hidden="true"></i>
                                    </li>
                                    <li>
                                       <i class="ph-fill ph-star" aria-hidden="true"></i>
                                    </li>
                                    <li>
                                       <i class="ph-fill ph-star" aria-hidden="true"></i>
                                    </li>
                                    <li>
                                       <i class="ph-fill ph-star" aria-hidden="true"></i>
                                    </li>
                                    <li>
                                       <i class="ph-fill ph-star" aria-hidden="true"></i>
                                    </li>
                                 </ul>
                              </div>
                              <div class="d-flex align-items-center gap-1">
                                 <p class="mb-0">9 </p>
                                 <img class="imdb-img" alt="imdb-logo" src="/assets/images/pages/imdb-logo.svg">
                              </div>
                              <div class="d-flex align-items-center gap-1">
                                 <i class="ph ph-clock font-size-14"></i>
                                 <span class="text-body">2hr : 59m</span>
                              </div>
                           </div>
                           <p class="mt-2 mb-3 line-count-3">The First of Us – In a post-apocalyptic world, a small
                              group of survivors uncovers the origins of humanity’s downfall. As they journey through a
                              dangerous, desolate landscape, they realize they may hold the key to rebuilding
                              civilization—or ensuring its final extinction.</p>
                           <a href="/movie-detail.html" class="btn btn-primary text-capitalize position-relative rounded-3">
                               <span class="d-flex align-items-center gap-2">
                                   <span class="button-text">play now</span>
                                   <i class="ph-fill ph-play fs-6"></i>
                               </span>
                           </a>
                        </div>
                     </div>
                  </div>
                  <div class="swiper-slide">
                     <div class="slider--image block-images"><img src="/assets/images/media/gameofhero.webp"
                           loading="lazy" alt="img" /></div>
                     <div class="description">
                        <div class="block-description">
                           <ul
                              class="ps-0 mb-2 pb-1 list-inline d-flex flex-wrap align-items-center movie-tag justify-content-center justify-content-lg-start genres-list gap-1 gap-sm-0">
                              <li class="text-capitalize font-size-14 letter-spacing-1">
                                 <a href="view-all-movie.html" class="text-decoration-none">Action</a>
                              </li>
                              <li class="text-capitalize font-size-14 letter-spacing-1">
                                 <a href="view-all-movie.html" class="text-decoration-none">Adventure </a>
                              </li>
                              <li class="text-capitalize font-size-14 letter-spacing-1">
                                 <a href="view-all-movie.html" class="text-decoration-none">Crime</a>
                              </li>
                              <li class="text-capitalize font-size-14 letter-spacing-1">
                                 <a href="view-all-movie.html" class="text-decoration-none">Horror</a>
                              </li>
                              <li class="text-capitalize font-size-14 letter-spacing-1">
                                 <a href="view-all-movie.html" class="text-decoration-none">Mystery</a>
                              </li>
                           </ul>
                           <h2 class="iq-title m-0 line-count-2"><a href="/tv-show-detail.html">Game of Heros</a>
                           </h2>
                           <div
                              class="d-flex align-items-center gap-3 py-2 justify-content-center justify-content-lg-start flex-wrap">
                              <div class="slider-ratting d-flex align-items-center gap-1">
                                 <ul
                                    class="ratting-start p-0 m-0 list-inline text-warning d-flex align-items-center justify-content-left">
                                    <li>
                                       <i class="ph-fill ph-star" aria-hidden="true"></i>
                                    </li>
                                    <li>
                                       <i class="ph-fill ph-star" aria-hidden="true"></i>
                                    </li>
                                    <li>
                                       <i class="ph-fill ph-star" aria-hidden="true"></i>
                                    </li>
                                    <li>
                                       <i class="ph ph-star" aria-hidden="true"></i>
                                    </li>
                                    <li>
                                       <i class="ph ph-star" aria-hidden="true"></i>
                                    </li>
                                 </ul>
                              </div>
                              <div class="d-flex align-items-center gap-1">
                                 <p class="mb-0">10 </p>
                                 <img class="imdb-img" alt="imdb-logo" src="/assets/images/pages/imdb-logo.svg">
                              </div>
                              <div class="d-flex align-items-center gap-1">
                                 <i class="ph ph-clock font-size-14"></i>
                                 <span class="text-body">2hr : 30m</span>
                              </div>
                           </div>
                           <p class="mt-2 mb-3 line-count-3">Game of Heros is an action-packed fantasy epic where the
                              fate of the world is decided in a battle of legendary warriors. When an ancient prophecy
                              foretells an all-out war between the greatest heroes of all realms, champions from
                              different eras and dimensions are summoned to fight for ultimate supremacy. Each warrior
                              possesses unique abilities, weapons, and a past that drives them to victory—or doom.</p>
                           <a href="/movie-detail.html" class="btn btn-primary text-capitalize position-relative rounded-3">
                               <span class="d-flex align-items-center gap-2">
                                   <span class="button-text">play now</span>
                                   <i class="ph-fill ph-play fs-6"></i>
                               </span>
                           </a>
                        </div>
                     </div>
                  </div>
                  <div class="swiper-slide">
                     <div class="slider--image block-images"><img src="/assets/images/media/venom.webp"
                           loading="lazy" alt="img" /></div>
                     <div class="description">
                        <div class="block-description">
                           <ul
                              class="ps-0 mb-2 pb-1 list-inline d-flex flex-wrap align-items-center movie-tag justify-content-center justify-content-lg-start genres-list gap-1 gap-sm-0">
                              <li class="text-capitalize font-size-14 letter-spacing-1">
                                 <a href="view-all-movie.html" class="text-decoration-none">Action</a>
                              </li>
                              <li class="text-capitalize font-size-14 letter-spacing-1">
                                 <a href="view-all-movie.html" class="text-decoration-none">Crime</a>
                              </li>
                              <li class="text-capitalize font-size-14 letter-spacing-1">
                                 <a href="view-all-movie.html" class="text-decoration-none">Horror</a>
                              </li>
                              <li class="text-capitalize font-size-14 letter-spacing-1">
                                 <a href="view-all-movie.html" class="text-decoration-none">Mystery</a>
                              </li>
                           </ul>
                           <h2 class="iq-title m-0 line-count-2"><a href="/movie-detail.html">Venom</a>
                           </h2>
                           <div
                              class="d-flex align-items-center gap-3 py-2 justify-content-center justify-content-lg-start flex-wrap">
                              <div class="slider-ratting d-flex align-items-center gap-1">
                                 <ul
                                    class="ratting-start p-0 m-0 list-inline text-warning d-flex align-items-center justify-content-left">
                                    <li>
                                       <i class="ph-fill ph-star" aria-hidden="true"></i>
                                    </li>
                                    <li>
                                       <i class="ph-fill ph-star" aria-hidden="true"></i>
                                    </li>
                                    <li>
                                       <i class="ph-fill ph-star" aria-hidden="true"></i>
                                    </li>
                                    <li>
                                       <i class="ph-fill ph-star" aria-hidden="true"></i>
                                    </li>
                                    <li>
                                       <i class="ph-fill ph-star-half" aria-hidden="true"></i>
                                    </li>
                                 </ul>
                              </div>
                              <div class="d-flex align-items-center gap-1">
                                 <p class="mb-0">5</p>
                                 <img class="imdb-img" alt="imdb-logo" src="/assets/images/pages/imdb-logo.svg">
                              </div>
                              <div class="d-flex align-items-center gap-1">
                                 <i class="ph ph-clock font-size-14"></i>
                                 <span class="text-body">2hr : 3m</span>
                              </div>
                           </div>
                           <p class="mt-2 mb-3 line-count-3">In a shadowy metropolis where danger lurks in every corner,
                              an alien symbiote crash-lands on Earth, searching for the perfect host. Enter Eddie Thorn,
                              an investigative journalist whose life takes a wild turn when he bonds with the mysterious
                              entity known as Venom. As Eddie struggles to control the powerful, chaotic force within
                              him, a new threat emerges: a rival symbiote with an insatiable thirst for destruction.</p>
                           <a href="/movie-detail.html" class="btn btn-primary text-capitalize position-relative rounded-3">
                               <span class="d-flex align-items-center gap-2">
                                   <span class="button-text">play now</span>
                                   <i class="ph-fill ph-play fs-6"></i>
                               </span>
                           </a>
                        </div>
                     </div>
                  </div>
                  <div class="swiper-slide">
                     <div class="slider--image block-images"><img src="/assets/images/media/rabbit.webp"
                           loading="lazy" alt="img" /></div>
                     <div class="description">
                        <div class="block-description">
                           <ul
                              class="ps-0 mb-2 pb-1 list-inline d-flex flex-wrap align-items-center movie-tag justify-content-center justify-content-lg-start genres-list gap-1 gap-sm-0">
                              <li class="text-capitalize font-size-14 letter-spacing-1">
                                 <a href="view-all-movie.html" class="text-decoration-none">Action</a>
                              </li>
                              <li class="text-capitalize font-size-14 letter-spacing-1">
                                 <a href="view-all-movie.html" class="text-decoration-none">Adventure </a>
                              </li>
                              <li class="text-capitalize font-size-14 letter-spacing-1">
                                 <a href="view-all-movie.html" class="text-decoration-none">Horror</a>
                              </li>
                           </ul>
                           <h2 class="iq-title m-0 line-count-2"><a href="/tv-show-detail.html">Rabbit</a>
                           </h2>
                           <div
                              class="d-flex align-items-center gap-3 py-2 justify-content-center justify-content-lg-start flex-wrap">
                              <div class="slider-ratting d-flex align-items-center gap-1">
                                 <ul
                                    class="ratting-start p-0 m-0 list-inline text-warning d-flex align-items-center justify-content-left">
                                    <li>
                                       <i class="ph-fill ph-star" aria-hidden="true"></i>
                                    </li>
                                    <li>
                                       <i class="ph-fill ph-star" aria-hidden="true"></i>
                                    </li>
                                    <li>
                                       <i class="ph-fill ph-star" aria-hidden="true"></i>
                                    </li>
                                    <li>
                                       <i class="ph-fill ph-star" aria-hidden="true"></i>
                                    </li>
                                    <li>
                                       <i class="ph-fill ph-star-half" aria-hidden="true"></i>
                                    </li>
                                 </ul>
                              </div>
                              <div class="d-flex align-items-center gap-1">
                                 <p class="mb-0">8</p>
                                 <img class="imdb-img" alt="imdb-logo" src="/assets/images/pages/imdb-logo.svg">
                              </div>
                              <div class="d-flex align-items-center gap-1">
                                 <i class="ph ph-clock font-size-14"></i>
                                 <span class="text-body">2hr : 30m</span>
                              </div>
                           </div>
                           <p class="mt-2 mb-3 line-count-3">The film builds tension through atmospheric cinematography,
                              depicting haunting landscapes in Australia's Riverland region. Critics have praised Sarah
                              Snook's performance but noted the film's reliance on familiar horror tropes, comparing it
                              to The Babadook and Hereditary. Despite mixed reviews, Run Rabbit Run stands out for its
                              exploration of motherhood, grief, and the psychological toll of buried memories.</p>
                           <a href="/movie-detail.html" class="btn btn-primary text-capitalize position-relative rounded-3">
                               <span class="d-flex align-items-center gap-2">
                                   <span class="button-text">play now</span>
                                   <i class="ph-fill ph-play fs-6"></i>
                               </span>
                           </a>
                        </div>
                     </div>
                  </div>
                  <div class="swiper-slide">
                     <div class="slider--image block-images"><img src="/assets/images/media/krishna.webp"
                           loading="lazy" alt="img" /></div>
                     <div class="description">
                        <div class="block-description">
                           <ul
                              class="ps-0 mb-2 pb-1 list-inline d-flex flex-wrap align-items-center movie-tag justify-content-center justify-content-lg-start genres-list gap-1 gap-sm-0">
                              <li class="text-capitalize font-size-14 letter-spacing-1">
                                 <a href="view-all-movie.html" class="text-decoration-none">Action</a>
                              </li>
                              <li class="text-capitalize font-size-14 letter-spacing-1">
                                 <a href="view-all-movie.html" class="text-decoration-none">Adventure </a>
                              </li>
                              <li class="text-capitalize font-size-14 letter-spacing-1">
                                 <a href="view-all-movie.html" class="text-decoration-none">Crime</a>
                              </li>
                              <li class="text-capitalize font-size-14 letter-spacing-1">
                                 <a href="view-all-movie.html" class="text-decoration-none">Horror</a>
                              </li>
                              <li class="text-capitalize font-size-14 letter-spacing-1">
                                 <a href="view-all-movie.html" class="text-decoration-none">Mystery</a>
                              </li>
                           </ul>
                           <h2 class="iq-title m-0 line-count-2"><a href="/tv-show-detail.html">Krishna</a></h2>
                           <div
                              class="d-flex align-items-center gap-3 py-2 justify-content-center justify-content-lg-start flex-wrap">
                              <div class="slider-ratting d-flex align-items-center gap-1">
                                 <ul
                                    class="ratting-start p-0 m-0 list-inline text-warning d-flex align-items-center justify-content-left">
                                    <li>
                                       <i class="ph-fill ph-star" aria-hidden="true"></i>
                                    </li>
                                    <li>
                                       <i class="ph-fill ph-star" aria-hidden="true"></i>
                                    </li>
                                    <li>
                                       <i class="ph-fill ph-star" aria-hidden="true"></i>
                                    </li>
                                    <li>
                                       <i class="ph-fill ph-star" aria-hidden="true"></i>
                                    </li>
                                    <li>
                                       <i class="ph ph-star" aria-hidden="true"></i>
                                    </li>
                                 </ul>
                              </div>
                              <div class="d-flex align-items-center gap-1">
                                 <p class="mb-0">8</p>
                                 <img class="imdb-img" alt="imdb-logo" src="/assets/images/pages/imdb-logo.svg">
                              </div>
                              <div class="d-flex align-items-center gap-1">
                                 <i class="ph ph-clock font-size-14"></i>
                                 <span class="text-body">1hr : 22m</span>
                              </div>
                           </div>
                           <p class="mt-2 mb-3 line-count-3">In the vibrant land of Vrindavan, Krishna: Portrait unfolds
                              as an intimate exploration of the life and legacy of Lord Krishna, one of history's most
                              beloved divine figures. Through a series of rich, interwoven tales, the film captures
                              Krishna's journey—from his mischievous childhood as a cowherd to his profound role as a
                              spiritual guide and warrior.</p>
                           <a href="/movie-detail.html" class="btn btn-primary text-capitalize position-relative rounded-3">
                               <span class="d-flex align-items-center gap-2">
                                   <span class="button-text">play now</span>
                                   <i class="ph-fill ph-play fs-6"></i>
                               </span>
                           </a>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="d-block d-lg-none">
                  <div class="swiper-button swiper-button-next"></div>
                  <div class="swiper-button swiper-button-prev"></div>
               </div>
            </div>
         </div>
      </div>
   </div>
</div>

<div class="container-fluid">
   <div class="overflow-hidden">
      <div class="suggested-block section-wraper">
         <div class="d-flex align-items-center justify-content-between px-1 mb-2 pb-1 mb-md-4 pb-md-0">
            <h4 class="main-title text-capitalize mb-0 fw-medium">Suggested For You</h4>
         </div>
         <div class="card-style-slider">
            <div class="position-relative swiper swiper-card" data-slide="6" data-laptop="4" data-tab="3"
               data-mobile="2" data-mobile-sm="2" data-autoplay="false" data-loop="true" data-navigation="true"
               data-pagination="true">
               <ul class="p-0 swiper-wrapper m-0  list-inline">
                  <li class="swiper-slide">
                     <div class="iq-card card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/movie-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/giirek-portrait.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize">
                                 <a href="/movie-detail.html">Giikre</a>
                               </h5>
                               <div class="d-flex align-items-center gap-3">
                                 <div class="d-flex align-items-center gap-2">
                                   <i class="ph ph-translate"></i>
                                   <small class="font-size-12">English</small>
                                 </div>
                               </div>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/movie-detail.html" class="btn btn-primary w-100">Play Now
                               </a>
                             </div>
                           </div>
                     
                         </div>
                         <div class="position-absolute z-1 primium-product d-flex align-items-center justify-content-center"
                           data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Premium" data-bs-original-title="Premium">
                           <i class="ph-fill ph-crown "></i>
                         </div>
                       </div>
                     
                     </div>

                  <li class="swiper-slide">
                     <div class="iq-card card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/movie-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/another-danger-portrait.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize">
                                 <a href="/movie-detail.html">Another Danger</a>
                               </h5>
                               <div class="d-flex align-items-center gap-3">
                                 <div class="d-flex align-items-center gap-2">
                                   <i class="ph ph-translate"></i>
                                   <small class="font-size-12">English</small>
                                 </div>
                               </div>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/movie-detail.html" class="btn btn-primary w-100">Play Now
                               </a>
                             </div>
                           </div>
                     
                         </div>
                         <div class="position-absolute z-1 primium-product d-flex align-items-center justify-content-center"
                           data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Premium" data-bs-original-title="Premium">
                           <i class="ph-fill ph-crown "></i>
                         </div>
                       </div>
                     
                     </div>
                  </li>
                  <li class="swiper-slide">
                     <div class="iq-card card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/movie-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/revenge-of-the-sith-portrait.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize">
                                 <a href="/movie-detail.html">Revenge of the Sith</a>
                               </h5>
                               <div class="d-flex align-items-center gap-3">
                                 <div class="d-flex align-items-center gap-2">
                                   <i class="ph ph-translate"></i>
                                   <small class="font-size-12">English</small>
                                 </div>
                               </div>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/movie-detail.html" class="btn btn-primary w-100">Play Now
                               </a>
                             </div>
                           </div>
                     
                         </div>
                         <div class="position-absolute z-1 primium-product d-flex align-items-center justify-content-center"
                           data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Premium" data-bs-original-title="Premium">
                           <i class="ph-fill ph-crown "></i>
                         </div>
                       </div>
                     
                     </div>
                  </li>
                  <li class="swiper-slide">
                     <div class="iq-card card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/movie-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/fast-furious-portrait.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize">
                                 <a href="/movie-detail.html">Fast
                                          Furious</a>
                               </h5>
                               <div class="d-flex align-items-center gap-3">
                                 <div class="d-flex align-items-center gap-2">
                                   <i class="ph ph-translate"></i>
                                   <small class="font-size-12">English</small>
                                 </div>
                               </div>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/movie-detail.html" class="btn btn-primary w-100">Play Now
                               </a>
                             </div>
                           </div>
                     
                         </div>
                       </div>
                     
                     </div>
                  </li>
                  <li class="swiper-slide">
                     <div class="iq-card card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/movie-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/dinoosaur-portrait.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize">
                                 <a href="/movie-detail.html">Dinoosaur</a>
                               </h5>
                               <div class="d-flex align-items-center gap-3">
                                 <div class="d-flex align-items-center gap-2">
                                   <i class="ph ph-translate"></i>
                                   <small class="font-size-12">English</small>
                                 </div>
                               </div>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/movie-detail.html" class="btn btn-primary w-100">Play Now
                               </a>
                             </div>
                           </div>
                     
                         </div>
                       </div>
                     
                     </div>
                  </li>
                  <li class="swiper-slide">
                     <div class="iq-card card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/movie-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/bumblebee-portrait.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize">
                                 <a href="/movie-detail.html">Bumblebee</a>
                               </h5>
                               <div class="d-flex align-items-center gap-3">
                                 <div class="d-flex align-items-center gap-2">
                                   <i class="ph ph-translate"></i>
                                   <small class="font-size-12">English</small>
                                 </div>
                               </div>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/movie-detail.html" class="btn btn-primary w-100">Play Now
                               </a>
                             </div>
                           </div>
                     
                         </div>
                         <div class="position-absolute z-1 primium-product d-flex align-items-center justify-content-center"
                           data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Premium" data-bs-original-title="Premium">
                           <i class="ph-fill ph-crown "></i>
                         </div>
                       </div>
                     
                     </div>
                  </li>
                  <li class="swiper-slide">
                     <div class="iq-card card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/movie-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/arrival-portrait.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize">
                                 <a href="/movie-detail.html">Arrival</a>
                               </h5>
                               <div class="d-flex align-items-center gap-3">
                                 <div class="d-flex align-items-center gap-2">
                                   <i class="ph ph-translate"></i>
                                   <small class="font-size-12">English</small>
                                 </div>
                               </div>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/movie-detail.html" class="btn btn-primary w-100">Play Now
                               </a>
                             </div>
                           </div>
                     
                         </div>
                       </div>
                     
                     </div>
                  </li>
               </ul>
               <div class="d-none d-lg-block">
                  <div class="swiper-button swiper-button-next"></div>
                  <div class="swiper-button swiper-button-prev"></div>
               </div>
            </div>
         </div>
      </div>
   </div>
</div>

<section id="parallex" class="parallax-window bg-attachment-fixed"
   style="background:url(/assets/images/pages/Movieof-the-year.webp) fixed;">
   <div class="container-fluid h-100">
      <div
         class="row align-items-center justify-content-center h-100 parallaxt-details flex-column-reverse flex-lg-row gap-4 gap-lg-0">
         <div class="col-xl-6 col-lg-6 col-md-12 r-mb-23">
            <div class="parallax-window-detail">
               <h2 class="mb-0 parallaxt-details-heading">Godzilla</h2>
               <div
                  class="d-flex flex-column flex-md-row gap-2 flex-wrap align-items-center r-mb-23 mt-2 mb-3 gap-md-3 justify-content-center justify-content-lg-start">
                  <div class="slider-ratting d-flex align-items-center">
                     <ul
                        class="ratting-start p-0 m-0 list-inline text-warning d-flex align-items-center justify-content-left">
                        <li>
                           <i class="ph-fill ph-star" aria-hidden="true"></i>
                        </li>
                        <li>
                           <i class="ph-fill ph-star" aria-hidden="true"></i>
                        </li>
                        <li>
                           <i class="ph-fill ph-star" aria-hidden="true"></i>
                        </li>
                        <li>
                           <i class="ph-fill ph-star" aria-hidden="true"></i>
                        </li>
                        <li>
                           <i class="ph ph-star" aria-hidden="true"></i>
                        </li>
                     </ul>
                  </div>
                  <div class="d-flex gap-2 align-items-center">
                     <span>5
                     </span>
                     <img src="/assets/images/pages/imdb-logo.svg" alt="imdb logo" class="img-fluid">
                  </div>
                  <div class="d-flex align-items-center gap-1">
                     <i class="ph ph-clock"></i>
                     <span class="">2h : 30m</span>
                  </div>
               </div>
               <h4 class="iq-title mb-2 pb-1 pb-lg-0 mb-lg-3 fw-medium">
                  Movie of the year
               </h4>
               <p class="mb-4 pb-2 parallaxt-details-descripttion ">Baileys Irish Cream is an Irish cream liqueur an
                  alcoholic beverage
                  flavoured with cream, cocoa, and Irish whiskey made by Diageo at Republic of Ireland and in
                  Mallusk, Northern Ireland.
               </p>
               <div class="parallax-buttons">
                  <a href="/movie-detail.html" class="btn btn-primary text-capitalize position-relative rounded-3">
                      <span class="d-flex align-items-center gap-2">
                          <span class="button-text">play now</span>
                          <i class="ph-fill ph-play fs-6"></i>
                      </span>
                  </a>
               </div>
            </div>
         </div>
         <div class="col-xl-6 col-lg-6 col-md-12 mt-0 mt-lg-5 mt-xl-0">
            <div class="parallax-img">
               <a href="/movie-detail.html">
                  <img src="/assets/images/pages/Movieof-the-year.webp" class="img-fluid w-100" loading="lazy"
                     alt="bailey">
               </a>
            </div>
         </div>
      </div>
   </div>
</section>

<section class="tranding-tab-slider section-padding">
   <div class="container-fluid">
      <div class="row m-0 p-0">
         <div id="iq-trending" class="s-margin iq-tvshow-tabs iq-trending-tabs overflow-hidden">
            <div class="d-flex align-items-center justify-content-between px-1">
               <h4 class="main-title text-capitalize mb-0 fw-medium">Trending</h4>
            </div>
            <div class="trending-contens position-relative ">
               <div id="gallery-top" class="swiper gallery-thumbs pt-0" data-swiper="gallery-top">
                  <ul
                     class="swiper-wrapper list-inline m-0 trending-swiper-padding trending-slider-nav align-items-center ">
                     <li class="swiper-slide">
                        <a href="javascript:void(0);">
                           <div class="movie-swiper position-relative">
                              <img src="/assets/images/media/pirates-ofdayones-orignal.webp" alt="img" />
                           </div>
                        </a>
                     </li>
                     <li class="swiper-slide">
                        <a href="javascript:void(0);">
                           <div class="movie-swiper position-relative">
                              <img src="/assets/images/media/the-hunter.webp" alt="img" />
                           </div>
                        </a>
                     </li>
                     <li class="swiper-slide">
                        <a href="javascript:void(0);">
                           <div class="movie-swiper position-relative">
                              <img src="/assets/images/media/lost-in-space.webp" alt="img" />
                           </div>
                        </a>
                     </li>
                     <li class="swiper-slide">
                        <a href="javascript:void(0);">
                           <div class="movie-swiper position-relative">
                              <img src="/assets/images/media/castle-rock.webp" alt="img" />
                           </div>
                        </a>
                     </li>
                     <li class="swiper-slide">
                        <a href="javascript:void(0);" tabindex="0">
                           <div class="movie-swiper position-relative">
                              <img src="/assets/images/media/assassins-creed.webp" alt="img" />
                           </div>
                        </a>
                     </li>
                     <li class="swiper-slide">
                        <a href="javascript:void(0);" tabindex="0">
                           <div class="movie-swiper position-relative">
                              <img src="/assets/images/media/vikings.webp" alt="img" />
                           </div>
                        </a>
                     </li>
                  </ul>
               </div>
               <div id="gallery-bottom" class="swiper trending-tab-slider swiper-no-swiping"
                  data-swiper="gallery-bottom">
                  <ul class="swiper-wrapper list-inline p-0 m-0 d-flex trending-slider">
                     <li class="swiper-slide slider-big-img-6">
                        <div class="trending-tab-slider-image">
                           <img src="/assets/images/media/pirates-ofdayones-orignal.webp"
                              alt="trending-tab-slider-image">
                        </div>
                        <div class="tranding-block position-relative">
                           <div class="trending-custom-tab">
                              <div class="tab-title-info position-relative">
                                 <ul class="trending-pills iq-custom-tab flex-nowrap d-flex nav nav-pills justify-content-md-center align-items-center text-center list-inline"
                                    id="trending-tab-6" role="tablist">
                                    <li class="nav-item" role="presentation">
                                       <a class="nav-link active" id="trending-data-tab-21" data-bs-toggle="pill"
                                          data-bs-target="#trending-data-21" aria-controls="trending-data-21" role="tab"
                                          aria-selected="true">Overview</a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                       <a class="nav-link" id="trending-data-tab-22" data-bs-toggle="pill"
                                          data-bs-target="#trending-data-22" aria-controls="trending-data-22" role="tab"
                                          aria-selected="false">Episodes</a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                       <a class="nav-link" id="trending-data-tab-23" data-bs-toggle="pill"
                                          data-bs-target="#trending-data-23" aria-controls="trending-data-23" role="tab"
                                          aria-selected="false">Trailers</a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                       <a class="nav-link" id="trending-data-tab-24" data-bs-toggle="pill"
                                          data-bs-target="#trending-data-24" aria-controls="trending-data-24" role="tab"
                                          aria-selected="false">Similar Like This</a>
                                    </li>
                                 </ul>
                              </div>
                              <div class="tab-content trending-content" id="trending-tab-6-content">
                                 <div id="trending-data-21" class="tab-pane fade show active" role="tabpanel"
                                    aria-labelledby="trending-data-tab-21" tabindex="0">
                                    <div
                                       class=" trending-info align-items-center w-100 animated fadeInUp iq-ltr-direction">
                                       <h1 class="trending-text big-title text-capitalize texture-text">Pirates of Day
                                          One </h1>
                                       <div
                                          class="d-flex align-items-center text-white text-detail flex-wrap gap-1 gap-md-3">
                                          <span>3 Seasons</span>
                                          <div class="d-flex align-items-center gap-1">
                                             <i class="ph ph-calendar-dots"></i>
                                             <span class="trending-year">
                                                February 2025</span>
                                          </div>
                                       </div>
                                       <div class="d-flex align-items-center flex-wrap series mb-4 gap-3">
                                          <a href="javascript:void(0);"><img
                                                src="/assets/images/pages/trending-label.webp"
                                                class="img-fluid trending-label-img  rounded-3" alt="img"></a>
                                          <span class="fw-bold">#1 in Series Today</span>
                                       </div>
                                       <p class="trending-dec line-count-4">Pirates of Day One is an original, epic
                                          series that reimagines the classic pirate genre by blending adventure,
                                          fantasy, and historical drama. Set in an alternative 17th-century world where
                                          mythical creatures, ancient magic, and powerful artifacts exist, the series
                                          follows a ragtag crew of pirates with diverse backgrounds and mysterious
                                          pasts. Each character is drawn to the high seas for different reasons—revenge,
                                          freedom, treasure, or secrets. They form an unlikely family as they face
                                          formidable foes and legendary sea monsters, uncovering secrets that could
                                          reshape the world.</p>
                                       <div class="p-btns">
                                          <div class="iq-button">
                                             <a href="/tv-show-detail.html"
                                                class="btn btn-primary text-uppercase position-relative rounded-3">
                                                <div class="d-flex align-items-center gap-2">
                                                   <span class="button-text">Play Now</span>
                                                   <i class="ph-fill ph-play"></i>
                                                </div>
                                             </a>
                                          </div>
                                       </div>
                                    </div>
                                 </div>
                                 <div id="trending-data-22" class="tab-pane fade" role="tabpanel"
                                    aria-labelledby="trending-data-tab-22" tabindex="0">
                                    <div
                                       class=" trending-info align-items-center w-100 animated fadeInUp iq-ltr-direction">

                                       <h1 class="trending-text big-title text-capitalize texture-text">Pirates of Day
                                          One
                                       </h1>
                                       <div
                                          class="d-flex align-items-center text-white text-detail flex-wrap mb-4 gap-3">
                                          <span class="season_date">
                                             2 Seasons
                                          </span>
                                          <div class="d-flex align-items-center gap-1">
                                             <i class="ph ph-calendar-dots"></i>
                                             <span class="trending-year">Feb 2019</span>
                                          </div>
                                       </div>
                                       <div class="iq-custom-select d-inline-block sea-epi mb-4">
                                          <select name="cars"
                                             class="form-control season-select select2-basic-single js-states">
                                             <option value="season1">Season 1</option>
                                             <option value="season2">Season 2</option>
                                             <option value="season3">Season 3</option>
                                          </select>
                                       </div>
                                       <div class="position-relative swiper swiper-card" data-slide="4" data-laptop="3"
                                          data-tab="2" data-mobile="2" data-mobile-sm="1" data-autoplay="false"
                                          data-loop="true" data-navigation="false" data-pagination="true">
                                          <ul class="p-0 swiper-wrapper m-0  list-inline">
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s1e4-island-of-secrets.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 04 : Island of Secrets</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base"> A flashback episode reveals
                                                                                                Captain Roarke’s troubled past as a noble-turned-pirate. His path
                                                                                                crossed with the kingdom’s navy captain, who now pursues them with a
                                                                                                personal vendetta. Meanwhile, the crew faces an attack from a band of
                                                                                                mercenary hunters.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            45m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s1e3-rivals-and-revelations.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 03 : Rivals and Revelations</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">After docking at a lawless
                                                                                                island, the crew meets a witch who claims she can help them decipher the
                                                                                                map in exchange for a favor. But her price may be more than they’re
                                                                                                willing to pay, and they discover her dark connection to one of their
                                                                                                own.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            40m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s1e2-hidden-allies.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 02 : Hidden Allies</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">The crew battles a storm and
                                                                                                discovers that an old shipwreck nearby holds clues about their map.
                                                                                                Haunted by memories of lost loved ones, the crew faces restless spirits
                                                                                                that threaten to sink The Day One.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            30m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s1e1-setting-sail.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 01 : Setting Sail</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base"> When a young sailor with a
                                                                                                mysterious map stumbles onto the deck of The Day One, Captain Roarke
                                                                                                saves him from execution. The crew quickly learns that the map may lead
                                                                                                to The Haven. However, rival pirates and a pursuing navy are hot on
                                                                                                their trail.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            45m</p>
                                                    </div>
                                                </div>                                             </li>
                                          </ul>
                                          <div class="swiper-pagination d-block"></div>
                                       </div>
                                    </div>
                                 </div>
                                 <div id="trending-data-23" class="tab-pane fade" role="tabpanel"
                                    aria-labelledby="trending-data-tab-23" tabindex="0">
                                    <div
                                       class=" trending-info align-items-center w-100 animated fadeInUp iq-ltr-direction text-center">
                                       <h3 class="trending-text big-title text-uppercase texture-text mt-2">Watch
                                          Trailer</h3>
                                       <div class="episodes-contens mt-5">
                                          <div
                                             class="tab-watch-trailer-container d-inline-block rounded-3 overflow-hidden">
                                             <div class="tab-watch-trailer position-relative rounded-3 overflow-hidden">
                                                <img src="/assets/images/video/trailer-poster.webp"
                                                   class="trailer-image" alt="trailer-image">
                                                <a data-fslightbox="html5-video"
                                                   href="/assets/images/video/trailer.mp4"
                                                   class="video-open playbtn text-decoration-none" tabindex="0">
                                                   <svg version="1.1" xmlns="http://www.w3.org/2000/svg"
                                                      xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
                                                      width="80px" height="80px" viewBox="0 0 213.7 213.7"
                                                      enable-background="new 0 0 213.7 213.7" xml:space="preserve">
                                                      <polygon class="triangle" fill="none" stroke-width="7"
                                                         stroke-linecap="round" stroke-linejoin="round"
                                                         stroke-miterlimit="10"
                                                         points="73.5,62.5 148.5,105.8 73.5,149.1 "></polygon>
                                                      <circle class="circle" fill="none" stroke-width="7"
                                                         stroke-linecap="round" stroke-linejoin="round"
                                                         stroke-miterlimit="10" cx="106.8" cy="106.8" r="103.3">
                                                      </circle>
                                                   </svg>
                                                   <span class="w-trailor text-uppercase"> Watch Trailer </span>
                                                </a>
                                             </div>
                                          </div>
                                       </div>
                                    </div>
                                 </div>
                                 <div id="trending-data-24" class="tab-pane fade" role="tabpanel"
                                    aria-labelledby="trending-data-tab-24" tabindex="0">
                                    <div
                                       class=" trending-info align-items-center w-100 animated fadeInUp iq-ltr-direction">
                                       <h3 class="trending-text big-title text-uppercase texture-text mb-5">Recommended
                                          For You</h3>
                                       <div class="position-relative swiper swiper-card" data-slide="4" data-laptop="3"
                                          data-tab="2" data-mobile="1" data-mobile-sm="1" data-autoplay="false"
                                          data-loop="true" data-navigation="false" data-pagination="true">
                                          <ul class="p-0 swiper-wrapper m-0  list-inline">
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s2e4-tides-of-betrayal.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 04: The Silent Theft</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base"> A covert thief infiltrates
                                                                                                the enemy&#x27;s camp during wartime to steal intelligence, but soon finds
                                                                                                himself entangled in a dangerous conspiracy that could end the war—and
                                                                                                his life.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            45m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s1e5-Lost-Souls.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 03: Tides of Battle</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">During a fierce naval war,
                                                                                                two rival commanders must outwit each other in an escalating battle for
                                                                                                control of the seas, with devastating consequences for both their
                                                                                                fleets.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            35m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s3e3-cursed-waters.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 02: Cannons of the Deep</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base"> Set in the heart of a naval
                                                                                                war, a fleet of ships must fight off a mysterious enemy that uses
                                                                                                advanced weaponry, forcing the naval commanders to adapt quickly or face
                                                                                                annihilation</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            36m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s3e1-betrayals-and-bonds.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 01: Rise of the Mech-Titans</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base"> A story set during the
                                                                                                Viking Age, following a young man who must take up arms to defend his
                                                                                                village against an invading force</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            41m</p>
                                                    </div>
                                                </div>                                             </li>
                                          </ul>
                                          <div class="swiper-pagination d-block"></div>
                                       </div>
                                    </div>
                                 </div>
                              </div>
                           </div>
                        </div>
                     </li>
                     <li class="swiper-slide slider-big-img-1 p-0">
                        <div class="trending-tab-slider-image">
                           <img src="/assets/images/media/the-hunter.webp" alt="trending-tab-slider-image">
                        </div>
                        <div class="tranding-block position-relative">
                           <div class="trending-custom-tab">
                              <div class="tab-title-info position-relative">
                                 <ul class="trending-pills iq-custom-tab flex-nowrap d-flex nav nav-pills justify-content-md-center align-items-center text-center list-inline"
                                    id="trending-tab-1" role="tablist">
                                    <li class="nav-item" role="presentation">
                                       <a class="nav-link active" id="trending-data-tab-1" data-bs-toggle="pill"
                                          data-bs-target="#trending-data-1" aria-controls="trending-data-1" role="tab"
                                          aria-selected="true">Overview</a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                       <a class="nav-link" id="trending-data-tab-2" data-bs-toggle="pill"
                                          data-bs-target="#trending-data-2" aria-controls="trending-data-2" role="tab"
                                          aria-selected="false">Episodes</a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                       <a class="nav-link" id="trending-data-tab-3" data-bs-toggle="pill"
                                          data-bs-target="#trending-data-3" aria-controls="trending-data-3" role="tab"
                                          aria-selected="false">Trailers</a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                       <a class="nav-link" id="trending-data-tab-4" data-bs-toggle="pill"
                                          data-bs-target="#trending-data-4" aria-controls="trending-data-4" role="tab"
                                          aria-selected="false">Similar Like This</a>
                                    </li>
                                 </ul>
                              </div>
                              <div class="tab-content trending-content" id="trending-tab-1-content">
                                 <div id="trending-data-1" class="tab-pane fade show active" role="tabpanel"
                                    aria-labelledby="trending-data-tab-1" tabindex="0">
                                    <div
                                       class=" trending-info align-items-center w-100 animated fadeInUp iq-ltr-direction">
                                       <h1 class="trending-text big-title text-capitalize texture-text">the hunter </h1>
                                       <div
                                          class="d-flex align-items-start align-items-md-center text-white text-detail flex-wrap gap-1 gap-md-3 flex-column flex-md-row">
                                          <span>4 Seasons</span>
                                          <div class="d-flex align-items-center gap-1">
                                             <i class="ph ph-calendar-dots"></i>
                                             <span class="trending-year"> February 2025</span>
                                          </div>
                                       </div>
                                       <div class="d-flex align-items-center flex-wrap series mb-4 gap-3">
                                          <a href="javascript:void(0);"><img
                                                src="/assets/images/pages/trending-label.webp"
                                                class="img-fluid trending-label-img  rounded-3" alt="img"></a>
                                          <span class="fw-bold">#2 in Series Today</span>
                                       </div>
                                       <p class="trending-dec line-count-4">In the enthralling television series "The
                                          Hunter," the city stands as a kingdom in turmoil, where dragons of corruption
                                          and power wage silent wars in the shadows. Our relentless detective, a
                                          self-proclaimed king of justice, navigates this treacherous realm, seeking to
                                          unravel the mysteries that lie within. Over the course of four thrilling
                                          seasons, "The Hunter" explores the battle for truth and morality as the
                                          detective confronts the dragons of corruption that threaten to consume the
                                          kingdom. With each season, new battles emerge, secrets resurface, and kings
                                          rise and fall, shaping the destinies of both the characters and the city they
                                          call home.</p>
                                       <div class="p-btns">
                                          <div class="iq-button">
                                             <a href="/tv-show-detail.html"
                                                class="btn text-uppercase position-relative btn-primary rounded-3">
                                                <div class="d-flex align-items-center gap-2">
                                                   <span class="button-text">Play Now</span>
                                                   <i class="ph-fill ph-play"></i>
                                                </div>
                                             </a>
                                          </div>
                                       </div>
                                    </div>
                                 </div>
                                 <div id="trending-data-2" class="tab-pane fade" role="tabpanel"
                                    aria-labelledby="trending-data-tab-2" tabindex="0">
                                    <div
                                       class=" trending-info align-items-center w-100 animated fadeInUp iq-ltr-direction">

                                       <h1 class="trending-text big-title text-capitalize texture-text">the hunter
                                       </h1>
                                       <div
                                          class="d-flex align-items-center text-white text-detail flex-wrap mb-4 gap-3">
                                          <span class="season_date">
                                             4 Seasons
                                          </span>
                                          <div class="d-flex align-items-center gap-1">
                                             <i class="ph ph-calendar-dots"></i>
                                             <span class="trending-year">February 2025</span>
                                          </div>
                                       </div>
                                       <div class="iq-custom-select d-inline-block sea-epi mb-4">
                                          <select name="cars"
                                             class="form-control select2-basic-single js-states season-select">
                                             <option value="season1">Season 1</option>
                                             <option value="season2">Season 2</option>
                                             <option value="season3">Season 3</option>
                                          </select>
                                       </div>
                                       <div class="position-relative swiper swiper-card" data-slide="4" data-laptop="3"
                                          data-tab="2" data-mobile="2" data-mobile-sm="1" data-autoplay="false"
                                          data-loop="true" data-navigation="false" data-pagination="true">
                                          <ul class="p-0 swiper-wrapper m-0  list-inline">
                                           <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s2e2-forged-alliances.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 05 : Forged Alliances</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">As the season unfolds, &#x27;Forged Alliances&#x27;
                                                                                                dives deeper into the legacy of dragon riders and the
                                                                                                interconnected fates of the characters. A shocking revelation rocks the
                                                                                                kingdom, leading to a battle of honor and the revelation of hidden
                                                                                                destinies. This episode is marked by fiery confrontations, deepening
                                                                                                loyalties, and the gradual unveiling of the dragons&#x27; true purpose in the
                                                                                                world.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            59m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s2e1-dragons-reckoning.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 04 : Dragons and Reckoning</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">As the season unfolds, &#x27;Dragons and Reckoning&#x27;
                                                                                                dives deeper into the legacy of dragon riders and the
                                                                                                interconnected fates of the characters. A shocking revelation rocks the
                                                                                                kingdom, leading to a battle of honor and the revelation of hidden
                                                                                                destinies. This episode is marked by fiery confrontations, deepening
                                                                                                loyalties, and the gradual unveiling of the dragons&#x27; true purpose in the
                                                                                                world.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            49m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s1e3-fire-and-bloodlines.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 03 : Fire and Bloodlines</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">As the season unfolds, &#x27;Fire
                                                                                                and Bloodlines&#x27; dives deeper into the legacy of dragon riders and the
                                                                                                interconnected fates of the characters. A shocking revelation rocks the
                                                                                                kingdom, leading to a battle of honor and the revelation of hidden
                                                                                                destinies. This episode is marked by fiery confrontations, deepening
                                                                                                loyalties, and the gradual unveiling of the dragons&#x27; true purpose in the
                                                                                                world.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            45m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s1e2-kings-and-conspiracies.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 02 : Kings
                                                                                                and Conspiracies</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">In Kings and Conspiracies,
                                                                                                the stakes grow higher as the warring kings jockey for supremacy and
                                                                                                their covert plots become increasingly intricate. The central hero
                                                                                                discovers that not all is as it seems, and must navigate a treacherous
                                                                                                web of deceit and power. The episode is a thrilling exploration of
                                                                                                political intrigue and the dangers that lie beneath the surface..</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            40m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s1e1-awakening-of-the-drakes.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 01 : Awakening of the
                                                                                                Drakes</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">In the series premiere,
                                                                                                Awakening of the Drakes, the land is plunged into turmoil as the
                                                                                                long-dormant dragons reawaken, stirring fearsome battles and shifting
                                                                                                power dynamics among the realm&#x27;s rival factions. The episode follows our
                                                                                                enigmatic hero as they embark on a quest to unravel the secrets of the
                                                                                                dragon resurgence, setting the stage for a season filled with intrigue
                                                                                                and peril.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            30m</p>
                                                    </div>
                                                </div>                                             </li>
                                          </ul>
                                          <div class="swiper-pagination"></div>
                                       </div>
                                    </div>
                                 </div>
                                 <div id="trending-data-3" class="tab-pane fade" role="tabpanel"
                                    aria-labelledby="trending-data-tab-3" tabindex="0">
                                    <div
                                       class=" trending-info align-items-center w-100 animated fadeInUp iq-ltr-direction text-center">
                                       <h3 class="trending-text big-title text-uppercase texture-text mt-2">Watch
                                          Trailer</h3>
                                       <div class="episodes-contens mt-5">
                                          <div
                                             class="tab-watch-trailer-container d-inline-block rounded-3 overflow-hidden">
                                             <div class="tab-watch-trailer position-relative rounded-3 overflow-hidden">
                                                <img src="/assets/images/video/trailer-poster.webp"
                                                   class="trailer-image" alt="trailer-image">
                                                <a data-fslightbox="html5-video"
                                                   href="/assets/images/video/trailer.mp4"
                                                   class="video-open playbtn text-decoration-none" tabindex="0">
                                                   <svg version="1.1" xmlns="http://www.w3.org/2000/svg"
                                                      xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
                                                      width="80px" height="80px" viewBox="0 0 213.7 213.7"
                                                      enable-background="new 0 0 213.7 213.7" xml:space="preserve">
                                                      <polygon class="triangle" fill="none" stroke-width="7"
                                                         stroke-linecap="round" stroke-linejoin="round"
                                                         stroke-miterlimit="10"
                                                         points="73.5,62.5 148.5,105.8 73.5,149.1 "></polygon>
                                                      <circle class="circle" fill="none" stroke-width="7"
                                                         stroke-linecap="round" stroke-linejoin="round"
                                                         stroke-miterlimit="10" cx="106.8" cy="106.8" r="103.3">
                                                      </circle>
                                                   </svg>
                                                   <span class="w-trailor text-uppercase"> Watch Trailer </span>
                                                </a>
                                             </div>
                                          </div>
                                       </div>
                                    </div>
                                 </div>
                                 <div id="trending-data-4" class="tab-pane fade" role="tabpanel"
                                    aria-labelledby="trending-data-tab-4" tabindex="0">
                                    <div
                                       class=" trending-info align-items-center w-100 animated fadeInUp iq-ltr-direction">
                                       <h3 class="trending-text big-title text-uppercase texture-text mb-5">Recommended
                                          For You</h3>
                                       <div class="position-relative swiper swiper-card" data-slide="4" data-laptop="3"
                                          data-tab="2" data-mobile="1" data-mobile-sm="1" data-autoplay="false"
                                          data-loop="true" data-navigation="false" data-pagination="true">
                                          <ul class="p-0 swiper-wrapper m-0  list-inline">
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s4e1-rebirth-of-the-realm.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 04 : Island of Secrets</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">In the series premiere, Midnight Hunter, the land
                                                                                                is plunged into turmoil as the long-dormant dragons reawaken, stirring
                                                                                                fearsome battles and shifting power dynamics among the realm&#x27;s rival
                                                                                                factions. The episode follows our enigmatic hero as they embark on a
                                                                                                quest to unravel the secrets of the dragon resurgence, setting the stage
                                                                                                for a season filled with intrigue and peril.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            45m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s2e3-the-dragons-redemption.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0">03: Dragonheart</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">IA disillusioned knight
                                                                                                teams up with a dragon named Draco to defeat a tyrant king. The dragon
                                                                                                shares his heart with the knight, creating a deep bond that alters the
                                                                                                course of their lives.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            35m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s4e3-the-final-dawn.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0">02: Reign of Fire</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">In a post-apocalyptic
                                                                                                world, a group of survivors fights to survive against fire-breathing
                                                                                                dragons that have decimated humanity. It&#x27;s a battle for survival in a
                                                                                                world where dragons reign supreme.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            36m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s4e2-echoes-of-legends.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0">01: Pete&#x27;s Dragon</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">A young boy named Pete, who
                                                                                                has been living in the wild with his dragon, Elliott, must face the
                                                                                                world when people discover the magical creature. It&#x27;s a heartwarming
                                                                                                tale about friendship and family</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            41m</p>
                                                    </div>
                                                </div>                                             </li>
                                          </ul>
                                          <div class="swiper-pagination d-block"></div>
                                       </div>
                                    </div>
                                 </div>
                              </div>
                           </div>
                        </div>
                     </li>
                     <li class="swiper-slide slider-big-img-4">
                        <div class="trending-tab-slider-image">
                           <img src="/assets/images/media/lost-in-space.webp" alt="trending-tab-slider-image">
                        </div>
                        <div class="tranding-block position-relative">
                           <div class="trending-custom-tab">
                              <div class="tab-title-info position-relative">
                                 <ul class="trending-pills iq-custom-tab flex-nowrap d-flex nav nav-pills justify-content-md-center align-items-center text-center list-inline"
                                    id="trending-tab-4" role="tablist">
                                    <li class="nav-item" role="presentation">
                                       <a class="nav-link active" id="trending-data-tab-13" data-bs-toggle="pill"
                                          data-bs-target="#trending-data-13" aria-controls="trending-data-13" role="tab"
                                          aria-selected="true">Overview</a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                       <a class="nav-link" id="trending-data-tab-14" data-bs-toggle="pill"
                                          data-bs-target="#trending-data-14" aria-controls="trending-data-14" role="tab"
                                          aria-selected="false">Episodes</a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                       <a class="nav-link" id="trending-data-tab-15" data-bs-toggle="pill"
                                          data-bs-target="#trending-data-15" aria-controls="trending-data-15" role="tab"
                                          aria-selected="false">Trailers</a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                       <a class="nav-link" id="trending-data-tab-16" data-bs-toggle="pill"
                                          data-bs-target="#trending-data-16" aria-controls="trending-data-16" role="tab"
                                          aria-selected="false">Similar Like This</a>
                                    </li>
                                 </ul>
                              </div>
                              <div class="tab-content trending-content" id="trending-tab-4-content">
                                 <div id="trending-data-13" class="tab-pane fade show active" role="tabpanel"
                                    aria-labelledby="trending-data-tab-13" tabindex="0">
                                    <div
                                       class=" trending-info align-items-center w-100 animated fadeInUp iq-ltr-direction">
                                       <h1 class="trending-text big-title text-capitalize texture-text">Lost In Space
                                       </h1>
                                       <div
                                          class="d-flex align-items-center text-white text-detail flex-wrap gap-1 gap-md-3">
                                          <span>3 Seasons</span>
                                          <div class="d-flex align-items-center gap-1">
                                             <i class="ph ph-calendar-dots"></i>
                                             <span class="trending-year"> December 2024</span>
                                          </div>
                                       </div>
                                       <div class="d-flex align-items-center flex-wrap series mb-4 gap-3">
                                          <a href="javascript:void(0);"><img
                                                src="/assets/images/pages/trending-label.webp"
                                                class="img-fluid trending-label-img  rounded-3" alt="img"></a>
                                          <span class="fw-bold">#3 in Series Today</span>
                                       </div>
                                       <p class="trending-dec line-count-4">Lost in Space is a sci-fi adventure series
                                          that follows the Robinson family, who are selected to join a space colony
                                          mission. However, when their spacecraft veers off course, they become stranded
                                          on an unknown planet. As they struggle to survive in a dangerous new
                                          environment, they encounter an enigmatic robot, strange alien life, and a
                                          cunning stowaway, Dr. Smith. The series explores themes of survival, family
                                          bonds, and resilience as they work to find a way back to their original
                                          mission.</p>
                                       <div class="p-btns">
                                          <div class="iq-button">
                                             <a href="/tv-show-detail.html"
                                                class="btn btn-primary text-uppercase position-relative rounded-3">
                                                <div class="d-flex align-items-center gap-2">
                                                   <span class="button-text">Play Now</span>
                                                   <i class="ph-fill ph-play"></i>
                                                </div>
                                             </a>
                                          </div>
                                       </div>
                                    </div>
                                 </div>
                                 <div id="trending-data-14" class="tab-pane fade" role="tabpanel"
                                    aria-labelledby="trending-data-tab-14" tabindex="0">
                                    <div
                                       class=" trending-info align-items-center w-100 animated fadeInUp iq-ltr-direction">
                                       <h1 class="trending-text big-title text-capitalize texture-text">Lost In Space
                                       </h1>
                                       <div
                                          class="d-flex align-items-center text-white text-detail flex-wrap mb-4 gap-3">
                                          <span class="season_date">
                                             3 Seasons
                                          </span>
                                          <div class="d-flex align-items-center gap-1">
                                             <i class="ph ph-calendar-dots"></i>
                                             <span class="trending-year">December 2024</span>
                                          </div>
                                       </div>
                                       <div class="iq-custom-select d-inline-block sea-epi mb-4">
                                          <select name="cars"
                                             class="form-control season-select select2-basic-single js-states">
                                             <option value="season1">Season 1</option>
                                             <option value="season2">Season 2</option>
                                             <option value="season3">Season 3</option>
                                          </select>
                                       </div>
                                       <div class="position-relative swiper swiper-card" data-slide="4" data-laptop="3"
                                          data-tab="2" data-mobile="2" data-mobile-sm="1" data-autoplay="false"
                                          data-loop="true" data-navigation="false" data-pagination="true">
                                          <ul class="p-0 swiper-wrapper m-0  list-inline">
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s3e1-contact.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 04: contact</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">As the season unfolds, All
                                                                                                Change dives deeper into the legacy of dragon riders and the
                                                                                                interconnected fates of the characters. A shocking revelation rocks the
                                                                                                kingdom, leading to a battle of honor and the revelation of hidden
                                                                                                destinies. This episode is marked by fiery confrontations, deepening
                                                                                                loyalties, and the gradual unveiling of the dragons&#x27; true purpose in the
                                                                                                world.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            41m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s1e3-shipwrecked.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 03 : Shipwrecked</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">As the chemistry teacher&#x27;s
                                                                                                alter ego emerged, a ruthless and cunning individual known only by a
                                                                                                pseudonym, the boundaries between right and wrong blurred into
                                                                                                obscurity. The series masterfully explored the consequences of choices,
                                                                                                unraveling a narrative that left viewers questioning the very fabric of
                                                                                                morality.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            1h</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s1e2-the-new-guy.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 02 : The New Guy</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">As the chemistry teacher&#x27;s
                                                                                                alter ego emerged, a ruthless and cunning individual known only by a
                                                                                                pseudonym, the boundaries between right and wrong blurred into
                                                                                                obscurity. The series masterfully explored the consequences of choices,
                                                                                                unraveling a narrative that left viewers questioning the very fabric of
                                                                                                morality.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            50m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s1e1-trust.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 01 : Trust</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">As the chemistry teacher&#x27;s
                                                                                                alter ego emerged, a ruthless and cunning individual known only by a
                                                                                                pseudonym, the boundaries between right and wrong blurred into
                                                                                                obscurity. The series masterfully explored the consequences of choices,
                                                                                                unraveling a narrative that left viewers questioning the very fabric of
                                                                                                morality.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            1h : 2m</p>
                                                    </div>
                                                </div>                                             </li>
                                          </ul>
                                          <div class="swiper-pagination d-block"></div>
                                       </div>
                                    </div>
                                 </div>
                                 <div id="trending-data-15" class="tab-pane fade" role="tabpanel"
                                    aria-labelledby="trending-data-tab-15" tabindex="0">
                                    <div
                                       class=" trending-info align-items-center w-100 animated fadeInUp iq-ltr-direction text-center">
                                       <h3 class="trending-text big-title text-uppercase texture-text mt-2">Watch
                                          Trailer</h3>
                                       <div class="episodes-contens mt-5">
                                          <div
                                             class="tab-watch-trailer-container d-inline-block rounded-3 overflow-hidden">
                                             <div class="tab-watch-trailer position-relative rounded-3 overflow-hidden">
                                                <img src="/assets/images/video/trailer-poster.webp"
                                                   class="trailer-image" alt="trailer-image">
                                                <a data-fslightbox="html5-video"
                                                   href="/assets/images/video/trailer.mp4"
                                                   class="video-open playbtn text-decoration-none" tabindex="0">
                                                   <svg version="1.1" xmlns="http://www.w3.org/2000/svg"
                                                      xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
                                                      width="80px" height="80px" viewBox="0 0 213.7 213.7"
                                                      enable-background="new 0 0 213.7 213.7" xml:space="preserve">
                                                      <polygon class="triangle" fill="none" stroke-width="7"
                                                         stroke-linecap="round" stroke-linejoin="round"
                                                         stroke-miterlimit="10"
                                                         points="73.5,62.5 148.5,105.8 73.5,149.1 "></polygon>
                                                      <circle class="circle" fill="none" stroke-width="7"
                                                         stroke-linecap="round" stroke-linejoin="round"
                                                         stroke-miterlimit="10" cx="106.8" cy="106.8" r="103.3">
                                                      </circle>
                                                   </svg>
                                                   <span class="w-trailor text-uppercase"> Watch Trailer </span>
                                                </a>
                                             </div>
                                          </div>
                                       </div>
                                    </div>
                                 </div>
                                 <div id="trending-data-16" class="tab-pane fade" role="tabpanel"
                                    aria-labelledby="trending-data-tab-16" tabindex="0">
                                    <div
                                       class=" trending-info align-items-center w-100 animated fadeInUp iq-ltr-direction">
                                       <h3 class="trending-text big-title text-uppercase texture-text mb-5">Recommended
                                          For You</h3>
                                       <div class="position-relative swiper swiper-card" data-slide="4" data-laptop="3"
                                          data-tab="2" data-mobile="1" data-mobile-sm="1" data-autoplay="false"
                                          data-loop="true" data-navigation="false" data-pagination="true">
                                          <ul class="p-0 swiper-wrapper m-0  list-inline">
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/kung-fu-panda.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 04: Kung Fu Panda</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">In the Valley of Peace, Po,
                                                                                                a clumsy and overweight panda, dreams of becoming a kung fu master. When
                                                                                                the legendary kung fu master, Oogway, unexpectedly chooses him as the
                                                                                                Dragon Warrior, Po must train to defeat the fearsome snow leopard Tai
                                                                                                Lung, who threatens the valley. With the help of the Furious Five, Po
                                                                                                learns that greatness comes from within.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            45m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/dinoosaur.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 03: Jurassic Park</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">A groundbreaking film
                                                                                                directed by Steven Spielberg, Jurassic Park follows a group of
                                                                                                scientists and visitors to a remote island where dinosaurs have been
                                                                                                brought back to life through cloning technology. As the dinosaurs break
                                                                                                free from their enclosures, chaos ensues, leading to thrilling action
                                                                                                and a deep exploration of the ethical consequences of playing with
                                                                                                nature. It became an iconic movie in the dinosaur genre.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            35m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/arrival.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 02: Jumanji</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">This original film stars
                                                                                                Robin Williams as Alan Parrish, a man who was trapped in the magical
                                                                                                board game Jumanji as a child. Many years later, two children, Judy and
                                                                                                Peter, find the game and begin to play, accidentally releasing Alan from
                                                                                                his years of imprisonment. Together, they must finish the game to stop
                                                                                                the dangerous creatures and disasters it unleashes into the real world.
                                                                                                The movie is a blend of suspense, adventure, and heart, as they race
                                                                                                against time to finish the game before it&#x27;s too late.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            36m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/venom.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 01: Venom</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">A reporter, Eddie Brock,
                                                                                                becomes the host for an alien symbiote, Venom, granting him incredible
                                                                                                powers. Together, they must battle against a mad scientist who seeks to
                                                                                                use the symbiote for his own destructive purposes.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            41m</p>
                                                    </div>
                                                </div>                                             </li>
                                          </ul>
                                          <div class="swiper-pagination d-block"></div>
                                       </div>
                                    </div>
                                 </div>
                              </div>
                           </div>
                        </div>
                     </li>
                     <li class="swiper-slide slider-big-img-2">
                        <div class="trending-tab-slider-image">
                           <img src="/assets/images/media/castle-rock.webp" alt="trending-tab-slider-image">
                        </div>
                        <div class="tranding-block position-relative">
                           <div class="trending-custom-tab">
                              <div class="tab-title-info position-relative">
                                 <ul class="trending-pills iq-custom-tab flex-nowrap d-flex nav nav-pills justify-content-md-center align-items-center text-center list-inline"
                                    id="trending-tab-2" role="tablist">
                                    <li class="nav-item" role="presentation">
                                       <a class="nav-link active" id="trending-data-tab-5" data-bs-toggle="pill"
                                          data-bs-target="#trending-data-5" aria-controls="trending-data-5" role="tab"
                                          aria-selected="true">Overview</a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                       <a class="nav-link" id="trending-data-tab-6" data-bs-toggle="pill"
                                          data-bs-target="#trending-data-6" aria-controls="trending-data-6" role="tab"
                                          aria-selected="false">Episodes</a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                       <a class="nav-link" id="trending-data-tab-7" data-bs-toggle="pill"
                                          data-bs-target="#trending-data-7" aria-controls="trending-data-7" role="tab"
                                          aria-selected="false">Trailers</a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                       <a class="nav-link" id="trending-data-tab-8" data-bs-toggle="pill"
                                          data-bs-target="#trending-data-8" aria-controls="trending-data-8" role="tab"
                                          aria-selected="false">Similar Like This</a>
                                    </li>
                                 </ul>
                              </div>
                              <div class="tab-content trending-content" id="trending-tab-2-content">
                                 <div id="trending-data-5" class="tab-pane fade show active" role="tabpanel"
                                    aria-labelledby="trending-data-tab-5" tabindex="0">
                                    <div
                                       class=" trending-info align-items-center w-100 animated fadeInUp iq-ltr-direction">
                                       <h1 class="trending-text big-title text-capitalize texture-text">Castle rock</h1>
                                       <div
                                          class="d-flex align-items-center text-white text-detail flex-wrap gap-1 gap-md-3">
                                          <span>3 Seasons</span>
                                          <div class="d-flex align-items-center gap-1">
                                             <i class="ph ph-calendar-dots"></i>
                                             <span class="trending-year"> December 2024</span>
                                          </div>
                                       </div>
                                       <div class="d-flex align-items-center flex-wrap series mb-4 gap-3">
                                          <a href="javascript:void(0);"><img
                                                src="/assets/images/pages/trending-label.webp"
                                                class="img-fluid trending-label-img  rounded-3" alt="img"></a>
                                          <span class="fw-bold">#4 in Series Today</span>
                                       </div>
                                       <p class="trending-dec line-count-4">Castle Rock is a psychological horror TV
                                          series inspired by the stories of Stephen King. Set in the fictional town of
                                          Castle Rock, Maine, the series explores interconnected mysteries and
                                          supernatural occurrences. Each season delves into different characters and
                                          plots from King’s universe, blending psychological tension with elements of
                                          horror, mystery, and suspense. Known for its atmospheric storytelling and
                                          complex characters, Castle Rock offers a fresh take on King's world with new
                                          twists and perspectives on his classic themes.</p>
                                       <div class="p-btns">
                                          <div class="iq-button">
                                             <a href="/tv-show-detail.html"
                                                class="btn btn-primary text-uppercase position-relative rounded-3">
                                                <div class="d-flex align-items-center gap-2">
                                                   <span class="button-text">Play Now</span>
                                                   <i class="ph-fill ph-play"></i>
                                                </div>
                                             </a>
                                          </div>
                                       </div>
                                    </div>
                                 </div>
                                 <div id="trending-data-6" class="tab-pane fade" role="tabpanel"
                                    aria-labelledby="trending-data-tab-6" tabindex="0">
                                    <div
                                       class=" trending-info align-items-center w-100 animated fadeInUp iq-ltr-direction">
                                       <h1 class="trending-text big-title text-capitalize texture-text">Castle rock</h1>
                                       <div
                                          class="d-flex align-items-center text-white text-detail flex-wrap mb-4 gap-3">
                                          <span class="season_date">
                                             3 Seasons
                                          </span>
                                          <div class="d-flex align-items-center gap-1">
                                             <i class="ph ph-calendar-dots"></i>
                                             <span class="trending-year"> December 2024 </span>
                                          </div>
                                       </div>
                                       <div class="iq-custom-select d-inline-block sea-epi mb-4">
                                          <select name="cars"
                                             class="form-control select2-basic-single js-states season-select">
                                             <option value="season1">Season 1</option>
                                             <option value="season2">Season 2</option>
                                             <option value="season3">Season 3</option>
                                          </select>
                                       </div>
                                       <div class="position-relative swiper swiper-card" data-slide="4" data-laptop="3"
                                          data-tab="2" data-mobile="2" data-mobile-sm="1" data-autoplay="false"
                                          data-loop="true" data-navigation="false" data-pagination="true">
                                          <ul class="p-0 swiper-wrapper m-0  list-inline">
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s1e4-the-box.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 02: The Box</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">The Box is a dark,
                                                                                                suspense-filled series that follows the story of a troubled young woman,
                                                                                                Sharon Pici (played by Anna Friel), who is a Kansas City detective with
                                                                                                an obsessive personality. When she becomes trapped in a small,
                                                                                                windowless room—a mysterious “box” that she can’t escape from—she must
                                                                                                confront her past traumas and inner demons.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            30m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s1e3-local-color.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 03: Local Color</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">Local color is a literary
                                                                                                style that emphasizes the unique characteristics of a particular
                                                                                                geographic region, focusing on the customs, dialects, clothing,
                                                                                                landscapes, and lifestyles of its people. Writers who use local color
                                                                                                aim to bring authenticity to their stories by depicting the specific
                                                                                                traits and idiosyncrasies of a place and its inhabitants.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            50m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s1e2-habeas-corpus.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 04: Habeas Corpus</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">Habeas corpus is a
                                                                                                fundamental legal principle originating from English common law, often
                                                                                                referred to as the “Great Writ of Liberty.” Latin for you shall have the
                                                                                                body, it is a legal procedure that protects individuals from unlawful
                                                                                                detention. By invoking habeas corpus, a person detained or imprisoned
                                                                                                can petition the court to determine whether their imprisonment is
                                                                                                lawful.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            32m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s1e1-recap-severance.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 01: Recap Severance</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">The show centers around Mark
                                                                                                Scout (played by Adam Scott), an employee of Lumon Industries, a company
                                                                                                where employees undergo a surgical procedure known as severance. This
                                                                                                procedure splits their memories into two parts: the “work self” (Innie)
                                                                                                and the “home self” (Outie). The “Innie” knows nothing of the outside
                                                                                                world, while the “Outie” is unaware of what happens at work.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            30m</p>
                                                    </div>
                                                </div>                                             </li>
                                          </ul>
                                          <div class="swiper-pagination d-block"></div>
                                       </div>
                                    </div>
                                 </div>
                                 <div id="trending-data-7" class="tab-pane fade" role="tabpanel"
                                    aria-labelledby="trending-data-tab-7" tabindex="0">
                                    <div
                                       class=" trending-info align-items-center w-100 animated fadeInUp iq-ltr-direction text-center">
                                       <h3 class="trending-text big-title text-uppercase texture-text mt-2">Watch
                                          Trailer</h3>
                                       <div class="episodes-contens mt-5">
                                          <div
                                             class="tab-watch-trailer-container d-inline-block rounded-3 overflow-hidden">
                                             <div class="tab-watch-trailer position-relative rounded-3 overflow-hidden">
                                                <img src="/assets/images/video/trailer-poster.webp"
                                                   class="trailer-image" alt="trailer-image">
                                                <a data-fslightbox="html5-video"
                                                   href="/assets/images/video/trailer.mp4"
                                                   class="video-open playbtn text-decoration-none" tabindex="0">
                                                   <svg version="1.1" xmlns="http://www.w3.org/2000/svg"
                                                      xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
                                                      width="80px" height="80px" viewBox="0 0 213.7 213.7"
                                                      enable-background="new 0 0 213.7 213.7" xml:space="preserve">
                                                      <polygon class="triangle" fill="none" stroke-width="7"
                                                         stroke-linecap="round" stroke-linejoin="round"
                                                         stroke-miterlimit="10"
                                                         points="73.5,62.5 148.5,105.8 73.5,149.1 "></polygon>
                                                      <circle class="circle" fill="none" stroke-width="7"
                                                         stroke-linecap="round" stroke-linejoin="round"
                                                         stroke-miterlimit="10" cx="106.8" cy="106.8" r="103.3">
                                                      </circle>
                                                   </svg>
                                                   <span class="w-trailor text-uppercase"> Watch Trailer </span>
                                                </a>
                                             </div>
                                          </div>
                                       </div>
                                    </div>
                                 </div>
                                 <div id="trending-data-8" class="tab-pane fade" role="tabpanel"
                                    aria-labelledby="trending-data-tab-8" tabindex="0">
                                    <div
                                       class=" trending-info align-items-center w-100 animated fadeInUp iq-ltr-direction">
                                       <h3 class="trending-text big-title text-uppercase texture-text mb-5">Recommended
                                          For You</h3>
                                       <div class="position-relative swiper swiper-card" data-slide="4" data-laptop="3"
                                          data-tab="2" data-mobile="1" data-mobile-sm="1" data-autoplay="false"
                                          data-loop="true" data-navigation="false" data-pagination="true">
                                          <ul class="p-0 swiper-wrapper m-0  list-inline">
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s2e2-filter.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 04: Misery</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">A famous author, Paul
                                                                                                Sheldon, is kidnapped by a crazed fan, Annie Wilkes, while recovering
                                                                                                from an accident in the isolated town of Castle Rock. As she holds him
                                                                                                captive, Paul must figure out how to escape her violent obsession.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            45m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s3e1-sunset.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 03: The Green Mile</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">Set in a Louisiana prison
                                                                                                but connected to the larger Stephen King universe, the film has some
                                                                                                links to Castle Rock. The story follows Paul Edgecomb, a prison guard,
                                                                                                and John Coffey, a supernatural inmate with miraculous healing powers,
                                                                                                whose fate seems to intertwine with the town&#x27;s dark history</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            35m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s2e1-the-harvest.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 02: War Of Planet</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">In this intense continuation
                                                                                                of the series, Caesar (the leader of the apes) struggles to protect his
                                                                                                people from a brutal human military faction led by a ruthless Colonel.
                                                                                                As the apes face a war for survival, Caesar must confront his own darker
                                                                                                instincts while making decisions that could determine the fate of both
                                                                                                species.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            36m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s1e3-local-color.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 01: Ex Machina </h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">A young programmer is
                                                                                                selected to participate in a groundbreaking experiment in artificial
                                                                                                intelligence by testing the human qualities of a highly advanced robot
                                                                                                named Ava. As the story unfolds, questions about consciousness, control,
                                                                                                and manipulation arise.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            41m</p>
                                                    </div>
                                                </div>                                             </li>
                                          </ul>
                                          <div class="swiper-pagination d-block"></div>
                                       </div>
                                    </div>
                                 </div>
                              </div>
                           </div>
                        </div>
                     </li>
                     <li class="swiper-slide slider-big-img-3">
                        <div class="trending-tab-slider-image">
                           <img src="/assets/images/media/assassins-creed.webp" alt="trending-tab-slider-image">
                        </div>
                        <div class="tranding-block position-relative">
                           <div class="trending-custom-tab">
                              <div class="tab-title-info position-relative">
                                 <ul class="trending-pills iq-custom-tab flex-nowrap d-flex nav nav-pills justify-content-md-center align-items-center text-center list-inline"
                                    id="trending-tab-3" role="tablist">
                                    <li class="nav-item" role="presentation">
                                       <a class="nav-link active" id="trending-data-tab-9" data-bs-toggle="pill"
                                          data-bs-target="#trending-data-9" aria-controls="trending-data-9" role="tab"
                                          aria-selected="true">Overview</a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                       <a class="nav-link" id="trending-data-tab-10" data-bs-toggle="pill"
                                          data-bs-target="#trending-data-10" aria-controls="trending-data-10" role="tab"
                                          aria-selected="false">Episodes</a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                       <a class="nav-link" id="trending-data-tab-11" data-bs-toggle="pill"
                                          data-bs-target="#trending-data-11" aria-controls="trending-data-11" role="tab"
                                          aria-selected="false">Trailers</a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                       <a class="nav-link" id="trending-data-tab-12" data-bs-toggle="pill"
                                          data-bs-target="#trending-data-12" aria-controls="trending-data-12" role="tab"
                                          aria-selected="false">Similar Like This</a>
                                    </li>
                                 </ul>
                              </div>
                              <div class="tab-content trending-content" id="trending-tab-3-content">
                                 <div id="trending-data-9" class="tab-pane fade show active" role="tabpanel"
                                    aria-labelledby="trending-data-tab-9" tabindex="0">
                                    <div
                                       class=" trending-info align-items-center w-100 animated fadeInUp iq-ltr-direction">
                                       <h1 class="trending-text big-title text-capitalize texture-text">Assassin creed
                                       </h1>
                                       <div
                                          class="d-flex align-items-center text-white text-detail flex-wrap gap-1 gap-md-3">
                                          <span>2 Seasons</span>
                                          <div class="d-flex align-items-center gap-1">
                                             <i class="ph ph-calendar-dots"></i>
                                             <span class="trending-year"> February 2025</span>
                                          </div>
                                       </div>
                                       <div class="d-flex align-items-center flex-wrap series mb-4 gap-3">
                                          <a href="javascript:void(0);"><img
                                                src="/assets/images/pages/trending-label.webp"
                                                class="img-fluid trending-label-img  rounded-3" alt="img"></a>
                                          <span class="fw-bold">#5 in Series Today</span>
                                       </div>
                                       <p class="trending-dec line-count-4">The Cannibalism among dinosaurs is not
                                          necessarily surprising. Many large predators, such as crocodiles and
                                          alligators, eat members of their own species under certain circumstances.
                                          “Every major predator today will eat its own species, whether you’re a Komodo
                                          dragon or a bear or a lion,” says Mark Loewen, a paleontologist at the Natural
                                          History Museum of Utah in Salt Lake City. “If there’s a dead animal, meat
                                          eaters will eat it.</p>
                                       <div class="p-btns">
                                          <div class="iq-button">
                                             <a href="/tv-show-detail.html"
                                                class="btn btn-primary text-uppercase position-relative rounded-3">
                                                <div class="d-flex align-items-center gap-2">
                                                   <span class="button-text">Play Now</span>
                                                   <i class="ph-fill ph-play"></i>
                                                </div>
                                             </a>
                                          </div>
                                       </div>
                                    </div>
                                 </div>
                                 <div id="trending-data-10" class="tab-pane fade" role="tabpanel"
                                    aria-labelledby="trending-data-tab-10" tabindex="0">
                                    <div
                                       class=" trending-info align-items-center w-100 animated fadeInUp iq-ltr-direction">
                                       <h1 class="trending-text big-title text-capitalize texture-text">Assassin creed
                                       </h1>
                                       <div
                                          class="d-flex align-items-center text-white text-detail flex-wrap mb-4 gap-3">
                                          <span class="season_date">
                                             2 Seasons
                                          </span>
                                          <div class="d-flex align-items-center gap-1">
                                             <i class="ph ph-calendar-dots"></i>
                                             <span class="trending-year">Febuary 2025</span>
                                          </div>
                                       </div>
                                       <div class="iq-custom-select d-inline-block sea-epi mb-4">
                                          <select name="cars"
                                             class="form-control season-select select2-basic-single js-states">
                                             <option value="season1">Season 1</option>
                                             <option value="season2">Season 2</option>
                                             <option value="season3">Season 3</option>
                                          </select>
                                       </div>
                                       <div class="position-relative swiper swiper-card" data-slide="4" data-laptop="3"
                                          data-tab="2" data-mobile="2" data-mobile-sm="1" data-autoplay="false"
                                          data-loop="true" data-navigation="false" data-pagination="true">
                                          <ul class="p-0 swiper-wrapper m-0  list-inline">
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s1e3-rivals-and-revelations.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 04: Rivals and Revelations</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">After docking at a lawless
                                                                                                island, the crew meets a witch who claims she can help them decipher the
                                                                                                map in exchange for a favor. But her price may be more than they’re
                                                                                                willing to pay, and they discover her dark connection to one of their
                                                                                                own.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            40m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s1e2-hidden-allies.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 03: Hidden Allies</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">The crew battles a storm and
                                                                                                discovers that an old shipwreck nearby holds clues about their map.
                                                                                                Haunted by memories of lost loved ones, the crew faces restless spirits
                                                                                                that threaten to sink The Day One.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            30m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s1e1-setting-sail.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 02: Setting Sail</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">When a young sailor with a
                                                                                                mysterious map stumbles onto the deck of The Day One, Captain Roarke
                                                                                                saves him from execution. The crew quickly learns that the map may lead
                                                                                                to The Haven However, rival pirates and a pursuing navy are hot on their
                                                                                                trail.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            45m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s1e1-setting-sail.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 01: Rivals and Revelations</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">After docking at a lawless
                                                                                                island, the crew meets a witch who claims she can help them decipher the
                                                                                                map in exchange for a favor. But her price may be more than they’re
                                                                                                willing to pay, and they discover her dark connection to one of their
                                                                                                own.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            41m</p>
                                                    </div>
                                                </div>                                             </li>
                                          </ul>
                                          <div class="swiper-pagination d-block"></div>
                                       </div>
                                    </div>
                                 </div>
                                 <div id="trending-data-11" class="tab-pane fade" role="tabpanel"
                                    aria-labelledby="trending-data-tab-11" tabindex="0">
                                    <div
                                       class=" trending-info align-items-center w-100 animated fadeInUp iq-ltr-direction text-center">
                                       <h3 class="trending-text big-title text-uppercase texture-text mt-2">Watch
                                          Trailer</h3>
                                       <div class="episodes-contens mt-5">
                                          <div
                                             class="tab-watch-trailer-container d-inline-block rounded-3 overflow-hidden">
                                             <div class="tab-watch-trailer position-relative rounded-3 overflow-hidden">
                                                <img src="/assets/images/video/trailer-poster.webp"
                                                   class="trailer-image" alt="trailer-image">
                                                <a data-fslightbox="html5-video"
                                                   href="/assets/images/video/trailer.mp4"
                                                   class="video-open playbtn text-decoration-none" tabindex="0">
                                                   <svg version="1.1" xmlns="http://www.w3.org/2000/svg"
                                                      xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
                                                      width="80px" height="80px" viewBox="0 0 213.7 213.7"
                                                      enable-background="new 0 0 213.7 213.7" xml:space="preserve">
                                                      <polygon class="triangle" fill="none" stroke-width="7"
                                                         stroke-linecap="round" stroke-linejoin="round"
                                                         stroke-miterlimit="10"
                                                         points="73.5,62.5 148.5,105.8 73.5,149.1 "></polygon>
                                                      <circle class="circle" fill="none" stroke-width="7"
                                                         stroke-linecap="round" stroke-linejoin="round"
                                                         stroke-miterlimit="10" cx="106.8" cy="106.8" r="103.3">
                                                      </circle>
                                                   </svg>
                                                   <span class="w-trailor text-uppercase"> Watch Trailer </span>
                                                </a>
                                             </div>
                                          </div>
                                       </div>
                                    </div>
                                 </div>
                                 <div id="trending-data-12" class="tab-pane fade" role="tabpanel"
                                    aria-labelledby="trending-data-tab-12" tabindex="0">
                                    <div
                                       class=" trending-info align-items-center w-100 animated fadeInUp iq-ltr-direction">
                                       <h3 class="trending-text big-title text-uppercase texture-text mb-5">Recommended
                                          For You</h3>
                                       <div class="position-relative swiper swiper-card" data-slide="4" data-laptop="3"
                                          data-tab="2" data-mobile="1" data-mobile-sm="1" data-autoplay="false"
                                          data-loop="true" data-navigation="false" data-pagination="true">
                                          <ul class="p-0 swiper-wrapper m-0  list-inline">
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s1e3-rivals-and-revelations.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 04: The Da Vinci Code</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">Based on Dan Brown&#x27;s
                                                                                                bestselling novel, this thriller follows Robert Langdon, a symbologist,
                                                                                                as he uncovers a secret society&#x27;s conspiracy involving the Catholic
                                                                                                Church. Alongside cryptologist Sophie Neveu, Langdon races to unravel a
                                                                                                hidden mystery involving Leonardo da Vinci&#x27;s works and a powerful
                                                                                                ancient secret.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            45m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s1e2-hidden-allies.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 03: Tomb Raider</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">This reboot of the Tomb
                                                                                                Raider franchise follows Lara Croft as she embarks on a dangerous
                                                                                                adventure to uncover her father&#x27;s mysterious disappearance. The film
                                                                                                mixes exploration, historical puzzles, and action, similar to the
                                                                                                Assassin&#x27;s Creed themes of uncovering secrets hidden in history.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            35m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s1e1-setting-sail.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 04: Sherlock Holmes</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">In Victorian London,
                                                                                                Sherlock Holmes and his partner Dr. Watson uncover a plot that involves
                                                                                                secret societies, ancient rituals, and a dangerous conspiracy. With
                                                                                                sharp wit and action-packed sequences, the movie evokes a sense of
                                                                                                historical intrigue and hidden knowledge, much like the Assassin&#x27;s Creed
                                                                                                games.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            36m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s1e3-rivals-and-revelations.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 01: Rivals and Revelations</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">After docking at a lawless
                                                                                                island, the crew meets a witch who claims she can help them decipher the
                                                                                                map in exchange for a favor. But her price may be more than they’re
                                                                                                willing to pay, and they discover her dark connection to one of their
                                                                                                own.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            41m</p>
                                                    </div>
                                                </div>                                             </li>
                                          </ul>
                                          <div class="swiper-pagination d-block"></div>
                                       </div>
                                    </div>
                                 </div>
                              </div>
                           </div>
                        </div>
                     </li>
                     <li class="swiper-slide slider-big-img-5">
                        <div class="trending-tab-slider-image">
                           <img src="/assets/images/media/vikings.webp" alt="trending-tab-slider-image">
                        </div>
                        <div class="tranding-block position-relative">
                           <div class="trending-custom-tab">
                              <div class="tab-title-info position-relative">
                                 <ul class="trending-pills iq-custom-tab flex-nowrap d-flex nav nav-pills justify-content-md-center align-items-center text-center list-inline"
                                    id="trending-tab-5" role="tablist">
                                    <li class="nav-item" role="presentation">
                                       <a class="nav-link active" id="trending-data-tab-17" data-bs-toggle="pill"
                                          data-bs-target="#trending-data-17" aria-controls="trending-data-17" role="tab"
                                          aria-selected="true">Overview</a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                       <a class="nav-link" id="trending-data-tab-18" data-bs-toggle="pill"
                                          data-bs-target="#trending-data-18" aria-controls="trending-data-18" role="tab"
                                          aria-selected="false">Episodes</a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                       <a class="nav-link" id="trending-data-tab-19" data-bs-toggle="pill"
                                          data-bs-target="#trending-data-19" aria-controls="trending-data-19" role="tab"
                                          aria-selected="false">Trailers</a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                       <a class="nav-link" id="trending-data-tab-20" data-bs-toggle="pill"
                                          data-bs-target="#trending-data-20" aria-controls="trending-data-20" role="tab"
                                          aria-selected="false">Similar Like This</a>
                                    </li>
                                 </ul>
                              </div>
                              <div class="tab-content trending-content" id="trending-tab-5-content">
                                 <div id="trending-data-17" class="tab-pane fade show active" role="tabpanel"
                                    aria-labelledby="trending-data-tab-17" tabindex="0">
                                    <div
                                       class=" trending-info align-items-center w-100 animated fadeInUp iq-ltr-direction">
                                       <h1 class="trending-text big-title text-capitalize texture-text">vikings</h1>
                                       <div
                                          class="d-flex align-items-center text-white text-detail flex-wrap gap-1 gap-md-3">
                                          <span>2 Seasons</span>
                                          <div class="d-flex align-items-center gap-1">
                                             <i class="ph ph-calendar-dots"></i>
                                             <span class="trending-year">
                                                January 2025</span>
                                          </div>
                                       </div>
                                       <div class="d-flex align-items-center flex-wrap series mb-4 gap-3">
                                          <a href="javascript:void(0);"><img
                                                src="/assets/images/pages/trending-label.webp"
                                                class="img-fluid trending-label-img  rounded-3" alt="img"></a>
                                          <span class="fw-bold">#6 in Series Today</span>
                                       </div>
                                       <p class="trending-dec line-count-4">As Ragnar Lodbrok, a Norse farmer, carries
                                          out triumphant raids into English territory with the help of his fellow
                                          warriors, he ends up holding sway over the Vikings and becoming a Scandinavian
                                          king.</p>
                                       <div class="p-btns">
                                          <div class="iq-button">
                                             <a href="/tv-show-detail.html"
                                                class="btn btn-primary text-uppercase position-relative rounded-3">
                                                <div class="d-flex align-items-center gap-2">
                                                   <span class="button-text">Play Now</span>
                                                   <i class="ph-fill ph-play"></i>
                                                </div>
                                             </a>
                                          </div>
                                       </div>
                                    </div>
                                 </div>
                                 <div id="trending-data-18" class="tab-pane fade" role="tabpanel"
                                    aria-labelledby="trending-data-tab-18" tabindex="0">
                                    <div
                                       class=" trending-info align-items-center w-100 animated fadeInUp iq-ltr-direction">
                                       <h1 class="trending-text big-title text-capitalize texture-text">vikings</h1>
                                       <div
                                          class="d-flex align-items-center text-white text-detail flex-wrap mb-4 gap-3">
                                          <span class="season_date">
                                             2 Seasons
                                          </span>
                                          <div class="d-flex align-items-center gap-1">
                                             <i class="ph ph-calendar-dots"></i>
                                             <span class="trending-year">January 2025</span>
                                          </div>
                                       </div>
                                       <div class="iq-custom-select d-inline-block sea-epi mb-4">
                                          <select name="cars"
                                             class="form-control season-select select2-basic-single js-states">
                                             <option value="season1">Season 1</option>
                                             <option value="season2">Season 2</option>
                                             <option value="season3">Season 3</option>
                                          </select>
                                       </div>
                                       <div class="position-relative swiper swiper-card" data-slide="4" data-laptop="3"
                                          data-tab="2" data-mobile="2" data-mobile-sm="1" data-autoplay="false"
                                          data-loop="true" data-navigation="false" data-pagination="true">
                                          <ul class="p-0 swiper-wrapper m-0  list-inline">
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s1e5-Lost-Souls.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 05 : Lost Souls</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">As the chemistry teacher&#x27;s
                                                                                                alter ego emerged, a ruthless and cunning individual known only by a
                                                                                                pseudonym, the boundaries between right and wrong blurred into
                                                                                                obscurity. The series masterfully explored the consequences of choices,
                                                                                                unraveling a narrative that left viewers questioning the very fabric of
                                                                                                morality.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            30m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s1e4-the-signal.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 04 : The Signal</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">As the chemistry teacher&#x27;s
                                                                                                alter ego emerged, a ruthless and cunning individual known only by a
                                                                                                pseudonym, the boundaries between right and wrong blurred into
                                                                                                obscurity. The series masterfully explored the consequences of choices,
                                                                                                unraveling a narrative that left viewers questioning the very fabric of
                                                                                                morality.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            45m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s1e3-All-Change.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 03 : All Change </h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">As the chemistry teacher&#x27;s
                                                                                                alter ego emerged, a ruthless and cunning individual known only by a
                                                                                                pseudonym, the boundaries between right and wrong blurred into
                                                                                                obscurity. The series masterfully explored the consequences of choices,
                                                                                                unraveling a narrative that left viewers questioning the very fabric of
                                                                                                morality.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            40m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s1e2-king-of-kings.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> E02 : King of King</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">As the chemistry teacher&#x27;s
                                                                                                alter ego emerged, a ruthless and cunning individual known only by a
                                                                                                pseudonym, the boundaries between right and wrong blurred into
                                                                                                obscurity. The series masterfully explored the consequences of choices,
                                                                                                unraveling a narrative that left viewers questioning the very fabric of
                                                                                                morality.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            30m</p>
                                                    </div>
                                                </div>                                             </li>
                                          </ul>
                                          <div class="swiper-pagination d-block"></div>
                                       </div>
                                    </div>
                                 </div>
                                 <div id="trending-data-19" class="tab-pane fade" role="tabpanel"
                                    aria-labelledby="trending-data-tab-19" tabindex="0">
                                    <div
                                       class=" trending-info align-items-center w-100 animated fadeInUp iq-ltr-direction text-center">
                                       <h3 class="trending-text big-title text-uppercase texture-text mt-2">Watch
                                          Trailer</h3>
                                       <div class="episodes-contens mt-5">
                                          <div
                                             class="tab-watch-trailer-container d-inline-block rounded-3 overflow-hidden">
                                             <div class="tab-watch-trailer position-relative rounded-3 overflow-hidden">
                                                <img src="/assets/images/video/trailer-poster.webp"
                                                   class="trailer-image" alt="trailer-image">
                                                <a data-fslightbox="html5-video"
                                                   href="/assets/images/video/trailer.mp4"
                                                   class="video-open playbtn text-decoration-none" tabindex="0">
                                                   <svg version="1.1" xmlns="http://www.w3.org/2000/svg"
                                                      xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
                                                      width="80px" height="80px" viewBox="0 0 213.7 213.7"
                                                      enable-background="new 0 0 213.7 213.7" xml:space="preserve">
                                                      <polygon class="triangle" fill="none" stroke-width="7"
                                                         stroke-linecap="round" stroke-linejoin="round"
                                                         stroke-miterlimit="10"
                                                         points="73.5,62.5 148.5,105.8 73.5,149.1 "></polygon>
                                                      <circle class="circle" fill="none" stroke-width="7"
                                                         stroke-linecap="round" stroke-linejoin="round"
                                                         stroke-miterlimit="10" cx="106.8" cy="106.8" r="103.3">
                                                      </circle>
                                                   </svg>
                                                   <span class="w-trailor text-uppercase"> Watch Trailer </span>
                                                </a>
                                             </div>
                                          </div>
                                       </div>
                                    </div>
                                 </div>
                                 <div id="trending-data-20" class="tab-pane fade" role="tabpanel"
                                    aria-labelledby="trending-data-tab-20" tabindex="0">
                                    <div
                                       class=" trending-info align-items-center w-100 animated fadeInUp iq-ltr-direction">
                                       <h3 class="trending-text big-title text-uppercase texture-text mb-5">Recommended
                                          For You</h3>
                                       <div class="position-relative swiper swiper-card" data-slide="4" data-laptop="3"
                                          data-tab="2" data-mobile="1" data-mobile-sm="1" data-autoplay="false"
                                          data-loop="true" data-navigation="false" data-pagination="true">
                                          <ul class="p-0 swiper-wrapper m-0  list-inline">
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s2e3-the-raft-of-the-medusa.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 04: Valhalla Rising</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">A dark and atmospheric film
                                                                                                about a mute warrior in the Viking Age who embarks on a journey to the
                                                                                                New World.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            45m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s2e2-its-only-magic.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 03: Rise of the Mech-Titans</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base"> A story set during the
                                                                                                Viking Age, following a young man who must take up arms to defend his
                                                                                                village against an invading force</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            35m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s2e1-the-signal.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 02: The Dragon of Neo-Earth</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">In a post-apocalyptic future
                                                                                                where humanity is on the brink of extinction, a robotic terminator is
                                                                                                sent back in time to protect the last hope for Earth—a dragon hiding in
                                                                                                the ruins. As they face a powerful machine uprising, they must unite to
                                                                                                restore balance to a dying world.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            36m</p>
                                                    </div>
                                                </div>                                             </li>
                                             <li class="swiper-slide">
                                                <div class="episode-block rounded-3">
                                                    <div class="block-image position-relative z-1">
                                                        <a href="/episode.html">
                                                            <img src="/assets/images/media/episode/s2e2-its-only-magic.webp" class="img-fluid img-zoom" alt="showImg-" loading="lazy">
                                                        </a>
                                                    </div>
                                                
                                                    <div class="episode-detail fw-medium position-absolute">
                                                        <h6 class="mt-2 mb-0"> 01: The Silent Hero</h6>
                                                        <span class="mb-0 line-count-2 mt-2 small lh-base">A mute street performer in a
                                                                                                futuristic dystopia must navigate the harsh realities of a robotic-run
                                                                                                city while using his wit and charm to inspire a revolution. His silent
                                                                                                gestures and slapstick antics become a beacon of hope for the oppressed
                                                                                                citizens.</span>
                                                    </div>
                                                    <div class="position-absolute episode-time font-size-12 fw-bold px-1 rounded-1 d-flex align-items-center z-2 gap-1">
                                                        <i class="ph ph-clock"></i>
                                                        <p class="mb-0">
                                                            41m</p>
                                                    </div>
                                                </div>                                             </li>

                                          </ul>
                                          <div class="swiper-pagination d-block"></div>
                                       </div>
                                    </div>
                                 </div>
                              </div>
                           </div>
                        </div>
                     </li>
                  </ul>
               </div>
            </div>
         </div>
      </div>
   </div>
</section>

<div class="container-fluid">
   <div class="overflow-hidden">
      <div class="recommended-blocks section-wraper">
         <div class="d-flex align-items-center justify-content-between px-1 mb-2 pb-1 mb-md-4 pb-md-0">
            <h4 class="main-title text-capitalize mb-0 fw-medium">Recommended TV Show</h4>
         </div>
         <div class="card-style-slider">
            <div class="position-relative swiper swiper-card" data-slide="6" data-laptop="4" data-tab="3"
               data-mobile="2" data-mobile-sm="2" data-autoplay="false" data-loop="true" data-navigation="true"
               data-pagination="true">
               <ul class="p-0 swiper-wrapper m-0  list-inline">
                  <li class="swiper-slide">
                     <div class="iq-card card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/tv-show-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/pirates-ofdayones-orignal.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize">
                                 <a href="/tv-show-detail.html">Pirates ofdayones Orignal</a>
                               </h5>
                               <div class="d-flex align-items-center gap-3">
                                 <div class="d-flex align-items-center gap-2">
                                   <i class="ph ph-translate"></i>
                                   <small class="font-size-12">English</small>
                                 </div>
                               </div>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/tv-show-detail.html" class="btn btn-primary w-100">Play Now
                               </a>
                             </div>
                           </div>
                     
                         </div>
                       </div>
                     
                     </div>
                  </li>
                  <li class="swiper-slide">
                     <div class="iq-card card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/tv-show-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/the-hunter-portrait.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize">
                                 <a href="/tv-show-detail.html">The
                                          Hunter</a>
                               </h5>
                               <div class="d-flex align-items-center gap-3">
                                 <div class="d-flex align-items-center gap-2">
                                   <i class="ph ph-translate"></i>
                                   <small class="font-size-12">English</small>
                                 </div>
                               </div>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/tv-show-detail.html" class="btn btn-primary w-100">Play Now
                               </a>
                             </div>
                           </div>
                     
                         </div>
                       </div>
                     
                     </div>
                  </li>
                  <li class="swiper-slide">
                     <div class="iq-card card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/tv-show-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/lost-in-space-portrait.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize">
                                 <a href="/tv-show-detail.html">Lost in
                                          Space</a>
                               </h5>
                               <div class="d-flex align-items-center gap-3">
                                 <div class="d-flex align-items-center gap-2">
                                   <i class="ph ph-translate"></i>
                                   <small class="font-size-12">English</small>
                                 </div>
                               </div>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/tv-show-detail.html" class="btn btn-primary w-100">Play Now
                               </a>
                             </div>
                           </div>
                     
                         </div>
                         <div class="position-absolute z-1 primium-product d-flex align-items-center justify-content-center"
                           data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Premium" data-bs-original-title="Premium">
                           <i class="ph-fill ph-crown "></i>
                         </div>
                       </div>
                     
                     </div>
                  </li>
                  <li class="swiper-slide">
                     <div class="iq-card card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/tv-show-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/castle-rock-portrait.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize">
                                 <a href="/tv-show-detail.html">Castle
                                          Rock</a>
                               </h5>
                               <div class="d-flex align-items-center gap-3">
                                 <div class="d-flex align-items-center gap-2">
                                   <i class="ph ph-translate"></i>
                                   <small class="font-size-12">English</small>
                                 </div>
                               </div>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/tv-show-detail.html" class="btn btn-primary w-100">Play Now
                               </a>
                             </div>
                           </div>
                     
                         </div>
                       </div>
                     
                     </div>
                  </li>
                  <li class="swiper-slide">
                     <div class="iq-card card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/tv-show-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/breaking-bad-portrait.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize">
                                 <a href="/tv-show-detail.html">Breaking
                                          Bad</a>
                               </h5>
                               <div class="d-flex align-items-center gap-3">
                                 <div class="d-flex align-items-center gap-2">
                                   <i class="ph ph-translate"></i>
                                   <small class="font-size-12">English</small>
                                 </div>
                               </div>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/tv-show-detail.html" class="btn btn-primary w-100">Play Now
                               </a>
                             </div>
                           </div>
                     
                         </div>
                       </div>
                     
                     </div>
                  </li>
                  <li class="swiper-slide">
                     <div class="iq-card card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/tv-show-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/assassins-creed-portrait.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize">
                                 <a href="/tv-show-detail.html">Assassins Creed</a>
                               </h5>
                               <div class="d-flex align-items-center gap-3">
                                 <div class="d-flex align-items-center gap-2">
                                   <i class="ph ph-translate"></i>
                                   <small class="font-size-12">English</small>
                                 </div>
                               </div>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/tv-show-detail.html" class="btn btn-primary w-100">Play Now
                               </a>
                             </div>
                           </div>
                     
                         </div>
                       </div>
                     
                     </div>
                  </li>
                  <li class="swiper-slide">
                     <div class="iq-card card-hover">
                       <div class="block-images position-relative w-100">
                         <div class="img-box w-100">
                           <a href="/tv-show-detail.html" class="position-relative top-0 bottom-0 start-0 end-0">
                             <img src="/assets/images/media/vikings-portrait.webp" alt="movie-card"
                               class="img-fluid object-cover w-100 d-block border-0 rounded-3">
                           </a>
                         </div>
                         <div class="card-description with-transition">
                           <ul class="genres-list p-0 mb-2 d-flex align-items-center flex-wrap list-inline">
                             <li class="fw-semi-bold">
                               <a href="view-all-movie.html" tabindex="0" class="font-size-14 ">
                                 Action </a>
                             </li>
                           </ul>
                           <div class="cart-content">
                             <div class="content-left">
                               <h5 class="iq-title text-capitalize">
                                 <a href="/tv-show-detail.html">Vikings</a>
                               </h5>
                               <div class="d-flex align-items-center gap-3">
                                 <div class="d-flex align-items-center gap-2">
                                   <i class="ph ph-translate"></i>
                                   <small class="font-size-12">English</small>
                                 </div>
                               </div>
                             </div>
                           </div>
                           <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                             <a href="watchlist-detail.html"
                               class="d-flex align-items-center justify-content-center flex-shrink-0 border-0 add-to-wishlist-btn btn btn-secondary"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                               data-bs-title="Add to Watchlist">
                               <i class="ph ph-plus font-size-18"></i>
                             </a>
                             <div class="iq-play-button iq-button">
                               <a href="/tv-show-detail.html" class="btn btn-primary w-100">Play Now
                               </a>
                             </div>
                           </div>
                     
                         </div>
                         <div class="position-absolute z-1 primium-product d-flex align-items-center justify-content-center"
                           data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Premium" data-bs-original-title="Premium">
                           <i class="ph-fill ph-crown "></i>
                         </div>
                       </div>
                     
                     </div>
                  </li>
               </ul>
               <div class="swiper-button swiper-button-next"></div>
               <div class="swiper-button swiper-button-prev"></div>
            </div>
         </div>
      </div>
   </div>
</div>

<div class="streamit-mobile-footer-menu" aria-label="Mobile Footer Navigation">
    <ul class="footer-menu list-inline d-flex align-items-center justify-content-between m-0">
        <li class="footer-menu-item">
            <a href="view-all-movie.html" class="menu-link font-size-12">
                <i class="ph ph-film-reel d-block  text-center "></i>
                Movies </a>
        </li>
        <li class="footer-menu-item">
            <a href="view-all-movie.html" class="menu-link font-size-12">
                <i class="ph ph-monitor-play d-block  text-center "></i>
                Videos </a>
        </li>
        <li class="footer-menu-item">
            <a href="view-all-movie.html" class="menu- font-size-12">
                <i class="ph ph-magnifying-glass d-block  text-center "></i>
                Search </a>
        </li>
        <li class="footer-menu-item">
            <a href="view-all-movie.html" class="menu-link font-size-12">
                <i class="ph ph-television d-block  text-center "></i>
                TV Shows </a>
        </li>
        <li class="footer-menu-item">
            <a href="profile-marvin.html" class="menu-link font-size-12">
                <i class="ph ph-user d-block  text-center "></i>
                Profile </a>
        </li>
    </ul>
</div>
  </main>
<?php include 'includes/footer.php'; ?>