<?php
/**
 * Content ingestion scraper.
 *
 * Discovers openly-licensed films on archive.org, verifies redistribution
 * rights, picks the highest-quality source file, matches the title to TMDB,
 * and writes a row into `ingestion_jobs` for the transcode stage to pick up.
 *
 * Usage:
 *   php v1/ingest/scrape.php --collection=feature_films --pages=2
 *   php v1/ingest/scrape.php --collection=film_noir --rows=25 --dry-run
 *
 * Options:
 *   --collection=NAME  archive.org collection to walk (default: feature_films)
 *   --rows=N           items per page, max 100 (default: 50)
 *   --pages=N          how many pages to walk (default: 1)
 *   --limit=N          stop after N newly-seen items
 *   --dry-run          print decisions, write nothing
 *   --refresh          re-evaluate items already in the table
 */

require_once __DIR__ . '/bootstrap.php';

$args       = ingest_args($argv);
$collection = (string) ($args['collection'] ?? 'feature_films');
$rows       = min((int) ($args['rows'] ?? 50), 100);
$pages      = max((int) ($args['pages'] ?? 1), 1);
$limit      = isset($args['limit']) ? (int) $args['limit'] : PHP_INT_MAX;
$dryRun     = isset($args['dry-run']);
$refresh    = isset($args['refresh']);

ingest_log("Scraping collection '{$collection}' ({$pages} page(s) x {$rows} rows)"
    . ($dryRun ? ' [DRY RUN]' : ''));

// --- verify the queue exists before doing any network work -------------------
try {
    $conn->query("SELECT 1 FROM ingestion_jobs LIMIT 1");
} catch (PDOException $e) {
    ingest_err("ingestion_jobs table is missing. Run: php v1/ingest/migrate.php up");
    exit(1);
}

$stats = [
    'seen' => 0, 'new' => 0, 'rejected' => 0,
    'matched' => 0, 'needs_review' => 0, 'skipped' => 0,
];

$runId = null;
if (!$dryRun) {
    $conn->prepare(
        "INSERT INTO ingestion_runs (source, collection) VALUES ('archive.org', ?)"
    )->execute([$collection]);
    $runId = (int) $conn->lastInsertId();
}

$existsStmt = $conn->prepare(
    "SELECT id FROM ingestion_jobs WHERE source = 'archive.org' AND source_identifier = ? LIMIT 1"
);

