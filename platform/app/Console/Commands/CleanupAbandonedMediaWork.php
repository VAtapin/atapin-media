<?php
namespace App\Console\Commands;

use App\Services\CanonicalMediaStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class CleanupAbandonedMediaWork extends Command
{
    protected $signature='media:cleanup-abandoned-work';
    protected $description='Remove abandoned HLS work directories when no conversion holds their lock.';

    public function handle(): int
    {
        $workRoot=Storage::disk('local')->path('media-work/hls');
        if(!is_dir($workRoot)&&!mkdir($workRoot,0700,true)&&!is_dir($workRoot)){$this->error('Cannot access the HLS work directory.');return self::FAILURE;}
        $targets=[
            [$workRoot,'/^([a-f0-9]{64})-[a-f0-9]{16}$/D'],
            [Storage::disk(CanonicalMediaStorage::DISK)->path(''),'/^\.hls-([a-f0-9]{64})-[a-f0-9]{16}$/D'],
        ];
        $removed=0;$active=0;
        foreach($targets as [$root,$pattern]){
            if(!is_dir($root))continue;
            foreach(new \DirectoryIterator($root) as $entry){
                if($entry->isDot()||!$entry->isDir()||!preg_match($pattern,$entry->getFilename(),$match))continue;
                $lock=fopen($workRoot.'/'.$match[1].'.lock','c');
                if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){$active++;if(is_resource($lock))fclose($lock);continue;}
                try{if(File::deleteDirectory($entry->getPathname()))$removed++;}
                finally{flock($lock,LOCK_UN);fclose($lock);}
            }
        }
        $this->info("Removed {$removed} abandoned HLS work directories; skipped {$active} locked directories.");
        return self::SUCCESS;
    }
}
