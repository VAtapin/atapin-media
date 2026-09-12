<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        $id = DB::table('permissions')->where('name', 'shop.manage')->value('id');
        if (!$id) { $id = DB::table('permissions')->insertGetId(['name' => 'shop.manage']); }
        foreach (DB::table('roles')->whereIn('name', ['Owner', 'Administrator'])->pluck('id') as $roleId) {
            DB::table('permission_role')->updateOrInsert(['permission_id'=>$id, 'role_id'=>$roleId], []);
        }
    }
    public function down(): void { $id = DB::table('permissions')->where('name', 'shop.manage')->value('id'); if ($id) { DB::table('permission_role')->where('permission_id',$id)->delete(); DB::table('permissions')->where('id',$id)->delete(); } }
};