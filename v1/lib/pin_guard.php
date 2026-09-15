<?php
/**
 * Limits guesses at the parental PIN (and at the account password when it is
 * used to reset a forgotten PIN), per session. After 5 wrong tries the next
 * try has to wait: 1 minute, then 2, 4 and so on, up to 30 minutes.
 */

const PIN_MAX_ATTEMPTS = 5;

/** Seconds until another try is allowed; 0 when not locked. */
function pinLockSeconds(): int
{
    return max(0, (int) ($_SESSION['pin_locked_until'] ?? 0) - time());
}

/** Records a wrong try. Returns the tries left before a lock; 0 means it is now locked. */
function pinRecordFailure(): int
{
    $_SESSION['pin_failures'] = (int) ($_SESSION['pin_failures'] ?? 0) + 1;
    $left = PIN_MAX_ATTEMPTS - $_SESSION['pin_failures'];
    if ($left > 0) {
        return $left;
    }

    $_SESSION['pin_lockouts'] = (int) ($_SESSION['pin_lockouts'] ?? 0) + 1;
    $_SESSION['pin_locked_until'] = time() + min(1800, 60 * (2 ** ($_SESSION['pin_lockouts'] - 1)));
    $_SESSION['pin_failures'] = 0;
    return 0;
}

function pinRecordSuccess(): void
{
    unset($_SESSION['pin_failures'], $_SESSION['pin_lockouts'], $_SESSION['pin_locked_until']);
}

/** "45 seconds", "2 minutes". */
function pinWaitText(int $seconds): string
{
    if ($seconds < 60) {
        return $seconds . ' second' . ($seconds === 1 ? '' : 's');
    }
    $minutes = (int) ceil($seconds / 60);
    return $minutes . ' minute' . ($minutes === 1 ? '' : 's');
}
