<?php

namespace Litepie\Shield\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Litepie\Shield\Contracts\Role as RoleContract;
use Litepie\Shield\Exceptions\GuardDoesNotMatch;
use Litepie\Shield\Exceptions\PermissionDoesNotExist;
use Litepie\Shield\Exceptions\RoleAlreadyExists;
use Litepie\Shield\Exceptions\RoleDoesNotExist;
use Litepie\Shield\Guard;
use Litepie\Shield\PermissionRegistrar;
use Litepie\Shield\Traits\HasPermissions;
use Litepie\Shield\Traits\RefreshesPermissionCache;

/**
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 */
class Role extends Model implements RoleContract
{
    use HasPermissions;
    use RefreshesPermissionCache;

    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        $attributes['guard_name'] ??= Guard::getDefaultName(static::class);

        parent::__construct($attributes);

        $this->guarded[] = $this->primaryKey;
        $this->table = config('shield.table_names.roles') ?: parent::getTable();
    }

    /**
     * @return RoleContract|Role
     *
     * @throws RoleAlreadyExists
     */
    public static function create(array $attributes = [])
    {
        $attributes['guard_name'] ??= Guard::getDefaultName(static::class);

        $params = ['name' => $attributes['name'], 'guard_name' => $attributes['guard_name']];
        if (app(PermissionRegistrar::class)->tenants) {
            $tenantsKey = app(PermissionRegistrar::class)->tenantsKey;

            if (array_key_exists($tenantsKey, $attributes)) {
                $params[$tenantsKey] = $attributes[$tenantsKey];
            } else {
                $attributes[$tenantsKey] = getPermissionsTenantId();
            }
        }
        if (static::findByParam($params)) {
            throw RoleAlreadyExists::create($attributes['name'], $attributes['guard_name']);
        }

        return static::query()->create($attributes);
    }

    /**
     * A role may be given various permissions.
     */
    public function permissions(): BelongsToMany
    {
        $relation = $this->belongsToMany(
            config('shield.models.permission'),
            config('shield.table_names.role_has_permissions'),
            app(PermissionRegistrar::class)->pivotRole,
            app(PermissionRegistrar::class)->pivotPermission
        );

        if (! app(PermissionRegistrar::class)->tenants) {
            return $relation;
        }

        return $relation->withPivot(app(PermissionRegistrar::class)->tenantsKey)
            ->wherePivot(app(PermissionRegistrar::class)->tenantsKey, getPermissionsTenantId());
    }

    /**
     * A role belongs to some users of the model associated with its guard.
     */
    public function users(): BelongsToMany
    {
        return $this->morphedByMany(
            getModelForGuard($this->attributes['guard_name'] ?? config('auth.defaults.guard')),
            'model',
            config('shield.table_names.model_has_roles'),
            app(PermissionRegistrar::class)->pivotRole,
            config('shield.column_names.model_morph_key')
        );
    }

    /**
     * Find a role by its name (and optionally guardName).
     *
     * @param  string  $name
     * @param  string|null  $guardName
     * @return \Litepie\Shield\Contracts\Role
     *
     * @throws \Litepie\Shield\Exceptions\RoleDoesNotExist
     */
    public static function findByName(string $name, $guardName = null): RoleContract
    {
        $guardName = $guardName ?? Guard::getDefaultName(static::class);
        $role = static::findByParam(['name' => $name, 'guard_name' => $guardName]);
        if (! $role) {
            throw RoleDoesNotExist::create($name, $guardName);
        }

        return $role;
    }

    /**
     * Find a role by its name and guard name or throw an exception.
     *
     * @param  string  $name
     * @param  string|null  $guardName
     * @return \Litepie\Shield\Contracts\Role
     */
    public static function findByNameOrFail(string $name, $guardName = null): RoleContract
    {
        return static::findByName($name, $guardName);
    }

    /**
     * Find or create a role by its name (and optionally guardName).
     *
     * @param  string  $name
     * @param  string|null  $guardName
     * @return \Litepie\Shield\Contracts\Role
     */
    public static function findOrCreate(string $name, $guardName = null): RoleContract
    {
        $guardName = $guardName ?? Guard::getDefaultName(static::class);
        $role = static::findByParam(['name' => $name, 'guard_name' => $guardName]);

        if (! $role) {
            return static::query()->create(['name' => $name, 'guard_name' => $guardName] + (app(PermissionRegistrar::class)->tenants ? [app(PermissionRegistrar::class)->tenantsKey => getPermissionsTenantId()] : []));
        }

        return $role;
    }

    /**
     * Determine if the user may perform the given permission.
     *
     * @param  string|\Litepie\Shield\Contracts\Permission  $permission
     * @return bool
     */
    public function hasPermissionTo($permission): bool
    {
        if (config('shield.enable_wildcard_permission')) {
            return $this->hasWildcardPermission($permission, $this->getDefaultGuardName());
        }

        return $this->hasDirectPermission($permission);
    }

    /**
     * Get the current cached roles.
     */
    protected static function getRoles(array $params = [], bool $onlyOne = false): \Illuminate\Database\Eloquent\Collection
    {
        return app(PermissionRegistrar::class)->getRoles($params, $onlyOne);
    }

    /**
     * Find a role by its parameters.
     */
    protected static function findByParam(array $params = []): ?self
    {
        $roles = static::getRoles($params, true);

        return $roles->first();
    }
}
