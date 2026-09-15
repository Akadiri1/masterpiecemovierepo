<?php
/**
 * Production configuration.
 *
 * Every value comes from an environment variable, so no secret is ever
 * committed. The Docker image copies this file to .env/config.php, which is
 * the path www/index.php loads. Local development keeps using the real,
 * git-ignored .env/config.php.
 *
 * DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD, DB_SSL_CA and DB_SQL_MODE
 * are read directly by v1/models/model.php.
 */

if (!function_exists('mpm_env')) {
    /** An environment variable, or $default when it is unset or empty. */
    function mpm_env(string $key, string $default = ''): string
    {
        $value = getenv($key);
        return ($value === false || $value === '') ? $default : $value;
    }
}

if (!function_exists('mpm_env_bool')) {
    function mpm_env_bool(string $key, bool $default): bool
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }
}

define('ALLOW_FREE_KIDS', mpm_env_bool('ALLOW_FREE_KIDS', true));
define('APP_DOMAIN', mpm_env('APP_DOMAIN'));

// External APIs
define('TMDB_API_KEY', mpm_env('TMDB_API_KEY'));
define('GROQ_API_KEY', mpm_env('GROQ_API_KEY'));
define('TMDB_IMAGE_BASE_URL', mpm_env('TMDB_IMAGE_BASE_URL', 'https://image.tmdb.org/t/p/w500'));

// AI models (Groq)
define('AI_MODEL_CHAT', mpm_env('AI_MODEL_CHAT', 'openai/gpt-oss-120b'));
define('AI_MODEL_HOOK', mpm_env('AI_MODEL_HOOK', 'openai/gpt-oss-20b'));

// Let curl use IPv4 or IPv6, whichever answers. See the note in .env/config.php.
define('HTTP_FORCE_IPV4', mpm_env_bool('HTTP_FORCE_IPV4', false));

// Wasabi storage for the ingestion pipeline (optional)
define('WASABI_ENDPOINT', mpm_env('WASABI_ENDPOINT'));
define('WASABI_REGION', mpm_env('WASABI_REGION'));
define('WASABI_BUCKET', mpm_env('WASABI_BUCKET'));
define('WASABI_ACCESS_KEY', mpm_env('WASABI_ACCESS_KEY'));
define('WASABI_SECRET_KEY', mpm_env('WASABI_SECRET_KEY'));
define('WASABI_PUBLIC_BASE', mpm_env('WASABI_PUBLIC_BASE'));

// Transcoding tools. ffmpeg is not installed in the web image.
define('FFMPEG_BIN', mpm_env('FFMPEG_BIN', 'ffmpeg'));
define('FFPROBE_BIN', mpm_env('FFPROBE_BIN', 'ffprobe'));
define('INGEST_WORK_DIR', mpm_env('INGEST_WORK_DIR'));
