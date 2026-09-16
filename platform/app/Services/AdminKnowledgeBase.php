<?php

namespace App\Services;

use App\Models\KaiKnowledgeEntry;
use Illuminate\Support\Facades\DB;

class AdminKnowledgeBase
{
    private const MANUAL='../docs/KAI-ADMIN-MANUAL.md';

    public function entries(): array
    {
        return KaiKnowledgeEntry::where('active',true)->orderBy('key')->get()
            ->map(fn(KaiKnowledgeEntry $entry)=>$this->present($entry))->all();
    }

    public function context(string $question,?string $keyPrefix=null): array
    {
        $tokens=array_values(array_unique(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u',mb_strtolower($question),-1,PREG_SPLIT_NO_EMPTY)?:[],
            fn(string $token)=>mb_strlen($token)>=3
        )));
        if(!$tokens)return [];
        return KaiKnowledgeEntry::where('active',true)->when($keyPrefix,fn($query)=>$query->where('key','like',$keyPrefix.'%'))
            ->get()->map(function(KaiKnowledgeEntry $entry)use($tokens){
            $title=mb_strtolower($entry->title.' '.implode(' ',$entry->keywords??[]));
            $body=mb_strtolower($entry->content);$score=0;
            foreach($tokens as $token){if(str_contains($title,$token))$score+=4;elseif(str_contains($body,$token))$score++;}
            return ['entry'=>$entry,'score'=>$score];
        })->filter(fn(array $row)=>$row['score']>0)->sortByDesc('score')->take(8)
            ->map(fn(array $row)=>$this->present($row['entry']))->values()->all();
    }

    public function sync(): int
    {
        $manual=file_get_contents(base_path(self::MANUAL));
        if($manual===false)throw new \RuntimeException('KAI manual is missing.');
        preg_match_all('/^## ([a-z0-9-]+) — ([^\r\n]+)\RStatus: (implemented|planned|unverified)\RKeywords: ([^\r\n]+)\RSources: ([^\r\n]+)\R(.*?)(?=^## |\z)/msu',$manual,$matches,PREG_SET_ORDER);
        preg_match_all('/^## /m',$manual,$headings);
        if(!$matches||count($matches)!==count($headings[0]))throw new \RuntimeException('KAI manual has malformed entries.');
        $records=[];
        foreach($matches as $match){
            [, $key,$title,$status,$keywords,$sources,$content]=$match;$content=trim($content);
            if(isset($records[$key])||$content==='')throw new \RuntimeException('Duplicate or empty KAI entry: '.$key);
            $records[$key]=['key'=>$key,'title'=>trim($title),'status'=>$status,
                'keywords'=>array_values(array_filter(array_map('trim',explode(',',$keywords)))),
                'sources'=>trim($sources),'content'=>$content,'source_hash'=>hash('sha256',$match[0]),'active'=>true];
        }
        foreach(config('public_ui.navigation') as $key=>$item){
            $id='public-nav-'.$key;$label=__('public.nav_'.$key);
            $records[$id]=['key'=>$id,'title'=>$label,'status'=>'implemented',
                'keywords'=>[$label,$key,'navigation','menü','menu','finden','найти','где'],
                'sources'=>'platform/config/public_ui.php; platform/resources/views/public/header.blade.php',
                'content'=>$label.' befindet sich oben in der Website-Navigation und öffnet '.$item['path'].'. Auf schmalen Bildschirmen zuerst ☰ öffnen.',
                'source_hash'=>hash('sha256',$label.'|'.$item['path']),'active'=>true];
        }
        DB::transaction(function()use($records){
            KaiKnowledgeEntry::whereNotIn('key',array_keys($records))->update(['active'=>false]);
            foreach($records as $record)KaiKnowledgeEntry::updateOrCreate(['key'=>$record['key']],$record);
        });
        return count($records);
    }

    private function present(KaiKnowledgeEntry $entry): array
    {
        return ['id'=>$entry->key,'title'=>$entry->title,'status'=>$entry->status,
            'keywords'=>$entry->keywords,'content'=>$entry->content,'sources'=>$entry->sources];
    }
}
