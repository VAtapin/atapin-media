<?php

namespace App\Services\Importing;

use App\Models\SourceRecord;
use App\Services\{Audit, Settings};

class ImportSortingRules
{
    public function __construct(private Settings $settings) {}

    public function durationRule(): array
    {
        return $this->settings->get('import_duration_rule', config('import_classification.duration_rule_defaults', [])[config('platform.brand')]
            ?? ['enabled'=>false, 'min_seconds'=>1, 'max_seconds'=>1, 'target_profile'=>'posts']);
    }

    public function target(string $kind, mixed $duration): ?string
    {
        $rule=$this->durationRule();
        if (!($rule['enabled']??false) || !in_array($kind,['video','short'],true) || !is_numeric($duration) || !is_finite((float)$duration) || (float)$duration<=0) return null;
        return (float)$duration >= $rule['min_seconds'] && (float)$duration <= $rule['max_seconds'] ? $rule['target_profile'] : null;
    }

    public function apply(SourceRecord $record): void
    {
        if ($record->status!=='unsorted' || in_array($record->metadata['classification_origin']??'', ['manual','ai'],true)) return;
        $target=$this->target($record->kind,$record->metadata['duration']??null);
        if (!$target) return;
        $metadata=$record->metadata??[];
        $metadata['import_duration_rule']=['previous_kind'=>$record->kind, ...$this->durationRule()];
        $metadata['format_needs_review']=false;
        $kind=match($target){'posts'=>'post','shorts'=>'short','videos'=>'video'};
        if ($kind==='post') unset($metadata['public_homepage']);
        $record->update(['kind'=>$kind,'metadata'=>$metadata]);
        app(Audit::class)->record('content.import_duration_assigned',(string)$record->id,['target_profile'=>$target]);
    }

    public function instructions(): string
    {
        $rule=$this->durationRule();
        return ($rule['enabled']??false) ? ' Owner-configured import duration rule for video/short items: '.json_encode($rule,JSON_THROW_ON_ERROR).
            '. Prefer the configured target only when duration_seconds falls inside the inclusive range. Do not generalize to other durations or guess missing duration. Contradictions require confidence below 0.85 for human review.' : '';
    }
}
