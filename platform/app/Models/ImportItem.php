<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ImportItem extends Model {
    protected $guarded=[];
    protected function casts():array {return ['metadata'=>'array'];}
}
