<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KaiKnowledgeEntry extends Model
{
    protected $primaryKey='key';
    public $incrementing=false;
    protected $keyType='string';
    protected $guarded=[];
    protected function casts(): array { return ['keywords'=>'array','active'=>'boolean']; }
}
