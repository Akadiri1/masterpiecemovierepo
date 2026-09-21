<?php
/**
 * "A new version is ready" — with a button that picks it up.
 *
 * An installed app keeps running the code it was opened with, so a deploy is
 * invisible until something reloads. Reloading on its own would be worse than
 * waiting: it would interrupt whatever is playing. So the page notices the new
 * build, says so, and leaves the moment to the person.
 *
 * Included from the header, the footer and the watch page; only the first one
 * renders.
 */
if (defined('PWA_UPDATE_RENDERED')) { return; }
define('PWA_UPDATE_RENDERED', true);

require_once __DIR__ . '/../../lib/app_version.php';
?>
<div class="zen-update-bar" id="zenUpdateBar" role="status" aria-live="polite" hidden>
    <span class="zen-update-dot" aria-hidden="true"></span>
    <div class="zen-update-text">
        <strong>A new version of ZEN is ready</strong>
        <small id="zenUpdateNote">Refresh to get the latest.</small>
    </div>
    <button type="button" class="zen-update-btn" id="zenUpdateBtn">Update</button>
    <button type="button" class="zen-update-dismiss" id="zenUpdateDismiss" aria-label="Not now">&times;</button>
</div>

<style>
    .zen-update-bar {
        position: fixed; left: 50%; transform: translate(-50%, 20px);
        bottom: 24px; z-index: 1000001;
        display: flex; align-items: center; gap: 12px;
        max-width: calc(100vw - 24px); padding: 12px 12px 12px 16px;
        border-radius: 14px; border: 1px solid rgba(0, 224, 255, 0.3);
        background: rgba(16, 17, 25, 0.97); backdrop-filter: blur(14px);
        box-shadow: 0 14px 40px rgba(0, 0, 0, 0.55);
        color: #e8e9ee; opacity: 0; pointer-events: none;
        transition: opacity 0.25s ease, transform 0.25s ease;
    }
    .zen-update-bar.show { opacity: 1; transform: translate(-50%, 0); pointer-events: auto; }
    .zen-update-dot {
        width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0;
        background: #00e0ff; box-shadow: 0 0 0 0 rgba(0, 224, 255, 0.5);
        animation: zenUpdatePulse 2s infinite;
    }
    @keyframes zenUpdatePulse {
        70% { box-shadow: 0 0 0 9px rgba(0, 224, 255, 0); }
        100% { box-shadow: 0 0 0 0 rgba(0, 224, 255, 0); }
    }
    .zen-update-text { flex: 1 1 auto; min-width: 0; line-height: 1.3; }
    .zen-update-text strong { display: block; font-size: 0.88rem; font-weight: 600; color: #fff; }
    .zen-update-text small { display: block; font-size: 0.74rem; color: #8b8f9c; }
    .zen-update-btn {
        flex-shrink: 0; border: none; border-radius: 10px; padding: 9px 16px;
        background: linear-gradient(135deg, #00e0ff, #7b2cbf); color: #fff;
        font-size: 0.85rem; font-weight: 700; cursor: pointer; transition: filter 0.15s;
    }
    .zen-update-btn:hover { filter: brightness(1.12); }
    .zen-update-btn[disabled] { opacity: 0.7; cursor: default; }
    .zen-update-dismiss {
        flex-shrink: 0; background: none; border: none; color: #6b7080;
        font-size: 1.4rem; line-height: 1; cursor: pointer; padding: 2px 6px; border-radius: 8px;
    }
    .zen-update-dismiss:hover { color: #fff; background: rgba(255, 255, 255, 0.08); }

    /* Clear of the phone tab bar, and out of the way of the chat. */
    @media (max-width: 1199.98px) { .zen-update-bar { bottom: 84px; } }

    /* On a phone it spans the width instead of shrinking around its text,
       which otherwise squeezes the wording down to one word per line. */
    @media (max-width: 575.98px) {
        .zen-update-bar { left: 12px; right: 12px; max-width: none; transform: translate(0, 20px); }
        .zen-update-bar.show { transform: translate(0, 0); }
        .zen-update-text strong { font-size: 0.84rem; }
        .zen-update-btn { padding: 9px 14px; }
    }
    body.zen-chat-open .zen-update-bar { display: none !important; }
</style>

<script>
(function () {
    var PAGE_VERSION = <?php echo json_encode(appVersion()); ?>;
    var bar = document.getElementById('zenUpdateBar');
    var btn = document.getElementById('zenUpdateBtn');
    var note = document.getElementById('zenUpdateNote');
    var dismiss = document.getElementById('zenUpdateDismiss');
    if (!bar || !btn) return;

    var shown = false;
    var liveVersion = PAGE_VERSION;

    function dismissedKey() { return 'zen_update_skip_' + liveVersion; }

    function wasDismissed() {
        try { return sessionStorage.getItem(dismissedKey()) === '1'; } catch (e) { return false; }
    }

    function show() {
        if (shown || wasDismissed()) return;
        shown = true;
        bar.hidden = false;
        // Let the browser paint the hidden state first, so it slides in.
        requestAnimationFrame(function () { bar.classList.add('show'); });
    }

    function hide() {
        shown = false;
        bar.classList.remove('show');
        setTimeout(function () { if (!shown) bar.hidden = true; }, 250);
    }

    // Is a different build live than the one this page was served from?
    function checkVersion() {
        if (shown || document.hidden) return;
        fetch('/version.php', { cache: 'no-store', credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) {
                if (!d || !d.version || d.version === PAGE_VERSION) return;
                liveVersion = d.version;
                show();
            })
            .catch(function () { /* offline, or the server is waking up */ });
    }

    // Taking the update: drop what the service worker cached, let it fetch the
    // new worker, then reload. Reloading alone can still hand back cached files.
    btn.addEventListener('click', function () {
        btn.disabled = true;
        btn.textContent = 'Updating…';
        if (note) note.textContent = 'Fetching the new version…';

        var jobs = [];
        if (window.caches && caches.keys) {
            jobs.push(caches.keys().then(function (keys) {
                return Promise.all(keys.map(function (k) { return caches.delete(k); }));
            }).catch(function () {}));
        }
        if (navigator.serviceWorker && navigator.serviceWorker.getRegistration) {
            jobs.push(navigator.serviceWorker.getRegistration().then(function (reg) {
                return reg ? reg.update() : null;
            }).catch(function () {}));
        }

        var done = false;
        var reload = function () {
            if (done) return;
            done = true;
            // Plain reload: the caches are already gone, and adding a parameter
            // to the URL would only risk landing somewhere the router mishandles.
            window.location.reload();
        };
        Promise.all(jobs).then(reload);
        setTimeout(reload, 4000); // never leave the button spinning
    });

    if (dismiss) {
        dismiss.addEventListener('click', function () {
            try { sessionStorage.setItem(dismissedKey(), '1'); } catch (e) {}
            hide();
        });
    }

    // A new service worker taking over also means the code on disk moved on.
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('controllerchange', function () { checkVersion(); });
        navigator.serviceWorker.ready.then(function (reg) {
            reg.addEventListener('updatefound', function () {
                var installing = reg.installing;
                if (!installing) return;
                installing.addEventListener('statechange', function () {
                    if (installing.state === 'installed' && navigator.serviceWorker.controller) checkVersion();
                });
            });
        }).catch(function () {});
    }

    // Coming back to an app left open for days is the common case, so look then.
    document.addEventListener('visibilitychange', function () { if (!document.hidden) checkVersion(); });
    window.addEventListener('online', checkVersion);
    setInterval(checkVersion, 15 * 60 * 1000);
    setTimeout(checkVersion, 8000); // after the page has settled

    // So other code (and the console) can ask on demand.
    window.zenCheckForUpdate = function () {
        shown = false;
        try { sessionStorage.removeItem(dismissedKey()); } catch (e) {}
        checkVersion();
    };
})();
</script>
