<?php
namespace App\Services\Importing;
use App\Models\ImportRun;
use RuntimeException;
use ZipArchive;

class TakeoutCatalog
{
    public function inspect(ImportRun $run,array $archives,?string $folder,?string $report): void
    {
        $reader=app(TakeoutManifest::class); $available=[]; $bytes=0; $count=0; $expected=[];
        foreach($archives as $archive) {
            $zip=new ZipArchive;
            if($zip->open($archive)!==true)throw new RuntimeException('Cannot open Takeout ZIP.');
            try {
                for($i=0;$i<$zip->numFiles;$i++) {
                    $entry=$zip->statIndex($i); ImportPath::entry($entry['name']);
                    $zip->getExternalAttributesIndex($i,$os,$attributes);
                    if($os===ZipArchive::OPSYS_UNIX && (($attributes>>16)&0170000)===0120000)throw new RuntimeException('Archive links are not supported.');
                    if(str_ends_with($entry['name'],'/'))continue;
                    $available[$reader->entry($entry['name'])]=true;
                    $bytes+=$entry['size']; $count++;
                }
                if(!$expected)$expected=$reader->read($archive);
            } finally {$zip->close();}
        }
        if($folder) {
            foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder,\FilesystemIterator::SKIP_DOTS)) as $file) {
                if($file->isLink())throw new RuntimeException('Takeout folder links are not supported.');
                if(!$file->isFile())continue;
                $path=ImportPath::resolve($folder,$file->getPathname());
                $entry=str_replace('\\','/',substr($path,strlen($folder)+1));
                if(preg_match('/\.(part|tmp|ytdl)$/i',$entry)||str_starts_with($file->getFilename(),'.'))continue;
                if($file->getFilename()==='archive_browser.html') {
                    if(!$expected)$expected=$reader->read($path);
                    continue;
                }
                $available[$reader->entry($entry)]=true; $bytes+=$file->getSize(); $count++;
                if($count>100000)throw new RuntimeException('Takeout catalog limit exceeded.');
            }
        }
        if($report) {
            try {
                $expected=$reader->read($report)?:$expected;
                if(app(ImportJournal::class)->item($run,'takeout-report'))app(ImportJournal::class)->record($run,'takeout-report',basename($report),'checkpoint','complete');
            }
            catch(RuntimeException $error) {
                app(ImportJournal::class)->record($run,'takeout-report',basename($report),'manifest','failed',null,['reason'=>$error->getMessage()]);
            }
        }
        if($count>100000 || $bytes>config('platform.media_upload_max_archive_bytes'))throw new RuntimeException('Takeout extraction limit exceeded.');
        if(!$count)throw new RuntimeException(__('imports.takeout_empty'));
        $journal=app(ImportJournal::class);
        $missing=$expected?array_values(array_diff($expected,array_keys($available))):[];
        $extra=$expected?array_values(array_diff(array_keys($available),$expected)):[];
        $data=['manifest_available'=>(bool)$expected,'expected_files'=>count($expected),'available_files'=>count($available),
            'missing_files'=>count($missing),'extra_files'=>count($extra),'bytes'=>$bytes,
            'reason'=>__(!$expected?'imports.takeout_no_manifest':($missing?'imports.takeout_manifest_missing':'imports.takeout_manifest_checked'))];
        $journal->record($run,'takeout-manifest',__('imports.takeout_manifest'),'manifest',!$expected?'unsupported':($missing?'missing':'linked'),null,$data);
        // Rechecking after a retry can resolve a formerly missing file; stale missing outcomes must disappear.
        \App\Models\ImportItem::where('import_run_id',$run->id)->where('type','manifest-file')->delete();
        foreach($missing as $entry)$journal->record($run,'manifest-file:'.$entry,$entry,'manifest-file','missing',null,['reason'=>__('imports.takeout_manifest_missing')]);
        app(ImportProgress::class)->checkpoint($run,'prepare',['manifest'=>$data],true);
    }
}
