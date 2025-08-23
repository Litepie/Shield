<?php

namespace Litepie\Shield\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

interface Role
{
    /**
     * A role may be given various permissions.
     */
    public function permissions(): BelongsToMany;

    /**
     * Find a role by its name and guard name.
     *
     * @param  string  $name
     * @param  string|null  $guardName
     * @return \Litepie\Shield\Contracts\Role
     */
    public static function findByName(string $name, $guardName = null): self;

    /**
     * Find a role by its name and guard name or throw an exception.
     *
     * @param  string  $name
     * @param  string|null  $guardName
     * @return \Litepie\Shield\Contracts\Role
     */
    public static function findByNameOrFail(string $name, $guardName = null): self;

    /**
     * Find or create a role by its name and guard name.
     *
     * @param  string  $name
     * @param  string|null  $guardName
     * @return \Litepie\Shield\Contracts\Role
     */
    public static function findOrCreate(string $name, $guardName = null): self;

    /**
     * Determine if the user may perform the given permission.
     *
     * @param  string|\Litepie\Shield\Contracts\Permission  $permission
     * @return bool
     */
    public function hasPermissionTo($permission): bool;
}
