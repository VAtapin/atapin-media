<?php
namespace App\Services\Importing;
use App\Models\{ImportRun,SourceRecord};
use App\Services\{PublicDocumentDownload,RichContent,Settings};
use Illuminate\Support\Facades\{Validator,Storage};
use Illuminate\Support\Str;

class WebDocumentAdapter implements ImportAdapter
{
    public function source():string{return 'web-document';}
    public function validate(array $input):array{return Validator::make($input,['source_ref'=>'required|url:https|max:255','target_profile'=>'nullable|in:posts,mixed'])->after(function($validator)use($input){$parts=parse_url($input['source_ref']??'');if(isset($parts['user'])||isset($parts['pass'])||isset($parts['port'])&&$parts['port']!==443)$validator->errors()->add('source_ref',__('imports.invalid_url'));})->validate();}
    public function normalize(array $input):array{return ['source'=>$this->source(),'source_kind'=>'web-document','source_ref'=>$input['source_ref'],'source_options'=>['url'=>$input['source_ref']],'target_profile'=>'posts'];}
    public function import(ImportRun $run):void
    {
        abort_unless(\App\Models\User::find($run->user_id)?->hasPermission('imports.manage')&&\App\Models\User::find($run->user_id)?->hasPermission('content.edit'),403);
        $url=$this->validate(['source_ref'=>$run->source_ref])['source_ref'];$download=app(PublicDocumentDownload::class)->download($url);
        try{
            $text=file_get_contents($download['path']);if(strlen($text)>PublicDocumentDownload::MAX_BYTES)throw new \RuntimeException(__('imports.web_document_invalid'));
            $items=$this->parse($text,$url);$hash=hash_file('sha256',$download['path']);$path='web-documents/'.$hash.'.txt';
            if(!Storage::disk('local')->exists($path)){ $stream=fopen($download['path'],'rb');try{Storage::disk('local')->put($path,$stream);}finally{fclose($stream);} }
            $media=app(ImportedMediaRegistry::class)->register(['source'=>'web-document','source_id'=>$hash,'title'=>parse_url($url,PHP_URL_HOST),'original_name'=>$hash.'.txt','kind'=>'document','mime'=>$download['mime'],'disk'=>'local','path'=>$path,'sha256'=>$hash,'bytes'=>strlen($text),'status'=>'ready','metadata'=>['library_only'=>true]]);
            $journal=app(ImportJournal::class);
            foreach($items as $item){app(ImportProgress::class)->checkpoint($run,'content');$key=$url.'|'.$item['key'];if($journal->done($run,$key))continue;
                $record=app(ImportedRecordMerger::class)->merge('web-document',hash('sha256',$key),'post',$item['title'],$item['body'],['body_format'=>'html','public_section'=>'beitraege','public_published'=>false,'external_sync_pending_review'=>true,'url'=>$item['url'],'feed_url'=>$url,'media_ids'=>[$media->id]]);
                if($record->wasRecentlyCreated)$record->update(['status'=>'review']);$media->usages()->firstOrCreate(['subject_type'=>SourceRecord::class,'subject_id'=>(string)$record->id,'used_as'=>'original']);$journal->record($run,$key,$item['title'],'content',$record->wasRecentlyCreated?'added':'duplicate',(string)$record->id);$run->increment('discovered');$run->increment($record->wasRecentlyCreated?'imported':'skipped');
            }
        }finally{if(is_file($download['path']))unlink($download['path']);}
    }
    public function parse(string $text,string $url):array
    {
        if(strlen($text)>PublicDocumentDownload::MAX_BYTES||!mb_check_encoding($text,'UTF-8')||stripos($text,'<!ENTITY')!==false)throw new \RuntimeException(__('imports.web_document_invalid'));
        $dom=new \DOMDocument;$previous=libxml_use_internal_errors(true);
        try{
            $xml=!preg_match('/<!DOCTYPE\s+html|<html\b/i',$text)&&preg_match('/<(?:rss|feed|rdf:RDF)\b/i',$text);
            if($xml){if(stripos($text,'<!DOCTYPE')!==false||!$dom->loadXML($text,LIBXML_NONET))throw new \RuntimeException(__('imports.web_document_invalid'));$xpath=new \DOMXPath($dom);$nodes=$xpath->query('//*[local-name()="item" or local-name()="entry"]');if($nodes->length>200)throw new \RuntimeException(__('imports.web_feed_limit'));$items=[];
                foreach($nodes as $node){$get=fn($name)=>$xpath->query('./*[local-name()="'.$name.'"]',$node)->item(0);$title=trim($get('title')?->textContent??'');$body=$get('encoded')?->textContent??$get('content')?->textContent??$get('description')?->textContent??$get('summary')?->textContent??'';$link=$get('link');$entryUrl=$link?->getAttribute('href')?:trim($link?->textContent??'');$key=trim($get('guid')?->textContent??$get('id')?->textContent??$entryUrl);if(!$key||!$title||!trim(strip_tags($body)))throw new \RuntimeException(__('imports.web_document_invalid'));$entryUrl=$this->safeLink($entryUrl)?$entryUrl:$url;$items[]=['key'=>$key,'title'=>mb_substr($title,0,255),'body'=>app(\App\Services\RichContent::class)->sanitize($body),'url'=>$entryUrl];}
                if(!$items)throw new \RuntimeException(__('imports.web_document_invalid'));return $items;
            }
            if(!$dom->loadHTML('<?xml encoding="UTF-8">'.$text,LIBXML_NONET))throw new \RuntimeException(__('imports.web_document_invalid'));$xpath=new \DOMXPath($dom);$title=trim($xpath->query('//title|//h1')->item(0)?->textContent??parse_url($url,PHP_URL_HOST));$root=$xpath->query('//article|//main')->item(0)??$xpath->query('//body')->item(0);$body=$root?$dom->saveHTML($root):'';$body=app(\App\Services\RichContent::class)->sanitize($body);if(!trim(strip_tags($body)))throw new \RuntimeException(__('imports.web_document_invalid'));return [['key'=>$url,'title'=>mb_substr($title,0,255),'body'=>$body,'url'=>$url]];
        }finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
    }
    private function safeLink(string $url):bool{return filter_var($url,FILTER_VALIDATE_URL)&&in_array(parse_url($url,PHP_URL_SCHEME),['https','http'],true)&&!parse_url($url,PHP_URL_USER)&&!parse_url($url,PHP_URL_PASS);}
}
