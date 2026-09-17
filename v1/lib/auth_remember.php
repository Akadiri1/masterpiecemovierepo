<?php
/**
 * Persistent "remember me" login.
 *
 * The previous attempt stored one bcrypt token on the users row and set a
 * cookie, but nothing ever read that cookie back, so it never signed anyone
 * in. A single column would also have meant one device per user: signing in
 * on a phone would have silently logged the laptop out.
 *
 * Design: a split token, "selector.validator", one row per browser.
 *   - selector  is random and stored as-is; it only locates the row.
 *   - validator is random and only its SHA-256 is stored, so a leaked
 *     database does not hand out working cookies.
 * Looking up by selector and then comparing with hash_equals() avoids the
 * timing leak a "WHERE token = ?" comparison would have.
 *
 * The validator rotates on every silent sign-in. A valid selector presented
 * with a wrong validator means the cookie was probably copied and already used
 * elsewhere, so every token for that user is revoked.
 *
 * Rotation has one real-world trap: a page that fires several requests at once
 * (this site does -- pitches, ZEN limits, watch progress) would race. Two
 * guards handle it: the rotating UPDATE is conditional, so only one request
 * wins, and the previous validator stays valid for a short grace window so the
 * losers are not mistaken for theft.
 */

const AUTH_REMEMBER_COOKIE = 'mpm_remember';
const AUTH_REMEMBER_DAYS   = 60;
const AUTH_REMEMBER_GRACE  = 60; // seconds the previous validator stays valid

function auth_remember_ensure_table(PDO $conn): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $conn->exec("
        CREATE TABLE IF NOT EXISTS user_remember_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            selector CHAR(24) NOT NULL,
            validator_hash CHAR(64) NOT NULL,
            prev_validator_hash CHAR(64) DEFAULT NULL,
            rotated_at DATETIME DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_used_at DATETIME DEFAULT NULL,
            expires_at DATETIME NOT NULL,
            UNIQUE KEY uniq_selector (selector),
            INDEX idx_user (user_id),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $ready = true;
}

function auth_remember_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function auth_remember_user_agent(): string
{
    return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

function auth_remember_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function auth_remember_set_cookie(string $value, int $expires): void
{
    if (!headers_sent()) {
        setcookie(AUTH_REMEMBER_COOKIE, $value, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => auth_remember_is_https(),
            'httponly' => true,     // unreadable from JavaScript
            'samesite' => 'Lax',    // not sent on cross-site POSTs
        ]);
    }

    // Keep the rest of this request consistent with what the browser will hold.
    if ($expires > time() && $value !== '') {
        $_COOKIE[AUTH_REMEMBER_COOKIE] = $value;
    } else {
        unset($_COOKIE[AUTH_REMEMBER_COOKIE]);
    }
}

function auth_remember_clear_cookie(): void
{
    auth_remember_set_cookie('', time() - 3600);

    // Cookie names from the earlier, non-working implementation.
    foreach (['remember_token', 'remember_me'] as $legacy) {
        if (isset($_COOKIE[$legacy])) {
            if (!headers_sent()) {
                setcookie($legacy, '', time() - 3600, '/');
            }
            unset($_COOKIE[$legacy]);
        }
    }
}

/** @return array{selector:string,validator:string}|null */
function auth_remember_parse_cookie(): ?array
{
    $raw = $_COOKIE[AUTH_REMEMBER_COOKIE] ?? '';
    if (!is_string($raw) || !preg_match('/^([a-f0-9]{24})\.([a-f0-9]{64})$/', $raw, $m)) {
        return null;
    }
    return ['selector' => $m[1], 'validator' => $m[2]];
}

/**
 * Populate the session for a signed-in user.
 *
 * Mirrors the keys set by views/includes/ajax/login-backend.php and
 * social-backend.php, so a silently restored session is indistinguishable
 * from one created by typing the password. Keep the three in sync.
 */
