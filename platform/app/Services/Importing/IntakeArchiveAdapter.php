<?php
namespace App\Services\Importing;

use App\Models\ImportRun;
use App\Services\ArchiveImporter;

class IntakeArchiveAdapter implements ImportAdapter
{
    public function __construct(private ArchiveImporter $archive)
    {
    }

    public function source(): string
    {
        return 'intake';
    }

    public function validate(array $input): array
    {
        return $input;
    }

    public function normalize(array $input): array
    {
        return [
            'source' => 'intake',
            'source_kind' => 'intake',
            'source_ref' => null,
            'target_profile' => $input['target_profile'] ?? 'mixed',
        ];
    }

    public function import(ImportRun $run): void
    {
        $this->archive->runIntake($run);
    }
}
