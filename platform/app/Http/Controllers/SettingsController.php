<?php
namespace App\Http\Controllers;
use App\Services\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
class SettingsController extends Controller
{

    public function pageData(Settings $settings): array
    {
        $canManageUsers = Gate::allows('users.manage');
        return ['settings' => $settings->all(), 'currentUser' => auth()->user()?->load('profile'),
            'roles' => $canManageUsers ? Role::with('permissions')->orderBy('name')->get() : collect(),
            'users' => $canManageUsers ? User::with('roles')->orderBy('name')->get() : collect(),
            'permissions' => $canManageUsers ? Permission::orderBy('name')->get() : collect(), 'secretStatus' => [
                'ai_api_key' => $settings->hasSecret('ai_api_key'),
            ]];
    }
    public function update(Request $request, Settings $settings)
    {
        $wallpapers = [...array_keys(config('desktop.wallpapers')), 'custom'];
        $section = $request->has('section') ? $request->string('section')->toString() : 'legacy';
        if (in_array($section, ['social', 'integrations'], true)) Gate::authorize('integrations.manage');
        if ($section === 'publishing') Gate::authorize('content.publish');
        $rules = match ($section) {
            'legacy' => ['site_name' => 'required|string|max:120', 'site_description' => 'nullable|string|max:500', 'contact_email' => 'nullable|email|max:255', 'desktop_icon_set' => ['required', Rule::in(array_keys(config('desktop.icon_sets')))], 'desktop_wallpaper' => ['required', Rule::in($wallpapers)], 'desktop_accent' => ['required', Rule::in(array_keys(config('desktop.accents')))], 'desktop_custom_wallpaper' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240', 'dimensions:min_width=320,min_height=180,max_width=8000,max_height=8000']],
            'desktop_design' => ['desktop_icon_set' => ['required', Rule::in(array_keys(config('desktop.icon_sets')))], 'desktop_wallpaper' => ['required', Rule::in($wallpapers)], 'desktop_accent' => ['required', Rule::in(array_keys(config('desktop.accents')))], 'desktop_density' => ['required', Rule::in(['comfortable','compact'])], 'desktop_shortcut_layout' => ['required', Rule::in(['free','grid'])], 'desktop_effects' => 'nullable|boolean', 'desktop_custom_wallpaper' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240', 'dimensions:min_width=320,min_height=180,max_width=8000,max_height=8000']],
            'ai' => ['ai_provider' => ['required', Rule::in(['none','openai','anthropic','azure'])], 'ai_model' => 'nullable|string|max:120', 'ai_api_key' => 'nullable|string|max:2000', 'ai_enabled' => 'nullable|boolean'],
            'social' => ['provider' => ['required', Rule::in(['youtube','facebook','instagram','tiktok','telegram','linkedin','x'])], 'public_url' => 'nullable|url|max:1000', 'external_id' => 'nullable|string|max:255', 'api_key' => 'nullable|string|max:2000', 'oauth_client_id' => 'nullable|string|max:2000', 'access_token' => 'nullable|string|max:4000', 'webhook_secret' => 'nullable|string|max:2000'],
            'publishing' => ['publishing_default_visibility' => ['required', Rule::in(['private','internal','public'])], 'publishing_default_timezone' => 'required|timezone', 'publishing_approval_required' => 'nullable|boolean', 'publishing_automation_enabled' => 'nullable|boolean'],
            'integrations' => ['provider' => ['required', Rule::in(['stripe','google_drive','google_calendar','google_analytics','mailchimp','zapier','webhook'])], 'public_url' => 'nullable|url|max:1000', 'account_id' => 'nullable|string|max:255', 'api_key' => 'nullable|string|max:2000', 'oauth_client_id' => 'nullable|string|max:2000', 'access_token' => 'nullable|string|max:4000', 'webhook_secret' => 'nullable|string|max:2000'],
            'system' => ['site_name' => 'required|string|max:120', 'site_description' => 'nullable|string|max:500', 'contact_email' => 'nullable|email|max:255', 'system_locale' => ['required', Rule::in(config('platform.locales'))], 'system_timezone' => 'required|timezone', 'system_branding_name' => 'nullable|string|max:120', 'legal_locale' => ['nullable', Rule::in(config('platform.locales'))], 'impressum' => 'nullable|string|max:50000', 'privacy_policy' => 'nullable|string|max:50000', 'editorial_policy' => 'nullable|string|max:50000'],
            default => abort(404),
        };
        $values = $request->validate($rules);
        if ($request->boolean('reset_wallpaper')) $values['desktop_wallpaper'] = 'mountains';
        if (in_array($section, ['desktop_design', 'legacy'], true) && $values['desktop_wallpaper'] === 'custom' && !$request->hasFile('desktop_custom_wallpaper') && !$settings->get('desktop_custom_wallpaper')) {
            return back()->withErrors(['desktop_custom_wallpaper' => __('ui.desktop_custom_wallpaper_required')])->withInput();
        }
        if (in_array($section, ['social', 'integrations'], true)) {
            $collectionKey = $section === 'social' ? 'social_connections' : 'integration_connections';
            $connections = $settings->get($collectionKey, []);
            $provider = $values['provider'];
            $connections[$provider] = array_filter([
                'provider' => $provider, 'public_url' => $values['public_url'] ?? null,
                'external_id' => $values['external_id'] ?? $values['account_id'] ?? null,
                'configured_at' => now()->toIso8601String(),
            ]);
            $credentials = array_filter(array_intersect_key($values, array_flip(['api_key','oauth_client_id','access_token','webhook_secret'])));
            $settings->update([$collectionKey => $connections]);
            if ($credentials) $settings->updateSecrets([$section.'_'.$provider => json_encode($credentials, JSON_THROW_ON_ERROR)]);
            if ($request->expectsJson()) return response()->json(['status' => 'saved', 'section' => $section, 'provider' => $provider]);
            return back()->with('status', __('ui.saved'));
        }
        if ($section === 'system' && !empty($values['legal_locale'])) {
            $documents = $settings->get('legal_documents', []);
            $documents[$values['legal_locale']] = array_filter([
                'impressum' => $this->sanitizeLegalMarkup($values['impressum'] ?? ''),
                'privacy_policy' => $this->sanitizeLegalMarkup($values['privacy_policy'] ?? ''),
                'editorial_policy' => $this->sanitizeLegalMarkup($values['editorial_policy'] ?? ''),
            ]);
            $values['legal_documents'] = $documents;
            unset($values['legal_locale'], $values['impressum'], $values['privacy_policy'], $values['editorial_policy']);
        }
        $secretKeys = ['ai_api_key'];
        $secrets = array_intersect_key($values, array_flip($secretKeys));
        $values = array_diff_key($values, array_flip($secretKeys));
        foreach (['desktop_effects','ai_enabled','publishing_approval_required','publishing_automation_enabled'] as $boolean) if (array_key_exists($boolean, $rules)) $values[$boolean] = $request->boolean($boolean);
        unset($values['desktop_custom_wallpaper']);
        if ($request->hasFile('desktop_custom_wallpaper')) {
            $values['desktop_custom_wallpaper'] = $request->file('desktop_custom_wallpaper')->store('desktop/wallpapers', 'local');
        }
        $settings->update($values);
        $settings->updateSecrets($secrets);
        if ($request->expectsJson()) return response()->json(['status' => 'saved', 'section' => $section]);
        return back()->with('status', __('ui.saved'))->with('saved_section', $section);
    }
    private function sanitizeLegalMarkup(?string $markup): string
    {
        if (!is_string($markup) || trim($markup) === '') return '';
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<div id="legal-content">'.$markup.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors(); libxml_use_internal_errors($previous);
        $container = $document->getElementById('legal-content');
        if (!$container) return '';
        $this->cleanLegalNodes($container);
        return trim(implode('', array_map(fn ($node) => $document->saveHTML($node), iterator_to_array($container->childNodes))));
    }
    private function cleanLegalNodes(\DOMNode $node): void
    {
        $allowed = ['p', 'br', 'strong', 'b', 'em', 'i', 'ul', 'ol', 'li'];
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (!$child instanceof \DOMElement) continue;
            $tag = strtolower($child->tagName);
            if (in_array($tag, ['script', 'style'], true)) { $node->removeChild($child); continue; }
            if (!in_array($tag, $allowed, true)) {
                while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                $node->removeChild($child); continue;
            }
            while ($child->hasAttributes()) $child->removeAttributeNode($child->attributes->item(0));
            $this->cleanLegalNodes($child);
        }
    }
    public function storeRole(Request $request, \App\Services\Audit $audit)
    {
        $data = $request->validate(['name' => 'required|string|max:80|unique:roles,name', 'permissions' => 'array', 'permissions.*' => 'integer|exists:permissions,id']);
        $role = Role::create(['name' => $data['name']]); $role->permissions()->sync($data['permissions'] ?? []);
        $audit->record('role.created', (string) $role->id);
        if ($request->expectsJson()) return response()->json(['status' => 'saved', 'section' => 'users']);
        return back()->with('status', __('ui.saved'));
    }
    public function updateRole(Request $request, Role $role, \App\Services\Audit $audit)
    {
        $data = $request->validate(['permissions' => 'array', 'permissions.*' => 'integer|exists:permissions,id']);
        $permissions = $data['permissions'] ?? [];
        if ($role->name === 'Owner') $permissions = Permission::pluck('id')->all();
        $role->permissions()->sync($permissions); $audit->record('role.permissions_updated', (string) $role->id);
        if ($request->expectsJson()) return response()->json(['status' => 'saved', 'section' => 'users']);
        return back()->with('status', __('ui.saved'));
    }
    public function wallpaper(Settings $settings)
    {
        $path = $settings->get('desktop_custom_wallpaper');
        abort_unless(is_string($path) && preg_match('#^desktop/wallpapers/[A-Za-z0-9._-]+$#', $path) && Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->response($path, null, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=3600']);
    }
}
