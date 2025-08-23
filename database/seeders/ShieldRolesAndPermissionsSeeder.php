<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Litepie\Shield\Models\Permission;
use Litepie\Shield\Models\Role;

class ShieldRolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Litepie\Shield\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // User management
            'users.view',
            'users.create', 
            'users.edit',
            'users.delete',
            'users.restore',
            'users.force-delete',

            // Role management
            'roles.view',
            'roles.create',
            'roles.edit', 
            'roles.delete',
            'roles.assign',

            // Permission management
            'permissions.view',
            'permissions.create',
            'permissions.edit',
            'permissions.delete',
            'permissions.assign',

            // Content management
            'posts.view',
            'posts.create',
            'posts.edit',
            'posts.delete',
            'posts.publish',

            'pages.view',
            'pages.create',
            'pages.edit',
            'pages.delete',
            'pages.publish',

            'categories.view',
            'categories.create',
            'categories.edit',
            'categories.delete',

            // Media management
            'media.view',
            'media.upload',
            'media.edit',
            'media.delete',

            // Settings management
            'settings.view',
            'settings.edit',
            'settings.general',
            'settings.security',
            'settings.integrations',

            // System administration
            'system.view',
            'system.maintenance',
            'system.logs',
            'system.cache',
            'system.backup',

            // Analytics and reports
            'analytics.view',
            'reports.view',
            'reports.create',
            'reports.export',

            // API access
            'api.access',
            'api.admin',

            // Wildcard permissions for super admin
            'admin.*',
            'system.*',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web'
            ]);
        }

        // Create roles
        $superAdminRole = Role::firstOrCreate([
            'name' => 'Super Admin',
            'guard_name' => 'web'
        ]);

        $adminRole = Role::firstOrCreate([
            'name' => 'Admin', 
            'guard_name' => 'web'
        ]);

        $editorRole = Role::firstOrCreate([
            'name' => 'Editor',
            'guard_name' => 'web'
        ]);

        $authorRole = Role::firstOrCreate([
            'name' => 'Author',
            'guard_name' => 'web'
        ]);

        $userRole = Role::firstOrCreate([
            'name' => 'User',
            'guard_name' => 'web'
        ]);

        // Assign permissions to Super Admin (all permissions)
        $superAdminRole->syncPermissions(Permission::all());

        // Assign permissions to Admin
        $adminPermissions = [
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'roles.view', 'roles.create', 'roles.edit', 'roles.assign',
            'permissions.view', 'permissions.assign',
            'posts.*', 'pages.*', 'categories.*', 'media.*',
            'settings.view', 'settings.edit', 'settings.general',
            'analytics.view', 'reports.*',
        ];
        $adminRole->syncPermissions(Permission::whereIn('name', $adminPermissions)->get());

        // Assign permissions to Editor
        $editorPermissions = [
            'posts.view', 'posts.create', 'posts.edit', 'posts.delete', 'posts.publish',
            'pages.view', 'pages.create', 'pages.edit', 'pages.delete', 'pages.publish',
            'categories.view', 'categories.create', 'categories.edit',
            'media.view', 'media.upload', 'media.edit',
            'users.view',
        ];
        $editorRole->syncPermissions(Permission::whereIn('name', $editorPermissions)->get());

        // Assign permissions to Author
        $authorPermissions = [
            'posts.view', 'posts.create', 'posts.edit',
            'pages.view', 'pages.create', 'pages.edit', 
            'media.view', 'media.upload',
            'categories.view',
        ];
        $authorRole->syncPermissions(Permission::whereIn('name', $authorPermissions)->get());

        // Basic user permissions
        $userPermissions = [
            'posts.view',
            'pages.view',
        ];
        $userRole->syncPermissions(Permission::whereIn('name', $userPermissions)->get());

        $this->command->info('Shield roles and permissions seeded successfully!');
        $this->command->info('Created roles: Super Admin, Admin, Editor, Author, User');
        $this->command->info('Created ' . count($permissions) . ' permissions');
    }
}
