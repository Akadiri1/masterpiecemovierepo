/* ==========================================================================
   Mobile video player.

   Wraps a direct-file <video> with touch controls laid out like a phone
   video app: a title bar with a row of quick actions, large transport
   controls along the bottom, and gestures on the picture itself (vertical
   drag for brightness/volume, horizontal drag to seek, double-tap to skip,
   long-press to speed up).

   A narrow player (portrait, inline on the page) gets a compact layout with
   play and skip in the centre. Once the player is wide enough (landscape or
   fullscreen) the full layout takes over.

   Only works on a real <video>. Cross-origin <iframe> embeds are sealed by
   the browser, so none of this can reach inside them.

   Platform limits are handled honestly rather than faked:
     - No web API controls screen brightness. "Brightness" composites a black
       veil, so it darkens but cannot go brighter than the device.
     - iOS Safari makes video.volume read-only. The player detects that and
       points to the hardware keys instead of showing a slider that lies.
     - Browsers pause hidden videos, so "background play" is offered as a
       picture-in-picture pop-up, and only where the browser supports it.
     - The screen can only be rotated programmatically in fullscreen on
       Android. Elsewhere the rotate button asks the user to turn the phone.
   ========================================================================== */

(function (global) {
  'use strict';

  /* Font Awesome 6 is already loaded by watch.php, which keeps the player's
     icons consistent with the rest of the site. Raw Unicode symbols were
     swapped for colour emoji on Android. */
  var ICON = {
    play:    '<i class="fa-solid fa-play"></i>',
    pause:   '<i class="fa-solid fa-pause"></i>',
    back:    '<i class="fa-solid fa-rotate-left"></i>',
    fwd:     '<i class="fa-solid fa-rotate-right"></i>',
    prev:    '<i class="fa-solid fa-backward-step"></i>',
    next:    '<i class="fa-solid fa-forward-step"></i>',
    lock:    '<i class="fa-solid fa-lock"></i>',
    unlock:  '<i class="fa-solid fa-lock-open"></i>',
    fit:     '<i class="fa-solid fa-crop-simple"></i>',
    fs:      '<i class="fa-solid fa-expand"></i>',
    fsExit:  '<i class="fa-solid fa-compress"></i>',
    bright:  '<i class="fa-solid fa-sun"></i>',
    vol:     '<i class="fa-solid fa-volume-high"></i>',
    mute:    '<i class="fa-solid fa-volume-xmark"></i>',
    ff:      '<i class="fa-solid fa-forward"></i>',
    rw:      '<i class="fa-solid fa-backward"></i>',
    speed:   '<i class="fa-solid fa-gauge-high"></i>',
    navBack: '<i class="fa-solid fa-arrow-left"></i>',
    more:    '<i class="fa-solid fa-ellipsis-vertical"></i>',
    night:   '<i class="fa-solid fa-moon"></i>',
    pip:     '<i class="fa-solid fa-clone"></i>',
    timer:   '<i class="fa-solid fa-stopwatch"></i>',
    rotate:  '<i class="fa-solid fa-rotate"></i>',
    cast:    '<i class="fa-solid fa-tv"></i>',
    episodes: '<i class="fa-solid fa-list"></i>'
  };

  var SEEK_TAP     = 10;    // seconds per double-tap or skip button
  var HIDE_DELAY   = 3200;  // ms before controls fade while playing
  var DRAG_START   = 12;    // px before a drag is recognised
  var LONG_PRESS   = 480;   // ms to trigger the speed boost
  var BOOST_RATE   = 2;
  var SEEK_PER_PX  = 0.35;  // seconds of seek per px dragged
  var WIDE_MIN     = 560;   // px wide before the full layout replaces the compact one
  var SPEEDS       = [0.5, 0.75, 1, 1.25, 1.5, 2];
  var SLEEP_STEPS  = [0, 15, 30, 45, 60];   // minutes; 0 = off

  function fmt(t) {
    if (!isFinite(t) || t < 0) t = 0;
    var h = Math.floor(t / 3600),
        m = Math.floor((t % 3600) / 60),
        s = Math.floor(t % 60);
    var mm = h ? String(m).padStart(2, '0') : String(m);
    return (h ? h + ':' : '') + mm + ':' + String(s).padStart(2, '0');
  }

  function clamp(v, lo, hi) { return v < lo ? lo : v > hi ? hi : v; }

  function rateLabel(r) { return (Math.round(r * 100) / 100) + '×'; }

  function fullscreenElement() {
    return document.fullscreenElement || document.webkitFullscreenElement || null;
  }

  /** iOS Safari ignores writes to video.volume. Probe rather than sniff UA. */
  function volumeIsWritable(video) {
    var original = video.volume;
    var probe = original > 0.5 ? 0.3 : 0.7;
    try {
      video.volume = probe;
      var writable = Math.abs(video.volume - probe) < 0.01;
      video.volume = original;
      return writable;
    } catch (e) {
      return false;
    }
  }

  /** 'standard' (Chrome/Android), 'webkit' (iOS Safari), or null. */
  function pipSupport(video) {
    if (document.pictureInPictureEnabled && !video.disablePictureInPicture &&
        typeof video.requestPictureInPicture === 'function') {
      return 'standard';
    }
    if (typeof video.webkitSetPresentationMode === 'function' &&
        typeof video.webkitSupportsPresentationMode === 'function' &&
        video.webkitSupportsPresentationMode('picture-in-picture')) {
      return 'webkit';
    }
    return null;
  }

  function MobilePlayer(video, options) {
    this.video = video;
    this.opts = options || {};
    this.dim = 0;             // 0 = untouched, 0.85 = heavily darkened
    this.locked = false;
    this.fit = 'contain';
    this.rate = 1;            // chosen speed; the long-press boost returns here
    this.orient = 'landscape';
    this.menuOpen = false;
    this.panel = null;        // 'bright' | 'volume' | null
    this.sleepStep = 0;
    this.sleepEndsAt = 0;
    this.hideTimer = null;
    this.hudTimer = null;
    this.lastTap = 0;
    this.lastTapX = 0;
    this.boosted = false;
    this.canSetVolume = volumeIsWritable(video);
    this.pip = pipSupport(video);

    this._build();
    this._bindVideo();
    this._bindGestures();
    this._bindControls();
    this._watchWidth();
    this._syncAll();
    this.showControls();
  }

  /* ------------------------------------------------------------ DOM setup */

  MobilePlayer.prototype._build = function () {
    var v = this.video;
    var parent = v.parentNode;

    // The site's own markup sizes the wrapper; we insert a shell inside it.
    var shell = document.createElement('div');
    shell.className = 'mp-shell mp-active';
    shell.setAttribute('data-fit', 'contain');
    parent.insertBefore(shell, v);
    shell.appendChild(v);

    // The page may start with the video hidden (an iframe server is active).
    // The shell has to inherit that, or it paints a stray black box over the
    // iframe. From here on the shell owns visibility and the video fills it.
    shell.style.display = (v.style.display === 'none') ? 'none' : 'block';
    v.style.display = 'block';

    v.removeAttribute('controls');
    v.setAttribute('playsinline', '');
    v.setAttribute('webkit-playsinline', '');

    var canCast = !!(v.remote && typeof v.remote.prompt === 'function' && !v.disableRemotePlayback);
    var hasPrev = typeof this.opts.onPrev === 'function';
    var hasNext = typeof this.opts.onNext === 'function';
    var hasEpisodes = typeof this.opts.onEpisodes === 'function';

    function btn(cls, label, icon, extra) {
      return '<button type="button" class="mp-btn ' + cls + '" aria-label="' + label + '"' +
             (extra || '') + '>' + icon + '</button>';
    }
    function action(name, label, icon) {
      return '<button type="button" class="mp-action" data-action="' + name + '" aria-pressed="false">' +
             '<span class="mp-action-icon">' + icon + '</span>' +
             '<span class="mp-action-label">' + label + '</span></button>';
    }

    shell.insertAdjacentHTML('beforeend', [
      '<div class="mp-dim"></div>',
      '<div class="mp-ripple left"></div>',
      '<div class="mp-ripple right"></div>',
      '<div class="mp-surface"></div>',
      '<div class="mp-spinner"></div>',
      '<div class="mp-hud"><span class="mp-hud-icon"></span>',
        '<span class="mp-hud-text"></span>',
        '<div class="mp-hud-bar"><i></i></div>',
        '<span class="mp-hud-sub"></span></div>',
      '<button type="button" class="mp-unlock" aria-label="Unlock controls">' + ICON.lock + '</button>',
      '<div class="mp-controls">',
        '<div class="mp-top">',
          btn('mp-navback', 'Back', ICON.navBack),
          '<span class="mp-title"></span>',
          canCast ? btn('mp-cast', 'Cast to a TV', ICON.cast) : '',
          hasEpisodes ? btn('mp-episodes', 'Episodes', ICON.episodes) : '',
          btn('mp-more', 'More options', ICON.more, ' aria-expanded="false"'),
        '</div>',
        '<div class="mp-actions" role="toolbar" aria-label="Player options">',
          action('night', 'Night mode', ICON.night),
          this.pip ? action('pip', 'Pop-up', ICON.pip) : '',
          action('timer', 'Timer', ICON.timer),
          action('speed', 'Speed', ICON.speed),
          action('bright', 'Brightness', ICON.bright),
          action('volume', 'Volume', ICON.vol),
          action('rotate', 'Landscape', ICON.rotate),
          action('mute', 'Mute', ICON.mute),
        '</div>',
        '<div class="mp-panel" aria-hidden="true">',
          '<span class="mp-panel-icon"></span>',
          '<input class="mp-panel-range" type="range" min="0" max="100" step="1">',
          '<span class="mp-panel-value"></span>',
        '</div>',
        '<div class="mp-center">',
          btn('mp-c-back', 'Back 10 seconds', ICON.back),
          btn('mp-c-play mp-playbtn', 'Play or pause', ICON.play),
          btn('mp-c-fwd', 'Forward 10 seconds', ICON.fwd),
        '</div>',
        '<div class="mp-bottom">',
          '<div class="mp-scrub-row">',
            '<span class="mp-now">0:00</span>',
            '<div class="mp-track">',
              '<div class="mp-track-bg"></div>',
              '<div class="mp-track-buf"></div>',
              '<div class="mp-track-fill"></div>',
              '<div class="mp-track-knob"></div>',
            '</div>',
            '<span class="mp-dur">0:00</span>',
          '</div>',
          '<div class="mp-bar">',
            btn('mp-lock', 'Lock controls', ICON.unlock),
            '<div class="mp-transport">',
              btn('mp-back', 'Back 10 seconds', ICON.back),
              hasPrev ? btn('mp-prev', 'Previous episode', ICON.prev) : '',
              btn('mp-play mp-playbtn', 'Play or pause', ICON.play),
              hasNext ? btn('mp-next', 'Next episode', ICON.next) : '',
              btn('mp-fwd', 'Forward 10 seconds', ICON.fwd),
            '</div>',
            '<div class="mp-bar-end">',
              btn('mp-fit', 'Change aspect ratio', ICON.fit),
              btn('mp-fs', 'Fullscreen', ICON.fs),
            '</div>',
          '</div>',
        '</div>',
      '</div>'
    ].join(''));

    this.shell      = shell;
    this.dimEl      = shell.querySelector('.mp-dim');
    this.surface    = shell.querySelector('.mp-surface');
    this.hud        = shell.querySelector('.mp-hud');
    this.hudIcon    = shell.querySelector('.mp-hud-icon');
    this.hudText    = shell.querySelector('.mp-hud-text');
    this.hudFill    = shell.querySelector('.mp-hud-bar > i');
    this.hudBar     = shell.querySelector('.mp-hud-bar');
    this.hudSub     = shell.querySelector('.mp-hud-sub');
    this.controls   = shell.querySelector('.mp-controls');
    this.rippleL    = shell.querySelector('.mp-ripple.left');
    this.rippleR    = shell.querySelector('.mp-ripple.right');
    this.track      = shell.querySelector('.mp-track');
    this.fill       = shell.querySelector('.mp-track-fill');
    this.buf        = shell.querySelector('.mp-track-buf');
    this.knob       = shell.querySelector('.mp-track-knob');
    this.nowEl      = shell.querySelector('.mp-now');
    this.durEl      = shell.querySelector('.mp-dur');
    this.playBtns   = shell.querySelectorAll('.mp-playbtn');
    this.moreBtn    = shell.querySelector('.mp-more');
    this.fsBtn      = shell.querySelector('.mp-fs');
    this.panelEl    = shell.querySelector('.mp-panel');
    this.panelIcon  = shell.querySelector('.mp-panel-icon');
    this.panelRange = shell.querySelector('.mp-panel-range');
    this.panelValue = shell.querySelector('.mp-panel-value');

    if (this.opts.title) {
      shell.querySelector('.mp-title').textContent = this.opts.title;
    }
  };

  /** Compact layout below WIDE_MIN px, full layout above it. */
  MobilePlayer.prototype._watchWidth = function () {
    var self = this;
    var apply = function () {
      self.shell.classList.toggle('mp-wide', self.shell.clientWidth >= WIDE_MIN);
    };
    apply();
    if (typeof ResizeObserver === 'function') {
      this.resizeObserver = new ResizeObserver(apply);
      this.resizeObserver.observe(this.shell);
    } else {
      this._onResize = apply;
      global.addEventListener('resize', apply);
    }
  };

  /* --------------------------------------------------------- video events */

  MobilePlayer.prototype._bindVideo = function () {
    var self = this, v = this.video;

    v.addEventListener('timeupdate', function () { self._renderProgress(); });
    v.addEventListener('progress',   function () { self._renderBuffer(); });
    v.addEventListener('loadedmetadata', function () {
      self.durEl.textContent = fmt(v.duration);
      self._renderProgress();
    });
    v.addEventListener('play',  function () { self._syncPlayIcon(); self.scheduleHide(); });
    v.addEventListener('pause', function () { self._syncPlayIcon(); self.showControls(true); });
    v.addEventListener('waiting',  function () { self.shell.classList.add('buffering'); });
    v.addEventListener('playing',  function () { self.shell.classList.remove('buffering'); });
    v.addEventListener('canplay',  function () { self.shell.classList.remove('buffering'); });
    v.addEventListener('ended',    function () { self.showControls(true); });
    v.addEventListener('volumechange', function () { self._syncMute(); self._syncPanel(); });

    // The media may already be past loadedmetadata by the time we attach --
    // a cached file, or a stylesheet delaying this script, is enough to win
    // that race. Waiting for an event that has already fired would leave the
    // duration stuck at 0:00, so paint the current state immediately.
    if (v.readyState >= 1 && isFinite(v.duration)) {
      this.durEl.textContent = fmt(v.duration);
      this._renderProgress();
      this._renderBuffer();
    }
  };

  MobilePlayer.prototype._syncPlayIcon = function () {
    var icon = this.video.paused ? ICON.play : ICON.pause;
    for (var i = 0; i < this.playBtns.length; i++) this.playBtns[i].innerHTML = icon;
  };

  MobilePlayer.prototype._renderProgress = function () {
    var v = this.video;
    if (!isFinite(v.duration) || v.duration <= 0) return;
    var pct = (v.currentTime / v.duration) * 100;
    this.fill.style.width = pct + '%';
    this.knob.style.left = pct + '%';
    this.nowEl.textContent = fmt(v.currentTime);
    this.durEl.textContent = fmt(v.duration);
  };

  MobilePlayer.prototype._renderBuffer = function () {
    var v = this.video;
    if (!v.buffered || !v.buffered.length || !isFinite(v.duration)) return;
    var end = v.buffered.end(v.buffered.length - 1);
    this.buf.style.width = ((end / v.duration) * 100) + '%';
  };

  /* ---------------------------------------------------------------- HUD */

  MobilePlayer.prototype.showHud = function (icon, text, ratio, sub) {
    this.hudIcon.innerHTML = icon;
    this.hudText.textContent = text;
    this.hudSub.textContent = sub || '';
    if (ratio === null || ratio === undefined) {
      this.hudBar.style.display = 'none';
    } else {
      this.hudBar.style.display = '';
      this.hudFill.style.width = clamp(ratio * 100, 0, 100) + '%';
    }
    this.hud.classList.add('show');
    clearTimeout(this.hudTimer);
    var self = this;
    this.hudTimer = setTimeout(function () { self.hud.classList.remove('show'); }, 900);
  };

  /* ----------------------------------------------------------- controls */

  MobilePlayer.prototype.showControls = function (sticky) {
    this.controls.classList.remove('hidden');
    clearTimeout(this.hideTimer);
    if (!sticky) this.scheduleHide();
  };

  MobilePlayer.prototype.scheduleHide = function () {
    var self = this;
    clearTimeout(this.hideTimer);
    // Keep everything up while the options row or a slider is open.
    if (this.video.paused || this.menuOpen || this.panel) return;
    this.hideTimer = setTimeout(function () { self.hideControls(); }, HIDE_DELAY);
  };

  MobilePlayer.prototype.hideControls = function () {
    this.closeMenu();
    this.controls.classList.add('hidden');
  };

  MobilePlayer.prototype.toggleControls = function () {
    if (this.controls.classList.contains('hidden')) this.showControls();
    else this.hideControls();
  };

  MobilePlayer.prototype.togglePlay = function () {
    if (this.video.paused) this.video.play().catch(function () {});
    else this.video.pause();
  };

  MobilePlayer.prototype.skip = function (delta) {
    var v = this.video;
    if (!isFinite(v.duration)) return;
    v.currentTime = clamp(v.currentTime + delta, 0, v.duration);
    var side = delta < 0 ? this.rippleL : this.rippleR;
    side.textContent = (delta < 0 ? '« ' : '') + Math.abs(delta) + 's' + (delta > 0 ? ' »' : '');
    side.classList.add('show');
    setTimeout(function () { side.classList.remove('show'); }, 340);
  };

  MobilePlayer.prototype.setDim = function (value) {
    this.dim = clamp(value, 0, 0.85);
    this.dimEl.style.opacity = this.dim;
    this._syncPanel();
  };

  MobilePlayer.prototype.setVolume = function (value) {
    if (!this.canSetVolume) return false;
    this.video.volume = clamp(value, 0, 1);
    this.video.muted = this.video.volume === 0;
    return true;
  };

  MobilePlayer.prototype.cycleFit = function () {
    var order = ['contain', 'cover', 'fill'];
    var names = { contain: 'Fit', cover: 'Crop', fill: 'Stretch' };
    this.fit = order[(order.indexOf(this.fit) + 1) % order.length];
    this.shell.setAttribute('data-fit', this.fit);
    this.showHud(ICON.fit, names[this.fit], null);
  };

  /* ------------------------------------------------- options row & sliders */

  MobilePlayer.prototype.toggleMenu = function (open) {
    this.menuOpen = (open === undefined) ? !this.menuOpen : !!open;
    this.shell.classList.toggle('menu-open', this.menuOpen);
    this.moreBtn.setAttribute('aria-expanded', String(this.menuOpen));
    if (!this.menuOpen) this.closePanel();
    this.showControls(this.menuOpen);
  };

  MobilePlayer.prototype.closeMenu = function () {
    if (this.menuOpen) {
      this.menuOpen = false;
      this.shell.classList.remove('menu-open');
      this.moreBtn.setAttribute('aria-expanded', 'false');
    }
    this.closePanel();
  };

  MobilePlayer.prototype.openPanel = function (kind) {
    if (this.panel === kind) { this.closePanel(); return; }
    this.panel = kind;
    this.shell.classList.add('panel-open');
    this.panelEl.setAttribute('aria-hidden', 'false');
    this.panelIcon.innerHTML = kind === 'bright' ? ICON.bright : ICON.vol;
    this.panelRange.setAttribute('aria-label', kind === 'bright' ? 'Brightness' : 'Volume');
    this._setActionActive('bright', kind === 'bright');
    this._setActionActive('volume', kind === 'volume');
    this._syncPanel();
    this.showControls(true);
  };

  MobilePlayer.prototype.closePanel = function () {
    if (!this.panel) return;
    this.panel = null;
    this.shell.classList.remove('panel-open');
    this.panelEl.setAttribute('aria-hidden', 'true');
    this._setActionActive('bright', false);
    this._setActionActive('volume', false);
  };

  MobilePlayer.prototype._syncPanel = function () {
    if (!this.panel || !this.panelRange) return;
    var pct = this.panel === 'bright'
      ? Math.round((1 - this.dim / 0.85) * 100)
      : Math.round((this.video.muted ? 0 : this.video.volume) * 100);
    this.panelRange.value = pct;
    this.panelValue.textContent = pct + '%';
  };

  MobilePlayer.prototype.runAction = function (name) {
    switch (name) {
      case 'night':  this.toggleNight(); break;
      case 'pip':    this.togglePip(); break;
      case 'timer':  this.cycleSleepTimer(); break;
      case 'speed':  this.cycleSpeed(); break;
      case 'bright': this.openPanel('bright'); break;
      case 'volume':
        if (!this.canSetVolume) {
          this.showHud(ICON.vol, 'Volume', null, "use your phone's volume buttons");
        } else {
          this.openPanel('volume');
        }
        break;
      case 'rotate': this.toggleOrientation(); break;
      case 'mute':   this.toggleMute(); break;
    }
    if (name !== 'bright' && name !== 'volume') this.closePanel();
    this.showControls(true);
  };

  MobilePlayer.prototype._actionEl = function (name) {
    return this.shell.querySelector('.mp-action[data-action="' + name + '"]');
  };

  MobilePlayer.prototype._setActionActive = function (name, on) {
    var el = this._actionEl(name);
    if (!el) return;
    el.classList.toggle('active', !!on);
    el.setAttribute('aria-pressed', on ? 'true' : 'false');
  };

  MobilePlayer.prototype._setActionLabel = function (name, text) {
    var el = this._actionEl(name);
    if (el) el.querySelector('.mp-action-label').textContent = text;
  };

  /* -------------------------------------------------------------- actions */

  MobilePlayer.prototype.toggleNight = function () {
    var on = !this.shell.classList.contains('night');
    this.shell.classList.toggle('night', on);
    this._setActionActive('night', on);
    this.showHud(ICON.night, on ? 'Night mode on' : 'Night mode off', null, on ? 'warmer, dimmer picture' : '');
  };

  MobilePlayer.prototype.togglePip = function () {
    var v = this.video, self = this;
    if (this.pip === 'standard') {
      if (document.pictureInPictureElement) {
        document.exitPictureInPicture().catch(function () {});
        return;
      }
      v.requestPictureInPicture().catch(function () {
        self.showHud(ICON.pip, 'Pop-up unavailable', null, 'start the video first');
      });
    } else if (this.pip === 'webkit') {
      try {
        v.webkitSetPresentationMode(
          v.webkitPresentationMode === 'picture-in-picture' ? 'inline' : 'picture-in-picture'
        );
      } catch (e) { /* refused by the browser */ }
    }
  };

  MobilePlayer.prototype.cycleSleepTimer = function () {
    this.sleepStep = (this.sleepStep + 1) % SLEEP_STEPS.length;
    var minutes = SLEEP_STEPS[this.sleepStep];
    this.setSleepTimer(minutes);
    this.showHud(ICON.timer, minutes ? 'Stop in ' + minutes + ' min' : 'Timer off', null,
                 minutes ? 'playback pauses then' : '');
  };

  /** Pause playback after `minutes`. 0 cancels. Fractions are allowed. */
  MobilePlayer.prototype.setSleepTimer = function (minutes) {
    var self = this;
    clearTimeout(this.sleepTimer);
    clearInterval(this.sleepTicker);
    this.sleepEndsAt = 0;

    if (minutes > 0) {
      this.sleepEndsAt = Date.now() + minutes * 60000;
      this.sleepTimer = setTimeout(function () {
        self.video.pause();
        self.sleepEndsAt = 0;
        self.sleepStep = 0;
        clearInterval(self.sleepTicker);
        self._syncTimerLabel();
        self.showHud(ICON.timer, 'Timer ended', null, 'playback paused');
      }, minutes * 60000);
      this.sleepTicker = setInterval(function () { self._syncTimerLabel(); }, 15000);
    } else {
      this.sleepStep = 0;
    }
    this._syncTimerLabel();
  };

  MobilePlayer.prototype._syncTimerLabel = function () {
    if (this.sleepEndsAt) {
      var left = Math.max(1, Math.ceil((this.sleepEndsAt - Date.now()) / 60000));
      this._setActionLabel('timer', left + ' min');
    } else {
      this._setActionLabel('timer', 'Timer');
    }
    this._setActionActive('timer', !!this.sleepEndsAt);
  };

  MobilePlayer.prototype.cycleSpeed = function () {
    var i = SPEEDS.indexOf(this.rate);
    this.setRate(SPEEDS[(i + 1) % SPEEDS.length]);
    this.showHud(ICON.speed, rateLabel(this.rate), null, 'playback speed');
  };

  MobilePlayer.prototype.setRate = function (rate) {
    this.rate = rate;
    if (!this.boosted) this.video.playbackRate = rate;
    this._setActionLabel('speed', rate === 1 ? 'Speed' : rateLabel(rate));
    this._setActionActive('speed', rate !== 1);
  };

  MobilePlayer.prototype.toggleMute = function () {
    var v = this.video;
    v.muted = !v.muted;
    if (!v.muted && v.volume === 0 && this.canSetVolume) v.volume = 0.5;
    this._syncMute();
    this.showHud(v.muted ? ICON.mute : ICON.vol, v.muted ? 'Muted' : 'Sound on', null);
  };

  MobilePlayer.prototype._syncMute = function () {
    this._setActionLabel('mute', this.video.muted ? 'Unmute' : 'Mute');
    this._setActionActive('mute', this.video.muted);
  };

  MobilePlayer.prototype.toggleOrientation = function () {
    var self = this;
    if (!this.isFullscreen()) {
      // Rotation can only be locked in fullscreen; entering it locks landscape.
      this.orient = 'landscape';
      this._syncRotateLabel();
      this.toggleFullscreen();
      return;
    }
    this.orient = this.orient === 'landscape' ? 'portrait' : 'landscape';
    this._syncRotateLabel();
    this._lockOrientation().then(function (ok) {
      if (!ok) self.showHud(ICON.rotate, 'Turn your phone', null, "this browser can't rotate the screen");
    });
  };

  MobilePlayer.prototype._syncRotateLabel = function () {
    this._setActionLabel('rotate', this.orient === 'landscape' ? 'Landscape' : 'Portrait');
  };

  /* -------------------------------------------------------- fullscreen */

  MobilePlayer.prototype.isFullscreen = function () {
    return fullscreenElement() === this.shell || this.shell.classList.contains('pseudo-fs');
  };

  MobilePlayer.prototype.toggleFullscreen = function () {
    var shell = this.shell;

    if (fullscreenElement()) {
      (document.exitFullscreen || document.webkitExitFullscreen).call(document);
      this._exitPseudo();
      return;
    }
    if (shell.classList.contains('pseudo-fs')) {
      this._exitPseudo();
      return;
    }

    var req = shell.requestFullscreen || shell.webkitRequestFullscreen;
    if (req) {
      var self = this;
      Promise.resolve(req.call(shell)).then(function () {
        self._lockOrientation();
        self._syncFsIcon();
      }).catch(function () {
        self._enterPseudo();
      });
    } else {
      // iPhone Safari: no element fullscreen. Fill the viewport instead so
      // the custom controls survive.
      this._enterPseudo();
    }
  };

  MobilePlayer.prototype._enterPseudo = function () {
    this.shell.classList.add('pseudo-fs');
    document.body.classList.add('mp-fs-lock');
    this._lockOrientation();
    this._syncFsIcon();
  };

  MobilePlayer.prototype._exitPseudo = function () {
    this.shell.classList.remove('pseudo-fs');
    document.body.classList.remove('mp-fs-lock');
    try {
      if (screen.orientation && screen.orientation.unlock) screen.orientation.unlock();
    } catch (e) { /* not supported; harmless */ }
    this._syncFsIcon();
  };

  /** Resolves true if the browser accepted the rotation request. */
  MobilePlayer.prototype._lockOrientation = function () {
    try {
      if (screen.orientation && screen.orientation.lock) {
        return screen.orientation.lock(this.orient).then(
          function () { return true; },
          function () { return false; }
        );
      }
    } catch (e) { /* iOS and desktop reject this */ }
    return Promise.resolve(false);
  };

  MobilePlayer.prototype._syncFsIcon = function () {
    var fs = this.isFullscreen();
    this.fsBtn.innerHTML = fs ? ICON.fsExit : ICON.fs;
    this.fsBtn.setAttribute('aria-label', fs ? 'Exit fullscreen' : 'Fullscreen');
  };

  MobilePlayer.prototype._syncAll = function () {
    this._syncPlayIcon();
    this._syncMute();
    this._syncTimerLabel();
    this._syncRotateLabel();
    this._syncFsIcon();
    this.setRate(this.rate);
  };

  /* ----------------------------------------------------------- gestures */

  MobilePlayer.prototype._bindGestures = function () {
    var self = this;
    var s = this.surface;

    var start = null;

    s.addEventListener('touchstart', function (e) {
      if (self.locked || e.touches.length !== 1) return;
      var t = e.touches[0];
      var rect = s.getBoundingClientRect();
      start = {
        x: t.clientX, y: t.clientY,
        time: Date.now(),
        rect: rect,
        leftHalf: (t.clientX - rect.left) < rect.width / 2,
        mode: null,
        baseTime: self.video.currentTime,
        baseVol: self.video.volume,
        baseDim: self.dim
      };
      self.longTimer = setTimeout(function () {
        if (!start || start.mode) return;
        start.mode = 'boost';
        self.boosted = true;
        self.video.playbackRate = BOOST_RATE;
        self.showHud(ICON.speed, BOOST_RATE + '× speed', null, 'release to restore');
      }, LONG_PRESS);
    }, { passive: true });

    s.addEventListener('touchmove', function (e) {
      if (!start || self.locked || e.touches.length !== 1) return;
      var t = e.touches[0];
      var dx = t.clientX - start.x;
      var dy = t.clientY - start.y;

      if (!start.mode) {
        if (Math.abs(dx) < DRAG_START && Math.abs(dy) < DRAG_START) return;
        clearTimeout(self.longTimer);
        start.mode = Math.abs(dx) > Math.abs(dy)
          ? 'seek'
          : (start.leftHalf ? 'bright' : 'volume');
      }

      if (start.mode === 'seek') {
        if (!isFinite(self.video.duration)) return;
        var target = clamp(start.baseTime + dx * SEEK_PER_PX, 0, self.video.duration);
        start.seekTarget = target;
        var diff = Math.round(target - start.baseTime);
        self.showHud(
          diff < 0 ? ICON.rw : ICON.ff,
          fmt(target),
          target / self.video.duration,
          (diff >= 0 ? '+' : '') + diff + 's'
        );
      } else if (start.mode === 'bright') {
        // Drag up = brighter (less veil), down = darker.
        var d = clamp(start.baseDim + (dy / start.rect.height) * 1.4, 0, 0.85);
        self.setDim(d);
        self.showHud(ICON.bright, Math.round((1 - d / 0.85) * 100) + '%',
                     1 - d / 0.85, 'screen dimmer');
      } else if (start.mode === 'volume') {
        if (!self.canSetVolume) {
          self.showHud(ICON.vol, 'Volume', null, "use your phone's volume buttons");
          return;
        }
        var vol = clamp(start.baseVol - (dy / start.rect.height) * 1.4, 0, 1);
        self.setVolume(vol);
        self.showHud(vol === 0 ? ICON.mute : ICON.vol,
                     Math.round(vol * 100) + '%', vol);
      }
    }, { passive: true });

    s.addEventListener('touchend', function () {
      clearTimeout(self.longTimer);
      if (!start) return;

      if (start.mode === 'boost') {
        self.boosted = false;
        self.video.playbackRate = self.rate;   // back to the chosen speed, not 1
        self.hud.classList.remove('show');
        start = null;
        return;
      }

      if (start.mode === 'seek' && start.seekTarget !== undefined) {
        self.video.currentTime = start.seekTarget;
        start = null;
        return;
      }

      if (start.mode === null && Date.now() - start.time < 300) {
        // A tap. Distinguish single from double.
        var now = Date.now();
        var x = start.x;
        var rect = start.rect;
        if (now - self.lastTap < 300 && Math.abs(x - self.lastTapX) < 60) {
          self.lastTap = 0;
          var third = rect.width / 3;
          var rel = x - rect.left;
          if (rel < third)              self.skip(-SEEK_TAP);
          else if (rel > third * 2)     self.skip(SEEK_TAP);
          else                          self.togglePlay();
        } else {
          self.lastTap = now;
          self.lastTapX = x;
          // Delay the single-tap action so a second tap can pre-empt it.
          setTimeout(function () {
            if (self.lastTap !== now) return;
            if (self.menuOpen || self.panel) {
              // Tapping the picture dismisses the options first.
              self.closeMenu();
              self.showControls();
            } else {
              self.toggleControls();
            }
          }, 260);
        }
      }
      start = null;
    }, { passive: true });

    s.addEventListener('touchcancel', function () {
      clearTimeout(self.longTimer);
      if (self.boosted) { self.boosted = false; self.video.playbackRate = self.rate; }
      start = null;
    }, { passive: true });

    // Desktop / mouse: click toggles playback, no gesture layer.
    s.addEventListener('click', function () {
      if (!self.locked && !('ontouchstart' in window)) self.togglePlay();
    });
  };

  /* ----------------------------------------------------- control wiring */

  MobilePlayer.prototype._bindControls = function () {
    var self = this, shell = this.shell;

    var on = function (selector, handler) {
      var el = shell.querySelector(selector);
      if (el) el.addEventListener('click', function (e) { e.stopPropagation(); handler(e); });
    };

    on('.mp-play',   function () { self.togglePlay(); self.showControls(); });
    on('.mp-c-play', function () { self.togglePlay(); self.showControls(); });
    on('.mp-back',   function () { self.skip(-SEEK_TAP); self.showControls(); });
    on('.mp-c-back', function () { self.skip(-SEEK_TAP); self.showControls(); });
    on('.mp-fwd',    function () { self.skip(SEEK_TAP); self.showControls(); });
    on('.mp-c-fwd',  function () { self.skip(SEEK_TAP); self.showControls(); });
    on('.mp-prev',   function () { self.opts.onPrev(); });
    on('.mp-next',   function () { self.opts.onNext(); });
    on('.mp-fit',    function () { self.cycleFit(); self.showControls(); });
    on('.mp-fs',     function () { self.toggleFullscreen(); self.showControls(); });
    on('.mp-more',   function () { self.toggleMenu(); });
    on('.mp-episodes', function () {
      self.closeMenu();
      self.showControls(true);
      self.opts.onEpisodes();
    });

    on('.mp-navback', function () {
      // In fullscreen, back means "leave fullscreen", as in phone video apps.
      if (self.isFullscreen()) { self.toggleFullscreen(); return; }
      if (typeof self.opts.onBack === 'function') self.opts.onBack();
      else if (global.history.length > 1) global.history.back();
    });

    on('.mp-cast', function () {
      self.video.remote.prompt().catch(function () {
        self.showHud(ICON.cast, 'No TV found', null, 'the TV must be on the same Wi-Fi');
      });
    });

    on('.mp-lock', function () {
      self.locked = true;
      self.closeMenu();
      shell.classList.add('locked');
      self.showHud(ICON.lock, 'Locked', null, 'tap the lock to release');
    });

    on('.mp-unlock', function () {
      self.locked = false;
      shell.classList.remove('locked');
      self.showControls();
    });

    var actions = shell.querySelectorAll('.mp-action');
    for (var i = 0; i < actions.length; i++) {
      actions[i].addEventListener('click', function (e) {
        e.stopPropagation();
        self.runAction(this.getAttribute('data-action'));
      });
    }

    this.panelRange.addEventListener('input', function () {
      var value = Number(self.panelRange.value) / 100;
      if (self.panel === 'bright') self.setDim((1 - value) * 0.85);
      else if (self.panel === 'volume') self.setVolume(value);
      self._syncPanel();
    });

    // Scrubbing the seek bar directly.
    var dragging = false;
    var seekFromEvent = function (clientX) {
      var r = self.track.getBoundingClientRect();
      var ratio = clamp((clientX - r.left) / r.width, 0, 1);
      if (isFinite(self.video.duration)) {
        self.video.currentTime = ratio * self.video.duration;
        self._renderProgress();
      }
    };

    this.track.addEventListener('touchstart', function (e) {
      dragging = true;
      seekFromEvent(e.touches[0].clientX);
      self.showControls(true);
    }, { passive: true });

    this.track.addEventListener('touchmove', function (e) {
      if (dragging) seekFromEvent(e.touches[0].clientX);
    }, { passive: true });

    this.track.addEventListener('touchend', function () {
      dragging = false;
      self.scheduleHide();
    }, { passive: true });

    this.track.addEventListener('click', function (e) { seekFromEvent(e.clientX); });

    this._onFullscreenChange = function () {
      if (!fullscreenElement()) self._exitPseudo();
      self._syncFsIcon();
    };
    document.addEventListener('fullscreenchange', this._onFullscreenChange);
    document.addEventListener('webkitfullscreenchange', this._onFullscreenChange);
  };

  /* ------------------------------------------------------------- public */

  MobilePlayer.prototype.destroy = function () {
    clearTimeout(this.hideTimer);
    clearTimeout(this.hudTimer);
    clearTimeout(this.longTimer);
    clearTimeout(this.sleepTimer);
    clearInterval(this.sleepTicker);
    if (this.resizeObserver) this.resizeObserver.disconnect();
    if (this._onResize) global.removeEventListener('resize', this._onResize);
    document.removeEventListener('fullscreenchange', this._onFullscreenChange);
    document.removeEventListener('webkitfullscreenchange', this._onFullscreenChange);
    var v = this.video;
    if (this.shell && this.shell.parentNode) {
      this.shell.parentNode.insertBefore(v, this.shell);
      this.shell.parentNode.removeChild(this.shell);
    }
    v.setAttribute('controls', '');
  };

  /**
   * Attach to a <video>, but only where it makes sense: a touch device with
   * an actual media file. Returns the instance, or null if skipped.
   *
   * Options: title, onPrev, onNext, onEpisodes, onBack (functions; the
   * previous/next/episodes buttons only appear when given), force (attach on
   * non-touch devices too).
   */
  MobilePlayer.attach = function (video, options) {
    if (!video) return null;
    if (video.closest('.mp-shell')) return null;          // already wrapped
    var coarse = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
    if (!coarse && !(options && options.force)) return null;
    return new MobilePlayer(video, options);
  };

  global.MobilePlayer = MobilePlayer;

})(window);
