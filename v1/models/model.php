<?php
define("DBNAME", getenv('DB_NAME') ?: 'masterpiecemovie');
define("DBUSER", getenv('DB_USER') ?: 'root');
define("DBPASS", getenv('DB_PASSWORD') ?: '');

try {
    $conn = new PDO("mysql:host=localhost;dbname=" . DBNAME, DBUSER, DBPASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Ensure watch_history table exists
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
    
} catch (PDOException $e) {
    echo "DB Connection failed: " . $e->getMessage();
}
?>
