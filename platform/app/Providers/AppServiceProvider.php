<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\SearchProviderInterface;
use App\Services\DatabaseSearch;
use App\Services\Access;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (Access::PERMISSIONS as $permission) Gate::define($permission, fn ($user) => $user->hasPermission($permission));
        Paginator::defaultView('components.pagination');
    }
}
