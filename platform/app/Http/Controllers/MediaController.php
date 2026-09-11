<?php
namespace App\Http\Controllers;
use App\Contracts\SearchProviderInterface;
use App\Models\Media;
use App\Services\MediaLibrary;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
class MediaController extends Controller
{
    public function index(Request $request, SearchProviderInterface $search)
    {
        $data = $request->validate(['q' => 'nullable|string|max:200', 'kind' => 'nullable|in:image,video,audio,pdf,document,other']);
        return view('media.index', ['media' => $search->media($data['q'] ?? '', $data['kind'] ?? null)]);
    }
    public function store(Request $request, MediaLibrary $library)
    {
        $request->validate(['file' => 'required|file|max:102400']);
        $media = $library->upload($request->file('file'), $request->user()->id);
        return redirect()->route('media.show', $media)->with('status', __('ui.uploaded'));
    }
    public function show(Media $media) { return view('media.show', compact('media')); }
    public function update(Request $request, Media $media, Audit $audit)
    {
        $media->update($request->validate(['title' => 'required|string|max:255']));
        $audit->record('media.updated', $media->id);
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
        // Never render uploaded HTML or SVG with the application origin.
        abort_unless(in_array($media->mime, ['image/jpeg','image/png','image/webp','image/gif','audio/mpeg','audio/ogg','video/mp4','video/webm']), 415);
        abort_unless(Storage::disk($media->disk)->exists($media->path), 404);
        return Storage::disk($media->disk)->response($media->path, null,
            ['Content-Type' => $media->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
