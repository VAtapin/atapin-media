<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Product extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['price_cents' => 'integer', 'metadata' => 'array']; }
    public function project() { return $this->belongsTo(Project::class); }
}
