<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class Media extends Model
{
    use HasUuids;
    protected $table = 'media';
    protected $guarded = [];
    protected function casts(): array { return ['metadata' => 'array', 'bytes' => 'integer', 'captured_at' => 'datetime', 'classified_at' => 'datetime', 'archived_at' => 'datetime', 'classification_confidence' => 'decimal:4']; }
    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function assets() { return $this->hasMany(self::class, 'parent_id'); }
    public function tags() { return $this->belongsToMany(Tag::class); }
    public function collections() { return $this->belongsToMany(Collection::class)->withPivot('position')->withTimestamps(); }
    public function usages() { return $this->hasMany(MediaUsage::class); }
    public function classifications() { return $this->hasMany(MediaClassification::class); }
    public function originals() { return $this->hasMany(MediaOriginal::class); }
    public function scopeVisibleLibrary($query) { return $query->whereNull('archived_at'); }
    public function formattedSize(): string
    {
        $size = $this->bytes; $units = ['B','KB','MB','GB','TB']; $i = 0;
        while ($size >= 1024 && $i < 4) { $size /= 1024; $i++; }
        return number_format($size, $i ? 1 : 0, ',', '.').' '.$units[$i];
    }
}
