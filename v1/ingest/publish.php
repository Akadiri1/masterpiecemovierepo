<?php
/**
 * Publish worker.
 *
 * Takes jobs that passed review (status = 'matched'), downloads the source,
 * transcodes it into a quality ladder, uploads each rendition to Wasabi, and
 * inserts rows into `media_downloads` so /download serves them.
 *
 * Usage:
 *   php v1/ingest/publish.php --limit=1
 *   php v1/ingest/publish.php --job=12
 *   php v1/ingest/publish.php --limit=3 --quiet
 *
 * Options:
 *   --limit=N     process at most N jobs (default 1)
 *   --job=ID      process one specific job, whatever its status
 *   --dry-run     show the plan, transcode nothing, upload nothing
 *   --keep-temp   leave the staged files on disk for inspection
 *   --quiet       suppress ffmpeg's per-frame progress output
 */

require_once __DIR__ . '/bootstrap.php';

$args      = ingest_args($argv);
$limit     = max((int) ($args['limit'] ?? 1), 1);
$onlyJob   = isset($args['job']) ? (int) $args['job'] : null;
$dryRun    = isset($args['dry-run']);
$keepTemp  = isset($args['keep-temp']);
$quiet     = isset($args['quiet']);

// ---------------------------------------------------------------- preflight
$cfg = s3_config();
if (!$dryRun && !$cfg['ready']) {
    ingest_err('Storage is not configured. Missing: ' . implode(', ', $cfg['missing']));
    ingest_err('Fill these in at the bottom of .env/config.php, then run: php v1/ingest/doctor.php');
    exit(1);
}

// A dry run only prints the plan, so it stays useful before ffmpeg is
// installed. A real run cannot proceed without it.
foreach (['ffmpeg', 'ffprobe'] as $bin) {
    [$found, $detail] = ff_check($bin);
    if ($found) {
        continue;
    }
    if ($dryRun) {
        ingest_warn("{$bin} not available ({$detail}) - fine for --dry-run");
    } else {
        ingest_err("{$bin} is not available: {$detail}");
        ingest_err('Install ffmpeg, or set FFMPEG_BIN / FFPROBE_BIN in .env/config.php');
        exit(1);
    }
}

$workDir = (defined('INGEST_WORK_DIR') && INGEST_WORK_DIR !== '')
    ? INGEST_WORK_DIR
    : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'masterpiece_ingest';

if (!is_dir($workDir) && !@mkdir($workDir, 0775, true)) {
    ingest_err("Cannot create work directory: {$workDir}");
    exit(1);
}

// ------------------------------------------------------------------- jobs
if ($onlyJob) {
    $stmt = $conn->prepare("SELECT * FROM ingestion_jobs WHERE id = ? LIMIT 1");
    $stmt->execute([$onlyJob]);
} else {
    $stmt = $conn->prepare(
        "SELECT * FROM ingestion_jobs
          WHERE status = 'matched' AND tmdb_id IS NOT NULL
       ORDER BY source_size ASC
          LIMIT {$limit}"
    );
    $stmt->execute();
}
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($jobs)) {
    ingest_log('Nothing to publish. Approve titles at /admin-ingestion first.');
    exit(0);
}

ingest_log(count($jobs) . ' job(s) to publish' . ($dryRun ? ' [DRY RUN]' : ''));

$published = $failed = 0;

