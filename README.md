# Litepie Shield - Laravel Role and Permission System

A production-ready Laravel package for role and permission-based access control, built from scratch with all the features from Spatie's Laravel Permission package and enhanced with additional capabilities.

## ✨ Features

- **🔐 Roles and Permissions**: Comprehensive role-based access control system
- **🛡️ Multiple Guards**: Support for multiple authentication guards (web, api, custom)
- **🏢 Multi-Tenant Ready**: Complete tenant/organization isolation with dynamic context switching
- **🌟 Wildcard Permissions**: Hierarchical permissions with pattern matching (`posts.*`, `admin.*`)
- **⚡ High Performance Caching**: Redis/file-based caching with automatic invalidation
- **🎨 Blade Directives**: Beautiful template permission checking (`@role`, `@permission`)
- **🚧 Middleware Protection**: Route-level security with flexible middleware
- **📡 Event System**: Listen to permission/role changes with Laravel events
- **🔑 Passport Integration**: Full support for API authentication and machine-to-machine tokens
- **⚙️ Artisan Commands**: Complete CLI management for roles, permissions, and users
- **🚀 Laravel Octane Ready**: Optimized for high-performance Laravel Octane deployments
- **📊 Database Query Scopes**: Elegant Eloquent scopes for complex queries
- **🧪 Fully Tested**: Comprehensive test suite for production reliability

## 📋 Table of Contents

