<?php

namespace Litepie\Shield;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Litepie\Shield\Contracts\Permission;
use Litepie\Shield\Contracts\PermissionsTenantResolver;
use Litepie\Shield\Contracts\Role;

class PermissionRegistrar
{
    protected Repository $cache;

    protected CacheManager $cacheManager;

    protected string $permissionClass;

    protected string $roleClass;

    /** @var Collection|array|null */
    protected $permissions;

    public string $pivotRole;

    public string $pivotPermission;

    /** @var \DateInterval|int */
    public $cacheExpirationTime;

    public bool $tenants;

    protected PermissionsTenantResolver $tenantResolver;

    public string $tenantsKey;

    public string $cacheKey;

    private array $cachedRoles = [];

    private array $alias = [];

    private array $except = [];

    private array $wildcardPermissionsIndex = [];

    /**
     * PermissionRegistrar constructor.
     */
    public function __construct(CacheManager $cacheManager)
    {
        $this->cacheManager = $cacheManager;
        $this->initializeCache();

        $this->permissionClass = config('shield.models.permission');
        $this->roleClass = config('shield.models.role');

        $this->cacheKey = config('shield.cache.key');
        $this->cacheExpirationTime = config('shield.cache.expiration_time');
        $this->tenants = config('shield.tenants', false);
        $this->tenantsKey = config('shield.column_names.tenant_foreign_key');

        $this->pivotRole = config('shield.column_names.role_pivot_key') ?: 'role_id';
        $this->pivotPermission = config('shield.column_names.permission_pivot_key') ?: 'permission_id';

        $this->tenantResolver = app(config('shield.tenant_resolver'));
    }

    protected function initializeCache(): self
    {
        $cacheDriver = config('shield.cache.store');

        if (is_null($cacheDriver)) {
            $cacheDriver = config('cache.default');
        }

        $this->cache = $this->cacheManager->store($cacheDriver);

        return $this;
    }

