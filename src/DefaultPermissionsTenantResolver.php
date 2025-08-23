<?php

namespace Litepie\Shield;

use Litepie\Shield\Contracts\PermissionsTenantResolver;

class DefaultPermissionsTenantResolver implements PermissionsTenantResolver
{
    protected ?int $tenantId = null;

    public function getPermissionsTenantId(): ?int
    {
        return $this->tenantId;
    }

    public function setPermissionsTenantId(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }
}
