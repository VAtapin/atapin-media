<?php
namespace App\Services\Publishing;
use App\Models\SourceRecord;
class PlatformText
{
    public static function value(SourceRecord $record,string $provider,string $key): string
    {
        $value=$record->metadata['platform_metadata'][$provider][$key]??null;
        $text=strip_tags(is_string($value)&&trim($value)!==''?$value:(string)$record->{$key});
        return $key==='body'?self::withHashtags($record,$provider,$text):$text;
    }
    public static function caption(SourceRecord $record,string $provider): string{return trim(self::value($record,$provider,'title')."\n\n".self::value($record,$provider,'body'));}
    public static function withHashtags(SourceRecord $record,string $provider,string $text): string
    {
        $tags=array_map(fn($tag)=>'#'.ltrim($tag,'#'),array_filter($record->metadata['platform_metadata'][$provider]['hashtags']??[],fn($tag)=>is_string($tag)&&preg_match('/^#?[\p{L}\p{N}_]{1,80}$/u',$tag)));
        return trim($text.($tags?"\n\n".implode(' ',array_unique($tags)):''));
    }
    public static function xCaption(SourceRecord $record): string{return mb_strimwidth(self::caption($record,'x'),0,280,'…');}
}
