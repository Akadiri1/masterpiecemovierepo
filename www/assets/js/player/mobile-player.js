/* ==========================================================================
   Mobile gesture player.

   Wraps a direct-file <video> with touch controls in the style phone video
   players use: vertical drag for brightness/volume, horizontal drag to seek,
   double-tap to skip, long-press to speed up, and a lock button.

   Only works on a real <video>. Cross-origin <iframe> embeds are sealed by
   the browser, so none of this can reach inside them.

   Two platform limits are handled honestly rather than faked:
     - There is no web API for screen brightness. "Brightness" composites a
       black veil, so it darkens but cannot go brighter than the device.
     - iOS Safari makes video.volume read-only. We detect that and tell the
       user to use the hardware keys instead of showing a slider that lies.
   ========================================================================== */

(function (global) {
  'use strict';

  /* Font Awesome 6 is already loaded by watch.php. Using it keeps the player
     consistent with the rest of the site's chrome — raw Unicode entities got
     substituted by colour emoji on Android, which looked out of place. */
  var ICON = {
    play:   '<i class="fa-solid fa-play"></i>',
    pause:  '<i class="fa-solid fa-pause"></i>',
    back:   '<i class="fa-solid fa-rotate-left"></i>',
    fwd:    '<i class="fa-solid fa-rotate-right"></i>',
    lock:   '<i class="fa-solid fa-lock"></i>',
    unlock: '<i class="fa-solid fa-lock-open"></i>',
    fit:    '<i class="fa-solid fa-crop-simple"></i>',
    fs:     '<i class="fa-solid fa-expand"></i>',
    bright: '<i class="fa-solid fa-sun"></i>',
    vol:    '<i class="fa-solid fa-volume-high"></i>',
    mute:   '<i class="fa-solid fa-volume-xmark"></i>',
    ff:     '<i class="fa-solid fa-forward"></i>',
    rw:     '<i class="fa-solid fa-backward"></i>',
    speed:  '<i class="fa-solid fa-forward-fast"></i>'
  };

  var SEEK_TAP     = 10;    // seconds per double-tap
  var HIDE_DELAY   = 3200;  // ms before controls fade while playing
  var DRAG_START   = 12;    // px before a drag is recognised
  var LONG_PRESS   = 480;   // ms to trigger speed boost
  var BOOST_RATE   = 2;
  var SEEK_PER_PX  = 0.35;  // seconds of seek per px dragged

  function fmt(t) {
    if (!isFinite(t) || t < 0) t = 0;
    var h = Math.floor(t / 3600),
        m = Math.floor((t % 3600) / 60),
        s = Math.floor(t % 60);
    var mm = h ? String(m).padStart(2, '0') : String(m);
    return (h ? h + ':' : '') + mm + ':' + String(s).padStart(2, '0');
  }

  function clamp(v, lo, hi) { return v < lo ? lo : v > hi ? hi : v; }

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

  function MobilePlayer(video, options) {
    this.video = video;
    this.opts = options || {};
    this.dim = 0;             // 0 = untouched, 0.85 = heavily darkened
    this.locked = false;
    this.fit = 'contain';
    this.hideTimer = null;
    this.hudTimer = null;
    this.lastTap = 0;
    this.lastTapX = 0;
    this.boosted = false;
    this.canSetVolume = volumeIsWritable(video);

    this._build();
    this._bindVideo();
    this._bindGestures();
    this._bindControls();
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
      '<button class="mp-unlock" aria-label="Unlock controls">' + ICON.lock + '</button>',
      '<div class="mp-controls">',
        '<div class="mp-top">',
          '<span class="mp-title"></span>',
          '<button class="mp-btn mp-fit" aria-label="Change aspect ratio">' + ICON.fit + '</button>',
          '<button class="mp-btn mp-lock" aria-label="Lock controls">' + ICON.unlock + '</button>',
        '</div>',
        '<div class="mp-center">',
          '<button class="mp-btn mp-back" aria-label="Back 10 seconds">' + ICON.back + '</button>',
          '<button class="mp-btn mp-play" aria-label="Play or pause">' + ICON.play + '</button>',
          '<button class="mp-btn mp-fwd" aria-label="Forward 10 seconds">' + ICON.fwd + '</button>',
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
            '<button class="mp-btn mp-fs" aria-label="Fullscreen">' + ICON.fs + '</button>',
          '</div>',
        '</div>',
      '</div>'
    ].join(''));

    this.shell    = shell;
    this.dimEl    = shell.querySelector('.mp-dim');
    this.surface  = shell.querySelector('.mp-surface');
    this.hud      = shell.querySelector('.mp-hud');
    this.hudIcon  = shell.querySelector('.mp-hud-icon');
    this.hudText  = shell.querySelector('.mp-hud-text');
    this.hudFill  = shell.querySelector('.mp-hud-bar > i');
    this.hudBar   = shell.querySelector('.mp-hud-bar');
    this.hudSub   = shell.querySelector('.mp-hud-sub');
    this.controls = shell.querySelector('.mp-controls');
    this.rippleL  = shell.querySelector('.mp-ripple.left');
    this.rippleR  = shell.querySelector('.mp-ripple.right');
    this.track    = shell.querySelector('.mp-track');
    this.fill     = shell.querySelector('.mp-track-fill');
    this.buf      = shell.querySelector('.mp-track-buf');
    this.knob     = shell.querySelector('.mp-track-knob');
    this.nowEl    = shell.querySelector('.mp-now');
    this.durEl    = shell.querySelector('.mp-dur');
    this.playBtn  = shell.querySelector('.mp-play');

    if (this.opts.title) {
      shell.querySelector('.mp-title').textContent = this.opts.title;
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

    // The media may already be past loadedmetadata by the time we attach --
    // a cached file, or a stylesheet delaying this script, is enough to win
    // that race. Waiting for an event that has already fired would leave the
    // duration stuck at 0:00, so paint the current state immediately.
    if (v.readyState >= 1 && isFinite(v.duration)) {
      this.durEl.textContent = fmt(v.duration);
      this._renderProgress();
      this._renderBuffer();
    }
    this._syncPlayIcon();
  };

  MobilePlayer.prototype._syncPlayIcon = function () {
    this.playBtn.innerHTML = this.video.paused ? ICON.play : ICON.pause;
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
    this.hudTimer = setTimeout(function () { self.hud.classList.remove('show'); }, 700);
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
    if (this.video.paused) return;
    this.hideTimer = setTimeout(function () {
      self.controls.classList.add('hidden');
    }, HIDE_DELAY);
  };

  MobilePlayer.prototype.toggleControls = function () {
    if (this.controls.classList.contains('hidden')) this.showControls();
    else this.controls.classList.add('hidden');
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
  };

  MobilePlayer.prototype.cycleFit = function () {
    var order = ['contain', 'cover', 'fill'];
    var names = { contain: 'Fit', cover: 'Crop', fill: 'Stretch' };
    this.fit = order[(order.indexOf(this.fit) + 1) % order.length];
    this.shell.setAttribute('data-fit', this.fit);
    this.showHud(ICON.fit, names[this.fit], null);
  };

  /* -------------------------------------------------------- fullscreen */

  MobilePlayer.prototype.toggleFullscreen = function () {
    var shell = this.shell;
    var isFs = document.fullscreenElement || document.webkitFullscreenElement;

    if (isFs) {
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
        self._lockLandscape();
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
    this._lockLandscape();
  };

  MobilePlayer.prototype._exitPseudo = function () {
    this.shell.classList.remove('pseudo-fs');
    document.body.classList.remove('mp-fs-lock');
    try {
      if (screen.orientation && screen.orientation.unlock) screen.orientation.unlock();
    } catch (e) { /* not supported; harmless */ }
  };

  MobilePlayer.prototype._lockLandscape = function () {
    try {
      if (screen.orientation && screen.orientation.lock) {
        screen.orientation.lock('landscape').catch(function () {});
      }
    } catch (e) { /* iOS and desktop reject this; ignore */ }
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
          self.showHud(ICON.vol, 'Volume', null, 'use the hardware keys');
          return;
        }
        var vol = clamp(start.baseVol - (dy / start.rect.height) * 1.4, 0, 1);
        self.video.volume = vol;
        if (vol > 0) self.video.muted = false;
        self.showHud(vol === 0 ? ICON.mute : ICON.vol,
                     Math.round(vol * 100) + '%', vol);
      }
    }, { passive: true });

    s.addEventListener('touchend', function (e) {
      clearTimeout(self.longTimer);
      if (!start) return;

      if (start.mode === 'boost') {
        self.video.playbackRate = 1;
        self.boosted = false;
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
            if (self.lastTap === now) self.toggleControls();
          }, 260);
        }
      }
      start = null;
    }, { passive: true });

    s.addEventListener('touchcancel', function () {
      clearTimeout(self.longTimer);
      if (self.boosted) { self.video.playbackRate = 1; self.boosted = false; }
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

    shell.querySelector('.mp-play').addEventListener('click', function () { self.togglePlay(); self.showControls(); });
    shell.querySelector('.mp-back').addEventListener('click', function () { self.skip(-SEEK_TAP); self.showControls(); });
    shell.querySelector('.mp-fwd').addEventListener('click',  function () { self.skip(SEEK_TAP); self.showControls(); });
    shell.querySelector('.mp-fit').addEventListener('click',  function () { self.cycleFit(); self.showControls(); });
    shell.querySelector('.mp-fs').addEventListener('click',   function () { self.toggleFullscreen(); self.showControls(); });

    shell.querySelector('.mp-lock').addEventListener('click', function () {
      self.locked = true;
      shell.classList.add('locked');
      self.showHud(ICON.lock, 'Locked', null, 'tap the lock to release');
    });

    shell.querySelector('.mp-unlock').addEventListener('click', function () {
      self.locked = false;
      shell.classList.remove('locked');
      self.showControls();
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

    document.addEventListener('fullscreenchange', function () {
      if (!document.fullscreenElement) self._exitPseudo();
    });
  };

  /* ------------------------------------------------------------- public */

  MobilePlayer.prototype.destroy = function () {
    clearTimeout(this.hideTimer);
    clearTimeout(this.hudTimer);
    clearTimeout(this.longTimer);
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
