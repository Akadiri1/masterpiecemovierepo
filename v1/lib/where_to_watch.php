<?php
/**
 * "Where to watch": the streaming services that carry a title in the
 * visitor's country, from TMDB's watch provider data (supplied by JustWatch,
 * which must be credited wherever it is shown).
 *
 * Used by the watch page in Discover mode (see site_settings.php).
 */

require_once __DIR__ . '/site_settings.php';

const WTW_REGION_COOKIE = 'wtw_region';

/**
 * The country to show services for: one picked on the page (remembered in a
 * cookie), else a country header from a CDN, else the region in the
 * browser's language (en-NG gives NG). Null when none of these say.
 */
function wtwVisitorRegion(): ?string
{
    $valid = fn($code) => is_string($code) && preg_match('/^[A-Za-z]{2}$/', $code) ? strtoupper($code) : null;

    if ($picked = $valid($_GET['region'] ?? null)) {
        if (!headers_sent()) {
            setcookie(WTW_REGION_COOKIE, $picked, [
                'expires' => time() + 365 * 86400, 'path' => '/', 'samesite' => 'Lax',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            ]);
        }
        return $picked;
    }
    if ($saved = $valid($_COOKIE[WTW_REGION_COOKIE] ?? null)) {
        return $saved;
    }
    foreach (['HTTP_CF_IPCOUNTRY', 'HTTP_CLOUDFRONT_VIEWER_COUNTRY', 'HTTP_X_COUNTRY_CODE'] as $header) {
        if (($code = $valid($_SERVER[$header] ?? null)) && $code !== 'XX') {
            return $code;
        }
    }
    if (preg_match('/\b[a-z]{2,3}[-_]([a-z]{2})\b/i', $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', $m)) {
        return strtoupper($m[1]);
    }
    return null;
}

function wtwRegionName(string $code): string
{
    if (class_exists('Locale')) {
        $name = Locale::getDisplayRegion('-' . $code, 'en');
        if ($name && $name !== $code) return $name;
    }
    return $code;
}

/**
 * A link that opens the title on the service: its search page for the
 * services whose search URLs are stable, otherwise TMDB's page for the
 * title, which links straight to every service. Amazon links carry the
 * Associates tag (Admin > Playback) for US visitors.
 */
function wtwProviderUrl(string $provider, string $title, string $region, string $fallback): array
{
    $q = rawurlencode($title);
    $amazonTag = siteSetting('amazon_tag');

    if (preg_match('/amazon|prime video/i', $provider)) {
        if ($amazonTag && $region === 'US') {
            return ['https://www.amazon.com/s?k=' . $q . '&i=instant-video&tag=' . rawurlencode($amazonTag), true];
        }
        return ['https://www.primevideo.com/search/?phrase=' . $q, false];
    }
    $searchPages = [
        '/netflix/i'      => 'https://www.netflix.com/search?q=%s',
        '/apple tv/i'     => 'https://tv.apple.com/search?term=%s',
        '/google play/i'  => 'https://play.google.com/store/search?q=%s&c=movies',
        '/youtube/i'      => 'https://www.youtube.com/results?search_query=%s',
        '/tubi/i'         => 'https://tubitv.com/search/%s',
    ];
    foreach ($searchPages as $pattern => $url) {
        if (preg_match($pattern, $provider)) {
            return [sprintf($url, $q), false];
        }
    }
    return [$fallback, false];
}

/**
 * Services carrying a title, for the visitor's country or, when it has none
 * listed, the site's default country (Admin > Playback).
 *
 * @return array{region: ?string, regionName: ?string, requested: ?string, requestedName: ?string,
 *               link: string, providers: array, regions: array}
 */
function whereToWatch(string $type, int $id, string $title): array
{
    $type = $type === 'tv' ? 'tv' : 'movie';
    $data = function_exists('fetchTmdbApi') ? fetchTmdbApi("{$type}/{$id}/watch/providers", [], 43200) : null;
    $byRegion = $data['results'] ?? [];

    $requested = wtwVisitorRegion();
    $default = strtoupper(siteSetting('wtw_default_region', 'US'));
    $region = null;
    foreach (array_unique(array_filter([$requested, $default, 'US'])) as $candidate) {
        if (!empty($byRegion[$candidate])) {
            $region = $candidate;
            break;
        }
    }

    $tmdbLink = "https://www.themoviedb.org/{$type}/{$id}/watch";
    $providers = [];
    if ($region) {
        $kindLabels = ['free' => 'Free', 'ads' => 'Free with ads', 'flatrate' => 'Subscription', 'rent' => 'Rent', 'buy' => 'Buy'];
        $tmdbLink = $byRegion[$region]['link'] ?? $tmdbLink . '?locale=' . $region;
        foreach ($kindLabels as $kind => $label) {
            $list = $byRegion[$region][$kind] ?? [];
            usort($list, fn($a, $b) => ($a['display_priority'] ?? 999) <=> ($b['display_priority'] ?? 999));
            foreach ($list as $p) {
                $key = $p['provider_id'] ?? $p['provider_name'];
                if (!isset($providers[$key])) {
                    [$url, $affiliate] = wtwProviderUrl($p['provider_name'], $title, $region, $tmdbLink);
                    $providers[$key] = [
                        'name' => $p['provider_name'],
                        'logo' => $p['logo_path'] ?? '',
                        'url' => $url,
                        'affiliate' => $affiliate,
                        'kinds' => [],
                    ];
                }
                $providers[$key]['kinds'][] = $label;
            }
        }
    }

    $regions = [];
    foreach (array_keys($byRegion) as $code) {
        $regions[$code] = wtwRegionName($code);
    }
    asort($regions);

    return [
        'region' => $region,
        'regionName' => $region ? wtwRegionName($region) : null,
        'requested' => $requested,
        'requestedName' => $requested ? wtwRegionName($requested) : null,
        'link' => $tmdbLink,
        'providers' => array_values($providers),
        'regions' => $regions,
    ];
}

/**
 * The player area when the site has nothing it may play: the backdrop and,
 * when there is one, a button that plays the trailer in place.
 */
function trailerStage(string $backdropPath, string $trailerKey, bool $hasProviders): string
{
    $bg = $backdropPath ? 'https://image.tmdb.org/t/p/w1280' . $backdropPath : '';
    ob_start(); ?>
    <div class="wtw-stage" id="wtwStage"<?php if ($bg): ?> style="background-image: url('<?php echo htmlspecialchars($bg); ?>');"<?php endif; ?>>
        <div class="wtw-stage-body">
            <?php if ($trailerKey): ?>
            <button type="button" class="wtw-trailer-btn" id="wtwTrailerBtn" data-key="<?php echo htmlspecialchars($trailerKey); ?>">
                <span class="wtw-trailer-icon"><i class="ph-fill ph-play"></i></span>
                <span>Play trailer</span>
            </button>
            <?php endif; ?>
            <p class="wtw-stage-note">
                Not streaming on ZEN<?php if ($hasProviders): ?> &middot; <a href="#where-to-watch">See where to watch</a><?php endif; ?>
            </p>
        </div>
    </div>
    <script>
    (function () {
        var btn = document.getElementById('wtwTrailerBtn');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var frame = document.createElement('iframe');
            frame.src = 'https://www.youtube.com/embed/' + encodeURIComponent(btn.dataset.key) + '?autoplay=1&rel=0&playsinline=1';
            frame.title = 'Trailer';
            frame.allow = 'autoplay; encrypted-media; fullscreen; picture-in-picture';
            frame.allowFullscreen = true;
            frame.className = 'wtw-trailer-frame';
            var stage = document.getElementById('wtwStage');
            stage.replaceChildren(frame);
            stage.classList.add('is-playing');
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

/**
 * The list of services, with a country picker. Under a title the site can
 * play, it only appears when some service carries the title too.
 */
function whereToWatchSection(array $wtw, bool $canPlay): string
{
    if ($canPlay && !$wtw['providers']) {
        return '';
    }
    $fallback = $wtw['region'] && $wtw['requested'] && $wtw['requested'] !== $wtw['region'];
    ob_start(); ?>
    <section class="wtw" id="where-to-watch" aria-labelledby="wtwTitle">
        <div class="wtw-head">
            <div>
                <h3 id="wtwTitle"><?php echo $canPlay ? 'Also available on' : 'Where to watch'; ?></h3>
                <p class="wtw-sub">
                    <?php if ($fallback): ?>
                        Not listed in <?php echo htmlspecialchars($wtw['requestedName']); ?> yet &middot; showing <?php echo htmlspecialchars($wtw['regionName']); ?>
                    <?php elseif ($wtw['region']): ?>
                        In <?php echo htmlspecialchars($wtw['regionName']); ?>
                    <?php endif; ?>
                </p>
            </div>
            <?php if (count($wtw['regions']) > 1): ?>
            <label class="wtw-region">
                <i class="ph ph-globe-simple" aria-hidden="true"></i>
                <span class="visually-hidden">Country</span>
                <select id="wtwRegion">
                    <?php foreach ($wtw['regions'] as $code => $name): ?>
                    <option value="<?php echo htmlspecialchars($code); ?>"<?php echo $code === $wtw['region'] ? ' selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php endif; ?>
        </div>

        <?php if ($wtw['providers']): ?>
        <div class="wtw-grid">
            <?php foreach ($wtw['providers'] as $p): ?>
            <a class="wtw-provider" href="<?php echo htmlspecialchars($p['url']); ?>" target="_blank" rel="noopener nofollow<?php echo $p['affiliate'] ? ' sponsored' : ''; ?>">
                <?php if ($p['logo']): ?>
                <img src="https://image.tmdb.org/t/p/w92<?php echo htmlspecialchars($p['logo']); ?>" alt="" width="44" height="44" loading="lazy">
                <?php else: ?>
                <span class="wtw-logo-blank"><i class="ph ph-television-simple"></i></span>
                <?php endif; ?>
                <span class="wtw-text">
                    <span class="wtw-name"><?php echo htmlspecialchars($p['name']); ?></span>
                    <span class="wtw-kinds"><?php echo htmlspecialchars(implode(' · ', $p['kinds'])); ?></span>
                </span>
                <i class="ph ph-arrow-square-out wtw-out" aria-hidden="true"></i>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="wtw-empty">
            <i class="ph ph-popcorn" aria-hidden="true"></i>
            No streaming, rental or purchase options are listed for this title yet. It may still be in cinemas or not released where you are.
        </p>
        <?php endif; ?>

        <p class="wtw-credit">
            Availability by <a href="https://www.justwatch.com" target="_blank" rel="noopener nofollow">JustWatch</a>, via TMDB.
            <a href="<?php echo htmlspecialchars($wtw['link']); ?>" target="_blank" rel="noopener nofollow">All options <i class="ph ph-arrow-square-out"></i></a>
        </p>
    </section>

    <script>
    (function () {
        var select = document.getElementById('wtwRegion');
        if (!select) return;
        select.addEventListener('change', function () {
            var url = new URL(location.href);
            url.searchParams.set('region', select.value);
            url.hash = 'where-to-watch';
            location.href = url.toString();
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

/** Styles for the stage and the section, printed once. */
function whereToWatchStyles(): string
{
    return <<<'CSS'
<style>
.wtw-stage { position: absolute; inset: 0; background: #07080c center / cover no-repeat; display: flex; align-items: center; justify-content: center; text-align: center; }
.wtw-stage::before { content: ""; position: absolute; inset: 0; background: radial-gradient(ellipse at center, rgba(5,6,10,.35) 0%, rgba(5,6,10,.8) 70%), linear-gradient(to top, rgba(5,6,10,.85), transparent 55%); }
.wtw-stage.is-playing::before { display: none; }
.wtw-stage-body { position: relative; display: flex; flex-direction: column; align-items: center; gap: 14px; padding: 16px; }
.wtw-trailer-btn { display: inline-flex; align-items: center; gap: 12px; padding: 8px 22px 8px 8px; border: 1px solid rgba(255,255,255,.18); border-radius: 999px; background: rgba(12,14,20,.55); color: #fff; font-weight: 600; font-size: 1rem; backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); cursor: pointer; transition: transform .15s, background .15s; }
.wtw-trailer-btn:hover, .wtw-trailer-btn:focus-visible { background: rgba(12,14,20,.75); transform: scale(1.03); }
.wtw-trailer-icon { width: 46px; height: 46px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: var(--primary, #e50914); font-size: 1.2rem; }
.wtw-trailer-icon i { margin-left: 3px; }
.wtw-stage-note { margin: 0; color: rgba(255,255,255,.78); font-size: .85rem; text-shadow: 0 1px 6px rgba(0,0,0,.6); }
.wtw-stage-note a { color: #fff; font-weight: 600; text-decoration: underline; text-underline-offset: 3px; }
.wtw-trailer-frame { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }

.wtw { margin: 22px 0 8px; padding: 18px; border: 1px solid rgba(255,255,255,.07); border-radius: 16px; background: rgba(255,255,255,.025); scroll-margin-top: 80px; }
.wtw-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
.wtw-head h3 { margin: 0; font-size: 1.1rem; font-weight: 700; color: #fff; }
.wtw-sub { margin: 3px 0 0; color: #8b8f99; font-size: .8rem; }
.wtw-region { position: relative; display: inline-flex; align-items: center; margin: 0; }
.wtw-region i { position: absolute; left: 11px; color: #8b8f99; pointer-events: none; }
.wtw-region select { appearance: none; -webkit-appearance: none; max-width: 190px; padding: 7px 30px 7px 32px; border: 1px solid rgba(255,255,255,.12); border-radius: 999px; color: #e6e7ea; font-size: .8rem; cursor: pointer;
    background: rgba(255,255,255,.04) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%238b8f99' fill='none' stroke-width='1.5'/%3E%3C/svg%3E") no-repeat right 12px center; }
.wtw-region select option { background: #14161c; }
.wtw-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px; }
.wtw-provider { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border: 1px solid rgba(255,255,255,.07); border-radius: 12px; background: rgba(255,255,255,.035); color: #fff; text-decoration: none; transition: background .15s, border-color .15s; min-width: 0; }
.wtw-provider:hover, .wtw-provider:focus-visible { background: rgba(255,255,255,.07); border-color: rgba(255,255,255,.16); color: #fff; }
.wtw-provider img, .wtw-logo-blank { width: 44px; height: 44px; border-radius: 10px; flex-shrink: 0; object-fit: cover; }
.wtw-logo-blank { display: inline-flex; align-items: center; justify-content: center; background: rgba(255,255,255,.08); font-size: 1.3rem; }
.wtw-text { display: flex; flex-direction: column; min-width: 0; flex: 1; }
.wtw-name { font-weight: 600; font-size: .92rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wtw-kinds { color: #8b8f99; font-size: .75rem; }
.wtw-out { color: #6b6f79; font-size: 1rem; flex-shrink: 0; }
.wtw-empty { display: flex; gap: 10px; align-items: flex-start; margin: 0; color: #a3a7b0; font-size: .88rem; line-height: 1.5; }
.wtw-empty i { font-size: 1.4rem; color: #6b6f79; flex-shrink: 0; }
.wtw-credit { margin: 14px 0 0; color: #6b6f79; font-size: .72rem; }
.wtw-credit a { color: #a3a7b0; text-decoration: underline; text-underline-offset: 2px; }
.wtw-credit a:last-child { margin-left: 6px; }
@media (max-width: 575.98px) {
    .wtw { padding: 14px; border-radius: 14px; }
    .wtw-grid { grid-template-columns: 1fr; gap: 8px; }
    .wtw-region select { max-width: 150px; }
    .wtw-trailer-btn { font-size: .92rem; padding-right: 18px; }
    .wtw-trailer-icon { width: 40px; height: 40px; }
}
</style>
CSS;
}
