<?php
namespace App\Http\Controllers;
use App\Models\Media;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use App\Services\Settings;
class DesktopController extends Controller
{
    public function __invoke(Settings $settings, SettingsController $settingsController)
    {
        $canMedia = Gate::allows('media.view');
        $canManageSettings = Gate::allows('settings.manage');
        return view('desktop', [
            'project'=>Gate::allows('projects.manage') ? \App\Models\Project::where('status','!=','published')->latest('updated_at')->first() : null,
            'media' => $canMedia ? Media::latest()->limit(6)->get() : collect(),
            'count' => $canMedia ? Media::count() : null,
            'bytes' => $canMedia ? Media::sum('bytes') : null,
            'queued' => $canManageSettings ? DB::table('jobs')->count() : null,
            'failed' => $canManageSettings ? DB::table('failed_jobs')->count() : null,
            'canManageSettings' => $canManageSettings,
            'settingsPageData' => $canManageSettings ? $settingsController->pageData($settings) : null,
            'desktopAppearance' => [
                'icon_set' => $settings->get('desktop_icon_set', 'manna'),
                'wallpaper' => $settings->get('desktop_wallpaper', 'mountains'),
                'accent' => $settings->get('desktop_accent', 'gold'),
                'custom_wallpaper' => $settings->get('desktop_custom_wallpaper'),
                'density' => $settings->get('desktop_density', 'comfortable'),
                'effects' => $settings->get('desktop_effects', true),
            ],
        ]);
    }
}
