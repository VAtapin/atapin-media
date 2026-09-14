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
        $this->app->singleton(\App\Services\Importing\ImportWorkBudget::class);
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
        Gate::define('public.participate',fn($user)=>$user->email_verified_at!==null||$user->roles()->exists());
        \Illuminate\Support\Facades\RateLimiter::for('broadcast-auth',fn($request)=>\Illuminate\Cache\RateLimiting\Limit::perMinute($request->input('action')==='read'?12000:30)->by(hash('sha256',json_encode([$request->input('path'),$request->input('action')]))));
        foreach (Access::PERMISSIONS as $permission) Gate::define($permission, fn ($user) => $user->hasPermission($permission));
        Paginator::defaultView('components.pagination');
        \App\Models\SourceRecord::saved(fn($record)=>app(\App\Services\ContentMediaPreparation::class)->queueMissing($record));
        foreach ([\App\Models\Media::class => 'media', \App\Models\SourceRecord::class => 'record'] as $model => $type) {
            $model::created(function ($item) use ($type) {
                if ($item->source!=='youtube-takeout' && !($item->metadata['takeout']??false) && !($item->metadata['archive_data']??false) && $item->status === 'unsorted' && app(\App\Services\Settings::class)->get('ai_auto_classify', true)
                    && app(\App\Services\Importing\AiContentClassifier::class)->available()) {
                    dispatch((new \App\Jobs\ClassifyImportedContent($type, (string) $item->id))->afterCommit());
                }
            });
        }

        // A website publication is the source of truth for outbound automation.
        // Queueing here also covers imports, Live Studio and future API writers.
        \App\Models\SourceRecord::created(function (\App\Models\SourceRecord $record) {
            if ((($record->metadata ?? [])['public_published'] ?? false) && $record->status === 'ready') {
                app(\App\Services\Publishing\PublishingService::class)->queueForRecord(
                    $record,
                    ($record->metadata ?? [])['publishing_targets'] ?? null
                );
            }
        });
        \App\Models\SourceRecord::updated(function (\App\Models\SourceRecord $record) {
            $before = $record->getRawOriginal('metadata');
            $before = is_string($before) ? json_decode($before, true) : $before;
            if (($before['public_published'] ?? false) && $record->wasChanged(['title', 'body', 'metadata', 'status'])) {
                $publishing = app(\App\Services\Publishing\PublishingService::class);
                $previous = (new \App\Models\SourceRecord)->setRawAttributes($record->getRawOriginal());
                if ($publishing->signature($previous) !== $publishing->signature($record)) $publishing->queueChanges($record);
            }
            if (! $record->wasChanged('metadata') || ! (($record->metadata ?? [])['public_published'] ?? false)) return;
            $before = $record->getOriginal('metadata');
            if (is_string($before)) $before = json_decode($before, true) ?: [];
            if (is_array($before) && ($before['public_published'] ?? false)) return;
            app(\App\Services\Publishing\PublishingService::class)->queueForRecord(
                $record->fresh(),
                ($record->metadata ?? [])['publishing_targets'] ?? null
            );
        });
        \App\Models\SourceRecord::deleted(fn ($record) => app(\App\Services\Publishing\PublishingService::class)->queueChanges($record));
        \App\Models\SourceRecord::restored(fn ($record) => app(\App\Services\Publishing\PublishingService::class)->queueChanges($record));
    }
}
