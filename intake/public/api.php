<?php
declare(strict_types=1);

use Atapin\Intake\Archive;
use Atapin\Intake\IntakeError;

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/auth.php';

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow, noarchive');
require_once __DIR__.'/../src/retirement.php';
if (intake_retired()) intake_json(['error' => 'media_library_required', 'desktop_url' => '/desktop'], 410);

try {
    $config = intake_config();
    $origin = $config['origin'];
    $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($requestOrigin !== '' && $requestOrigin !== $origin) throw new IntakeError('access_denied', 403);
    if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') throw new IntakeError('access_denied', 403);
    // Same-origin guard is additional to the shared-password authentication.
    if (($_SERVER['HTTP_X_INTAKE_REQUEST'] ?? '') !== '1') throw new IntakeError('access_denied', 403);
    if (!intake_authenticated($config)) throw new IntakeError('login_required', 401);
    $archive = new Archive($config);
    $action = $_GET['action'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];
    if ($action === 'overview' && $method === 'GET') intake_json($archive->overview());
    if ($method !== 'POST') throw new IntakeError('method_not_allowed', 405);
    if ($action === 'start') {
        $data = json_decode(intake_body(16384), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new IntakeError('invalid_file');
        intake_json($archive->start($data));
    }
    $id = $_GET['id'] ?? '';
    if (!is_string($id)) throw new IntakeError('not_found', 404);
    if ($action === 'chunk') {
        $offset = $_SERVER['HTTP_X_UPLOAD_OFFSET'] ?? '';
        if (!preg_match('/^[0-9]{1,15}$/D', $offset)) throw new IntakeError('invalid_chunk');
        intake_json($archive->append($id, (int)$offset, intake_body(Archive::CHUNK_SIZE), $_SERVER['HTTP_X_CHUNK_SHA256'] ?? ''));
    }
    if ($action === 'finish') intake_json($archive->finish($id));
    throw new IntakeError('not_found', 404);
} catch (IntakeError $error) {
    intake_json(['error' => $error->key], $error->status);
} catch (JsonException $error) {
    intake_json(['error' => 'invalid_file'], 400);
} catch (Throwable $error) {
    // Do not log request bodies, credentials or original file names.
    error_log('Atapin intake failure: ' . get_class($error) . ' code=' . $error->getCode());
    intake_json(['error' => 'server_error'], 500);
}