function auth_populate_session(PDO $conn, array $user): void
{
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['username']   = $user['username'];
    $_SESSION['email']      = $user['email'];
    $_SESSION['role']       = $user['role'] ?? 'user';
    $_SESSION['avatar_url'] = $user['avatar_url'] ?? null;
    $_SESSION['logged_in']  = true;

    $planId   = !empty($user['current_plan_id']) ? (int) $user['current_plan_id'] : 1;
    $planName = 'free';
    try {
        $p = $conn->prepare("SELECT name FROM plans WHERE id = ? LIMIT 1");
        $p->execute([$planId]);
        $row = $p->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['name'])) {
            $planName = $row['name'];
        }
    } catch (Throwable $e) {
        // plans table missing: keep the free default
    }
    $_SESSION['plan_id']   = $planId;
    $_SESSION['plan_name'] = strtolower($planName);

    $kids = isset($user['is_kids_mode']) && (int) $user['is_kids_mode'] === 1;
    $_SESSION['is_kids_mode'] = $kids;
    $_SESSION['is_kid']       = $kids ? 1 : 0;
}

/** Issue a remembered login for this browser. Call right after a successful sign-in. */
function auth_remember_issue(PDO $conn, int $userId): bool
{
    try {
        auth_remember_ensure_table($conn);

        // One row per browser: replace whatever this browser held before,
        // including a token that belonged to a different account.
        $existing = auth_remember_parse_cookie();
        if ($existing) {
            $conn->prepare("DELETE FROM user_remember_tokens WHERE selector = ?")
                 ->execute([$existing['selector']]);
        }

        $selector  = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $expires   = time() + AUTH_REMEMBER_DAYS * 86400;

        $conn->prepare("
            INSERT INTO user_remember_tokens
                (user_id, selector, validator_hash, user_agent, ip_address, last_used_at, expires_at)
            VALUES (?, ?, ?, ?, ?, NOW(), FROM_UNIXTIME(?))
        ")->execute([
            $userId, $selector, hash('sha256', $validator),
            auth_remember_user_agent(), auth_remember_ip(), $expires,
        ]);

        auth_remember_set_cookie($selector . '.' . $validator, $expires);

        // Opportunistic cleanup instead of a cron job.
        if (random_int(1, 50) === 1) {
            $conn->exec("DELETE FROM user_remember_tokens WHERE expires_at < NOW()");
        }

        return true;
    } catch (Throwable $e) {
        error_log('auth_remember_issue: ' . $e->getMessage());
        return false;
    }
}

/**
 * Restore a session from the remember cookie. Called once per request from
 * www/index.php, before routing. Returns true if it signed the user in.
 */
