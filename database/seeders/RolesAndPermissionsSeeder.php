<?php


namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Define permissions
        $permissions = [
            'view-users',
            'edit-users',
            'delete-users',
            'create-users',
        ];

        // Check if permissions already exist before creating them
        foreach ($permissions as $permission) {
            if (!Permission::where('name', $permission)->where('guard_name', 'web')->exists()) {
                Permission::create(['name' => $permission, 'guard_name' => 'web']);
            }
        }

        // Check if roles already exist before creating them
        if (!Role::where('name', 'Admin')->exists()) {
            $adminRole = Role::create(['name' => 'Admin']);
            $adminRole->givePermissionTo(Permission::all());
        }

        if (!Role::where('name', 'Auxiliar')->exists()) {
            $editorRole = Role::create(['name' => 'Auxiliar']);
            $editorRole->givePermissionTo(['edit-users', 'delete-users']);
        }
         if (!Role::where('name', 'Lectura')->exists()) {
             $editorRole = Role::create(['name' => 'Lectura']);
             $editorRole->givePermissionTo(['view-users']);
         }

      
    }
}
