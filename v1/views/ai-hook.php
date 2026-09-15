<?php
/**
 * AI hook endpoint -- generates a one or two sentence pitch for a title.
 *
 * Hardening notes (this endpoint was previously open to the internet):
 *
 *  - Login is now required. It used to be unauthenticated with
 *    Access-Control-Allow-Origin: *, so anyone could drive it in a loop and
 *    drain the Groq quota.
 *
 *  - The title is resolved from TMDB server-side using media_id. It used to be
 *    taken from the POST body while the cache key was media_id, so a caller
 *    could write arbitrary text into the pitch shown to every visitor on any
 *    movie page -- and the client renders it with innerHTML, making that
 *    stored XSS rather than mere defacement.
 *
 *  - Generations are rate limited per user and recorded in ai_usage_log.
 *
 *  - TLS verification is on. This previously set SSL_VERIFYPEER => false on a
 *    request carrying the Groq API key.
 *
 *  - The cache lives in the ai_hooks table. It was a whole-file JSON
 *    read-modify-write with no locking, which lost entries under concurrency.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

// Same-origin only. This is a credentialed endpoint; it must not be callable
// from other sites.
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../lib/tls.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Max model generations per user per day. Cache hits do not count. */
const AI_HOOK_DAILY_LIMIT = 40;

/* Configured in .env/config.php so a Groq model retirement is a config change
   rather than a code edit. The fallback keeps this working if config is old. */
if (!defined('AI_HOOK_MODEL')) {
    define('AI_HOOK_MODEL', defined('AI_MODEL_HOOK') ? AI_MODEL_HOOK : 'openai/gpt-oss-20b');
}

/** Record an attempt, best effort -- logging must never break the response. */
function ai_hook_log(?PDO $conn, ?int $userId, ?int $tmdbId, string $status, string $detail = ''): void
{
    if (!$conn) {
        return;
    }
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $packed = $ip !== '' ? @inet_pton($ip) : null;

        $stmt = $conn->prepare(
            "INSERT INTO ai_usage_log (user_id, endpoint, tmdb_id, status, detail, ip)
             VALUES (?, 'ai-hook', ?, ?, ?, ?)"
        );
        $stmt->execute([
            $userId ?: null,
            $tmdbId ?: null,
            $status,
            $detail !== '' ? mb_substr($detail, 0, 255) : null,
            $packed ?: null,
        ]);
    } catch (Exception $e) {
        // A logging failure is not worth failing the request over.
    }
}

function ai_hook_fail(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

// ---------------------------------------------------------------- request ---

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ai_hook_fail('POST required.', 405);
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
if (!$userId) {
    ai_hook_log($conn ?? null, null, null, 'blocked', 'not logged in');
    ai_hook_fail('Login required.', 401);
}

$tmdbId = (int) ($_POST['media_id'] ?? 0);
if ($tmdbId <= 0) {
    ai_hook_fail('Missing or invalid media_id.');
}

$mediaType = strtolower(trim((string) ($_POST['media_type'] ?? 'movie')));
if (!in_array($mediaType, ['movie', 'tv'], true)) {
    $mediaType = 'movie';
}

if (!isset($conn) || !($conn instanceof PDO)) {
    ai_hook_fail('Database unavailable.', 500);
}

// ------------------------------------------------------------------ cache ---

try {
    $stmt = $conn->prepare(
        "SELECT hook FROM ai_hooks
          WHERE tmdb_id = ? AND media_type = ? AND is_active = 1
          LIMIT 1"
    );
    $stmt->execute([$tmdbId, $mediaType]);
    $cached = $stmt->fetchColumn();

    if ($cached) {
        ai_hook_log($conn, $userId, $tmdbId, 'hit');
        echo json_encode(['status' => 'success', 'hook' => $cached, 'cached' => true]);
        exit;
    }
} catch (PDOException $e) {
    ai_hook_fail('Hook cache unavailable. Run: php v1/db/migrate_ai.php up', 500);
}

// ------------------------------------------------------------- rate limit ---

try {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) FROM ai_usage_log
          WHERE user_id = ? AND endpoint = 'ai-hook' AND status = 'generated'
            AND created_at >= CURDATE()"
    );
    $stmt->execute([$userId]);

    if ((int) $stmt->fetchColumn() >= AI_HOOK_DAILY_LIMIT) {
        ai_hook_log($conn, $userId, $tmdbId, 'blocked', 'daily generation limit');
        ai_hook_fail('Daily AI limit reached.', 429);
    }
} catch (PDOException $e) {
    // If the limit cannot be read, fail closed rather than allowing unbounded spend.
    ai_hook_fail('Rate limit check failed.', 500);
}

