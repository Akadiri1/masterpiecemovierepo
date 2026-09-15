<?php
/**
 * Outbound TLS trust store.
 *
 * WAMP ships PHP with curl.cainfo unset, so curl has no trust store and every
 * HTTPS request fails peer verification. The workaround that had crept into
 * the AI endpoints was CURLOPT_SSL_VERIFYPEER => false, which disables
 * verification entirely -- on requests that carry the Groq API key in an
 * Authorization header. This resolves a real CA bundle instead.
 *
 * Safe to include from both CLI and web contexts.
 */

if (!function_exists('app_resolve_ca_bundle')) {

    /**
     * Locate a usable CA bundle, or null if none is available.
     * Override with the APP_CA_BUNDLE environment variable.
     */
    function app_resolve_ca_bundle(): ?string
    {
        static $resolved = false;
        static $cached = null;

        if ($resolved) {
            return $cached;
        }
        $resolved = true;

        $candidates = [];

        foreach (['APP_CA_BUNDLE', 'INGEST_CA_BUNDLE'] as $env) {
            if ($val = getenv($env)) {
                $candidates[] = $val;
            }
        }

        // A bundle committed alongside the app takes priority over guesses.
        $candidates[] = __DIR__ . '/cacert.pem';

        foreach (['curl.cainfo', 'openssl.cafile'] as $key) {
            if ($val = ini_get($key)) {
                $candidates[] = $val;
            }
        }

        // Any PHP version installed under this WAMP ships extras/ssl/cacert.pem.
        // PHP_BINARY is php.exe under bin/php/<version>/, so two levels up is
        // the directory holding every installed version.
        if (defined('PHP_BINARY') && PHP_BINARY) {
            $siblings = glob(dirname(PHP_BINARY, 2) . '/*/extras/ssl/cacert.pem') ?: [];
            foreach ($siblings as $path) {
                $candidates[] = $path;
            }
        }

        // Common fixed locations, for when PHP runs as an Apache module and
        // PHP_BINARY points at httpd rather than php.exe.
        foreach (glob('C:/wamp64/bin/php/*/extras/ssl/cacert.pem') ?: [] as $path) {
            $candidates[] = $path;
        }

        foreach ($candidates as $path) {
            if ($path && @is_readable($path)) {
                $cached = $path;
                return $cached;
            }
        }

        return null;
    }

    /**
     * Apply the resolved trust store to a curl handle.
     *
     * Verification is always left ON. If no bundle can be found the request is
     * allowed to fail loudly rather than silently downgrading to an
     * unauthenticated connection.
     */
    function app_apply_tls($ch): void
    {
        $bundle = app_resolve_ca_bundle();

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        if ($bundle !== null) {
            curl_setopt($ch, CURLOPT_CAINFO, $bundle);
        } else {
            error_log('app_apply_tls: no CA bundle found; HTTPS verification will fail. '
                . 'Set APP_CA_BUNDLE or place cacert.pem in v1/lib/.');
        }

        /**
         * Address family.
         *
         * The AI endpoints used to force IPv4. On a host that reaches these
         * APIs over IPv6, forcing IPv4 makes the TCP connect hang until the
         * timeout and every AI feature silently falls back -- which is exactly
         * what happened here: DNS resolved in milliseconds while the connect
         * never completed.
         *
         * Letting curl pick (it tries both and keeps whichever answers first)
         * is correct on dual-stack and IPv6-only hosts alike. Set
         * HTTP_FORCE_IPV4 to true in config only if a host genuinely needs it.
         */
        if (defined('HTTP_FORCE_IPV4') && HTTP_FORCE_IPV4) {
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }
    }
}
