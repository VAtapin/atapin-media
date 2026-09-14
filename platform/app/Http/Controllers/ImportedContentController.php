<?php
namespace App\Http\Controllers;
use App\Models\Media;
use App\Models\SourceRecord;
use Illuminate\Http\Request;

class ImportedContentController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['q' => 'nullable|string|max:120', 'kind' => 'nullable|in:'.implode(',',SourceRecord::KINDS).',archive_data',
            'section' => 'nullable|in:videos,posts,podcast,community', 'status' => 'nullable|in:unsorted,review,ready,needs_attention',
            'publication' => 'nullable|in:published,unpublished',
            'source' => 'nullable|string|max:32', 'page' => 'nullable|integer|min:1','trash'=>'nullable|in:active,deleted']);
        $query = SourceRecord::query()->where('source','!=','catalog-reset')->latest();
        if(($data['trash']??'active')==='deleted')$query->onlyTrashed();
        $kinds = match ($data['section'] ?? '') { 'videos', 'podcast' => ['video', 'short'], 'posts' => ['post'], 'community' => ['poll', 'comment','live_chat'], default => [] };
        if (($data['section'] ?? '') === 'podcast') $query->where('metadata->public_section', 'podcast');
        elseif (($data['section'] ?? '') === 'videos') $query->where(fn($q) => $q->whereNull('metadata->public_section')->orWhere('metadata->public_section', '!=', 'podcast'));
        if ($kinds) $query->whereIn('kind', $kinds)->where(fn ($q) => $q->whereNull('metadata->library_only')->orWhere('metadata->library_only', false));
        if(($data['kind']??'')==='archive_data') {$query->where('metadata->archive_data',true);unset($data['kind']);}
        elseif(empty($data['kind']))$query->where(fn($q)=>$q->whereNull('metadata->archive_data')->orWhere('metadata->archive_data',false));
        foreach (['kind', 'source', 'status'] as $field) if ($data[$field] ?? '') $query->where($field, $data[$field]);
        if (($data['publication'] ?? '') === 'published') $query->where('metadata->public_published', true);
        if (($data['publication'] ?? '') === 'unpublished') $query->where(fn ($q) => $q->whereNull('metadata->public_published')->orWhere('metadata->public_published', false));
        if ($data['q'] ?? '') $query->where(fn ($q) => $q->where('title', 'like', '%'.$data['q'].'%')->orWhere('body', 'like', '%'.$data['q'].'%'));
        $page = $query->paginate(30);
        return response()->json(['data' => $page->getCollection()->map(fn ($record) => [
            'id' => $record->id, 'title' => $record->title, 'body' => mb_substr($record->body ?? '', 0, 250),
            'kind' => $record->kind, 'source' => $record->source, 'status' => $record->status,
            'public_published' => (bool) ($record->metadata['public_published'] ?? false),
            'public_homepage' => (bool) ($record->metadata['public_homepage'] ?? false),
            'detail_url' => route('content.show', $record),
        ]), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]]);
    }

    public function store(Request $request, \App\Services\Importing\ContentAssignment $assignment)
    {
        $data = $request->validate(['title'=>'required|string|max:255','body'=>'nullable|string|max:1000000',
            'kind'=>'required|in:video,short,post','public_section'=>'required|in:videos,beitraege,podcast','status'=>'required|in:unsorted,ready,needs_attention']);
        $record = SourceRecord::create(['source'=>'manual','source_id'=>(string)\Illuminate\Support\Str::uuid(),
            'title'=>$data['title'],'body'=>$data['body'] ?? '','kind'=>$data['kind'],'status'=>$data['status'],
            'metadata'=>['public_section'=>$data['public_section'],'public_published'=>false,'classification_origin'=>'manual']]);
        app(\App\Services\Audit::class)->record('content.created',(string)$record->id);
        return response()->json(['status'=>'saved','id'=>$record->id,'detail_url'=>route('content.show',$record)],201);
    }

    public function update(Request $request, SourceRecord $record, \App\Services\Importing\ContentAssignment $assignment)
    {
        if($request->hasAny(['public_published','public_section','public_homepage'])||($record->metadata['public_published']??false)||($record->metadata['public_homepage']??false))\Illuminate\Support\Facades\Gate::authorize('content.publish');
        if($request->has('platform_metadata'))\Illuminate\Support\Facades\Gate::authorize('content.publish');
        $assignment->record($record, $request->validate(['title' => 'required|string|max:255', 'body' => 'nullable|string|max:1000000',
            'kind' => 'required|in:'.implode(',',SourceRecord::KINDS), 'status' => 'required|in:unsorted,review,ready,needs_attention',
            'target_profile'=>'nullable|in:media_library,videos,shorts,posts,polls,comments,podcast',
            'public_published'=>'sometimes|boolean','public_homepage'=>'sometimes|boolean',
            'public_section'=>'sometimes|in:videos,beitraege,podcast,live,community',
            'short_description'=>'nullable|string|max:300',
            'tags' => 'nullable|array|max:30', 'tags.*' => 'string|max:100', ...\App\Services\ContentEditorData::rules()]));
        return response()->json(['status' => 'saved']);
    }

    public function playlists(Request $request)
    {
        $data = $request->validate(['q'=>'nullable|string|max:120','page'=>'nullable|integer|min:1']);
        $query = \App\Models\Collection::withCount('items')->where('source','!=','catalog-reset')->latest();
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
        $videoIds = Media::whereIn('id', $ids)->where('kind', 'video')->get()->filter(fn ($media) => app(\App\Services\MediaOriginalLocator::class)->find($media))->pluck('id')->all();
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
        $publicContent=app(\App\Services\PublicContent::class);
        $publicUrl=$publicContent->visible($record)?$publicContent->card($record)['url']:null;
        $version=app(\App\Services\Importing\ContentState::class)->version($record);
        return response()->json(['id' => $record->id, 'title' => $record->title, 'body' => ($metadata['body_format']??'plain')==='html'?app(\App\Services\RichContent::class)->sanitize($record->body??''):$record->body,
            ...array_intersect_key($metadata,array_flip(['workflow_stage','slug','locale','episode_number','season'])),
            ...array_intersect_key($metadata,array_flip(['author','seo_title','seo_description','transcript','guest','external_podcast_url','cover_media_id','taxonomy_term_ids'])),
            'short_description'=>$metadata['short_description']??'', 'short_description_job'=>$metadata['short_description_job']['state']??null,
            'project_id'=>$record->project_id,'editor'=>array_intersect_key($metadata,array_flip(['author','seo_title','seo_description','transcript','guest','external_podcast_url','cover_media_id','taxonomy_term_ids'])),
            'media_jobs'=>$metadata['media_jobs']??[],
            'body_format'=>$metadata['body_format']??'plain','platform_metadata'=>$metadata['platform_metadata']??[],'pdf_job'=>$metadata['pdf_job']??null,
            'trashed'=>$record->trashed(),
            'kind' => $record->kind, 'source' => $record->source, 'source_id' => $record->source_id, 'status' => $record->status,
            'parent_source_id' => $metadata['parent_source_id'] ?? null, 'poll' => $metadata['poll'] ?? null,
            'author' => $metadata['author'] ?? null, 'tags' => $metadata['tags'] ?? [], 'classification' => $metadata['classification'] ?? null,
            'target_profile'=>($metadata['library_only']??false) ? 'media_library' : match($record->kind){'video'=>'videos','short'=>'shorts','post'=>'posts','poll'=>'polls','comment'=>'comments',default=>'media_library'},
            'archive_data'=>(bool)($metadata['archive_data']??false),'takeout_data'=>$metadata['takeout_data']??[],
            'public_published'=>(bool)($metadata['public_published']??false),'public_homepage'=>(bool)($metadata['public_homepage']??false),
            'public_section'=>$publicContent->section($record),
            'public_url'=>$publicUrl,'preview_url'=>in_array($record->kind,['video','short','post'])?route('content.preview',$record):null,
            'references'=>$presentation->references($record),
            'external_url' => $presentation->externalUrl($metadata ?? [], $record->source, $record->source_id, $record->kind),
            'has_local_video' => $assets->contains(fn ($asset) => $asset['kind'] === 'video' && $asset['available']),
            'classifications'=>$record->classifications()->latest()->limit(20)->get()->map(fn($log)=>[
                'id'=>$log->id,'provider'=>$log->provider,'model'=>$log->model,'status'=>$log->status,'confidence'=>$log->confidence,'proposal'=>$log->proposal,'created_at'=>$log->created_at,
                'undo_url'=>$log->status==='applied'&&($log->applied_changes['after_version']??null)===$version ? route('content.classification.undo',[$record,$log]) : null,
            ]),
            'import_enriched' => (bool) ($metadata['import_enriched'] ?? false),
            'import_versions' => \App\Models\SourceRecordSnapshot::where('source_record_id',$record->id)->latest()->limit(20)->get(['id','title','created_at'])
                ->map(fn ($snapshot) => ['title'=>$snapshot->title,'created_at'=>$snapshot->created_at,'url'=>route('content.import-version',[$record,$snapshot])]),
            'assets' => $assets, 'private' => $publicUrl===null]);
    }

    public function importVersion(SourceRecord $record, \App\Models\SourceRecordSnapshot $snapshot)
    {
        abort_unless($snapshot->source_record_id === $record->id,404);
        return response()->json(['title'=>$snapshot->title,'body'=>$snapshot->body,'kind'=>$snapshot->kind,'metadata'=>$snapshot->metadata]);
    }
    public function children(Request $request,SourceRecord $record)
    {
        $request->validate(['page'=>'nullable|integer|min:1']);
        $page=SourceRecord::where('source',$record->source)->where('metadata->parent_source_id',$record->source_id)->whereIn('kind',['comment','poll','live_chat'])->orderBy('id')->paginate(30);
        return response()->json(['data'=>$page->getCollection()->map(fn($child)=>['id'=>$child->id,'kind'=>$child->kind,'body'=>$child->body,'author'=>$child->metadata['author']??null,
            'poll'=>$child->metadata['poll']??null,'detail_url'=>route('content.show',$child)]),'meta'=>['current_page'=>$page->currentPage(),'last_page'=>$page->lastPage(),'total'=>$page->total()]]);
    }
}
