<?php

namespace App\Contracts;

use App\Models\Publication;

interface ManagesPublications
{
    public function actions(): array;
    public function manage(Publication $publication, string $action): array;
}
