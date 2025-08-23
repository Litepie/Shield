<?php

namespace Litepie\Shield\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Litepie\Shield\Exceptions\UnauthorizedException;
use Litepie\Shield\Guard;

class RoleMiddleware
{
    public function handle($request, Closure $next, $role, $guard = null)
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

        if (! method_exists($user, 'hasAnyRole')) {
            throw UnauthorizedException::missingTraitHasRoles($user);
        }

        $roles = explode('|', self::parseRolesToString($role));

        if (! $user->hasAnyRole($roles)) {
            throw UnauthorizedException::forRoles($roles);
        }

        return $next($request);
    }

    /**
     * Parse roles into a string for middleware usage.
     */
    public static function parseRolesToString($role): string
    {
        if (is_array($role)) {
            return implode('|', $role);
        }

        if ($role instanceof \BackedEnum) {
            return $role->value;
        }

        return (string) $role;
    }

    /**
     * Generate a static middleware using method.
     */
    public static function using($role, $guard = null): string
    {
        $args = is_null($guard) ? $role : $role.','.$guard;

        return static::class.':'.$args;
    }
}
