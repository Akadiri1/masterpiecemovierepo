/*
 * Loading placeholders. The styles, and why they exist, are in
 * assets/css/core/skeleton.css.
 *
 * 1. Images shimmer until they load, then fade in.
 * 2. Following a link (or submitting a search) shows an outline of the next
 *    page at once.
 */
(function () {
  'use strict';

  if (window.__zenSkeleton) return;
  window.__zenSkeleton = true;

  /* ---- 1. Images --------------------------------------------------------- */

  // Logos and anything opted out never get a placeholder.
  var SKIP_IMAGES = '.logo, .navbar-brand img, .zen-nav-sk img, [data-no-skeleton]';
  var CSS_URL = /url\(\s*(['"]?)(.*?)\1\s*\)/;

  function trackImage(img) {
    if (img.__zenSk) return;
    img.__zenSk = true;
    // complete is true once an image has loaded or failed (or has no src).
    if (img.complete || img.matches(SKIP_IMAGES)) return;

    var box = img.getBoundingClientRect();
    if (box.width && box.width < 40) return; // small icons and avatars

    var started = Date.now();
    img.classList.add('zen-sk-img');

    function finish() {
      img.classList.remove('zen-sk-img');
      // Fade in only after a noticeable wait; cached images just appear.
      if (img.naturalWidth && Date.now() - started > 120) {
        img.classList.add('zen-sk-fade');
        setTimeout(function () { img.classList.remove('zen-sk-fade'); }, 400);
      }
    }
    img.addEventListener('load', finish, { once: true });
    img.addEventListener('error', finish, { once: true });
  }

  // CSS background images give no load event, so the same URL is loaded
  // into an Image and that is watched instead. It only happens once the
  // element is on screen, so hidden slides are never downloaded early.
  var backgroundObserver = 'IntersectionObserver' in window
    ? new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          backgroundObserver.unobserve(entry.target);
          watchBackground(entry.target);
        });
      }, { rootMargin: '100px' })
    : null;

  function watchBackground(el) {
    var match = CSS_URL.exec(el.style.backgroundImage || '');
    if (!match || !match[2] || match[2].indexOf('data:') === 0) return;
    var probe = new Image();
    probe.src = match[2];
    if (probe.complete) return;
    el.classList.add('zen-sk-bg');
    probe.onload = probe.onerror = function () { el.classList.remove('zen-sk-bg'); };
  }

  function trackBackground(el) {
    if (el.__zenSk || !backgroundObserver) return;
    el.__zenSk = true;
    backgroundObserver.observe(el);
  }

  function scan(node) {
    if (!node || node.nodeType !== 1) return;
    if (node.tagName === 'IMG') {
      trackImage(node);
      return;
    }
    if (node.style && node.style.backgroundImage) trackBackground(node);
    var images = node.getElementsByTagName('img');
    for (var i = 0; i < images.length; i++) trackImage(images[i]);
    var backgrounds = node.querySelectorAll('[style*="url("]');
    for (var j = 0; j < backgrounds.length; j++) trackBackground(backgrounds[j]);
  }

  function startImages() {
    scan(document.body);
    if (!('MutationObserver' in window)) return;
    // Content added later (sliders, search results, reviews).
    new MutationObserver(function (mutations) {
      for (var i = 0; i < mutations.length; i++) {
        var added = mutations[i].addedNodes;
        for (var j = 0; j < added.length; j++) scan(added[j]);
      }
    }).observe(document.body, { childList: true, subtree: true });
  }

  /* ---- 2. Outline of the next page ------------------------------------- */

  function repeat(html, times) {
    var out = '';
    for (var i = 0; i < times; i++) out += html;
    return out;
  }

  var TOP_BAR = '<div class="sk-top"><div class="sk sk-logo"></div><div class="sk-top-r">' +
    repeat('<div class="sk sk-icon"></div>', 3) + '</div></div>';
  var POSTER_ROW = '<div class="sk sk-h"></div><div class="sk-row">' +
    repeat('<div class="sk sk-poster"></div>', 6) + '</div>';
  var POSTER_GRID = function (count) {
    return '<div class="sk-grid">' + repeat('<div class="sk sk-poster"></div>', count) + '</div>';
  };

  var LAYOUTS = {
    home: TOP_BAR + '<div class="sk sk-hero"></div>' + POSTER_ROW + POSTER_ROW,

    detail: '<div class="sk-top"><div class="sk-top-r">' + repeat('<div class="sk sk-icon"></div>', 2) + '</div></div>' +
      '<div class="sk sk-player"></div>' +
      '<div class="sk-chips">' + repeat('<div class="sk sk-chip"></div>', 3) + '</div>' +
      '<div class="sk sk-title"></div>' +
      '<div class="sk sk-line"></div><div class="sk sk-line"></div><div class="sk sk-line w60"></div>' +
      '<div class="sk sk-btn"></div>' +
      '<div class="sk-tiles">' + repeat('<div class="sk sk-tile"></div>', 4) + '</div>' +
      POSTER_ROW,

    watch: '<div class="sk sk-player"></div>' +
      '<div class="sk sk-title"></div><div class="sk sk-line w60"></div>' +
      '<div class="sk-tiles">' + repeat('<div class="sk sk-tile"></div>', 6) + '</div>' +
      POSTER_ROW,

    grid: TOP_BAR + '<div class="sk sk-title"></div>' + POSTER_GRID(12),

    profile: TOP_BAR +
      '<div class="sk-center"><div class="sk sk-avatar"></div><div class="sk sk-title"></div><div class="sk sk-line"></div></div>' +
      '<div class="sk-tabs">' + repeat('<div class="sk"></div>', 3) + '</div>' + POSTER_GRID(6),

    person: TOP_BAR +
      '<div class="sk-person"><div class="sk sk-portrait"></div>' +
      '<div><div class="sk sk-title"></div><div class="sk sk-line"></div><div class="sk sk-line w60"></div></div></div>' +
      '<div class="sk-tabs">' + repeat('<div class="sk"></div>', 2) + '</div>' + POSTER_GRID(6)
  };

  function layoutFor(url) {
    var path = url.pathname;
    if (/^\/(movie|tv)\/\d+/.test(path) || /^\/movie-detail/.test(path)) return 'detail';
    if (/^\/watch/.test(path)) return 'watch';
    if (/^\/(view-all|search)/.test(path)) return 'grid';
    if (/^\/profile/.test(path)) return 'profile';
    if (/^\/person-detail/.test(path)) return 'person';
    if (/^\/(home|index\.php)?$/.test(path)) return 'home';
    return null; // other pages (sign-in, plans, admin) load without an outline
  }

  var outline = null;
  var safetyTimer = null;

  function closeMenus() {
    var menu = document.getElementById('appSidebar');
    if (menu && menu.classList.contains('mobile-open')) {
      menu.classList.remove('mobile-open');
      var shade = document.getElementById('sidebarOverlay');
      if (shade) shade.classList.remove('active');
    }
    var search = document.getElementById('searchOverlay');
    if (search) search.classList.remove('active');
  }

  function showOutline(kind) {
    if (!outline) {
      outline = document.createElement('div');
      outline.className = 'zen-nav-sk';
      outline.setAttribute('aria-hidden', 'true');
      document.body.appendChild(outline);
    }
    outline.innerHTML = '<div class="sk-inner">' + LAYOUTS[kind] + '</div>';

    // On desktop, leave the sidebar showing so only the page area changes.
    var left = 0;
    var sidebar = document.querySelector('#appSidebar, .watch-sidebar');
    if (sidebar && window.innerWidth >= 1200) {
      var box = sidebar.getBoundingClientRect();
      if (box.left >= 0 && box.width > 0 && box.width < window.innerWidth / 2) left = Math.round(box.right);
    }
    outline.style.left = left + 'px';

    closeMenus();
    void outline.offsetWidth; // apply the hidden state first so it fades in
    outline.classList.add('is-on');

    // If the navigation never finishes (a download, a cancelled request),
    // don't leave the outline covering the page.
    clearTimeout(safetyTimer);
    safetyTimer = setTimeout(hideOutline, 20000);
  }

  function hideOutline() {
    clearTimeout(safetyTimer);
    if (outline) outline.classList.remove('is-on');
  }

  function linkDestination(event) {
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return null;
    var link = event.target.closest ? event.target.closest('a[href]') : null;
    if (!link) return null;
    if ((link.target && link.target !== '_self') || link.hasAttribute('download') ||
        link.hasAttribute('data-bs-toggle') || link.hasAttribute('data-no-skeleton')) return null;

    var href = link.getAttribute('href');
    if (!href || href.charAt(0) === '#' || /^\s*(javascript|mailto|tel|sms):/i.test(href)) return null;

    var url;
    try { url = new URL(link.href, location.href); } catch (e) { return null; }
    if (url.origin !== location.origin) return null;
    if (url.pathname === location.pathname && url.search === location.search) return null; // same page
    return url;
  }

  // Both listeners run in the capture phase: some of the theme's own
  // handlers stop clicks from bubbling up to the document. The decision still
  // waits until every handler has run, so one that cancels the navigation
  // (preventDefault) is respected.
  document.addEventListener('click', function (event) {
    var url = linkDestination(event);
    var kind = url && layoutFor(url);
    if (!kind) return;
    setTimeout(function () {
      if (!event.defaultPrevented) showOutline(kind);
    }, 0);
  }, true);

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!form || (form.getAttribute('method') || 'get').toLowerCase() !== 'get') return;
    var url;
    try { url = new URL(form.getAttribute('action') || location.href, location.href); } catch (e) { return; }
    var kind = url.origin === location.origin && layoutFor(url);
    if (!kind) return;
    setTimeout(function () {
      if (!event.defaultPrevented) showOutline(kind);
    }, 0);
  }, true);

  // The Back button can restore a page from memory with the outline still on.
  window.addEventListener('pageshow', hideOutline);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startImages);
  } else {
    startImages();
  }
})();
