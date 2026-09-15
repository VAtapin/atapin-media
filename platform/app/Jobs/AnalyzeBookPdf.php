<?php

namespace App\Jobs;

use App\Models\{Media, Product, User};
use App\Services\{BookCatalog, BookPdfAnalyzer};
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class AnalyzeBookPdf implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 180;

    public function __construct(public int $productId, public string $mediaId, public int $userId) {}

    public function handle(BookPdfAnalyzer $analyzer, BookCatalog $catalog): void
    {
        $product = Product::find($this->productId);
        if (! $product || ! User::find($this->userId)?->hasPermission('shop.manage')) return;
        $this->state($product, 'processing');
        try {
            $text = $analyzer->extract(Media::findOrFail($this->mediaId));
            $result = [...$analyzer->analyze($text), '_source_text_chars'=>strlen($text)];
            DB::transaction(function () use ($catalog, $result) {
                $product = Product::lockForUpdate()->findOrFail($this->productId);
                $metadata = $product->metadata ?? [];
                $ai = $metadata['book_pdf_ai'] ?? [];
                $seed = (string) ($ai['seed_title'] ?? '');
                $data = [];
                foreach (['title','description','author','contents','isbn','language','page_count'] as $key) {
                    $value = $result[$key] ?? null;
                    if ($value !== null && $value !== '' && (empty($product->{$key}) || ($key === 'title' && $product->title === $seed))) {
                        $data[$key] = $key === 'page_count' ? max(1, (int) $value) : mb_substr(trim((string) $value), 0, match ($key) {
                            'title','author' => 255, 'isbn' => 32, 'language' => 8, 'description' => 10000, 'contents' => 20000, default => 255,
                        });
                    }
                }
                foreach (['subtitle','seo_title','seo_description','publication_date','tags'] as $key) {
                    $value = $result[$key] ?? null;
                    if ($value !== null && $value !== '' && empty($metadata[$key])) {
                        if ($key === 'tags') $value = array_values(array_filter(array_map(fn($tag)=>mb_substr(trim((string) $tag),0,100), is_array($value) ? $value : [])));
                        else $value = mb_substr(trim((string) $value), 0, match ($key) {
                            'seo_description' => 500, default => 255,
                        });
                        if ($key !== 'publication_date' || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) $data[$key] = $value;
                    }
                }
                if ($data) $product = $catalog->save([...$data, 'currency' => $product->currency ?: 'EUR', 'price_cents' => $product->price_cents ?? 0, 'status' => $product->status ?: 'draft'], $product);
                $product->updateQuietly(['metadata' => [...($product->metadata ?? []), 'book_pdf_ai' => ['status'=>'completed','seed_title'=>$seed,'fields'=>array_keys($data),'source_text_chars'=>(int)($result['_source_text_chars'] ?? 0),'completed_at'=>now()->toIso8601String()]]);
            });
        } catch (\Throwable) {
            $this->state(Product::find($this->productId), 'failed');
        }
    }

    public function failed(?\Throwable $error): void
    {
        $this->state(Product::find($this->productId), 'failed');
    }

    private function state(?Product $product, string $status): void
    {
        if ($product) $product->updateQuietly(['metadata' => [...($product->metadata ?? []), 'book_pdf_ai' => [...($product->metadata['book_pdf_ai'] ?? [], 'status'=>$status, 'updated_at'=>now()->toIso8601String()]]]);
    }
}
