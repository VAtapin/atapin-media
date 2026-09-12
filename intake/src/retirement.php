<?php
declare(strict_types=1);

function intake_retired(): bool
{
    $marker = getenv('INTAKE_PLATFORM_READY') ?: dirname(__DIR__, 3).'/private/atapin-platform/storage/app/private/intake-retired.json';
    if (!is_file($marker) || filesize($marker) > 1024) return false;
    $data = json_decode((string) file_get_contents($marker), true);
    return is_array($data) && ($data['schema'] ?? '') === 'atapin-library-cutover/v1' && !empty($data['completed_at']);
}
