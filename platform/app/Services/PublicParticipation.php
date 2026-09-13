<?php
namespace App\Services;

use App\Models\{PublicContentState,SourceRecord,Product,User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicParticipation
{
    public function states($subject)
    {
        return PublicContentState::where('subject_type',$subject instanceof Product?'book':'record')->where('subject_id',$subject->id);
    }
    public function mine($subject,?User $user): array
    {
        return $user?$this->states($subject)->where('user_id',$user->id)->pluck('value','action')->all():[];
    }
    public function options(SourceRecord $poll): array
    {
        return array_values(array_filter($poll->metadata['poll']['options']??[],fn($option)=>is_array($option)&&is_string($option['text']??null)));
    }
    public function save(User $user,$subject,array $data): void
    {
        $action=$data['action'];
        abort_unless(in_array($action,$subject instanceof Product?['bookmark','progress']:['bookmark','like','reminder','progress','vote']),422);
        if($action==='reminder')abort_unless(app(PublicContent::class)->section($subject)==='live',422);
        if($action==='vote'){
            abort_unless($subject->kind==='poll'&&array_key_exists($data['option']??-1,$this->options($subject)),422);
            $ends=$subject->metadata['poll']['ends_at']??null;
            abort_if($ends&&\Illuminate\Support\Carbon::parse($ends)->isPast(),422,__('public.poll_closed'));
            $value=['option'=>(int)$data['option']];
        }elseif($action==='progress'){
            abort_if($subject instanceof Product&&($data['position']??0)>100,422);
            $value=['position'=>(int)($data['position']??0)];
        }
        else $value=['enabled'=>(bool)($data['enabled']??true)];
        PublicContentState::updateOrCreate(['user_id'=>$user->id,'subject_type'=>$subject instanceof Product?'book':'record','subject_id'=>$subject->id,'action'=>$action],['value'=>$value]);
    }
    public function comment(User $user,SourceRecord $parent,string $body,string $kind='comment'): void
    {
        DB::transaction(function()use($user,$parent,$body,$kind){
            $record=SourceRecord::create(['source'=>$parent->source,'source_id'=>'website-comment:'.Str::uuid(),'kind'=>$kind,
                'title'=>Str::limit($body,120,''),'body'=>$body,'status'=>'needs_attention',
                'metadata'=>['parent_source_id'=>$parent->source_id,'author'=>$user->name,'author_user_id'=>$user->id,'public_published'=>false,'website_comment'=>true]]);
            app(Audit::class)->record('public.comment_submitted',(string)$record->id);
        });
    }
}
