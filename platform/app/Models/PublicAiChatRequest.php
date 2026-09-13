<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PublicAiChatRequest extends Model
{
    protected $guarded=[];
    protected function casts(): array {return ['question'=>'encrypted','answer'=>'encrypted'];}
}
