<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ChannelMessagePage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['page_token' => 'string'];
    }

    public function run() { return $this->belongsTo(ChannelMessageRun::class, 'run_id'); }
}
