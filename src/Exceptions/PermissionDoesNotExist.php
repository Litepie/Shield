<?php

namespace Litepie\Shield\Exceptions;

use InvalidArgumentException;

class PermissionDoesNotExist extends InvalidArgumentException
{
    public static function create(string $permissionName, string $guardName = ''): self
    {
        return new static("There is no permission named `{$permissionName}` for guard `{$guardName}`.");
    }

    public static function withId(int|string $permissionId, string $guardName = ''): self
    {
        return new static("There is no [permission] with id `{$permissionId}` for guard `{$guardName}`.");
    }
}
