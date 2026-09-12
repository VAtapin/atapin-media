<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaClassification extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['proposal' => 'array', 'applied_changes' => 'array', 'confidence' => 'decimal:4']; }
    public function media() { return $this->belongsTo(Media::class); }
}
