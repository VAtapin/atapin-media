<?php
namespace App\Http\Controllers;
use App\Models\Media;
use App\Services\MediaLibrary;
use App\Services\Audit;
use App\Services\ResumableMediaUploadService;
use App\Models\ResumableMediaUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
class MediaController extends Controller
{
    private const PREVIEW_MIMES = ['image/jpeg','image/png','image/webp','image/gif','audio/mpeg','audio/ogg','video/mp4','video/webm','application/pdf'];

    public function library(Request $request)
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:120',
            'source' => 'nullable|in:intake,youtube,upload,local-folder,local-archive,youtube-service,tiktok,instagram,facebook-video',
            'kind' => 'nullable|in:video,audio,image,document,pdf,other',
            'status' => 'nullable|in:unsorted,processing,ready,needs_attention,failed',
            'sort' => 'nullable|in:newest,oldest,name,size',
            'tag' => 'nullable|string|max:100', 'collection' => 'nullable|integer|exists:collections,id',
            'archive' => 'nullable|in:active,archived,all',
        ]);
        $query = Media::query()->with(['tags:id,name', 'assets:id,parent_id,asset_role,mime']);
        if (($filters['archive'] ?? 'active') !== 'all') {
            if (($filters['archive'] ?? 'active') === 'archived') $query->whereNotNull('archived_at');
            else $query->whereNull('archived_at');
        }
        if ($filters['tag'] ?? '') $query->whereHas('tags', fn ($q) => $q->where('name', $filters['tag']));
        if ($filters['collection'] ?? '') $query->whereHas('collections', fn ($q) => $q->where('collections.id', $filters['collection']));
        if ($filters['q'] ?? null) {
            $term = $filters['q'];
            $query->where(fn ($items) => $items->where('title', 'like', "%{$term}%")->orWhere('original_name', 'like', "%{$term}%"));
        }
        if ($filters['source'] ?? null) $query->where(fn ($q) => $q->where('source',$filters['source'])->orWhereHas('originals',fn ($originals) => $originals->where('source',$filters['source'])));
        foreach (['kind', 'status'] as $field) if ($filters[$field] ?? null) $query->where($field, $filters[$field]);
        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->oldest(),
            'name' => $query->orderBy('title'),
            'size' => $query->orderByDesc('bytes'),
            default => $query->latest(),
        };
        $page = $query->paginate(30)->withQueryString();
        return response()->json([
            'data' => $page->getCollection()->map(fn (Media $media) => [
                'id' => $media->id,
                'title' => $media->title,
                'original_name' => $media->original_name,
                'kind' => $media->kind,
                'mime' => $media->mime,
                'asset_role' => $media->asset_role,
                'bytes' => $media->bytes,
                'formatted_size' => $media->formattedSize(),
                'source' => $media->source,
                'status' => $media->status,
                'target_profile' => $media->metadata['target_profile'] ?? 'media_library',
                'classification_confidence' => $media->classification_confidence,
                'archived' => $media->archived_at !== null,
                'detail_url' => route('media.details', $media),
                'created_at' => $media->created_at?->toIso8601String(),
                'tags' => $media->tags->pluck('name')->values(),
                'asset_count' => $media->assets->count(),
                'thumbnail_url' => str_starts_with($media->mime, 'image/') && in_array($media->mime, self::PREVIEW_MIMES, true)
                    ? route('media.preview', $media) : (($thumbnail = $media->assets->firstWhere('id', $media->metadata['cover_media_id'] ?? '') ?? $media->assets->first(fn ($asset) => in_array($asset->mime, ['image/jpeg','image/png','image/webp','image/gif'], true))) ? route('media.preview', $thumbnail) : null),
                'download_url' => route('media.download', $media),
                'preview_url' => in_array($media->mime, self::PREVIEW_MIMES, true) ? route('media.preview', $media) : null,
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function details(Media $media, \App\Services\Importing\ImportedContentPresentation $presentation)
    {
        $media->load(['tags', 'assets', 'parent', 'collections', 'usages']);
        $asset = fn ($item) => ['id' => $item->id, 'title' => $item->title, 'kind' => $item->kind, 'mime' => $item->mime,
            'role' => $item->asset_role, 'download_url' => route('media.download', $item),
            'preview_url' => in_array($item->mime, self::PREVIEW_MIMES, true) ? route('media.preview', $item) : null];
        $records = \App\Models\SourceRecord::whereIn('id', $media->usages->where('subject_type', \App\Models\SourceRecord::class)->pluck('subject_id'))->get();
        $linked = \App\Models\SourceRecord::where(function ($query) use ($media) {
            foreach (['media_ids','images','media->video','media->thumbnail','media->subtitles'] as $key) $query->orWhereJsonContains('metadata->'.$key, $media->id);
            $query->orWhere('metadata->cover_media_id', $media->id);
        })->limit(30)->get();
        $records = $records->merge($linked)->unique('id');
        $version = app(\App\Services\Importing\ContentState::class)->version($media);
        return response()->json(['id' => $media->id, 'summary' => $media->metadata['summary'] ?? '', 'client_relative_path' => $media->metadata['client_relative_path'] ?? null,
            'external_url' => $presentation->externalUrl($media->metadata ?? [], $media->source ?? '', $media->source_id, $media->kind),
            'storage' => ['disk' => $media->disk, 'path' => $media->path, 'sha256' => $media->sha256],
            'technical' => array_intersect_key($media->metadata['technical'] ?? [], array_flip(['duration', 'width', 'height', 'format', 'codec'])),
            'parent' => $media->parent ? $asset($media->parent) : null,
            'assets' => $media->assets->map($asset), 'collections' => $media->collections->map(fn ($item) => ['id' => $item->id, 'title' => $item->title]),
            'cover_url' => route('media.cover', $media),
            'originals' => $media->originals()->get(['disk','path','source','original_name','sha256','bytes']),
            'usages' => $records->map(fn ($item) => ['title' => $item->title, 'kind' => $item->kind, 'detail_url' => route('content.show', $item)]),
            'classifications' => $media->classifications()->latest()->limit(20)->get()->map(fn ($log) => [
                'id'=>$log->id, 'provider'=>$log->provider, 'model'=>$log->model, 'status'=>$log->status, 'confidence'=>$log->confidence,
                'proposal'=>$log->proposal, 'created_at'=>$log->created_at,
                'undo_url'=>$log->status === 'applied' && ($log->applied_changes['after_version'] ?? null) === $version
                    ? route('media.classification.undo', [$media, $log]) : null,
            ])]);
    }

    public function store(Request $request, MediaLibrary $library)
    {
        $request->validate(['file' => 'required|file|max:102400']);
        $media = $library->upload($request->file('file'), $request->user()->id);
        app(\App\Services\MediaUploadCutover::class)->confirm();
        if ($request->expectsJson()) return response()->json(['status'=>'saved','media_id'=>$media->id]);
        return back()->with('status', __('ui.uploaded'));
    }
    public function uploadStart(Request $request, ResumableMediaUploadService $uploads)
    {
        $request->validate([
            'request_key' => 'required|uuid',
            'name' => 'required|string|max:255',
            'size' => 'required|integer|min:1|max:' . config('platform.media_upload_max_bytes'),
        ]);
        $upload = $uploads->start($request->only('request_key', 'name', 'size'), $request->user()->id);
        return response()->json(['id'=>$upload->id, 'offset'=>$upload->offset, 'chunk_size'=>ResumableMediaUploadService::CHUNK_SIZE]);
    }
    public function uploadChunk(Request $request, ResumableMediaUpload $upload, ResumableMediaUploadService $uploads)
    {
        abort_unless($upload->user_id === $request->user()->id, 404);
        $rawOffset = (string) $request->header('X-Upload-Offset');
        $sha = (string) $request->header('X-Chunk-SHA256');
        if (!preg_match('/^[a-f0-9]{64}$/i', $sha)) {
            abort(422, 'Invalid chunk checksum.');
        }
        if (!preg_match('/^\d+$/', $rawOffset)) {
            abort(422, 'Invalid chunk offset.');
        }
        $upload = $uploads->append($upload, (int) $rawOffset, $request->getContent(), $sha);
        return response()->json(['id'=>$upload->id, 'offset'=>$upload->offset]);
    }
    public function uploadFinish(Request $request, ResumableMediaUpload $upload, ResumableMediaUploadService $uploads)
    {
        abort_unless($upload->user_id === $request->user()->id, 404);
        $data = $request->validate(['client_relative_path' => 'nullable|string|max:2000']);
        if (! empty($data['client_relative_path'])) {
            try {\App\Services\Importing\ImportPath::entry($data['client_relative_path']);}
            catch (\RuntimeException $error) {throw \Illuminate\Validation\ValidationException::withMessages(['client_relative_path' => __('imports.path_required')]);}
        }
        $media = $uploads->finish($upload);
        if (! empty($data['client_relative_path'])) $media->update(['metadata' => [...($media->metadata ?? []), 'client_relative_path' => $data['client_relative_path']]]);
        app(\App\Services\MediaUploadCutover::class)->confirm();
        return response()->json(['status'=>'saved','media_id'=>$media->id]);
    }
    public function update(Request $request, Media $media, Audit $audit)
    {
        app(\App\Services\Importing\ContentAssignment::class)->media($media, $request->validate([
            'title' => 'required|string|max:255', 'status' => 'nullable|in:unsorted,ready,needs_attention',
            'target_profile' => 'nullable|in:media_library,videos,shorts,posts', 'tags' => 'nullable|array|max:30', 'tags.*' => 'string|max:100',
        ]));
        $audit->record('media.updated', $media->id);
        if ($request->expectsJson()) return response()->json(['status'=>'saved','media_id'=>$media->id]);
        return back()->with('status', __('ui.saved'));
    }
    public function download(Media $media)
    {
        abort_unless(Storage::disk($media->disk)->exists($media->path), 404);
        return Storage::disk($media->disk)->download($media->path, $media->original_name,
            ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
    public function preview(Media $media)
    {
        abort_unless(in_array($media->mime, self::PREVIEW_MIMES, true), 415);
        abort_unless(Storage::disk($media->disk)->exists($media->path), 404);
        return Storage::disk($media->disk)->response($media->path, null,
            ['Content-Type' => $media->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "sandbox; default-src 'none';"]);
    }
}
