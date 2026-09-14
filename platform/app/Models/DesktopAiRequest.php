<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DesktopAiRequest extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['proposal' => 'array']; }
    public function record() { return $this->belongsTo(SourceRecord::class, 'source_record_id'); }
}
