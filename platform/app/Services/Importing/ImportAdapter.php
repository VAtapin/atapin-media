<?php
namespace App\Services\Importing;

use App\Models\ImportRun;

interface ImportAdapter
{
    public function source(): string;

    public function validate(array $input): array;

    public function normalize(array $input): array;

    public function import(ImportRun $run): void;
}
