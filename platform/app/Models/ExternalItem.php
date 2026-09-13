<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'remote_published_at' => 'datetime',
            'imported_at' => 'datetime',
        ];
    }

    public function record()
    {
        return $this->belongsTo(SourceRecord::class, 'source_record_id');
    }
}
