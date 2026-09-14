<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterCampaign extends Model
{
    public function user() { return $this->belongsTo(User::class); }
    protected $guarded = [];
    protected function casts(): array { return ['send_at' => 'datetime']; }
    public function deliveries() { return $this->hasMany(NewsletterDelivery::class); }
}
