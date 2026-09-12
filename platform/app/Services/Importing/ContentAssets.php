<?php
namespace App\Services\Importing;
use App\Models\Media;
use App\Models\SourceRecord;
use App\Services\MediaOriginalLocator;
use Illuminate\Support\Facades\DB;

class ContentAssets
{
    public function change(SourceRecord $record,array $data): void
    {
        DB::transaction(function()use($record,$data){
            $record=SourceRecord::lockForUpdate()->findOrFail($record->id);
            $links=app(LocalMediaLinks::class);$ids=$links->ids($record);$metadata=$record->metadata??[];
            $role=$data['role'];$action=$data['action'];$old=$data['old_media_id']??null;
            abort_if($old && !in_array($old,$ids,true),422,__('imports.asset_not_linked'));
            $chosen=$action==='detach'?null:Media::findOrFail($data['media_id']);
            if($chosen){
                abort_unless(app(MediaOriginalLocator::class)->find($chosen),422,__('imports.asset_unavailable'));
                abort_if($role==='video' && ($chosen->kind!=='video'||!in_array($record->kind,['video','short'],true)),422,__('imports.asset_wrong_kind'));
                abort_if($role==='cover' && $chosen->kind!=='image',422,__('imports.asset_wrong_kind'));
                if($old && $action==='replace')abort_if(Media::findOrFail($old)->kind!==$chosen->kind,422,__('imports.asset_wrong_kind'));
            }
            $remove=$old?[$old]:[];
            if($action==='replace' && $role==='video')$remove=Media::whereIn('id',$ids)->where('kind','video')->pluck('id')->all();
            if($action==='replace' && $role==='cover' && !$old){
                $remove=[...($metadata['media']['thumbnail']??[]),...isset($metadata['cover_media_id'])?[$metadata['cover_media_id']]:[]];
                if(in_array($record->kind,['video','short'],true))$remove=[...$remove,...Media::whereIn('id',$ids)->where('kind','image')->pluck('id')->all()];
            }
            $excluded=array_values(array_unique([...($metadata['excluded_media_ids']??[]),...$remove]));
            if($chosen)$excluded=array_values(array_diff($excluded,[$chosen->id]));
            foreach(['media_ids','images'] as $key)$metadata[$key]=array_values(array_diff($metadata[$key]??[],$remove));
            foreach($metadata['media']??[] as $key=>$group)if(is_array($group))$metadata['media'][$key]=array_values(array_diff($group,$remove));
            foreach(['cover_media_id','primary_video_id'] as $key)if(in_array($metadata[$key]??null,$remove,true))unset($metadata[$key]);
            foreach(Media::whereIn('id',$remove)->get() as $asset)$asset->usages()->where('subject_type',SourceRecord::class)->where('subject_id',(string)$record->id)->delete();
            $metadata['excluded_media_ids']=$excluded;$metadata['manual_assets']=true;$metadata['classification_origin']='manual';
            if($chosen){
                $metadata['media_ids']=array_values(array_unique([...($metadata['media_ids']??[]),$chosen->id]));
                if($role==='video'){$metadata['primary_video_id']=$chosen->id;$metadata['media']['video']=[$chosen->id];}
                if($role==='cover'){$metadata['cover_media_id']=$chosen->id;$metadata['media']['thumbnail']=[$chosen->id];}
            }
            $record->update(['metadata'=>$metadata]);
            $links->repair($record,$role==='video'?$chosen:null);
        });
    }
}
