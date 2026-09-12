<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Models\Role;
use App\Models\UserProfile;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class UserController extends Controller
{
    public function store(Request $request, Audit $audit)
    {
        $data = $request->validate(['name'=>'required|string|max:255','email'=>'required|email|max:255|unique:users,email',
            'password'=>'required|string|max:72','role_id'=>'required|integer|exists:roles,id']);
        $user = DB::transaction(function() use ($data, $audit) {
            $role = $data['role_id']; unset($data['role_id']);
            $user = User::create($data); $user->roles()->attach($role);
            $audit->record('user.created', (string) $user->id);
            return $user;
        });
        if ($request->expectsJson()) return response()->json(['status'=>'saved','user_id'=>$user->id]);
        return back()->with('status', __('ui.saved'));
    }
    public function update(Request $request, User $user, Audit $audit)
    {
        $data = $request->validate([
            'name'=>'required|string|max:255', 'email'=>['required','email','max:255',Rule::unique('users','email')->ignore($user)],
            'password'=>'nullable|string|min:5|max:72|confirmed', 'role_id'=>'required|integer|exists:roles,id',
        ]);
        DB::transaction(function() use ($data, $user, $audit) {
            $owner = Role::where('name','Owner')->lockForUpdate()->firstOrFail();
            if ($user->roles()->whereKey($owner->id)->exists() && (int) $data['role_id'] !== $owner->id
                && User::whereHas('roles', fn ($query) => $query->where('roles.id',$owner->id))->count() <= 1) {
                throw ValidationException::withMessages(['role_id'=>__('ui.last_owner')]);
            }
            $user->roles()->sync([$data['role_id']]);
            $user->fill(['name'=>$data['name'],'email'=>$data['email']]);
            if (!empty($data['password'])) $user->password = $data['password'];
            $user->save();
            $audit->record('user.updated',(string) $user->id,['role_id'=>$data['role_id'],'password_changed'=>!empty($data['password'])]);
        });
        if ($request->expectsJson()) return response()->json(['status'=>'saved','user_id'=>$user->id,'name'=>$user->name,'email'=>$user->email]);
        return back()->with('status', __('ui.saved'));
    }
    public function updateProfile(Request $request, Audit $audit)
    {
        $user = $request->user();
        $data = $request->validate([
            'name'=>'required|string|max:255', 'email'=>['required','email','max:255',Rule::unique('users','email')->ignore($user)],
            'current_password'=>'required_with:password|string|max:72', 'password'=>'nullable|string|min:5|max:72|confirmed',
            'avatar'=>'nullable|image|mimes:jpg,jpeg,png,webp|max:4096|dimensions:min_width=64,min_height=64,max_width=4000,max_height=4000',
            'phone'=>'nullable|string|max:80', 'location'=>'nullable|string|max:120', 'website'=>'nullable|url|max:1000', 'bio'=>'nullable|string|max:3000',
            'personal_youtube'=>'nullable|url|max:1000', 'personal_facebook'=>'nullable|url|max:1000', 'personal_instagram'=>'nullable|url|max:1000',
            'personal_tiktok'=>'nullable|url|max:1000', 'personal_telegram'=>'nullable|url|max:1000', 'personal_linkedin'=>'nullable|url|max:1000',
        ]);
        if (!empty($data['password']) && !Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password'=>__('ui.login_failed')]);
        }
        $user->fill(['name'=>$data['name'],'email'=>$data['email']]);
        if (!empty($data['password'])) $user->password = $data['password'];
        $user->save();
        $profile = $user->profile ?: new UserProfile;
        $profile->user_id = $user->id;
        $existingLinks = $profile->social_links ?? [];
        $profileValues = [
            'phone'=>array_key_exists('phone', $data) ? $data['phone'] : $profile->phone,
            'location'=>array_key_exists('location', $data) ? $data['location'] : $profile->location,
            'website'=>array_key_exists('website', $data) ? $data['website'] : $profile->website,
            'bio'=>array_key_exists('bio', $data) ? $data['bio'] : $profile->bio,
            'social_links'=>array_filter([
                'youtube'=>array_key_exists('personal_youtube', $data) ? $data['personal_youtube'] : ($existingLinks['youtube'] ?? null),
                'facebook'=>array_key_exists('personal_facebook', $data) ? $data['personal_facebook'] : ($existingLinks['facebook'] ?? null),
                'instagram'=>array_key_exists('personal_instagram', $data) ? $data['personal_instagram'] : ($existingLinks['instagram'] ?? null),
                'tiktok'=>array_key_exists('personal_tiktok', $data) ? $data['personal_tiktok'] : ($existingLinks['tiktok'] ?? null),
                'telegram'=>array_key_exists('personal_telegram', $data) ? $data['personal_telegram'] : ($existingLinks['telegram'] ?? null),
                'linkedin'=>array_key_exists('personal_linkedin', $data) ? $data['personal_linkedin'] : ($existingLinks['linkedin'] ?? null),
            ]),
        ];
        if ($request->hasFile('avatar')) $profileValues['avatar_path'] = $request->file('avatar')->store('profiles/avatars', 'local');
        $profile->fill($profileValues); $profile->save();
        $audit->record('user.profile_updated',(string) $user->id,['password_changed'=>!empty($data['password']),'avatar_changed'=>$request->hasFile('avatar')]);
        if ($request->expectsJson()) return response()->json(['status'=>'saved','user_id'=>$user->id,'name'=>$user->name,'email'=>$user->email,'avatar_url'=>$profile->avatar_path ? route('profile.avatar').'?v='.$profile->updated_at->timestamp : null]);
        return back()->with('status', __('ui.saved'));
    }
    public function avatar(Request $request)
    {
        $path = $request->user()->profile?->avatar_path;
        abort_unless(is_string($path) && preg_match('#^profiles/avatars/[A-Za-z0-9._-]+$#', $path) && Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->response($path, null, ['X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, max-age=3600']);
    }
}