- [Installation](#installation)
- [Quick Setup](#quick-setup)
- [Configuration](#configuration)
- [Basic Usage](#basic-usage)
- [Advanced Features](#advanced-features)
- [Multi-Tenant Support](#multi-tenant-support)
- [Wildcard Permissions](#wildcard-permissions)
- [Middleware](#middleware)
- [Blade Directives](#blade-directives)
- [Database Queries](#database-queries)
- [Events](#events)
- [API Integration](#api-integration)
- [Performance & Caching](#performance--caching)
- [Artisan Commands](#artisan-commands)
- [Testing](#testing)
- [Migration from Spatie](#migration-from-spatie)
- [Contributing](#contributing)

## 🚀 Installation

```bash
composer require litepie/shield
```

Publish the migration and config file:

```bash
php artisan vendor:publish --provider="Litepie\Shield\ShieldServiceProvider"
```

Run the migrations:

```bash
php artisan migrate
```

## Quick Setup

### Create Super User

After installation, create a super user with full admin privileges:

```bash
php artisan shield:create-superuser
```

This command will:
- Create a "Super Admin" role with all permissions
- Create comprehensive permissions for your application
- Create a user account and assign the Super Admin role
- Provide you with login credentials

You can also run it non-interactively:

```bash
php artisan shield:create-superuser --name="Administrator" --email="admin@yourdomain.com" --password="secure-password"
```

## ⚙️ Configuration

The configuration file `config/shield.php` provides extensive customization options:

```php
return [
    'models' => [
        'permission' => Litepie\Shield\Models\Permission::class,
        'role' => Litepie\Shield\Models\Role::class,
    ],

    'table_names' => [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'model_has_permissions' => 'model_has_permissions',
        'model_has_roles' => 'model_has_roles',
        'role_has_permissions' => 'role_has_permissions',
    ],

    'cache' => [
        'expiration_time' => \DateInterval::createFromDateString('24 hours'),
        'key' => 'shield.cache',
        'store' => 'default',
    ],

    'tenants' => false, // Enable for multi-tenant applications
    'use_passport_client_credentials' => false, // Enable for API authentication
    'enable_wildcard_permission' => false, // Enable hierarchical permissions
    'events_enabled' => false, // Enable role/permission events
];
```

### Configuration Options Explained

| Option | Description | Default |
|--------|-------------|---------|
| `models.permission` | Permission model class | `Litepie\Shield\Models\Permission` |
| `models.role` | Role model class | `Litepie\Shield\Models\Role` |
| `table_names.*` | Database table names | Standard naming |
| `cache.expiration_time` | Cache duration | 24 hours |
| `cache.store` | Cache store to use | `default` |
| `tenants` | Enable multi-tenant support | `false` |
| `use_passport_client_credentials` | API authentication | `false` |
| `enable_wildcard_permission` | Hierarchical permissions | `false` |
| `events_enabled` | Event firing | `false` |

## 📚 Basic Usage

### Setting up Models

Add the `HasRoles` trait to your User model:

```php
use Litepie\Shield\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;

    // ...
}
```

For models that only need permissions (not roles), use `HasPermissions`:

```php
use Litepie\Shield\Traits\HasPermissions;

class ApiClient extends Model
{
    use HasPermissions;
}
```

### Creating Roles and Permissions

#### Basic Creation

```php
use Litepie\Shield\Models\Role;
use Litepie\Shield\Models\Permission;

// Create permissions
$permission = Permission::create(['name' => 'edit articles']);
$deletePermission = Permission::create(['name' => 'delete articles']);

// Create roles
$writerRole = Role::create(['name' => 'writer']);
$editorRole = Role::create(['name' => 'editor']);

// Assign permissions to roles
$writerRole->givePermissionTo('edit articles');
$editorRole->givePermissionTo(['edit articles', 'delete articles']);

// Or using the permission object
$writerRole->givePermissionTo($permission);
```

#### Advanced Creation with Guards

```php
// Create for specific guard
$apiPermission = Permission::create([
    'name' => 'access-api',
    'guard_name' => 'api'
]);

$adminRole = Role::create([
    'name' => 'admin',
    'guard_name' => 'web'
]);

// Create with additional attributes
$moderatorRole = Role::create([
    'name' => 'moderator',
    'guard_name' => 'web',
    'description' => 'Content moderation role',
    'level' => 5
]);
```

### Assigning Roles and Permissions

#### Role Assignment

```php
// Assign single role
$user->assignRole('writer');
$user->assignRole($writerRole);

// Assign multiple roles
$user->assignRole(['writer', 'editor']);
$user->assignRole([$writerRole, $editorRole]);

// Sync roles (removes all other roles)
$user->syncRoles(['admin', 'editor']);

// Remove roles
$user->removeRole('writer');
$user->removeRole(['writer', 'editor']);
```

#### Direct Permission Assignment

```php
// Give permission directly to user
$user->givePermissionTo('edit articles');
$user->givePermissionTo(['edit articles', 'delete articles']);

// Using permission object
$user->givePermissionTo($permission);

// Sync permissions
$user->syncPermissions(['edit articles', 'view dashboard']);

// Revoke permissions
$user->revokePermissionTo('edit articles');
$user->revokePermissionTo(['edit articles', 'delete articles']);
```

### Checking Permissions and Roles

#### Permission Checks

```php
// Laravel's built-in authorization
$user->can('edit articles');
$user->cannot('delete articles');

// Package-specific methods
$user->hasPermissionTo('edit articles');
$user->hasDirectPermission('edit articles'); // Only direct permissions
$user->hasPermissionViaRole('edit articles'); // Only via roles

// Check multiple permissions
$user->hasAnyPermission(['edit articles', 'delete articles']);
$user->hasAllPermissions(['edit articles', 'view dashboard']);
```

#### Role Checks

```php
// Check if user has role
$user->hasRole('writer');
$user->hasRole($writerRole);

// Check multiple roles
$user->hasAnyRole(['writer', 'editor']);
$user->hasAllRoles(['writer', 'editor']);
$user->hasExactRoles(['writer', 'editor']); // Only these roles, no more

// Get user roles
$roles = $user->roles; // Collection of roles
$roleNames = $user->getRoleNames(); // Collection of role names
```

#### Advanced Checks

```php
// Check with specific guard
$user->hasRole('admin', 'web');
$user->hasPermissionTo('access-api', 'api');

// Get permissions
$permissions = $user->permissions; // Direct permissions
$allPermissions = $user->getAllPermissions(); // Direct + via roles
$rolePermissions = $user->getPermissionsViaRoles(); // Only via roles

// Check if user has any permissions
if ($user->permissions->isNotEmpty()) {
    // User has some permissions
}
```

## 🚀 Advanced Features

### Custom Models

Extend the base models to add your own functionality:

```php
use Litepie\Shield\Models\Permission as ShieldPermission;

class Permission extends ShieldPermission
{
    protected $fillable = ['name', 'guard_name', 'description', 'category'];

    public function category()
    {
        return $this->belongsTo(PermissionCategory::class);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }
}
```

Update your config:

```php
'models' => [
    'permission' => App\Models\Permission::class,
    'role' => App\Models\Role::class,
],
```

### Multiple Guards

Shield supports multiple authentication guards seamlessly:

```php
// Web guard (default)
$webRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
$user->assignRole($webRole);

// API guard
$apiRole = Role::create(['name' => 'api-admin', 'guard_name' => 'api']);
$apiUser->assignRole($apiRole);

// Custom guard
$customRole = Role::create(['name' => 'manager', 'guard_name' => 'custom']);

// Check permissions with specific guard
$user->hasPermissionTo('edit posts', 'web');
$apiUser->hasPermissionTo('access api', 'api');
```

### Permission Inheritance

Create hierarchical permission structures:

```php
// Parent permissions
Permission::create(['name' => 'posts']);
Permission::create(['name' => 'posts.view']);
Permission::create(['name' => 'posts.create']);
Permission::create(['name' => 'posts.edit']);
Permission::create(['name' => 'posts.delete']);

// With wildcard enabled, granting 'posts.*' gives all post permissions
$user->givePermissionTo('posts.*');

$user->can('posts.view'); // true
$user->can('posts.edit'); // true
$user->can('posts.delete'); // true
```

## 🏢 Multi-Tenant Support

Shield provides complete multi-tenant isolation with tenant-based permissions.

### Enable Tenants

```php
// config/shield.php
'tenants' => true,
'column_names' => [
    'tenant_foreign_key' => 'tenant_id',
],
```

### Tenant Context Management

```php
// Set tenant context globally
setPermissionsTenantId(1);

// Now all permission checks are scoped to tenant 1
$user->hasPermissionTo('edit articles'); // Only tenant 1 permissions
$user->can('manage users'); // Scoped to tenant 1

// Get current tenant
$tenantId = getPermissionsTenantId(); // Returns: 1

// Switch tenants dynamically
setPermissionsTenantId(2);
$user->can('edit posts'); // Now checks tenant 2 permissions
```

### Tenant-Specific Operations

```php
// Create tenant-specific roles and permissions
$tenantRole = Role::create([
    'name' => 'Tenant Manager',
    'guard_name' => 'web',
    'tenant_id' => 1
]);

$tenantPermission = Permission::create([
    'name' => 'manage tenant members',
    'guard_name' => 'web',
    'tenant_id' => 1
]);

// Assign with tenant context
setPermissionsTenantId(1);
$user->assignRole('Tenant Manager');
$user->givePermissionTo('manage tenant members');
```

### Custom Tenant Resolver

Implement automatic tenant detection:

```php
use Litepie\Shield\Contracts\PermissionsTenantResolver;

class CustomTenantResolver implements PermissionsTenantResolver
{
    public function getPermissionsTenantId(): ?int
    {
        // Get from authenticated user
        return auth()->user()?->current_tenant_id;
        
        // Or from request header
        return request()->header('X-Tenant-ID');
        
        // Or from subdomain
        $subdomain = request()->getHost();
        return Tenant::where('subdomain', $subdomain)->value('id');
    }

    public function setPermissionsTenantId(?int $tenantId): void
    {
        if ($user = auth()->user()) {
            $user->update(['current_tenant_id' => $tenantId]);
        }
        
        session(['current_tenant_id' => $tenantId]);
    }
}
```

Register in `AppServiceProvider`:

```php
$this->app->bind(PermissionsTenantResolver::class, CustomTenantResolver::class);
```

### Multi-Tenant Middleware

```php
class SetTenantContext
{
    public function handle($request, Closure $next)
    {
        // Extract tenant from subdomain
        $host = $request->getHost();
        $subdomain = explode('.', $host)[0];
        
        $tenant = Tenant::where('subdomain', $subdomain)->first();
        
        if ($tenant) {
            setPermissionsTenantId($tenant->id);
            app()->instance('current_tenant', $tenant);
        }
        
        return $next($request);
    }
}
```

## ⭐ Wildcard Permissions

Register the middleware in your `Kernel.php`:

```php
protected $routeMiddleware = [
    // ...
    'role' => \Litepie\Shield\Middleware\RoleMiddleware::class,
    'permission' => \Litepie\Shield\Middleware\PermissionMiddleware::class,
    'role_or_permission' => \Litepie\Shield\Middleware\RoleOrPermissionMiddleware::class,
];
```

```php
// Role-based protection
Route::group(['middleware' => ['role:admin']], function () {
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
    Route::resource('/admin/users', UserController::class);
});

// Permission-based protection
Route::group(['middleware' => ['permission:edit articles']], function () {
    Route::put('/articles/{article}', [ArticleController::class, 'update']);
    Route::delete('/articles/{article}', [ArticleController::class, 'destroy']);
});

// Multiple permissions (user needs ALL)
Route::group(['middleware' => ['permission:edit articles,publish articles']], function () {
    Route::post('/articles/{article}/publish', [ArticleController::class, 'publish']);
});

// Multiple permissions (user needs ANY)  
Route::group(['middleware' => ['role_or_permission:admin|edit articles']], function () {
    Route::get('/articles/{article}/edit', [ArticleController::class, 'edit']);
});
```

### Advanced Middleware Usage

```php
// Multiple roles (user needs ANY)
Route::middleware('role:admin|editor|author')->group(function () {
    Route::get('/content', [ContentController::class, 'index']);
});

// Specific guard
Route::middleware('role:api-admin,api')->group(function () {
    Route::apiResource('/api/users', ApiUserController::class);
});

// Combined with other middleware
Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::get('/admin/settings', [SettingsController::class, 'index']);
});

// Using route macros (more elegant)
Route::role('admin')->group(function () {
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
});

Route::permission('manage content')->group(function () {
    Route::resource('/content', ContentController::class);
});
```

### Custom Middleware

Create your own permission middleware:

```php
class RequirePermissionMiddleware
{
    public function handle($request, Closure $next, $permission, $guard = null)
    {
        $user = Auth::guard($guard)->user();
        
        if (!$user || !$user->can($permission)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Forbidden'], 403);
            }
            
            abort(403, 'You do not have permission to access this resource.');
        }
        
        return $next($request);
    }
}
```

## 🎨 Blade Directives

Beautiful template-level permission checking.

### Role Directives

```blade
@role('admin')
    <div class="admin-panel">
        <h2>Admin Panel</h2>
        <p>Welcome to the admin dashboard!</p>
    </div>
@endrole

@hasrole('editor')
    <button class="btn btn-primary">Edit Content</button>
@endhasrole

@hasanyrole(['admin', 'editor', 'author'])
    <div class="content-management">
        <h3>Content Tools</h3>
    </div>
@endhasanyrole

@hasallroles(['admin', 'supervisor'])
    <button class="btn btn-danger">Critical Action</button>
@endhasallroles

@hasexactroles(['editor'])
    <p>You are an editor (and nothing else)</p>
@endhasexactroles
```

### Permission Directives

```blade
@permission('edit articles')
    <a href="{{ route('articles.edit', $article) }}" class="btn btn-edit">
        Edit Article
    </a>
@endpermission

@haspermission('delete articles')
    <form action="{{ route('articles.destroy', $article) }}" method="POST">
        @csrf @method('DELETE')
        <button type="submit" class="btn btn-danger">Delete</button>
    </form>
@endhaspermission

@hasanypermission(['edit articles', 'publish articles'])
    <div class="article-actions">
        <!-- Article management tools -->
    </div>
@endhasanypermission
```

### Advanced Blade Usage

```blade
<!-- Combine with other conditions -->
@auth
    @role('admin')
        <li><a href="{{ route('admin.dashboard') }}">Admin Dashboard</a></li>
    @endrole
    
    @permission('view reports')
        <li><a href="{{ route('reports.index') }}">Reports</a></li>
    @endpermission
@endauth

<!-- Nested permissions -->
@role('manager')
    <div class="manager-section">
        @permission('approve requests')
            <button class="btn btn-success">Approve</button>
        @endpermission
        
        @permission('reject requests')
            <button class="btn btn-danger">Reject</button>
        @endpermission
    </div>
@endrole

<!-- Dynamic role checking -->
@hasrole($requiredRole)
    <p>You have the required role: {{ $requiredRole }}</p>
@endhasrole

<!-- With guards -->
@role('admin', 'web')
    <p>Web admin access</p>
@endrole
```

## 🗃️ Database Queries

Powerful Eloquent scopes for complex permission queries.

### User Queries

### Tenants Feature

Enable tenants in config:

```php
'tenants' => true,
```

Set the tenant context:

```php
// Set tenant context
setPermissionsTenantId(1);

// Now all permission checks will be scoped to tenant 1
$user->hasPermissionTo('edit articles'); // Only checks tenant 1 permissions

// Get current tenant
$tenantId = getPermissionsTenantId();
```

Create hierarchical permissions with pattern matching:

### Enable Wildcards

```php
// config/shield.php
'enable_wildcard_permission' => true,
```

### Wildcard Patterns

```php
// Grant broad permissions with wildcards
$user->givePermissionTo('posts.*');

// This automatically grants:
$user->can('posts.create'); // true
$user->can('posts.edit');   // true
$user->can('posts.delete'); // true
$user->can('posts.view');   // true
$user->can('posts.publish'); // true

// But not:
$user->can('comments.create'); // false
$user->can('users.edit'); // false

// Multi-level wildcards
$user->givePermissionTo('admin.*');
$user->can('admin.users.create'); // true
$user->can('admin.settings.edit'); // true
$user->can('admin.reports.view'); // true

// Specific patterns
$user->givePermissionTo('posts.*.own'); 
$user->can('posts.edit.own'); // true
$user->can('posts.delete.own'); // true
$user->can('posts.edit.any'); // false
```

### Wildcard Examples

```php
// Content management
$editor->givePermissionTo('content.*');
// Grants: content.posts.*, content.pages.*, content.media.*

// User administration  
$admin->givePermissionTo('users.*');
// Grants: users.view, users.create, users.edit, users.delete

// API access levels
$apiUser->givePermissionTo('api.v1.*');
// Grants: api.v1.users, api.v1.posts, api.v1.analytics

// Department-specific access
$manager->givePermissionTo('department.sales.*');
// Grants: department.sales.reports, department.sales.leads, etc.
```

## 🚧 Middleware

Protect your routes with flexible middleware options.

### Register Middleware

Add to `app/Http/Kernel.php`:

```php
protected $routeMiddleware = [
    // ...
    'role' => \Litepie\Shield\Middleware\RoleMiddleware::class,
    'permission' => \Litepie\Shield\Middleware\PermissionMiddleware::class,
    'role_or_permission' => \Litepie\Shield\Middleware\RoleOrPermissionMiddleware::class,
];
```

### Basic Usage

```bash
Complete CLI management for your permission system.

### Basic Commands

```bash
# Create permission
php artisan shield:create-permission "edit articles"
php artisan shield:create-permission "manage users" --guard=web

# Create role
php artisan shield:create-role writer
php artisan shield:create-role "content manager" --guard=web

# Create super user with full admin access
php artisan shield:create-superuser

# Interactive mode (prompts for details)
php artisan shield:create-superuser

# Non-interactive mode
php artisan shield:create-superuser 
  --name="Site Administrator" 
  --email="admin@yoursite.com" 
  --password="secure-password-123"

# Clear permission cache
php artisan shield:cache-reset
```

### Tenant-Specific Commands

```bash
# Create tenant-specific permission
php artisan shield:create-permission "manage inventory" --tenantId=1

# Create tenant-specific role
php artisan shield:create-role "warehouse manager" --tenantId=1

# Create superuser for specific tenant
php artisan shield:create-superuser --tenantId=1
```

### Advanced Management

```bash
# Show all roles and permissions
php artisan shield:show

# Upgrade database for tenants feature
php artisan shield:upgrade-for-tenants

# Custom commands you can create
php artisan make:command AssignBulkPermissions
php artisan make:command SyncUserRoles
php artisan make:command AuditPermissions
```

## 🧪 Testing

Comprehensive testing helpers for your application tests.

### Test Helpers

```php
use Litepie\Shield\Models\Role;
use Litepie\Shield\Models\Permission;

class UserPermissionTest extends TestCase
{
    /** @test */
    public function user_can_edit_articles_with_permission()
    {
        // Create user with permission
        $user = User::factory()->create();
        $permission = Permission::create(['name' => 'edit articles']);
        $user->givePermissionTo($permission);
        
        // Test permission
        $this->assertTrue($user->can('edit articles'));
        
        // Test via HTTP
        $this->actingAs($user)
            ->get('/articles/1/edit')
            ->assertStatus(200);
    }
    
    /** @test */
    public function user_can_access_admin_with_role()
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'admin']);
        $user->assignRole($role);
        
        $this->assertTrue($user->hasRole('admin'));
        
        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertStatus(200);
    }
}
```

### Testing Multi-Tenant

```php
/** @test */
public function permissions_are_isolated_by_tenant()
{
    $user = User::factory()->create();
    
    // Tenant 1 permission
    setPermissionsTenantId(1);
    $user->givePermissionTo('edit posts');
    
    // Tenant 2 permission  
    setPermissionsTenantId(2);
    $user->givePermissionTo('delete posts');
    
    // Test tenant 1 context
    setPermissionsTenantId(1);
    $this->assertTrue($user->can('edit posts'));
    $this->assertFalse($user->can('delete posts'));
    
    // Test tenant 2 context
    setPermissionsTenantId(2);
    $this->assertFalse($user->can('edit posts'));
    $this->assertTrue($user->can('delete posts'));
}
```

### Testing Middleware

```php
/** @test */
public function middleware_blocks_unauthorized_access()
{
    $user = User::factory()->create();
    
    // Without permission
    $this->actingAs($user)
        ->get('/admin/users')
        ->assertStatus(403);
    
    // With permission
    $user->givePermissionTo('manage users');
    $this->actingAs($user)
        ->get('/admin/users')
        ->assertStatus(200);
}
```

### Performance Testing

```php
/** @test */
public function permission_checks_are_cached()
{
    $user = User::factory()->create();
    $user->givePermissionTo('test permission');
    
    // First check (loads from database)
    $start = microtime(true);
    $user->can('test permission');
    $firstCheckTime = microtime(true) - $start;
    
    // Second check (loads from cache)
    $start = microtime(true);
    $user->can('test permission');
    $secondCheckTime = microtime(true) - $start;
    
    // Cache should be significantly faster
    $this->assertLessThan($firstCheckTime / 2, $secondCheckTime);
}
```

## 🔄 Migration from Spatie

Easy migration path from Spatie Laravel Permission.

### Migration Script

```php
// Create a migration command
php artisan make:command MigrateFromSpatie

class MigrateFromSpatie extends Command
{
    protected $signature = 'shield:migrate-from-spatie';
    protected $description = 'Migrate from Spatie Laravel Permission to Litepie Shield';
    
    public function handle()
    {
        $this->info('Migrating from Spatie to Litepie Shield...');
        
        // Update namespace references
        $this->updateModelReferences();
        
        // Update config references
        $this->updateConfigReferences();
        
        // Update database references if needed
        $this->updateDatabaseReferences();
        
        $this->info('Migration completed successfully!');
    }
    
    protected function updateModelReferences()
    {
        // Replace Spatie namespace with Litepie
        $files = [
            'app/Models/User.php',
            'config/permission.php',
            // Add other files as needed
        ];
        
        foreach ($files as $file) {
            if (File::exists($file)) {
                $content = File::get($file);
                $content = str_replace(
                    'Spatie\Permission',
                    'Litepie\Shield',
                    $content
                );
                File::put($file, $content);
            }
        }
    }
}
```

### Key Differences

| Feature | Spatie | Litepie Shield |
|---------|--------|----------------|
| Namespace | `Spatie\Permission` | `Litepie\Shield` |
| Config file | `permission.php` | `shield.php` |
| Service Provider | `PermissionServiceProvider` | `ShieldServiceProvider` |
| Cache key | `spatie.permission.cache` | `shield.cache` |
| Tenants config | `enable_tenants` | `tenants` |

### Update Steps

1. **Install Shield**: `composer require litepie/shield`
2. **Update config**: Rename and update configuration
3. **Update models**: Change namespace references
4. **Update middleware**: Register new middleware classes
5. **Test thoroughly**: Ensure all permissions work correctly

## 🤝 Contributing

We welcome contributions! Please see our contributing guidelines.

### Development Setup

```bash
# Clone the repository
git clone https://github.com/litepie/shield.git
cd shield

# Install dependencies
composer install

# Run tests
composer test

# Run code analysis
composer analyse

# Run code formatting
composer format
```

### Running Tests

```bash
# Run all tests
composer test

# Run specific test
./vendor/bin/phpunit tests/PermissionTest.php

# Run with coverage
composer test-coverage
```

### Code Standards

- Follow PSR-12 coding standards
- Add tests for new features
- Update documentation
- Use semantic versioning

## 📝 License
```

```php
// Users with specific role
$admins = User::role('admin')->get();
$editors = User::role(['editor', 'author'])->get();

// Users with specific permission
$canEdit = User::permission('edit articles')->get();
$canPublish = User::permission(['publish articles', 'edit articles'])->get();

// Users without specific role
$nonAdmins = User::withoutRole('admin')->get();
$notManagement = User::withoutRole(['admin', 'manager'])->get();

// Users without specific permission
$cannotDelete = User::withoutPermission('delete articles')->get();

// Complex combinations
$contentTenants = User::role(['editor', 'author'])
    ->permission('edit articles')
    ->where('active', true)
    ->get();

// Users with any of the specified roles
$management = User::hasAnyRole(['admin', 'manager', 'supervisor'])->get();

// Users with all specified roles
$superUsers = User::hasAllRoles(['admin', 'superuser'])->get();
```

### Role and Permission Queries

```php
// Roles with specific permissions
$rolesWithEditAccess = Role::whereHas('permissions', function ($query) {
    $query->where('name', 'edit articles');
})->get();

// Permissions belonging to specific roles
$adminPermissions = Permission::whereHas('roles', function ($query) {
    $query->where('name', 'admin');
})->get();

// Unused permissions
$unusedPermissions = Permission::doesntHave('roles')
    ->doesntHave('users')
    ->get();

// Most common roles
$popularRoles = Role::withCount('users')
    ->orderBy('users_count', 'desc')
    ->get();

// Tenant-specific queries (when tenants enabled)
setPermissionsTenantId(1);
$tenantRoles = Role::where('tenant_id', 1)->get();
$tenantPermissions = Permission::where('tenant_id', 1)->get();
```

### Advanced Queries

```php
// Users who can perform specific action
$usersWhoCanEdit = User::whereHas('roles.permissions', function ($query) {
    $query->where('name', 'edit articles');
})->orWhereHas('permissions', function ($query) {
    $query->where('name', 'edit articles');
})->get();

// Roles that grant specific permission
$rolesWithPermission = Role::whereHas('permissions', function ($query) {
    $query->where('name', 'like', 'admin.%');
})->get();

// Permission usage statistics
$permissionStats = Permission::withCount(['roles', 'users'])
    ->get()
    ->map(function ($permission) {
        return [
            'name' => $permission->name,
            'total_users' => $permission->users_count + 
                $permission->roles->sum('users_count'),
            'direct_assignments' => $permission->users_count,
            'role_assignments' => $permission->roles_count,
        ];
    });
```

## 📡 Events

Listen to role and permission changes throughout your application.

### Enable Events

```php
// config/shield.php
'events_enabled' => true,
```

### Available Events

```php
use Litepie\Shield\Events\RoleAttached;
use Litepie\Shield\Events\RoleDetached;
use Litepie\Shield\Events\PermissionAttached;
use Litepie\Shield\Events\PermissionDetached;
```

### Event Listeners

```php
// In EventServiceProvider
protected $listen = [
    RoleAttached::class => [
        SendRoleAssignmentNotification::class,
        LogRoleChange::class,
        UpdateUserCache::class,
    ],
    
    RoleDetached::class => [
        SendRoleRemovalNotification::class,
        LogRoleChange::class,
    ],
    
    PermissionAttached::class => [
        LogPermissionChange::class,
        NotifySecurityTenant::class,
    ],
    
    PermissionDetached::class => [
        LogPermissionChange::class,
    ],
];
```

### Example Listeners

```php
class SendRoleAssignmentNotification
{
    public function handle(RoleAttached $event)
    {
        $user = $event->model;
        $role = $event->role;
        
        // Send notification
        $user->notify(new RoleAssignedNotification($role));
        
        // Log the change
        Log::info("Role '{$role->name}' assigned to user {$user->id}");
        
        // Update external systems
        if ($role->name === 'admin') {
            ExternalApi::grantAdminAccess($user);
        }
    }
}

class LogPermissionChange
{
    public function handle($event)
    {
        $user = $event->model;
        $permission = $event->permission;
        $action = $event instanceof PermissionAttached ? 'granted' : 'revoked';
        
        Log::channel('security')->info("Permission '{$permission->name}' {$action} for user {$user->id}");
        
        // Store audit trail
        AuditLog::create([
            'user_id' => $user->id,
            'action' => "permission_{$action}",
            'details' => [
                'permission' => $permission->name,
                'guard' => $permission->guard_name,
                'timestamp' => now(),
            ],
        ]);
    }
}
```

### Real-time Updates

```php
// Broadcast role changes
class BroadcastRoleChange
{
    public function handle(RoleAttached $event)
    {
        broadcast(new UserRoleUpdated($event->model, $event->role));
    }
}

// WebSocket event
class UserRoleUpdated implements ShouldBroadcast
{
    public $user;
    public $role;
    
    public function __construct($user, $role)
    {
        $this->user = $user;
        $this->role = $role;
    }
    
    public function broadcastOn()
    {
        return new PrivateChannel("user.{$this->user->id}");
    }
}
```

## 🔌 API Integration

Full support for API authentication and machine-to-machine communication.

### Passport Integration

Enable Passport client credentials:

```php
// config/shield.php
'use_passport_client_credentials' => true,
```

### API Authentication

```php
// routes/api.php
Route::middleware(['client', 'permission:api.access'])->group(function () {
    Route::get('/users', [ApiUserController::class, 'index']);
    Route::post('/users', [ApiUserController::class, 'store']);
});

Route::middleware(['client', 'role:api-admin'])->group(function () {
    Route::delete('/users/{user}', [ApiUserController::class, 'destroy']);
});
```

### Machine-to-Machine

```php
// Create API client with permissions
$client = PassportClient::create([
    'name' => 'Analytics Service',
    'secret' => Str::random(40),
    'personal_access_client' => false,
    'password_client' => false,
    'revoked' => false,
]);

// Grant permissions to client
$client->givePermissionTo(['api.analytics.read', 'api.reports.create']);

// Client credentials request
$response = Http::asForm()->post('your-app.com/oauth/token', [
    'grant_type' => 'client_credentials',
    'client_id' => $client->id,
    'client_secret' => $client->secret,
    'scope' => 'api.analytics.read api.reports.create',
]);

$token = $response->json()['access_token'];

// Use token for API requests
$apiResponse = Http::withToken($token)
    ->get('your-app.com/api/analytics');
```

### API Controllers

```php
class ApiUserController extends Controller
{
    public function index()
    {
        // Automatic permission checking via middleware
        return UserResource::collection(User::paginate());
    }
    
    public function store(Request $request)
    {
        $this->authorize('create', User::class);
        
        // Create user logic
        $user = User::create($request->validated());
        
        return new UserResource($user);
    }
    
    public function destroy(User $user)
    {
        // Check permissions programmatically
        if (!auth()->user()->can('delete users')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        
        $user->delete();
        
        return response()->json(['message' => 'User deleted successfully']);
    }
}
```

## ⚡ Performance & Caching

Optimized for high-performance applications with intelligent caching.

### Cache Configuration

```php
// config/shield.php
'cache' => [
    'expiration_time' => \DateInterval::createFromDateString('24 hours'),
    'key' => 'shield.cache',
    'store' => 'redis', // Use Redis for better performance
],
```

### Cache Management

```php
// Clear all permission cache
app(\Litepie\Shield\PermissionRegistrar::class)->forgetCachedPermissions();

// Automatically cleared when:
// - Roles or permissions are created/updated/deleted
// - User roles are assigned/removed
// - User permissions are granted/revoked

// Manual cache operations
Cache::tags(['shield'])->flush(); // Clear all Shield cache
Cache::forget('shield.permissions'); // Clear specific cache key
```

### Performance Tips

```php
// 1. Eager load relationships
$users = User::with(['roles', 'permissions'])->get();

// 2. Use specific permission checks
$user->hasDirectPermission('edit articles'); // Faster than checking via roles

// 3. Cache complex queries
$adminUsers = Cache::remember('admin_users', 3600, function () {
    return User::role('admin')->get();
});

// 4. Use database indexes
// Add to migration:
$table->index(['model_type', 'model_id']); // For polymorphic relations
$table->index('tenant_id'); // For tenant-based permissions

// 5. Optimize wildcard permissions
$user->givePermissionTo('posts.*'); // Better than multiple specific permissions
```

### Laravel Octane Support

Shield is fully compatible with Laravel Octane:

```php
// config/shield.php
'register_octane_reset_listener' => true,

// Automatically resets permission cache between requests
// Handles tenant context isolation
// Prevents memory leaks in long-running processes
```

## ⚙️ Artisan Commands

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## 🆘 Support

- **Documentation**: [Full documentation](https://shield.litepie.com)
- **Issues**: [GitHub Issues](https://github.com/litepie/shield/issues)
- **Discussions**: [GitHub Discussions](https://github.com/litepie/shield/discussions)
- **Security**: Please email security@litepie.com for security vulnerabilities

## 🎯 Roadmap

- [ ] **Vue.js Components**: Pre-built permission components
- [ ] **React Components**: Permission management components
- [ ] **GraphQL Support**: GraphQL permission checking
- [ ] **Audit Trails**: Built-in permission change logging
- [ ] **Permission Import/Export**: Bulk permission management
- [ ] **Advanced Caching**: Multi-layer caching strategies
- [ ] **Performance Dashboard**: Permission usage analytics

---

**Made with ❤️ by [Litepie](https://litepie.com)**

*Shield - Your Laravel application's guardian angel for role and permission management.*
