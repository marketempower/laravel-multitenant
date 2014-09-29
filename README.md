Laravel Multi-Tenant
====================

Sep 29, 2014: WIP for release as an L4 package.

# Installation

1. Require this package in your `composer.json` file in the `"require"` block:

    ```php
    "globecode/laravel-multitenant": "dev-master"
    ```

1. Add the service provider to the providers array in `app/config/app.php`:

    ```php
    'GlobeCode\LaravelMultiTenant\LaravelMultiTenantServiceProvider'
    ```

1. `cd` into your project directory and update via _Composer_:

    ```bash
    composer update
    ```

1. Publish the `config` to your application. This allows you to change the name of the _Tenant_ column name in your schema. A `config` file will be installed to `app/config/packages/globecode/laravel-multitenant/` which you can edit:

    ```bash
    php artisan config:publish globecode/laravel-multitenant
    ```

1. Run migrations to setup the necessary schema. Example migrations are provided in the Package `migrations` folder.

1. Add the `getTenantId()` method to your `User` (and any other "scoped" models) or just once in a `BaseModel` (recommended):

    ```php
    /**
     * Get the value to scope the "tenant id" with.
     *
     * @return string
     */
    public function getTenantId()
    {
        return (isset($this->tenant_id)) ? $this->tenant_id : null;
    }
    ```

1. Optional Global Override. If you want to globally override scope, such as for an _Admin_, then add the `isAdmin()` method to your `User` model. The `ScopedByTenant` trait will look for this method and automatically override the scope on all queries if this method returns `true`:

    ```php
    /**
     * Does the current user have an 'admin' role?
     *
     * @return bool
     */
    public function isAdmin()
    {
        // Change to return true using whatever
        // roles/permissions you use in your app.
        return $this->hasRole('admin');
    }
    ```

# Usage

1. Scope a model using the `ScopedByTenant` trait to make all queries on that model globally scoped to a Tenant. Never worry about accidentally querying outside a Tenant's data!

    ```php
    <?php namespace Acme;

    use GlobeCode\LaravelMultiTenant\ScopedByTenant;

    class Example {

        /**
         * Only this line is required. The extra below is just
         * for example on what a relation might look like.
         */
        use ScopedByTenant;

        protected $table = 'example';

        /**
         * Query the Tenant the Example belongs to.
         *
         * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
         */
        public function tenant()
        {
            return $this->belongsTo('Acme\Tenant');
        }
    }
    ```

1. Globally removing scope:

    A: Globally __remove__ scope from a _Controller_, such as in an _Admin_ situation:

    ```php
    <?php

    use GlobeCode\LaravelMultiTenant\ScopedByTenant;

    class AdminExamplesController {

        /**
         * @var Acme\Repositories\ExampleRepository
         */
        protected $exampleRepo;

        public function __construct(ExampleRepository $exampleRepo)
        {
            $this->exampleRepo = $exampleRepo;

            // All queries in this controller on 'exampleRepo'
            // will be 'global' and not scoped.
            $this->exampleRepo->removeTenant();
        }

        /**
         * Display a listing of all Examples.
         *
         * @return Response
         */
        public function index()
        {
            // Global, will *not* be scoped.
            $leads = $this->exampleRepo->getAll();

            $this->view('examples.index', compact('examples'));
        }
    }
    ```

    __Or__


    B: Globally __remove__ scope by using the `Auth` check in the `ScopedByTenant` trait's `bootTenantId()` method. See the instructions in the __Setup__ -> _Optional Global Override_ section above.

Note: You can use any of the methods on the `ScopedByTenant` trait in your models and controllers.

# Repositories

If you use repositories, you will need to _build_ off of the `TenantScope` class instead of the `ScopedByTenant` trait. If you look at the `TenantScope` class you will see there are _extensions_ to `builder`, these are methods available to your repositories; the trait won't work in repos.

