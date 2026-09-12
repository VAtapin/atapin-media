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

class ClassifyImportedContent implements ShouldQueue, ShouldBeUnique
{
    use Queueable;
    public int $timeout = 180;
    public int $tries = 1;
    public int $uniqueFor = 600;
    public function __construct(public string $type, public string $id, public ?array $previousState=null, public ?string $expectedVersion=null) {}
    public function uniqueId(): string { return $this->type.':'.$this->id; }
    private function version($item): string { return app(\App\Services\Importing\ContentState::class)->version($item); }
    public function handle(AiContentClassifier $classifier, ContentAssignment $assignment): void
    {
        $model = $this->type === 'media' ? Media::class : SourceRecord::class;
        $item = $model::findOrFail($this->id);
        if ($item->source==='catalog-reset' || $item->status !== 'unsorted' || ! $classifier->available()) return;
        $version = $this->version($item); $log = null;
        if(isset($this->expectedVersion) && $version!==$this->expectedVersion)return;
        $state = app(\App\Services\Importing\ContentState::class);
        $before = $this->previousState ?? $state->snapshot($item);
        $managed = $item instanceof Media ? SourceRecord::where('source_id','media:'.$item->id)->first() : null;
        $beforeRecord = $managed ? $state->snapshot($managed) : null;
        try {
            $log = $item->classifications()->create(['provider' => 'openai', 'model' => app(\App\Services\Settings::class)->get('ai_model'), 'status' => 'running']);
            $input = app(\App\Services\Importing\AiContentEvidence::class)->build($item);
            if (! $input['sufficient']) {
                DB::transaction(function () use ($model, $item, $version, $log) {
                    $current = $model::lockForUpdate()->findOrFail($item->id);
                    if ($current->status === 'unsorted' && $this->version($current) === $version) {
                        $current->update(['status' => 'needs_attention']); $log?->update(['status' => 'insufficient_data']);
                    } else $log?->update(['status' => 'superseded']);
                });
                return; // Do not pay a provider to guess from a filename.
            }
            $proposal = $classifier->classify($input['evidence'], $input['image']);
            // Classifying a filename is not equivalent to watching or listening to the original.
            $proposal['status'] = $proposal['confidence'] >= 0.85 ? 'ready' : 'needs_attention';
            DB::transaction(function () use ($model, $item, $version, $proposal, $assignment, $log, $before, $beforeRecord, $state) {
                $current = $model::lockForUpdate()->findOrFail($item->id);
                if ($current->status !== 'unsorted' || $this->version($current) !== $version) { $log?->update(['status' => 'superseded', 'proposal' => $proposal]); return; }
                if ($current instanceof Media) {
                    if ($beforeRecord) {
                        $managedBefore = SourceRecord::where('source_id', 'media:'.$current->id)->lockForUpdate()->first();
                        if (! $managedBefore || $state->version($managedBefore) !== hash('sha256', json_encode($beforeRecord, JSON_THROW_ON_ERROR))) {
                            $log->update(['status' => 'superseded', 'proposal' => $proposal]); return;
                        }
                    }
                    if (! in_array($proposal['target_profile'], ['media_library','videos','shorts','posts'], true)) $proposal['target_profile'] = 'media_library';
                    if ($proposal['status'] !== 'ready') $proposal['target_profile'] = $current->metadata['target_profile'] ?? 'media_library';
                    if ($current->asset_role === 'thumbnail' || ($current->metadata['role'] ?? null) === 'thumbnail') $proposal['target_profile'] = 'media_library';
                    if ($current->kind === 'video' && $proposal['target_profile'] === 'posts') $proposal['target_profile'] = 'videos';
                    $assignment->media($current, $proposal, 'ai');
                    $current->update(['classification_confidence' => $proposal['confidence'], 'classification_version' => 'v1']);
                    $managed = SourceRecord::where('source_id','media:'.$current->id)->first();
                    $log->update(['status' => 'applied', 'confidence' => $proposal['confidence'], 'proposal' => $proposal,
                        'applied_changes' => ['before' => $before, 'after_version' => $state->version($current), 'before_record' => $beforeRecord,
                            'managed_record_id' => $managed?->id, 'managed_record_version' => $managed ? $state->version($managed) : null]]);
                } else {
                    $assignment->record($current, $proposal, 'ai');
                    $meta = $current->metadata; $meta['classification'] = $proposal; $current->update(['metadata' => $meta]);
                    $log->update(['status'=>'applied','confidence'=>$proposal['confidence'],'proposal'=>$proposal,
                        'applied_changes'=>['before'=>$before,'after_version'=>$state->version($current)]]);
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
