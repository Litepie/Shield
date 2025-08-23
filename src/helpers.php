<?php

if (! function_exists('getModelForGuard')) {
    /**
     * Get the model class for the given guard.
     */
    function getModelForGuard(string $guard): string
    {
        return collect(config('auth.guards'))
            ->map(function ($guard) {
                if (! isset($guard['provider'])) {
                    return null;
                }

                return config("auth.providers.{$guard['provider']}.model");
            })
            ->get($guard, config('auth.providers.users.model'));
    }
}

if (! function_exists('setPermissionsTenantId')) {
    /**
     * Set the tenant ID for permission checks.
     */
    function setPermissionsTenantId($tenantId): void
    {
        app(\Litepie\Shield\PermissionRegistrar::class)->setPermissionsTenantId($tenantId);
    }
}

if (! function_exists('getPermissionsTenantId')) {
    /**
     * Get the current tenant ID for permission checks.
     */
    function getPermissionsTenantId()
    {
        return app(\Litepie\Shield\PermissionRegistrar::class)->getPermissionsTenantId();
    }
}
