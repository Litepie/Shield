<?php

namespace Litepie\Shield\Exceptions;

use InvalidArgumentException;

class PermissionAlreadyExists extends InvalidArgumentException
{
    public static function create(string $permissionName, string $guardName): self
    {
        return new static("A permission `{$permissionName}` already exists for guard `{$guardName}`.");
    }
}
