<?php
namespace App\Jobs;
use App\Models\{TaxonomyTerm,SourceRecord,User};
use App\Services\Taxonomy;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
class RefreshTaxonomyAssignments implements ShouldQueue
{
    use Queueable;
    public int $tries=3;
    public int $timeout=180;
    public function __construct(public int $id,public int $user){}
    public function handle(Taxonomy $taxonomy): void
    {
        $term=TaxonomyTerm::find($this->id);if(!$term)return;$user=User::find($this->user);
        abort_unless($user?->hasPermission('content.edit'),403);
        if(DB::table('taxonomy_assignments')->where('taxonomy_term_id',$term->id)->where('subject_type','record')->whereIn('subject_id',SourceRecord::where('metadata->public_published',true)->select('id'))->exists())abort_unless($user->hasPermission('content.publish'),403);
        $term->updateQuietly(['refresh_status'=>'processing']);$taxonomy->refreshAssigned($term);$term->updateQuietly(['refresh_status'=>'ready']);
    }
    public function failed(?\Throwable $error): void{TaxonomyTerm::whereKey($this->id)->update(['refresh_status'=>'failed']);}
}
