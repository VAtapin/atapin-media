<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Media;
use App\Services\Audit;
use App\Services\MediaOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MediaCollectionController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['q' => 'nullable|string|max:120', 'page' => 'nullable|integer|min:1']);
        $query = Collection::where('source', 'local')->withCount('media')->orderBy('title');
        if ($data['q'] ?? '') $query->where('title', 'like', '%'.$data['q'].'%');
        $page = $query->paginate(30);
        return response()->json(['data' => $page->items(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]]);
    }

    public function store(Request $request, Audit $audit)
    {
        $data = $request->validate(['title' => 'required|string|max:255', 'description' => 'nullable|string|max:10000']);
        $collection = Collection::create([...$data, 'source' => 'local', 'source_id' => (string) Str::uuid()]);
        $audit->record('collection.created', (string) $collection->id);
        return response()->json(['id' => $collection->id, 'title' => $collection->title], 201);
    }

    public function show(Request $request, Collection $collection)
    {
        abort_unless($collection->source === 'local', 404);
        $request->validate(['page' => 'nullable|integer|min:1']);
        $page = $collection->media()->orderBy('collection_media.position')->orderBy('media.id')->paginate(30);
        return response()->json(['id' => $collection->id, 'title' => $collection->title, 'description' => $collection->description,
            'data' => $page->getCollection()->map(fn ($media) => ['id' => $media->id, 'title' => $media->title, 'position' => $media->pivot->position,
                'archived' => $media->archived_at !== null, 'download_url' => route('media.download', $media)]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]]);
    }

    public function update(Request $request, Collection $collection, Audit $audit)
    {
        abort_unless($collection->source === 'local', 404);
        $collection->update($request->validate(['title' => 'required|string|max:255', 'description' => 'nullable|string|max:10000']));
        $audit->record('collection.updated', (string) $collection->id);
        return response()->json(['status' => 'saved']);
    }

    public function member(Request $request, Collection $collection, Media $media, MediaOrganization $organization, Audit $audit)
    {
        abort_unless($collection->source === 'local', 404);
        $data = $request->validate(['action' => 'required|in:remove,up,down']);
        if ($data['action'] === 'remove') {
            DB::transaction(function () use ($collection, $media) {
                $locked = Collection::lockForUpdate()->findOrFail($collection->id);
                $locked->media()->detach($media->id);
            });
            $audit->record('collection.member.removed', (string) $collection->id, ['media_id' => $media->id]);
        } else $organization->move($collection, $media, $data['action']);
        return response()->json(['status' => 'saved']);
    }
}
