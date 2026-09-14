<?php
namespace App\Services\Importing;
use App\Models\{ImportRun,Media,NewsletterSubscription,User};
use App\Services\MediaOriginalLocator;
use Illuminate\Support\Facades\{Validator,Storage,DB};
class SubscriberCsvAdapter implements ImportAdapter
{
    public function source():string{return 'subscriber-csv';}
    public function validate(array $input):array{return Validator::make($input,['media_id'=>'required|uuid|exists:media,id','email_column'=>'required|integer|min:0|max:99','delimiter'=>'required|in:comma,semicolon,tab','locale'=>'required|in:de,en'])->validate();}
    public function normalize(array $input):array{return ['source'=>$this->source(),'source_kind'=>'subscriber-csv','source_ref'=>$input['media_id'],'source_options'=>$input,'target_profile'=>'mixed'];}
    public function rows(Media $media,string $delimiter):array
    {
        abort_if($media->archived_at||$media->disk!=='local'||!in_array($media->mime,['text/csv','text/plain','application/csv','application/vnd.ms-excel'],true),422);
        $location=app(MediaOriginalLocator::class)->find($media);abort_unless($location&&$location['disk']==='local',422);$stream=Storage::disk('local')->readStream($location['path']);if(!$stream)throw new \RuntimeException(__('workspaces.csv_invalid'));
        try{$text=stream_get_contents($stream,2*1024*1024+1);}finally{fclose($stream);}if(strlen($text)>2*1024*1024||!mb_check_encoding($text,'UTF-8'))throw new \RuntimeException(__('workspaces.csv_invalid'));$text=preg_replace('/^\xEF\xBB\xBF/','',$text);$stream=fopen('php://temp','w+b');fwrite($stream,$text);rewind($stream);$rows=[];
        try{while(($row=fgetcsv($stream,0,match($delimiter){'semicolon'=>';','tab'=>"\t",default=>','},'"',''))!==false){if(count($row)>100||count($rows)>=10001)throw new \RuntimeException(__('workspaces.csv_invalid'));$rows[]=$row;}}finally{fclose($stream);}if(count($rows)<2)throw new \RuntimeException(__('workspaces.csv_invalid'));return $rows;
    }
    public function import(ImportRun $run):void
    {
        $user=User::find($run->user_id);abort_unless($user?->hasPermission('subscribers.manage')&&$user->hasPermission('media.view'),403);$options=$this->validate($run->source_options);$media=Media::findOrFail($options['media_id']);$rows=$this->rows($media,$options['delimiter']);$headers=array_shift($rows);abort_unless(isset($headers[$options['email_column']]),422);$journal=app(ImportJournal::class);
        foreach($rows as $index=>$row){app(ImportProgress::class)->checkpoint($run,'content');$key='subscriber:'.($index+2);if($journal->done($run,$key))continue;$email=mb_strtolower(trim($row[$options['email_column']]??''));$valid=filter_var($email,FILTER_VALIDATE_EMAIL)&&strlen($email)<=254;$outcome='failed';$id=null;
            if($valid){$entry=DB::transaction(fn()=>NewsletterSubscription::firstOrCreate(['email'=>$email],['locale'=>$options['locale'],'status'=>'imported','delivery_status'=>'not_requested','token_hash'=>hash('sha256',random_bytes(32)),'consented_at'=>null,'confirmed_at'=>null,'tags'=>[],'import_provenance'=>['media_id'=>$media->id,'sha256'=>$media->sha256,'row'=>$index+2]]));$outcome=$entry->wasRecentlyCreated?'added':'duplicate';$id=(string)$entry->id;}
            $journal->record($run,$key,'#'.($index+2),'subscriber',$outcome,$id);$run->increment('discovered');$run->increment($outcome==='added'?'imported':'skipped');
        }
    }
}
