<?php

namespace Litepie\Shield\Commands;

use Illuminate\Console\Command;
use Litepie\Shield\Contracts\Role as RoleContract;

class CreateRole extends Command
{
    protected $signature = 'shield:create-role
                            {name : The name of the role}
                            {guard? : The name of the guard}
                            {--tenantId= : The tenant ID for tenant-based roles}';

    protected $description = 'Create a role';

    public function handle()
    {
        $roleClass = app(RoleContract::class);

        $role = $roleClass::create([
            'name' => $this->argument('name'),
            'guard_name' => $this->argument('guard') ?: config('auth.defaults.guard'),
            'tenant_id' => $this->option('tenantId'),
        ]);

        $this->info("Role `{$role->name}` created");
    }
}
