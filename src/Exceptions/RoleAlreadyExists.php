<?php

namespace Litepie\Shield\Exceptions;

use InvalidArgumentException;

class RoleAlreadyExists extends InvalidArgumentException
{
    public static function create(string $roleName, string $guardName): self
    {
        return new static("A role `{$roleName}` already exists for guard `{$guardName}`.");
    }
}
