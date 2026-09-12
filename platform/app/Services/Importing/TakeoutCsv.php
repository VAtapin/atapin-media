<?php
namespace App\Services\Importing;

use Illuminate\Support\Str;

class TakeoutCsv
{
    public static function key(string $value): string
    {
        return preg_replace('/[^a-z0-9]/','',strtolower(Str::ascii(ltrim($value,"\xEF\xBB\xBF"))));
    }
    public static function title(string $value): string
    {
        if(class_exists(\Normalizer::class))$value=\Normalizer::normalize($value,\Normalizer::FORM_C);
        return mb_strtolower(preg_replace('/[^\p{L}\p{N}]/u','',$value));
    }
    public function rows(string $path): \Generator
    {
        if(filesize($path)>64*1024*1024) throw new \RuntimeException('CSV exceeds 64 MB.');
        $stream=fopen($path,'rb');
        if(!$stream)throw new \RuntimeException('Cannot read CSV.');
        try {
            $headers=fgetcsv($stream,escape:'');
            if(!$headers)return;
            $keys=array_map(self::key(...),$headers);
            while(($values=fgetcsv($stream,escape:''))!==false) {
                if($values===[null])continue;
                if(count($values)!==count($keys))throw new \RuntimeException('CSV column count mismatch.');
                yield array_combine($keys,$values);
            }
        } finally {fclose($stream);}
    }
}
