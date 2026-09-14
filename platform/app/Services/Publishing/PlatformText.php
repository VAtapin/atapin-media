<?php
namespace App\Services\Publishing;
use App\Models\SourceRecord;
class PlatformText
{
    public static function value(SourceRecord $record,string $provider,string $key): string
    {
        $value=$record->metadata['platform_metadata'][$provider][$key]??null;
        return strip_tags(is_string($value)&&trim($value)!==''?$value:(string)$record->{$key});
    }
    public static function caption(SourceRecord $record,string $provider): string{return trim(self::value($record,$provider,'title')."\n\n".self::value($record,$provider,'body'));}
}
