<?php
/**
 * Migration: Add download support tables
 * 
 * 1. Ensures `media_downloads` table has season/episode/file_name columns
 * 2. Creates `download_logs` table for tracking downloads
 * 
 * Run this migration once to set up download support.
 */

if (!isset($conn)) {
    die("Database connection required.");
}

try {
    // ==========================================
    // 1. ALTER media_downloads (add missing columns if needed)
    // ==========================================
    
    // Check if 'season' column exists
    $cols = $conn->query("SHOW COLUMNS FROM media_downloads")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('season', $cols)) {
        $conn->exec("ALTER TABLE media_downloads 
            ADD COLUMN season INT DEFAULT NULL AFTER media_type,
            ADD COLUMN episode INT DEFAULT NULL AFTER season,
            ADD COLUMN file_name VARCHAR(500) DEFAULT NULL AFTER download_url
        ");
        echo "✅ Added season, episode, file_name columns to media_downloads\n";
    } else {
        echo "ℹ️ media_downloads already has season column, skipping ALTER.\n";
    }

    // ==========================================
    // 2. CREATE download_logs table
    // ==========================================
    $conn->exec("
        CREATE TABLE IF NOT EXISTS download_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL,
            tmdb_id INT NOT NULL,
            media_type VARCHAR(10) NOT NULL DEFAULT 'movie',
            season INT DEFAULT NULL,
            episode INT DEFAULT NULL,
            quality VARCHAR(20) DEFAULT '720p',
            downloaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tmdb (tmdb_id, media_type),
            INDEX idx_user (user_id),
            INDEX idx_date (downloaded_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "✅ download_logs table created (or already exists)\n";

    echo "\n🎉 Download migration complete!\n";

} catch (PDOException $e) {
    echo "❌ Migration error: " . $e->getMessage() . "\n";
}
