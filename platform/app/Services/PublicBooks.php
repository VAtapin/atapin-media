<?php
namespace App\Services;

use App\Models\{Product,Media};
use Illuminate\Support\{Collection,Str};

class PublicBooks
{
    public function query(): \Illuminate\Database\Eloquent\Builder { return Product::where('status','active'); }
    public function assets(Product $book): Collection
    {
        return Media::whereNull('archived_at')->whereHas('usages',function($q)use($book){
            $q->where('subject_type',Product::class)->where('subject_id',(string)$book->id)->where(function($q)use($book){
                $q->where(fn($q)=>$q->where('used_as','cover')->whereIn('media.mime',['image/jpeg','image/png','image/webp']))
                    ->orWhere(fn($q)=>$q->where('used_as','sample')->where('media.mime','application/pdf'));
                if($book->price_cents===0)$q->orWhere(fn($q)=>$q->where('used_as','public_download')->where('media.mime','application/pdf'));
            });
        })->whereIn('mime',['image/jpeg','image/png','image/webp','application/pdf'])->get()
            ->filter(fn($media)=>app(MediaOriginalLocator::class)->find($media));
    }
    public function card(Product $book): array
    {
        $cover=$this->assets($book)->first(fn($media)=>$media->kind==='image');
        return ['id'=>$book->id,'title'=>$book->title,'excerpt'=>Str::limit($book->description??'',140),'author'=>$book->author??'','meta'=>$book->isbn??'',
            'description'=>$book->description??'',
            'price'=>$book->price_cents===null?null:number_format($book->price_cents/100,2,',','.').' '.$book->currency,
            'url'=>route('public.book',['slug'=>Str::slug($book->title).'-'.$book->id]),
            'image'=>$cover?route('public.book-media',[$book,$cover]):null,'tags'=>$book->metadata['tags']??[]];
    }
}
