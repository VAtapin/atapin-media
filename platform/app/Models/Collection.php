<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Collection extends Model
{
    protected $guarded=[];
    protected function casts():array{return ['metadata'=>'array'];}
    public function items(){return $this->hasMany(CollectionItem::class)->orderBy('position');}
}
