<?php
define("DBNAME", getenv('DB_NAME') ?: 'vienna');
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
    
} catch (PDOException $e) {
    echo "DB Connection failed: " . $e->getMessage();
}
?>
