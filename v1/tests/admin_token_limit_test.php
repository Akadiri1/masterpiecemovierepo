<?php
/**
 * Integration test for AI Token Limit and Admin Privilege dashboard feature.
 * Run with: php v1/tests/admin_token_limit_test.php
 */

require_once __DIR__ . '/../../.env/config.php';
require_once __DIR__ . '/../models/model.php';

function out($m){ echo $m . PHP_EOL; }

try {
    // 1. Check if column exists
    $check = $conn->prepare("SHOW COLUMNS FROM users LIKE 'ai_tokens_limit'");
    $check->execute();
    $col = $check->fetch(PDO::FETCH_ASSOC);
    if (!$col) {
        throw new Exception("Column 'ai_tokens_limit' does not exist in the users table.");
    }
    out("Column 'ai_tokens_limit' exists.");

    // 2. Create a temporary user
    $email = 'test_token_' . time() . '@example.test';
    $username = 'tokenuser_' . rand(10000,99999);
    $passwordHash = password_hash('TestPass!123', PASSWORD_BCRYPT);

    $insert = $conn->prepare("INSERT INTO users (username, email, password, is_admin, ai_tokens_limit, created_at) VALUES (?, ?, ?, 0, 10, NOW())");
    $insert->execute([$username, $email, $passwordHash]);
    $userId = $conn->lastInsertId();
    out("Created test user id={$userId} with limit=10 and is_admin=0");

    // 3. Test update to admin and higher limit
    $updateStmt = $conn->prepare("UPDATE users SET is_admin = 1, ai_tokens_limit = 150 WHERE id = ?");
    $updateStmt->execute([$userId]);
    
    // Fetch and verify
    $selectStmt = $conn->prepare("SELECT is_admin, ai_tokens_limit FROM users WHERE id = ?");
    $selectStmt->execute([$userId]);
    $user = $selectStmt->fetch(PDO::FETCH_ASSOC);
    
    out("User attributes after update -> is_admin: " . var_export($user['is_admin'], true) . ", ai_tokens_limit: " . var_export($user['ai_tokens_limit'], true));
    
    if ((int)$user['is_admin'] !== 1) {
        throw new Exception("Failed to update is_admin status");
    }
    if ((int)$user['ai_tokens_limit'] !== 150) {
        throw new Exception("Failed to update ai_tokens_limit status");
    }

    // 4. Test setting unlimited (-1) limit
    $updateStmt2 = $conn->prepare("UPDATE users SET is_admin = 0, ai_tokens_limit = -1 WHERE id = ?");
    $updateStmt2->execute([$userId]);
    
    $selectStmt->execute([$userId]);
    $user2 = $selectStmt->fetch(PDO::FETCH_ASSOC);
    out("User attributes after second update -> is_admin: " . var_export($user2['is_admin'], true) . ", ai_tokens_limit: " . var_export($user2['ai_tokens_limit'], true));
    
    if ((int)$user2['is_admin'] !== 0) {
        throw new Exception("Failed to revoke is_admin status");
    }
    if ((int)$user2['ai_tokens_limit'] !== -1) {
        throw new Exception("Failed to set ai_tokens_limit to -1");
    }

    // 5. Clean up
    $conn->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
    out("Test passed successfully and cleaned up test user id={$userId}.");

} catch(Exception $e) {
    out('Error: ' . $e->getMessage());
    if (isset($userId)) {
        $conn->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
    }
    exit(1);
}

exit(0);
?>
