<?php

if ($argc < 2) {
    echo "Usage: php " . basename(__FILE__) . " up|down\n";
    exit(1);
}

$action = $argv[1];

try {
    if ($action === 'up') {
        // Add column if it does not exist
        $check = $conn->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_kids_mode'");
        $check->execute();
        if ($check->fetch()) {
            echo "Column is_kids_mode already exists. Nothing to do.\n";
            exit(0);
        }

        $conn->exec("ALTER TABLE users ADD COLUMN is_kids_mode TINYINT(1) DEFAULT 0 COMMENT '0=normal,1=kids' AFTER avatar_url");
        echo "Added is_kids_mode column to users.\n";
        exit(0);
    }

    if ($action === 'down') {
        // Remove column if exists
        $check = $conn->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_kids_mode'");
        $check->execute();
        if (!$check->fetch()) {
            echo "Column is_kids_mode does not exist. Nothing to do.\n";
            exit(0);
        }

        $conn->exec("ALTER TABLE users DROP COLUMN is_kids_mode");
        echo "Dropped is_kids_mode column from users.\n";
        exit(0);
    }

    echo "Unknown action: $action\n";
    exit(1);

} catch (PDOException $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(2);
}
