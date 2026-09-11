<?php
namespace App\Contracts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
interface SearchProviderInterface
{
    public function media(string $query, ?string $kind = null): LengthAwarePaginator;
}
