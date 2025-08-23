<?php

namespace Litepie\Shield;

use Litepie\Shield\Contracts\Wildcard;

class WildcardPermission implements Wildcard
{
    protected string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Check if the wildcard pattern implies the permission.
     */
    public function implies($permission, $guardName, $index): bool
    {
        // Direct match
        if ($this->name === $permission) {
            return true;
        }

        // If no wildcard character, no match
        if (strpos($this->name, '*') === false) {
            return false;
        }

        // Convert wildcard to regex pattern
        $pattern = str_replace(
            ['*', '.'],
            ['.*', '\.'],
            $this->name
        );

        return (bool) preg_match('/^' . $pattern . '$/i', $permission);
    }

    /**
     * Check if the permission name contains wildcard characters.
     */
    public static function containsWildcard(string $permission): bool
    {
        return strpos($permission, '*') !== false;
    }

    /**
     * Get all permissions that match the wildcard pattern.
     */
    public static function getMatchingPermissions(string $wildcard, array $permissions): array
    {
        return array_filter($permissions, function ($permission) use ($wildcard) {
            $wildcardInstance = new static($wildcard);
            return $wildcardInstance->implies($permission, null, []);
        });
    }
}
