<?php
/**
 * Which streaming hosts are answering at all.
 *
 * The embed servers run inside a cross-origin iframe, which the browser seals
 * off completely: the page cannot see whether what loaded is a film or an
 * error. What it can do is ask this side, which is not bound by that rule.
 *
 * This only answers "is the host reachable" — a DNS failure, a refused
 * connection or a 5xx. A host that answers but has nothing for this particular
 * title still looks healthy from here, so the player keeps its own checks too.
 *
 * Results are shared by everyone and cached, because these hosts go away for
 * hours at a time, not for single requests.
 */

const SERVER_HEALTH_TTL = 600;      // how long a verdict is trusted, seconds
const SERVER_HEALTH_TIMEOUT = 9;    // per host, seconds — generous, so that a
                                    // host that is merely slow is not called dead

/**
 * The hosts worth checking: the embed servers, and only those.
 *
 * Kept here rather than read from the page, so a request can never make this
 * server reach out to an address of its choosing.
 *
 * The site's own licensed files are deliberately absent. A <video> element
 * reports its own failures exactly, and archive.org is genuinely slow — it can
 * take longer to answer than any sensible timeout here — so judging it from
 * this side would eventually condemn a source that plays perfectly well. The
 * browser is the better judge wherever it is allowed to look.
 *
 * Adding a streaming server in views/watch.php? Add its host here too, or it
 * simply goes unchecked (the player then falls back to its own timeout).
 */
function serverHealthHosts(): array
{
    return [
        'vidsrc.in',
        'vidlink.pro',
        'autoembed.co',
        'vidbinge.com',
        'player.smashy.stream',
    ];
}

function serverHealthCacheFile(): string
{
    return dirname(__DIR__) . '/cache/server-health.json';
}

function serverHealthRead(): array
{
    $file = serverHealthCacheFile();
    if (!is_readable($file)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function serverHealthWrite(array $data): void
{
    $file = serverHealthCacheFile();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    // Written aside and moved into place, so a reader never sees half a file.
    $temp = $file . '.' . getmypid() . '.tmp';
    if (@file_put_contents($temp, json_encode($data)) !== false) {
        @rename($temp, $file);
    }
}

/**
 * Asks the hosts themselves, in parallel. Anything that answers with an HTTP
 * status below 500 counts as up: 403 and 404 mean the host is alive and simply
 * does not want that path, which is not the failure being looked for here.
 */
function serverHealthProbe(array $hosts): array
{
    if (!$hosts || !function_exists('curl_multi_init')) {
        return [];
    }

    require_once __DIR__ . '/tls.php';

    $multi = curl_multi_init();
    $handles = [];

    foreach ($hosts as $host) {
        $ch = curl_init('https://' . $host . '/');
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => SERVER_HEALTH_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ZenHealthCheck/1.0)',
        ]);
        if (function_exists('app_apply_tls')) {
            app_apply_tls($ch);
        }
        curl_multi_add_handle($multi, $ch);
        $handles[$host] = $ch;
    }

    do {
        $status = curl_multi_exec($multi, $running);
        if ($running) {
            curl_multi_select($multi, 1.0);
        }
    } while ($running && $status === CURLM_OK);

    $now = time();
    $results = [];
    foreach ($handles as $host => $ch) {
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $results[$host] = ['up' => $code > 0 && $code < 500, 'code' => $code, 'checked' => $now];
        curl_multi_remove_handle($multi, $ch);
        curl_close($ch);
    }
    curl_multi_close($multi);

    return $results;
}

/**
 * host => ['up' => bool, 'checked' => timestamp]. Hosts whose verdict is still
 * fresh are answered from the cache; the rest are probed and stored.
 */
function serverHealthStatus(): array
{
    $cached = serverHealthRead();
    $now = time();

    $stale = [];
    foreach (serverHealthHosts() as $host) {
        if (!isset($cached[$host]['checked']) || ($now - (int) $cached[$host]['checked']) > SERVER_HEALTH_TTL) {
            $stale[] = $host;
        }
    }

    if ($stale) {
        $fresh = serverHealthProbe($stale);
        if ($fresh) {
            serverHealthWrite(array_merge($cached, $fresh));
            $cached = array_merge($cached, $fresh);
        }
    }

    $out = [];
    foreach (serverHealthHosts() as $host) {
        if (isset($cached[$host]['up'])) {
            $out[$host] = ['up' => (bool) $cached[$host]['up'], 'checked' => (int) ($cached[$host]['checked'] ?? 0)];
        }
    }
    return $out;
}
