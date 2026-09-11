<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Project extends Model
{
    public const STATES=['idea','script','production','review','published'];
    protected $guarded=[];
    protected function casts(): array { return ['due_date'=>'date']; }
    public function tasks() { return $this->hasMany(Task::class); }
    public function owner() { return $this->belongsTo(User::class,'user_id'); }
}
