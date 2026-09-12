<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Sale extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['amount_cents' => 'integer', 'paid_at' => 'datetime']; }
    public function product() { return $this->belongsTo(Product::class); }
}