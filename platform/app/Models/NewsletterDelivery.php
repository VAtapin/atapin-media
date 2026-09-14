<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterDelivery extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['sent_at' => 'datetime']; }
    public function campaign() { return $this->belongsTo(NewsletterCampaign::class, 'newsletter_campaign_id'); }
    public function subscription() { return $this->belongsTo(NewsletterSubscription::class, 'newsletter_subscription_id'); }
}
