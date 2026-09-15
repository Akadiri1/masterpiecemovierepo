<?php
/**
 * Migration: Create ingestion tables
 *
 * Adds the queue that the content scraper writes into. Finished jobs are
 * published into the existing `media_downloads` table, so /download keeps
 * working with no changes.
 *
 * Usage:
 *   php v1/ingest/migrate.php up
 *   php v1/ingest/migrate.php down
 */

if (!isset($conn)) {
    die("Database connection required.\n");
}

$action = $action ?? 'up';

try {
    if ($action === 'up') {

        $conn->exec("
            CREATE TABLE IF NOT EXISTS ingestion_jobs (
                id INT AUTO_INCREMENT PRIMARY KEY,

                -- where the item came from
                source VARCHAR(50) NOT NULL DEFAULT 'archive.org',
                source_identifier VARCHAR(255) NOT NULL,
                source_title VARCHAR(500) NOT NULL,
                source_year INT DEFAULT NULL,
                source_url TEXT NOT NULL,
                source_file_name VARCHAR(500) DEFAULT NULL,
                source_format VARCHAR(100) DEFAULT NULL,
                source_width INT DEFAULT NULL,
                source_height INT DEFAULT NULL,
                source_size BIGINT DEFAULT NULL,

                -- redistribution rights: nothing is queued without these
                license_label VARCHAR(100) DEFAULT NULL,
                license_url VARCHAR(500) DEFAULT NULL,

                -- catalog identity
                tmdb_id INT DEFAULT NULL,
                tmdb_confidence DECIMAL(4,3) DEFAULT NULL,
                tmdb_title VARCHAR(500) DEFAULT NULL,
                media_type ENUM('movie','tv') NOT NULL DEFAULT 'movie',
                quality VARCHAR(20) DEFAULT NULL,

                -- pipeline state
                status ENUM(
                    'discovered',
                    'needs_review',
                    'rejected',
                    'matched',
                    'queued',
                    'transcoding',
                    'published',
                    'failed'
                ) NOT NULL DEFAULT 'discovered',
                status_message TEXT DEFAULT NULL,
                attempts INT NOT NULL DEFAULT 0,
                media_download_id INT DEFAULT NULL,

                -- Audit trail. Clearing an ambiguous licence is a human
                -- assertion of redistribution rights, so record who made it.
                reviewed_by INT DEFAULT NULL,
                reviewed_at DATETIME DEFAULT NULL,
                review_note VARCHAR(500) DEFAULT NULL,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY uniq_source_item (source, source_identifier, quality),
                INDEX idx_status (status),
                INDEX idx_tmdb (tmdb_id, media_type),
                INDEX idx_review (status, tmdb_confidence)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "ingestion_jobs table ready.\n";

        $conn->exec("
            CREATE TABLE IF NOT EXISTS ingestion_runs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                source VARCHAR(50) NOT NULL DEFAULT 'archive.org',
                collection VARCHAR(255) DEFAULT NULL,
                items_seen INT NOT NULL DEFAULT 0,
                items_new INT NOT NULL DEFAULT 0,
                items_rejected INT NOT NULL DEFAULT 0,
                items_matched INT NOT NULL DEFAULT 0,
                items_needs_review INT NOT NULL DEFAULT 0,
                started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                finished_at DATETIME DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                INDEX idx_started (started_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "ingestion_runs table ready.\n";

        // Upgrade path for installs created before the review columns existed.
        $cols = $conn->query("SHOW COLUMNS FROM ingestion_jobs")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('reviewed_by', $cols, true)) {
            $conn->exec("ALTER TABLE ingestion_jobs
                ADD COLUMN reviewed_by INT DEFAULT NULL AFTER media_download_id,
                ADD COLUMN reviewed_at DATETIME DEFAULT NULL AFTER reviewed_by,
                ADD COLUMN review_note VARCHAR(500) DEFAULT NULL AFTER reviewed_at
            ");
            echo "Added review audit columns to ingestion_jobs.\n";
        }

        // Creator, kept for attribution. CC-BY and CC-BY-SA require crediting
        // the author wherever the work is distributed, so this has to travel
        // with the file rather than living only in the scraper's memory.
        if (!in_array('source_creator', $cols, true)) {
            $conn->exec("ALTER TABLE ingestion_jobs
                ADD COLUMN source_creator VARCHAR(500) DEFAULT NULL AFTER source_year
            ");
            echo "Added source_creator to ingestion_jobs.\n";
        }

        // Carry licence + attribution onto the published download itself, so
        // the front end can display credit next to the file it applies to.
        $dlCols = $conn->query("SHOW COLUMNS FROM media_downloads")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('license_label', $dlCols, true)) {
            $conn->exec("ALTER TABLE media_downloads
                ADD COLUMN license_label VARCHAR(100) DEFAULT NULL,
                ADD COLUMN license_url VARCHAR(500) DEFAULT NULL,
                ADD COLUMN source_attribution VARCHAR(500) DEFAULT NULL
            ");
            echo "Added licence/attribution columns to media_downloads.\n";
        }

        // Repair schema drift. The live table was created without columns that
        // the rest of the app already relies on: views/download.php filters on
        // is_active and selects file_name, and admin/manage_downloads.php
        // inserts file_name and toggles is_active. Without these, the movie
        // download lookup fails outright.
        if (!in_array('file_name', $dlCols, true)) {
            $conn->exec("ALTER TABLE media_downloads
                ADD COLUMN file_name VARCHAR(500) DEFAULT NULL AFTER download_url
            ");
            echo "Added missing file_name column to media_downloads.\n";
        }
        if (!in_array('is_active', $dlCols, true)) {
            // Default 1 so links that already exist stay served.
            $conn->exec("ALTER TABLE media_downloads
                ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1
            ");
            echo "Added missing is_active column to media_downloads.\n";
        }
        if (!in_array('created_at', $dlCols, true)) {
            $conn->exec("ALTER TABLE media_downloads
                ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ");
            echo "Added missing timestamp columns to media_downloads.\n";
        }

        echo "\nIngestion migration complete.\n";
        return;
    }

    if ($action === 'down') {
        $conn->exec("DROP TABLE IF EXISTS ingestion_jobs");
        $conn->exec("DROP TABLE IF EXISTS ingestion_runs");
        echo "Dropped ingestion_jobs and ingestion_runs tables.\n";
        return;
    }

    echo "Unknown action: {$action}\n";

} catch (PDOException $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
}
