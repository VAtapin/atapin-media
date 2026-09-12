<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SourceRecord extends Model
{
    public const KINDS=['video','short','post','poll','comment','live_chat','channel','subscription','history','shopping_collection','archive_setting'];
    use \Illuminate\Database\Eloquent\SoftDeletes;
    protected $guarded=[];
    protected function casts():array{return ['metadata'=>'array'];}
    public function classifications(){return $this->hasMany(RecordClassification::class);}
}
