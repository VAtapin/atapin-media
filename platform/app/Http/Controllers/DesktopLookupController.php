<?php

namespace App\Http\Controllers;

use App\Models\{Collection, Media, Project, SourceRecord, TaxonomyTerm, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DesktopLookupController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate(['kind'=>'required|in:projects,users,terms,categories,series,records,books,media', 'q'=>'nullable|string|max:120']);
        $kind = $data['kind'];
        Gate::authorize(match ($kind) { 'books'=>'shop.manage','projects','users'=>'projects.manage', 'terms','categories','series','records'=>'content.edit', 'media'=>'media.view' });
        $query = match ($kind) {
            'books'=>\App\Models\Product::query(),'projects'=>Project::query(), 'users'=>User::query(), 'terms'=>TaxonomyTerm::where('active',true),
            'categories'=>TaxonomyTerm::where('active',true)->where('kind','category'),
            'series'=>Collection::where('metadata->workspace_series',true), 'records'=>SourceRecord::whereIn('kind',['video','short','post']),
            'media'=>Media::visibleLibrary(),
        };
        $column = in_array($kind, ['users','terms','categories'], true) ? 'name' : 'title';
        if ($data['q'] ?? '') $query->where($column,'like','%'.$data['q'].'%');
        $columns = match ($kind) {
            'media'=>['id','title','kind','mime','disk','path'], 'terms'=>['id','name','kind','parent_id'],
            'series'=>['id','title','metadata'], 'records'=>['id','title','kind','status'], default=>['id',$column],
        };
        return response()->json(['data'=>$query->orderBy($column)->limit(100)->get($columns)->map(function ($row) use ($kind) {
            $item = ['id'=>$row->id,'title'=>$row->title ?? $row->name];
            if ($kind === 'media') $item += ['kind'=>$row->kind,'mime'=>$row->mime,'preview_url'=>$row->previewUrl()];
            if ($kind === 'terms') $item += ['kind'=>$row->kind,'parent_id'=>$row->parent_id];
            if ($kind === 'records') $item += ['kind'=>$row->kind,'status'=>$row->status];
            return $item;
        })]);
    }
}
