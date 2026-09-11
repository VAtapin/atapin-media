<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Task extends Model
{
    public const STATES=['open','working','waiting','done'];
    protected $guarded=[];
    protected function casts(): array { return ['due_date'=>'date']; }
    public function project() { return $this->belongsTo(Project::class); }
    public function assignee() { return $this->belongsTo(User::class,'assigned_to'); }
}
