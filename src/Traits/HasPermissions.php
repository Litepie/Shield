<?php

namespace Litepie\Shield\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Litepie\Shield\Contracts\Permission;
use Litepie\Shield\Contracts\Role;
use Litepie\Shield\Contracts\Wildcard;
use Litepie\Shield\Events\PermissionAttached;
use Litepie\Shield\Events\PermissionDetached;
use Litepie\Shield\Exceptions\GuardDoesNotMatch;
use Litepie\Shield\Exceptions\PermissionDoesNotExist;
use Litepie\Shield\Exceptions\WildcardPermissionInvalidArgument;
use Litepie\Shield\Exceptions\WildcardPermissionNotImplementsContract;
use Litepie\Shield\Guard;
use Litepie\Shield\PermissionRegistrar;
use Litepie\Shield\WildcardPermission;

trait HasPermissions
{
    private ?string $permissionClass = null;

    private ?string $wildcardClass = null;

    private array $wildcardPermissionsIndex;

    public static function bootHasPermissions()
    {
        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $tenants = app(PermissionRegistrar::class)->tenants;
            app(PermissionRegistrar::class)->tenants = false;
            if (! is_a($model, Permission::class)) {
                $model->permissions()->detach();
            }
            if (is_a($model, Role::class)) {
                $model->users()->detach();
            }
            app(PermissionRegistrar::class)->tenants = $tenants;
        });
    }

    public function getPermissionClass(): string
    {
        if (! $this->permissionClass) {
            $this->permissionClass = app(PermissionRegistrar::class)->getPermissionClass();
        }

        return $this->permissionClass;
    }

    public function getWildcardClass()
    {
        if (! is_null($this->wildcardClass)) {
            return $this->wildcardClass;
        }

        $this->wildcardClass = '';

        if (config('shield.enable_wildcard_permission')) {
            $this->wildcardClass = config('shield.wildcard_permission', WildcardPermission::class);

            if (! is_subclass_of($this->wildcardClass, Wildcard::class)) {
                throw WildcardPermissionNotImplementsContract::create();
            }
        }

        return $this->wildcardClass;
    }

    /**
     * A model may have multiple direct permissions.
     */
    public function permissions(): BelongsToMany
    {
        $relation = $this->morphToMany(
            config('shield.models.permission'),
            'model',
            config('shield.table_names.model_has_permissions'),
            config('shield.column_names.model_morph_key'),
            app(PermissionRegistrar::class)->pivotPermission
        );

        if (! app(PermissionRegistrar::class)->tenants) {
            return $relation;
        }

        $tenantsKey = app(PermissionRegistrar::class)->tenantsKey;
        $relation->withPivot($tenantsKey);

        return $relation->wherePivot($tenantsKey, getPermissionsTenantId());
    }

    /**
     * Scope the model query to certain permissions only.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string|int|array|\Litepie\Shield\Contracts\Permission|\Illuminate\Support\Collection  $permissions
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePermission(Builder $query, $permissions): Builder
    {
        $permissions = $this->convertToPermissionModels($permissions);

        $rolesWithPermissions = collect([]);
        if (! is_a($this, Permission::class)) {
            $rolesWithPermissions = $this->morphToMany(
                config('shield.models.role'),
                'model',
                config('shield.table_names.model_has_roles'),
                config('shield.column_names.model_morph_key'),
                app(PermissionRegistrar::class)->pivotRole
            )->select([
                config('shield.table_names.roles').'.'.app(PermissionRegistrar::class)->pivotRole,
                config('shield.table_names.roles').'.name',
            ])->whereHas('permissions', function (Builder $subQuery) use ($permissions) {
                $permissionKeyName = (new ($this->getPermissionClass()))->getKeyName();
                $subQuery->whereIn(config('shield.table_names.permissions').'.'.$permissionKeyName, \Illuminate\Support\Arr::flatten(\Illuminate\Support\Arr::pluck($permissions, $permissionKeyName)));
            });

            if (app(PermissionRegistrar::class)->tenants) {
                $tenantsKey = app(PermissionRegistrar::class)->tenantsKey;
                $rolesWithPermissions->wherePivot($tenantsKey, getPermissionsTenantId());
            }

            $rolesWithPermissions = $rolesWithPermissions->get();
        }

        return $query->where(function (Builder $subQuery) use ($permissions, $rolesWithPermissions) {
            $permissionKeyName = (new ($this->getPermissionClass()))->getKeyName();
            $modelKeyName = $this->getKeyName();
            $modelTable = $this->getTable();
            $modelMorphKey = config('shield.column_names.model_morph_key');
            $modelType = $this->getMorphClass();

            $subQuery->whereHas('permissions', function (Builder $permissionQuery) use ($permissions, $permissionKeyName) {
                $permissionQuery->whereIn(config('shield.table_names.permissions').'.'.$permissionKeyName, \Illuminate\Support\Arr::flatten(\Illuminate\Support\Arr::pluck($permissions, $permissionKeyName)));
            });

            if ($rolesWithPermissions->count() > 0) {
                $subQuery->orWhereHas('roles', function (Builder $roleQuery) use ($rolesWithPermissions) {
                    $roleKeyName = (new (config('shield.models.role')))->getKeyName();
                    $roleQuery->whereIn(config('shield.table_names.roles').'.'.$roleKeyName, \Illuminate\Support\Arr::flatten(\Illuminate\Support\Arr::pluck($rolesWithPermissions, $roleKeyName)));
                });
            }
        });
    }

    /**
     * Scope the model query to exclude certain permissions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string|int|array|\Litepie\Shield\Contracts\Permission|\Illuminate\Support\Collection  $permissions
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithoutPermission(Builder $query, $permissions): Builder
    {
        $permissions = $this->convertToPermissionModels($permissions);

        return $query->whereDoesntHave('permissions', function (Builder $subQuery) use ($permissions) {
            $permissionKeyName = (new ($this->getPermissionClass()))->getKeyName();
            $subQuery->whereIn(config('shield.table_names.permissions').'.'.$permissionKeyName, \Illuminate\Support\Arr::flatten(\Illuminate\Support\Arr::pluck($permissions, $permissionKeyName)));
        })->whereDoesntHave('roles', function (Builder $subQuery) use ($permissions) {
            $subQuery->whereHas('permissions', function (Builder $permissionQuery) use ($permissions) {
                $permissionKeyName = (new ($this->getPermissionClass()))->getKeyName();
                $permissionQuery->whereIn(config('shield.table_names.permissions').'.'.$permissionKeyName, \Illuminate\Support\Arr::flatten(\Illuminate\Support\Arr::pluck($permissions, $permissionKeyName)));
            });
        });
    }

    /**
     * Convert mixed permission data into permission models.
     *
     * @param  string|int|array|\Litepie\Shield\Contracts\Permission|\Illuminate\Support\Collection  $permissions
     * @return array
     */
    protected function convertToPermissionModels($permissions): array
    {
        if ($permissions instanceof Collection) {
            $permissions = $permissions->all();
        }

        $permissions = Arr::wrap($permissions);

        return array_map(function ($permission) {
            if ($permission instanceof Permission) {
                return $permission;
            }

            $method = is_string($permission) ? 'findByName' : 'findById';

            return $this->getPermissionClass()::$method($permission, $this->getDefaultGuardName());
        }, $permissions);
    }

    /**
     * Determine if the entity has the given permission(s).
     *
     * @param  string|int|array|\Litepie\Shield\Contracts\Permission|\Illuminate\Support\Collection|\BackedEnum  $permissions
     * @param  string|null  $guardName
     * @return bool
     */
    public function hasPermissionTo($permissions, $guardName = null): bool
    {
        if (config('shield.enable_wildcard_permission')) {
            return $this->hasWildcardPermission($permissions, $guardName);
        }

        $permissionClass = $this->getPermissionClass();
        if (is_string($permissions) && ! str_contains($permissions, '|')) {
            $permission = $permissionClass::findByName($permissions, $guardName ?: $this->getDefaultGuardName());

            return $this->hasDirectPermission($permission) || $this->hasPermissionViaRole($permission);
        }

        if (is_string($permissions) && str_contains($permissions, '|')) {
            $permissions = explode('|', $permissions);
        }

        if (is_array($permissions) || $permissions instanceof Collection) {
            foreach (Arr::wrap($permissions) as $permission) {
                if ($this->hasPermissionTo($permission, $guardName)) {
                    return true;
                }
            }

            return false;
        }

        if ($permissions instanceof Permission) {
            return $this->hasDirectPermission($permissions) || $this->hasPermissionViaRole($permissions);
        }

        if ($permissions instanceof \BackedEnum) {
            return $this->hasPermissionTo($permissions->value, $guardName);
        }

        return false;
    }

    /**
     * Determine if the entity has wildcard permission for the given permission.
     *
     * @param  string|int|Permission|\BackedEnum  $permission
     * @param  string|null  $guardName
     * @return bool
     */
    protected function hasWildcardPermission($permission, $guardName = null): bool
    {
        $guardName = $guardName ?? $this->getDefaultGuardName();

        if (is_int($permission)) {
            $permission = $this->getPermissionClass()::findById($permission, $guardName);
        }

        if ($permission instanceof Permission) {
            $permission = $permission->name;
        }

        if ($permission instanceof \BackedEnum) {
            $permission = $permission->value;
        }

        if (! is_string($permission)) {
            throw WildcardPermissionInvalidArgument::create();
        }

        return app($this->getWildcardClass(), ['record' => $this])->implies(
            $permission,
            $guardName,
            app(PermissionRegistrar::class)->getWildcardPermissionIndex($this),
        );
    }

    /**
     * An alias to hasPermissionTo(), but avoids throwing an exception.
     *
     * @param  string|int|Permission|\BackedEnum  $permission
     * @param  string|null  $guardName
     */
    public function checkPermissionTo($permission, $guardName = null): bool
    {
        try {
            return $this->hasPermissionTo($permission, $guardName);
        } catch (PermissionDoesNotExist $e) {
            return false;
        }
    }

    /**
     * Determine if the model has any of the given permissions.
     *
     * @param  string|int|array|Permission|Collection|\BackedEnum  ...$permissions
     */
    public function hasAnyPermission(...$permissions): bool
    {
        $permissions = collect($permissions)->flatten();

        foreach ($permissions as $permission) {
            if ($this->checkPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the model has all of the given permissions.
     *
     * @param  string|int|array|Permission|Collection|\BackedEnum  ...$permissions
     */
    public function hasAllPermissions(...$permissions): bool
    {
        $permissions = collect($permissions)->flatten();

        foreach ($permissions as $permission) {
            if (! $this->checkPermissionTo($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if the model has, via roles, the given permission.
     */
    protected function hasPermissionViaRole(Permission $permission): bool
    {
        if (is_a($this, Role::class)) {
            return false;
        }

        return $this->hasRole($permission->roles);
    }

    /**
     * Determine if the model has the given permission.
     *
     * @param  string|int|Permission|\BackedEnum  $permission
     */
    public function hasDirectPermission($permission): bool
    {
        $permissionClass = $this->getPermissionClass();

        if (is_string($permission)) {
            $permission = $permissionClass::findByName($permission, $this->getDefaultGuardName());
        }

        if (is_int($permission)) {
            $permission = $permissionClass::findById($permission, $this->getDefaultGuardName());
        }

        if ($permission instanceof \BackedEnum) {
            $permission = $permissionClass::findByName($permission->value, $this->getDefaultGuardName());
        }

        if (! $permission instanceof Permission) {
            return false;
        }

        return $this->permissions->contains(fn ($p) => $p->getKey() === $permission->getKey());
    }

    /**
     * Return all the stored permissions.
     */
    public function getAllPermissions(): Collection
    {
        /** @var Collection $permissions */
        $permissions = $this->permissions;

        if (! is_a($this, Permission::class)) {
            $permissions = $permissions->merge($this->getPermissionsViaRoles());
        }

        return $permissions->sort()->values();
    }

    /**
     * Grant the given permission(s) to a role.
     *
     * @param  string|int|array|Permission|Collection|\BackedEnum  $permissions
     * @return $this
     */
    public function givePermissionTo(...$permissions)
    {
        $permissions = $this->collectPermissions($permissions);

        $model = $this->getModel();
        $tenantPivot = app(PermissionRegistrar::class)->tenants && ! is_a($this, Role::class) ?
            [app(PermissionRegistrar::class)->tenantsKey => getPermissionsTenantId()] : [];

        if ($model->exists) {
            $currentPermissions = $this->permissions->map(fn ($permission) => $permission->getKey())->toArray();

            $this->permissions()->attach(array_diff($permissions, $currentPermissions), $tenantPivot);
            $model->unsetRelation('permissions');
        } else {
            $class = \get_class($model);
            $saved = false;

            $class::saved(
                function ($object) use ($permissions, $model, $tenantPivot, &$saved) {
                    if ($saved || $model->getKey() != $object->getKey()) {
                        return;
                    }
                    $model->permissions()->attach($permissions, $tenantPivot);
                    $model->unsetRelation('permissions');
                    $saved = true;
                }
            );
        }

        if (is_a($this, Role::class)) {
            $this->forgetCachedPermissions();
        }

        if (config('shield.events_enabled')) {
            event(new PermissionAttached($this->getModel(), $permissions));
        }

        $this->forgetWildcardPermissionIndex();

        return $this;
    }

    public function forgetWildcardPermissionIndex(): void
    {
        app(PermissionRegistrar::class)->forgetWildcardPermissionIndex(
            is_a($this, Role::class) ? null : $this,
        );
    }

    /**
     * Remove all current permissions and set the given ones.
     *
     * @param  string|int|array|Permission|Collection|\BackedEnum  $permissions
     * @return $this
     */
    public function syncPermissions(...$permissions)
    {
        if ($this->getModel()->exists) {
            $this->collectPermissions($permissions);
            $this->permissions()->detach();
            $this->setRelation('permissions', collect());
        }

        return $this->givePermissionTo($permissions);
    }

    /**
     * Revoke the given permission.
     *
     * @param  string|int|array|Permission|Collection|\BackedEnum  $permissions
     * @return $this
     */
    public function revokePermissionTo(...$permissions)
    {
        $permissions = $this->collectPermissions($permissions);

        $this->permissions()->detach($permissions);
        $this->unsetRelation('permissions');

        if (is_a($this, Role::class)) {
            $this->forgetCachedPermissions();
        }

        if (config('shield.events_enabled')) {
            event(new PermissionDetached($this->getModel(), $permissions));
        }

        $this->forgetWildcardPermissionIndex();

        return $this;
    }

    /**
     * Return all permissions via roles.
     */
    public function getPermissionsViaRoles(): Collection
    {
        return $this->loadMissing('roles', 'roles.permissions')
            ->roles->flatMap(function ($role) {
                return $role->permissions;
            })->sort()->values();
    }

    /**
     * Return all direct permissions.
     */
    public function getDirectPermissions(): Collection
    {
        return $this->permissions;
    }

    /**
     * Return Role objects assigned to this model.
     */
    protected function getStoredPermission($permissions): Permission
    {
        $permissionClass = $this->getPermissionClass();

        if (is_numeric($permissions)) {
            return $permissionClass::findById($permissions, $this->getDefaultGuardName());
        }

        if (is_string($permissions)) {
            return $permissionClass::findByName($permissions, $this->getDefaultGuardName());
        }

        if (is_array($permissions)) {
            return $permissionClass::findByName($permissions['name'], $permissions['guard_name'] ?? $this->getDefaultGuardName());
        }

        return $permissions;
    }

    /**
     * Returns array of permissions ids
     *
     * @param  string|int|array|Permission|Collection|\BackedEnum  $permissions
     */
    private function collectPermissions(...$permissions): array
    {
        return collect($permissions)
            ->flatten()
            ->reduce(function ($array, $permission) {
                if (empty($permission)) {
                    return $array;
                }

                $permission = $this->getStoredPermission($permission);
                if (! $permission instanceof Permission) {
                    return $array;
                }

                if (! in_array($permission->getKey(), $array)) {
                    $this->ensureModelSharesGuard($permission);
                    $array[] = $permission->getKey();
                }

                return $array;
            }, []);
    }

    /**
     * @throws GuardDoesNotMatch
     */
    protected function ensureModelSharesGuard($roleOrPermission)
    {
        if (! $this->getGuardNames()->contains($roleOrPermission->guard_name)) {
            throw GuardDoesNotMatch::create($roleOrPermission->guard_name, $this->getGuardNames());
        }
    }

    protected function getGuardNames(): Collection
    {
        return Guard::getNames($this);
    }

    protected function getDefaultGuardName(): string
    {
        return Guard::getDefaultName($this);
    }

    /**
     * Forget the cached permissions.
     */
    public function forgetCachedPermissions()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Check if the model has All of the requested Direct permissions.
     *
     * @param  string|int|array|Permission|Collection|\BackedEnum  ...$permissions
     */
    public function hasAllDirectPermissions(...$permissions): bool
    {
        $permissions = collect($permissions)->flatten();

        foreach ($permissions as $permission) {
            if (! $this->hasDirectPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the model has Any of the requested Direct permissions.
     *
     * @param  string|int|array|Permission|Collection|\BackedEnum  ...$permissions
     */
    public function hasAnyDirectPermission(...$permissions): bool
    {
        $permissions = collect($permissions)->flatten();

        foreach ($permissions as $permission) {
            if ($this->hasDirectPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}
