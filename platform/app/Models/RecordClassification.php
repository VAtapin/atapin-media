<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RecordClassification extends Model
{
    protected $guarded=[];
    protected function casts(): array {return ['proposal'=>'array','applied_changes'=>'array','confidence'=>'decimal:4'];}
}
