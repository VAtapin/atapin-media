<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class NewsletterSubscription extends Model {
    protected $guarded=[];
    protected $hidden=['token_hash'];
    protected function casts(): array{return ['consented_at'=>'datetime','confirmed_at'=>'datetime','tags'=>'array','import_provenance'=>'array'];}
}
