<?php

namespace App\Http\Controllers;

use App\Models\{Product, SourceRecord, TaxonomyTerm};
use App\Services\{Audit, Taxonomy};
use App\Services\MediaLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\{Str, Facades\DB};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    public function assignments(Request $request, TaxonomyTerm $term)
    {
        $data = $request->validate([
            'scope'=>'required|in:posts,videos,books', 'q'=>'nullable|string|max:120',
            'assigned'=>'nullable|boolean', 'page'=>'nullable|integer|min:1',
        ]);
        $this->authorizeAssignmentScope($data['scope']);
        $query = $this->assignmentScope($data['scope']);
        if ($data['q'] ?? '') $query->where('title', 'like', '%'.$data['q'].'%');
        $subjectType = $data['scope'] === 'books' ? 'product' : 'record';
        $assignedIds = DB::table('taxonomy_assignments')->where('taxonomy_term_id', $term->id)
            ->where('subject_type', $subjectType)->select('subject_id');
        if (array_key_exists('assigned', $data)) {
            ($data['assigned'] ? $query->whereIn('id', $assignedIds) : $query->whereNotIn('id', $assignedIds));
        }
        $page = $query->orderBy('title')->paginate(40);
        $selected = DB::table('taxonomy_assignments')->where('taxonomy_term_id', $term->id)
            ->where('subject_type', $subjectType)->whereIn('subject_id', $page->getCollection()->pluck('id'))
            ->pluck('subject_id')->map(fn ($id) => (string) $id)->flip();
        $counts = $this->assignmentCounts($term);
        return response()->json([
            ...$page->toArray(), 'data'=>$page->getCollection()->map(fn ($subject) => [
                'id'=>$subject->id, 'subject_type'=>$subjectType, 'title'=>$subject->title,
                'status'=>$subject->status, 'assigned'=>$selected->has((string) $subject->id),
            ])->values(), 'counts'=>$counts,
        ]);
    }

    public function updateAssignments(Request $request, TaxonomyTerm $term, Taxonomy $taxonomy, Audit $audit)
    {
        $data = $request->validate([
            'scope'=>'required|in:posts,videos,books', 'subject_type'=>'required|in:record,product',
            'operation'=>'required|in:add,remove,replace', 'subject_ids'=>'present|array|max:100',
            'subject_ids.*'=>'integer|distinct|min:1',
        ]);
        $this->authorizeAssignmentScope($data['scope']);
        $expectedType = $data['scope'] === 'books' ? 'product' : 'record';
        if ($data['subject_type'] !== $expectedType) throw ValidationException::withMessages([
            'subject_type'=>__('ui.taxonomy_assignment_type_mismatch'),
        ]);
        $ids = array_values(array_unique(array_map('intval', $data['subject_ids'])));
        if ($data['operation'] !== 'replace' && $ids === []) throw ValidationException::withMessages([
            'subject_ids'=>__('ui.taxonomy_assignment_selection_required'),
        ]);
        $subjects = $this->assignmentScope($data['scope'])->whereKey($ids)->get()->keyBy('id');
        if ($subjects->count() !== count($ids)) throw ValidationException::withMessages([
            'subject_ids'=>__('ui.taxonomy_assignment_invalid_subject'),
        ]);
        if ($data['operation'] === 'replace') {
            $current = $this->assignedScope($term, $data['scope'])->get()->keyBy('id');
            if ($current->keys()->merge($ids)->unique()->count() > 100) throw ValidationException::withMessages([
                'subject_ids'=>__('ui.taxonomy_assignment_batch_limit'),
            ]);
            $subjects = $current->union($subjects);
        }
        if ($data['subject_type'] === 'record' && $subjects->contains(fn (SourceRecord $record) => (bool) ($record->metadata['public_published'] ?? false))) {
            Gate::authorize('content.publish');
        }
        DB::transaction(function () use ($subjects, $ids, $data, $term, $taxonomy) {
            $selected = array_flip($ids);
            foreach ($subjects as $subject) {
                $termIds = DB::table('taxonomy_assignments')->where('subject_type', $data['subject_type'])
                    ->where('subject_id', $subject->id)->pluck('taxonomy_term_id')->map(fn ($id) => (int) $id)->all();
                $hasTerm = in_array($term->id, $termIds, true);
                $attach = $data['operation'] === 'add'
                    || ($data['operation'] === 'replace' && isset($selected[$subject->id]));
                if ($data['operation'] === 'remove') $attach = false;
                if ($attach && !$hasTerm) $termIds[] = $term->id;
                if (!$attach && $hasTerm) $termIds = array_values(array_diff($termIds, [$term->id]));
                if ($attach !== $hasTerm) $taxonomy->sync($subject, $termIds);
            }
        });
        $audit->record('taxonomy.assignments_updated', (string) $term->id, [
            'scope'=>$data['scope'], 'operation'=>$data['operation'], 'count'=>count($ids),
        ]);
        return response()->json(['status'=>'saved', 'counts'=>$this->assignmentCounts($term)]);
    }

    public function destroy(Request $request, TaxonomyTerm $term, Taxonomy $taxonomy, Audit $audit)
    {
        $request->validate(['confirmation'=>'required|in:DELETE']);
        if (DB::table('taxonomy_assignments')->where('taxonomy_term_id', $term->id)->where('subject_type', 'record')
            ->whereIn('subject_id', SourceRecord::where('metadata->public_published', true)->select('id'))->exists()) {
            Gate::authorize('content.publish');
        }
        if (DB::table('taxonomy_assignments')->where('taxonomy_term_id', $term->id)->where('subject_type', 'product')->exists()) {
            Gate::authorize('shop.manage');
        }
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
        $data=$request->validate(['media_id'=>'required_without:file|nullable|uuid|exists:media,id','file'=>'required_without:media_id|nullable|image|mimes:jpg,jpeg,png,webp,gif|max:10240']);
        if($request->hasFile('file')) { Gate::authorize('media.upload'); $media=$library->upload($request->file('file'),$request->user()->id); }
        else $media=\App\Models\Media::visibleLibrary()->findOrFail($data['media_id']);
        abort_unless($media->kind==='image',422);
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
        if ($term->exists && DB::table('taxonomy_assignments')->where('taxonomy_term_id',$term->id)->where('subject_type','record')->whereIn('subject_id',\App\Models\SourceRecord::where('metadata->public_published',true)->select('id'))->exists()) Gate::authorize('content.publish');
        if ($term->exists && DB::table('taxonomy_assignments')->where('taxonomy_term_id',$term->id)->where('subject_type','product')->exists()) Gate::authorize('shop.manage');
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

    private function assignmentScope(string $scope): Builder
    {
        if ($scope === 'books') return Product::query()->select(['id', 'title', 'status']);
        $query = SourceRecord::query()->select(['id', 'title', 'status', 'metadata'])->where('source', '!=', 'catalog-reset');
        if ($scope === 'posts') return $query->where('kind', 'post')
            ->where(fn ($records) => $records->whereNull('metadata->public_section')->orWhere('metadata->public_section', 'beitraege'))
            ->where(fn ($records) => $records->whereNull('metadata->library_only')->orWhere('metadata->library_only', false))
            ->where(fn ($records) => $records->whereNull('metadata->archive_data')->orWhere('metadata->archive_data', false));
        return $query->whereIn('kind', ['video', 'short'])
            ->where(fn ($records) => $records->whereNull('metadata->public_section')->orWhere('metadata->public_section', 'videos'))
            ->where(fn ($records) => $records->whereNull('metadata->library_only')->orWhere('metadata->library_only', false))
            ->where(fn ($records) => $records->whereNull('metadata->archive_data')->orWhere('metadata->archive_data', false));
    }

    private function assignedScope(TaxonomyTerm $term, string $scope): Builder
    {
        $type = $scope === 'books' ? 'product' : 'record';
        return $this->assignmentScope($scope)->whereIn('id', DB::table('taxonomy_assignments')
            ->where('taxonomy_term_id', $term->id)->where('subject_type', $type)->select('subject_id'));
    }

    private function authorizeAssignmentScope(string $scope): void
    {
        Gate::authorize($scope === 'books' ? 'shop.manage' : 'content.edit');
    }

    private function assignmentCounts(TaxonomyTerm $term): array
    {
        return [
            'posts'=>Gate::allows('content.edit') ? $this->assignedScope($term, 'posts')->count() : null,
            'videos'=>Gate::allows('content.edit') ? $this->assignedScope($term, 'videos')->count() : null,
            'books'=>Gate::allows('shop.manage') ? $this->assignedScope($term, 'books')->count() : null,
        ];
    }
}
