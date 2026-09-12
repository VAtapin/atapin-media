<?php
namespace App\Services\Importing;

use App\Models\ImportRun;

class PlannedServiceAdapter implements ImportAdapter
{
    public function __construct(private string $source)
    {
    }

    public function source(): string
    {
        return $this->source;
    }

    public function validate(array $input): array
    {
        if (empty($input['source_ref'])) {
            throw new \InvalidArgumentException('Service import requires a public source URL.');
        }
        return $input;
    }

    public function normalize(array $input): array
    {
        return [
            'source' => $this->source,
            'source_kind' => $this->source,
            'source_ref' => $input['source_ref'],
            'source_options' => ['source_ref' => $input['source_ref'], 'notes' => $input['notes'] ?? null],
            'target_profile' => $input['target_profile'] ?? 'mixed',
        ];
    }

    public function import(ImportRun $run): void
    {
        $label = match ($run->source) {
            'youtube-service' => 'YouTube',
            'tiktok' => 'TikTok',
            'instagram' => 'Instagram',
            'facebook-video' => 'Facebook',
            default => $run->source,
        };

        $run->update([
            'status' => 'partial',
            'notes' => [
                sprintf(
                    '%s import is intentionally prepared for future API connector integration. For now: download export to private/import-inbox and run Local Folder/Archive import.',
                    $label
                ),
            ],
            'source_ref' => $run->source_ref,
            'target_profile' => $run->target_profile ?? 'mixed',
        ]);
    }
}
