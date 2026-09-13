<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PublicPushSubscription extends Model
{
    protected $guarded=[];
    protected $hidden=['subscription','endpoint_hash'];
    protected function casts(): array {return ['subscription'=>'encrypted:array','sent_for'=>'integer','failed_for'=>'integer'];}
}
