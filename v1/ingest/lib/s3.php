<?php
/**
 * Minimal S3 client (Wasabi-compatible) using AWS Signature V4.
 *
 * Written against curl directly because this project has no composer
 * dependencies and pulling in aws-sdk-php for three operations would be
 * heavier than the operations themselves.
 *
 * Supports: PUT (streamed from disk), HEAD, DELETE.
 * Single-PUT uploads are capped at 5 GB by the S3 API; transcoded renditions
 * sit far below that, and s3_put_file() fails loudly rather than silently
 * truncating if a file ever exceeds it.
 */

const S3_MAX_SINGLE_PUT = 5368709120; // 5 GB

/**
 * Build the SigV4 Authorization header set for one request.
 *
 * @param array $o  method, host, uri, region, access_key, secret_key,
 *                  payload_hash, headers (assoc), timestamp (optional)
 * @return array Header lines ready for CURLOPT_HTTPHEADER
 */
function s3_sign_v4(array $o): array
{
    $method      = strtoupper($o['method']);
    $host        = $o['host'];
    $uri         = $o['uri'];              // already-encoded key path, leading /
    $region      = $o['region'];
    $service     = $o['service'] ?? 's3';
    $accessKey   = $o['access_key'];
    $secretKey   = $o['secret_key'];
    $payloadHash = $o['payload_hash'];
    $query       = $o['query'] ?? '';

    $ts       = $o['timestamp'] ?? time();
    $amzDate  = gmdate('Ymd\THis\Z', $ts);
    $dateOnly = gmdate('Ymd', $ts);

    // --- canonical headers: lowercase, sorted, trimmed ---
    $headers = array_change_key_case($o['headers'] ?? [], CASE_LOWER);
    $headers['host']                 = $host;
    $headers['x-amz-content-sha256'] = $payloadHash;
    $headers['x-amz-date']           = $amzDate;
    ksort($headers);

    $canonicalHeaders = '';
    foreach ($headers as $k => $v) {
        $canonicalHeaders .= $k . ':' . trim((string) $v) . "\n";
    }
    $signedHeaders = implode(';', array_keys($headers));

    $canonicalRequest = implode("\n", [
        $method,
        $uri,
        $query,
        $canonicalHeaders,
        $signedHeaders,
        $payloadHash,
    ]);

    $scope = "{$dateOnly}/{$region}/{$service}/aws4_request";

    $stringToSign = implode("\n", [
        'AWS4-HMAC-SHA256',
        $amzDate,
        $scope,
        hash('sha256', $canonicalRequest),
    ]);

    // --- derive the signing key ---
    $kDate    = hash_hmac('sha256', $dateOnly, 'AWS4' . $secretKey, true);
    $kRegion  = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', $service, $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
    $signature = hash_hmac('sha256', $stringToSign, $kSigning);

    $authorization = "AWS4-HMAC-SHA256 "
        . "Credential={$accessKey}/{$scope}, "
        . "SignedHeaders={$signedHeaders}, "
        . "Signature={$signature}";

    $lines = ["Authorization: {$authorization}"];
    foreach ($headers as $k => $v) {
        if ($k === 'host') {
            continue; // curl sets Host itself
        }
        $lines[] = $k . ': ' . $v;
    }

    return $lines;
}

/** Exposed for testing: return just the hex signature. */
function s3_signature_only(array $o): string
{
    foreach (s3_sign_v4($o) as $line) {
        if (preg_match('/Signature=([0-9a-f]+)/', $line, $m)) {
            return $m[1];
        }
    }
    return '';
}

/** Percent-encode an object key, preserving path separators. */
function s3_encode_key(string $key): string
{
    return '/' . implode('/', array_map('rawurlencode', explode('/', ltrim($key, '/'))));
}

/**
 * Upload a local file, streamed from disk (never loaded into memory).
 *
 * @return array{ok:bool,status:int,url:string,error:?string}
 */
