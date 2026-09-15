<?php
/**
 * Migration: AI hook cache + usage log
 *
 * 1. `ai_hooks` replaces cache/ai_hooks.json. The JSON file was a whole-file
 *    read-modify-write with no locking, so concurrent generations silently
 *    dropped entries, and it grew without bound.
 *
 * 2. `ai_usage_log` records every AI endpoint call. It gives /ai-hook the
 *    per-user rate limiting it never had, and backs the admin AI dashboard.
 *
 * Usage:
 *   php v1/db/migrate_ai.php up
 *   php v1/db/migrate_ai.php down
 */

if (!isset($conn)) {
    die("Database connection required.\n");
}

$action = $action ?? 'up';

try {
    if ($action === 'up') {

        $conn->exec("
            CREATE TABLE IF NOT EXISTS ai_hooks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tmdb_id INT NOT NULL,
                media_type ENUM('movie','tv') NOT NULL DEFAULT 'movie',

                -- the title the hook was actually generated from, resolved
                -- server-side from TMDB so it cannot be spoofed by the caller
                title VARCHAR(500) DEFAULT NULL,
                hook TEXT NOT NULL,
                model VARCHAR(100) DEFAULT NULL,

                -- who triggered generation, for auditing a bad hook
                generated_by INT DEFAULT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY uniq_media (tmdb_id, media_type),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "ai_hooks table ready.\n";

        $conn->exec("
            CREATE TABLE IF NOT EXISTS ai_usage_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT DEFAULT NULL,
                endpoint VARCHAR(50) NOT NULL,
                tmdb_id INT DEFAULT NULL,

                -- 'hit' served from cache, 'generated' called the model,
                -- 'blocked' refused by auth or rate limit, 'error' upstream failure
                status VARCHAR(20) NOT NULL DEFAULT 'generated',
                detail VARCHAR(255) DEFAULT NULL,
                ip VARBINARY(16) DEFAULT NULL,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                INDEX idx_user_day (user_id, created_at),
                INDEX idx_endpoint (endpoint, created_at),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "ai_usage_log table ready.\n";

        // ---- one-time import of the existing JSON cache -------------------
        $jsonPath = __DIR__ . '/../../cache/ai_hooks.json';
        if (is_readable($jsonPath)) {
            $existing = json_decode((string) file_get_contents($jsonPath), true);
            if (is_array($existing) && $existing) {
                $ins = $conn->prepare("
                    INSERT INTO ai_hooks (tmdb_id, media_type, hook, model)
                    VALUES (?, 'movie', ?, 'imported-from-json')
                    ON DUPLICATE KEY UPDATE hook = VALUES(hook)
                ");
                $imported = 0;
                foreach ($existing as $mediaId => $hook) {
                    if (!is_string($hook) || trim($hook) === '') {
                        continue;
                    }
                    // Keys were the raw media_id posted by the client.
                    if (!ctype_digit((string) $mediaId)) {
                        continue;
                    }
                    $ins->execute([(int) $mediaId, trim($hook)]);
                    $imported++;
                }
                echo "Imported {$imported} hooks from ai_hooks.json.\n";
                echo "NOTE: imported hooks were generated from client-supplied titles.\n";
                echo "      Review them in the admin AI page before trusting them.\n";
            }
        }

        echo "\nAI migration complete.\n";
        return;
    }

    if ($action === 'down') {
        $conn->exec("DROP TABLE IF EXISTS ai_usage_log");
        $conn->exec("DROP TABLE IF EXISTS ai_hooks");
        echo "Dropped ai_hooks and ai_usage_log tables.\n";
        return;
    }

    echo "Unknown action: {$action}\n";

} catch (PDOException $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
}