// ------------------------------------------------- authoritative title ---

// The caller does not get to say what this title is. Anything it posted as
// `title` is deliberately ignored.
if (!function_exists('fetchTmdbApi')) {
    ai_hook_fail('TMDB helper unavailable.', 500);
}

$details = fetchTmdbApi("{$mediaType}/{$tmdbId}", [], 604800);
$title = $details['title'] ?? $details['name'] ?? '';
$year = substr((string) ($details['release_date'] ?? $details['first_air_date'] ?? ''), 0, 4);

if ($title === '') {
    ai_hook_log($conn, $userId, $tmdbId, 'error', 'title not found on TMDB');
    ai_hook_fail('Could not resolve that title.', 404);
}

// ------------------------------------------------------------ generation ---

$groqApiKey = defined('GROQ_API_KEY') ? GROQ_API_KEY : '';
if ($groqApiKey === '') {
    ai_hook_log($conn, $userId, $tmdbId, 'error', 'missing API key');
    ai_hook_fail('AI is not configured.', 500);
}

$prompt = $title . ($year ? " ({$year})" : '');

$payload = [
    'model' => AI_HOOK_MODEL,
    'messages' => [
        [
            'role' => 'system',
            'content' =>
                "You are an expert film critic writing a short, exciting pitch to convince "
                . "someone to watch a title. Exactly 1 or 2 sentences. Punchy and specific: "
                . "highlight what makes it special, such as a twist, a performance, or its "
                . "atmosphere. Do not use the title in the pitch. No quotes, no markdown, "
                . "no emoji. Plain prose only."
        ],
        ['role' => 'user', 'content' => 'Write a pitch for: ' . $prompt],
    ],
    'temperature' => 0.7,
    // The gpt-oss models reason before answering, and reasoning is billed
    // against max_tokens. At 150 the budget was being spent entirely on
    // reasoning, so `content` came back empty for a two sentence pitch.
    // Keep reasoning minimal and leave real headroom for the answer.
    'reasoning_effort' => 'low',
    'max_tokens' => 512,
];

$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $groqApiKey,
    ],
    // Without these a hung upstream pins an Apache worker indefinitely.
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_CONNECTTIMEOUT => 5,
]);
app_apply_tls($ch);

$response = curl_exec($ch);
$curlErr  = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($curlErr || $response === false) {
    ai_hook_log($conn, $userId, $tmdbId, 'error', 'curl: ' . $curlErr);
    ai_hook_fail('Could not reach the AI service.', 502);
}
if ($httpCode >= 400) {
    ai_hook_log($conn, $userId, $tmdbId, 'error', 'upstream HTTP ' . $httpCode);
    ai_hook_fail('AI service returned an error.', 502);
}

$aiResult = json_decode((string) $response, true);
$hook = $aiResult['choices'][0]['message']['content'] ?? '';

// Defence in depth: the client renders this, and model output is never trusted.
$hook = trim(strip_tags((string) $hook));
$hook = trim($hook, "\"'");
$hook = mb_substr($hook, 0, 500);

if ($hook === '') {
    ai_hook_log($conn, $userId, $tmdbId, 'error', 'empty completion');
    ai_hook_fail('Failed to generate a pitch.', 502);
}

// ------------------------------------------------------------------ store ---

try {
    $stmt = $conn->prepare("
        INSERT INTO ai_hooks (tmdb_id, media_type, title, hook, model, generated_by)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            hook  = VALUES(hook),
            model = VALUES(model),
            generated_by = VALUES(generated_by),
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$tmdbId, $mediaType, $title, $hook, AI_HOOK_MODEL, $userId]);
} catch (PDOException $e) {
    // Serving the hook still works even if caching it failed.
    error_log('ai-hook: cache write failed: ' . $e->getMessage());
}

ai_hook_log($conn, $userId, $tmdbId, 'generated');

echo json_encode(['status' => 'success', 'hook' => $hook, 'cached' => false]);
