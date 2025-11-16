<?php include 'includes/header.php'; ?>
    <!--bread-crumb-->
    <!--bread-crumb-->

<section class="section-padding profile-section-padding">
    <div class="container-fluid">
        <!-- user profile start -->
        <div class="px-sm-5 px-3 py-4 rounded-3 profile-user-info">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <div class="d-flex align-items-center flex-wrap gap-3">
                        <div class="profile-image flex-shrink-0">
                            <img src="assets/images/user/user6.jpg" alt="Marvin McKinney"
                                class="user-image user-profile-image">
                        </div>
                        <div class="profile-info">
                            <h5 class="mt-0 info-title">Marvin McKinney</h5>
                            <p class="mb-1 mt-0">marvin@demo.com</p>
                            <p class="m-0">marvin</p>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 mt-sm-0 mt-3">
                    <div class="d-flex align-items-center justify-content-sm-end gap-3">
                        <button type="button" class="btn btn-sm custom-btn-sm btn-primary text-nowrap fw-semibold"
                            data-bs-toggle="modal" data-bs-target="#edit-profile-modal">
                            <i class="icon-edit-icon"></i> Edit Profile </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-5">
            <div class="col-xl-2 col-lg-3">
                <ul class="profile-page-list list-inline p-0 mx-0 nav-tabs" role="tablist">
                    <li class="profile-page-list-item">
                        <a href="profile-marvin.html#" class="profile-page-list-link active" data-bs-toggle="tab"
                            data-bs-target="#playlist-tab" role="tab" aria-selected="true">
                            Playlist </a>
                    </li>
                    <li class="profile-page-list-item">
                        <a href="profile-marvin.html#" class="profile-page-list-link" data-bs-toggle="tab" data-bs-target="#watchlist-tab"
                            role="tab" aria-selected="true">
                            Watch List </a>
                    </li>
                    <li class="profile-page-list-item">
                        <a href="profile-marvin.html#" class="profile-page-list-link" data-bs-toggle="tab"
                            data-bs-target="#notification-tab" role="tab" aria-selected="true">
                            Notification </a>
                    </li>
                    <li class="profile-page-list-item">
                        <a href="profile-marvin.html#" class="profile-page-list-link" data-bs-toggle="tab" data-bs-target="#membership-tab"
                            role="tab" aria-selected="true">
                            Membership </a>
                    </li>
                </ul>
            </div>

            <div class="col-xl-10 col-lg-9 mt-5 mt-lg-0">
                <div class="tab-content" id="profile-menu-content">
                    <div class="tab-pane fade show active" id="playlist-tab" role="tabpanel">
                        <div class="play-lists">
                            <div
                                class="row g-2 column-reverce align-items-center border-bottom playlist-bottom-margin">
                                <div class="col-8 col-sm-9 col-lg-10">
                                    <div id="item-nav">
                                        <div class="item-list-tabs no-ajax css_prefix-tab-lists" id="object-nav">
                                            <!-- Playlist Tabs -->
                                            <ul class="nav nav-underline data-search-tab" id="pills-tab" role="tablist">
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link active" id="pills-movie-tab"
                                                        data-bs-toggle="pill" data-bs-target="#pills-movie"
                                                        type="button" role="tab" aria-controls="pills-movie"
                                                        aria-selected="true">
                                                        Movie </button>
                                                </li>
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link " id="pills-video-tab" data-bs-toggle="pill"
                                                        data-bs-target="#pills-video" type="button" role="tab"
                                                        aria-controls="pills-video" aria-selected="false" tabindex="-1">
                                                        Video </button>
                                                </li>
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link " id="pills-episode-tab"
                                                        data-bs-toggle="pill" data-bs-target="#pills-episode"
                                                        type="button" role="tab" aria-controls="pills-episode"
                                                        aria-selected="false" tabindex="-1">
                                                        Episode </button>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4 col-sm-3 col-lg-2">
                                    <div class="d-flex justify-content-md-end mb-md-0 mb-1">
                                        <button type="button" class="manage_playlist  btn btn-link"
                                            data-bs-toggle="modal" data-bs-target="#creatplaylistModal">
                                            <span class="h-100 w-100 d-block" data-bs-toggle="tooltip"
                                                data-bs-placement="top" data-bs-title="Playlist">
                                                Add Playlist </span>
                                        </button>

                                        <div class="modal fade view-more-data-modal edit-profile-modal"
                                            id="creatplaylistModal" tabindex="-1" aria-modal="true" role="dialog">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="exampleModalLabelplaylist">Create
                                                            Playlist
                                                        </h5>
                                                        <button type="button" class="btn-close me-0"
                                                            data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body pt-0">
                                                        <form id="st_creat_playlist">
                                                            <input id="st_playlist_id" type="hidden" value="">
                                                            <div class="form-group mb-4">
                                                                <label class="form-label">Playlist Title</label>
                                                                <span class="text-danger">*</span>
                                                                <input class="form-control" type="text"
                                                                    id="st_playlist_title" value="">
                                                            </div>
                                                            <div class="form-group playlist-select mb-4">
                                                                <label class="form-label">Select Playlist Type</label>
                                                                <span class="text-danger">*</span>
                                                                <select name="movieselect"
                                                                    class="form-control movie-select select2-basic-single js-states">
                                                                    <option value="1">Movie</option>
                                                                    <option value="2">Video</option>
                                                                    <option value="3">Episode</option>
                                                                </select>
                                                            </div>
                                                            <div class="iq-button">
                                                                <button type="button"
                                                                    class="btn btn-primary text-capitalize position-relative rounded-3"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#addNewPlaylist">
                                                                    <span class="button-text">Create Playlist</span>
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Playlist Tab Content -->
                            <div class="tab-content" id="pills-tabContent">
                                <div class="tab-pane fade active show" id="pills-movie" role="tabpanel" tabindex="0"
                                    aria-labelledby="pills-movie-tab">
                                    <div
                                        class="row gy-4 row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-3 row-cols-xl-4 row-cols-xxl-5 data-listing">
                                        <div class="col">
                                            <div class="playlist-card">
                                                <!-- Playlist Image -->
                                                <div class="image-box">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html">
                                                        <img src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/migration.webp" alt="90s Throwback"
                                                            class="img-fluid object-cover w-100 border-0">
                                                    </a>
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html" class="play-icon">
                                                        <i class="icon-play-button"></i>
                                                    </a>
                                                </div>
                                                <!-- Playlist Content -->
                                                <div class="content-part">
                                                    <div class="d-flex justify-content-between gap-2 mb-1">
                                                        <h5 class="my-0 text-capitalize">90s
                                                                                        Throwback</h5>
                                                        <div class="dropdown">
                                                            <button class="btn p-0 border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                <i class="icon-three-dots-vertical"></i>
                                                            </button>
                                                            <ul class="dropdown-menu border">
                                                                <li><a data-playlist-name="90s Throwback" data-playlist-id="14" data-post-type="movie"
                                                                        class="manage_playlist dropdown-item update_user_playlist">Update</a>
                                                                </li>
                                                                <li><a data-playlist_id="14" data-post-type="movie"
                                                                        class="dropdown-item delete_user_playlist">Delete</a>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                    <div class="small mb-1">
                                                        <small>
                                                            4 Movies
                                                        </small>
                                                    </div>
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html" class="btn btn-link btn-playlist p-0 border-radius-0">View
                                                        playlist</a>
                                                </div>
                                            </div>                                        </div>
                                        <div class="col">
                                            <div class="playlist-card">
                                                <!-- Playlist Image -->
                                                <div class="image-box">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html">
                                                        <img src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/jumanjj.webp" alt="90s Throwback"
                                                            class="img-fluid object-cover w-100 border-0">
                                                    </a>
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html" class="play-icon">
                                                        <i class="icon-play-button"></i>
                                                    </a>
                                                </div>
                                                <!-- Playlist Content -->
                                                <div class="content-part">
                                                    <div class="d-flex justify-content-between gap-2 mb-1">
                                                        <h5 class="my-0 text-capitalize">Action</h5>
                                                        <div class="dropdown">
                                                            <button class="btn p-0 border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                <i class="icon-three-dots-vertical"></i>
                                                            </button>
                                                            <ul class="dropdown-menu border">
                                                                <li><a data-playlist-name="90s Throwback" data-playlist-id="14" data-post-type="movie"
                                                                        class="manage_playlist dropdown-item update_user_playlist">Update</a>
                                                                </li>
                                                                <li><a data-playlist_id="14" data-post-type="movie"
                                                                        class="dropdown-item delete_user_playlist">Delete</a>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                    <div class="small mb-1">
                                                        <small>
                                                            3 Movies
                                                        </small>
                                                    </div>
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html" class="btn btn-link btn-playlist p-0 border-radius-0">View
                                                        playlist</a>
                                                </div>
                                            </div>                                        </div>
                                        <div class="col">
                                            <div class="playlist-card">
                                                <!-- Playlist Image -->
                                                <div class="image-box">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html">
                                                        <img src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/chosfies.webp" alt="90s Throwback"
                                                            class="img-fluid object-cover w-100 border-0">
                                                    </a>
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html" class="play-icon">
                                                        <i class="icon-play-button"></i>
                                                    </a>
                                                </div>
                                                <!-- Playlist Content -->
                                                <div class="content-part">
                                                    <div class="d-flex justify-content-between gap-2 mb-1">
                                                        <h5 class="my-0 text-capitalize">Blockbuster Trt</h5>
                                                        <div class="dropdown">
                                                            <button class="btn p-0 border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                <i class="icon-three-dots-vertical"></i>
                                                            </button>
                                                            <ul class="dropdown-menu border">
                                                                <li><a data-playlist-name="90s Throwback" data-playlist-id="14" data-post-type="movie"
                                                                        class="manage_playlist dropdown-item update_user_playlist">Update</a>
                                                                </li>
                                                                <li><a data-playlist_id="14" data-post-type="movie"
                                                                        class="dropdown-item delete_user_playlist">Delete</a>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                    <div class="small mb-1">
                                                        <small>
                                                            3 Movies
                                                        </small>
                                                    </div>
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html" class="btn btn-link btn-playlist p-0 border-radius-0">View
                                                        playlist</a>
                                                </div>
                                            </div>                                        </div>
                                        <div class="col">
                                            <div class="playlist-card">
                                                <!-- Playlist Image -->
                                                <div class="image-box">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html">
                                                        <img src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/Bumblebee.webp" alt="90s Throwback"
                                                            class="img-fluid object-cover w-100 border-0">
                                                    </a>
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html" class="play-icon">
                                                        <i class="icon-play-button"></i>
                                                    </a>
                                                </div>
                                                <!-- Playlist Content -->
                                                <div class="content-part">
                                                    <div class="d-flex justify-content-between gap-2 mb-1">
                                                        <h5 class="my-0 text-capitalize">Dramas</h5>
                                                        <div class="dropdown">
                                                            <button class="btn p-0 border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                <i class="icon-three-dots-vertical"></i>
                                                            </button>
                                                            <ul class="dropdown-menu border">
                                                                <li><a data-playlist-name="90s Throwback" data-playlist-id="14" data-post-type="movie"
                                                                        class="manage_playlist dropdown-item update_user_playlist">Update</a>
                                                                </li>
                                                                <li><a data-playlist_id="14" data-post-type="movie"
                                                                        class="dropdown-item delete_user_playlist">Delete</a>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                    <div class="small mb-1">
                                                        <small>
                                                            2 Movies
                                                        </small>
                                                    </div>
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html" class="btn btn-link btn-playlist p-0 border-radius-0">View
                                                        playlist</a>
                                                </div>
                                            </div>                                        </div>
                                        <div class="col">
                                            <div class="playlist-card">
                                                <!-- Playlist Image -->
                                                <div class="image-box">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html">
                                                        <img src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/venom.webp" alt="90s Throwback"
                                                            class="img-fluid object-cover w-100 border-0">
                                                    </a>
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html" class="play-icon">
                                                        <i class="icon-play-button"></i>
                                                    </a>
                                                </div>
                                                <!-- Playlist Content -->
                                                <div class="content-part">
                                                    <div class="d-flex justify-content-between gap-2 mb-1">
                                                        <h5 class="my-0 text-capitalize">Horror</h5>
                                                        <div class="dropdown">
                                                            <button class="btn p-0 border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                <i class="icon-three-dots-vertical"></i>
                                                            </button>
                                                            <ul class="dropdown-menu border">
                                                                <li><a data-playlist-name="90s Throwback" data-playlist-id="14" data-post-type="movie"
                                                                        class="manage_playlist dropdown-item update_user_playlist">Update</a>
                                                                </li>
                                                                <li><a data-playlist_id="14" data-post-type="movie"
                                                                        class="dropdown-item delete_user_playlist">Delete</a>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                    <div class="small mb-1">
                                                        <small>
                                                            1 Movies
                                                        </small>
                                                    </div>
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html" class="btn btn-link btn-playlist p-0 border-radius-0">View
                                                        playlist</a>
                                                </div>
                                            </div>                                        </div>
                                        <div class="col hidden-tags" style="display: none;">
                                            <div class="playlist-card">
                                                <!-- Playlist Image -->
                                                <div class="image-box">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html">
                                                        <img src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/rabbit.webp" alt="90s Throwback"
                                                            class="img-fluid object-cover w-100 border-0">
                                                    </a>
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html" class="play-icon">
                                                        <i class="icon-play-button"></i>
                                                    </a>
                                                </div>
                                                <!-- Playlist Content -->
                                                <div class="content-part">
                                                    <div class="d-flex justify-content-between gap-2 mb-1">
                                                        <h5 class="my-0 text-capitalize">Road
                                                                                        Trip Movies</h5>
                                                        <div class="dropdown">
                                                            <button class="btn p-0 border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                <i class="icon-three-dots-vertical"></i>
                                                            </button>
                                                            <ul class="dropdown-menu border">
                                                                <li><a data-playlist-name="90s Throwback" data-playlist-id="14" data-post-type="movie"
                                                                        class="manage_playlist dropdown-item update_user_playlist">Update</a>
                                                                </li>
                                                                <li><a data-playlist_id="14" data-post-type="movie"
                                                                        class="dropdown-item delete_user_playlist">Delete</a>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                    <div class="small mb-1">
                                                        <small>
                                                            1 Movies
                                                        </small>
                                                    </div>
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html" class="btn btn-link btn-playlist p-0 border-radius-0">View
                                                        playlist</a>
                                                </div>
                                            </div>                                        </div>
                                        <div class="col hidden-tags" style="display: none;">
                                            <div class="playlist-card">
                                                <!-- Playlist Image -->
                                                <div class="image-box">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html">
                                                        <img src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/yoshi.webp" alt="90s Throwback"
                                                            class="img-fluid object-cover w-100 border-0">
                                                    </a>
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html" class="play-icon">
                                                        <i class="icon-play-button"></i>
                                                    </a>
                                                </div>
                                                <!-- Playlist Content -->
                                                <div class="content-part">
                                                    <div class="d-flex justify-content-between gap-2 mb-1">
                                                        <h5 class="my-0 text-capitalize">Romantic ...</h5>
                                                        <div class="dropdown">
                                                            <button class="btn p-0 border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                <i class="icon-three-dots-vertical"></i>
                                                            </button>
                                                            <ul class="dropdown-menu border">
                                                                <li><a data-playlist-name="90s Throwback" data-playlist-id="14" data-post-type="movie"
                                                                        class="manage_playlist dropdown-item update_user_playlist">Update</a>
                                                                </li>
                                                                <li><a data-playlist_id="14" data-post-type="movie"
                                                                        class="dropdown-item delete_user_playlist">Delete</a>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                    <div class="small mb-1">
                                                        <small>
                                                            2 Movies
                                                        </small>
                                                    </div>
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html" class="btn btn-link btn-playlist p-0 border-radius-0">View
                                                        playlist</a>
                                                </div>
                                            </div>                                        </div>
                                    </div>
                                    <div class="text-center mt-4">
                                        <div class="iq-button">
                                            <a href="javascript:void(0)"
                                                class="btn btn-primary text-capitalize position-relative load-more-btn rounded-3">
                                                <span class="button-text">Load More</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="pills-tvshow" role="tabpanel" tabindex="0">
                                    <div
                                        class="row gy-4 row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-3 row-cols-xl-4 row-cols-xxl-5 ">
                                        <p class="text-center w-100">No playlists available.</p>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="pills-video" role="tabpanel" tabindex="0"
                                    aria-labelledby="pills-video-tab">
                                    <div
                                        class="row gy-4 row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-3 row-cols-xl-4 row-cols-xxl-5 ">
                                        <div class="col">
                                            <div class="playlist-card">
                                                <!-- Playlist Image -->
                                                <div class="image-box">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html">
                                                        <img src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/krishna.webp" alt="90s Throwback"
                                                            class="img-fluid object-cover w-100 border-0">
                                                    </a>
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html" class="play-icon">
                                                        <i class="icon-play-button"></i>
                                                    </a>
                                                </div>
                                                <!-- Playlist Content -->
                                                <div class="content-part">
                                                    <div class="d-flex justify-content-between gap-2 mb-1">
                                                        <h5 class="my-0 text-capitalize">Chill &amp;
                                                                                        Relax</h5>
                                                        <div class="dropdown">
                                                            <button class="btn p-0 border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                <i class="icon-three-dots-vertical"></i>
                                                            </button>
                                                            <ul class="dropdown-menu border">
                                                                <li><a data-playlist-name="90s Throwback" data-playlist-id="14" data-post-type="movie"
                                                                        class="manage_playlist dropdown-item update_user_playlist">Update</a>
                                                                </li>
                                                                <li><a data-playlist_id="14" data-post-type="movie"
                                                                        class="dropdown-item delete_user_playlist">Delete</a>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                    <div class="small mb-1">
                                                        <small>
                                                            3 Videos
                                                        </small>
                                                    </div>
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html" class="btn btn-link btn-playlist p-0 border-radius-0">View
                                                        playlist</a>
                                                </div>
                                            </div>                                        </div>
                                        <div class="col">
                                            <div class="playlist-card">
                                                <!-- Playlist Image -->
                                                <div class="image-box">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html">
                                                        <img src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/kung-fu-panda.webp" alt="90s Throwback"
                                                            class="img-fluid object-cover w-100 border-0">
                                                    </a>
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html" class="play-icon">
                                                        <i class="icon-play-button"></i>
                                                    </a>
                                                </div>
                                                <!-- Playlist Content -->
                                                <div class="content-part">
                                                    <div class="d-flex justify-content-between gap-2 mb-1">
                                                        <h5 class="my-0 text-capitalize">Love Of
                                                                                        Animals</h5>
                                                        <div class="dropdown">
                                                            <button class="btn p-0 border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                <i class="icon-three-dots-vertical"></i>
                                                            </button>
                                                            <ul class="dropdown-menu border">
                                                                <li><a data-playlist-name="90s Throwback" data-playlist-id="14" data-post-type="movie"
                                                                        class="manage_playlist dropdown-item update_user_playlist">Update</a>
                                                                </li>
                                                                <li><a data-playlist_id="14" data-post-type="movie"
                                                                        class="dropdown-item delete_user_playlist">Delete</a>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                    <div class="small mb-1">
                                                        <small>
                                                            2 Videos
                                                        </small>
                                                    </div>
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html" class="btn btn-link btn-playlist p-0 border-radius-0">View
                                                        playlist</a>
                                                </div>
                                            </div>                                        </div>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="pills-episode" role="tabpanel" tabindex="0"
                                    aria-labelledby="pills-episode-tab">
                                    <div
                                        class="row gy-4 row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-3 row-cols-xl-4 row-cols-xxl-5 ">
                                        <div class="col">
                                            <div class="playlist-card">
                                                <!-- Playlist Image -->
                                                <div class="image-box">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html">
                                                        <img src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/minions-2.webp" alt="90s Throwback"
                                                            class="img-fluid object-cover w-100 border-0">
                                                    </a>
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html" class="play-icon">
                                                        <i class="icon-play-button"></i>
                                                    </a>
                                                </div>
                                                <!-- Playlist Content -->
                                                <div class="content-part">
                                                    <div class="d-flex justify-content-between gap-2 mb-1">
                                                        <h5 class="my-0 text-capitalize">Episode Unlimited</h5>
                                                        <div class="dropdown">
                                                            <button class="btn p-0 border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                <i class="icon-three-dots-vertical"></i>
                                                            </button>
                                                            <ul class="dropdown-menu border">
                                                                <li><a data-playlist-name="90s Throwback" data-playlist-id="14" data-post-type="movie"
                                                                        class="manage_playlist dropdown-item update_user_playlist">Update</a>
                                                                </li>
                                                                <li><a data-playlist_id="14" data-post-type="movie"
                                                                        class="dropdown-item delete_user_playlist">Delete</a>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                    <div class="small mb-1">
                                                        <small>
                                                            2 Episodes
                                                        </small>
                                                    </div>
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html" class="btn btn-link btn-playlist p-0 border-radius-0">View
                                                        playlist</a>
                                                </div>
                                            </div>                                        </div>
                                        <div class="col">
                                            <div class="playlist-card">
                                                <!-- Playlist Image -->
                                                <div class="image-box">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html">
                                                        <img src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/krishna.webp" alt="90s Throwback"
                                                            class="img-fluid object-cover w-100 border-0">
                                                    </a>
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html" class="play-icon">
                                                        <i class="icon-play-button"></i>
                                                    </a>
                                                </div>
                                                <!-- Playlist Content -->
                                                <div class="content-part">
                                                    <div class="d-flex justify-content-between gap-2 mb-1">
                                                        <h5 class="my-0 text-capitalize">Radhe
                                                                                        Krishna Stories</h5>
                                                        <div class="dropdown">
                                                            <button class="btn p-0 border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                <i class="icon-three-dots-vertical"></i>
                                                            </button>
                                                            <ul class="dropdown-menu border">
                                                                <li><a data-playlist-name="90s Throwback" data-playlist-id="14" data-post-type="movie"
                                                                        class="manage_playlist dropdown-item update_user_playlist">Update</a>
                                                                </li>
                                                                <li><a data-playlist_id="14" data-post-type="movie"
                                                                        class="dropdown-item delete_user_playlist">Delete</a>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                    <div class="small mb-1">
                                                        <small>
                                                            1 Episode
                                                        </small>
                                                    </div>
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//playlist-detail.html" class="btn btn-link btn-playlist p-0 border-radius-0">View
                                                        playlist</a>
                                                </div>
                                            </div>                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="watchlist-tab" role="tabpanel">
                        <div class="col-md-12">
                            <div class="border-bottom mb-5 watchlist-tab">
                                <div id="item-nav">
                                    <div class="item-list-tabs no-ajax css_prefix-tab-lists" id="object-nav">

                                        <!-- Watchlist Tabs -->
                                        <ul class="nav nav-underline data-search-tab" id="pills-tab" role="tablist">
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link active" id="pills-movie-tab"
                                                    data-bs-toggle="pill" data-bs-target="#pills-movie1" type="button"
                                                    role="tab" aria-controls="pills-movie1" aria-selected="true">
                                                    Movie </button>
                                            </li>
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link " id="pills-video-tab" data-bs-toggle="pill"
                                                    data-bs-target="#pills-video1" type="button" role="tab"
                                                    aria-controls="pills-video1" aria-selected="false" tabindex="-1">
                                                    Video </button>
                                            </li>
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link " id="pills-tvshow-tab" data-bs-toggle="pill"
                                                    data-bs-target="#pills-tvshow1" type="button" role="tab"
                                                    aria-controls="pills-tvshow1" aria-selected="false" tabindex="-1">
                                                    Tvshow </button>
                                            </li>
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link " id="pills-episode-tab" data-bs-toggle="pill"
                                                    data-bs-target="#pills-episode1" type="button" role="tab"
                                                    aria-controls="pills-episode1" aria-selected="false" tabindex="-1">
                                                    Episode </button>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <!-- Playlist Tab Content -->
                            <div class="tab-content" id="pills-tabContent-watch">
                                <div class="tab-pane fade active show" id="pills-movie1" role="tabpanel" tabindex="0"
                                    aria-labelledby="pills-movie-tab">
                                    <div
                                        class="row gy-4 row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-3 row-cols-xl-4 row-cols-xxl-5 data-listing">
                                        <div class="col">
                                            <div class="common_card">
                                                <div class="image-box w-100">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//" class="d-block">
                                                        <img decoding="async" src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/gameofhero.webp" alt="Game of Heros" class="img-fluid">
                                                    </a>
                                                </div>
                                                <div class="css_prefix-detail-part">
                                                    <h6 class="text-capitalize line-count-1 mb-0">
                                                        <a href="https://templates.iqonic.design/streamit-dist/frontend/html//" class="color-inherit">
                                                            Game of Heros </a>
                                                    </h6>
                                                    <button class="btn in-watchlist btn-secondary watch-list-btn" data-post-id="32"
                                                        data-post-type="movie" data-action="remove" data-bs-toggle="tooltip" data-bs-title="Remove from watchlist"
                                                        data-bs-placement="top" tabindex="0">
                                                        <i class="icon-check-2"></i>
                                                    </button>
                                                </div>
                                            </div>                                        </div>
                                        <div class="col">
                                            <div class="common_card">
                                                <div class="image-box w-100">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//" class="d-block">
                                                        <img decoding="async" src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/rabbit.webp" alt="Game of Heros" class="img-fluid">
                                                    </a>
                                                </div>
                                                <div class="css_prefix-detail-part">
                                                    <h6 class="text-capitalize line-count-1 mb-0">
                                                        <a href="https://templates.iqonic.design/streamit-dist/frontend/html//" class="color-inherit">
                                                            Rabbit </a>
                                                    </h6>
                                                    <button class="btn in-watchlist btn-secondary watch-list-btn" data-post-id="32"
                                                        data-post-type="movie" data-action="remove" data-bs-toggle="tooltip" data-bs-title="Remove from watchlist"
                                                        data-bs-placement="top" tabindex="0">
                                                        <i class="icon-check-2"></i>
                                                    </button>
                                                </div>
                                            </div>                                        </div>
                                        <div class="col">
                                            <div class="common_card">
                                                <div class="image-box w-100">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//" class="d-block">
                                                        <img decoding="async" src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/migration.webp" alt="Game of Heros" class="img-fluid">
                                                    </a>
                                                </div>
                                                <div class="css_prefix-detail-part">
                                                    <h6 class="text-capitalize line-count-1 mb-0">
                                                        <a href="https://templates.iqonic.design/streamit-dist/frontend/html//" class="color-inherit">
                                                            Migration </a>
                                                    </h6>
                                                    <button class="btn in-watchlist btn-secondary watch-list-btn" data-post-id="32"
                                                        data-post-type="movie" data-action="remove" data-bs-toggle="tooltip" data-bs-title="Remove from watchlist"
                                                        data-bs-placement="top" tabindex="0">
                                                        <i class="icon-check-2"></i>
                                                    </button>
                                                </div>
                                            </div>                                        </div>
                                        <div class="col">
                                            <div class="common_card">
                                                <div class="image-box w-100">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//" class="d-block">
                                                        <img decoding="async" src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/krishna.webp" alt="Game of Heros" class="img-fluid">
                                                    </a>
                                                </div>
                                                <div class="css_prefix-detail-part">
                                                    <h6 class="text-capitalize line-count-1 mb-0">
                                                        <a href="https://templates.iqonic.design/streamit-dist/frontend/html//" class="color-inherit">
                                                            Krishna </a>
                                                    </h6>
                                                    <button class="btn in-watchlist btn-secondary watch-list-btn" data-post-id="32"
                                                        data-post-type="movie" data-action="remove" data-bs-toggle="tooltip" data-bs-title="Remove from watchlist"
                                                        data-bs-placement="top" tabindex="0">
                                                        <i class="icon-check-2"></i>
                                                    </button>
                                                </div>
                                            </div>                                        </div>
                                        <div class="col">
                                            <div class="common_card">
                                                <div class="image-box w-100">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//" class="d-block">
                                                        <img decoding="async" src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/jumanjj.webp" alt="Game of Heros" class="img-fluid">
                                                    </a>
                                                </div>
                                                <div class="css_prefix-detail-part">
                                                    <h6 class="text-capitalize line-count-1 mb-0">
                                                        <a href="https://templates.iqonic.design/streamit-dist/frontend/html//" class="color-inherit">
                                                            Jumanji </a>
                                                    </h6>
                                                    <button class="btn in-watchlist btn-secondary watch-list-btn" data-post-id="32"
                                                        data-post-type="movie" data-action="remove" data-bs-toggle="tooltip" data-bs-title="Remove from watchlist"
                                                        data-bs-placement="top" tabindex="0">
                                                        <i class="icon-check-2"></i>
                                                    </button>
                                                </div>
                                            </div>                                        </div>
                                        <div class="col">
                                            <div class="common_card">
                                                <div class="image-box w-100">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//" class="d-block">
                                                        <img decoding="async" src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/yoshi.webp" alt="Game of Heros" class="img-fluid">
                                                    </a>
                                                </div>
                                                <div class="css_prefix-detail-part">
                                                    <h6 class="text-capitalize line-count-1 mb-0">
                                                        <a href="https://templates.iqonic.design/streamit-dist/frontend/html//" class="color-inherit">
                                                            YoShi </a>
                                                    </h6>
                                                    <button class="btn in-watchlist btn-secondary watch-list-btn" data-post-id="32"
                                                        data-post-type="movie" data-action="remove" data-bs-toggle="tooltip" data-bs-title="Remove from watchlist"
                                                        data-bs-placement="top" tabindex="0">
                                                        <i class="icon-check-2"></i>
                                                    </button>
                                                </div>
                                            </div>                                        </div>
                                        <div class="col">
                                            <div class="common_card">
                                                <div class="image-box w-100">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//" class="d-block">
                                                        <img decoding="async" src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/kung-fu-panda.webp" alt="Game of Heros" class="img-fluid">
                                                    </a>
                                                </div>
                                                <div class="css_prefix-detail-part">
                                                    <h6 class="text-capitalize line-count-1 mb-0">
                                                        <a href="https://templates.iqonic.design/streamit-dist/frontend/html//" class="color-inherit">
                                                            Kung Fu Panda </a>
                                                    </h6>
                                                    <button class="btn in-watchlist btn-secondary watch-list-btn" data-post-id="32"
                                                        data-post-type="movie" data-action="remove" data-bs-toggle="tooltip" data-bs-title="Remove from watchlist"
                                                        data-bs-placement="top" tabindex="0">
                                                        <i class="icon-check-2"></i>
                                                    </button>
                                                </div>
                                            </div>                                        </div>
                                        <div class="col">
                                            <div class="common_card">
                                                <div class="image-box w-100">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//" class="d-block">
                                                        <img decoding="async" src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/the-hunter.webp" alt="Game of Heros" class="img-fluid">
                                                    </a>
                                                </div>
                                                <div class="css_prefix-detail-part">
                                                    <h6 class="text-capitalize line-count-1 mb-0">
                                                        <a href="https://templates.iqonic.design/streamit-dist/frontend/html//" class="color-inherit">
                                                            The Hunter </a>
                                                    </h6>
                                                    <button class="btn in-watchlist btn-secondary watch-list-btn" data-post-id="32"
                                                        data-post-type="movie" data-action="remove" data-bs-toggle="tooltip" data-bs-title="Remove from watchlist"
                                                        data-bs-placement="top" tabindex="0">
                                                        <i class="icon-check-2"></i>
                                                    </button>
                                                </div>
                                            </div>                                        </div>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="pills-video1" role="tabpanel" tabindex="0"
                                    aria-labelledby="pills-video-tab">
                                    <div
                                        class="row gy-4 row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-3 row-cols-xl-4 row-cols-xxl-5 data-listing">
                                        <div class="col">
                                            <div class="common_card">
                                                <div class="image-box w-100">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//" class="d-block">
                                                        <img decoding="async" src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/toddler.webp" alt="Game of Heros" class="img-fluid">
                                                    </a>
                                                </div>
                                                <div class="css_prefix-detail-part">
                                                    <h6 class="text-capitalize line-count-1 mb-0">
                                                        <a href="https://templates.iqonic.design/streamit-dist/frontend/html//" class="color-inherit">
                                                            Toddler </a>
                                                    </h6>
                                                    <button class="btn in-watchlist btn-secondary watch-list-btn" data-post-id="32"
                                                        data-post-type="movie" data-action="remove" data-bs-toggle="tooltip" data-bs-title="Remove from watchlist"
                                                        data-bs-placement="top" tabindex="0">
                                                        <i class="icon-check-2"></i>
                                                    </button>
                                                </div>
                                            </div>                                        </div>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="pills-tvshow1" role="tabpanel" tabindex="0">
                                    <div
                                        class="row gy-4 row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-3 row-cols-xl-4 row-cols-xxl-5 data-listing">
                                        <div class="col">
                                            <div class="common_card">
                                                <div class="image-box w-100">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//" class="d-block">
                                                        <img decoding="async" src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/the-first-of-us.webp" alt="Game of Heros" class="img-fluid">
                                                    </a>
                                                </div>
                                                <div class="css_prefix-detail-part">
                                                    <h6 class="text-capitalize line-count-1 mb-0">
                                                        <a href="https://templates.iqonic.design/streamit-dist/frontend/html//" class="color-inherit">
                                                            Awakening: The First Ones </a>
                                                    </h6>
                                                    <button class="btn in-watchlist btn-secondary watch-list-btn" data-post-id="32"
                                                        data-post-type="movie" data-action="remove" data-bs-toggle="tooltip" data-bs-title="Remove from watchlist"
                                                        data-bs-placement="top" tabindex="0">
                                                        <i class="icon-check-2"></i>
                                                    </button>
                                                </div>
                                            </div>                                        </div>
                                        <div class="col">
                                            <div class="common_card">
                                                <div class="image-box w-100">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//" class="d-block">
                                                        <img decoding="async" src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/migration.webp" alt="Game of Heros" class="img-fluid">
                                                    </a>
                                                </div>
                                                <div class="css_prefix-detail-part">
                                                    <h6 class="text-capitalize line-count-1 mb-0">
                                                        <a href="https://templates.iqonic.design/streamit-dist/frontend/html//" class="color-inherit">
                                                            Beyond Borders </a>
                                                    </h6>
                                                    <button class="btn in-watchlist btn-secondary watch-list-btn" data-post-id="32"
                                                        data-post-type="movie" data-action="remove" data-bs-toggle="tooltip" data-bs-title="Remove from watchlist"
                                                        data-bs-placement="top" tabindex="0">
                                                        <i class="icon-check-2"></i>
                                                    </button>
                                                </div>
                                            </div>                                        </div>
                                        <div class="col">
                                            <div class="common_card">
                                                <div class="image-box w-100">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//" class="d-block">
                                                        <img decoding="async" src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/spiderman.webp" alt="Game of Heros" class="img-fluid">
                                                    </a>
                                                </div>
                                                <div class="css_prefix-detail-part">
                                                    <h6 class="text-capitalize line-count-1 mb-0">
                                                        <a href="https://templates.iqonic.design/streamit-dist/frontend/html//" class="color-inherit">
                                                            Spider Sentinel </a>
                                                    </h6>
                                                    <button class="btn in-watchlist btn-secondary watch-list-btn" data-post-id="32"
                                                        data-post-type="movie" data-action="remove" data-bs-toggle="tooltip" data-bs-title="Remove from watchlist"
                                                        data-bs-placement="top" tabindex="0">
                                                        <i class="icon-check-2"></i>
                                                    </button>
                                                </div>
                                            </div>                                        </div>
                                        <div class="col">
                                            <div class="common_card">
                                                <div class="image-box w-100">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//" class="d-block">
                                                        <img decoding="async" src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/minions.webp" alt="Game of Heros" class="img-fluid">
                                                    </a>
                                                </div>
                                                <div class="css_prefix-detail-part">
                                                    <h6 class="text-capitalize line-count-1 mb-0">
                                                        <a href="https://templates.iqonic.design/streamit-dist/frontend/html//" class="color-inherit">
                                                            Minions </a>
                                                    </h6>
                                                    <button class="btn in-watchlist btn-secondary watch-list-btn" data-post-id="32"
                                                        data-post-type="movie" data-action="remove" data-bs-toggle="tooltip" data-bs-title="Remove from watchlist"
                                                        data-bs-placement="top" tabindex="0">
                                                        <i class="icon-check-2"></i>
                                                    </button>
                                                </div>
                                            </div>                                        </div>
                                        <div class="col">
                                            <div class="common_card">
                                                <div class="image-box w-100">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//" class="d-block">
                                                        <img decoding="async" src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/pirates-ofdayones-orignal.webp" alt="Game of Heros" class="img-fluid">
                                                    </a>
                                                </div>
                                                <div class="css_prefix-detail-part">
                                                    <h6 class="text-capitalize line-count-1 mb-0">
                                                        <a href="https://templates.iqonic.design/streamit-dist/frontend/html//" class="color-inherit">
                                                            Pirates of Day One </a>
                                                    </h6>
                                                    <button class="btn in-watchlist btn-secondary watch-list-btn" data-post-id="32"
                                                        data-post-type="movie" data-action="remove" data-bs-toggle="tooltip" data-bs-title="Remove from watchlist"
                                                        data-bs-placement="top" tabindex="0">
                                                        <i class="icon-check-2"></i>
                                                    </button>
                                                </div>
                                            </div>                                        </div>
                                        <div class="col">
                                            <div class="common_card">
                                                <div class="image-box w-100">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//" class="d-block">
                                                        <img decoding="async" src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/assassins-creed.webp" alt="Game of Heros" class="img-fluid">
                                                    </a>
                                                </div>
                                                <div class="css_prefix-detail-part">
                                                    <h6 class="text-capitalize line-count-1 mb-0">
                                                        <a href="https://templates.iqonic.design/streamit-dist/frontend/html//" class="color-inherit">
                                                            Assassin creed </a>
                                                    </h6>
                                                    <button class="btn in-watchlist btn-secondary watch-list-btn" data-post-id="32"
                                                        data-post-type="movie" data-action="remove" data-bs-toggle="tooltip" data-bs-title="Remove from watchlist"
                                                        data-bs-placement="top" tabindex="0">
                                                        <i class="icon-check-2"></i>
                                                    </button>
                                                </div>
                                            </div>                                        </div>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="pills-episode1" role="tabpanel" tabindex="0"
                                    aria-labelledby="pills-episode-tab">
                                    <div
                                        class="row gy-4 row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-3 row-cols-xl-4 row-cols-xxl-5 ">
                                        <p class="text-center w-100">No watchlist available.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="notification-tab" role="tabpanel">
                        <div
                            class="d-flex align-items-center gap-3 justify-content-between flex-sm-row flex-column-reverse border-bottom mb-5">
                            <div id="item-nav1">
                                <div class="item-list-tabs no-ajax css_prefix-tab-lists" id="object-nav1">
                                    <ul class="nav nav-underline data-search-tab" id="notification-tab1" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="unread-tab" data-bs-toggle="tab"
                                                data-bs-target="#unread" type="button" role="tab" aria-controls="unread"
                                                aria-selected="true">
                                                Unread </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="read-tab" data-bs-toggle="tab"
                                                data-bs-target="#read" type="button" role="tab" aria-controls="read"
                                                aria-selected="false" tabindex="-1">
                                                Read </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="tab-content" id="notification-tabContent">
                            <!-- Unread Notifications Tab -->
                            <div class="tab-pane fade show active" id="unread" role="tabpanel" tabindex="0"
                                aria-labelledby="unread-tab">
                                <ul class="notification-list m-0 p-0">
                                    <li class="notification-item">
                                        <div class="d-flex align-items-center justify-content-between gap-3">
                                            <div class="d-flex align-items-md-center flex-md-row flex-column gap-3">
                                                <div class="notification-image flex-shrink-0">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//movie-detail.html" class="link-overlay" data-user_id="12" data-notification_id="1">
                                                        <img decoding="async" src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/krishna.webp" alt="image"
                                                            class="img-fluid object-cover result-image">
                                                    </a>
                                                </div>
                                                <div class="notification-message">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//movie-detail.html" class="link-overlay message" data-user_id="12"
                                                        data-notification_id="1">
                                                        New movie is released: Krishna </a>
                                                    <span class="d-block">
                                                        4 weeks ago </span>
                                                </div>
                                            </div>
                                            <div class="notification-actions flex-shrink-0">
                                                <div class="d-flex justify-content-center align-items-center gap-3">
                                                    <button type="button" class="btn btn-secondary btn-circle border notification-action-btn"
                                                        data-user_id="12" data-notification_id="1" data-bs-toggle="tooltip" data-bs-placement="top"
                                                        data-bs-title="Mark as read">
                                                        <i class="icon-eye-2"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </li>                                    <li class="notification-item">
                                        <div class="d-flex align-items-center justify-content-between gap-3">
                                            <div class="d-flex align-items-md-center flex-md-row flex-column gap-3">
                                                <div class="notification-image flex-shrink-0">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//movie-detail.html" class="link-overlay" data-user_id="12" data-notification_id="1">
                                                        <img decoding="async" src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/the-crew.webp" alt="image"
                                                            class="img-fluid object-cover result-image">
                                                    </a>
                                                </div>
                                                <div class="notification-message">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//movie-detail.html" class="link-overlay message" data-user_id="12"
                                                        data-notification_id="1">
                                                        New episode is released: All Hands on Deck </a>
                                                    <span class="d-block">
                                                        2 weeks ago </span>
                                                </div>
                                            </div>
                                            <div class="notification-actions flex-shrink-0">
                                                <div class="d-flex justify-content-center align-items-center gap-3">
                                                    <button type="button" class="btn btn-secondary btn-circle border notification-action-btn"
                                                        data-user_id="12" data-notification_id="1" data-bs-toggle="tooltip" data-bs-placement="top"
                                                        data-bs-title="Mark as read">
                                                        <i class="icon-eye-2"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </li>                                    <li class="notification-item">
                                        <div class="d-flex align-items-center justify-content-between gap-3">
                                            <div class="d-flex align-items-md-center flex-md-row flex-column gap-3">
                                                <div class="notification-image flex-shrink-0">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//movie-detail.html" class="link-overlay" data-user_id="12" data-notification_id="1">
                                                        <img decoding="async" src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/the-first-of-us.webp" alt="image"
                                                            class="img-fluid object-cover result-image">
                                                    </a>
                                                </div>
                                                <div class="notification-message">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//movie-detail.html" class="link-overlay message" data-user_id="12"
                                                        data-notification_id="1">
                                                        New movie is released: The First Of Us </a>
                                                    <span class="d-block">
                                                        4 hours ago </span>
                                                </div>
                                            </div>
                                            <div class="notification-actions flex-shrink-0">
                                                <div class="d-flex justify-content-center align-items-center gap-3">
                                                    <button type="button" class="btn btn-secondary btn-circle border notification-action-btn"
                                                        data-user_id="12" data-notification_id="1" data-bs-toggle="tooltip" data-bs-placement="top"
                                                        data-bs-title="Mark as read">
                                                        <i class="icon-eye-2"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </li>                                    <li class="notification-item">
                                        <div class="d-flex align-items-center justify-content-between gap-3">
                                            <div class="d-flex align-items-md-center flex-md-row flex-column gap-3">
                                                <div class="notification-image flex-shrink-0">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//movie-detail.html" class="link-overlay" data-user_id="12" data-notification_id="1">
                                                        <img decoding="async" src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/episode/s1e1-trust.webp" alt="image"
                                                            class="img-fluid object-cover result-image">
                                                    </a>
                                                </div>
                                                <div class="notification-message">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//movie-detail.html" class="link-overlay message" data-user_id="12"
                                                        data-notification_id="1">
                                                        New episode is released: Trust </a>
                                                    <span class="d-block">
                                                        3 hours ago </span>
                                                </div>
                                            </div>
                                            <div class="notification-actions flex-shrink-0">
                                                <div class="d-flex justify-content-center align-items-center gap-3">
                                                    <button type="button" class="btn btn-secondary btn-circle border notification-action-btn"
                                                        data-user_id="12" data-notification_id="1" data-bs-toggle="tooltip" data-bs-placement="top"
                                                        data-bs-title="Mark as read">
                                                        <i class="icon-eye-2"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </li>                                    <li class="notification-item">
                                        <div class="d-flex align-items-center justify-content-between gap-3">
                                            <div class="d-flex align-items-md-center flex-md-row flex-column gap-3">
                                                <div class="notification-image flex-shrink-0">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//movie-detail.html" class="link-overlay" data-user_id="12" data-notification_id="1">
                                                        <img decoding="async" src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/episode/s1e2-the-new-guy.webp" alt="image"
                                                            class="img-fluid object-cover result-image">
                                                    </a>
                                                </div>
                                                <div class="notification-message">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//movie-detail.html" class="link-overlay message" data-user_id="12"
                                                        data-notification_id="1">
                                                        New episode is released: The New Guy </a>
                                                    <span class="d-block">
                                                        3 hours ago </span>
                                                </div>
                                            </div>
                                            <div class="notification-actions flex-shrink-0">
                                                <div class="d-flex justify-content-center align-items-center gap-3">
                                                    <button type="button" class="btn btn-secondary btn-circle border notification-action-btn"
                                                        data-user_id="12" data-notification_id="1" data-bs-toggle="tooltip" data-bs-placement="top"
                                                        data-bs-title="Mark as read">
                                                        <i class="icon-eye-2"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </li>                                    <li class="notification-item">
                                        <div class="d-flex align-items-center justify-content-between gap-3">
                                            <div class="d-flex align-items-md-center flex-md-row flex-column gap-3">
                                                <div class="notification-image flex-shrink-0">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//movie-detail.html" class="link-overlay" data-user_id="12" data-notification_id="1">
                                                        <img decoding="async" src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/episode/s1e4-island-of-secrets.webp" alt="image"
                                                            class="img-fluid object-cover result-image">
                                                    </a>
                                                </div>
                                                <div class="notification-message">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//movie-detail.html" class="link-overlay message" data-user_id="12"
                                                        data-notification_id="1">
                                                        New episode is released: Island of Secrets </a>
                                                    <span class="d-block">
                                                        3 hours ago </span>
                                                </div>
                                            </div>
                                            <div class="notification-actions flex-shrink-0">
                                                <div class="d-flex justify-content-center align-items-center gap-3">
                                                    <button type="button" class="btn btn-secondary btn-circle border notification-action-btn"
                                                        data-user_id="12" data-notification_id="1" data-bs-toggle="tooltip" data-bs-placement="top"
                                                        data-bs-title="Mark as read">
                                                        <i class="icon-eye-2"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </li>                                    <li class="notification-item">
                                        <div class="d-flex align-items-center justify-content-between gap-3">
                                            <div class="d-flex align-items-md-center flex-md-row flex-column gap-3">
                                                <div class="notification-image flex-shrink-0">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//movie-detail.html" class="link-overlay" data-user_id="12" data-notification_id="1">
                                                        <img decoding="async" src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/episode/s2e1-stuck.webp" alt="image"
                                                            class="img-fluid object-cover result-image">
                                                    </a>
                                                </div>
                                                <div class="notification-message">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//movie-detail.html" class="link-overlay message" data-user_id="12"
                                                        data-notification_id="1">
                                                        New episode is released: Stuck </a>
                                                    <span class="d-block">
                                                        3 hours ago </span>
                                                </div>
                                            </div>
                                            <div class="notification-actions flex-shrink-0">
                                                <div class="d-flex justify-content-center align-items-center gap-3">
                                                    <button type="button" class="btn btn-secondary btn-circle border notification-action-btn"
                                                        data-user_id="12" data-notification_id="1" data-bs-toggle="tooltip" data-bs-placement="top"
                                                        data-bs-title="Mark as read">
                                                        <i class="icon-eye-2"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </li>                                    <li class="notification-item">
                                        <div class="d-flex align-items-center justify-content-between gap-3">
                                            <div class="d-flex align-items-md-center flex-md-row flex-column gap-3">
                                                <div class="notification-image flex-shrink-0">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//movie-detail.html" class="link-overlay" data-user_id="12" data-notification_id="1">
                                                        <img decoding="async" src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/episode/s2e2-forged-alliances.webp" alt="image"
                                                            class="img-fluid object-cover result-image">
                                                    </a>
                                                </div>
                                                <div class="notification-message">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//movie-detail.html" class="link-overlay message" data-user_id="12"
                                                        data-notification_id="1">
                                                        New episode is released: Forged Alliances </a>
                                                    <span class="d-block">
                                                        2 hours ago </span>
                                                </div>
                                            </div>
                                            <div class="notification-actions flex-shrink-0">
                                                <div class="d-flex justify-content-center align-items-center gap-3">
                                                    <button type="button" class="btn btn-secondary btn-circle border notification-action-btn"
                                                        data-user_id="12" data-notification_id="1" data-bs-toggle="tooltip" data-bs-placement="top"
                                                        data-bs-title="Mark as read">
                                                        <i class="icon-eye-2"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </li>                                    <li class="notification-item">
                                        <div class="d-flex align-items-center justify-content-between gap-3">
                                            <div class="d-flex align-items-md-center flex-md-row flex-column gap-3">
                                                <div class="notification-image flex-shrink-0">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//movie-detail.html" class="link-overlay" data-user_id="12" data-notification_id="1">
                                                        <img decoding="async" src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/episode/s2e3-the-queen.webp" alt="image"
                                                            class="img-fluid object-cover result-image">
                                                    </a>
                                                </div>
                                                <div class="notification-message">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//movie-detail.html" class="link-overlay message" data-user_id="12"
                                                        data-notification_id="1">
                                                        New episode is released: The Queen </a>
                                                    <span class="d-block">
                                                        2 hours ago </span>
                                                </div>
                                            </div>
                                            <div class="notification-actions flex-shrink-0">
                                                <div class="d-flex justify-content-center align-items-center gap-3">
                                                    <button type="button" class="btn btn-secondary btn-circle border notification-action-btn"
                                                        data-user_id="12" data-notification_id="1" data-bs-toggle="tooltip" data-bs-placement="top"
                                                        data-bs-title="Mark as read">
                                                        <i class="icon-eye-2"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </li>                                    <li class="notification-item">
                                        <div class="d-flex align-items-center justify-content-between gap-3">
                                            <div class="d-flex align-items-md-center flex-md-row flex-column gap-3">
                                                <div class="notification-image flex-shrink-0">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//movie-detail.html" class="link-overlay" data-user_id="12" data-notification_id="1">
                                                        <img decoding="async" src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/episode/s2e4-restore-hope.webp" alt="image"
                                                            class="img-fluid object-cover result-image">
                                                    </a>
                                                </div>
                                                <div class="notification-message">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//movie-detail.html" class="link-overlay message" data-user_id="12"
                                                        data-notification_id="1">
                                                        New episode is released: Restore Hope </a>
                                                    <span class="d-block">
                                                        2 hours ago </span>
                                                </div>
                                            </div>
                                            <div class="notification-actions flex-shrink-0">
                                                <div class="d-flex justify-content-center align-items-center gap-3">
                                                    <button type="button" class="btn btn-secondary btn-circle border notification-action-btn"
                                                        data-user_id="12" data-notification_id="1" data-bs-toggle="tooltip" data-bs-placement="top"
                                                        data-bs-title="Mark as read">
                                                        <i class="icon-eye-2"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </li>                                </ul>
                            </div>

                            <!-- Read Notifications Tab -->
                            <div class="tab-pane fade" id="read" role="tabpanel" tabindex="0"
                                aria-labelledby="read-tab">
                                <form>
                                    <ul class="notification-list p-0 m-0">
                                        <li class="notification-item">
                                            <div class="d-flex align-items-center justify-content-between gap-3">
                                                <div class="d-flex align-items-md-center flex-md-row flex-column gap-3">
                                                    <div class="notification-image flex-shrink-0">
                                                        <a href="https://templates.iqonic.design/streamit-dist/frontend/html//movie-detail.html" class="link-overlay" data-user_id="12" data-notification_id="1">
                                                            <img decoding="async" src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/images/media/gameofhero.webp" alt="image"
                                                                class="img-fluid object-cover result-image">
                                                        </a>
                                                    </div>
                                                    <div class="notification-message">
                                                        <a href="https://templates.iqonic.design/streamit-dist/frontend/html//movie-detail.html" class="link-overlay message" data-user_id="12"
                                                            data-notification_id="1">
                                                            New movie is released: Champions’ War </a>
                                                        <span class="d-block">
                                                            2 weeks ago </span>
                                                    </div>
                                                </div>
                                                <div class="notification-actions flex-shrink-0">
                                                    <div class="d-flex justify-content-center align-items-center gap-3">
                                                        <button type="button" class="btn btn-secondary btn-circle border notification-action-btn"
                                                            data-user_id="12" data-notification_id="1" data-bs-toggle="tooltip" data-bs-placement="top"
                                                            data-bs-title="Mark as read">
                                                            <i class="icon-eye-2"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </li>                                    </ul>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="membership-tab" role="tabpanel">
                        <div class="pmpro">
                            <p class="error-message">You do not have an active membership level.</p>
                            <section class="st-pmp-section mt-5">
                                <h4 class="pmpro_section_title">Order History</h4>
                                <div class="pmpro_card">
                                    <table class="pmpro_table pmpro_table_orders">
                                        <thead>
                                            <tr>
                                                <th class="st-pmp-table-order">Date</th>
                                                <th class="st-pmp-table-order">Level</th>
                                                <th class="st-pmp-table-order">Total</th>
                                                <th class="st-pmp-table-order">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr id="pmpro_table_order-51311C6265">
                                                <th class="pmpro_table_order-date" data-title="Date">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//membership-invoice.html">
                                                        February 18, 2025 </a>
                                                </th>
                                                <td class="pmpro_table_order-level" data-title="Level">
                                                    Basic Plan </td>
                                                <td class="pmpro_table_order-amount" data-title="Amount">
                                                    $10.00 </td>
                                                <td class="pmpro_table_order-status" data-title="Status">
                                                    <span class="pmpro_tag pmpro_tag-success">
                                                        Paid </span>
                                                </td>
                                            </tr>
                                            <tr id="pmpro_table_order-A467E41A35">
                                                <th class="pmpro_table_order-date" data-title="Date">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//membership-invoice.html">
                                                        February 10, 2025 </a>
                                                </th>
                                                <td class="pmpro_table_order-level" data-title="Level">
                                                    Premium Plan </td>
                                                <td class="pmpro_table_order-amount" data-title="Amount">
                                                    $180.00 </td>
                                                <td class="pmpro_table_order-status" data-title="Status">
                                                    <span class="pmpro_tag pmpro_tag-success">
                                                        Paid </span>
                                                </td>
                                            </tr>
                                            <tr id="pmpro_table_order-3B9A37110A">
                                                <th class="pmpro_table_order-date" data-title="Date">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//membership-invoice.html">
                                                        February 6, 2025 </a>
                                                </th>
                                                <td class="pmpro_table_order-level" data-title="Level">
                                                    Basic Plan </td>
                                                <td class="pmpro_table_order-amount" data-title="Amount">
                                                    $10.00 </td>
                                                <td class="pmpro_table_order-status" data-title="Status">
                                                    <span class="pmpro_tag pmpro_tag-success">
                                                        Paid </span>
                                                </td>
                                            </tr>
                                            <tr id="pmpro_table_order-68B3C8559C">
                                                <th class="pmpro_table_order-date" data-title="Date">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//membership-invoice.html">
                                                        February 6, 2025 </a>
                                                </th>
                                                <td class="pmpro_table_order-level" data-title="Level">
                                                    Basic Plan </td>
                                                <td class="pmpro_table_order-amount" data-title="Amount">
                                                    $10.00 </td>
                                                <td class="pmpro_table_order-status" data-title="Status">
                                                    <span class="pmpro_tag pmpro_tag-success">
                                                        Paid </span>
                                                </td>
                                            </tr>
                                            <tr id="pmpro_table_order-FB6969474C">
                                                <th class="pmpro_table_order-date" data-title="Date">
                                                    <a href="https://templates.iqonic.design/streamit-dist/frontend/html//membership-invoice.html">
                                                        January 29, 2025 </a>
                                                </th>
                                                <td class="pmpro_table_order-level" data-title="Level">
                                                    Standard Plan </td>
                                                <td class="pmpro_table_order-amount" data-title="Amount">
                                                    $79.00 </td>
                                                <td class="pmpro_table_order-status" data-title="Status">
                                                    <span class="pmpro_tag pmpro_tag-success">
                                                        Paid </span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div> <!-- end st-pmp-card-content -->
                            </section>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- edit profile modal -->
    </div>
