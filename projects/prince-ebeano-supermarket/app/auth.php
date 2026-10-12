<?php
declare(strict_types=1);

const LOGIN_MAX_FAILURES = 5;
const LOGIN_WINDOW_MINUTES = 15;

function current_account(): ?array
{
    static $account = false;
    if ($account !== false) {
        return $account;
    }
    $account = null;
    $id = $_SESSION['account_id'] ?? null;
    if ($id) {
        $row = q_one('SELECT * FROM accounts WHERE id = ? AND is_active = 1', [$id]);
        if ($row) {
            $account = $row;
        } else {
            unset($_SESSION['account_id'], $_SESSION['staff_name']);
        }
    }
    return $account;
}

function is_owner(): bool
{
    return (current_account()['role'] ?? '') === 'owner';
}

function staff_name(): string
{
    return (string)($_SESSION['staff_name'] ?? '');
}

function require_login(): array
{
    $account = current_account();
    if (!$account) {
        redirect(url('admin/login.php'));
    }
    return $account;
}

function require_owner(): array
{
    $account = require_login();
    if ($account['role'] !== 'owner') {
        abort(403, 'Only the owner account can open this page.');
    }
    return $account;
}

/**
 * The branch + category a staff login is locked to.
 * Owners are not locked and get [null, null].
 */
function staff_scope(): array
{
    $a = require_login();
    if ($a['role'] === 'owner') {
        return [null, null];
    }
    return [(int)$a['branch_id'], (int)$a['category_id']];
}

function can_edit_branch_category(int $branchId, int $categoryId): bool
{
    [$b, $c] = staff_scope();
    return $b === null || ($b === $branchId && $c === $categoryId);
}

function can_edit_category(int $categoryId): bool
{
    [, $c] = staff_scope();
    return $c === null || $c === $categoryId;
}

// ---------------------------------------------------------------- login throttling

function too_many_login_attempts(string $username): bool
{
    $count = (int)q_val(
        'SELECT COUNT(*) FROM login_attempts
         WHERE (ip = ? OR username = ?) AND attempted_at > (NOW() - INTERVAL ' . LOGIN_WINDOW_MINUTES . ' MINUTE)',
        [client_ip(), $username]
    );
    return $count >= LOGIN_MAX_FAILURES;
}

function record_failed_login(string $username): void
{
    q('INSERT INTO login_attempts (ip, username) VALUES (?, ?)', [client_ip(), $username]);
    // Housekeeping: drop rows older than a day.
    q('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
}

function clear_failed_logins(string $username): void
{
    q('DELETE FROM login_attempts WHERE ip = ? OR username = ?', [client_ip(), $username]);
}

/** Returns the account on success, or an error message string. */
function attempt_login(string $username, string $password, string $staffName)
{
    if (too_many_login_attempts($username)) {
        return 'Too many wrong attempts. Please wait ' . LOGIN_WINDOW_MINUTES . ' minutes and try again.';
    }
    $account = q_one('SELECT * FROM accounts WHERE username = ?', [$username]);
    if (!$account || !password_verify($password, $account['password_hash'])) {
        record_failed_login($username);
        return 'That username or password is not right.';
    }
    if (!(int)$account['is_active']) {
        return 'This login has been switched off. Please ask the owner.';
    }
    if ($account['role'] === 'staff' && $staffName === '') {
        return 'Please type your name so we know who made today\'s update.';
    }
    if (password_needs_rehash($account['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE accounts SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $account['id']]);
    }
    clear_failed_logins($username);
    session_regenerate_id(true);
    $_SESSION['account_id'] = (int)$account['id'];
    $_SESSION['staff_name'] = $staffName !== '' ? $staffName : $account['username'];
    q('UPDATE accounts SET last_login_at = NOW() WHERE id = ?', [$account['id']]);
    return $account;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Human label for an account, e.g. "Ikeja · Drinks". */
function account_scope_label(array $account): string
{
    if ($account['role'] === 'owner') {
        return 'Owner · all branches';
    }
    $b = branch_by_id((int)$account['branch_id']);
    $c = category_by_id((int)$account['category_id']);
    return ($b['name'] ?? '?') . ' · ' . ($c['name'] ?? '?');
}
