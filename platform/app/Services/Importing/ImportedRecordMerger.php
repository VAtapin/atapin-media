<?php

namespace App\Services\Importing;

use App\Models\SourceRecord;
use App\Models\SourceRecordSnapshot;
use Illuminate\Support\Facades\DB;

class ImportedRecordMerger
{
    public function merge(string $source, string $id, string $kind, string $title, string $body, array $metadata): SourceRecord
    {
        return DB::transaction(function () use ($source,$id,$kind,$title,$body,$metadata) {
            $record = SourceRecord::firstOrCreate(['source'=>$source,'source_id'=>$id], [
                'kind'=>$kind,'title'=>$title,'body'=>$body,'metadata'=>$metadata,'status'=>'unsorted',
            ]);
            $created = $record->wasRecentlyCreated;
            $record = SourceRecord::lockForUpdate()->findOrFail($record->id);
            if (! SourceRecordSnapshot::where('source_record_id',$record->id)->exists())
                $this->snapshot($record,$record->kind,$record->title,$record->body ?? '',$record->metadata ?? []);
            $snapshot = $this->snapshot($record,$kind,$title,$body,$metadata);
            $current = $record->metadata ?? [];
            $merged = $this->metadata($current,$metadata);
            if (mb_strlen($body) > mb_strlen($current['original_description'] ?? '')) $merged['original_description']=$body;
            $protected = in_array($current['classification_origin'] ?? '', ['manual','ai'],true) || $record->status === 'ready';
            $data = ['metadata'=>$merged];
            if (! $protected) {
                if (mb_strlen($body) > mb_strlen($record->body ?? '')) $data['body']=$body;
                if ($record->title === '' || $record->title === $id) $data['title']=$title;
            }
            if (! $created && $snapshot->wasRecentlyCreated && ($body !== ($record->body ?? '') || $title !== $record->title))
                $data['metadata']['import_enriched'] = true;
            $record->update($data); $record->wasRecentlyCreated=$created;
            return $record;
        });
    }

    private function snapshot(SourceRecord $record, string $kind, string $title, string $body, array $metadata): SourceRecordSnapshot
    {
        $stable = $metadata; unset($stable['import_id'],$stable['import_enriched']);
        $fingerprint = hash('sha256',json_encode([$kind,$title,$body,$stable],JSON_THROW_ON_ERROR));
        return SourceRecordSnapshot::firstOrCreate(['source_record_id'=>$record->id,'fingerprint'=>$fingerprint],
            ['kind'=>$kind,'title'=>$title,'body'=>$body,'metadata'=>$metadata]);
    }

    private function metadata(array $old, array $new): array
    {
        foreach ($new as $key=>$value) {
            if (in_array($key,['media_ids','images','links'],true) && is_array($value)) {
                $old[$key] = array_values(array_unique([...($old[$key] ?? []),...$value],SORT_REGULAR));
            } elseif ($key === 'media' && is_array($value)) {
                foreach ($value as $role=>$ids) if (is_array($ids)) $old['media'][$role]=array_values(array_unique([...($old['media'][$role] ?? []),...$ids]));
            } elseif (!isset($old[$key]) || $old[$key] === '' || $old[$key] === []) $old[$key]=$value;
        }
        return $old;
    }
}
