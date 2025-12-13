<?php
/**
 * Lightweight integration test for admin Kids Mode persistence.
 * Run with: php v1/tests/admin_kids_mode_test.php
 * It will create a temporary user, toggle is_kids_mode, and verify DB values.
 */

require_once __DIR__ . '/../includes/config.php';

function out($m){ echo $m . PHP_EOL; }

try {
    // ensure column exists
    $check = $conn->prepare("SHOW COLUMNS FROM users LIKE 'is_kids_mode'");
    $check->execute();
    $col = $check->fetch(PDO::FETCH_ASSOC);
    if (!$col) {
        out("Column 'is_kids_mode' does not exist — test will attempt to create it.");
        $conn->exec("ALTER TABLE users ADD COLUMN is_kids_mode TINYINT(1) DEFAULT 0 AFTER parental_pin_hash");
        out("Column created.");
    } else {
        out("Column 'is_kids_mode' exists.");
    }

    // create a temporary user
    $email = 'test+' . time() . '@example.test';
    $username = 'testuser_' . rand(10000,99999);
    $passwordHash = password_hash('TestPass!123', PASSWORD_BCRYPT);

    $insert = $conn->prepare("INSERT INTO users (username, email, password, created_at) VALUES (?, ?, ?, NOW())");
    $insert->execute([$username, $email, $passwordHash]);
    $userId = $conn->lastInsertId();
    out("Created test user id={$userId}");

    // Toggle ON
    $conn->prepare("UPDATE users SET is_kids_mode = 1 WHERE id = ?")->execute([$userId]);
    $stmt = $conn->prepare("SELECT is_kids_mode FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $val = $stmt->fetch(PDO::FETCH_ASSOC)['is_kids_mode'] ?? null;
    out("After set -> is_kids_mode = " . var_export($val, true));

    if ((int)$val !== 1) throw new Exception('Failed to set is_kids_mode = 1');

    // Toggle OFF
    $conn->prepare("UPDATE users SET is_kids_mode = 0 WHERE id = ?")->execute([$userId]);
    $stmt->execute([$userId]);
    $val2 = $stmt->fetch(PDO::FETCH_ASSOC)['is_kids_mode'] ?? null;
    out("After unset -> is_kids_mode = " . var_export($val2, true));
    if ((int)$val2 !== 0) throw new Exception('Failed to set is_kids_mode = 0');

    // Clean up
    $conn->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
    out("Test passed. Cleaned up test user id={$userId}.");

} catch(Exception $e) {
    out('Error: ' . $e->getMessage());
    exit(1);
}

return 0;
