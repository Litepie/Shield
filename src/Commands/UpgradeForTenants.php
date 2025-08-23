<?php

namespace Litepie\Shield\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class UpgradeForTenants extends Command
{
    protected $signature = 'shield:upgrade-for-tenants';

    protected $description = 'Upgrade the shield package for tenant support';

    public function handle()
    {
        $this->info('Upgrading Shield package for tenant support...');

        if ($this->shouldAddTenantColumns()) {
            $this->info('Adding tenant columns to shield tables...');
            $this->addTenantColumns();
        } else {
            $this->info('Tenant columns already exist.');
        }

        $this->info('Shield package upgraded for tenant support successfully.');
    }

    protected function shouldAddTenantColumns(): bool
    {
        $modelHasPermissionsTable = config('shield.table_names.model_has_permissions');
        $modelHasRolesTable = config('shield.table_names.model_has_roles');
        $rolesTable = config('shield.table_names.roles');
        $permissionsTable = config('shield.table_names.permissions');

        return ! Schema::hasColumn($modelHasPermissionsTable, 'tenant_id') ||
               ! Schema::hasColumn($modelHasRolesTable, 'tenant_id') ||
               ! Schema::hasColumn($rolesTable, 'tenant_id') ||
               ! Schema::hasColumn($permissionsTable, 'tenant_id');
    }

    protected function addTenantColumns(): void
    {
        $tenantColumnName = config('shield.column_names.tenant_foreign_key', 'tenant_id');

        $tableNames = config('shield.table_names');

        if (! Schema::hasColumn($tableNames['permissions'], $tenantColumnName)) {
            Schema::table($tableNames['permissions'], function ($table) use ($tenantColumnName) {
                $table->unsignedBigInteger($tenantColumnName)->nullable();
                $table->index($tenantColumnName, 'permissions_tenant_id_index');
            });
        }

        if (! Schema::hasColumn($tableNames['roles'], $tenantColumnName)) {
            Schema::table($tableNames['roles'], function ($table) use ($tenantColumnName) {
                $table->unsignedBigInteger($tenantColumnName)->nullable();
                $table->index($tenantColumnName, 'roles_tenant_id_index');
            });
        }

        if (! Schema::hasColumn($tableNames['model_has_permissions'], $tenantColumnName)) {
            Schema::table($tableNames['model_has_permissions'], function ($table) use ($tenantColumnName) {
                $table->unsignedBigInteger($tenantColumnName)->nullable();
                $table->index($tenantColumnName, 'model_has_permissions_tenant_id_index');
            });
        }

        if (! Schema::hasColumn($tableNames['model_has_roles'], $tenantColumnName)) {
            Schema::table($tableNames['model_has_roles'], function ($table) use ($tenantColumnName) {
                $table->unsignedBigInteger($tenantColumnName)->nullable();
                $table->index($tenantColumnName, 'model_has_roles_tenant_id_index');
            });
        }
    }
}
