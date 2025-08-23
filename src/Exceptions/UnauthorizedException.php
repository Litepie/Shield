<?php

namespace Litepie\Shield\Exceptions;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UnauthorizedException extends HttpException
{
    private array $requiredRoles = [];

    private array $requiredPermissions = [];

    public static function forRoles(array $roles): self
    {
        $message = 'User does not have the right roles.';

        if (config('shield.display_role_in_exception')) {
            $message = 'User does not have any of the necessary access roles.';
            $message .= ' Necessary roles are '.implode(', ', $roles);
        }

        $exception = new static(Response::HTTP_FORBIDDEN, $message, null, []);
        $exception->requiredRoles = $roles;

        return $exception;
    }

    public static function forPermissions(array $permissions): self
    {
        $message = 'User does not have the right permissions.';

        if (config('shield.display_permission_in_exception')) {
            $message = 'User does not have any of the necessary access permissions.';
            $message .= ' Necessary permissions are '.implode(', ', $permissions);
        }

        $exception = new static(Response::HTTP_FORBIDDEN, $message, null, []);
        $exception->requiredPermissions = $permissions;

        return $exception;
    }

    public static function forRolesOrPermissions(array $rolesOrPermissions): self
    {
        $message = 'User does not have any of the necessary access rights.';

        $exception = new static(Response::HTTP_FORBIDDEN, $message, null, []);
        $exception->requiredRoles = $rolesOrPermissions;
        $exception->requiredPermissions = $rolesOrPermissions;

        return $exception;
    }

    public static function notLoggedIn(): self
    {
        return new static(Response::HTTP_FORBIDDEN, 'User is not logged in.', null, []);
    }

    public static function missingTraitHasRoles($user): self
    {
        $class = get_class($user);

        return new static(Response::HTTP_FORBIDDEN, "User model [$class] does not have the HasRoles trait.", null, []);
    }

    public function getRequiredRoles(): array
    {
        return $this->requiredRoles;
    }

    public function getRequiredPermissions(): array
    {
        return $this->requiredPermissions;
    }
}
