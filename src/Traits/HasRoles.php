<?php

namespace Litepie\Shield\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Litepie\Shield\Contracts\Permission;
use Litepie\Shield\Contracts\Role;
use Litepie\Shield\Events\RoleAttached;
use Litepie\Shield\Events\RoleDetached;
use Litepie\Shield\PermissionRegistrar;

trait HasRoles
{
    use HasPermissions;

    private ?string $roleClass = null;

    public static function bootHasRoles()
    {
        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $tenants = app(PermissionRegistrar::class)->tenants;
            app(PermissionRegistrar::class)->tenants = false;
            $model->roles()->detach();
            if (is_a($model, Permission::class)) {
                $model->users()->detach();
            }
            app(PermissionRegistrar::class)->tenants = $tenants;
        });
    }

    public function getRoleClass(): string
    {
        if (! $this->roleClass) {
            $this->roleClass = app(PermissionRegistrar::class)->getRoleClass();
        }

        return $this->roleClass;
    }

    /**
     * A model may have multiple roles.
     */
    public function roles(): BelongsToMany
    {
        $relation = $this->morphToMany(
            config('shield.models.role'),
            'model',
            config('shield.table_names.model_has_roles'),
            config('shield.column_names.model_morph_key'),
            app(PermissionRegistrar::class)->pivotRole
        );

        if (! app(PermissionRegistrar::class)->tenants) {
            return $relation;
        }

        $tenantsKey = app(PermissionRegistrar::class)->tenantsKey;
        $relation->withPivot($tenantsKey);

        return $relation->wherePivot($tenantsKey, getPermissionsTenantId());
    }

    /**
     * Scope the model query to certain roles only.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string|int|array|\Litepie\Shield\Contracts\Role|\Illuminate\Support\Collection  $roles
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRole(Builder $query, $roles): Builder
    {
        if ($roles instanceof Collection) {
            $roles = $roles->all();
        }

        if (! is_array($roles)) {
            $roles = [$roles];
        }

        $roles = array_map(function ($role) {
            if ($role instanceof Role) {
                return $role;
            }

            $method = is_string($role) ? 'findByName' : 'findById';

            return $this->getRoleClass()::$method($role, $this->getDefaultGuardName());
        }, $roles);

        return $query->whereHas('roles', function (Builder $subQuery) use ($roles) {
            $roleKeyName = (new ($this->getRoleClass()))->getKeyName();
            $subQuery->whereIn(config('shield.table_names.roles').'.'.$roleKeyName, \Illuminate\Support\Arr::flatten(\Illuminate\Support\Arr::pluck($roles, $roleKeyName)));
        });
    }

    /**
     * Scope the model query to exclude certain roles.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string|int|array|\Litepie\Shield\Contracts\Role|\Illuminate\Support\Collection  $roles
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithoutRole(Builder $query, $roles): Builder
    {
        if ($roles instanceof Collection) {
            $roles = $roles->all();
        }

        if (! is_array($roles)) {
            $roles = [$roles];
        }

        $roles = array_map(function ($role) {
            if ($role instanceof Role) {
                return $role;
            }

            $method = is_string($role) ? 'findByName' : 'findById';

            return $this->getRoleClass()::$method($role, $this->getDefaultGuardName());
        }, $roles);

        return $query->whereDoesntHave('roles', function (Builder $subQuery) use ($roles) {
            $roleKeyName = (new ($this->getRoleClass()))->getKeyName();
            $subQuery->whereIn(config('shield.table_names.roles').'.'.$roleKeyName, \Illuminate\Support\Arr::flatten(\Illuminate\Support\Arr::pluck($roles, $roleKeyName)));
        });
    }

    /**
     * Assign the given role to the model.
     *
     * @param  array|string|int|\Litepie\Shield\Contracts\Role|\Illuminate\Support\Collection|\BackedEnum  ...$roles
     * @return $this
     */
    public function assignRole(...$roles)
    {
        $roles = $this->collectRoles($roles);

        $model = $this->getModel();
        if ($model->exists) {
            $this->roles()->attach($roles);
            $model->unsetRelation('roles');
        } else {
            $class = \get_class($model);

            $class::saved(
                function ($object) use ($roles, $model) {
                    static $modelLastFiredOn;
                    if ($modelLastFiredOn !== null && $modelLastFiredOn === $model) {
                        return;
                    }
                    $object->roles()->attach($roles);
                    $object->unsetRelation('roles');
                    $modelLastFiredOn = $object;
                }
            );
        }

        $this->forgetCachedPermissions();

        if (config('shield.events_enabled')) {
            event(new RoleAttached($this->getModel(), $roles));
        }

        return $this;
    }

    /**
     * Revoke the given role from the model.
     *
     * @param  string|int|\Litepie\Shield\Contracts\Role  $role
     * @return $this
     */
    public function removeRole($role)
    {
        $this->roles()->detach($this->getStoredRole($role));

        $this->unsetRelation('roles');
        $this->forgetCachedPermissions();

        if (config('shield.events_enabled')) {
            event(new RoleDetached($this->getModel(), [$role]));
        }

        return $this;
    }

    /**
     * Remove all current roles and set the given ones.
     *
     * @param  array|string|int|\Litepie\Shield\Contracts\Role|\Illuminate\Support\Collection|\BackedEnum  ...$roles
     * @return $this
     */
    public function syncRoles(...$roles)
    {
        $this->roles()->detach();
        $this->setRelation('roles', collect());

        return $this->assignRole($roles);
    }

    /**
     * Determine if the model has (one of) the given role(s).
     *
     * @param  string|int|array|\Litepie\Shield\Contracts\Role|\Illuminate\Support\Collection|\BackedEnum  $roles
     * @param  string|null  $guard
     * @return bool
     */
    public function hasRole($roles, string $guard = null): bool
    {
        $this->loadMissing('roles');

        if (is_string($roles) && str_contains($roles, '|')) {
            $roles = $this->convertPipeToArray($roles);
        }

        if (is_string($roles)) {
            return $guard
                ? $this->roles->where('guard_name', $guard)->contains('name', $roles)
                : $this->roles->contains('name', $roles);
        }

        if (is_int($roles)) {
            $key = (new ($this->getRoleClass()))->getKeyName();

            return $guard
                ? $this->roles->where('guard_name', $guard)->contains($key, $roles)
                : $this->roles->contains($key, $roles);
        }

        if ($roles instanceof Role) {
            return $this->roles->contains(fn ($role) => 
                $role->getKey() === $roles->getKey() &&
                ($guard ? $role->guard_name === $guard : true)
            );
        }

        if ($roles instanceof \BackedEnum) {
            return $this->hasRole($roles->value, $guard);
        }

        if (is_array($roles)) {
            foreach ($roles as $role) {
                if ($this->hasRole($role, $guard)) {
                    return true;
                }
            }

            return false;
        }

        if ($roles instanceof Collection) {
            $roles = $roles->all();

            foreach ($roles as $role) {
                if ($this->hasRole($role, $guard)) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }

    /**
     * Determine if the model has any of the given role(s).
     *
     * Alias to hasRole() but without Guard controls
     *
     * @param  string|int|array|\Litepie\Shield\Contracts\Role|\Illuminate\Support\Collection|\BackedEnum  ...$roles
     * @return bool
     */
    public function hasAnyRole(...$roles): bool
    {
        return $this->hasRole($roles);
    }

    /**
     * Determine if the model has all of the given role(s).
     *
     * @param  string|int|array|\Litepie\Shield\Contracts\Role|\Illuminate\Support\Collection|\BackedEnum  $roles
     * @param  string|null  $guard
     * @return bool
     */
    public function hasAllRoles($roles, string $guard = null): bool
    {
        $this->loadMissing('roles');

        if (is_string($roles) && str_contains($roles, '|')) {
            $roles = $this->convertPipeToArray($roles);
        }

        if (is_string($roles)) {
            return $guard
                ? $this->roles->where('guard_name', $guard)->contains('name', $roles)
                : $this->roles->contains('name', $roles);
        }

        if ($roles instanceof Role) {
            return $this->hasRole($roles, $guard);
        }

        if ($roles instanceof \BackedEnum) {
            return $this->hasAllRoles($roles->value, $guard);
        }

        $roles = collect($roles)->map(function ($role) {
            return $role instanceof Role ? $role->name : $role;
        });

        return $roles->intersect(
            $guard ? $this->roles->where('guard_name', $guard)->pluck('name') : $this->roles->pluck('name')
        )->count() === $roles->count();
    }

    /**
     * Determine if the model has exactly all of the given role(s) and no more.
     *
     * @param  string|int|array|\Litepie\Shield\Contracts\Role|\Illuminate\Support\Collection|\BackedEnum  $roles
     * @param  string|null  $guard
     * @return bool
     */
    public function hasExactRoles($roles, string $guard = null): bool
    {
        $this->loadMissing('roles');

        if (is_string($roles) && str_contains($roles, '|')) {
            $roles = $this->convertPipeToArray($roles);
        }

        if (is_string($roles)) {
            $roles = [$roles];
        }

        if ($roles instanceof Role) {
            $roles = [$roles->name];
        }

        if ($roles instanceof \BackedEnum) {
            $roles = [$roles->value];
        }

        $roles = collect($roles)->map(function ($role) {
            return $role instanceof Role ? $role->name : $role;
        });

        $currentRoles = $guard ? $this->roles->where('guard_name', $guard)->pluck('name') : $this->roles->pluck('name');

        return $roles->sort()->values()->toArray() === $currentRoles->sort()->values()->toArray();
    }

    /**
     * Return Role objects assigned to this model.
     */
    protected function getStoredRole($roles): Role
    {
        $roleClass = $this->getRoleClass();

        if (is_numeric($roles)) {
            return $roleClass::findById($roles, $this->getDefaultGuardName());
        }

        if (is_string($roles)) {
            return $roleClass::findByName($roles, $this->getDefaultGuardName());
        }

        if (is_array($roles)) {
            return $roleClass::findByName($roles['name'], $roles['guard_name'] ?? $this->getDefaultGuardName());
        }

        return $roles;
    }

    /**
     * Return a collection of role names associated with this model.
     */
    public function getRoleNames(): Collection
    {
        $this->loadMissing('roles');

        return $this->roles->pluck('name');
    }

    protected function convertPipeToArray(string $pipeString): array
    {
        $pipeString = trim($pipeString);

        if (strlen($pipeString) <= 2) {
            return $pipeString;
        }

        $quoteCharacter = substr($pipeString, 0, 1);
        $endCharacter = substr($quoteCharacter, -1, 1);

        if ($quoteCharacter !== $endCharacter) {
            return explode('|', $pipeString);
        }

        if (! in_array($quoteCharacter, ["'", '"'])) {
            return explode('|', $pipeString);
        }

        return explode('|', trim($pipeString, $quoteCharacter));
    }

    /**
     * Returns array of role ids
     *
     * @param  string|int|array|Role|Collection|\BackedEnum  $roles
     */
    private function collectRoles(...$roles): array
    {
        return collect($roles)
            ->flatten()
            ->reduce(function ($array, $role) {
                if (empty($role)) {
                    return $array;
                }

                $role = $this->getStoredRole($role);
                if (! $role instanceof Role) {
                    return $array;
                }

                if (! in_array($role->getKey(), $array)) {
                    $this->ensureModelSharesGuard($role);
                    $array[] = $role->getKey();
                }

                return $array;
            }, []);
    }
}
