<?php
namespace App\Services;
use App\Contracts\SearchProviderInterface;
use App\Models\Media;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
class DatabaseSearch implements SearchProviderInterface
{
    public function media(string $query, ?string $kind = null): LengthAwarePaginator
    {
        return Media::query()->when($query !== '', fn ($q) => $q->where('title', 'like', '%'.$query.'%'))
            ->when($kind, fn ($q) => $q->where('kind', $kind))->latest()->paginate(24)->withQueryString();
    }
}
