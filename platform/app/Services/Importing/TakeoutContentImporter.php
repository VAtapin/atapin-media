<?php
namespace App\Services\Importing;

use App\Models\ImportRun;
use App\Models\Media;
use App\Models\SourceRecord;
use Illuminate\Support\Facades\DB;

class TakeoutContentImporter
{
    private array $files=[];
    private array $videos=[];
    private array $playlists=[];
    private array $assets=[];
    private array $videoFiles=[];
    private ImportRun $run;
    private TakeoutObjectMetadata $objectMetadata;
    private TakeoutMediaImporter $mediaImporter;
    private string $filesFingerprint;

    public function import(ImportRun $run,array $roots): void
    {
        $this->run=$run; $this->files=$this->videos=$this->playlists=$this->assets=$this->videoFiles=[];
        $this->mediaImporter = app(TakeoutMediaImporter::class);
        foreach($roots as $root) {
            $iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root,\FilesystemIterator::SKIP_DOTS));
            foreach($iterator as $file) {
                if(!$file->isFile()||$file->isLink())continue;
                $path=ImportPath::resolve($root,$file->getPathname());
                $entry=str_replace('\\','/',substr($path,strlen(realpath($root))+1));
                app(ImportProgress::class)->checkpoint($run);
                $this->files[]=['path'=>$path,'entry'=>$entry,'media_id'=>null];
            }
        }
        $this->filesFingerprint=hash('sha256',json_encode(array_map(fn($file)=>[$file['entry'],filesize($file['path']),filemtime($file['path'])],$this->files),JSON_THROW_ON_ERROR));
        // All parts are indexed before metadata is attached: CSV and video may be in different ZIPs.
        foreach($this->files as $file)if($this->isCsv($file,'videometadaten','videos')) {
            $this->rows($file,function($row){$id=$row['videoid']??'';if(!$id)return false;$this->videos[$id]=$row;return true;},true);
        }
        $this->matchVideos();
        $this->objectMetadata = app(TakeoutObjectMetadata::class);
        $this->objectMetadata->load($run, $this->files);
        foreach($this->files as $file)if($this->isCsv($file,'playlists','playlists')) {
            $this->rows($file,function($row){$id=$row['playlistid']??'';if(!$id)return false;$this->playlists[$id]=$row;return true;},true);
        }
        $this->importPlaylists();
        foreach($this->videos as $id=>$row)$this->video($id,$row);
        // Text fragments may provide a newer, more complete description than Videos.csv.
        foreach($this->files as $file)if($this->isCsv($file,'beitrage','beitrage'))$this->rows($file,function($row) {
            foreach($row as $key=>$name)if(is_string($name)&&preg_match('/(?:namefurbild|imagename|bildnamefuroption)/',$key))foreach($this->imageFiles($name) as $image)$this->mediaImporter->prepare($this->run,$image);
            return DB::transaction(fn()=>$this->post($row));
        });
        foreach($this->files as $file)if($this->isCsv($file,'kommentare','kommentare'))$this->rows($file,$this->comment(...));
        $handled=app(TakeoutAncillaryImporter::class)->import($run,$this->files,$this->record(...),$this->image(...));
        // Keep unmatched binaries as unsorted files, without inventing a content relationship.
        foreach($this->files as $file) {
            if(!preg_match('/\.(mp4|webm|mkv|mov|avi|mp3|m4a|wav|ogg|flac|png|jpe?g|webp|gif|srt|vtt|ass)$/i',$file['entry']))continue;
            if(app(ImportJournal::class)->done($run,'takeout-file:v3:'.$file['path']))continue;
            app(ImportWorkBudget::class)->boundary($run);
            $this->mediaImporter->file($run,$file);
        }
        foreach($this->files as $file) {
            $key=TakeoutCsv::key($file['entry']);
            if(!preg_match('/\.(csv|html|json)$/i',$file['entry']))continue;
            if(in_array($file['path'],$handled,true)||basename($file['path'])==='archive_browser.html' || $this->isCsv($file,'videometadaten','videos') || $this->isCsv($file,'videometadaten','videotexte') || $this->isCsv($file,'beitrage','beitrage') || $this->isCsv($file,'kommentare','kommentare') || str_contains($key,'playlists'))continue;
            // GPS/video recording coordinates, channel settings and viewing history remain private originals.
            app(ImportJournal::class)->record($run,'unsupported:'.$file['path'],$file['entry'],'metadata','unsupported',$file['media_id'],['reason'=>'No supported content schema; original retained.']);
        }
        foreach(SourceRecord::where('source','youtube')->whereIn('source_id',array_keys($this->videos))->cursor() as $record) {
            $key='takeout-repair:'.$record->id;
            if(app(ImportJournal::class)->done($run,$key))continue;
            app(ImportWorkBudget::class)->boundary($run);
            app(LocalMediaLinks::class)->repair($record);
            app(ImportJournal::class)->record($run,$key,$record->title,'checkpoint','complete');
        }
    }
    private function isCsv(array $file,string $directory,string $name): bool
    {
        $directories=['videometadaten'=>['videometadaten','videometadata'],'beitrage'=>['beitrage','posts'],'kommentare'=>['kommentare','comments']][$directory]??[$directory];
        $names=['videotexte'=>['videotexte','videotexts'],'beitrage'=>['beitrage','posts'],'kommentare'=>['kommentare','comments']][$name]??[$name];
        return strtolower(pathinfo($file['entry'],PATHINFO_EXTENSION))==='csv'
            && collect($directories)->contains(fn($dir)=>str_contains(TakeoutCsv::key(dirname($file['entry'])),$dir))
            && in_array(preg_replace('/\d+$/','',TakeoutCsv::key(pathinfo($file['entry'],PATHINFO_FILENAME))),$names,true);
    }
    private function videoTitle(array $row,string $id): string {return $row['videotiteloriginal']??$row['videotitleoriginal']??$row['title']??$id;}
    private function matchVideos(): void
    {
        $titles=[]; foreach($this->videos as $id=>$row)$titles[$id]=TakeoutCsv::title($this->videoTitle($row,$id));
        foreach($this->files as $file) {
            if(!preg_match('/\.(mp4|webm|mkv|mov|avi)$/i',$file['entry']))continue;
            $name=pathinfo($file['entry'],PATHINFO_FILENAME); $norm=TakeoutCsv::title($name);
            $matches=[];
            if(isset($this->videos[$name]))$matches=[$name];
            else {
                // Preserve whitespace/punctuation first: two different uploads can have nearly identical titles.
                foreach($this->videos as $id=>$row)if($this->videoTitle($row,$id)===$name)$matches[]=$id;
                if(!$matches)foreach($titles as $id=>$title)if($title===$norm)$matches[]=$id;
            }
            if(count($matches)===1) {
                $this->videoFiles[$matches[0]][]=$file;
            } else app(ImportJournal::class)->record($this->run,'match:'.$file['path'],$file['entry'],'connection',count($matches)>1?'ambiguous':'unmatched',$file['media_id'],['candidate_ids'=>$matches,'reason'=>'No unique export title/ID match.']);
        }
    }
    private function video(string $id,array $row): void
    {
        $files = $this->videoFiles[$id] ?? [];
        $texts = $this->objectMetadata->texts[$id] ?? [];
        $key='takeout-video:v3:'.$id.':'.hash('sha256',json_encode([$row,$this->filesFingerprint,$this->objectMetadata->fingerprint($id)]));
        if(app(ImportJournal::class)->done($this->run,$key))return;
        app(ImportWorkBudget::class)->boundary($this->run);
        app(ImportProgress::class)->checkpoint($this->run);
        // Copy large binaries before the DB transaction: progress and stop remain visible.
        foreach($files as $file) {
            $this->mediaImporter->prepare($this->run,$file);
            foreach($this->sidecars($file,$id) as $sidecar)$this->mediaImporter->prepare($this->run,$sidecar);
        }
        foreach($row as $column=>$name)if(is_string($name)&&preg_match('/(?:thumbnail|imagename|namefurbild|miniaturansicht)/',$column))foreach($this->imageFiles($name) as $image)$this->mediaImporter->prepare($this->run,$image);
        DB::transaction(function () use ($id, $row, $files, $texts, $key) {
        $title = $this->videoTitle($row, $id);
        $body = $row['videobeschreibungoriginal'] ?? $row['videodescriptionoriginal'] ?? '';
        $textMetadata = [];
        foreach ($texts as $text) {
            $description = $this->fragments($text, ['videobeschreibungtextabschnitt','descriptiontextsegment']);
            if (mb_strlen($description) > mb_strlen($body)) $body = $description;
            $textTitle = $this->fragments($text, ['videotiteltextabschnitt','titletextsegment']);
            if ($textTitle) $title = $textTitle;
            $textMetadata = ['raw_takeout_text' => $text, 'video_text' => $text];
        }
        $this->assets[$id] = [];
        foreach ($files as $file) {
            $media = $this->mediaImporter->file($this->run, $file, $title, 'video');
            $this->assets[$id][] = $media->id;
            app(ImportJournal::class)->record($this->run,'match:'.$file['path'],$title,'connection','linked',$media->id,['youtube_id'=>$id]);
            foreach ($this->sidecars($file,$id) as $sidecar) {
                $extension = strtolower(pathinfo($sidecar['entry'], PATHINFO_EXTENSION));
                $role = in_array($extension,['srt','vtt','ass']) ? 'subtitles' : 'thumbnail';
                $asset = $this->mediaImporter->file($this->run,$sidecar,$title,$role);
                $this->assets[$id][]=$asset->id;
            }
        }
        foreach ($row as $column => $value) {
            if (is_string($value) && preg_match('/(?:thumbnail|imagename|namefurbild|miniaturansicht)/',$column)) $this->assets[$id]=[...$this->assets[$id],...$this->image($value)];
        }
        $tags=[]; foreach($row as $column=>$value)if(preg_match('/^tag\d+$/',$column)&&$value!=='')$tags[]=$value;
        $existing=SourceRecord::where('source','youtube')->where('source_id',$id)->first();
        $previous=SourceRecord::where('source','catalog-reset')->where('metadata->catalog_reset->source_id',$id)->first();
        $knownShort=$existing?->kind==='short' || in_array('shorts',$previous?->metadata['sources']??[],true) || ($previous?->metadata['catalog_reset']['kind']??'')==='short';
        $kind=$knownShort?'short':'video';
        $record=$this->record($id,$kind,$title,$body,[
            'raw_takeout'=>$row,'original_tags'=>$tags,'tags'=>$tags,'media_ids'=>array_values(array_unique($this->assets[$id]??[])),
            'duration'=>isset($row['ungefahredauerms'])?(float)$row['ungefahredauerms']/1000:null,
            'published_at'=>$row['zeitstempelbeivideoveroffentlichung']??null,'privacy'=>$row['datenschutz']??null,
            'format_needs_review'=>!$knownShort && !$existing,'takeout_schema_version'=>2,
            'takeout_data'=>['video'=>$row,...$textMetadata],
        ]);
        foreach($this->assets[$id]??[] as $mediaId)if($media=Media::find($mediaId))$media->update(['metadata'=>[...($media->metadata??[]),'youtube_id'=>$id]]);
        app(LocalMediaLinks::class)->repair($record);
        $this->objectMetadata->attach($id,$kind,$title,$body,$this->record(...),$this->comment(...));
        \App\Models\CollectionItem::where('source_id',$id)->whereIn('collection_id',\App\Models\Collection::where('source','youtube')->select('id'))->update(['source_record_id'=>$record->id]);
        app(ImportJournal::class)->record($this->run,$key,$record->title,'checkpoint','complete',(string)$record->id);
        });
    }
    private function record(string $id,string $kind,string $title,string $body,array $metadata=[]): SourceRecord
    {
        return app(ContentMetadataImporter::class)->record('youtube',$id,$kind,mb_substr($title,0,255),$body,[...$metadata,'import_id'=>$this->run->id,'takeout'=>true,'takeout_schema_version'=>2,'target_profile'=>$this->run->target_profile??'mixed']);
    }
    private function rows(array $file,callable $handler,bool $replay=false): void
    {
        try {
            foreach(app(TakeoutCsv::class)->rows($file['path']) as $index=>$row) {
                app(ImportProgress::class)->checkpoint($this->run);
                $key='csv:v2:'.$file['path'].':'.$index.':'.hash('sha256',json_encode($row));
                if(!$replay && app(ImportJournal::class)->done($this->run,$key))continue;
                if(!$replay)app(ImportWorkBudget::class)->boundary($this->run);
                try {
                    $ok=$handler($row);
                    app(ImportJournal::class)->record($this->run,$key,$file['entry'].' #'.($index+1),$ok?'checkpoint':'metadata',$ok?'complete':'unsupported',null,$ok?[]:['reason'=>__('imports.takeout_row_id')]);
                } catch(ImportStopped|ImportYielded $e){throw $e;} catch(\Throwable $e) {
                    app(ImportJournal::class)->record($this->run,$key,$file['entry'].' #'.($index+1),'metadata','failed',null,['error'=>$e->getMessage()]);
                }
            }
            if(app(ImportJournal::class)->item($this->run,'csv-error:'.$file['path']))
                app(ImportJournal::class)->record($this->run,'csv-error:'.$file['path'],$file['entry'],'checkpoint','complete');
        } catch(ImportStopped|ImportYielded $e){throw $e;} catch(\Throwable $e) {
            app(ImportJournal::class)->record($this->run,'csv-error:'.$file['path'],$file['entry'],'metadata','failed',null,['error'=>$e->getMessage()]);
        }
    }
    private function sidecars(array $file,string $id): array
    {
        $stem=pathinfo($file['entry'],PATHINFO_FILENAME);
        return array_values(array_filter($this->files,function($sidecar)use($file,$id,$stem) {
            $extension=strtolower(pathinfo($sidecar['entry'],PATHINFO_EXTENSION));$name=pathinfo($sidecar['entry'],PATHINFO_FILENAME);
            return basename(dirname($sidecar['entry']))===basename(dirname($file['entry']))
                && in_array($extension,['jpg','jpeg','png','webp','gif','srt','vtt','ass'],true)
                && ($name===$stem||$name===$id||(in_array($extension,['srt','vtt','ass'],true)&&str_starts_with($name,$stem.'.')));
        }));
    }
    private function imageFiles(string $name): array
    {
        if($name==='')return [];
        $found=array_values(array_filter($this->files,fn($file)=>basename($file['entry'])===basename($name) && preg_match('/\.(png|jpe?g|webp|gif)$/i',$file['entry'])));
        if(!$found && pathinfo($name,PATHINFO_EXTENSION)==='')$found=array_values(array_filter($this->files,fn($file)=>pathinfo($file['entry'],PATHINFO_FILENAME)===basename($name) && preg_match('/\.(png|jpe?g|webp|gif)$/i',$file['entry'])));
        return $found;
    }
    private function image(string $name): array
    {
        if($name==='')return [];
        $ids=[];
        foreach($this->imageFiles($name) as $file)$ids[]=$this->mediaImporter->file($this->run,$file,null,'thumbnail')->id;
        $ids=array_values(array_unique($ids));
        if(count($ids)!==1)app(ImportJournal::class)->record($this->run,'image-match:'.$name,$name,'connection',count($ids)>1?'ambiguous':'unmatched',null,['candidate_ids'=>$ids,'reason'=>__('imports.takeout_attachment')]);
        return count($ids)===1?$ids:[];
    }
    private function post(array $row): bool
    {
        $id=$row['beitragsid']??$row['postid']??''; if(!$id)return false;
        $parsed=app(TakeoutText::class)->parse($row['textdesbeitrags']??$row['posttext']??'');$text=$parsed['text']; $images=[]; $options=[];
        foreach($row as $key=>$value) {
            if(preg_match('/^(?:namefurbild|imagename)\d+$/',$key))$images=[...$images,...$this->image($value)];
            if(preg_match('/^textderantwortoption(\d+)furumfragequiz$/',$key,$m)&&$value!=='') {
                $n=$m[1];$answer=app(TakeoutText::class)->parse($value);$explanation=app(TakeoutText::class)->parse($row['erklarungzurrichtigenantwort'.$n.'imquiz']??'');
                $options[]=['text'=>$answer['text'],'id'=>$row['idderantwortoption'.$n.'furumfragequiz']??null,'position'=>(int)$n,
                    'media_ids'=>$this->image($row['bildnamefuroption'.$n.'derumfrage']??''),'is_correct'=>($row['richtigeantwortoption1furquiz']??'')===$n,
                    'explanation'=>$explanation['text'],'references'=>[...$answer['references'],...$explanation['references']]];
            }
        }
        $this->record($id,'post',mb_substr($text,0,120)?:$id,$text,['raw_takeout'=>$row,'media_ids'=>array_values(array_unique($images)),
            'published_at'=>$row['zeitstempelderbeitragsveroffentlichung']??null,'references'=>$parsed['references'],'structured_text'=>$parsed['runs'],
            'takeout_data'=>['post'=>$row],'post_type'=>$row['beitragstyp']??null]);
        if($options)$this->record('poll:'.$id,'poll',mb_substr($text,0,120)?:$id,$text,['parent_source_id'=>$id,'media_ids'=>array_values(array_unique(array_merge(...array_column($options,'media_ids')))),'poll'=>['options'=>$options,'quiz'=>($row['beitragstyp']??'')==='Quiz'],
            'raw_takeout'=>$row,'takeout_data'=>['post'=>$row],'references'=>[...$parsed['references'],...array_merge(...array_column($options,'references'))]]);
        $this->objectMetadata->attach($id,'post',mb_substr($text,0,120)?:$id,$text,$this->record(...),$this->comment(...));
        return true;
    }
    private function comment(array $row): bool
    {
        $id=$row['kommentarid']??$row['commentid']??''; $parent=$row['videoid']??''; $parent=$parent?:($row['beitragsid']??$row['postid']??'');
        if(!$id)return false;
        $parsed=app(TakeoutText::class)->parse($row['kommentartext']??$row['commenttext']??'');$text=$parsed['text'];
        $this->record('comment:'.$parent.':'.$id,'comment',mb_substr($text,0,120)?:$id,$text,['parent_source_id'=>$parent?:null,
            'parent_comment_id'=>$row['ubergeordnetekommentarid']??null,'author'=>$row['kanalid']??null,
            'timestamp'=>$row['zeitstempeldererstellungdeskommentars']??null,'raw_takeout'=>$row,'takeout_data'=>['comment'=>$row],
            'references'=>$parsed['references'],'structured_text'=>$parsed['runs'],'price'=>$row['preis']??null,
            'top_level_comment_id'=>$row['kommentaridderoberstenebene']??null]); return true;
    }
    private function importPlaylists(): void
    {
        $members=[];
        foreach($this->files as $file) {
            if(!str_contains(TakeoutCsv::key(dirname($file['entry'])),'playlists') || !preg_match('/(?:-Videos|-videos)\.csv$/u',$file['entry']))continue;
            $title=preg_replace('/-videos$/i','',pathinfo($file['entry'],PATHINFO_FILENAME)); $norm=TakeoutCsv::title($title);
            $ids=[];foreach($this->playlists as $id=>$row)if(TakeoutCsv::title($row['playlisttiteloriginal']??$row['playlisttitleoriginal']??'')===$norm)$ids[]=$id;
            if(count($ids)>1) {app(ImportJournal::class)->record($this->run,'playlist-match:'.$file['path'],$file['entry'],'playlist','ambiguous',null,['candidate_ids'=>$ids]);continue;}
            $id=$ids[0]??'takeout-playlist:'.hash('sha256',$norm); $members[$id]??=[];
            $this->rows($file,function($row)use(&$members,$id) {
                $videoId=$row['videoid']??'';if(!$videoId)return false;
                $members[$id][]=['id'=>$videoId,'position'=>count($members[$id])+1,'title'=>$this->videoTitle($this->videos[$videoId]??[],$videoId)];return true;
            },true);
            $this->playlists[$id]??=['playlisttiteloriginal'=>$title];
        }
        foreach($this->playlists as $id=>$row) {
            // Membership rows are cheap to read again so a resumed playlist never loses completed positions.
            if(!isset($members[$id]))$members[$id]=[];
            $data=['id'=>$id,'title'=>$row['playlisttiteloriginal']??$row['playlisttitleoriginal']??$id,
                'description'=>$row['playlistbeschreibungoriginal']??'','ordered_items'=>$members[$id]];
            $key='playlist:'.$id.':'.hash('sha256',json_encode([$row,$data]));
            if(app(ImportJournal::class)->done($this->run,$key))continue;
            app(ImportWorkBudget::class)->boundary($this->run);
            $existing=\App\Models\Collection::where('source','youtube')->where('source_id',$id)->exists();
            $collection=app(ContentMetadataImporter::class)->playlist('youtube',$data,['import_id'=>$this->run->id,'raw_takeout'=>$row,'takeout'=>true]);
            app(ImportJournal::class)->record($this->run,$key,$data['title'],'playlist',$existing?'merged':'added',(string)$collection->id);
        }
    }
    private function fragments(array $row,array $prefixes): string
    {
        $parts=[];
        foreach($row as $key=>$value)foreach($prefixes as $prefix)if(preg_match('/^'.preg_quote($prefix,'/').'e?(\d+)$/',$key,$m))$parts[(int)$m[1]]=$value;
        ksort($parts); return implode('',$parts);
    }
}
