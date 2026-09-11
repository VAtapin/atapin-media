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
    public function index(){return view('users.index',['users'=>User::with('roles')->orderBy('name')->paginate(30),'roles'=>Role::all()]);}
    public function store(Request $r,Audit $audit)
    {
        $data=$r->validate(['name'=>'required|string|max:255','email'=>'required|email|max:255|unique:users,email',
            'password'=>'required|string|max:72','role_id'=>'required|integer|exists:roles,id']);
        DB::transaction(function()use($data,$audit){
            $role=$data['role_id'];unset($data['role_id']);$user=User::create($data);$user->roles()->attach($role);
            $audit->record('user.created',(string)$user->id);
        });return back()->with('status',__('ui.saved'));
    }
    public function update(Request $r,User $user,Audit $audit)
    {
        $data=$r->validate(['role_id'=>'required|integer|exists:roles,id']);
        DB::transaction(function()use($data,$user,$audit){
            $owner=Role::where('name','Owner')->lockForUpdate()->firstOrFail();
            if($user->roles()->whereKey($owner->id)->exists() && (int)$data['role_id']!==$owner->id
                && User::whereHas('roles',fn($q)=>$q->where('roles.id',$owner->id))->count()<=1){
                throw ValidationException::withMessages(['role_id'=>__('ui.last_owner')]);
            }
            $user->roles()->sync([$data['role_id']]);$audit->record('user.role_changed',(string)$user->id,['role_id'=>$data['role_id']]);
        });return back()->with('status',__('ui.saved'));
    }
}
