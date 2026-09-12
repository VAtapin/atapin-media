<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UserProfile extends Model
{
    protected $fillable = ['avatar_path', 'phone', 'location', 'website', 'bio', 'social_links'];
    protected function casts(): array { return ['social_links' => 'array']; }
    public function user() { return $this->belongsTo(User::class); }
}
