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
        if ($kinds) $query->whereIn('kind', $kinds)->where(fn ($q) => $q->whereNull('metadata->library_only')->orWhere('metadata->library_only', false));
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
            'target_profile'=>'nullable|in:media_library,videos,shorts,posts,polls,comments',
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

    public function playlist(Request $request, \App\Models\Collection $collection, \App\Services\Importing\ImportedContentPresentation $presentation)
    {
        $request->validate(['page'=>'nullable|integer|min:1']);
        $page = $collection->items()->paginate(100);
        $items = $page->getCollection();
        $records = SourceRecord::where('source',$collection->source)->whereIn('source_id',$items->pluck('source_id')->filter())->get()->keyBy('source_id');
        $explicit = SourceRecord::whereIn('id',$items->pluck('source_record_id')->filter())->get()->keyBy('id');
        $byItem = $items->mapWithKeys(fn($item)=>[$item->id=>$item->source_record_id ? ($explicit[$item->source_record_id]??null) : ($records[$item->source_id??'']??null)]);
        $records=$records->merge($explicit)->filter();
        $ids = $records->flatMap(fn ($record) => app(\App\Services\Importing\LocalMediaLinks::class)->ids($record))->unique();
        $videoIds = Media::whereIn('id', $ids)->where('kind', 'video')->get()->filter(fn ($media) => \Illuminate\Support\Facades\Storage::disk($media->disk)->exists($media->path))->pluck('id')->all();
        return response()->json(['id'=>$collection->id,'title'=>$collection->title,'body'=>$collection->description,'kind'=>'playlist',
            'source'=>$collection->source,'source_id'=>$collection->source_id,'status'=>'','assets'=>[],'private'=>true,
            'external_url' => $presentation->externalUrl($collection->metadata ?? [], $collection->source ?? '', $collection->source_id, 'playlist'),
            'items'=>$items->map(fn ($item)=>['id'=>$item->id,'position'=>$item->position,'title'=>$byItem[$item->id]?->title ?? $item->title,'source_id'=>$item->source_id,
                'has_local_video' => $byItem[$item->id] && (bool) array_intersect(app(\App\Services\Importing\LocalMediaLinks::class)->ids($byItem[$item->id]), $videoIds),
                'external_url' => $presentation->externalUrl([], $collection->source ?? '', $item->source_id, 'video'),
                'availability'=>$item->availability,'detail_url'=>$byItem[$item->id] ? route('content.show',$byItem[$item->id]) : null]),
            'previous_url'=>$page->previousPageUrl(),'next_url'=>$page->nextPageUrl(),
        ]);
    }

    public function show(SourceRecord $record, \App\Services\Importing\ImportedContentPresentation $presentation)
    {
        $metadata = $record->metadata;
        $assets = $presentation->assets($record);
        return response()->json(['id' => $record->id, 'title' => $record->title, 'body' => $record->body,
            'kind' => $record->kind, 'source' => $record->source, 'source_id' => $record->source_id, 'status' => $record->status,
            'parent_source_id' => $metadata['parent_source_id'] ?? null, 'poll' => $metadata['poll'] ?? null,
            'author' => $metadata['author'] ?? null, 'tags' => $metadata['tags'] ?? [], 'classification' => $metadata['classification'] ?? null,
            'target_profile'=>($metadata['library_only']??false) ? 'media_library' : match($record->kind){'video'=>'videos','short'=>'shorts','post'=>'posts','poll'=>'polls','comment'=>'comments'},
            'external_url' => $presentation->externalUrl($metadata ?? [], $record->source, $record->source_id, $record->kind),
            'has_local_video' => $assets->contains(fn ($asset) => $asset['kind'] === 'video' && $asset['available']),
            'import_enriched' => (bool) ($metadata['import_enriched'] ?? false),
            'import_versions' => \App\Models\SourceRecordSnapshot::where('source_record_id',$record->id)->latest()->limit(20)->get(['id','title','created_at'])
                ->map(fn ($snapshot) => ['title'=>$snapshot->title,'created_at'=>$snapshot->created_at,'url'=>route('content.import-version',[$record,$snapshot])]),
            'assets' => $assets, 'private' => true]);
    }

    public function importVersion(SourceRecord $record, \App\Models\SourceRecordSnapshot $snapshot)
    {
        abort_unless($snapshot->source_record_id === $record->id,404);
        return response()->json(['title'=>$snapshot->title,'body'=>$snapshot->body,'kind'=>$snapshot->kind,'metadata'=>$snapshot->metadata]);
    }
}
