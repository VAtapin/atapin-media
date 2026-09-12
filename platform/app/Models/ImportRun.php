<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ImportRun extends Model
{
    use HasUuids;

    protected $guarded=[];

    protected function casts():array
    {
        return [
            'notes' => 'array',
            'source_options' => 'array',
            'progress' => 'array',
            'discovered' => 'integer',
            'imported' => 'integer',
            'skipped' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
