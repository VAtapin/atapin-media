<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PublicAiChatRequest extends Model
{
    protected $guarded=[];
    public function record(){return $this->belongsTo(SourceRecord::class);}
    protected function casts(): array {return ['question'=>'encrypted','answer'=>'encrypted'];}
}
