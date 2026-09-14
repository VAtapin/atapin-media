<?php

namespace App\Services;

use App\Models\{DesktopAiRequest, SourceRecord, User};
use App\Services\Importing\{ContentAssignment, ContentState};
use Illuminate\Support\Facades\{DB, Gate};

class DesktopAi
{
    public function submit(User $user, array $data): DesktopAiRequest
    {
        abort_unless(app(ContentShortDescriptions::class)->available(),422,__('workspaces.ai_unavailable'));
        $record = !empty($data['source_record_id']) ? SourceRecord::findOrFail($data['source_record_id']) : null;
        $product=!empty($data['product_id'])?\App\Models\Product::findOrFail($data['product_id']):null;
        $data['context']=[...($data['context']??[]),'locale'=>app()->getLocale()];
        $entry = DB::transaction(function () use ($user,$data,$record,$product) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_if(DesktopAiRequest::where('user_id',$user->id)->where('created_at','>=',now()->startOfDay())->count() >= 50,429,__('workspaces.ai_limit'));
            return DesktopAiRequest::create([...$data,'user_id'=>$user->id,'source_version'=>$record?app(ContentState::class)->version($record):($product?$this->bookVersion($product):null)]);
        });
        \App\Jobs\AnswerDesktopAi::dispatch($entry->id)->afterCommit();
        return $entry;
    }

    public function answer(DesktopAiRequest $entry): array
    {
        if (!app(ContentShortDescriptions::class)->available()) throw new \RuntimeException('AI disabled.');
        $record = $entry->record;$product=$entry->product;
        if($entry->source_version&&!$record&&!$product)throw new \RuntimeException('Source no longer exists.');
        if($entry->source_record_id&&!$record)throw new \RuntimeException('Source unavailable.');
        if($entry->product_id&&!$product)throw new \RuntimeException('Book unavailable.');
        $source=$record?['title'=>$record->title,'body'=>mb_substr(strip_tags($record->body??''),0,10000),'author'=>$record->metadata['author']??null,'summary'=>$record->metadata['short_description']??null,'platform_text'=>$record->metadata['platform_metadata'][$entry->context['provider']??'']??null]:null;
        if($product)$source=['title'=>$product->title,'description'=>$product->description,'author'=>$product->author,'contents'=>mb_substr($product->contents??'',0,10000)];
        if($record&&$entry->purpose==='reply'){$parent=SourceRecord::find($record->metadata['parent_record_id']??0)??SourceRecord::where('source',$record->source)->where('source_id',$record->metadata['parent_source_id']??'')->first();$source['discussion']=$parent?['title'=>$parent->title,'body'=>mb_substr(strip_tags($parent->body??''),0,5000)]:null;}
        return app(\App\Contracts\AiProviderInterface::class)->suggest(['purpose'=>$entry->purpose,'question'=>$entry->question,'provider'=>$entry->context['provider']??null,'locale'=>$entry->context['locale']??app()->getLocale(),'source'=>$source]);
    }

    public function apply(DesktopAiRequest $entry): void
    {
        abort_unless($entry->status === 'completed' && ($entry->source_record_id||$entry->product_id),422);
        DB::transaction(function () use ($entry) {
            $entry = DesktopAiRequest::whereKey($entry->id)->lockForUpdate()->firstOrFail();
            abort_unless($entry->status === 'completed',409);
            if($entry->product_id){
                Gate::authorize('shop.manage');$book=\App\Models\Product::lockForUpdate()->findOrFail($entry->product_id);
                abort_unless(hash_equals($entry->source_version,$this->bookVersion($book)),409,__('workspaces.ai_stale'));
                $proposal=$entry->proposal??[];
                $data=match($entry->purpose){'title'=>['title'=>mb_substr($proposal['title']??'',0,255)],'summary'=>['description'=>mb_substr($proposal['short_description']??'',0,300)],'seo'=>['seo_title'=>mb_substr($proposal['seo_title']??'',0,255),'seo_description'=>mb_substr($proposal['seo_description']??'',0,500)],default=>[]};
                abort_unless($data&&!in_array('',array_values($data),true),422);app(BookCatalog::class)->save($data,$book);$entry->update(['status'=>'applied']);app(Audit::class)->record('ai.suggestion_applied',(string)$entry->id);return;
            }
            $record = SourceRecord::whereKey($entry->source_record_id)->lockForUpdate()->firstOrFail();
            if ($record->metadata['public_published'] ?? false) Gate::authorize('content.publish');
            abort_unless(hash_equals($entry->source_version,app(ContentState::class)->version($record)),409,__('workspaces.ai_stale'));
            $proposal = $entry->proposal ?? [];
            $data = match ($entry->purpose) {
                'title'=>['title'=>mb_substr($proposal['title'],0,255)], 'summary'=>['short_description'=>mb_substr($proposal['short_description'],0,300)],
                'seo'=>['seo_title'=>mb_substr($proposal['seo_title'],0,255),'seo_description'=>mb_substr($proposal['seo_description'],0,500)],
                'social','youtube_description'=>!empty($entry->context['provider'])?['platform_metadata'=>[...($record->metadata['platform_metadata']??[]),$entry->context['provider']=>['title'=>$record->metadata['platform_metadata'][$entry->context['provider']]['title']??null,'body'=>mb_substr($proposal['social_text']??'',0,10000)]]]:[],
                default=>[],
            };
            abort_unless($data && !in_array('',array_values($data),true),422);
            if(isset($data['platform_metadata'])){Gate::authorize('content.publish');abort_unless(!empty($proposal['social_text']),422);}
            app(ContentAssignment::class)->record($record,$data);
            $entry->update(['status'=>'applied']);
            app(Audit::class)->record('ai.suggestion_applied',(string)$entry->id);
        });
    }
    private function bookVersion(\App\Models\Product $book): string {return hash('sha256',json_encode([$book->title,$book->description,$book->contents,$book->metadata,$book->updated_at?->toIso8601String()],JSON_THROW_ON_ERROR));}
}
