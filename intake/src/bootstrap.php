<?php
declare(strict_types=1);

require_once __DIR__ . '/Archive.php';

function intake_config_path(): string
{
    if ($configured = getenv('INTAKE_CONFIG')) return $configured;
    // In Plesk the checkout may itself be httpdocs. Prefer its private sibling.
    $private = dirname(__DIR__, 3) . '/private/manna-intake-config.php';
    return is_file($private) ? $private : __DIR__ . '/../config.local.php';
}

function intake_config(): array
{
    $file = intake_config_path();
    if (!is_file($file)) throw new \Atapin\Intake\IntakeError('not_configured', 503);
    // Password changes must invalidate existing cookies on the next request,
    // even when FPM/CLI OPcache would otherwise keep the old configuration.
    if (function_exists('opcache_invalidate')) opcache_invalidate($file, true);
    $config = require $file;
    if (!is_array($config) || empty($config['storage_path']) || empty($config['origin'])) {
        throw new \Atapin\Intake\IntakeError('not_configured', 503);
    }
    return $config;
}

function intake_json(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function intake_body(int $limit): string
{
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $limit) throw new \Atapin\Intake\IntakeError('request_too_large', 413);
    $input = fopen('php://input', 'rb');
    $body = stream_get_contents($input, $limit + 1);
    fclose($input);
    if ($body === false || strlen($body) > $limit) throw new \Atapin\Intake\IntakeError('request_too_large', 413);
    return $body;
}
