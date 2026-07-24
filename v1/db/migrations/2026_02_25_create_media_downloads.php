<?php
/**
 * Migration: Create media_downloads table
 * 
 * Usage: 
 *   php 2026_02_25_create_media_downloads.php up
 *   php 2026_02_25_create_media_downloads.php down
 */

if ($argc < 2) {
    echo "Usage: php " . basename(__FILE__) . " up|down\n";
    exit(1);
}

$action = $argv[1];

try {
    if ($action === 'up') {
        // Check if table already exists
        $check = $conn->query("SHOW TABLES LIKE 'media_downloads'");
        if ($check->rowCount() > 0) {
            echo "Table media_downloads already exists. Nothing to do.\n";
            exit(0);
        }

        $conn->exec("
            CREATE TABLE media_downloads (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tmdb_id INT NOT NULL,
                media_type ENUM('movie','tv') NOT NULL DEFAULT 'movie',
                season INT DEFAULT NULL,
                episode INT DEFAULT NULL,
                quality VARCHAR(20) NOT NULL DEFAULT '720p',
                language VARCHAR(50) NOT NULL DEFAULT 'English',
                file_size VARCHAR(50) DEFAULT NULL,
                file_name VARCHAR(500) DEFAULT NULL,
                download_url TEXT NOT NULL,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_tmdb_lookup (tmdb_id, media_type),
                INDEX idx_episode_lookup (tmdb_id, media_type, season, episode)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "Created media_downloads table.\n";

        // Also create download_logs table
        $check2 = $conn->query("SHOW TABLES LIKE 'download_logs'");
        if ($check2->rowCount() === 0) {
            $conn->exec("
                CREATE TABLE download_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT DEFAULT NULL,
                    tmdb_id INT NOT NULL,
                    media_type VARCHAR(10) NOT NULL,
                    season INT DEFAULT NULL,
                    episode INT DEFAULT NULL,
                    quality VARCHAR(20) DEFAULT NULL,
                    downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user (user_id),
                    INDEX idx_media (tmdb_id, media_type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            echo "Created download_logs table.\n";
        }

        exit(0);
    }

    if ($action === 'down') {
        $conn->exec("DROP TABLE IF EXISTS download_logs");
        $conn->exec("DROP TABLE IF EXISTS media_downloads");
        echo "Dropped media_downloads and download_logs tables.\n";
        exit(0);
    }

    echo "Unknown action: $action\n";
    exit(1);

} catch (PDOException $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(2);
}
