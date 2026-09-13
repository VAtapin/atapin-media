<?php

namespace App\Contracts;

use App\Models\Publication;

interface PublishingConnector
{
    public function provider(): string;

    /** @return array<string, bool> */
    public function capabilities(): array;

    /** @return array{external_id?:string,external_url?:string,remote_status?:string,payload?:array} */
    public function publish(Publication $publication): array;
}
