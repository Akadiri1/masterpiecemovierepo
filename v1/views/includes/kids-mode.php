<?php
/*
 * Kids Mode switching: switchProfileMode() and its dialog.
 *
 * The Kids Mode buttons in the header, the profile menu and the sidebar call
 * switchProfileMode(). The server (/switch-mode) decides what is needed:
 * nothing, the parental PIN, creating a PIN first, a Premium plan, or signing
 * in. Each of those is one step of the dialog below; "Forgot your PIN?"
 * resets it with the account password (/set-parental-pin).
 *
 * Included by the footer and by pages that have their own layout.
 */
?>
<style>
.km-overlay { position: fixed; inset: 0; z-index: 2147483000; display: flex; align-items: center; justify-content: center; padding: 16px; background: rgba(5, 5, 10, 0.72); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); overscroll-behavior: contain; opacity: 0; visibility: hidden; transition: opacity 0.2s, visibility 0s linear 0.2s; }
.km-overlay.is-open { opacity: 1; visibility: visible; transition: opacity 0.2s; }
.km-dialog { position: relative; width: 100%; max-width: 380px; padding: 30px 24px 22px; border-radius: 22px; border: 1px solid rgba(255, 255, 255, 0.08); background: #12121a; color: #fff; text-align: center; box-shadow: 0 30px 80px rgba(0, 0, 0, 0.6); transform: translateY(14px) scale(0.98); transition: transform 0.22s ease; }
.km-overlay.is-open .km-dialog { transform: none; }
.km-view[hidden] { display: none !important; }

.km-close { position: absolute; top: 12px; right: 12px; display: grid; place-items: center; width: 36px; height: 36px; min-height: 0; padding: 0; border: 0; border-radius: 10px; background: transparent; color: #8a8a96; font-size: 1.2rem; }
.km-close:hover { background: rgba(255, 255, 255, 0.08); color: #fff; }

.km-icon { display: grid; place-items: center; width: 60px; height: 60px; margin: 0 auto 16px; border-radius: 18px; background: linear-gradient(135deg, var(--primary, #e50914), #7b2cbf); box-shadow: 0 14px 30px -14px var(--primary, #e50914); color: #fff; font-size: 1.8rem; }
.km-icon.is-gold { background: linear-gradient(135deg, #f5c451, #e39b1b); box-shadow: 0 14px 30px -14px #e39b1b; color: #1b1300; }
.km-icon.is-muted { background: rgba(255, 255, 255, 0.08); box-shadow: none; }
.km-title { margin: 0 0 6px; color: #fff; font-size: 1.3rem; font-weight: 800; letter-spacing: -0.2px; }
.km-text { max-width: 300px; margin: 0 auto 22px; color: #a3a3b0; font-size: 0.92rem; line-height: 1.5; }

.km-field { position: relative; margin-bottom: 12px; text-align: left; }
.km-label { display: block; margin-bottom: 6px; color: #bdbdc8; font-size: 0.8rem; font-weight: 600; }
.km-pin,
.km-input { display: block; width: 100%; border-radius: 14px; border: 1px solid #2a2a36; background: #0b0b11; color: #fff; transition: border-color 0.2s, box-shadow 0.2s; }
.km-pin { height: 60px; padding: 0 52px; font-size: 1.7rem; font-weight: 700; letter-spacing: 0.45em; text-align: center; font-variant-numeric: tabular-nums; }
.km-pin::placeholder { color: #3d3d48; letter-spacing: 0.3em; }
.km-input { height: 52px; padding: 0 48px 0 14px; font-size: 1rem; }
.km-pin:focus,
.km-input:focus { outline: none; border-color: var(--primary, #e50914); box-shadow: 0 0 0 3px rgba(229, 9, 20, 0.22); }
.km-pin:disabled { opacity: 0.5; }
.km-eye { position: absolute; right: 8px; bottom: 10px; display: grid; place-items: center; width: 40px; height: 40px; min-height: 0; padding: 0; border: 0; border-radius: 10px; background: transparent; color: #8a8a96; font-size: 1.2rem; }
.km-eye:hover { color: #fff; }
.km-label + .km-pin ~ .km-eye,
.km-label + .km-input ~ .km-eye { bottom: 10px; }

.km-error { min-height: 1.35em; margin: 4px 0 14px; color: #ff7a7a; font-size: 0.85rem; line-height: 1.35; }

.km-primary { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; height: 52px; min-height: 0; border: 0; border-radius: 14px; background: var(--primary, #e50914); color: #fff; font-size: 1rem; font-weight: 700; text-decoration: none; transition: filter 0.2s, opacity 0.2s; }
.km-primary:hover { filter: brightness(1.08); color: #fff; }
.km-primary:disabled { opacity: 0.6; cursor: default; }
.km-secondary { display: block; width: 100%; height: 46px; min-height: 0; margin-top: 8px; border: 0; border-radius: 14px; background: transparent; color: #a3a3b0; font-size: 0.95rem; font-weight: 600; }
.km-secondary:hover { background: rgba(255, 255, 255, 0.05); color: #fff; }
.km-link { display: inline-block; min-height: 0; margin-top: 14px; padding: 4px; border: 0; background: none; color: #cfcfd8; font-size: 0.85rem; font-weight: 600; text-decoration: underline; text-underline-offset: 3px; }
.km-link:hover { color: #fff; }

.km-spinner { width: 48px; height: 48px; margin: 6px auto 18px; border-radius: 50%; border: 3px solid rgba(255, 255, 255, 0.12); border-top-color: var(--primary, #e50914); animation: kmSpin 0.8s linear infinite; }
@keyframes kmSpin { to { transform: rotate(360deg); } }
.km-shake { animation: kmShake 0.35s; }
@keyframes kmShake { 20%, 60% { transform: translateX(-7px); } 40%, 80% { transform: translateX(7px); } }

/* Phones: a sheet that rises from the bottom. */
@media (max-width: 575.98px) {
    .km-overlay { align-items: flex-end; padding: 0; }
    .km-dialog { max-width: none; padding-bottom: calc(22px + env(safe-area-inset-bottom, 0px)); border-radius: 24px 24px 0 0; transform: translateY(40px); }
}
@media (prefers-reduced-motion: reduce) {
    .km-dialog, .km-overlay { transition: none; }
    .km-shake { animation: none; }
}
</style>

<div class="km-overlay" id="kmOverlay" aria-hidden="true">
  <div class="km-dialog" role="dialog" aria-modal="true" aria-labelledby="km-title-unlock">
    <button type="button" class="km-close" data-km-close aria-label="Close"><i class="ph ph-x"></i></button>

    <!-- Leaving Kids Mode -->
    <form class="km-view" data-view="unlock" novalidate hidden>
      <div class="km-icon"><i class="ph-fill ph-lock-key"></i></div>
      <h2 class="km-title" id="km-title-unlock">Leave Kids Mode</h2>
      <p class="km-text">Enter the parental PIN to switch back to the main profile.</p>
      <div class="km-field">
        <input class="km-pin" id="kmPin" type="password" inputmode="numeric" autocomplete="off" maxlength="8" placeholder="••••" aria-label="Parental PIN">
        <button type="button" class="km-eye" data-km-eye="kmPin" aria-label="Show PIN"><i class="ph ph-eye"></i></button>
      </div>
      <p class="km-error" data-km-error role="alert"></p>
      <button type="submit" class="km-primary">Unlock</button>
      <button type="button" class="km-link" data-km-go="reset">Forgot your PIN?</button>
    </form>

    <!-- First time: create a PIN, then turn Kids Mode on -->
    <form class="km-view" data-view="create" novalidate hidden>
      <div class="km-icon"><i class="ph-fill ph-shield-check"></i></div>
      <h2 class="km-title" id="km-title-create">Create a parental PIN</h2>
      <p class="km-text">Kids Mode needs a PIN, so only a grown-up can switch back to the main profile.</p>
      <div class="km-field">
        <label class="km-label" for="kmNewPin">PIN (4 to 8 digits)</label>
        <input class="km-pin" id="kmNewPin" type="password" inputmode="numeric" autocomplete="new-password" maxlength="8" placeholder="••••">
        <button type="button" class="km-eye" data-km-eye="kmNewPin" aria-label="Show PIN"><i class="ph ph-eye"></i></button>
      </div>
      <div class="km-field">
        <label class="km-label" for="kmConfirmPin">Enter it again</label>
        <input class="km-pin" id="kmConfirmPin" type="password" inputmode="numeric" autocomplete="new-password" maxlength="8" placeholder="••••">
      </div>
      <p class="km-error" data-km-error role="alert"></p>
      <button type="submit" class="km-primary">Save PIN and turn on Kids Mode</button>
      <button type="button" class="km-secondary" data-km-close>Not now</button>
    </form>

    <!-- Forgotten PIN -->
    <form class="km-view" data-view="reset" novalidate hidden>
      <div class="km-icon"><i class="ph-fill ph-key"></i></div>
      <h2 class="km-title" id="km-title-reset">Forgot your PIN?</h2>
      <p class="km-text">Enter your account password. The PIN is removed and you're switched back to the main profile; you can create a new PIN in your profile.</p>
      <div class="km-field">
        <label class="km-label" for="kmPassword">Account password</label>
        <input class="km-input" id="kmPassword" type="password" autocomplete="current-password">
        <button type="button" class="km-eye" data-km-eye="kmPassword" aria-label="Show password"><i class="ph ph-eye"></i></button>
      </div>
      <p class="km-error" data-km-error role="alert"></p>
      <button type="submit" class="km-primary">Remove PIN</button>
      <button type="button" class="km-secondary" data-km-go="unlock">Back</button>
    </form>

    <!-- Free plan -->
    <div class="km-view" data-view="upgrade" hidden>
      <div class="km-icon is-gold"><i class="ph-fill ph-crown"></i></div>
      <h2 class="km-title" id="km-title-upgrade">Kids Mode is a Premium feature</h2>
      <p class="km-text">Upgrade to give children their own safe, family-friendly profile.</p>
      <a class="km-primary" href="/pricing-plan">See plans</a>
      <button type="button" class="km-secondary" data-km-close>Not now</button>
    </div>

    <!-- Signed out -->
    <div class="km-view" data-view="login" hidden>
      <div class="km-icon is-muted"><i class="ph ph-user-circle"></i></div>
      <h2 class="km-title" id="km-title-login">Sign in to use Kids Mode</h2>
      <p class="km-text">Kids Mode belongs to your account, so sign in first.</p>
      <a class="km-primary" href="/login" data-km-login>Sign in</a>
      <button type="button" class="km-secondary" data-km-close>Not now</button>
    </div>

    <!-- Anything else -->
    <div class="km-view" data-view="notice" hidden>
      <div class="km-icon is-muted"><i class="ph ph-warning-circle"></i></div>
      <h2 class="km-title" id="km-title-notice">Something went wrong</h2>
      <p class="km-text" data-km-text></p>
      <button type="button" class="km-primary" data-km-close>Close</button>
    </div>

    <!-- Switching (the page reloads) -->
    <div class="km-view" data-view="switching" hidden>
      <div class="km-spinner" aria-hidden="true"></div>
      <h2 class="km-title" id="km-title-switching">Switching…</h2>
      <p class="km-text">Just a moment.</p>
    </div>
  </div>
</div>

<script>
(function () {
  var overlay = document.getElementById('kmOverlay');
  if (!overlay || window.__kidsModeReady) return;
  window.__kidsModeReady = true;

  var dialog = overlay.querySelector('.km-dialog');
  var closeButton = overlay.querySelector('.km-close');
  var views = Array.prototype.slice.call(overlay.querySelectorAll('.km-view'));
  var CONNECTION_PROBLEM = 'Connection problem. Check your internet and try again.';
  var lastFocus = null;
  var lockTimer = null;
  var switching = false;

  function viewNamed(name) {
    return overlay.querySelector('[data-view="' + name + '"]');
  }

  function show(name) {
    clearInterval(lockTimer);
    views.forEach(function (view) { view.hidden = view.dataset.view !== name; });
    var view = viewNamed(name);
    dialog.setAttribute('aria-labelledby', 'km-title-' + name);
    closeButton.hidden = name === 'switching';
    view.querySelectorAll('input').forEach(function (input) { input.value = ''; input.disabled = false; });
    view.querySelectorAll('[data-km-error]').forEach(function (box) { box.textContent = ''; });
    view.querySelectorAll('button').forEach(function (button) { button.disabled = false; });

    if (!overlay.classList.contains('is-open')) {
      lastFocus = document.activeElement;
      overlay.classList.add('is-open');
      overlay.setAttribute('aria-hidden', 'false');
    }
    var first = view.querySelector('input, .km-primary');
    if (first) setTimeout(function () { first.focus(); }, 80);
    return view;
  }

  function close() {
    if (switching) return;
    clearInterval(lockTimer);
    overlay.classList.remove('is-open');
    overlay.setAttribute('aria-hidden', 'true');
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  function fail(view, message) {
    view.querySelector('[data-km-error]').textContent = message;
    dialog.classList.remove('km-shake');
    void dialog.offsetWidth;
    dialog.classList.add('km-shake');
  }

  function post(url, fields) {
    var body = new FormData();
    Object.keys(fields || {}).forEach(function (key) { body.append(key, fields[key]); });
    return fetch(url, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (response) { return response.json(); })
      .catch(function () { return { status: 'error', message: CONNECTION_PROBLEM }; });
  }

  function lockFor(view, seconds) {
    var input = view.querySelector('input');
    var submit = view.querySelector('.km-primary');
    var box = view.querySelector('[data-km-error]');
    clearInterval(lockTimer);
    function tick() {
      if (seconds <= 0) {
        clearInterval(lockTimer);
        if (input) input.disabled = false;
        submit.disabled = false;
        box.textContent = 'You can try again now.';
        return;
      }
      if (input) input.disabled = true;
      submit.disabled = true;
      box.textContent = 'Too many wrong tries. Try again in ' + (seconds >= 60 ? Math.ceil(seconds / 60) + ' min' : seconds + ' s') + '.';
      seconds -= 1;
    }
    tick();
    lockTimer = setInterval(tick, 1000);
  }

  // Shows whatever the server says comes next. `view` is the step that sent
  // the request, if any.
  function handle(data, view) {
    switch (data.status) {
      case 'success':
        if (!data.mode) return;
        switching = true;
        show('switching').querySelector('.km-title').textContent =
          data.mode === 'kid' ? 'Switching to Kids Mode…' : 'Switching to the main profile…';
        setTimeout(function () { window.location.reload(); }, 700);
        return;
      case 'need_pin':
        return show('unlock');
      case 'no_pin':
        return show('create');
      case 'upgrade':
        return show('upgrade');
      case 'login':
        show('login').querySelector('[data-km-login]').href =
          '/login?next=' + encodeURIComponent(location.pathname + location.search);
        return;
      case 'wrong_pin':
        view.querySelector('input').value = '';
        return fail(view, data.message);
      case 'locked':
        return lockFor(view || show('unlock'), data.retry_after || 60);
      default:
        if (view) return fail(view, data.message || CONNECTION_PROBLEM);
        show('notice').querySelector('[data-km-text]').textContent = data.message || CONNECTION_PROBLEM;
    }
  }

  function submitWith(view, url, fields) {
    var submit = view.querySelector('.km-primary');
    submit.disabled = true;
    return post(url, fields).then(function (data) {
      submit.disabled = false;
      return data;
    });
  }

  window.switchProfileMode = function () {
    post('/switch-mode', {}).then(function (data) { handle(data, null); });
  };

  viewNamed('unlock').addEventListener('submit', function (event) {
    event.preventDefault();
    var view = event.currentTarget;
    var pin = view.querySelector('#kmPin').value;
    if (!/^\d{4,8}$/.test(pin)) return fail(view, 'Enter your 4 to 8 digit PIN.');
    submitWith(view, '/switch-mode', { parent_pin: pin }).then(function (data) { handle(data, view); });
  });

  viewNamed('create').addEventListener('submit', function (event) {
    event.preventDefault();
    var view = event.currentTarget;
    var pin = view.querySelector('#kmNewPin').value;
    var again = view.querySelector('#kmConfirmPin').value;
    if (!/^\d{4,8}$/.test(pin)) return fail(view, 'Use 4 to 8 digits for the PIN.');
    if (pin !== again) return fail(view, "The two PINs don't match.");
    submitWith(view, '/set-parental-pin', { new_pin: pin, confirm_pin: again }).then(function (data) {
      if (data.status !== 'success') return handle(data, view);
      // PIN saved: now turn Kids Mode on.
      return post('/switch-mode', {}).then(function (next) { handle(next, view); });
    });
  });

  viewNamed('reset').addEventListener('submit', function (event) {
    event.preventDefault();
    var view = event.currentTarget;
    var password = view.querySelector('#kmPassword').value;
    if (!password) return fail(view, 'Enter your account password.');
    submitWith(view, '/set-parental-pin', { action: 'reset', password: password }).then(function (data) { handle(data, view); });
  });

  // PIN boxes take digits only.
  overlay.querySelectorAll('.km-pin').forEach(function (input) {
    input.addEventListener('input', function () {
      var digits = input.value.replace(/\D/g, '').slice(0, 8);
      if (digits !== input.value) input.value = digits;
    });
  });

  overlay.addEventListener('click', function (event) {
    if (event.target === overlay || event.target.closest('[data-km-close]')) return close();
    var go = event.target.closest('[data-km-go]');
    if (go) return show(go.dataset.kmGo);
    var eye = event.target.closest('[data-km-eye]');
    if (eye) {
      var field = document.getElementById(eye.dataset.kmEye);
      var reveal = field.type === 'password';
      field.type = reveal ? 'text' : 'password';
      eye.querySelector('i').className = reveal ? 'ph ph-eye-slash' : 'ph ph-eye';
      eye.setAttribute('aria-label', (reveal ? 'Hide ' : 'Show ') + (eye.dataset.kmEye === 'kmPassword' ? 'password' : 'PIN'));
      field.focus();
    }
  });

  document.addEventListener('keydown', function (event) {
    if (!overlay.classList.contains('is-open')) return;
    if (event.key === 'Escape') return close();
    if (event.key !== 'Tab') return;
    // Keep keyboard focus inside the dialog.
    var focusable = Array.prototype.filter.call(dialog.querySelectorAll('button, a[href], input'), function (el) {
      return !el.disabled && !el.hidden && el.offsetParent !== null;
    });
    if (!focusable.length) return;
    var first = focusable[0], last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });
})();
</script>
