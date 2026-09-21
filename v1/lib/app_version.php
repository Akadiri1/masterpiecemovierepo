<?php
/**
 * Which build of the site this is.
 *
 * Installed copies of the app keep running the HTML and JavaScript they were
 * opened with, so a deploy alone does not reach anyone until the page is
 * reloaded. The pages carry this string and compare it against /version.php,
 * which is how the "new version" button knows there is something to pick up.
 *
 * The Docker build writes .build-version, so every deploy gets a new value.
 * Without that file (a local WAMP checkout) the newest modification time of
 * the files that change most often stands in for it.
 */

if (!function_exists('appVersion')) {
    function appVersion(): string
    {
        static $version = null;
        if ($version !== null) {
            return $version;
        }

        $root = dirname(dirname(__DIR__));

        $stamp = $root . '/.build-version';
        if (is_readable($stamp)) {
            $built = trim((string) file_get_contents($stamp));
            if ($built !== '') {
                return $version = $built;
            }
        }

        $newest = 0;
        foreach ([
            $root . '/www/index.php',
            $root . '/www/sw.js',
            $root . '/v1/views/includes/header.php',
            $root . '/v1/views/includes/footer.php',
            $root . '/v1/views/zen-ai.php',
        ] as $file) {
            $time = @filemtime($file);
            if ($time && $time > $newest) {
                $newest = $time;
            }
        }

        return $version = 'dev-' . ($newest ?: time());
    }
}
