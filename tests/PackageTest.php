<?php

namespace Tests;

/**
 * Simple test to validate the package structure
 */
class PackageTest
{
    public function testBasicStructure()
    {
        // Test that core classes exist
        $this->assertTrue(class_exists(\Litepie\Shield\ShieldServiceProvider::class));
        $this->assertTrue(class_exists(\Litepie\Shield\Models\Permission::class));
        $this->assertTrue(class_exists(\Litepie\Shield\Models\Role::class));
        $this->assertTrue(interface_exists(\Litepie\Shield\Contracts\Permission::class));
        $this->assertTrue(interface_exists(\Litepie\Shield\Contracts\Role::class));
    }

    public function testTraitsExist()
    {
        $this->assertTrue(trait_exists(\Litepie\Shield\Traits\HasPermissions::class));
        $this->assertTrue(trait_exists(\Litepie\Shield\Traits\HasRoles::class));
        $this->assertTrue(trait_exists(\Litepie\Shield\Traits\RefreshesPermissionCache::class));
    }

    public function testMiddlewareExists()
    {
        $this->assertTrue(class_exists(\Litepie\Shield\Middleware\PermissionMiddleware::class));
        $this->assertTrue(class_exists(\Litepie\Shield\Middleware\RoleMiddleware::class));
        $this->assertTrue(class_exists(\Litepie\Shield\Middleware\RoleOrPermissionMiddleware::class));
    }

    public function testExceptionsExist()
    {
        $this->assertTrue(class_exists(\Litepie\Shield\Exceptions\PermissionDoesNotExist::class));
        $this->assertTrue(class_exists(\Litepie\Shield\Exceptions\RoleDoesNotExist::class));
        $this->assertTrue(class_exists(\Litepie\Shield\Exceptions\UnauthorizedException::class));
    }

    public function testCommandsExist()
    {
        $this->assertTrue(class_exists(\Litepie\Shield\Commands\CreatePermission::class));
        $this->assertTrue(class_exists(\Litepie\Shield\Commands\CreateRole::class));
        $this->assertTrue(class_exists(\Litepie\Shield\Commands\CacheReset::class));
    }

    private function assertTrue($condition)
    {
        if (!$condition) {
            throw new \Exception('Assertion failed');
        }
        return true;
    }
}
