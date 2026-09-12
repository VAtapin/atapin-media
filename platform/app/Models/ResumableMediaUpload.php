<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ResumableMediaUpload extends Model
{
    use HasUuids;

    public const STATUS_UPLOADING = 'uploading';
    public const STATUS_COMPLETE = 'complete';
    public const STATUS_FINALIZING = 'finalizing';
    public const STATUS_FAILED = 'failed';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'bytes' => 'integer',
            'offset' => 'integer',
        ];
    }

    public static function statusValues(): array
    {
        return [
            self::STATUS_UPLOADING,
            self::STATUS_COMPLETE,
            self::STATUS_FINALIZING,
            self::STATUS_FAILED,
        ];
    }
}
