<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\SearchProviderInterface;
use App\Services\DatabaseSearch;
use App\Services\Access;
use App\Services\Importing\ImportCenter;
use App\Services\Importing\IntakeArchiveAdapter;
use App\Services\Importing\YoutubeArchiveAdapter;
use App\Services\Importing\LocalFolderAdapter;
use App\Services\Importing\LocalArchiveAdapter;
use App\Services\Importing\ServiceLinkAdapter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SearchProviderInterface::class, DatabaseSearch::class);
        $this->app->bind(ImportCenter::class, function ($app) {
            return new ImportCenter([
                $app->make(IntakeArchiveAdapter::class),
                $app->make(YoutubeArchiveAdapter::class),
                $app->make(LocalFolderAdapter::class),
                $app->make(LocalArchiveAdapter::class),
                $app->make(\App\Services\Importing\TakeoutArchiveAdapter::class),
                $app->make(\App\Services\Importing\LocalVideoAuditAdapter::class),
                $app->make(ServiceLinkAdapter::class, ['source' => 'youtube-service']),
                $app->make(ServiceLinkAdapter::class, ['source' => 'tiktok']),
                $app->make(ServiceLinkAdapter::class, ['source' => 'instagram']),
                $app->make(ServiceLinkAdapter::class, ['source' => 'facebook-video']),
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (Access::PERMISSIONS as $permission) Gate::define($permission, fn ($user) => $user->hasPermission($permission));
        Paginator::defaultView('components.pagination');
        foreach ([\App\Models\Media::class => 'media', \App\Models\SourceRecord::class => 'record'] as $model => $type) {
            $model::created(function ($item) use ($type) {
                if ($item->source!=='youtube-takeout' && !($item->metadata['takeout']??false) && $item->status === 'unsorted' && app(\App\Services\Settings::class)->get('ai_auto_classify', true)
                    && app(\App\Services\Importing\AiContentClassifier::class)->available()) {
                    dispatch((new \App\Jobs\ClassifyImportedContent($type, (string) $item->id))->afterCommit());
                }
            });
        }
    }
}
