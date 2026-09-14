<?php

namespace App\Services;

use App\Models\{Product, SourceRecord, TaxonomyTerm};
use Illuminate\Support\Facades\DB;

class Taxonomy
{
    public function sync(SourceRecord|Product $subject, array $ids): void
    {
        $type = $subject instanceof SourceRecord ? 'record' : 'product';
        $terms = TaxonomyTerm::whereIn('id', $ids)->where('active', true)->get();
        abort_unless($terms->count() === count(array_unique($ids)), 422, __('workspaces.invalid_terms'));
        DB::transaction(function () use ($subject, $type, $terms) {
            $subject->newQuery()->whereKey($subject->id)->lockForUpdate()->firstOrFail();
            $subject->refresh();
            DB::table('taxonomy_assignments')->where('subject_type', $type)->where('subject_id', $subject->id)->whereNotIn('taxonomy_term_id',$terms->pluck('id'))->delete();
            foreach ($terms as $term) DB::table('taxonomy_assignments')->insertOrIgnore(['taxonomy_term_id'=>$term->id, 'subject_type'=>$type, 'subject_id'=>$subject->id]);
            $meta = $subject->metadata ?? [];
            $manual = array_diff($meta['tags'] ?? [], $meta['taxonomy_tags'] ?? []);
            $meta['taxonomy_tags'] = $terms->pluck('name')->all();
            $meta['taxonomy_term_ids'] = $terms->pluck('id')->all();
            $meta['tags'] = array_values(array_unique([...$manual, ...$meta['taxonomy_tags']]));
            $subject->update(['metadata'=>$meta]);
        });
    }

    public function refreshAssigned(TaxonomyTerm $term): void
    {
        DB::table('taxonomy_assignments')->where('taxonomy_term_id', $term->id)->orderBy('id')->chunkById(100, function ($links) {
            foreach ($links as $link) {
                $subject = $link->subject_type === 'record' ? SourceRecord::find($link->subject_id) : Product::find($link->subject_id);
                if ($subject) $this->sync($subject, TaxonomyTerm::whereIn('id',DB::table('taxonomy_assignments')->where('subject_type',$link->subject_type)->where('subject_id',$link->subject_id)->select('taxonomy_term_id'))->where('active',true)->pluck('id')->all());
            }
        });
    }
}
