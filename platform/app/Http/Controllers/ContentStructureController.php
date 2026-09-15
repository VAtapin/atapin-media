<?php

namespace App\Http\Controllers;

use App\Models\SourceRecord;
use App\Services\ContentStructureReview;
use Illuminate\Http\Request;

class ContentStructureController extends Controller
{
    public function store(Request $request, SourceRecord $record, ContentStructureReview $review)
    {
        $data = $request->validate(['force' => 'sometimes|boolean']);
        return response()->json($review->queue($request->user(), $record, (bool) ($data['force'] ?? false)), 202);
    }

    public function batch(Request $request, ContentStructureReview $review)
    {
        abort_unless(app(\App\Services\ContentShortDescriptions::class)->available(), 422, __('workspaces.ai_unavailable'));
        $filters = $request->validate(['filters' => 'nullable|array'])['filters'] ?? [];
        $filters = validator($filters, [
            'q' => 'nullable|string|max:120',
            'kind' => 'nullable|in:video,short,post',
            'section' => 'nullable|in:videos,posts,podcast',
            'status' => 'nullable|in:unsorted,review,ready,needs_attention',
            'publication' => 'nullable|in:published,unpublished',
            'trash' => 'nullable|in:active',
        ])->validate();

        $query = SourceRecord::query()
            ->whereIn('kind', ContentStructureReview::SUPPORTED_KINDS)
            ->where(fn ($query) => $query->whereNull('metadata->archive_data')->orWhere('metadata->archive_data', false))
            ->orderBy('id');
        if (($filters['kind'] ?? '') !== '') $query->where('kind', $filters['kind']);
        if (($filters['section'] ?? '') === 'videos') $query->whereIn('kind', ['video', 'short']);
        if (($filters['section'] ?? '') === 'posts') $query->where('kind', 'post');
        if (($filters['section'] ?? '') === 'podcast') $query->where('metadata->public_section', 'podcast');
        if (($filters['status'] ?? '') !== '') $query->where('status', $filters['status']);
        if (($filters['publication'] ?? '') === 'published') $query->where('metadata->public_published', true);
        if (($filters['publication'] ?? '') === 'unpublished') $query->where(fn ($query) => $query->whereNull('metadata->public_published')->orWhere('metadata->public_published', false));
        if (($filters['q'] ?? '') !== '') $query->where(fn ($query) => $query->where('title', 'like', '%'.$filters['q'].'%')->orWhere('body', 'like', '%'.$filters['q'].'%'));

        $queued = 0;
        $skipped = 0;
        $errors = 0;
        $firstError = null;
        foreach ($query->limit(500)->get() as $record) {
            try {
                $result = $review->queue($request->user(), $record);
                $result['status'] === 'queued' ? $queued++ : $skipped++;
                if ($queued >= 50) break;
            } catch (\Throwable $error) {
                $errors++;
                $firstError ??= $error;
            }
        }
        if ($queued === 0 && $skipped === 0 && $firstError) throw $firstError;

        return response()->json(['status' => 'queued', 'queued' => $queued, 'skipped' => $skipped, 'errors' => $errors], 202);
    }
}
