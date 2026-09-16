<?php
/**
 * Site-wide settings changed from the admin panel (Admin > Playback), and
 * the playback mode they control.
 *
 * Kept in the database rather than config.php because the live server's
 * disk is replaced on every deploy.
 *
 * Playback modes:
 *   discover  Trailers, "Where to watch" links to the services that carry
 *             each title, and full playback only for videos the site has
 *             the rights to (Admin > Playback > Titles you can play, and
 *             files published by the ingestion pipeline). The default.
 *   servers   The third-party streaming servers for every title, as the
 *             watch page worked before the modes existed.
 */

const PLAYBACK_DISCOVER = 'discover';
const PLAYBACK_SERVERS  = 'servers';

/** Every saved setting, read once per request. Empty until the first save creates the table. */
function siteSettings(bool $reload = false): array
{
    static $settings = null;
    if ($settings !== null && !$reload) {
        return $settings;
    }
    $settings = [];
    $conn = $GLOBALS['conn'] ?? null;
    if (!$conn instanceof PDO) {
        return $settings;
    }
    try {
        foreach ($conn->query("SELECT setting_key, setting_value FROM site_settings") as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (PDOException $e) {
        // No table yet: every setting has its default.
    }
    return $settings;
}

function siteSetting(string $key, ?string $default = null): ?string
{
    $settings = siteSettings();
    return isset($settings[$key]) && $settings[$key] !== '' ? $settings[$key] : $default;
}

function saveSiteSetting(PDO $conn, string $key, ?string $value, ?int $adminId = null): void
{
    $conn->exec("CREATE TABLE IF NOT EXISTS site_settings (
        setting_key   VARCHAR(64) NOT NULL PRIMARY KEY,
        setting_value TEXT NULL,
        updated_by    INT NULL,
        updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $conn->prepare("INSERT INTO site_settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)")
         ->execute([$key, $value, $adminId]);
    siteSettings(true);
}

/**
 * The mode this visitor gets. Admins can preview either mode for themselves
 * (Admin > Playback > Preview) without changing what everyone else sees.
 */
function playbackMode(): string
{
    $preview = $_SESSION['playback_preview'] ?? null;
    if (!empty($_SESSION['admin_id']) && in_array($preview, [PLAYBACK_DISCOVER, PLAYBACK_SERVERS], true)) {
        return $preview;
    }
    return siteSetting('playback_mode') === PLAYBACK_SERVERS ? PLAYBACK_SERVERS : PLAYBACK_DISCOVER;
}

/** Whether a URL is a video file the browser can play itself (rather than a page to embed). */
function isDirectVideoUrl(string $url): bool
{
    return (bool) preg_match('/\.(mp4|mkv|webm|m3u8)(\?|$)/i', $url);
}

/**
 * Videos the site has the rights to play for a movie or episode, as watch
 * page servers ({name, url}):
 *   - titles added under Admin > Playback > Titles you can play (media_sources)
 *   - video files from Download Links and the ingestion pipeline (media_downloads)
 */
function licensedSources(PDO $conn, int $tmdbId, string $type, int $season, int $episode): array
{
    $type = $type === 'tv' ? 'tv' : 'movie';
    // Movies have no season or episode (stored as 0 or NULL).
    $episodeSql = $type === 'tv' ? 'AND season = ? AND episode = ?' : '';
    $params = $type === 'tv' ? [$tmdbId, $type, $season, $episode] : [$tmdbId, $type];

    $sources = [];
    try {
        $stmt = $conn->prepare("SELECT video_url, is_embed FROM media_sources
                                 WHERE tmdb_id = ? AND media_type = ? $episodeSql ORDER BY id");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $i => $row) {
            if (empty($row['video_url'])) continue;
            $sources[] = ['name' => 'ZEN' . ($i ? ' ' . ($i + 1) : ''), 'url' => $row['video_url']];
        }
    } catch (PDOException $e) {
        error_log('licensedSources media_sources: ' . $e->getMessage());
    }
    try {
        $stmt = $conn->prepare("SELECT quality, download_url FROM media_downloads
                                 WHERE tmdb_id = ? AND media_type = ? $episodeSql AND is_active = 1
                                 ORDER BY CAST(quality AS UNSIGNED) DESC");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isDirectVideoUrl((string) $row['download_url'])) {
                $sources[] = ['name' => 'ZEN ' . $row['quality'], 'url' => $row['download_url']];
            }
        }
    } catch (PDOException $e) {
        error_log('licensedSources media_downloads: ' . $e->getMessage());
    }
    return $sources;
}

/**
 * Which of several titles have something the site may play, as a set of
 * "type:id" keys. One query for a whole row of cards.
 *
 * @param array $titles list of [type, tmdbId]
 */
function licensedTitleKeys(PDO $conn, array $titles): array
{
    $ids = array_values(array_unique(array_map(fn($t) => (int) $t[1], $titles)));
    if (!$ids) return [];
    $in = implode(',', array_fill(0, count($ids), '?'));
    $keys = [];
    try {
        $stmt = $conn->prepare("SELECT media_type, tmdb_id FROM media_sources WHERE tmdb_id IN ($in)
                                 UNION
                                SELECT media_type, tmdb_id FROM media_downloads
                                 WHERE tmdb_id IN ($in) AND is_active = 1
                                   AND download_url REGEXP '\\\\.(mp4|mkv|webm|m3u8)(\\\\?|$)'");
        $stmt->execute(array_merge($ids, $ids));
        foreach ($stmt->fetchAll(PDO::FETCH_NUM) as [$type, $id]) {
            $keys[$type . ':' . (int) $id] = true;
        }
    } catch (PDOException $e) {
        error_log('licensedTitleKeys: ' . $e->getMessage());
    }
    return $keys;
}

/**
 * Label and icon for a title's main "Play" button. In Discover mode a title
 * with nothing the site may play leads to its trailer and where to watch it,
 * so the button says so.
 */
function playButton(bool $licensed, string $playLabel = 'Play'): array
{
    if (playbackMode() === PLAYBACK_SERVERS || $licensed) {
        return ['label' => $playLabel, 'icon' => 'ph-fill ph-play'];
    }
    return ['label' => 'Where to watch', 'icon' => 'ph ph-television-simple'];
}
