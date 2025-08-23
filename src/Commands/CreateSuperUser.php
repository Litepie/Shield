<?php

namespace Litepie\Shield\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Litepie\Shield\Models\Permission;
use Litepie\Shield\Models\Role;

class CreateSuperUser extends Command
{
    protected $signature = 'shield:create-superuser
                            {--name= : The name of the super user}
                            {--email= : The email of the super user}
                            {--password= : The password of the super user}
                            {--tenantId= : The tenant ID for tenant-specific superuser}
                            {--force : Force creation without confirmation}';

    protected $description = 'Create a super user with Super Admin role and all permissions';

    public function handle()
    {
        $this->info('Creating Super User...');

        // Get user model class
        $userModel = $this->getUserModel();

        if (!$userModel) {
            $this->error('Could not determine user model. Please ensure your auth configuration is set up correctly.');
            return 1;
        }

        // Get user details
        $name = $this->option('name') ?: $this->ask('Name');
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->option('password') ?: $this->secret('Password');

        // Validate input
        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return 1;
        }

        // Check if user already exists
        $existingUser = $userModel::where('email', $email)->first();
        if ($existingUser && !$this->option('force')) {
            if (!$this->confirm("User with email {$email} already exists. Do you want to continue and assign Super Admin role?")) {
                $this->info('Operation cancelled.');
                return 0;
            }
            $user = $existingUser;
        } else {
            // Create new user
            $user = $userModel::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]);
        }

        // Create Super Admin role if it doesn't exist
        $superAdminRole = Role::firstOrCreate([
            'name' => 'Super Admin',
            'guard_name' => config('auth.defaults.guard', 'web')
        ]);

        // Create comprehensive permissions for Super Admin
        $this->createSuperAdminPermissions();

        // Assign all permissions to Super Admin role
        $superAdminRole->syncPermissions(Permission::all());

        // Assign Super Admin role to user
        if (!$user->hasRole('Super Admin')) {
            $user->assignRole($superAdminRole);
        }

        $this->info('✅ Super user created successfully!');
        $this->table(['Field', 'Value'], [
            ['Name', $user->name],
            ['Email', $user->email],
            ['Role', 'Super Admin'],
            ['Permissions', Permission::count() . ' permissions assigned'],
        ]);

        if (!$this->option('password')) {
            $this->warn('⚠️  Please ensure you remember the password you entered!');
        }

        return 0;
    }

    protected function getUserModel()
    {
        $guard = config('auth.defaults.guard');
        $provider = config("auth.guards.{$guard}.provider");
        return config("auth.providers.{$provider}.model");
    }

    protected function createSuperAdminPermissions()
    {
        $permissions = [
            // User management
            'users.view', 'users.create', 'users.edit', 'users.delete', 'users.restore', 'users.force-delete',
            
            // Role & Permission management
            'roles.view', 'roles.create', 'roles.edit', 'roles.delete', 'roles.assign',
            'permissions.view', 'permissions.create', 'permissions.edit', 'permissions.delete', 'permissions.assign',
            
            // Content management
            'posts.view', 'posts.create', 'posts.edit', 'posts.delete', 'posts.publish',
            'pages.view', 'pages.create', 'pages.edit', 'pages.delete', 'pages.publish',
            'categories.view', 'categories.create', 'categories.edit', 'categories.delete',
            
            // Media management
            'media.view', 'media.upload', 'media.edit', 'media.delete',
            
            // Settings management
            'settings.view', 'settings.edit', 'settings.general', 'settings.security', 'settings.integrations',
            
            // System administration
            'system.view', 'system.maintenance', 'system.logs', 'system.cache', 'system.backup',
            
            // Analytics and reports
            'analytics.view', 'reports.view', 'reports.create', 'reports.export',
            
            // API access
            'api.access', 'api.admin',
            
            // Wildcard permissions
            'admin.*', 'system.*',
        ];

        $created = 0;
        foreach ($permissions as $permission) {
            $perm = Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => config('auth.defaults.guard', 'web')
            ]);
            
            if ($perm->wasRecentlyCreated) {
                $created++;
            }
        }

        if ($created > 0) {
            $this->info("Created {$created} new permissions.");
        }
    }
}
