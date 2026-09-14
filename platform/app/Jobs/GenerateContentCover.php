<?php
namespace App\Jobs;

use App\Models\SourceRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\{ShouldQueue,ShouldBeUnique};
use Illuminate\Support\Facades\DB;

class GenerateContentCover implements ShouldQueue,ShouldBeUnique
{
    use Queueable;
    public int $tries=1;
    public int $timeout=300;
    public int $uniqueFor=600;
    public function __construct(public int $id,public ?string $coverId,public string $version) {}
    public function uniqueId(): string{return (string)$this->id;}
    public function handle(\App\Services\AiCoverGenerator $generator): void
    {
        $record=SourceRecord::find($this->id);
        if(!$record)return;
        if(($record->metadata['cover_media_id']??null)!==$this->coverId || app(\App\Services\ContentShortDescriptions::class)->version($record)!==$this->version){$this->state('superseded');return;}
        try{
            $this->state('running');
            $image=$generator->generate($record);
            DB::transaction(function()use($image){
                $current=SourceRecord::lockForUpdate()->find($this->id);
                if(!$current)return;
                if(($current->metadata['cover_media_id']??null)!==$this->coverId || app(\App\Services\ContentShortDescriptions::class)->version($current)!==$this->version){$this->state('superseded');return;}
                app(\App\Services\Importing\MediaCoverAssignment::class)->assign($image,$current);$this->state('completed');
            });
        }catch(\Throwable $error){$this->state('failed');throw $error;}
    }
    public function failed(?\Throwable $error): void{$this->state('failed');}
    private function state(string $state): void
    {
        $record=SourceRecord::find($this->id);if(!$record)return;
        $meta=$record->metadata;$meta['media_jobs']['ai_cover']=['state'=>$state,'updated_at'=>now()->toIso8601String()];$record->updateQuietly(['metadata'=>$meta]);
    }
}
