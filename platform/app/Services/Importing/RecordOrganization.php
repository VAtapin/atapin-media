<?php
namespace App\Services\Importing;
use App\Models\SourceRecord;
use Illuminate\Support\Facades\DB;
class RecordOrganization
{
    public function apply(array $data): int
    {
        abort_unless(count(array_intersect(array_keys($data),['status','target_profile','add_tags']))>0,422,__('imports.no_changes'));
        return DB::transaction(function () use ($data) {
            $records=SourceRecord::whereIn('id',$data['ids'])->lockForUpdate()->get();abort_unless($records->count()===count($data['ids']),404);
            foreach($records as $record) {
                $changes=[];
                foreach(['status','target_profile'] as $key) if(isset($data[$key]))$changes[$key]=$data[$key];
                if(isset($data['add_tags'])) {
                    $changes['tags']=array_values(array_unique([...($record->metadata['tags']??[]),...array_filter(array_map('trim',$data['add_tags']))]));
                    abort_if(count($changes['tags'])>30,422,__('imports.selection_limit'));
                }
                app(ContentAssignment::class)->record($record,$changes);
            }
            return $records->count();
        });
    }
}
