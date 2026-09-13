<?php

namespace App\Console\Commands;

use App\Jobs\PreparePublicVideo;
use App\Models\Media;
use App\Services\PublicContent;
use App\Services\Importing\LocalMediaLinks;
use Illuminate\Console\Command;

class PreparePublicVideos extends Command
{
    protected $signature = 'public:prepare-videos {--id=* : Prepare only the specified media IDs}';
    protected $description = 'Queue web-optimized copies of local videos without changing originals';

    public function handle(): int
    {
        $ids = $this->option('id');
        if (!$ids) {
            $ids = app(PublicContent::class)->query()->whereIn('kind',['video','short'])->get()
                ->flatMap(fn ($record) => app(LocalMediaLinks::class)->ids($record))->unique()->values()->all();
        }
        $query = Media::query()->whereIn('id',$ids)->where('kind','video');
        $count = 0;
        $query->pluck('id')->each(function ($id) use (&$count) { PreparePublicVideo::dispatch((string)$id); $count++; });
        $this->info("Queued {$count} video optimizations.");
        return self::SUCCESS;
    }
}
