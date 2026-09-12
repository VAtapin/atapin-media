<?php
namespace App\Http\Controllers;
use App\Services\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class SettingsController extends Controller
{
    public function edit(Settings $settings) { return view('settings', ['settings' => $settings->all()]); }
    public function update(Request $request, Settings $settings)
    {
        $settings->update($request->validate([
            'site_name' => 'required|string|max:120',
            'site_description' => 'nullable|string|max:500',
            'contact_email' => 'nullable|email|max:255',
            'desktop_icon_set' => ['required', Rule::in(array_keys(config('desktop.icon_sets')))],
            'desktop_wallpaper' => ['required', Rule::in(array_keys(config('desktop.wallpapers')))],
            'desktop_accent' => ['required', Rule::in(array_keys(config('desktop.accents')))],
        ]));
        return back()->with('status', __('ui.saved'));
    }
}
