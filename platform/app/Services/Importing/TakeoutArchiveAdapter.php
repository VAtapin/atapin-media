<?php
namespace App\Services\Importing;

use App\Models\ImportRun;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class TakeoutArchiveAdapter implements ImportAdapter
{
    public function source(): string {return 'youtube-takeout';}
    public function inventory(): array
    {
        $root=config('platform.takeout_root'); $batches=[]; $reports=[];
        $prepared=config('platform.takeout_folder');
        if(is_string($prepared)&&is_dir($prepared)&&!is_link($prepared))
            $batches['prepared:Takeout']=['id'=>'prepared:Takeout','layout'=>'folder','parts'=>[],'name'=>basename($prepared)];
        foreach(is_dir($root)?new \DirectoryIterator($root):[] as $file) {
            if($file->isLink()||$file->isDot())continue;
            if($file->isDir()) {
                $batches['folder:'.$file->getFilename()]=['id'=>'folder:'.$file->getFilename(),'layout'=>'folder','parts'=>[],'name'=>$file->getFilename()];
                continue;
            }
            if(!$file->isFile())continue;
            if(preg_match('/^(takeout-\d{8}T\d{6}Z-\d+)-(\d{3})\.zip$/i',$file->getFilename(),$m)) {
                $batches[$m[1]]['id']=$m[1];
                $batches[$m[1]]['parts'][]=['number'=>(int)$m[2],'name'=>$file->getFilename(),'bytes'=>$file->getSize()];
            } elseif(preg_match('/^takeout-.*\.zip$/i',$file->getFilename()))$reports[]=$file->getFilename();
        }
        foreach($batches as &$batch) {
            usort($batch['parts'],fn($a,$b)=>$a['number']<=>$b['number']);
            $batch['layout']??='zip';
            $batch['report']=$batch['layout']==='zip'?$this->report($batch['id']):null;
        }
        return ['batches'=>array_values($batches),'reports'=>$reports,'available'=>is_dir($root)||isset($batches['prepared:Takeout'])];
    }
    public function validate(array $input): array
    {
        $id=$input['batch']??''; $count=(int)($input['expected_parts']??0);
        if($id==='prepared:Takeout') {
            $root=config('platform.takeout_folder');
            if(!is_string($root)||!is_dir($root)||is_link($root))throw ValidationException::withMessages(['batch'=>__('imports.takeout_select')]);
            ImportPath::resolve(dirname($root),basename($root));
            return $input;
        }
        if(str_starts_with($id,'folder:')) {
            $name=substr($id,7);
            if($name===''||str_contains($name,'/')||str_contains($name,'\\')||in_array($name,['.','..'],true))throw ValidationException::withMessages(['batch'=>__('imports.takeout_select')]);
            $folder=ImportPath::resolve(config('platform.takeout_root'),$name);
            if(!is_dir($folder)||is_link(config('platform.takeout_root').'/'.$name))throw ValidationException::withMessages(['batch'=>__('imports.takeout_select')]);
            return $input;
        }
        if(!preg_match('/^takeout-\d{8}T\d{6}Z-\d+$/i',$id)||$count<1||$count>100)
            throw ValidationException::withMessages(['batch'=>__('imports.takeout_select')]);
        $batch=collect($this->inventory()['batches'])->firstWhere('id',$id);
        if(!$batch || array_column($batch['parts'],'number')!==range(1,$count))
            throw ValidationException::withMessages(['batch'=>__('imports.takeout_incomplete')]);
        return $input;
    }
    public function normalize(array $input): array
    {
        return ['source'=>$this->source(),'source_kind'=>$this->source(),'source_ref'=>$input['batch'],
            'source_options'=>['batch'=>$input['batch'],'expected_parts'=>(int)($input['expected_parts']??0)],
            'target_profile'=>$input['target_profile']??'mixed'];
    }
    public function import(ImportRun $run): void
    {
        $this->validate($run->source_options);
        $batch=collect($this->inventory()['batches'])->firstWhere('id',$run->source_options['batch']);
        if(($batch['layout']??null)==='folder') {
            $prepared=$batch['id']==='prepared:Takeout';
            $storageRoot=$prepared?config('platform.takeout_folder'):config('platform.takeout_root');
            $disk=$prepared?'takeout-prepared':'takeout';
            $root=$prepared?ImportPath::resolve(dirname($storageRoot),basename($storageRoot)):ImportPath::resolve($storageRoot,substr($batch['id'],7));
            app(TakeoutCatalog::class)->inspect($run,[],$root,null);
            (new LocalFolderAdapter($storageRoot,$disk))->importDirectory($run,$root,false);
            app(TakeoutContentImporter::class)->import($run,[$root],$disk,$storageRoot);
            return;
        }
        $archives=[]; $required=0; $entries=0;
        foreach($batch['parts'] as $part) {
            $path=ImportPath::resolve(config('platform.takeout_root'),$part['name']); $archives[]=$path;
            $zip=new ZipArchive;
            if($zip->open($path)!==true)throw new \RuntimeException('Cannot open Takeout ZIP.');
            try {
                for($i=0;$i<$zip->numFiles;$i++) {
                    app(ImportProgress::class)->checkpoint($run);
                    $entry=$zip->statIndex($i); ImportPath::entry($entry['name']);
                    $required+=$entry['size']; $entries++;
                    $zip->getExternalAttributesIndex($i,$os,$attributes);
                    if($os===ZipArchive::OPSYS_UNIX && (($attributes>>16)&0170000)===0120000)throw new \RuntimeException('Archive links are not supported.');
                }
            } finally {$zip->close();}
        }
        $inbox=config('platform.import_inbox_root');
        if(!is_dir($inbox))mkdir($inbox,0700,true);
        $expandedTotal=$required;
        // Already completed extracts need no additional space on a resumed run.
        foreach($archives as $archive) {
            $item=app(ImportJournal::class)->item($run,'archive:'.$archive);
            if($item && ($item->metadata['signature']??[])===['bytes'=>filesize($archive),'mtime'=>filemtime($archive)]) {
                $root=$inbox.'/archives/'.$item->metadata['sha256'];
                $zip=new ZipArchive; $zip->open($archive);
                try {
                    for($i=0;$i<$zip->numFiles;$i++) {
                        $entry=$zip->statIndex($i);$destination=$root.'/files/'.$entry['name'];
                        $signature=['size'=>$entry['size'],'crc'=>$entry['crc']];
                        if(is_file($root.'/.complete') || (app(ImportJournal::class)->done($run,'extract:'.$destination,$signature) && is_file($destination) && filesize($destination)===$entry['size']))$required-=$entry['size'];
                    }
                } finally {$zip->close();}
            }
        }
        $free=disk_free_space($inbox);
        if($entries>100000 || $expandedTotal>config('platform.media_upload_max_archive_bytes'))throw new \RuntimeException('Takeout extraction limit exceeded.');
        if($free===false || $free-$required<config('platform.media_upload_reserve_free_bytes'))throw new \RuntimeException('Not enough private storage for all Takeout parts.');
        app(TakeoutCatalog::class)->inspect($run,$archives,null,$batch['report']?ImportPath::resolve(config('platform.takeout_root'),$batch['report']):null);
        $roots=[];
        foreach($archives as $index => $archive) {
            $journal=app(ImportJournal::class);$key='takeout-part:'.$archive;
            $signature=['bytes'=>filesize($archive),'mtime'=>filemtime($archive)];
            $saved=$journal->item($run,$key);
            if($saved && $journal->done($run,$key,$signature) && is_dir($saved->metadata['root']??'')) {
                $roots[]=ImportPath::resolve($inbox,$saved->metadata['root']);continue;
            }
            app(ImportProgress::class)->checkpoint($run,'extract', ['part' => $index + 1, 'parts' => count($archives), 'archive' => basename($archive)], true);
            $root=app(LocalArchiveAdapter::class)->expandFile($run,$archive,basename($archive)); $roots[]=$root;
            app(LocalFolderAdapter::class)->importDirectory($run,$root,false);
            $journal->record($run,$key,basename($archive),'checkpoint','complete',null,['signature'=>$signature,'root'=>$root]);
        }
        app(ImportProgress::class)->checkpoint($run,'metadata');
        app(TakeoutContentImporter::class)->import($run,$roots);
    }
    private function report(string $batch): ?string
    {
        $name=preg_replace('/-\d+$/','',$batch).'-001.zip';
        $path=config('platform.takeout_root').'/'.$name;
        return is_file($path)&&!is_link($path)?$name:null;
    }
}
