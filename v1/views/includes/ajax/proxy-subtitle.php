<?php
// Prevent any notices or warnings from breaking the subtitle stream
error_reporting(0);
ini_set('display_errors', '0');

$remoteUrl = trim($_GET['url'] ?? '');
$format = strtolower(trim($_GET['format'] ?? 'vtt'));

if (empty($remoteUrl)) {
    http_response_code(400);
    echo "Missing subtitle URL";
    exit;
}

// Basic security check: allow trusted subtitle CDNs
$parsed = parse_url($remoteUrl);
$host = strtolower($parsed['host'] ?? '');
$allowedHosts = ['strem.io', 'opensubtitles.com', 'opensubtitles.org', 'subdl.com'];

$isAllowed = false;
foreach ($allowedHosts as $allowed) {
    if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
        $isAllowed = true;
        break;
    }
}

if (!$isAllowed) {
    http_response_code(403);
    echo "Host not permitted";
    exit;
}

// Download subtitle file
$ch = curl_init($remoteUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'MasterpieceMovie/2.0');
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_ENCODING, ''); // handle gzip/deflate if needed
$content = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || empty($content)) {
    http_response_code(502);
    echo "Failed to retrieve subtitle from origin";
    exit;
}

// Clean UTF-8 and strip BOM
if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
    $content = substr($content, 3);
}

// Check encoding and convert to UTF-8 if necessary
$detected = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);
if ($detected && $detected !== 'UTF-8') {
    $content = mb_convert_encoding($content, 'UTF-8', $detected);
}

// Normalize line breaks
$content = str_replace(["\r\n", "\r"], "\n", $content);

/**
 * Helper to parse timestamps to seconds
 */
function srtTimeToSeconds($timeStr) {
    $timeStr = trim(str_replace(',', '.', $timeStr));
    $parts = explode(':', $timeStr);
    if (count($parts) === 3) {
        return ((float)$parts[0] * 3600) + ((float)$parts[1] * 60) + (float)$parts[2];
    } elseif (count($parts) === 2) {
        return ((float)$parts[0] * 60) + (float)$parts[1];
    }
    return (float)$timeStr;
}

// Parse blocks
$blocks = preg_split('/\n\s*\n/', trim($content));
$cues = [];

foreach ($blocks as $block) {
    $lines = explode("\n", trim($block));
    if (count($lines) < 2) continue;

    // Line 0 might be index number (1, 2, 3...) or timestamp
    $timeLineIndex = 0;
    if (is_numeric(trim($lines[0])) && isset($lines[1])) {
        $timeLineIndex = 1;
    }

    $timeLine = $lines[$timeLineIndex] ?? '';
    if (strpos($timeLine, '-->') === false) continue;

    $times = explode('-->', $timeLine);
    if (count($times) !== 2) continue;

    $startSec = srtTimeToSeconds($times[0]);
    $endSec = srtTimeToSeconds($times[1]);

    $textLines = array_slice($lines, $timeLineIndex + 1);
    $text = trim(implode("\n", $textLines));

    // Strip basic styling or font tags if present
    $cleanText = preg_replace('/<font[^>]*>/i', '', $text);
    $cleanText = str_replace('</font>', '', $cleanText);

    if (!empty($cleanText) && $endSec > $startSec) {
        $cues[] = [
            'start' => round($startSec, 3),
            'end'   => round($endSec, 3),
            'text'  => $cleanText
        ];
    }
}

// Return JSON if requested
if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Cache-Control: public, max-age=86400');
    echo json_encode(['cues' => $cues]);
    exit;
}

// Default: WebVTT output
header('Content-Type: text/vtt; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=86400');

echo "WEBVTT\n\n";

function formatVttTime($sec) {
    $hours = floor($sec / 3600);
    $mins = floor(($sec % 3600) / 60);
    $secs = $sec - ($hours * 3600) - ($mins * 60);
    return sprintf('%02d:%02d:%06.3f', $hours, $mins, $secs);
}

foreach ($cues as $idx => $cue) {
    $num = $idx + 1;
    $s = formatVttTime($cue['start']);
    $e = formatVttTime($cue['end']);
    echo "{$num}\n{$s} --> {$e}\n{$cue['text']}\n\n";
}
