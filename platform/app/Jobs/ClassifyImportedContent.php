<?php
namespace App\Jobs;
use App\Models\Media;
use App\Models\SourceRecord;
use App\Services\Importing\AiContentClassifier;
use App\Services\Importing\ContentAssignment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ClassifyImportedContent implements ShouldQueue, ShouldBeUnique
{
    use Queueable;
    public int $timeout = 180;
    public int $tries = 1;
    public int $uniqueFor = 600;
    public function __construct(public string $type, public string $id) {}
    public function uniqueId(): string { return $this->type.':'.$this->id; }
    private function version($item): string { return hash('sha256', json_encode($item->getAttributes(), JSON_THROW_ON_ERROR)); }
    public function handle(AiContentClassifier $classifier, ContentAssignment $assignment): void
    {
        $model = $this->type === 'media' ? Media::class : SourceRecord::class;
        $item = $model::findOrFail($this->id);
        if ($item->status !== 'unsorted' || ! $classifier->available()) return;
        $version = $this->version($item); $image = null; $log = null;
        $evidence = ['title' => $item->title, 'kind' => $item->kind, 'source' => $item->source];
        try {
            if ($item instanceof Media) {
                $evidence['original_name'] = $item->original_name;
                $evidence['mime'] = $item->mime;
                $log = $item->classifications()->create(['provider' => 'openai', 'model' => app(\App\Services\Settings::class)->get('ai_model'), 'status' => 'running']);
                if (str_starts_with($item->mime, 'text/') && $item->bytes <= 1024 * 1024) $evidence['body'] = mb_substr(Storage::disk($item->disk)->get($item->path),0,30000);
                if (in_array($item->mime, ['image/jpeg','image/png','image/webp'], true) && $item->bytes <= 10 * 1024 * 1024) $image = 'data:'.$item->mime.';base64,'.base64_encode(Storage::disk($item->disk)->get($item->path));
            } else {
                $evidence['body'] = mb_substr($item->body ?? '',0,30000);
                if (isset($item->metadata['poll'])) $evidence['poll'] = mb_substr(json_encode($item->metadata['poll'], JSON_THROW_ON_ERROR),0,10000);
            }
            $proposal = $classifier->classify($evidence, $image);
            // Classifying a filename is not equivalent to watching or listening to the original.
            $sufficient = $image !== null || ! empty($evidence['body']) || ! empty($evidence['poll']);
            $proposal['status'] = $sufficient && $proposal['confidence'] >= 0.85 ? 'ready' : 'needs_attention';
            DB::transaction(function () use ($model, $item, $version, $proposal, $assignment, $log) {
                $current = $model::lockForUpdate()->findOrFail($item->id);
                if ($current->status !== 'unsorted' || $this->version($current) !== $version) { $log?->update(['status' => 'superseded', 'proposal' => $proposal]); return; }
                if ($current instanceof Media) {
                    if (! in_array($proposal['target_profile'], ['media_library','videos','shorts','posts'], true)) $proposal['target_profile'] = 'media_library';
                    $assignment->media($current, $proposal, 'ai');
                    $current->update(['classification_confidence' => $proposal['confidence'], 'classification_version' => 'v1']);
                    $log->update(['status' => 'applied', 'confidence' => $proposal['confidence'], 'proposal' => $proposal, 'applied_changes' => $proposal]);
                } else {
                    $assignment->record($current, $proposal, 'ai');
                    $meta = $current->metadata; $meta['classification'] = $proposal; $current->update(['metadata' => $meta]);
                }
            });
        } catch (\Throwable $error) {
            $log?->update(['status' => 'failed']);
            app(\App\Services\Audit::class)->record('content.classification.failed', (string) $item->id, ['type' => $this->type, 'error_type' => $error::class]);
            DB::transaction(function () use ($model, $item, $version) {
                $current = $model::lockForUpdate()->find($item->id);
                if ($current && $current->status === 'unsorted' && $this->version($current) === $version) $current->update(['status' => 'needs_attention']);
            });
            throw $error;
        }
    }
}