function auth_remember_try_login(PDO $conn): bool
{
    if (!empty($_SESSION['user_id'])) {
        return false;
    }

    $cookie = auth_remember_parse_cookie();
    if ($cookie === null) {
        if (isset($_COOKIE[AUTH_REMEMBER_COOKIE])) {
            auth_remember_clear_cookie(); // malformed
        }
        return false;
    }

    try {
        auth_remember_ensure_table($conn);

        $stmt = $conn->prepare("
            SELECT *, TIMESTAMPDIFF(SECOND, rotated_at, NOW()) AS since_rotation
              FROM user_remember_tokens
             WHERE selector = ? AND expires_at > NOW()
             LIMIT 1
        ");
        $stmt->execute([$cookie['selector']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            auth_remember_clear_cookie(); // expired or revoked
            return false;
        }

        $presented = hash('sha256', $cookie['validator']);
        $isCurrent = hash_equals($row['validator_hash'], $presented);
        $inGrace   = !$isCurrent
            && !empty($row['prev_validator_hash'])
            && $row['since_rotation'] !== null
            && (int) $row['since_rotation'] <= AUTH_REMEMBER_GRACE
            && hash_equals($row['prev_validator_hash'], $presented);

        if (!$isCurrent && !$inGrace) {
            auth_remember_revoke_all($conn, (int) $row['user_id']);
            auth_remember_clear_cookie();
            error_log(sprintf(
                'auth_remember: stale validator for user %d from %s; all remembered logins revoked',
                (int) $row['user_id'], auth_remember_ip()
            ));
            return false;
        }

        $u = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $u->execute([(int) $row['user_id']]);
        $user = $u->fetch(PDO::FETCH_ASSOC);

        if (!$user || auth_user_is_suspended($user)) {
            $conn->prepare("DELETE FROM user_remember_tokens WHERE user_id = ?")->execute([$row['user_id']]);
            auth_remember_clear_cookie();
            return false;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        session_regenerate_id(true);
        auth_populate_session($conn, $user);
        $_SESSION['auth_via_remember'] = true;

        if ($isCurrent) {
            $newValidator = bin2hex(random_bytes(32));
            $expires      = time() + AUTH_REMEMBER_DAYS * 86400;

            // Conditional on the validator we just checked: if a concurrent
            // request rotated first, this matches nothing and that request's
            // cookie stands.
            $rot = $conn->prepare("
                UPDATE user_remember_tokens
                   SET prev_validator_hash = validator_hash,
                       validator_hash      = ?,
                       rotated_at          = NOW(),
                       last_used_at        = NOW(),
                       expires_at          = FROM_UNIXTIME(?),
                       user_agent          = ?,
                       ip_address          = ?
                 WHERE id = ? AND validator_hash = ?
            ");
            $rot->execute([
                hash('sha256', $newValidator), $expires,
                auth_remember_user_agent(), auth_remember_ip(),
                $row['id'], $presented,
            ]);

            if ($rot->rowCount() === 1) {
                auth_remember_set_cookie($row['selector'] . '.' . $newValidator, $expires);
            }
        }

        return true;
    } catch (Throwable $e) {
        error_log('auth_remember_try_login: ' . $e->getMessage());
        return false;
    }
}

/** Sign this browser out of its remembered login. */
function auth_remember_forget_current(PDO $conn): void
{
    $cookie = auth_remember_parse_cookie();
    if ($cookie) {
        try {
            auth_remember_ensure_table($conn);
            $conn->prepare("DELETE FROM user_remember_tokens WHERE selector = ?")
                 ->execute([$cookie['selector']]);
        } catch (Throwable $e) {
            error_log('auth_remember_forget_current: ' . $e->getMessage());
        }
    }
    auth_remember_clear_cookie();
}

/** Sign every device out. Call whenever a password changes. */
function auth_remember_revoke_all(PDO $conn, int $userId): void
{
    try {
        auth_remember_ensure_table($conn);
        $conn->prepare("DELETE FROM user_remember_tokens WHERE user_id = ?")->execute([$userId]);
    } catch (Throwable $e) {
        error_log('auth_remember_revoke_all: ' . $e->getMessage());
    }
}

/** Accounts suspended under Admin > Members (users.user_status = 2) can't sign in. */
const AUTH_SUSPENDED_STATUS = 2;
const AUTH_SUSPENDED_MESSAGE = 'This account has been suspended. Contact support if you think this is a mistake.';

function auth_user_is_suspended(array $user): bool
{
    return isset($user['user_status']) && (int) $user['user_status'] === AUTH_SUSPENDED_STATUS;
}

/** How often a signed-in member's account is re-read, in seconds. */
const AUTH_SYNC_SECONDS = 60;

/**
 * Applies changes an admin made to a signed-in member (Admin > Members):
 * signs them out if suspended, and switches Kids Mode on or off. The site
 * reads both from the session, which is otherwise only filled at sign-in.
 * Re-read once a minute rather than on every page, so it costs one small
 * query now and then. Members' own Kids Mode switches are saved to the
 * database too (switch-mode.php), so this never undoes them.
 */
function auth_sync_member(PDO $conn): void
{
    if (empty($_SESSION['user_id'])) {
        return;
    }
    if (time() - (int) ($_SESSION['status_checked_at'] ?? 0) < AUTH_SYNC_SECONDS) {
        return;
    }
    try {
        $stmt = $conn->prepare("SELECT user_status, is_kids_mode FROM users WHERE id = ?");
        $stmt->execute([(int) $_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return; // Status column not added yet.
    }
    if ($user && !auth_user_is_suspended($user)) {
        $kids = (int) $user['is_kids_mode'] === 1;
        $_SESSION['is_kids_mode'] = $kids;
        $_SESSION['is_kid'] = $kids ? 1 : 0;
        $_SESSION['status_checked_at'] = time();
        return;
    }
    // Suspended, or the account was deleted.
    auth_remember_revoke_all($conn, (int) $_SESSION['user_id']);
    auth_remember_clear_cookie();
    $_SESSION = [];
    session_regenerate_id(true);
}
