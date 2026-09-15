<?php

namespace App\Http\Controllers;

use App\Models\TaxonomyTerm;
use App\Services\{Audit, Taxonomy};
use App\Services\MediaLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\{Str, Facades\DB};
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;

class TaxonomyController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['q'=>'nullable|string|max:120','kind'=>'nullable|in:topic,category','page'=>'nullable|integer|min:1']);
        $query = TaxonomyTerm::with('parent:id,name')->orderBy('kind')->orderBy('name');
        if ($data['q'] ?? '') $query->where('name','like','%'.$data['q'].'%');
        if ($data['kind'] ?? '') $query->where('kind',$data['kind']);
        $page = $query->paginate(50);
        $covers = \App\Models\Media::whereIn('id', $page->getCollection()->pluck('cover_media_id')->filter())->get()->keyBy('id');
        return response()->json($page->through(fn (TaxonomyTerm $term) => [...$term->toArray(), 'cover_url' => $covers->get($term->cover_media_id)?->previewUrl()]));
    }

    public function store(Request $request) { return $this->save($request, new TaxonomyTerm); }
    public function update(Request $request, TaxonomyTerm $term) { return $this->save($request, $term); }

    public function destroy(Request $request, TaxonomyTerm $term, Taxonomy $taxonomy, Audit $audit)
    {
        $request->validate(['confirmation'=>'required|in:DELETE']);
        $links = DB::table('taxonomy_assignments')->where('taxonomy_term_id', $term->id)->get(['subject_type', 'subject_id']);
        DB::transaction(function () use ($term, $links, $taxonomy, $audit) {
            $id = (string) $term->id;
            $term->delete();
            foreach ($links->groupBy(fn ($link) => $link->subject_type.'|'.$link->subject_id) as $group) {
                $link = $group->first();
                $subject = $link->subject_type === 'record'
                    ? \App\Models\SourceRecord::find($link->subject_id)
                    : ($link->subject_type === 'product' ? \App\Models\Product::find($link->subject_id) : null);
                if ($subject) {
                    $ids = DB::table('taxonomy_assignments')->where('subject_type', $link->subject_type)->where('subject_id', $link->subject_id)->pluck('taxonomy_term_id')->all();
                    $taxonomy->sync($subject, $ids);
                }
            }
            $audit->record('taxonomy.deleted', $id);
        });
        return response()->json(['status'=>'deleted']);
    }

    public function cover(Request $request, TaxonomyTerm $term, MediaLibrary $library)
    {
        Gate::authorize('media.upload');
        $request->validate(['file'=>'required|image|mimes:jpg,jpeg,png,webp,gif|max:10240']);
        $media = $library->upload($request->file('file'), $request->user()->id);
        $term->update(['cover_media_id'=>$media->id]);
        return response()->json(['status'=>'saved','media_id'=>$media->id,'cover_url'=>$media->previewUrl()]);
    }

    private function save(Request $request, TaxonomyTerm $term)
    {
        $data = $request->validate(['name'=>'required|string|max:100','kind'=>'required|in:topic,category',
            'slug'=>['nullable','string','max:180',Rule::unique('taxonomy_terms')->ignore($term->id)],
            'description'=>'nullable|string|max:10000','parent_id'=>'nullable|integer|exists:taxonomy_terms,id','active'=>'required|boolean']);
        $parent = $data['parent_id'] ?? null;
        $seen = [];
        while ($parent) {
            abort_if($parent == $term->id || in_array($parent,$seen,true),422,__('workspaces.taxonomy_cycle'));
            $seen[] = $parent;
            $parent = TaxonomyTerm::findOrFail($parent)->parent_id;
        }
        if ($term->exists && DB::table('taxonomy_assignments')->where('taxonomy_term_id',$term->id)->where('subject_type','record')->whereIn('subject_id',\App\Models\SourceRecord::where('metadata->public_published',true)->select('id'))->exists()) \Illuminate\Support\Facades\Gate::authorize('content.publish');
        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['name']).'-'.substr(hash('sha256',$data['kind'].'|'.$data['name']),0,12);
        \Illuminate\Support\Facades\Validator::make($data,['slug'=>[Rule::unique('taxonomy_terms')->ignore($term->id)]])->validate();
        DB::transaction(function () use ($term,$data,$request) {
            $term->fill($data)->save();
            if(DB::table('taxonomy_assignments')->where('taxonomy_term_id',$term->id)->count()>100){$term->updateQuietly(['refresh_status'=>'queued']);\App\Jobs\RefreshTaxonomyAssignments::dispatch($term->id,$request->user()->id)->afterCommit();}
            else { app(Taxonomy::class)->refreshAssigned($term);$term->updateQuietly(['refresh_status'=>'ready']); }
            app(Audit::class)->record('taxonomy.saved',(string)$term->id);
        });
        return response()->json(['status'=>'saved','id'=>$term->id]);
    }
}
