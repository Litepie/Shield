<?php

namespace Litepie\Shield\Exceptions;

use InvalidArgumentException;

class WildcardPermissionNotImplementsContract extends InvalidArgumentException
{
    public static function create(): self
    {
        return new static('Wildcard permission class must implement Litepie\Shield\Contracts\Wildcard contract.');
    }
}
