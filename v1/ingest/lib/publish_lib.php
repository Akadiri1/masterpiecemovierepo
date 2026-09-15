<?php
/**
 * Helpers for the publish worker.
 *
 * Kept out of publish.php so they can be exercised independently of a full
 * publish run, which needs ffmpeg, credentials and multi-gigabyte downloads.
 */

/**
 * Download a URL to disk, streamed, resuming a partial file if present.
 *
 * @return array{ok:bool,size:int,error:?string}
 */
function publish_download(string $url, string $destination): array
{
    $existing = is_file($destination) ? filesize($destination) : 0;
    $fh = fopen($destination, $existing > 0 ? 'ab' : 'wb');
    if (!$fh) {
        return ['ok' => false, 'size' => 0, 'error' => "Cannot write {$destination}"];
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_FILE           => $fh,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 0,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_USERAGENT      => defined('IA_USER_AGENT') ? IA_USER_AGENT : 'MasterpieceMovie-Ingest/1.0',
        CURLOPT_NOPROGRESS     => false,
        CURLOPT_PROGRESSFUNCTION => function ($handle, $dlTotal, $dlNow) {
            static $lastTick = 0;
            if ($dlTotal > 0 && time() - $lastTick >= 5) {
                $lastTick = time();
                printf("    %5.1f%%  %s / %s\n",
                    ($dlNow / $dlTotal) * 100,
                    ff_human_size($dlNow), ff_human_size($dlTotal));
            }
            return 0;
        },
    ]);
    if ($existing > 0) {
        curl_setopt($ch, CURLOPT_RESUME_FROM, $existing);
    }
    if (function_exists('ingest_apply_tls')) {
        ingest_apply_tls($ch);
    }

    curl_exec($ch);
    $errno  = curl_errno($ch);
    $err    = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fh);

    if ($errno) {
        return ['ok' => false, 'size' => 0, 'error' => $err];
    }
    // 206 is a successful resumed range request.
    if ($status !== 200 && $status !== 206) {
        return ['ok' => false, 'size' => 0, 'error' => "HTTP {$status}"];
    }

    return ['ok' => true, 'size' => (int) filesize($destination), 'error' => null];
}

/**
 * Insert or update the media_downloads row for one rendition.
 *
 * Keyed on (tmdb_id, movie, quality) with no season/episode, so re-publishing
 * a title replaces its links instead of stacking duplicates on the download
 * page.
 *
 * @return int The media_downloads row id.
 */
function publish_upsert_download(PDO $conn, array $d): int
{
    $find = $conn->prepare(
        "SELECT id FROM media_downloads
          WHERE tmdb_id = ? AND media_type = 'movie' AND quality = ?
            AND season IS NULL AND episode IS NULL
          LIMIT 1"
    );
    $find->execute([$d['tmdb_id'], $d['quality']]);
    $existingId = $find->fetchColumn();

    if ($existingId) {
        $conn->prepare(
            "UPDATE media_downloads
                SET file_size = ?, file_name = ?, download_url = ?, is_active = 1,
                    license_label = ?, license_url = ?, source_attribution = ?
              WHERE id = ?"
        )->execute([
            $d['file_size'], $d['file_name'], $d['download_url'],
            $d['license'], $d['license_url'], $d['attribution'], $existingId,
        ]);
        return (int) $existingId;
    }

    $conn->prepare(
        "INSERT INTO media_downloads
            (tmdb_id, media_type, season, episode, quality, language,
             file_size, file_name, download_url, is_active,
             license_label, license_url, source_attribution)
         VALUES (?, 'movie', NULL, NULL, ?, 'English', ?, ?, ?, 1, ?, ?, ?)"
    )->execute([
        $d['tmdb_id'], $d['quality'], $d['file_size'], $d['file_name'],
        $d['download_url'], $d['license'], $d['license_url'], $d['attribution'],
    ]);

    return (int) $conn->lastInsertId();
}
