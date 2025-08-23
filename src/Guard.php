<?php

namespace Litepie\Shield;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravel\Passport\HasApiTokens;

class Guard
{
    /**
     * Return collection of guard names defined in auth config.
     */
    public static function getNames($model): Collection
    {
        $guardName = static::getDefaultName($model);

        return collect(config('auth.guards'))
            ->reject(fn ($guard) => ! isset($guard['provider']))
            ->filter(function ($guard, $key) use ($model) {
                if (! isset(config('auth.providers.'.$guard['provider'])['model'])) {
                    return false;
                }
                $guardModel = config('auth.providers.'.$guard['provider'])['model'];
                return $model instanceof $guardModel || $model->getMorphClass() === $guardModel;
            })
            ->keys()
            ->push($guardName);
    }

    /**
     * Lookup a guard name relevant for the $model or $class.
     */
    public static function getDefaultName($class): string
    {
        $default = config('auth.defaults.guard');

        if ($class instanceof Model) {
            if (isset($class->guard_name)) {
                return $class->guard_name;
            }
            if (method_exists($class, 'guardName')) {
                return $class->guardName();
            }
        }

        if (is_string($class)) {
            if (defined($class.'::GUARD_NAME')) {
                return $class::GUARD_NAME;
            }
            if (property_exists($class, 'guard_name')) {
                return (new $class())->guard_name;
            }
            if (method_exists($class, 'guardName')) {
                return (new $class())->guardName();
            }
        }

        return $default;
    }

    /**
     * Get passport client if available.
     */
    public static function getPassportClient($guardName = null)
    {
        if (! class_exists('\Laravel\Passport\Passport')) {
            return null;
        }

        $guards = $guardName ? [$guardName] : array_keys(config('auth.guards'));

        foreach ($guards as $guard) {
            if (request()->user($guard)) {
                return request()->user($guard);
            }
        }

        $bearerToken = request()->bearerToken();
        $tokenRepository = app(\Laravel\Passport\TokenRepository::class);
        $token = $tokenRepository->findValidToken(
            hash('sha256', $bearerToken)
        );

        if ($token && $token->client && ! $token->user_id) {
            $clientModel = config('auth.guards.api.provider') ?
                config('auth.providers.'.config('auth.guards.api.provider').'.model') :
                config('auth.providers.users.model');

            if (method_exists($clientModel, 'clients')) {
                return $token->client;
            }
        }

        return null;
    }
}
