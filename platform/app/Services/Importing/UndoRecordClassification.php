<?php
namespace App\Services\Importing;
use App\Models\SourceRecord;
use App\Models\RecordClassification;
use Illuminate\Support\Facades\DB;
class UndoRecordClassification
{
    public function undo(SourceRecord $record,RecordClassification $classification): void
    {
        DB::transaction(function () use ($record,$classification) {
            $record=SourceRecord::lockForUpdate()->findOrFail($record->id);$classification=RecordClassification::lockForUpdate()->findOrFail($classification->id);
            abort_unless($classification->source_record_id===$record->id,404);$changes=$classification->applied_changes??[];
            abort_unless($classification->status==='applied'&&isset($changes['before']['fields'],$changes['after_version'])
                &&app(ContentState::class)->version($record)===$changes['after_version'],409,__('imports.undo_conflict'));
            $record->update($changes['before']['fields']);$classification->update(['status'=>'undone']);
            app(\App\Services\Audit::class)->record('content.classification.undone',(string)$record->id,['classification_id'=>$classification->id]);
        });
    }
}
