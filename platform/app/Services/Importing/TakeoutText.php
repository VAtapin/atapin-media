<?php
namespace App\Services\Importing;

class TakeoutText
{
    public function parse(string $value): array
    {
        if(!str_starts_with(ltrim($value),'{') && !str_starts_with(ltrim($value),'['))return ['text'=>$value,'runs'=>[],'references'=>[]];
        $runs=json_decode($value,true,64);
        if(!is_array($runs))$runs=json_decode('['.$value.']',true,64);
        if(!is_array($runs))throw new \RuntimeException('Invalid structured Takeout text.');
        if(isset($runs['text']))$runs=[$runs];
        if(!array_is_list($runs))throw new \RuntimeException('Unsupported structured Takeout text.');
        $text='';$references=[];
        foreach($runs as $run) {
            if(!is_array($run)||!is_string($run['text']??null))throw new \RuntimeException('Unsupported Takeout text fragment.');
            $text.=$run['text'];
            foreach(['videoLink'=>'externalVideoId','shortsLink'=>'externalVideoId','postLink'=>'externalPostId',
                'playlistLink'=>'externalPlaylistId','channelLink'=>'externalChannelId','mention'=>'externalChannelId'] as $type=>$key) {
                if(is_string($run[$type][$key]??null))$references[]=['type'=>$type,'id'=>$run[$type][$key],'text'=>$run['text']];
            }
            if(isset($run['timestamp']['startTimeSeconds']))$references[]=['type'=>'timestamp','seconds'=>$run['timestamp']['startTimeSeconds'],'text'=>$run['text']];
            if(is_string($run['link']['linkUrl']??null))$references[]=$this->url($run['link']['linkUrl'],$run['text']);
        }
        return ['text'=>$text,'runs'=>$runs,'references'=>array_values(array_unique($references,SORT_REGULAR))];
    }
    public function url(string $url,string $text=''): array
    {
        $parts=parse_url($url);$host=strtolower($parts['host']??'');$path=$parts['path']??'';
        if(in_array($host,['youtube.com','www.youtube.com','m.youtube.com','music.youtube.com','youtu.be'],true)) {
            parse_str($parts['query']??'',$query);
            if($host==='youtu.be')return ['type'=>'videoLink','id'=>ltrim($path,'/'),'text'=>$text];
            if(isset($query['v']))return ['type'=>'videoLink','id'=>$query['v'],'text'=>$text];
            if(isset($query['list']))return ['type'=>'playlistLink','id'=>$query['list'],'text'=>$text];
            if(preg_match('~^/(shorts|post|channel)/([^/]+)~',$path,$m))return ['type'=>['shorts'=>'shortsLink','post'=>'postLink','channel'=>'channelLink'][$m[1]],'id'=>$m[2],'text'=>$text];
            return ['type'=>'unresolved_youtube','text'=>$text];
        }
        return ['type'=>'external','url'=>$url,'text'=>$text];
    }
}