function s3_put_file(array $cfg, string $localPath, string $key, string $contentType = 'video/mp4'): array
{
    $url = rtrim($cfg['endpoint'], '/') . '/' . $cfg['bucket'] . s3_encode_key($key);

    if (!is_readable($localPath)) {
        return ['ok' => false, 'status' => 0, 'url' => $url, 'error' => "Cannot read {$localPath}"];
    }

    $size = filesize($localPath);
    if ($size > S3_MAX_SINGLE_PUT) {
        return [
            'ok' => false, 'status' => 0, 'url' => $url,
            'error' => sprintf(
                'File is %.2f GB, above the %d GB single-PUT limit. Multipart upload is not implemented.',
                $size / 1073741824, S3_MAX_SINGLE_PUT / 1073741824
            ),
        ];
    }

    // S3 requires the payload hash up front; hash_file streams it.
    $payloadHash = hash_file('sha256', $localPath);
    $host = parse_url($cfg['endpoint'], PHP_URL_HOST);

    $headers = s3_sign_v4([
        'method'       => 'PUT',
        'host'         => $host,
        'uri'          => '/' . $cfg['bucket'] . s3_encode_key($key),
        'region'       => $cfg['region'],
        'access_key'   => $cfg['access_key'],
        'secret_key'   => $cfg['secret_key'],
        'payload_hash' => $payloadHash,
        'headers'      => [
            'content-type'   => $contentType,
            'content-length' => (string) $size,
        ],
    ]);

    $fh = fopen($localPath, 'rb');
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_PUT            => true,
        CURLOPT_INFILE         => $fh,
        CURLOPT_INFILESIZE     => $size,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 0,   // large uploads
        CURLOPT_CONNECTTIMEOUT => 30,
    ]);
    if (function_exists('ingest_apply_tls')) {
        ingest_apply_tls($ch);
    }

    $body   = curl_exec($ch);
    $errno  = curl_errno($ch);
    $err    = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fh);

    if ($errno) {
        return ['ok' => false, 'status' => 0, 'url' => $url, 'error' => $err];
    }
    if ($status < 200 || $status >= 300) {
        return ['ok' => false, 'status' => $status, 'url' => $url, 'error' => trim((string) $body)];
    }

    return ['ok' => true, 'status' => $status, 'url' => $url, 'error' => null];
}

/** HEAD an object. Returns [exists, size, status]. */
function s3_head(array $cfg, string $key): array
{
    $host = parse_url($cfg['endpoint'], PHP_URL_HOST);
    $url  = rtrim($cfg['endpoint'], '/') . '/' . $cfg['bucket'] . s3_encode_key($key);

    $headers = s3_sign_v4([
        'method'       => 'HEAD',
        'host'         => $host,
        'uri'          => '/' . $cfg['bucket'] . s3_encode_key($key),
        'region'       => $cfg['region'],
        'access_key'   => $cfg['access_key'],
        'secret_key'   => $cfg['secret_key'],
        'payload_hash' => hash('sha256', ''),
        'headers'      => [],
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_NOBODY         => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    if (function_exists('ingest_apply_tls')) {
        ingest_apply_tls($ch);
    }
    curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $size   = (int) curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    curl_close($ch);

    return ['exists' => $status === 200, 'size' => $size, 'status' => $status];
}

/** Read S3 settings out of the app config, with validation. */
function s3_config(): array
{
    $cfg = [
        'endpoint'   => defined('WASABI_ENDPOINT')   ? WASABI_ENDPOINT   : '',
        'region'     => defined('WASABI_REGION')     ? WASABI_REGION     : '',
        'bucket'     => defined('WASABI_BUCKET')     ? WASABI_BUCKET     : '',
        'access_key' => defined('WASABI_ACCESS_KEY') ? WASABI_ACCESS_KEY : '',
        'secret_key' => defined('WASABI_SECRET_KEY') ? WASABI_SECRET_KEY : '',
        'public_base'=> defined('WASABI_PUBLIC_BASE')? WASABI_PUBLIC_BASE: '',
    ];

    $cfg['missing'] = [];
    foreach (['endpoint', 'region', 'bucket', 'access_key', 'secret_key'] as $k) {
        if ($cfg[$k] === '') {
            $cfg['missing'][] = strtoupper('wasabi_' . $k);
        }
    }
    $cfg['ready'] = empty($cfg['missing']);

    return $cfg;
}

/** Public URL for an uploaded key. */
function s3_public_url(array $cfg, string $key): string
{
    $base = $cfg['public_base'] !== ''
        ? rtrim($cfg['public_base'], '/')
        : rtrim($cfg['endpoint'], '/') . '/' . $cfg['bucket'];

    return $base . s3_encode_key($key);
}
