<?php
/**
 * Minimal Internet Archive API client.
 *
 * Two endpoints are used:
 *   - advancedsearch.php  -> discover items in a collection
 *   - /metadata/{id}      -> full item metadata, including the file list
 */

const IA_USER_AGENT = 'MasterpieceMovie-Ingest/1.0 (+catalogue ingestion; contact site admin)';

/** Format keywords ranked best-first. Anything unlisted is ignored. */
const IA_VIDEO_FORMAT_RANK = [
    'h.264'   => 100,
    'mpeg4'   => 90,
    'matroska' => 80,
    'mpeg2'   => 60,
    'quicktime' => 50,
    'ogg video' => 30,
    'windows media' => 20,
];

function ia_http_get(string $url, int $timeout = 20): ?string
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_USERAGENT      => IA_USER_AGENT,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    ingest_apply_tls($ch);

    $body = curl_exec($ch);

    if (curl_errno($ch)) {
        ingest_err('archive.org request failed: ' . curl_error($ch));
        curl_close($ch);
        return null;
    }

    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code >= 400) {
        ingest_err("archive.org returned HTTP {$code} for {$url}");
        return null;
    }

    return is_string($body) ? $body : null;
}

/**
 * Search archive.org for movie items.
 *
 * @return array List of doc arrays (identifier, title, year, licenseurl, ...)
 */
function ia_search(string $collection, int $rows = 50, int $page = 1): array
{
    $query = sprintf('collection:(%s) AND mediatype:(movies)', $collection);

    $params = [
        'q'      => $query,
        'rows'   => $rows,
        'page'   => $page,
        'output' => 'json',
        'sort[]' => 'downloads desc',
    ];

    $fields = ['identifier', 'title', 'year', 'date', 'licenseurl', 'collection', 'downloads'];

    $url = 'https://archive.org/advancedsearch.php?' . http_build_query($params);
    foreach ($fields as $f) {
        $url .= '&fl%5B%5D=' . urlencode($f);
    }

    $body = ia_http_get($url);
    if ($body === null) {
        return [];
    }

    $data = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        ingest_err('archive.org search returned invalid JSON');
        return [];
    }

    return $data['response']['docs'] ?? [];
}

/** Fetch the full metadata document for one item. */
function ia_metadata(string $identifier): ?array
{
    $body = ia_http_get('https://archive.org/metadata/' . rawurlencode($identifier));
    if ($body === null) {
        return null;
    }

    $data = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($data['metadata'])) {
        return null;
    }

    return $data;
}

function ia_format_rank(string $format): int
{
    $format = strtolower($format);
    foreach (IA_VIDEO_FORMAT_RANK as $needle => $rank) {
        if (strpos($format, $needle) !== false) {
            return $rank;
        }
    }
    return 0;
}

/** Map a pixel height onto the site's quality ladder. */
function ia_quality_from_height(?int $height): string
{
    if (!$height) {
        return 'unknown';
    }
    if ($height >= 1900) return '2160p';
    if ($height >= 1000) return '1080p';
    if ($height >= 700)  return '720p';
    if ($height >= 460)  return '480p';
    return '360p';
}

/**
 * Choose the highest-quality usable video file from an item's file list.
 *
 * @return array|null {name, format, size, width, height, length, quality}
 */
function ia_pick_best_video(array $metadata): ?array
{
    $files = $metadata['files'] ?? [];
    $best = null;
    $bestScore = -1;

    foreach ($files as $file) {
        $format = (string) ($file['format'] ?? '');
        $rank = ia_format_rank($format);
        if ($rank === 0) {
            continue; // not a video format we accept
        }

        $height = isset($file['height']) ? (int) $file['height'] : 0;
        $width  = isset($file['width'])  ? (int) $file['width']  : 0;
        $size   = isset($file['size'])   ? (int) $file['size']   : 0;

        // Rank dominates, then resolution, then file size as a tiebreak.
        $score = ($rank * 1000000) + ($height * 100) + min((int) ($size / 1048576), 99);

        if ($score > $bestScore) {
            $bestScore = $score;
            $best = [
                'name'    => (string) ($file['name'] ?? ''),
                'format'  => $format,
                'size'    => $size,
                'width'   => $width,
                'height'  => $height,
                'length'  => $file['length'] ?? null,
                'quality' => ia_quality_from_height($height ?: null),
            ];
        }
    }

    return ($best && $best['name'] !== '') ? $best : null;
}

function ia_download_url(string $identifier, string $fileName): string
{
    return 'https://archive.org/download/'
        . rawurlencode($identifier) . '/'
        . str_replace('%2F', '/', rawurlencode($fileName));
}

/** Pull a usable release year out of IA's inconsistent year/date fields. */
function ia_extract_year(array $meta): ?int
{
    foreach (['year', 'date', 'publicdate'] as $key) {
        $val = $meta[$key] ?? null;
        if (is_array($val)) {
            $val = $val[0] ?? null;
        }
        if ($val && preg_match('/(1[89]\d{2}|20\d{2})/', (string) $val, $m)) {
            return (int) $m[1];
        }
    }
    return null;
}
