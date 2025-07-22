<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Define comprehensive permissions for the system
        $permissions = [
            // User management permissions
            'view-users',
            'create-users',
            'edit-users',
            'delete-users',
            'deactivate-users',
            'assign-roles',
            
            // Survey permissions
            'create-surveys',
            'edit-surveys',
            'delete-surveys',
            'publish-surveys',
            'unpublish-surveys',
            
            // Question bank permissions
            'view-question-bank',
            'create-questions',
            'edit-questions',
            'delete-questions',
            'reuse-questions',
            'create-sections',
            'edit-sections',
            'delete-sections',
            'reuse-sections',
            'create-categories',
            'edit-categories',
            'delete-categories',
            'reuse-categories',
            
            // Temporary table permissions
            'view-temporary-table',
            'delete-temporary-items',
            'checkpoint-return',
            
            // Navigation permissions
            'access-home',
            'access-create-surveys',
            'access-question-bank',
            'access-temporary-table',
            'access-user-manager',
            'access-encuestados',
            
            // Import/Export permissions
            'import-data',
            'export-data',
        ];

        // Check if permissions already exist before creating them
        foreach ($permissions as $permission) {
            if (!Permission::where('name', $permission)->where('guard_name', 'web')->exists()) {
                Permission::create(['name' => $permission, 'guard_name' => 'web']);
            }
        }

        // SUPERADMIN ROLE - Full access to everything
        if (!Role::where('name', 'Superadmin')->exists()) {
            $superadminRole = Role::create(['name' => 'Superadmin']);
            $superadminRole->givePermissionTo(Permission::all());
        }

        // ADMIN ROLE - Same permissions as Superadmin
        $adminRole = Role::where('name', 'Admin')->first();
        if (!$adminRole) {
            $adminRole = Role::create(['name' => 'Admin']);
        }
        // Always sync permissions to ensure they're up to date
        $adminRole->syncPermissions(Permission::all());

        // FUNCIONARIO ROLE - Limited survey creation and view-only user management
        if (!Role::where('name', 'Funcionario')->exists()) {
            $funcionarioRole = Role::create(['name' => 'Funcionario']);
            $funcionarioRole->givePermissionTo([
                // User management - view only
                'view-users',
                
                // Survey management - can create surveys
                'create-surveys',
                
                // Question bank - view access
                'view-question-bank',
                
                // Temporary table - view only, no deletions
                'view-temporary-table',
                
                // Navigation - access to all navbar options
                'access-home',
                'access-create-surveys',
                'access-question-bank',
                'access-temporary-table',
                'access-user-manager',
            ]);
        }

        // OPERARIO ROLE - Most limited access
        if (!Role::where('name', 'Operario')->exists()) {
            $operarioRole = Role::create(['name' => 'Operario']);
            $operarioRole->givePermissionTo([
                // Survey management - can create surveys
                'create-surveys',
                
                // Question bank - view and import only
                'view-question-bank',
                'import-data',
                
                // Temporary table - can only return checkpoint, no deletions
                'view-temporary-table',
                'checkpoint-return',
                
                // Navigation - all except user manager
                'access-home',
                'access-create-surveys',
                'access-question-bank',
                'access-temporary-table',
            ]);
        }
    }
}
