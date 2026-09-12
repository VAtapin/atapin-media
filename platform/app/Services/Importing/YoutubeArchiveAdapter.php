<?php
namespace App\Services\Importing;

use App\Models\ImportRun;
use App\Services\ArchiveImporter;

class YoutubeArchiveAdapter implements ImportAdapter
{
    public function __construct(private ArchiveImporter $archive)
    {
    }

    public function source(): string
    {
        return 'youtube';
    }

    public function validate(array $input): array
    {
        return $input;
    }

    public function normalize(array $input): array
    {
        return [
            'source' => 'youtube',
            'source_kind' => 'youtube',
            'source_ref' => $input['source_ref'] ?? null,
            'target_profile' => $input['target_profile'] ?? 'mixed',
            'source_options' => [
                'channel_id' => $input['channel_id'] ?? null,
                'playlist_id' => $input['playlist_id'] ?? null,
                'only_unsorted' => (bool) ($input['only_unsorted'] ?? true),
                'notes' => $input['notes'] ?? null,
            ],
        ];
    }

    public function import(ImportRun $run): void
    {
        $this->archive->runYoutube($run);
    }
}