</section>


<div class="modal fade view-more-data-modal edit-profile-modal" id="edit-profile-modal" tabindex="-1" aria-modal="true"
    role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header pb-0">
                <h5 class="modal-title" id="exampleModalLabelEdit1">Edit Profile</h5>
                <button type="button" class="btn-close me-0" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="st-result-msg">
                    <div class="st-status-message">

                    </div>
                </div>
                <form>
                    <input type="hidden" name="action" value="edit_profile">
                    <input type="hidden" name="user_id" value="12">
                    <input type="hidden" id="is_remove_avtar" name="is_remove_avtar" value="">

                    <input type="hidden" id="edit_profile_nonce" name="edit_profile_nonce" value="ec49b112d5"><input
                        type="hidden" name="_wp_http_referer" value="/product/wp/streamit/media/marvin/">
                    <div class="row">
                        <!-- Avatar Upload Section -->
                        <div class="col-12 text-center mb-5">
                            <div class="position-relative d-inline-block avtar_image">
                                <img src="assets/images/user/user6.jpg" alt="User Avatar"
                                    class="user-image user-profile-image" id="profile-picture-preview">

                                <div class="avtar_action">
                                    <a class="avtar_action-btn" id="edit-profile-picture-btn">
                                        <i class="icon-edit-icon"></i>
                                    </a>
                                    <a class="avtar_action-btn" id="remove-profile-picture-btn">
                                        <i class="icon-trash-icon"></i>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- First Name Field -->
                        <div class="col-sm-6">
                            <div class="form-group mb-3">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" name="first_name" id="first_name" value="Marvin"
                                    required="">
                            </div>
                        </div>

                        <!-- Last Name Field -->
                        <div class="col-sm-6">
                            <div class="form-group mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="last_name" id="last_name" value="McKinney"
                                    required="">
                            </div>
                        </div>

                        <!-- Email Field -->
                        <div class="col-12">
                            <div class="form-group mb-0">
                                <label for="user_email" class="form-label">Email</label>
                                <input type="email" class="form-control" name="user_email" id="user_email"
                                    value="marvin@demo.com" required="">
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="iq-button mt-4 text-end">
                        <button class="btn btn-primary text-capitalize position-relative rounded-3">
                            <span class="button-text">Save</span>
                        </button>
                    </div>
                </form>
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
</div>  </main>

 <?php include 'includes/footer.php'; ?>