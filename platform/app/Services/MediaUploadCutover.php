<?php
namespace App\Services;
use Illuminate\Support\Facades\Storage;

class MediaUploadCutover
{
    public const MARKER = 'intake-retired.json';
    public function confirm(): void
    {
        // Only called after a real desktop upload has been committed successfully.
        // The shared marker lives outside the public root, alongside platform runtime.
        $written = Storage::disk('local')->put(self::MARKER, json_encode([
            'schema' => 'atapin-library-cutover/v1', 'completed_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR));
        if (! $written) logger()->warning('Desktop upload succeeded but intake cutover marker could not be saved.');
    }
}
