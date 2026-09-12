<?php
namespace App\Services\Importing;
use App\Models\ImportRun;
use App\Models\SourceRecord;
use DOMDocument;
use DOMXPath;

/** Private exported account data and live chat, not public posts or fabricated shop products. */
class TakeoutAncillaryImporter
{
    public function import(ImportRun $run,array $files,callable $record,callable $image): array
    {
        $handled=[];$channels=[];$channelRows=[];$collections=[];$items=[];
        foreach($files as $file) {
            $folder=TakeoutCsv::key(basename(dirname($file['entry'])));$name=TakeoutCsv::key(pathinfo($file['entry'],PATHINFO_FILENAME));
            if(strtolower(pathinfo($file['entry'],PATHINFO_EXTENSION))==='html' && in_array($folder,['verlauf','history'],true)) {
                $this->history($run,$file,$record);$handled[]=$file['path'];continue;
            }
            if(strtolower(pathinfo($file['entry'],PATHINFO_EXTENSION))!=='csv')continue;
            $isSettings=in_array($folder,['beitrage','posts'],true)&&str_starts_with($name,'einstellungenfurkommentare');
            $isCoordinates=in_array($folder,['videometadaten','videometadata'],true)&&str_starts_with($name,'videoaufzeichnungen');
            if(!in_array($folder,['abos','subscriptions','kanale','channels','livechats','shoppingcollections'],true)&&!$isSettings&&!$isCoordinates)continue;
            $handled[]=$file['path'];
            $replay=in_array($folder,['kanale','channels','shoppingcollections'],true);
            $this->csv($run,$file,function($row)use($run,$file,$folder,$isSettings,$isCoordinates,$record,&$channels,&$channelRows,&$collections,&$items) {
                if(in_array($folder,['kanale','channels'],true)) {
                    $id=$row['kanalid']??$row['channelid']??'';
                    if($id)$channels[$id]=[...($channels[$id]??[]),...$row];
                    $channelRows[]=['file'=>basename($file['entry']),'row'=>$row];return;
                }
                if($folder==='shoppingcollections') {
                    $id=$row['shoppingcollectionid']??'';
                    if(!$id)throw new \RuntimeException('Shopping collection ID is missing.');
                    if(isset($row['shoppingcollectiontitle']))$collections[$id]=$row;
                    else $items[pathinfo($file['entry'],PATHINFO_FILENAME)][]=$row;
                    return;
                }
                if($isSettings||$isCoordinates) {
                    $id=$row[$isSettings?'beitragsid':'videoid']??'';
                    $parent=SourceRecord::where('source','youtube')->where('source_id',$id)->first();
                    if($parent) {
                        $record($id,$parent->kind,$parent->title,$parent->body??'',['takeout_data'=>[$isSettings?'comment_settings':'recording'=>$row]]);
                    } else $record('setting:'.hash('sha256',json_encode([$folder,$row])),'archive_setting',basename($file['entry']),'',
                        $this->archive(['parent_source_id'=>$id?:null,'takeout_data'=>['settings'=>$row]]));
                    return;
                }
                if(in_array($folder,['abos','subscriptions'],true)) {
                    $id=$row['kanalid']??$row['channelid']??'';
                    if(!$id)throw new \RuntimeException('Subscription channel ID is missing.');
                    $record('subscription:'.$id,'subscription',$row['kanaltitel']??$row['channeltitle']??$id,'',
                        $this->archive(['takeout_data'=>['subscription'=>$row]]));return;
                }
                $id=$row['livechatid']??'';
                if(!$id)throw new \RuntimeException('Live chat ID is missing.');
                $parsed=app(TakeoutText::class)->parse($row['textfurdenlivechat']??'');
                $record('live-chat:'.$id,'live_chat',mb_substr($parsed['text'],0,120)?:$id,$parsed['text'],[
                    'parent_source_id'=>($row['videoid']??'')?:null,'author'=>$row['kanalid']??null,
                    'timestamp'=>$row['zeitstempelbeierstellungdeslivechats']??null,'price'=>$row['preis']??null,
                    'takeout_data'=>['live_chat'=>$row],'structured_text'=>$parsed['runs'],'references'=>$parsed['references']]);
            },$replay);
        }
        foreach($channels as $id=>$channel) {
            $data=[];
            foreach($channelRows as $entry)if(($entry['row']['kanalid']??$entry['row']['channelid']??(count($channels)===1?$id:null))===$id)$data[$entry['file']][]=$entry['row'];
            $this->once($run,'channel:'.$id,$data,function()use($id,$channel,$data,$record,$run) {
                $record('channel:'.$id,'channel',$channel['kanaltiteloriginal']??$id,$channel['kanalbeschreibungoriginal']??'',
                    $this->archive(['takeout_data'=>['channel'=>$data]]));
                foreach($data as $rows)foreach($rows as $row)foreach($row as $key=>$value) {
                    if(in_array($key,['kanalbannerurl','vollstandigeinhaltsurldeskanalbilds'],true)&&$value!=='')
                        app(ImportJournal::class)->record($run,'remote-asset:'.hash('sha256',$value),$key,'connection','missing',null,['reason'=>__('imports.takeout_remote_asset')]);
                }
            });
        }
        // If no owning channel ID is exported, preserve its rows rather than discard them.
        foreach($channelRows as $entry)if(!$channels||count($channels)>1&&!isset($entry['row']['kanalid'])&&!isset($entry['row']['channelid']))$this->once($run,'channel-setting:'.hash('sha256',json_encode($entry)),$entry,
            fn()=>$record('channel-setting:'.hash('sha256',json_encode($entry)),'archive_setting',$entry['file'],'',$this->archive(['takeout_data'=>$entry])));
        foreach($collections as $id=>$row) {
            $title=$row['shoppingcollectiontitle']??$id;
            $data=['collection'=>$row,'items'=>$items[$title.'-items']??[]];unset($items[$title.'-items']);
            $this->once($run,'shopping:'.$id,$data,function()use($id,$row,$data,$title,$files,$record,$image) {
                $media=[];foreach($files as $file)if(TakeoutCsv::key(basename(dirname($file['entry'])))==='shoppingcollections'&&pathinfo($file['entry'],PATHINFO_FILENAME)===$title.'-thumbnail')$media=[...$media,...$image(basename($file['entry']))];
                $record('shopping:'.$id,'shopping_collection',$row['shoppingcollectiontitle']??$id,$row['shoppingcollectiondescription']??'',
                    $this->archive(['takeout_data'=>$data,'media_ids'=>array_values(array_unique($media))]));
            });
        }
        foreach($items as $name=>$rows)$this->once($run,'shopping-items:'.$name,$rows,
            fn()=>$record('shopping-items:'.$name,'archive_setting',$name,'',$this->archive(['takeout_data'=>['unmatched_collection_items'=>$rows]])));
        return $handled;
    }
    private function archive(array $data): array {return [...$data,'library_only'=>true,'archive_data'=>true];}
    private function once(ImportRun $run,string $id,array $data,callable $handler): void
    {
        $journal=app(ImportJournal::class);$key='takeout-aux:v2:'.$id.':'.hash('sha256',json_encode($data));
        if($journal->done($run,$key))return;
        app(ImportWorkBudget::class)->boundary($run);$handler();$journal->record($run,$key,$id,'checkpoint','complete');
    }
    private function csv(ImportRun $run,array $file,callable $handler,bool $replay): void
    {
        $journal=app(ImportJournal::class);
        try {
            foreach(app(TakeoutCsv::class)->rows($file['path']) as $index=>$row) {
                app(ImportProgress::class)->checkpoint($run);
                $key='takeout-aux-csv:v2:'.$file['path'].':'.$index.':'.hash('sha256',json_encode($row));
                if(!$replay&&$journal->done($run,$key))continue;
                if(!$replay)app(ImportWorkBudget::class)->boundary($run);
                try {$handler($row);$journal->record($run,$key,$file['entry'].' #'.($index+1),'checkpoint','complete');}
                catch(ImportStopped|ImportYielded $e){throw $e;}
                catch(\Throwable $e){$journal->record($run,$key,$file['entry'].' #'.($index+1),'metadata','failed',null,['error'=>$e->getMessage()]);}
            }
            $journal->record($run,'unsupported:'.$file['path'],$file['entry'],'checkpoint','complete');
        } catch(ImportStopped|ImportYielded $e){throw $e;}
        catch(\Throwable $e){$journal->record($run,'unsupported:'.$file['path'],$file['entry'],'metadata','failed',null,['error'=>$e->getMessage()]);}
    }
    private function history(ImportRun $run,array $file,callable $record): void
    {
        if(filesize($file['path'])>64*1024*1024)throw new \RuntimeException('History HTML exceeds 64 MB.');
        $document=new DOMDocument;$previous=libxml_use_internal_errors(true);
        try {$document->loadHTMLFile($file['path'],LIBXML_NONET);}finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
        $xpath=new DOMXPath($document);$occurrences=[];
        $cells=$xpath->query('//*[contains(concat(" ",@class," ")," outer-cell ")]');
        if(!$cells->length) {
            app(ImportJournal::class)->record($run,'unsupported:'.$file['path'],$file['entry'],'metadata','unsupported',null,['reason'=>__('imports.takeout_unknown_history')]);return;
        }
        foreach($cells as $index=>$cell) {
            app(ImportProgress::class)->checkpoint($run);
            $links=[];$references=[];$title='';
            foreach($xpath->query('.//a[@href]',$cell) as $anchor) {
                $url=$anchor->getAttribute('href');$links[]=['text'=>$anchor->textContent,'url'=>$url];$title=$title?:$anchor->textContent;
                $references[]=app(TakeoutText::class)->url($url,$anchor->textContent);
            }
            $text=trim($cell->textContent);$data=['text'=>$text,'links'=>$links];$hash=hash('sha256',json_encode($data));$n=$occurrences[$hash]=($occurrences[$hash]??0)+1;
            $id='history:'.$hash.':'.$n;
            $this->once($run,$id,$data,fn()=>$record($id,'history',$title?:mb_substr($text,0,120),$text,
                $this->archive(['takeout_data'=>['history'=>$data],'references'=>$references,'original_media_id'=>$file['media_id']])));
        }
        app(ImportJournal::class)->record($run,'unsupported:'.$file['path'],$file['entry'],'checkpoint','complete');
    }
}
