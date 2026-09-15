<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DesktopAiRequest extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['proposal' => 'array','context'=>'array','input_tokens'=>'integer','output_tokens'=>'integer','total_tokens'=>'integer','estimated_cost_micros'=>'integer']; }
    public function proposalVersion(): string { return hash('sha256',json_encode([$this->status,$this->proposal],JSON_THROW_ON_ERROR)); }
    public function product() { return $this->belongsTo(Product::class); }
    public function record() { return $this->belongsTo(SourceRecord::class, 'source_record_id'); }
}
