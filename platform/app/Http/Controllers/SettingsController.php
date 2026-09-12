<?php
namespace App\Http\Controllers;
use App\Services\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\Gate;
class SettingsController extends Controller
{
    public function edit(Request $request, Settings $settings) {
        $data = ['embedded' => $request->boolean('embed'), 'settings' => $settings->all(), 'roles' => Role::with('permissions')->orderBy('name')->get(),
            'permissions' => Permission::orderBy('name')->get(), 'secretStatus' => [
                'ai_api_key' => $settings->hasSecret('ai_api_key'), 'youtube_api_key' => $settings->hasSecret('youtube_api_key'),
                'facebook_access_token' => $settings->hasSecret('facebook_access_token'), 'instagram_access_token' => $settings->hasSecret('instagram_access_token'),
                'tiktok_access_token' => $settings->hasSecret('tiktok_access_token'), 'telegram_bot_token' => $settings->hasSecret('telegram_bot_token'),
                'integration_api_token' => $settings->hasSecret('integration_api_token'),
            ]];
        return view($request->boolean('embed') ? 'settings-embed' : 'settings', $data);
    }
    public function update(Request $request, Settings $settings)
    {
        $wallpapers = [...array_keys(config('desktop.wallpapers')), 'custom'];
        $section = $request->has('section') ? $request->string('section')->toString() : 'legacy';
        if (in_array($section, ['social', 'integrations'], true)) Gate::authorize('integrations.manage');
        if ($section === 'publishing') Gate::authorize('content.publish');
        $rules = match ($section) {
            'legacy' => ['site_name' => 'required|string|max:120', 'site_description' => 'nullable|string|max:500', 'contact_email' => 'nullable|email|max:255', 'desktop_icon_set' => ['required', Rule::in(array_keys(config('desktop.icon_sets')))], 'desktop_wallpaper' => ['required', Rule::in($wallpapers)], 'desktop_accent' => ['required', Rule::in(array_keys(config('desktop.accents')))], 'desktop_custom_wallpaper' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240', 'dimensions:min_width=320,min_height=180,max_width=8000,max_height=8000']],
            'desktop_design' => ['desktop_icon_set' => ['required', Rule::in(array_keys(config('desktop.icon_sets')))], 'desktop_wallpaper' => ['required', Rule::in($wallpapers)], 'desktop_accent' => ['required', Rule::in(array_keys(config('desktop.accents')))], 'desktop_density' => ['required', Rule::in(['comfortable','compact'])], 'desktop_effects' => 'nullable|boolean', 'desktop_custom_wallpaper' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240', 'dimensions:min_width=320,min_height=180,max_width=8000,max_height=8000']],
            'ai' => ['ai_provider' => ['required', Rule::in(['none','openai','anthropic','azure'])], 'ai_model' => 'nullable|string|max:120', 'ai_api_key' => 'nullable|string|max:2000', 'ai_enabled' => 'nullable|boolean'],
            'social' => ['youtube_channel' => 'nullable|string|max:255', 'facebook_page' => 'nullable|string|max:255', 'instagram_account' => 'nullable|string|max:255', 'tiktok_account' => 'nullable|string|max:255', 'telegram_chat_id' => 'nullable|string|max:255', 'youtube_api_key' => 'nullable|string|max:2000', 'facebook_access_token' => 'nullable|string|max:2000', 'instagram_access_token' => 'nullable|string|max:2000', 'tiktok_access_token' => 'nullable|string|max:2000', 'telegram_bot_token' => 'nullable|string|max:2000'],
            'publishing' => ['publishing_default_visibility' => ['required', Rule::in(['private','internal','public'])], 'publishing_default_timezone' => 'required|timezone', 'publishing_approval_required' => 'nullable|boolean', 'publishing_automation_enabled' => 'nullable|boolean'],
            'integrations' => ['integration_api_url' => 'nullable|url|max:1000', 'integration_api_token' => 'nullable|string|max:2000', 'integration_webhook_url' => 'nullable|url|max:1000'],
            'system' => ['site_name' => 'required|string|max:120', 'site_description' => 'nullable|string|max:500', 'contact_email' => 'nullable|email|max:255', 'system_locale' => ['required', Rule::in(config('platform.locales'))], 'system_timezone' => 'required|timezone', 'system_branding_name' => 'nullable|string|max:120'],
            default => abort(404),
        };
        $values = $request->validate($rules);
        if ($request->boolean('reset_wallpaper')) $values['desktop_wallpaper'] = 'mountains';
        if (in_array($section, ['desktop_design', 'legacy'], true) && $values['desktop_wallpaper'] === 'custom' && !$request->hasFile('desktop_custom_wallpaper') && !$settings->get('desktop_custom_wallpaper')) {
            return back()->withErrors(['desktop_custom_wallpaper' => __('ui.desktop_custom_wallpaper_required')])->withInput();
        }
        $secretKeys = ['ai_api_key','youtube_api_key','facebook_access_token','instagram_access_token','tiktok_access_token','telegram_bot_token','integration_api_token'];
        $secrets = array_intersect_key($values, array_flip($secretKeys));
        $values = array_diff_key($values, array_flip($secretKeys));
        foreach (['desktop_effects','ai_enabled','publishing_approval_required','publishing_automation_enabled'] as $boolean) if (array_key_exists($boolean, $rules)) $values[$boolean] = $request->boolean($boolean);
        unset($values['desktop_custom_wallpaper']);
        if ($request->hasFile('desktop_custom_wallpaper')) {
            $values['desktop_custom_wallpaper'] = $request->file('desktop_custom_wallpaper')->store('desktop/wallpapers', 'local');
        }
        $settings->update($values);
        $settings->updateSecrets($secrets);
        return back()->with('status', __('ui.saved'))->with('saved_section', $section);
    }
    public function storeRole(Request $request, \App\Services\Audit $audit)
    {
        $data = $request->validate(['name' => 'required|string|max:80|unique:roles,name', 'permissions' => 'array', 'permissions.*' => 'integer|exists:permissions,id']);
        $role = Role::create(['name' => $data['name']]); $role->permissions()->sync($data['permissions'] ?? []);
        $audit->record('role.created', (string) $role->id); return back()->with('status', __('ui.saved'));
    }
    public function updateRole(Request $request, Role $role, \App\Services\Audit $audit)
    {
        $data = $request->validate(['permissions' => 'array', 'permissions.*' => 'integer|exists:permissions,id']);
        $permissions = $data['permissions'] ?? [];
        if ($role->name === 'Owner') $permissions = Permission::pluck('id')->all();
        $role->permissions()->sync($permissions); $audit->record('role.permissions_updated', (string) $role->id);
        return back()->with('status', __('ui.saved'));
    }
    public function wallpaper(Settings $settings)
    {
        $path = $settings->get('desktop_custom_wallpaper');
        abort_unless(is_string($path) && preg_match('#^desktop/wallpapers/[A-Za-z0-9._-]+$#', $path) && Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->response($path, null, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=3600']);
    }
}
