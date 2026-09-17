<?php
/**
 * Guards for the admin endpoints the older admin pages call through plain
 * links (www/updateContent.php, www/deleteContent.php).
 */

function adminRequestHost(): string
{
    return strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
}

/**
 * Whether the request came from one of this site's own pages. Browsers mark
 * every request with Sec-Fetch-Site, so a link or image on another website
 * (or in an email) can't act for a signed-in admin. Browsers too old to send
 * it are checked against the Referer instead.
 */
function adminRequestIsSameSite(): bool
{
    if (isset($_SERVER['HTTP_SEC_FETCH_SITE'])) {
        return $_SERVER['HTTP_SEC_FETCH_SITE'] === 'same-origin';
    }
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    return $referer !== '' && strtolower((string) parse_url($referer, PHP_URL_HOST)) === adminRequestHost();
}

/** The admin page the request came from, or $fallback. Never another site. */
function adminReturnPath(string $fallback): string
{
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if ($referer === '' || strtolower((string) parse_url($referer, PHP_URL_HOST)) !== adminRequestHost()) {
        return $fallback;
    }
    $path = parse_url($referer, PHP_URL_PATH) ?: $fallback;
    $query = parse_url($referer, PHP_URL_QUERY);
    return $path . ($query ? '?' . $query : '');
}

/** A plain error page for these endpoints, with a way back. */
function adminRequestFail(int $status, string $message, string $fallback): void
{
    http_response_code($status);
    $back = htmlspecialchars(adminReturnPath($fallback));
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>Admin</title><body style="background:#0d0a10;color:#e9eaee;font-family:Arial,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;padding:16px;">'
       . '<div style="max-width:420px;text-align:center;"><p style="font-size:1rem;line-height:1.5;">' . htmlspecialchars($message) . '</p>'
       . '<a href="' . $back . '" style="color:#fff;background:#e50914;padding:10px 18px;border-radius:9px;text-decoration:none;display:inline-block;margin-top:8px;">Go back</a></div></body>';
    exit;
}
