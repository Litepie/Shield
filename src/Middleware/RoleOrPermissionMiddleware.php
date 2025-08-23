<?php

namespace Litepie\Shield\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Litepie\Shield\Exceptions\UnauthorizedException;
use Litepie\Shield\Guard;

class RoleOrPermissionMiddleware
{
    public function handle($request, Closure $next, $roleOrPermission, $guard = null)
    {
        $authGuard = Auth::guard($guard);

        $user = $authGuard->user();

        // For machine-to-machine Passport clients
        if (! $user && $request->bearerToken() && config('shield.use_passport_client_credentials')) {
            $user = Guard::getPassportClient($guard);
        }

        if (! $user) {
            throw UnauthorizedException::notLoggedIn();
        }

        if (! method_exists($user, 'hasAnyRole') || ! method_exists($user, 'hasAnyPermission')) {
            throw UnauthorizedException::missingTraitHasRoles($user);
        }

        $rolesOrPermissions = explode('|', self::parseRolesOrPermissionsToString($roleOrPermission));

        if (! $user->hasAnyRole($rolesOrPermissions) && ! $user->hasAnyPermission($rolesOrPermissions)) {
            throw UnauthorizedException::forRolesOrPermissions($rolesOrPermissions);
        }

        return $next($request);
    }

    /**
     * Parse roles or permissions into a string for middleware usage.
     */
    public static function parseRolesOrPermissionsToString($roleOrPermission): string
    {
        if (is_array($roleOrPermission)) {
            return implode('|', $roleOrPermission);
        }

        if ($roleOrPermission instanceof \BackedEnum) {
            return $roleOrPermission->value;
        }

        return (string) $roleOrPermission;
    }

    /**
     * Generate a static middleware using method.
     */
    public static function using($roleOrPermission, $guard = null): string
    {
        $args = is_null($guard) ? $roleOrPermission : $roleOrPermission.','.$guard;

        return static::class.':'.$args;
    }
}
