<?php
namespace App\Services;
use App\Models\Permission;
use App\Models\Role;
class Access
{
    public const PERMISSIONS = ['desktop.view','media.view','media.upload','media.edit','media.delete',
        'settings.manage','users.manage','audit.view','projects.manage','content.edit','content.publish',
        'imports.manage','integrations.manage','community.moderate','subscribers.manage','analytics.view','live.manage'];
    public function seed(): void
    {
        foreach (self::PERMISSIONS as $name) Permission::firstOrCreate(['name' => $name]);
        $roles = ['Owner' => self::PERMISSIONS, 'Administrator' => self::PERMISSIONS,
            'Mediengestalter' => ['desktop.view','media.view','media.upload','media.edit','projects.manage','content.edit','imports.manage'],
            'Editor' => ['desktop.view','media.view','media.upload','media.edit','projects.manage','content.edit','content.publish'],
            'Moderator' => ['desktop.view','community.moderate'], 'Support' => ['desktop.view','audit.view']];
        foreach ($roles as $name => $permissions) {
            $role = Role::firstOrCreate(['name' => $name]);
            $role->permissions()->sync(Permission::whereIn('name', $permissions)->pluck('id'));
        }
    }
}
