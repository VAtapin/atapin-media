<?php
namespace App\Services;
use App\Models\{Media,SourceRecord};
use Illuminate\Support\Facades\{DB,Storage};
class DocumentArticleImport
{
    public const MIMES=['text/plain','text/html','application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    public function import(Media $media): SourceRecord
    {
        abort_unless(in_array($media->mime,self::MIMES,true)&&!$media->archived_at,422);
        $locator=app(MediaOriginalLocator::class);$location=$locator->find($media);if(!$location)throw new \RuntimeException('Original unavailable.');
        $stream=Storage::disk($location['disk'])->readStream($location['path']);if(!is_resource($stream))throw new \RuntimeException('Original unreadable.');
        try{$text=stream_get_contents($stream,20*1024*1024+1);}finally{fclose($stream);}
        if(strlen($text)>20*1024*1024)throw new \RuntimeException('Document too large.');
        $format='plain';
        if($media->mime==='application/vnd.openxmlformats-officedocument.wordprocessingml.document'){
            $temp=tempnam(sys_get_temp_dir(),'atapin-doc-');if(!$temp)throw new \RuntimeException('Temporary file unavailable.');$zip=new \ZipArchive;
            try{file_put_contents($temp,$text);if($zip->open($temp)!==true)throw new \RuntimeException('Invalid DOCX.');$stat=$zip->statName('word/document.xml');if(!$stat||$stat['size']>5*1024*1024)throw new \RuntimeException('Invalid document XML.');$xml=$zip->getFromName('word/document.xml');if(!$xml||stripos($xml,'<!DOCTYPE')!==false)throw new \RuntimeException('Invalid document XML.');$dom=new \DOMDocument;$previous=libxml_use_internal_errors(true);try{if(!$dom->loadXML($xml,LIBXML_NONET))throw new \RuntimeException('Invalid document XML.');}finally{libxml_clear_errors();libxml_use_internal_errors($previous);}$xpath=new \DOMXPath($dom);$xpath->registerNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');$paragraphs=[];foreach($xpath->query('//w:body//w:p') as $paragraph){$line='';foreach($xpath->query('.//w:t|.//w:tab|.//w:br',$paragraph) as $node)$line.=match($node->localName){'tab'=>"\t",'br'=>"\n",default=>$node->textContent};$paragraphs[]=$line;}$text=implode("\n\n",$paragraphs);
            }finally{try{$zip->close();}catch(\Throwable){}unlink($temp);}
        }elseif($media->mime==='text/html'){$text=app(RichContent::class)->sanitize($text);$format='html';}
        else{if(str_starts_with($text,"\xEF\xBB\xBF"))$text=substr($text,3);if(str_starts_with($text,"\xFF\xFE"))$text=mb_convert_encoding(substr($text,2),'UTF-8','UTF-16LE');elseif(str_starts_with($text,"\xFE\xFF"))$text=mb_convert_encoding(substr($text,2),'UTF-8','UTF-16BE');if(!mb_check_encoding($text,'UTF-8'))throw new \RuntimeException('Use UTF-8 text.');}
        if(trim(strip_tags($text))===''||mb_strlen($text)>1000000)throw new \RuntimeException('Document text empty or too large.');
        return DB::transaction(function()use($media,$text,$format){
            $locked=Media::lockForUpdate()->findOrFail($media->id);abort_if($locked->archived_at,422);
            $record=SourceRecord::firstOrCreate(['source'=>'manual','source_id'=>'document:'.$media->id],['kind'=>'post','title'=>$media->title,'body'=>$text,'status'=>'unsorted','metadata'=>['body_format'=>$format,'public_section'=>'beitraege','public_published'=>false,'classification_origin'=>'manual','document_media_id'=>$media->id,'media_ids'=>[$media->id]]]);
            $media->usages()->firstOrCreate(['subject_type'=>SourceRecord::class,'subject_id'=>(string)$record->id,'used_as'=>'original']);app(Audit::class)->record('document.article_imported',$media->id,['record_id'=>$record->id]);return $record;
        });
    }
}
