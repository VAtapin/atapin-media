<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LiveBrowserSession extends Model
{
    public $incrementing=false;
    protected $keyType='string';
    protected $guarded=[];
    protected $hidden=['upstream_location'];
    protected function casts(): array { return ['expires_at'=>'datetime','upstream_location'=>'encrypted']; }
}
