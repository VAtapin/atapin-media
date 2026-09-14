<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxonomyTerm extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['active' => 'boolean']; }
    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
}