foreach ($jobs as $job) {

    $jobId = (int) $job['id'];
    $label = $job['tmdb_title'] ?: $job['source_title'];

    ingest_log('');
    ingest_log(str_repeat('-', 62));
    ingest_log("#{$jobId}  {$label}  (tmdb={$job['tmdb_id']}, source={$job['quality']})");

    $staged = [];

    try {
        if (!$dryRun) {
            $conn->prepare(
                "UPDATE ingestion_jobs SET status='transcoding', attempts=attempts+1 WHERE id=?"
            )->execute([$jobId]);
        }

        // ---------------------------------------------------- 1. download
        $srcPath = $workDir . DIRECTORY_SEPARATOR . 'src_' . $jobId . '_' . basename($job['source_file_name'] ?: 'source.mp4');
        $expected = (int) $job['source_size'];

        if (is_file($srcPath) && $expected > 0 && filesize($srcPath) === $expected) {
            ingest_log('  source already staged (' . ff_human_size($expected) . ')');
        } elseif ($dryRun) {
            ingest_log('  would download ' . ff_human_size($expected) . ' from ' . $job['source_url']);
        } else {
            ingest_log('  downloading ' . ff_human_size($expected) . ' ...');
            $dl = publish_download($job['source_url'], $srcPath);
            if (!$dl['ok']) {
                throw new RuntimeException('Download failed: ' . $dl['error']);
            }
            ingest_log('  downloaded ' . ff_human_size($dl['size']));
        }

        // ------------------------------------------------------- 2. probe
        if ($dryRun && !is_file($srcPath)) {
            $height = (int) $job['source_height'] ?: 480;
            ingest_log("  would probe; assuming source height {$height}");
        } else {
            $probe = ff_probe($srcPath);
            if (!$probe || $probe['height'] <= 0) {
                throw new RuntimeException('ffprobe could not read a video stream from the source');
            }
            $height = $probe['height'];
            ingest_log(sprintf('  probed: %dx%d  %s  %s',
                $probe['width'], $probe['height'],
                ff_human_duration($probe['duration']), $probe['vcodec']));
        }

        // ------------------------------------------------------ 3. ladder
        $ladder = ff_ladder_for($height);
        ingest_log('  ladder: ' . implode(', ', array_keys($ladder)) . '  (no upscaling)');

        $lastDownloadId = null;

        foreach ($ladder as $qname => $spec) {

            $outPath = $workDir . DIRECTORY_SEPARATOR . "out_{$jobId}_{$qname}.mp4";
            $key     = "movies/{$job['tmdb_id']}/{$qname}.mp4";

            if ($dryRun) {
                ingest_log("  would transcode {$qname} -> upload to {$key}");
                continue;
            }

            // -------------------------------------------- 3a. transcode
            ingest_log("  transcoding {$qname} ...");
            $t0 = microtime(true);
            $res = ff_transcode($srcPath, $outPath, $spec, $quiet);
            if (!$res['ok']) {
                throw new RuntimeException("Transcode {$qname} failed: " . $res['error']);
            }
            $staged[] = $outPath;
            ingest_log(sprintf('  %s done: %s in %ds',
                $qname, ff_human_size($res['size']), round(microtime(true) - $t0)));

            // ----------------------------------------------- 3b. upload
            ingest_log("  uploading {$qname} ...");
            $up = s3_put_file($cfg, $outPath, $key, 'video/mp4');
            if (!$up['ok']) {
                throw new RuntimeException("Upload {$qname} failed (HTTP {$up['status']}): " . $up['error']);
            }
            $publicUrl = s3_public_url($cfg, $key);
            ingest_log("  uploaded -> {$publicUrl}");

            // ------------------------------------- 3c. publish the row
            $fileName = sprintf('%s%s %s.mp4',
                $label,
                $job['source_year'] ? " ({$job['source_year']})" : '',
                $qname
            );

            $lastDownloadId = publish_upsert_download($conn, [
                'tmdb_id'      => (int) $job['tmdb_id'],
                'quality'      => $qname,
                'file_size'    => ff_human_size($res['size']),
                'file_name'    => $fileName,
                'download_url' => $publicUrl,
                'license'      => $job['license_label'],
                'license_url'  => $job['license_url'],
                'attribution'  => $job['source_creator'],
            ]);
        }

        // ---------------------------------------------------- 4. finish
        if (!$dryRun) {
            $conn->prepare(
                "UPDATE ingestion_jobs
                    SET status='published', media_download_id=?, status_message=?
                  WHERE id=?"
            )->execute([$lastDownloadId, 'Published ' . implode(', ', array_keys($ladder)), $jobId]);
            ingest_log('  PUBLISHED');
        }
        $published++;

    } catch (Throwable $e) {
        $failed++;
        ingest_err('  ' . $e->getMessage());
        if (!$dryRun) {
            $conn->prepare(
                "UPDATE ingestion_jobs SET status='failed', status_message=? WHERE id=?"
            )->execute([mb_substr($e->getMessage(), 0, 1000), $jobId]);
        }
    } finally {
        if (!$keepTemp) {
            foreach ($staged as $f) {
                @unlink($f);
            }
        }
    }
}

ingest_log('');
if ($dryRun) {
    ingest_log("Done. {$published} job(s) planned, {$failed} would fail. Nothing was written.");
} else {
    ingest_log("Done. published={$published} failed={$failed}");
    ingest_log("Staged sources kept in {$workDir} (delete manually when done).");
}

