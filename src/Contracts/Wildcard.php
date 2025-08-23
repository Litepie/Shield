<?php

namespace Litepie\Shield\Contracts;

interface Wildcard
{
    /**
     * Check if a wildcard permission implies the given permission.
     *
     * @param  string  $permission
     * @param  string|null  $guardName
     * @param  array  $index
     * @return bool
     */
    public function implies($permission, $guardName, $index): bool;
}
