<?php
define("DBNAME", getenv('DB_NAME') ?: 'masterpiecemovie');
define("DBUSER", getenv('DB_USER') ?: 'root');
define("DBPASS", getenv('DB_PASSWORD') ?: '');
// Hosted MySQL runs on its own host and port. Unset locally, so WAMP keeps
// using localhost:3306.
define("DBHOST", getenv('DB_HOST') ?: 'localhost');
define("DBPORT", getenv('DB_PORT') ?: '3306');

// Bump this when the schema checks below change, so they run again once.
define("DB_SCHEMA_VERSION", '2026-09-17.2');

try {
    $dbOptions = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

    // Hosted MySQL (e.g. Aiven) requires an encrypted connection verified
    // against the provider's CA certificate. DB_SSL_CA can point at a file
    // mounted at runtime, and the Docker image also bundles the certificate
    // (DB_SSL_CA_BUNDLED). A missing, unreadable or incomplete file makes PDO
    // fail with the unhelpful "Cannot connect to MySQL using SSL", so each
    // candidate is checked and the first genuine certificate is used.
    $dbCaCandidates = array_values(array_filter([getenv('DB_SSL_CA'), getenv('DB_SSL_CA_BUNDLED')]));
    if ($dbCaCandidates) {
        $dbCaFile = null;
        foreach ($dbCaCandidates as $candidate) {
            if (is_readable($candidate) && @openssl_x509_read((string) file_get_contents($candidate))) {
                $dbCaFile = $candidate;
                break;
            }
        }
        if ($dbCaFile === null) {
            throw new PDOException('No usable database CA certificate (missing, unreadable or incomplete): '
                . implode(', ', $dbCaCandidates));
        }
        $dbOptions[PDO::MYSQL_ATTR_SSL_CA] = $dbCaFile;
    }

    if (getenv('DB_HOST')) {
        // Give up on an unreachable database after 10 seconds instead of
        // PHP's default 60, so visitors see the error page instead of a hang.
        $dbOptions[PDO::ATTR_TIMEOUT] = 10;

        // A remote database costs several network round trips just to open an
        // encrypted connection. Persistent connections let each Apache worker
        // reuse its connection instead of reconnecting on every page (the app
        // uses no transactions, so nothing carries between requests). Opt-in
        // with DB_PERSISTENT=1: the live site lost its database connection
        // after they were switched on for everyone.
        if (filter_var(getenv('DB_PERSISTENT'), FILTER_VALIDATE_BOOLEAN)) {
            $dbOptions[PDO::ATTR_PERSISTENT] = true;
        }
    }

    // Local MySQL runs with an empty sql_mode, so the site's queries were
    // never written for strict mode. Hosted MySQL is strict by default and
    // would reject some of them; DB_SQL_MODE restores the permissive
    // behaviour. Sent while connecting, so a reused connection doesn't pay
    // for it again. Left untouched when the variable is not set.
    if (($dbSqlMode = getenv('DB_SQL_MODE')) !== false) {
        $dbOptions[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET SESSION sql_mode = '" . str_replace("'", '', $dbSqlMode) . "'";
    }

    $conn = new PDO(
        "mysql:host=" . DBHOST . ";port=" . DBPORT . ";dbname=" . DBNAME . ";charset=utf8mb4",
        DBUSER,
        DBPASS,
        $dbOptions
    );

    // Schema checks: make sure the tables and columns the site expects exist.
    // These used to run on every page load -- nine statements that almost
    // always changed nothing, and on the remote database cost over a second
    // per page. They now run once per server (and per schema version), then
    // leave a marker file. If the marker can't be written they simply run
    // again next time, which is the old behaviour.
    $schemaMarker = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mpm-schema-'
        . md5(DBHOST . '|' . DBPORT . '|' . DBNAME . '|' . DB_SCHEMA_VERSION) . '.ok';

    if (!is_file($schemaMarker)) {
        $conn->exec("CREATE TABLE IF NOT EXISTS watch_history (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `tmdb_movie_id` INT NOT NULL,
            `current_time` FLOAT DEFAULT 0,
            `total_duration` FLOAT DEFAULT 0,
            `last_watched` DATETIME,
            UNIQUE KEY `user_movie` (`user_id`, `tmdb_movie_id`)
        )");

        $conn->exec("CREATE TABLE IF NOT EXISTS content_views (
            `tmdb_id` INT NOT NULL,
            `media_type` VARCHAR(10) NOT NULL DEFAULT 'movie',
            `views` INT DEFAULT 0,
            PRIMARY KEY (`tmdb_id`, `media_type`)
        )");

        $conn->exec("CREATE TABLE IF NOT EXISTS watchlist (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `tmdb_movie_id` INT NOT NULL,
            `media_type` VARCHAR(10) NOT NULL DEFAULT 'movie',
            `date_added` DATETIME,
            UNIQUE KEY `user_media_watchlist` (`user_id`, `tmdb_movie_id`, `media_type`)
        )");

        // Auto-upgrade schema to support TV Shows vs Movies
        try {
            $conn->exec("ALTER TABLE users ADD COLUMN ai_tokens_limit INT DEFAULT 10 AFTER is_admin");
        } catch (PDOException $e) {}
        try {
            $conn->exec("ALTER TABLE zen_search_history ADD COLUMN is_deleted TINYINT(1) DEFAULT 0 AFTER is_pinned");
        } catch (PDOException $e) {}
        try {
            $conn->exec("ALTER TABLE watch_history ADD COLUMN media_type VARCHAR(10) DEFAULT 'movie' AFTER tmdb_movie_id");
        } catch (PDOException $e) {}
        try {
            $conn->exec("ALTER TABLE watch_history DROP INDEX unique_view");
        } catch (PDOException $e) {}
        try {
            $conn->exec("ALTER TABLE watch_history DROP INDEX user_movie");
        } catch (PDOException $e) {}
        try {
            $conn->exec("ALTER TABLE watch_history ADD UNIQUE KEY `user_media` (user_id, tmdb_movie_id, media_type)");
        } catch (PDOException $e) {}
        // Member status, set under Admin > Members: NULL not verified,
        // 1 verified, 2 suspended (can't sign in).
        try {
            $conn->exec("ALTER TABLE users ADD COLUMN user_status TINYINT NULL DEFAULT NULL");
        } catch (PDOException $e) {}
        // Films the site may play (Admin > Free films), with where they came
        // from and whether they still play.
        try {
            require_once __DIR__ . '/../lib/free_films.php';
            ensureMediaSourcesTable($conn);
        } catch (PDOException $e) {}

        @file_put_contents($schemaMarker, date('c'));
    }

} catch (PDOException $e) {
    // Full details always go to the error log (the Render logs in production).
    // Visitors see them only where display_errors is on, i.e. local WAMP.
    // Elsewhere they get MySQL's error number, which reveals nothing private
    // but tells the cause apart: 2002 host down or unreachable (e.g. a
    // powered-off database), 1045 wrong user or password, 1040 too many
    // connections, 1049 no such database, 2026 SSL problem.
    error_log('DB Connection failed: ' . $e->getMessage());
    $dbErrorRef = (int) $e->getCode() ?: 'setup';
    echo ini_get('display_errors')
        ? "DB Connection failed: " . $e->getMessage()
        : "The site can't reach its database right now. Please try again shortly. (Error {$dbErrorRef})";
}
?>
