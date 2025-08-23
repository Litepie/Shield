<?php

namespace Litepie\Shield\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Litepie\Shield\Contracts\Permission as PermissionContract;
use Litepie\Shield\Exceptions\PermissionAlreadyExists;
use Litepie\Shield\Exceptions\PermissionDoesNotExist;
use Litepie\Shield\Guard;
use Litepie\Shield\PermissionRegistrar;
use Litepie\Shield\Traits\HasRoles;
use Litepie\Shield\Traits\RefreshesPermissionCache;

/**
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 */
class Permission extends Model implements PermissionContract
{
    use HasRoles;
    use RefreshesPermissionCache;

    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        $attributes['guard_name'] ??= Guard::getDefaultName(static::class);

        parent::__construct($attributes);

        $this->guarded[] = $this->primaryKey;
        $this->table = config('shield.table_names.permissions') ?: parent::getTable();
    }

    /**
     * @return PermissionContract|Permission
     *
     * @throws PermissionAlreadyExists
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
            throw PermissionAlreadyExists::create($attributes['name'], $attributes['guard_name']);
        }

        return static::query()->create($attributes);
    }

    /**
     * A permission can be applied to roles.
     */
    public function roles(): BelongsToMany
    {
        $relation = $this->belongsToMany(
            config('shield.models.role'),
            config('shield.table_names.role_has_permissions'),
            app(PermissionRegistrar::class)->pivotPermission,
            app(PermissionRegistrar::class)->pivotRole
        );

        if (! app(PermissionRegistrar::class)->tenants) {
            return $this->belongsToMany($related, $table, $foreignPivotKey, $relatedPivotKey);
        }

        return $relation->withPivot(app(PermissionRegistrar::class)->tenantsKey)
            ->wherePivot(app(PermissionRegistrar::class)->tenantsKey, getPermissionsTenantId());
    }

    /**
     * A permission belongs to some users of the model associated with its guard.
     */
    public function users(): BelongsToMany
    {
        return $this->morphedByMany(
            getModelForGuard($this->attributes['guard_name'] ?? config('auth.defaults.guard')),
            'model',
            config('shield.table_names.model_has_permissions'),
            app(PermissionRegistrar::class)->pivotPermission,
            config('shield.column_names.model_morph_key')
        );
    }

    /**
     * Find a permission by its name (and optionally guardName).
     *
     * @param  string  $name
     * @param  string|null  $guardName
     * @return \Litepie\Shield\Contracts\Permission
     *
     * @throws \Litepie\Shield\Exceptions\PermissionDoesNotExist
     */
    public static function findByName(string $name, $guardName = null): PermissionContract
    {
        $guardName = $guardName ?? Guard::getDefaultName(static::class);
        $permission = static::findByParam(['name' => $name, 'guard_name' => $guardName]);
        if (! $permission) {
            throw PermissionDoesNotExist::create($name, $guardName);
        }

        return $permission;
    }

    /**
     * Find a permission by its name and guard name or throw an exception.
     *
     * @param  string  $name
     * @param  string|null  $guardName
     * @return \Litepie\Shield\Contracts\Permission
     */
    public static function findByNameOrFail(string $name, $guardName = null): PermissionContract
    {
        return static::findByName($name, $guardName);
    }

    /**
     * Find or create a permission by its name (and optionally guardName).
     *
     * @param  string  $name
     * @param  string|null  $guardName
     * @return \Litepie\Shield\Contracts\Permission
     */
    public static function findOrCreate(string $name, $guardName = null): PermissionContract
    {
        $guardName = $guardName ?? Guard::getDefaultName(static::class);
        $permission = static::findByParam(['name' => $name, 'guard_name' => $guardName]);

        if (! $permission) {
            return static::query()->create(['name' => $name, 'guard_name' => $guardName] + (app(PermissionRegistrar::class)->tenants ? [app(PermissionRegistrar::class)->tenantsKey => getPermissionsTenantId()] : []));
        }

        return $permission;
    }

    /**
     * Get the current cached permissions.
     */
    protected static function getPermissions(array $params = [], bool $onlyOne = false): Collection
    {
        return app(PermissionRegistrar::class)->getPermissions($params, $onlyOne);
    }

    /**
     * Find a permission by its parameters.
     */
    protected static function findByParam(array $params = []): ?self
    {
        $permissions = static::getPermissions($params, true);

        return $permissions->first();
    }
}