$insertStmt = $conn->prepare("
    INSERT INTO ingestion_jobs
        (source, source_identifier, source_title, source_year, source_creator, source_url,
         source_file_name, source_format, source_width, source_height, source_size,
         license_label, license_url,
         tmdb_id, tmdb_confidence, tmdb_title, media_type, quality,
         status, status_message)
    VALUES
        ('archive.org', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'movie', ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        source_title    = VALUES(source_title),
        source_creator  = VALUES(source_creator),
        source_url      = VALUES(source_url),
        source_size     = VALUES(source_size),
        license_label   = VALUES(license_label),
        license_url     = VALUES(license_url),
        tmdb_id         = VALUES(tmdb_id),
        tmdb_confidence = VALUES(tmdb_confidence),
        tmdb_title      = VALUES(tmdb_title),
        status          = VALUES(status),
        status_message  = VALUES(status_message),
        updated_at      = CURRENT_TIMESTAMP
");

for ($page = 1; $page <= $pages; $page++) {

    $docs = ia_search($collection, $rows, $page);
    if (empty($docs)) {
        ingest_warn("No results on page {$page}; stopping.");
        break;
    }
    ingest_log("Page {$page}: {$rows} requested, " . count($docs) . " returned");

    foreach ($docs as $doc) {

        if ($stats['new'] >= $limit) {
            ingest_log("Reached --limit={$limit}; stopping.");
            break 2;
        }

        $identifier = (string) ($doc['identifier'] ?? '');
        if ($identifier === '') {
            continue;
        }
        $stats['seen']++;

        // Already known?
        if (!$refresh) {
            $existsStmt->execute([$identifier]);
            if ($existsStmt->fetch()) {
                $stats['skipped']++;
                continue;
            }
        }

        ingest_sleep();
        $full = ia_metadata($identifier);
        if (!$full) {
            ingest_warn("{$identifier}: metadata unavailable");
            continue;
        }

        $meta  = $full['metadata'] ?? [];
        $title = (string) ($meta['title'] ?? $identifier);
        $year  = ia_extract_year($meta);

        // Creator, for attribution. CC-BY and CC-BY-SA require crediting the
        // author, so this has to be captured at discovery time.
        $creator = $meta['creator'] ?? null;
        if (is_array($creator)) {
            $creator = implode(', ', array_filter($creator, 'is_string'));
        }
        $creator = $creator ? mb_substr(trim((string) $creator), 0, 500) : null;

        // --- rights gate: runs before anything else ------------------------
        $license = ingest_classify_license($meta, INGEST_COMMERCIAL_USE);

        if ($license['decision'] === 'reject') {
            $stats['rejected']++;
            ingest_log(sprintf('  REJECT  %-45s %s', mb_substr($title, 0, 45), $license['reason']));
            if (!$dryRun) {
                $insertStmt->execute([
                    $identifier, $title, $year, $creator, '', null, null, null, null, null,
                    $license['label'], $license['url'],
                    null, null, null, null,
                    'rejected', $license['reason'],
                ]);
                $stats['new']++;
            }
            continue;
        }

        // --- pick the best available source file ---------------------------
        $file = ia_pick_best_video($full);
        if (!$file) {
            $stats['rejected']++;
            ingest_log(sprintf('  NOFILE  %-45s no usable video derivative', mb_substr($title, 0, 45)));
            continue;
        }

        $sourceUrl = ia_download_url($identifier, $file['name']);

        // --- catalogue identity --------------------------------------------
        ingest_sleep();
        $match = ingest_match_tmdb($title, $year);

        if ($license['decision'] === 'review') {
            $status  = 'needs_review';
            $message = 'Licence: ' . $license['reason'];
        } elseif ($match === null) {
            $status  = 'needs_review';
            $message = 'No confident TMDB match for: ' . $title;
        } elseif ($match['decision'] === 'needs_review') {
            $status  = 'needs_review';
            $message = sprintf('Low match confidence %.2f -> %s', $match['confidence'], $match['tmdb_title']);
        } else {
            $status  = 'matched';
            $message = sprintf('Matched %s (%.2f)', $match['tmdb_title'], $match['confidence']);
        }

        $stats[$status === 'matched' ? 'matched' : 'needs_review']++;

        ingest_log(sprintf(
            '  %-8s %-45s %-6s %-14s %s',
            $status === 'matched' ? 'OK' : 'REVIEW',
            mb_substr($title, 0, 45),
            $file['quality'],
            $license['label'],
            $message
        ));

        if (!$dryRun) {
            $insertStmt->execute([
                $identifier, $title, $year, $creator, $sourceUrl,
                $file['name'], $file['format'], $file['width'] ?: null,
                $file['height'] ?: null, $file['size'] ?: null,
                $license['label'], $license['url'],
                $match['tmdb_id'] ?? null,
                $match['confidence'] ?? null,
                $match['tmdb_title'] ?? null,
                $file['quality'],
                $status, $message,
            ]);
            $stats['new']++;
        }
    }
}

if ($runId) {
    $conn->prepare("
        UPDATE ingestion_runs
           SET items_seen = ?, items_new = ?, items_rejected = ?,
               items_matched = ?, items_needs_review = ?, finished_at = NOW()
         WHERE id = ?
    ")->execute([
        $stats['seen'], $stats['new'], $stats['rejected'],
        $stats['matched'], $stats['needs_review'], $runId,
    ]);
}

ingest_log('');
ingest_log(sprintf(
    'Done. seen=%d new=%d matched=%d needs_review=%d rejected=%d already_known=%d',
    $stats['seen'], $stats['new'], $stats['matched'],
    $stats['needs_review'], $stats['rejected'], $stats['skipped']
));

if ($stats['needs_review'] > 0) {
    ingest_log("Review queue: SELECT * FROM ingestion_jobs WHERE status = 'needs_review';");
}
