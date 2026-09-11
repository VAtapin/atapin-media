<?php
declare(strict_types=1);

use Atapin\Intake\IntakeError;

const INTAKE_COOKIE = 'manna_intake_access';
const INTAKE_LOGIN_DAYS = 30;

function intake_password_hash(array $config): string
{
    $hash = $config['password_hash'] ?? '';
    if (!is_string($hash) || (password_get_info($hash)['algoName'] ?? 'unknown') === 'unknown') {
        throw new IntakeError('not_configured', 503);
    }
    return $hash;
}

function intake_token(array $config, int $expires): string
{
    $payload = $expires . '.' . bin2hex(random_bytes(16));
    return $payload . '.' . hash_hmac('sha256', $payload, intake_password_hash($config));
}

function intake_authenticated(array $config): bool
{
    $secret = intake_password_hash($config); // Missing password always fails closed, including old installations.
    $token = $_COOKIE[INTAKE_COOKIE] ?? '';
    if (!is_string($token) || !preg_match('/^([0-9]{10})\.([a-f0-9]{32})\.([a-f0-9]{64})$/D', $token, $parts)) return false;
    $expires = (int)$parts[1];
    return $expires > time() && $expires <= time() + INTAKE_LOGIN_DAYS * 86400
        && hash_equals(hash_hmac('sha256', $parts[1] . '.' . $parts[2], $secret), $parts[3]);
}

function intake_cookie(array $config, bool $login): void
{
    $expires = $login ? time() + INTAKE_LOGIN_DAYS * 86400 : time() - 3600;
    setcookie(INTAKE_COOKIE, $login ? intake_token($config, $expires) : '', [
        'expires' => $expires, 'path' => '/', 'secure' => str_starts_with($config['origin'], 'https://'),
        'httponly' => true, 'samesite' => 'Strict',
    ]);
}

function intake_same_origin(array $config): void
{
    if (($_SERVER['HTTP_ORIGIN'] ?? '') !== $config['origin'] || ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') {
        throw new IntakeError('access_denied', 403);
    }
}

function intake_login(array $config, string $password): bool
{
    $hash = intake_password_hash($config);
    // Store only keyed IP digests, never passwords. Ignore untrusted forwarding headers.
    $ip = hash_hmac('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown', $hash);
    $db = new PDO('sqlite:' . $config['storage_path'] . '/access.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA busy_timeout=5000');
    $db->exec('CREATE TABLE IF NOT EXISTS attempts (ip TEXT PRIMARY KEY, started INTEGER NOT NULL, count INTEGER NOT NULL)');
    $db->exec('BEGIN IMMEDIATE');
    try {
        $now = time();
        $db->prepare('DELETE FROM attempts WHERE started <= ?')->execute([$now - 900]);
        $query = $db->prepare('SELECT count FROM attempts WHERE ip = ?');
        $query->execute([$ip]);
        $count = (int)$query->fetchColumn();
        $total = (int)$db->query('SELECT COALESCE(SUM(count), 0) FROM attempts')->fetchColumn();
        if ($count >= 5 || $total >= 100) throw new IntakeError('login_limited', 429);
        $db->prepare('INSERT INTO attempts (ip, started, count) VALUES (?, ?, 1) ON CONFLICT(ip) DO UPDATE SET count = count + 1')->execute([$ip, $now]);
        $db->exec('COMMIT');
    } catch (Throwable $error) {
        $db->exec('ROLLBACK');
        throw $error;
    }
    $valid = strlen($password) <= 72 && password_verify($password, $hash);
    if ($valid) $db->prepare('DELETE FROM attempts WHERE ip = ?')->execute([$ip]);
    return $valid;
}
