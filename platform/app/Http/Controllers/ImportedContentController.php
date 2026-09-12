<?php
namespace App\Http\Controllers;
use App\Models\Media;
use App\Models\SourceRecord;
use Illuminate\Http\Request;

class ImportedContentController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['q' => 'nullable|string|max:120', 'kind' => 'nullable|in:video,short,post,poll,comment',
            'section' => 'nullable|in:videos,posts,community', 'status' => 'nullable|in:unsorted,review,ready,needs_attention',
            'source' => 'nullable|string|max:32', 'page' => 'nullable|integer|min:1']);
        $query = SourceRecord::query()->latest();
        $kinds = match ($data['section'] ?? '') { 'videos' => ['video', 'short'], 'posts' => ['post'], 'community' => ['poll', 'comment'], default => [] };
        if ($kinds) $query->whereIn('kind', $kinds);
        foreach (['kind', 'source', 'status'] as $field) if ($data[$field] ?? '') $query->where($field, $data[$field]);
        if ($data['q'] ?? '') $query->where(fn ($q) => $q->where('title', 'like', '%'.$data['q'].'%')->orWhere('body', 'like', '%'.$data['q'].'%'));
        $page = $query->paginate(30);
        return response()->json(['data' => $page->getCollection()->map(fn ($record) => [
            'id' => $record->id, 'title' => $record->title, 'body' => mb_substr($record->body ?? '', 0, 250),
            'kind' => $record->kind, 'source' => $record->source, 'status' => $record->status,
            'detail_url' => route('content.show', $record),
        ]), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]]);
    }

    public function update(Request $request, SourceRecord $record, \App\Services\Importing\ContentAssignment $assignment)
    {
        $assignment->record($record, $request->validate(['title' => 'required|string|max:255', 'body' => 'nullable|string|max:1000000',
            'kind' => 'required|in:video,short,post,poll,comment', 'status' => 'required|in:unsorted,ready,needs_attention',
            'tags' => 'nullable|array|max:30', 'tags.*' => 'string|max:100']));
        return response()->json(['status' => 'saved']);
    }

    public function playlists(Request $request)
    {
        $data = $request->validate(['q'=>'nullable|string|max:120','page'=>'nullable|integer|min:1']);
        $query = \App\Models\Collection::withCount('items')->latest();
        if ($data['q']??'') $query->where('title','like','%'.$data['q'].'%');
        $page = $query->paginate(30);
        return response()->json(['data'=>$page->getCollection()->map(fn ($item)=>[
            'id'=>$item->id,'title'=>$item->title,'body'=>$item->items_count.' '.__('imports.playlist_items'),
            'kind'=>'playlist','source'=>$item->source,'status'=>'', 'detail_url'=>route('content.playlist',$item),
        ]),'meta'=>['current_page'=>$page->currentPage(),'last_page'=>$page->lastPage(),'total'=>$page->total()]]);
    }

    public function playlist(Request $request, \App\Models\Collection $collection)
    {
        $request->validate(['page'=>'nullable|integer|min:1']);
        $page = $collection->items()->paginate(100);
        $items = $page->getCollection();
        $records = SourceRecord::where('source',$collection->source)->whereIn('source_id',$items->pluck('source_id')->filter())->get()->keyBy('source_id');
        return response()->json(['id'=>$collection->id,'title'=>$collection->title,'body'=>$collection->description,'kind'=>'playlist',
            'source'=>$collection->source,'source_id'=>$collection->source_id,'status'=>'','assets'=>[],'private'=>true,
            'items'=>$items->map(fn ($item)=>['position'=>$item->position,'title'=>$item->title,'source_id'=>$item->source_id,
                'availability'=>$item->availability,'detail_url'=>isset($records[$item->source_id??'']) ? route('content.show',$records[$item->source_id]) : null]),
            'previous_url'=>$page->previousPageUrl(),'next_url'=>$page->nextPageUrl(),
        ]);
    }

    public function show(SourceRecord $record)
    {
        $metadata = $record->metadata;
        $ids = [...($metadata['media_ids'] ?? []), ...($metadata['images'] ?? [])];
        foreach ($metadata['media'] ?? [] as $assets) $ids = [...$ids, ...$assets];
        return response()->json(['id' => $record->id, 'title' => $record->title, 'body' => $record->body,
            'kind' => $record->kind, 'source' => $record->source, 'source_id' => $record->source_id, 'status' => $record->status,
            'parent_source_id' => $metadata['parent_source_id'] ?? null, 'poll' => $metadata['poll'] ?? null,
            'author' => $metadata['author'] ?? null, 'tags' => $metadata['tags'] ?? [], 'classification' => $metadata['classification'] ?? null,
            'assets' => Media::whereIn('id', array_unique($ids))->get()->map(fn ($media) => [
                'title' => $media->title, 'kind' => $media->kind, 'mime' => $media->mime,
                'download_url' => route('media.download', $media),
                'preview_url' => in_array($media->mime, ['image/jpeg','image/png','image/webp','image/gif','audio/mpeg','audio/ogg','video/mp4','video/webm','application/pdf'], true) ? route('media.preview', $media) : null,
            ]), 'private' => true]);
    }
}
