<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Publication extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'last_attempt_at' => 'datetime',
            'next_attempt_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function record()
    {
        return $this->belongsTo(SourceRecord::class, 'source_record_id')->withTrashed();
    }
}
