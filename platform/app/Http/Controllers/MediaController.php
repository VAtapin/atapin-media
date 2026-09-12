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
        ]);
        $query = Media::visibleLibrary()->with(['tags:id,name', 'assets:id,parent_id,asset_role']);
        if ($filters['q'] ?? null) {
            $term = $filters['q'];
            $query->where(fn ($items) => $items->where('title', 'like', "%{$term}%")->orWhere('original_name', 'like', "%{$term}%"));
        }
        foreach (['source', 'kind', 'status'] as $field) if ($filters[$field] ?? null) $query->where($field, $filters[$field]);
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
                'created_at' => $media->created_at?->toIso8601String(),
                'tags' => $media->tags->pluck('name')->values(),
                'asset_count' => $media->assets->count(),
                'download_url' => route('media.download', $media),
                'preview_url' => in_array($media->mime, self::PREVIEW_MIMES, true) ? route('media.preview', $media) : null,
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function store(Request $request, MediaLibrary $library)
    {
        $request->validate(['file' => 'required|file|max:102400']);
        $media = $library->upload($request->file('file'), $request->user()->id);
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
        $media = $uploads->finish($upload);
        return response()->json(['status'=>'saved','media_id'=>$media->id]);
    }
    public function update(Request $request, Media $media, Audit $audit)
    {
        $media->update($request->validate(['title' => 'required|string|max:255']));
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
