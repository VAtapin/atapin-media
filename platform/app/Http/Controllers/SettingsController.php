<?php
namespace App\Http\Controllers;
use App\Services\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
class SettingsController extends Controller
{
    public function edit(Settings $settings) { return view('settings', ['settings' => $settings->all()]); }
    public function update(Request $request, Settings $settings)
    {
        $wallpapers = [...array_keys(config('desktop.wallpapers')), 'custom'];
        $values = $request->validate([
            'site_name' => 'required|string|max:120',
            'site_description' => 'nullable|string|max:500',
            'contact_email' => 'nullable|email|max:255',
            'desktop_icon_set' => ['required', Rule::in(array_keys(config('desktop.icon_sets')))],
            'desktop_wallpaper' => ['required', Rule::in($wallpapers)],
            'desktop_accent' => ['required', Rule::in(array_keys(config('desktop.accents')))],
            'desktop_custom_wallpaper' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240', 'dimensions:min_width=320,min_height=180,max_width=8000,max_height=8000'],
        ]);
        if ($values['desktop_wallpaper'] === 'custom' && !$request->hasFile('desktop_custom_wallpaper') && !$settings->get('desktop_custom_wallpaper')) {
            return back()->withErrors(['desktop_custom_wallpaper' => __('ui.desktop_custom_wallpaper_required')])->withInput();
        }
        unset($values['desktop_custom_wallpaper']);
        if ($request->hasFile('desktop_custom_wallpaper')) {
            $values['desktop_custom_wallpaper'] = $request->file('desktop_custom_wallpaper')->store('desktop/wallpapers', 'local');
        }
        $settings->update($values);
        return back()->with('status', __('ui.saved'));
    }
    public function wallpaper(Settings $settings)
    {
        $path = $settings->get('desktop_custom_wallpaper');
        abort_unless(is_string($path) && preg_match('#^desktop/wallpapers/[A-Za-z0-9._-]+$#', $path) && Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->response($path, null, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=3600']);
    }
}
