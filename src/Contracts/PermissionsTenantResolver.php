<?php

namespace Litepie\Shield\Contracts;

interface PermissionsTenantResolver
{
    /**
     * Get the current tenant ID for permission checks.
     */
    public function getPermissionsTenantId(): ?int;

    /**
     * Set the tenant ID for permission checks.
     */
    public function setPermissionsTenantId(?int $tenantId): void;
}
