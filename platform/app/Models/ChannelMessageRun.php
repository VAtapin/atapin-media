<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ChannelMessageRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['pending_pages' => 'integer', 'pages' => 'integer', 'imported' => 'integer'];
    }

    public function pages() { return $this->hasMany(ChannelMessagePage::class, 'run_id'); }
}
