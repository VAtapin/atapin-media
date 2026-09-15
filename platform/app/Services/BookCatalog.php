<?php
namespace App\Services;

use App\Models\{Product,Media};
use Illuminate\Support\Facades\DB;

class BookCatalog
{
    public static function rules(): array
    {
        return ['title'=>'required|string|max:255','description'=>'nullable|string|max:10000','price_cents'=>'nullable|integer|min:0|max:10000000',
            'currency'=>'required|in:EUR,USD,CHF,GBP','status'=>'required|in:draft,active,archived','author'=>'nullable|string|max:255',
            'contents'=>'nullable|string|max:20000','edition_text'=>'nullable|string|max:200000','isbn'=>'nullable|string|max:32','language'=>'nullable|string|max:8','page_count'=>'nullable|integer|min:1|max:100000',
            'project_id'=>'nullable|integer|exists:projects,id','taxonomy_term_ids'=>'sometimes|array|max:30','taxonomy_term_ids.*'=>'integer|distinct|exists:taxonomy_terms,id',
            'external_shop_url'=>'nullable|url:http,https|max:1000','subtitle'=>'nullable|string|max:255','seo_title'=>'nullable|string|max:255','seo_description'=>'nullable|string|max:500','publication_date'=>'nullable|date_format:Y-m-d','tags'=>'nullable|array|max:30','tags.*'=>'string|max:100'];
    }

    public function save(array $data, ?Product $product=null): Product
    {
        return DB::transaction(function () use ($data,$product) {
            $product = $product ? Product::lockForUpdate()->findOrFail($product->id) : new Product;
            $terms = $data['taxonomy_term_ids'] ?? null; unset($data['taxonomy_term_ids']);
            foreach(['subtitle','seo_title','seo_description','publication_date','tags'] as $key)if(array_key_exists($key,$data)){$product->metadata=[...($product->metadata??[]),$key=>$data[$key]];unset($data[$key]);}
            if(array_key_exists('edition_text',$data)){$product->metadata=[...($product->metadata??[]),'edition_text'=>$data['edition_text']];unset($data['edition_text']);}
            if(array_key_exists('external_shop_url',$data)) { $product->metadata=[...($product->metadata??[]),'external_shop_url'=>$data['external_shop_url']]; unset($data['external_shop_url']); }
            $product->fill($data);
            if($product->exists&&$product->price_cents>0){
                $full=DB::table('media_usages')->where('subject_type',Product::class)->where('subject_id',(string)$product->id)->whereIn('used_as',['public_download','paid_download'])->pluck('media_id');
                abort_if(Media::whereIn('id',$full)->where('disk',CanonicalMediaStorage::DISK)->exists()||DB::table('media_usages')->where('subject_type',Product::class)->where('subject_id',(string)$product->id)->where('used_as','sample')->whereIn('media_id',$full)->exists(),422,__('workspaces.pdf_separate_sample'));
            }
            $product->save();
            if($terms!==null) app(Taxonomy::class)->sync($product,$terms);
            // A price change must never leave a complete paid PDF exposed as a free download.
            DB::table('media_usages')->where('subject_type',Product::class)->where('subject_id',(string)$product->id)
                ->whereIn('used_as',['public_download','paid_download'])->update(['used_as'=>$product->price_cents>0?'paid_download':'public_download']);
            app(Audit::class)->record('shop.product_saved',(string)$product->id);
            return $product->refresh();
        });
    }

    public function attach(Product $product, Media $media, string $slot): void
    {
        abort_unless(in_array($slot,['cover','sample','full'],true),422);
        abort_if($media->archived_at,422);
        abort_unless($slot==='cover'?in_array($media->mime,['image/jpeg','image/png','image/webp','image/gif'],true):$media->mime==='application/pdf',422);
        // Complete editions remain private even when the book is temporarily free.
        abort_if($slot==='full'&&$media->disk===CanonicalMediaStorage::DISK,422);
        abort_unless(app(MediaOriginalLocator::class)->find($media),422);
        DB::transaction(function()use($product,$media,$slot){
            $product=Product::lockForUpdate()->findOrFail($product->id);
            if($slot!=='cover'&&$product->price_cents>0)abort_if(DB::table('media_usages')->where('subject_type',Product::class)->where('subject_id',(string)$product->id)->where('media_id',$media->id)->whereIn('used_as',$slot==='full'?['sample']:['paid_download','public_download'])->exists(),422,__('workspaces.pdf_separate_sample'));
            $roles=$slot==='full'?['public_download','paid_download']:[$slot];
            DB::table('media_usages')->where('subject_type',Product::class)->where('subject_id',(string)$product->id)->whereIn('used_as',$roles)->delete();
            $media->usages()->firstOrCreate(['subject_type'=>Product::class,'subject_id'=>(string)$product->id,'used_as'=>$slot==='full'?($product->price_cents>0?'paid_download':'public_download'):$slot]);
            if ($slot === 'cover') $product->updateQuietly(['metadata'=>[...($product->metadata ?? []),'cover_media_id'=>$media->id]]);
            app(Audit::class)->record('shop.asset_attached',(string)$product->id,['slot'=>$slot,'media_id'=>$media->id]);
        });
    }
}
