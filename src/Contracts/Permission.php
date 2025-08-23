<?php

namespace Litepie\Shield\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

interface Permission
{
    /**
     * A permission can be applied to roles.
     */
    public function roles(): BelongsToMany;

    /**
     * Find a permission by its name and guard name.
     *
     * @param  string  $name
     * @param  string|null  $guardName
     * @return \Litepie\Shield\Contracts\Permission
     */
    public static function findByName(string $name, $guardName = null): self;

    /**
     * Find a permission by its name and guard name or throw an exception.
     *
     * @param  string  $name
     * @param  string|null  $guardName
     * @return \Litepie\Shield\Contracts\Permission
     */
    public static function findByNameOrFail(string $name, $guardName = null): self;

    /**
     * Find or create a permission by its name and guard name.
     *
     * @param  string  $name
     * @param  string|null  $guardName
     * @return \Litepie\Shield\Contracts\Permission
     */
    public static function findOrCreate(string $name, $guardName = null): self;
}
