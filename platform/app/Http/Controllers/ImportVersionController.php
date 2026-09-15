<?php

namespace App\Http\Controllers;

use App\Models\SourceRecord;
use App\Models\SourceRecordSnapshot;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ImportVersionController extends Controller
{
    public function destroy(Request $request, SourceRecord $record, SourceRecordSnapshot $snapshot, Audit $audit)
    {
        $request->validate(['confirmation' => 'required|in:DELETE']);

        DB::transaction(function () use ($record, $snapshot, $audit) {
            $lockedRecord = SourceRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();
            if ($lockedRecord->metadata['public_published'] ?? false) Gate::authorize('content.publish');
            $version = SourceRecordSnapshot::whereKey($snapshot->id)->where('source_record_id', $record->id)
                ->lockForUpdate()->firstOrFail();
            $version->delete();
            $audit->record('content.import_version_deleted', (string) $record->id, ['snapshot_id' => $snapshot->id]);
        });

        return response()->json(['status' => 'deleted']);
    }
}
