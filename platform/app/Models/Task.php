<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Task extends Model
{
    public const STATES=['open','working','waiting','done'];
    public const PRIORITIES=['low','normal','high','urgent'];
    protected $guarded=[];
    protected function casts(): array { return ['due_date'=>'date:Y-m-d','checklist'=>'array','tags'=>'array']; }
    public function record() { return $this->belongsTo(SourceRecord::class,'source_record_id'); }
    public function project() { return $this->belongsTo(Project::class); }
    public function assignee() { return $this->belongsTo(User::class,'assigned_to'); }
}
