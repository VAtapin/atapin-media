<?php
namespace App\Http\Controllers;
use App\Models\Media;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use App\Services\Settings;
use App\Services\PublicCommunityModeration;
use App\Services\PublicVideoHealth;
class DesktopController extends Controller
{
    public function __invoke(Settings $settings, SettingsController $settingsController)
    {
        $canMedia = Gate::allows('media.view');
        $canManageSettings = Gate::allows('settings.manage');
        $canModerateCommunity = Gate::allows('community.moderate');
        $communityModeration = app(PublicCommunityModeration::class);
        return view('desktop', [
            'project'=>Gate::allows('projects.manage') ? \App\Models\Project::where('status','!=','published')->latest('updated_at')->first() : null,
            'media' => $canMedia ? Media::latest()->limit(6)->get() : collect(),
            'count' => $canMedia ? Media::count() : null,
            'bytes' => $canMedia ? Media::sum('bytes') : null,
            'queued' => $canManageSettings ? DB::table('jobs')->count() : null,
            'failed' => $canManageSettings ? DB::table('failed_jobs')->count() : null,
            'videoHealth' => $canManageSettings ? app(PublicVideoHealth::class)->report() : null,
            'canManageSettings' => $canManageSettings,
            'canModerateCommunity' => $canModerateCommunity,
            'communityEntries' => $canModerateCommunity ? $communityModeration->pending()->latest('id')->limit(50)->get() : collect(),
            'communityStats' => $canModerateCommunity ? $communityModeration->stats() : ['human_review' => 0, 'ai_pending' => 0],
            'settingsPageData' => $settingsController->pageData($settings),
            'desktopAppearance' => [
                'icon_set' => $settings->get('desktop_icon_set', 'manna'),
                'wallpaper' => $settings->get('desktop_wallpaper', 'mountains'),
                'accent' => $settings->get('desktop_accent', 'gold'),
                'custom_wallpaper' => $settings->get('desktop_custom_wallpaper'),
                'density' => $settings->get('desktop_density', 'comfortable'),
                'shortcut_layout' => $settings->get('desktop_shortcut_layout', 'free'),
                'effects' => $settings->get('desktop_effects', true),
            ],
        ]);
    }
}
