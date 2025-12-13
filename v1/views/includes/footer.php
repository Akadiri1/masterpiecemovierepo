<?php include APP_PATH . '/views/zen-ai.php'; ?>
<div class="streamit-mobile-footer-menu" aria-label="Mobile Footer Navigation">
    <ul class="footer-menu list-inline d-flex align-items-center justify-content-between m-0">
        <li class="footer-menu-item">
            <a href="/view-all?type=movie" class="menu-link font-size-12">
                <i class="ph ph-film-reel d-block  text-center "></i>
                Movies </a>
        </li>
        <li class="footer-menu-item">
            <a href="/view-all?type=fresh" class="menu-link font-size-12">
                <i class="ph ph-monitor-play d-block  text-center "></i>
                Videos </a>
        </li>
        <li class="footer-menu-item">
            <a href="/" class="menu-link font-size-12">
                <i class="fa fa-home d-block  text-center "></i>
                Home </a>
        </li>
        <li class="footer-menu-item">
            <a href="/view-all?type=tv" class="menu-link font-size-12">
                <i class="ph ph-television d-block  text-center "></i>
                TV Shows </a>
        </li>
        <li class="footer-menu-item">
            <a href="/profile" class="menu-link font-size-12">
                <i class="ph ph-user d-block  text-center "></i>
                Profile </a>
        </li>
    </ul>
</div> 
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
  <script src="https://templates.iqonic.design/streamit-dist/frontend/html//assets/vendor/swiperSlider/swiper.min.js"></script>
  
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
</body>
</html>