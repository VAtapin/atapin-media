<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicationSchedule extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['providers' => 'array', 'publish_at' => 'datetime']; }
    public function record() { return $this->belongsTo(SourceRecord::class, 'source_record_id'); }
}
