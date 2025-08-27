# Laravel 12 Upgrade Guide for Litepie Shield

## Overview

Litepie Shield has been upgraded to support Laravel 12.x while maintaining backward compatibility with Laravel 10.x and 11.x.

## Requirements

- **PHP**: 8.2 or higher (updated from 8.1)
- **Laravel**: 10.x, 11.x, or 12.x
- **PHPUnit**: 10.x or 11.x for testing

## What's Changed

### Composer Dependencies
- Updated all Illuminate packages to support `^12.0`
- Updated Orchestra Testbench to support `^10.0`
- Updated PHPUnit to support version 11.x
- Raised minimum PHP version to 8.2

### Core Features
All existing functionality remains the same:
- Role and permission management
- Multi-tenant support
- Wildcard permissions
- Blade directives
- Middleware
- Event system
- Caching
- Artisan commands

### Laravel 12 Optimizations
The package automatically benefits from Laravel 12 improvements:
- Better performance with optimized query builders
- Enhanced caching mechanisms
- Improved Artisan commands
- Better exception handling

## Installation on Laravel 12

```bash
composer require litepie/shield
```

## Upgrading from Previous Versions

If you're upgrading from an earlier version of Shield:

1. Update your composer.json to require the latest version
2. Run `composer update litepie/shield`
3. Clear your config cache: `php artisan config:clear`
4. Clear your permission cache: `php artisan shield:cache-reset`

## Testing

Run the package tests with PHPUnit 10/11:

```bash
vendor/bin/phpunit
```

## Compatibility Notes

- All existing configurations remain valid
- No breaking changes to the API
- Database migrations are fully compatible
- All middleware signatures remain the same

## Laravel 12 Best Practices

When using Shield with Laravel 12, consider:

1. **Performance**: Laravel 12's improved query performance benefits Shield's permission checking
2. **Caching**: Utilize Laravel 12's enhanced caching for better permission cache performance
3. **Security**: Leverage Laravel 12's security improvements alongside Shield's authorization

## Support

For issues specific to Laravel 12 compatibility, please check:

1. Ensure PHP 8.2+ is being used
2. Verify all Shield configurations are published and up to date
3. Clear all caches after upgrading
