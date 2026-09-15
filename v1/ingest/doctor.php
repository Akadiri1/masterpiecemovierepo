<?php
/**
 * Preflight checks for the ingestion pipeline.
 *
 *   php v1/ingest/doctor.php
 *
 * Run this after filling in the Wasabi credentials in .env/config.php.
 * Every failure prints what to do about it.
 */

require_once __DIR__ . '/bootstrap.php';

$ok = $warn = $bad = 0;

function d_pass(string $label, string $detail = ''): void
{
    global $ok; $ok++;
    printf("  [ OK ]  %-34s %s\n", $label, $detail);
}

function d_warn(string $label, string $detail, string $fix = ''): void
{
    global $warn; $warn++;
    printf("  [WARN]  %-34s %s\n", $label, $detail);
    if ($fix !== '') printf("          -> %s\n", $fix);
}

function d_fail(string $label, string $detail, string $fix = ''): void
{
    global $bad; $bad++;
    printf("  [FAIL]  %-34s %s\n", $label, $detail);
    if ($fix !== '') printf("          -> %s\n", $fix);
}

echo "\nIngestion pipeline preflight\n";
echo str_repeat('=', 66) . "\n\n";

// ---------------------------------------------------------------- runtime
echo "Runtime\n";

PHP_VERSION_ID >= 70400
    ? d_pass('PHP version', PHP_VERSION)
    : d_fail('PHP version', PHP_VERSION, 'PHP 7.4 or newer is required');

foreach (['curl', 'pdo_mysql', 'json'] as $ext) {
    extension_loaded($ext)
        ? d_pass("ext/{$ext}", 'loaded')
        : d_fail("ext/{$ext}", 'missing', "Enable extension={$ext} in php.ini");
}

INGEST_CA_BUNDLE !== ''
    ? d_pass('TLS trust store', INGEST_CA_BUNDLE)
    : d_fail('TLS trust store', 'no CA bundle found',
        'Download https://curl.se/ca/cacert.pem to v1/ingest/cacert.pem');

// --------------------------------------------------------------- database
echo "\nDatabase\n";

try {
    $conn->query('SELECT 1');
    d_pass('Connection', DBNAME . '@localhost');
} catch (Throwable $e) {
    d_fail('Connection', $e->getMessage(), 'Check DB_USER / DB_PASSWORD in .env/config.php');
}

foreach (['ingestion_jobs', 'ingestion_runs', 'media_downloads'] as $table) {
    try {
        $n = $conn->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        d_pass("Table {$table}", "{$n} rows");
    } catch (Throwable $e) {
        $fix = $table === 'media_downloads'
            ? 'Run the media_downloads migration in v1/db/migrations/'
            : 'Run: php v1/ingest/migrate.php up';
        d_fail("Table {$table}", 'missing', $fix);
    }
}

// ----------------------------------------------------------------- ffmpeg
echo "\nTranscoding\n";

foreach (['ffmpeg', 'ffprobe'] as $bin) {
    [$found, $detail] = ff_check($bin);
    $found
        ? d_pass(ucfirst($bin), substr($detail, 0, 46))
        : d_fail(ucfirst($bin), $detail,
            'Install from https://www.gyan.dev/ffmpeg/builds/ and add to PATH, '
            . 'or set ' . strtoupper($bin) . '_BIN in .env/config.php');
}

// --------------------------------------------------------------- work dir
echo "\nWorkspace\n";

$workDir = (defined('INGEST_WORK_DIR') && INGEST_WORK_DIR !== '')
    ? INGEST_WORK_DIR
    : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'masterpiece_ingest';

if (!is_dir($workDir)) {
    @mkdir($workDir, 0775, true);
}

if (is_dir($workDir) && is_writable($workDir)) {
    $free = @disk_free_space($workDir);
    $detail = $workDir . ($free ? '  (' . ff_human_size($free) . ' free)' : '');
    if ($free && $free < 10 * 1073741824) {
        d_warn('Work directory', $detail, 'Under 10 GB free; large transcodes may fail');
    } else {
        d_pass('Work directory', $detail);
    }
} else {
    d_fail('Work directory', "not writable: {$workDir}", 'Set INGEST_WORK_DIR in .env/config.php');
}

// ---------------------------------------------------------------- storage
echo "\nStorage (Wasabi / S3)\n";

$cfg = s3_config();

if (!$cfg['ready']) {
    d_fail('Credentials', 'not set: ' . implode(', ', $cfg['missing']),
        'Fill these in at the bottom of .env/config.php');
} else {
    d_pass('Credentials', 'all values present');
    d_pass('Endpoint', $cfg['endpoint'] . '  bucket=' . $cfg['bucket'] . '  region=' . $cfg['region']);

    // HEAD a key that should not exist. The status code tells us whether the
    // credentials and bucket are good, without creating anything.
    $probe = s3_head($cfg, '__ingest_preflight_probe__');

    switch ($probe['status']) {
        case 404:
            d_pass('Bucket access', 'authenticated (probe key absent, as expected)');
            break;
        case 200:
            d_pass('Bucket access', 'authenticated (probe key unexpectedly exists)');
            break;
        case 403:
            d_fail('Bucket access', 'HTTP 403 - signature rejected or no permission',
                'Check the access key, secret key, and that the key can write to this bucket');
            break;
        case 301:
        case 307:
            d_fail('Bucket access', 'HTTP ' . $probe['status'] . ' - wrong region for this bucket',
                'Set WASABI_REGION and WASABI_ENDPOINT to the bucket\'s actual region');
            break;
        case 0:
            d_fail('Bucket access', 'no response', 'Check the endpoint URL and network access');
            break;
        default:
            d_fail('Bucket access', 'HTTP ' . $probe['status'],
                'Verify WASABI_ENDPOINT, WASABI_BUCKET and WASABI_REGION');
    }
}

// ------------------------------------------------------------------ queue
echo "\nQueue\n";

try {
    $rows = $conn->query(
        "SELECT status, COUNT(*) c FROM ingestion_jobs GROUP BY status ORDER BY c DESC"
    )->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        d_warn('Jobs', 'queue is empty',
            'Populate it: php v1/ingest/scrape.php --collection=feature_films');
    } else {
        foreach ($rows as $r) {
            d_pass('Jobs: ' . $r['status'], $r['c']);
        }
    }
} catch (Throwable $e) {
    d_fail('Jobs', 'could not read queue', 'Run: php v1/ingest/migrate.php up');
}

// ---------------------------------------------------------------- summary
echo "\n" . str_repeat('=', 66) . "\n";
printf("%d passed, %d warnings, %d failed\n\n", $ok, $warn, $bad);

if ($bad === 0) {
    echo "Ready. Publish approved titles with:\n";
    echo "  php v1/ingest/publish.php --limit=1\n\n";
} else {
    echo "Fix the failures above, then run this again.\n\n";
}

exit($bad > 0 ? 1 : 0);
