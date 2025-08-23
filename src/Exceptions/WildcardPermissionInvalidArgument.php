<?php

namespace Litepie\Shield\Exceptions;

use InvalidArgumentException;

class WildcardPermissionInvalidArgument extends InvalidArgumentException
{
    public static function create(): self
    {
        return new static('Wildcard permissions can only check string permissions.');
    }
}