    /**
     * Register the permission check method on the gate.
     * We resolve the Gate fresh here, for when the gate is registered as a singleton.
     */
    public function registerPermissions(Gate $gate): bool
    {
        try {
            $gate->before(function (Authorizable $user, string $ability, $arguments = []) {
                if (method_exists($user, 'checkPermissionTo')) {
                    return $user->checkPermissionTo($ability, $this->getDefaultGuardName()) ?: null;
                }
            });

            return true;
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Flush the cache.
     */
    public function forgetCachedPermissions(): void
    {
        $this->permissions = null;
        $this->cache->forget($this->cacheKey);
        $this->clearPermissionsCollection();
    }

    /**
     * Clear the cache.
     */
    public function clearPermissionsCollection(): void
    {
        $this->permissions = null;
        $this->cachedRoles = [];
        $this->alias = [];
        $this->except = [];
        $this->wildcardPermissionsIndex = [];
    }

    /**
     * Load permissions from cache
     * And turns permissions array into a \Illuminate\Database\Eloquent\Collection
     */
    private function loadPermissions(): void
    {
        if ($this->permissions) {
            return;
        }

        $this->permissions = $this->cache->remember(
            $this->cacheKey, $this->cacheExpirationTime, fn () => $this->getSerializedPermissionsForCache()
        );

        $this->alias = $this->permissions['alias'];

        $this->hydrateRolesCache();

        $this->permissions = $this->getHydratedPermissionCollection();

        $this->cachedRoles = $this->alias = $this->except = [];
    }

    /**
     * Get the permissions based on the passed params.
     */
    public function getPermissions(array $params = [], bool $onlyOne = false): Collection
    {
        $this->loadPermissions();

        $method = $onlyOne ? 'first' : 'filter';

        $permissions = $this->permissions->{$method}(static function ($permission) use ($params) {
            foreach ($params as $attr => $value) {
                if ($permission->getAttribute($attr) != $value) {
                    return false;
                }
            }

            return true;
        });

        if ($onlyOne) {
            $permissions = new Collection($permissions ? [$permissions] : []);
        }

        return $permissions;
    }

    /**
     * Get an instance of the permission class.
     */
    public function getPermissionClass(): string
    {
        return $this->permissionClass;
    }

    /**
     * Get an instance of the role class.
     */
    public function getRoleClass(): string
    {
        return $this->roleClass;
    }

    /**
     * Get the cache store instance.
     */
    public function getCacheStore(): Store
    {
        return $this->cache->getStore();
    }

    /**
     * Get the default guard name.
     */
    public function getDefaultGuardName(): string
    {
        return config('auth.defaults.guard');
    }

    /**
     * Set the permissions tenant id.
     */
    public function setPermissionsTenantId(?int $tenantId): void
    {
        $this->tenantResolver->setPermissionsTenantId($tenantId);
    }

    /**
     * Get the permissions tenant id.
     */
    public function getPermissionsTenantId(): ?int
    {
        return $this->tenantResolver->getPermissionsTenantId();
    }

    /**
     * Get the wildcard permissions index.
     */
    public function getWildcardPermissionIndex($model): array
    {
        $cacheKey = 'wildcard_permission_index_' . get_class($model) . '_' . ($model->getKey() ?? 'null');

        if (! isset($this->wildcardPermissionsIndex[$cacheKey])) {
            $this->buildWildcardPermissionIndex($model, $cacheKey);
        }

        return $this->wildcardPermissionsIndex[$cacheKey];
    }

    /**
     * Forget the wildcard permissions index.
     */
    public function forgetWildcardPermissionIndex($model): void
    {
        $cacheKey = 'wildcard_permission_index_' . get_class($model) . '_' . ($model->getKey() ?? 'null');
        unset($this->wildcardPermissionsIndex[$cacheKey]);
    }

    /**
     * Build the wildcard permissions index.
     */
    protected function buildWildcardPermissionIndex($model, string $cacheKey): void
    {
        $this->wildcardPermissionsIndex[$cacheKey] = [];

        if (! config('shield.enable_wildcard_permission')) {
            return;
        }

        $permissions = $model->getAllPermissions()->pluck('name');

        foreach ($permissions as $permission) {
            if (str_contains($permission, '*')) {
                $this->wildcardPermissionsIndex[$cacheKey][] = $permission;
            }
        }
    }

    /**
     * Get serialized permissions for cache.
     */
    protected function getSerializedPermissionsForCache(): array
    {
        return [
            'permissions' => $this->permissionClass::with('roles')->get()->keyBy('id')->map(function ($permission) {
                return $this->serializePermissionForCache($permission);
            })->toArray(),
            'alias' => [],
        ];
    }

    /**
     * Serialize permission for cache.
     */
    protected function serializePermissionForCache($permission): array
    {
        return [
            'id' => $permission->id,
            'name' => $permission->name,
            'guard_name' => $permission->guard_name,
            'roles' => $permission->roles->map(function ($role) {
                return $this->serializeRoleForCache($role);
            })->toArray(),
        ];
    }

    /**
     * Serialize role for cache.
     */
    protected function serializeRoleForCache($role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
        ];
    }

    /**
     * Hydrate roles cache.
     */
    protected function hydrateRolesCache(): void
    {
        array_walk($this->permissions['permissions'], function (&$permission) {
            $permission['roles'] = array_map(function ($role) {
                $roleClass = $this->getRoleClass();
                if (! isset($this->cachedRoles[$role['id']])) {
                    $this->cachedRoles[$role['id']] = (new $roleClass())->newFromBuilder($role);
                }

                return $this->cachedRoles[$role['id']];
            }, $permission['roles']);
        });
    }

    /**
     * Get hydrated permission collection.
     */
    protected function getHydratedPermissionCollection(): Collection
    {
        $permissionClass = $this->getPermissionClass();

        return new Collection(array_map(function ($permission) use ($permissionClass) {
            $roles = $permission['roles'];
            unset($permission['roles']);
            $model = (new $permissionClass())->newFromBuilder($permission);
            $model->setRelation('roles', new Collection($roles));

            return $model;
        }, $this->permissions['permissions']));
    }
}
