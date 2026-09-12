<?php
namespace App\Http\Middleware;
use App\Services\Settings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
class ApplyPlatformSettings
{
    public function handle(Request $request, Closure $next)
    {
        if (Schema::hasTable('settings')) {
            $settings = app(Settings::class);
            $brand = $settings->get('system_branding_name') ?: $settings->get('site_name');
            if (is_string($brand) && $brand !== '') config(['platform.brand' => $brand]);
            $locale = $settings->get('system_locale');
            if (is_string($locale) && in_array($locale, config('platform.locales'), true)) app()->setLocale($locale);
            $timezone = $settings->get('system_timezone');
            if (is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true)) {
                config(['app.timezone' => $timezone]); date_default_timezone_set($timezone);
            }
        }
        return $next($request);
    }
}
