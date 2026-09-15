<?php
/**
 * Shared bootstrap for the ingestion CLI tools.
 *
 * Loads config (DB creds + TMDB_API_KEY), the PDO connection from model.php,
 * and fetchTmdbApi() from controller.php. Safe to include from CLI.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Ingestion tools are CLI-only.\n");
}

require_once __DIR__ . '/../../.env/config.php';
require_once __DIR__ . '/../models/model.php';        // defines $conn
require_once __DIR__ . '/../controllers/controller.php'; // defines fetchTmdbApi()

require_once __DIR__ . '/lib/license.php';
require_once __DIR__ . '/lib/archive_client.php';
require_once __DIR__ . '/lib/tmdb_matcher.php';
require_once __DIR__ . '/lib/s3.php';
require_once __DIR__ . '/lib/transcode.php';
require_once __DIR__ . '/lib/publish_lib.php';

if (!isset($conn) || !($conn instanceof PDO)) {
    die("Could not establish a database connection.\n");
}

/**
 * This site sells subscription plans (see views/pricing-plan.php + Paystack
 * checkout), so ingested content is being distributed commercially. That rules
 * out anything under a NonCommercial licence. Flip to false only if the
 * catalogue is ever made genuinely free.
 */
define('INGEST_COMMERCIAL_USE', true);

/** Seconds to wait between outbound API calls, to stay a polite client. */
define('INGEST_REQUEST_DELAY', 0.5);

/**
 * Resolve a CA bundle for outbound TLS.
 *
 * WAMP ships PHP with curl.cainfo unset, so CLI curl has no trust store and
 * every HTTPS call fails verification. We locate a real bundle instead of
 * disabling peer verification. Override with the INGEST_CA_BUNDLE env var.
 */
function ingest_resolve_ca_bundle(): ?string
{
    $candidates = [];

    if ($env = getenv('INGEST_CA_BUNDLE')) {
        $candidates[] = $env;
    }
    $candidates[] = __DIR__ . '/cacert.pem';

    foreach (['curl.cainfo', 'openssl.cafile'] as $key) {
        if ($val = ini_get($key)) {
            $candidates[] = $val;
        }
    }

    // Any PHP version installed under this WAMP ships extras/ssl/cacert.pem.
    $siblings = glob(dirname(PHP_BINARY, 2) . '/*/extras/ssl/cacert.pem') ?: [];
    foreach ($siblings as $path) {
        $candidates[] = $path;
    }

    foreach ($candidates as $path) {
        if ($path && is_readable($path)) {
            return $path;
        }
    }
    return null;
}

$ingestCaBundle = ingest_resolve_ca_bundle();
if ($ingestCaBundle === null) {
    fwrite(STDERR,
        "WARNING: no CA bundle found; HTTPS calls will fail verification.
" .
        "         Download https://curl.se/ca/cacert.pem to v1/ingest/cacert.pem
" .
        "         or set the INGEST_CA_BUNDLE environment variable.
"
    );
}

/** Absolute path to the CA bundle, or '' if none was found. */
define('INGEST_CA_BUNDLE', $ingestCaBundle ?? '');

/** Apply the resolved trust store to a curl handle. */
function ingest_apply_tls($ch): void
{
    if (INGEST_CA_BUNDLE !== '') {
        curl_setopt($ch, CURLOPT_CAINFO, INGEST_CA_BUNDLE);
    }
}

function ingest_log(string $msg): void
{
    echo '[' . date('H:i:s') . '] ' . $msg . "\n";
}

function ingest_warn(string $msg): void
{
    echo '[' . date('H:i:s') . '] WARN  ' . $msg . "\n";
}

function ingest_err(string $msg): void
{
    fwrite(STDERR, '[' . date('H:i:s') . '] ERROR ' . $msg . "\n");
}

/** Parse "--key=value" and "--flag" style CLI args into an array. */
function ingest_args(array $argv): array
{
    $out = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (strpos($arg, '--') !== 0) {
            continue;
        }
        $arg = substr($arg, 2);
        if (strpos($arg, '=') !== false) {
            [$k, $v] = explode('=', $arg, 2);
            $out[$k] = $v;
        } else {
            $out[$arg] = true;
        }
    }
    return $out;
}

function ingest_sleep(): void
{
    usleep((int) (INGEST_REQUEST_DELAY * 1000000));
}
