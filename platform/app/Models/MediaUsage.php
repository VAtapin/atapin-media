<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaUsage extends Model
{
    protected $guarded = [];
    public function media() { return $this->belongsTo(Media::class); }
}
