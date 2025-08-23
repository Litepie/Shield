<?php

namespace Litepie\Shield\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Litepie\Shield\Exceptions\UnauthorizedException;
use Litepie\Shield\Guard;

class PermissionMiddleware
{
    public function handle($request, Closure $next, $permission, $guard = null)
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

        if (! method_exists($user, 'hasAnyPermission')) {
            throw UnauthorizedException::missingTraitHasRoles($user);
        }

        $permissions = explode('|', self::parsePermissionsToString($permission));

        if (! $user->canAny($permissions)) {
            throw UnauthorizedException::forPermissions($permissions);
        }

        return $next($request);
    }

    /**
     * Parse permissions into a string for middleware usage.
     */
    public static function parsePermissionsToString($permission): string
    {
        if (is_array($permission)) {
            return implode('|', $permission);
        }

        if ($permission instanceof \BackedEnum) {
            return $permission->value;
        }

        return (string) $permission;
    }

    /**
     * Generate a static middleware using method.
     */
    public static function using($permission, $guard = null): string
    {
        $args = is_null($guard) ? $permission : $permission.','.$guard;

        return static::class.':'.$args;
    }
}
