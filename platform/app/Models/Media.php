<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class Media extends Model
{
    use HasUuids;
    protected $table = 'media';
    protected $guarded = [];
    protected function casts(): array { return ['metadata' => 'array', 'bytes' => 'integer']; }
    public function formattedSize(): string
    {
        $size = $this->bytes; $units = ['B','KB','MB','GB','TB']; $i = 0;
        while ($size >= 1024 && $i < 4) { $size /= 1024; $i++; }
        return number_format($size, $i ? 1 : 0, ',', '.').' '.$units[$i];
    }
}
