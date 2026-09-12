<?php
namespace App\Http\Controllers;
use App\Jobs\ClassifyImportedContent;
use App\Models\Media;
use App\Models\SourceRecord;
use App\Services\Importing\AiContentClassifier;
use Illuminate\Http\Request;

class ContentClassificationController extends Controller
{
    public function store(Request $request, AiContentClassifier $classifier)
    {
        $data = $request->validate(['type' => 'required|in:media,record', 'id' => 'nullable|string|max:64', 'batch' => 'nullable|boolean']);
        abort_unless($classifier->available(), 422, __('imports.ai_unavailable'));
        $model = $data['type'] === 'media' ? Media::class : SourceRecord::class;
        if ($data['batch'] ?? false) {
            $query = $model::where('status', 'unsorted');
            if ($data['type'] === 'media') $query->whereNull('archived_at');
            $items = $query->oldest()->limit(100)->get();
        } else {
            $item = $model::findOrFail($data['id'] ?? '');
            $item->update(['status' => 'unsorted']);
            $items = collect([$item]);
        }
        foreach ($items as $item) dispatch(new ClassifyImportedContent($data['type'], (string) $item->id));
        app(\App\Services\Audit::class)->record('content.classification.queued', $data['id'] ?? null, ['type' => $data['type'], 'count' => $items->count()]);
        return response()->json(['status' => 'queued', 'count' => $items->count()]);
    }

    public function undo(Media $media, \App\Models\MediaClassification $classification, \App\Services\Importing\UndoMediaClassification $undo)
    {
        $undo->undo($media, $classification);
        return response()->json(['status' => 'saved']);
    }
}
