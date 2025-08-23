<?php

namespace Litepie\Shield\Commands;

use Illuminate\Console\Command;
use Litepie\Shield\Contracts\Permission as PermissionContract;

class CreatePermission extends Command
{
    protected $signature = 'shield:create-permission
                            {name : The name of the permission}
                            {guard? : The name of the guard}
                            {--tenantId= : The tenant ID for tenant-based permissions}';

    protected $description = 'Create a permission';

    public function handle()
    {
        $permissionClass = app(PermissionContract::class);

        $permission = $permissionClass::create([
            'name' => $this->argument('name'),
            'guard_name' => $this->argument('guard') ?: config('auth.defaults.guard'),
            'tenant_id' => $this->option('tenantId'),
        ]);

        $this->info("Permission `{$permission->name}` created");
    }
}