```php
<?php namespace Acme\Repositories;

use Illuminate\Database\Eloquent\Model;

use Acme\Example;
use Acme\Repositories\ExampleRepository

class EloquentExampleRepository extends ExampleRepository {

    /**
     * @var Example
     */
    protected $model;

    /**
     * Method extensions from TenantScope class
     */
    protected $whereTenant;
    protected $applyTenant;
    protected $removeTenant;

    public function __construct(Example $model)
    {
        $this->model = $model;

        $this->whereTenant = null;
        $this->applyTenant = null;
        $this->removeTenant = false;
    }

    /**
     * Example get all using scope.
     */
    public function getAll()
    {
        return $this->getQueryBuilder()->get();
    }

    /**
     * Example get by id using scope.
     */
    public function getById($id)
    {
        return $this->getQueryBuilder()->find((int) $id);
    }

    /**
     * Softdelete, include trashed.
     *
     * @return void
     */
    public function withTrashed()
    {
        $this->withTrashed = true;

        return $this;
    }

    /**
     * Limit scope to specific Tenant
     * Local method on repo, not on TenantScope.
     *
     * @param  integer  $id
     */
    public function whereTenant($id)
    {
        $this->whereTenant = $id;

        return $this;
    }

    /**
     * Remove Tenant scope.
     */
    public function removeTenant()
    {
        $this->removeTenant = true;

        return $this;
    }

    /**
     * Limit scope to specific Tenant(s)
     *
     * @param  int|array $arg
     */
    public function applyTenant($arg)
    {
        $this->applyTenant = $arg;

        return $this;
    }

    /**
     * Expand scope to all Tenants.
     */
    public function allTenants()
    {
        return $this->removeTenant();
    }

    protected function getQualifiedTenantColumn()
    {
        $tenantColumn = Config::get('laravel-multitenant::tenant_column');

        return $this->model->getTable() .'.'. $tenantColumn;
    }

    /**
     * Returns a Builder instance for use in constructing a query, honoring the
     * current filters. Resets the filters, ready for the next query.
     *
     * Example usage:
     * $result = $this->getQueryBuilder()->find($id);
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function getQueryBuilder()
    {
        $modelClass = $this->model;

        $builder = with(new $modelClass)->newQuery();

        if ( ! is_null($this->whereTenant))
            $builder->where($this->getQualifiedTenantColumn(), $this->whereTenant);

        if ($this->applyTenant)
            $builder->applyTenant($this->applyTenant);

        if ($this->removeTenant)
            $builder->removeTenant();

        $this->whereTenant = null;
        $this->applyTenant = null;
        $this->removeTenant = null;

        return $builder;
    }
}
```

# Seeding

You can manually override the scoping in your seed files to avoid difficult lookups for relations, by setting the __override__ manually:

```php
<?php

use Acme\User;

use GlobeCode\LaravelMultiTenant\TenantScope;

class UsersTableSeeder extends DatabaseSeeder {

    public function run()
    {
        // Manually override tenant scoping.
        TenantScope::setOverride();

        User::create([
            'id' => 1,
            'tenant_id' => null, // an admin
            'email' => 'admin@us.com',
            'password' => 'secret',

            'created_at' => time(),
            'updated_at' => time()
        ]);

        User::create([
            'id' => 2,
            'tenant_id' => 1000, // a tenant
            'email' => 'user@tenant.com',
            'password' => 'secret',

            'created_at' => time(),
            'updated_at' => time()
        ]);

        ...
    }
}
```

Note: set the `tenant_id` to null for any in-house staff/admins.

# About

Conception: Hard fork off [AuraEQ Laravel Multi Tenant](https://github.com/AuraEQ/laravel-multi-tenant). Many thanks to him for the initial work.

Packagist: [globecode/laravel-multitenant](https://packagist.org/packages/globecode/laravel-multitenant)

Hashtag: [#laravel-multitenant](https://twitter.com/hashtag/laravel-multitenant)

Twitter: [@jhaurawachsman](https://twitter.com/jhaurawachsman)

Copyright (c) 2014 Jhaura Wachsman

Licensed under MIT.
