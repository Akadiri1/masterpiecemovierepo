<?php
/**
 * Free films: full-length films the site may play because their rights
 * holder published them for free viewing -- official uploads on YouTube
 * (played through YouTube's embedded player, so the owner keeps the views
 * and ad revenue) and public-domain or openly licensed films on archive.org.
 *
 * Admin > Free films pastes a link; this file reads the video's details,
 * judges what can be told about its rights, suggests the TMDB title it
 * belongs to, and saves it to media_sources, which the watch page plays
 * (licensedSources() in site_settings.php).
 */

require_once __DIR__ . '/tls.php';
require_once __DIR__ . '/site_settings.php';
require_once __DIR__ . '/../ingest/lib/license.php';      // archive.org licence rules
require_once __DIR__ . '/../ingest/lib/tmdb_matcher.php'; // title similarity

const FREE_FILM_MIN_MINUTES = 40; // shorter than this is probably a trailer or a clip

function ensureMediaSourcesTable(PDO $conn): void
{
    $conn->exec("CREATE TABLE IF NOT EXISTS media_sources (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tmdb_id INT NOT NULL,
        media_type ENUM('movie','tv') NOT NULL DEFAULT 'movie',
        season INT DEFAULT 0,
        episode INT DEFAULT 0,
        video_url TEXT NOT NULL,
        is_embed TINYINT(1) DEFAULT 0,
        INDEX idx_media_sources_lookup (tmdb_id, media_type, season, episode)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $columns = $conn->query("SHOW COLUMNS FROM media_sources")->fetchAll(PDO::FETCH_COLUMN);
    $add = [
        'rights_note'   => "VARCHAR(255) NULL",
        'created_at'    => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP",
        'source'        => "VARCHAR(20) NULL",     // youtube, archive, link
        'source_url'    => "VARCHAR(500) NULL",    // the link that was pasted
        'source_title'  => "VARCHAR(255) NULL",
        'source_owner'  => "VARCHAR(255) NULL",    // YouTube channel or archive.org creator
        'checked_at'    => "DATETIME NULL",
        'check_status'  => "VARCHAR(20) NULL",     // ok, unavailable
    ];
    foreach ($add as $column => $definition) {
        if (!in_array($column, $columns, true)) {
            $conn->exec("ALTER TABLE media_sources ADD COLUMN `$column` $definition");
        }
    }
}

/** Recognises a YouTube or archive.org link. */
function freeFilmParseLink(string $link): ?array
{
    $link = trim($link);
    if (!preg_match('~^https?://~i', $link)) {
        $link = 'https://' . $link;
    }
    $host = strtolower((string) parse_url($link, PHP_URL_HOST));
    $host = preg_replace('/^(www|m|music)\./', '', $host);
    $path = (string) parse_url($link, PHP_URL_PATH);
    parse_str((string) parse_url($link, PHP_URL_QUERY), $query);

    if (in_array($host, ['youtube.com', 'youtube-nocookie.com', 'youtu.be'], true)) {
        $id = null;
        if ($host === 'youtu.be') {
            $id = trim($path, '/');
        } elseif (!empty($query['v'])) {
            $id = $query['v'];
        } elseif (preg_match('~^/(embed|shorts|live|v)/([^/?#]+)~', $path, $m)) {
            $id = $m[2];
        }
        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? ['source' => 'youtube', 'id' => $id] : null;
    }

    if ($host === 'archive.org' && preg_match('~^/(details|embed|download)/([A-Za-z0-9._-]+)~', $path, $m)) {
        return ['source' => 'archive', 'id' => $m[2]];
    }
    return null;
}

/** GET a URL. Returns [HTTP status (0 when unreachable), decoded JSON or null]. */
function freeFilmFetchJson(string $url, int $timeout = 15): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_USERAGENT      => 'ZEN-Admin/1.0 (free film lookup)',
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    app_apply_tls($ch);
    $body = curl_exec($ch);
    $status = curl_errno($ch) ? 0 : (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $data = is_string($body) ? json_decode($body, true) : null;
    return [$status, is_array($data) ? $data : null];
}

/** "PT1H52M3S" -> 112 */
function freeFilmIsoMinutes(string $duration): ?int
{
    if (!preg_match('/^P(?:(\d+)D)?T?(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $duration, $m)) {
        return null;
    }
    $seconds = ((int) ($m[1] ?? 0)) * 86400 + ((int) ($m[2] ?? 0)) * 3600 + ((int) ($m[3] ?? 0)) * 60 + (int) ($m[4] ?? 0);
    return (int) round($seconds / 60);
}

/**
 * Strips what uploaders add around a film's name, so it can be searched:
 * "The Wedding Party (2016) | Full Movie | Official HD" -> "The Wedding Party (2016)".
 */
function freeFilmSearchTitle(string $title): string
{
    $t = preg_split('/\s[|•]\s|\s\|\s?|\s\/\/\s/u', $title)[0];
    $noise = '/\b(official\s+(full\s+)?(movie|film|video)|full\s+(hd\s+)?(movie|film)s?|complete\s+(movie|film)|'
           . 'latest\s+\d{4}\s+\w+\s+movies?|nollywood\s+movies?|nigerian\s+movies?|with\s+english\s+subtitles|'
           . 'english\s+subtitles|hd|4k|8k|uhd|hdr|\d{2,3}\s?fps|1080p|720p|remastered|restored)\b/iu';
    $t = preg_replace($noise, ' ', $t);
    // "Big Buck Bunny - Official Blender Foundation Short Film": the part after
    // the dash describes the upload, not the film.
    $t = preg_replace('/\s[-–—]\s.*\b(official|short film|trailer|studio|foundation|channel|presents)\b.*$/iu', '', $t);
    $t = preg_replace('/\s[-–—:]\s*$/u', '', trim(preg_replace('/\s+/u', ' ', $t)));
    return trim($t, " -–—:|");
}

/** Readable name for an archive.org licence label from ingest_classify_license(). */
function freeFilmLicenceName(string $label): string
{
    $names = [
        'public_domain' => 'Public domain',
        'cc0' => 'CC0 (public domain)',
        'claimed_public_domain' => 'Said to be public domain',
        'collection_hint' => 'No licence stated',
        'rights_text_only' => 'Rights described in text only',
        'unrecognised' => 'Unrecognised licence',
        'cc-nd' => 'Creative Commons, no derivatives',
    ];
    if (isset($names[$label])) {
        return $names[$label];
    }
    return strpos($label, 'cc-') === 0 ? 'Creative Commons ' . strtoupper(substr($label, 3)) : ucfirst(str_replace('_', ' ', $label));
}

/**
 * Suggests TMDB titles for a video title.
 *
 * @return array list of {tmdb_id, type, title, year, poster, score}
 */
function freeFilmCandidates(string $title, ?int $year, string $type = 'movie'): array
{
    $type = $type === 'tv' ? 'tv' : 'movie';
    $query = freeFilmSearchTitle($title);
    // The cleaned title first; failing that, just the part before a colon or dash.
    $results = [];
    foreach (array_unique(array_filter([
        ingest_normalise_title($query) ?: $query,
        trim(preg_split('/\s*[:–—-]\s/u', $query)[0]),
    ])) as $attempt) {
        $results = fetchTmdbApi("search/$type", ['query' => $attempt, 'include_adult' => 'false'], 86400)['results'] ?? [];
        if ($results) {
            break;
        }
    }

    $candidates = [];
    foreach (array_slice($results, 0, 12) as $r) {
        $name = (string) ($r['title'] ?? $r['name'] ?? '');
        $date = $r['release_date'] ?? $r['first_air_date'] ?? null;
        $score = max(ingest_title_similarity($query, $name), ingest_title_similarity($query, (string) ($r['original_title'] ?? $r['original_name'] ?? '')));
        if ($year) {
            $score = $score * 0.75 + ingest_year_score($year, $date) * 0.25;
        }
        // Popularity breaks ties between remakes that share a name.
        $score += min((float) ($r['popularity'] ?? 0), 100) / 10000;
        $candidates[] = [
            'tmdb_id' => (int) $r['id'],
            'type'    => $type,
            'title'   => $name,
            'year'    => $date ? substr($date, 0, 4) : '',
            'poster'  => $r['poster_path'] ?? '',
            'score'   => round($score, 3),
        ];
    }
    usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);
    return array_slice($candidates, 0, 6);
}

/**
 * Reads a pasted link.
 *
 * @return array {ok, error, source, source_id, link, title, owner, owner_url, thumbnail, year,
 *                minutes, embed_url, watch_url, rights: {status: ok|check|blocked, label, note},
 *                warnings: string[]}
 */
function freeFilmLookup(string $link): array
{
    $result = [
        'ok' => false, 'error' => '', 'source' => '', 'source_id' => '', 'link' => trim($link),
        'title' => '', 'owner' => '', 'owner_url' => '', 'thumbnail' => '', 'year' => null,
        'minutes' => null, 'embed_url' => '', 'watch_url' => '',
        'rights' => ['status' => 'check', 'label' => '', 'note' => ''], 'warnings' => [],
    ];

    $parsed = freeFilmParseLink($link);
    if (!$parsed) {
        $result['error'] = "That isn't a YouTube or archive.org video link.";
        return $result;
    }
    $result['source'] = $parsed['source'];
    $result['source_id'] = $parsed['id'];

    if ($parsed['source'] === 'youtube') {
        $watch = 'https://www.youtube.com/watch?v=' . $parsed['id'];
        [$status, $data] = freeFilmFetchJson('https://www.youtube.com/oembed?format=json&url=' . rawurlencode($watch));
        if ($status === 401 || $status === 403) {
            $result['error'] = "The owner doesn't allow this video to play on other sites (or it's private), so it can't play on ZEN.";
            return $result;
        }
        if ($status === 404 || $status === 400) {
            $result['error'] = 'YouTube says this video doesn\'t exist or was removed.';
            return $result;
        }
        if ($status !== 200 || !$data) {
            $result['error'] = "Couldn't reach YouTube to check the video. Please try again.";
            return $result;
        }
        $result['title'] = (string) ($data['title'] ?? '');
        $result['owner'] = (string) ($data['author_name'] ?? '');
        $result['owner_url'] = (string) ($data['author_url'] ?? '');
        $result['thumbnail'] = 'https://i.ytimg.com/vi/' . $parsed['id'] . '/hqdefault.jpg';
        $result['watch_url'] = $watch;
        $result['embed_url'] = 'https://www.youtube-nocookie.com/embed/' . $parsed['id'] . '?rel=0';
        if (preg_match('/\((1[89]\d{2}|20\d{2})\)/', $result['title'], $m)) {
            $result['year'] = (int) $m[1];
        }

        // Length needs the YouTube Data API, which needs a key.
        $apiKey = getenv('YOUTUBE_API_KEY') ?: (defined('YOUTUBE_API_KEY') ? YOUTUBE_API_KEY : '');
        if ($apiKey) {
            [$status, $data] = freeFilmFetchJson('https://www.googleapis.com/youtube/v3/videos?part=contentDetails,status&id='
                . rawurlencode($parsed['id']) . '&key=' . rawurlencode($apiKey));
            $item = $data['items'][0] ?? null;
            if ($item) {
                $result['minutes'] = freeFilmIsoMinutes((string) ($item['contentDetails']['duration'] ?? ''));
                if (isset($item['status']['embeddable']) && !$item['status']['embeddable']) {
                    $result['error'] = "The owner doesn't allow this video to play on other sites.";
                    return $result;
                }
            }
        } else {
            $result['warnings'][] = "Check it's the full film, not a trailer or a clip. (Add a YOUTUBE_API_KEY to have the length checked for you.)";
        }

        $result['rights'] = [
            'status' => 'check',
            'label'  => 'Official upload?',
            'note'   => 'Official upload by ' . ($result['owner'] ?: 'the rights holder') . ' on YouTube (played in the YouTube player)',
        ];
        $result['warnings'][] = "Only add it if {$result['owner']} is the film's studio, distributor or filmmaker. Films re-uploaded by other channels are pirated, even on YouTube.";
    } else {
        [$status, $data] = freeFilmFetchJson('https://archive.org/metadata/' . rawurlencode($parsed['id']), 20);
        if ($status !== 200) {
            $result['error'] = "Couldn't reach archive.org to check the film. Please try again.";
            return $result;
        }
        if (empty($data['metadata'])) {
            $result['error'] = 'archive.org has no item with that link.';
            return $result;
        }
        $meta = $data['metadata'];
        $first = fn($v) => is_array($v) ? (string) ($v[0] ?? '') : (string) $v;
        if (($meta['mediatype'] ?? '') !== 'movies') {
            $result['error'] = "That archive.org item isn't a film (it's listed as " . ($first($meta['mediatype'] ?? 'something else')) . ').';
            return $result;
        }
        $result['title'] = $first($meta['title'] ?? $parsed['id']);
        $result['owner'] = $first($meta['creator'] ?? '');
        $result['owner_url'] = 'https://archive.org/details/' . $parsed['id'];
        $result['thumbnail'] = 'https://archive.org/services/img/' . rawurlencode($parsed['id']);
        $result['watch_url'] = 'https://archive.org/details/' . $parsed['id'];
        $result['embed_url'] = 'https://archive.org/embed/' . rawurlencode($parsed['id']);
        foreach (['year', 'date'] as $key) {
            if (preg_match('/(1[89]\d{2}|20\d{2})/', $first($meta[$key] ?? ''), $m)) {
                $result['year'] = (int) $m[1];
                break;
            }
        }
        $longest = 0;
        foreach ($data['files'] ?? [] as $file) {
            if (!preg_match('/mpeg4|h\.264|matroska|ogg video|quicktime|mpeg2/i', (string) ($file['format'] ?? ''))) {
                continue;
            }
            $length = (string) ($file['length'] ?? '');
            $seconds = strpos($length, ':') !== false
                ? array_reduce(explode(':', $length), fn($carry, $part) => $carry * 60 + (float) $part, 0)
                : (float) $length;
            $longest = max($longest, $seconds);
        }
        if ($longest > 0) {
            $result['minutes'] = (int) round($longest / 60);
        } elseif (empty($data['files'])) {
            $result['warnings'][] = 'archive.org lists no video files for this item, so it may not play.';
        }

        $licence = ingest_classify_license($meta, true);
        $status = ['accept' => 'ok', 'review' => 'check', 'reject' => 'blocked'][$licence['decision']];
        $licenceName = freeFilmLicenceName($licence['label']);
        $result['rights'] = [
            'status' => $status,
            'label'  => $licenceName,
            'note'   => $status === 'ok' ? "$licenceName · archive.org" : 'Public domain (checked by admin) · archive.org',
        ];
        if ($status === 'blocked') {
            $result['error'] = "Its licence doesn't allow showing it on ZEN: " . $licence['reason'] . '.';
            return $result;
        }
        if ($status === 'check') {
            $result['warnings'][] = 'archive.org has no clear licence for this film (' . $licence['reason'] . '). Only add it if you know it\'s in the public domain.';
        }
    }

    if ($result['minutes'] !== null && $result['minutes'] < FREE_FILM_MIN_MINUTES) {
        $result['warnings'][] = "It's only {$result['minutes']} minutes long, so it may be a trailer, a clip or a short film.";
    }
    $result['ok'] = true;
    return $result;
}

/** Whether a saved free film still plays (the video exists and allows embedding). */
function freeFilmStillAvailable(string $sourceUrl): ?bool
{
    $parsed = freeFilmParseLink($sourceUrl);
    if (!$parsed) {
        return null;
    }
    if ($parsed['source'] === 'youtube') {
        [$status] = freeFilmFetchJson('https://www.youtube.com/oembed?format=json&url='
            . rawurlencode('https://www.youtube.com/watch?v=' . $parsed['id']), 10);
        return $status === 0 ? null : $status === 200;
    }
    [$status, $data] = freeFilmFetchJson('https://archive.org/metadata/' . rawurlencode($parsed['id']) . '/metadata', 10);
    return $status === 0 ? null : !empty($data['result']);
}
