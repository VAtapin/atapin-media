<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Models\Role;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $data = $request->validate(['role_id'=>'required|integer|exists:roles,id']);
        DB::transaction(function() use ($data, $user, $audit) {
            $owner = Role::where('name','Owner')->lockForUpdate()->firstOrFail();
            if ($user->roles()->whereKey($owner->id)->exists() && (int) $data['role_id'] !== $owner->id
                && User::whereHas('roles', fn ($query) => $query->where('roles.id',$owner->id))->count() <= 1) {
                throw ValidationException::withMessages(['role_id'=>__('ui.last_owner')]);
            }
            $user->roles()->sync([$data['role_id']]);
            $audit->record('user.role_changed',(string) $user->id,['role_id'=>$data['role_id']]);
        });
        if ($request->expectsJson()) return response()->json(['status'=>'saved','user_id'=>$user->id]);
        return back()->with('status', __('ui.saved'));
    }
}