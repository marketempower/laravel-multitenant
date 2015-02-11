<?php namespace GlobeCode\LaravelMultiTenant;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

/**
 * Class ScopedByTenant
 *
 * @method static void addGlobalScope(\Illuminate\Database\Eloquent\ScopeInterface $scope)
 * @method static void observe(object $class)
 */
trait ScopedByTenant {

    public static function bootScopedByTenant()
    {
        // Get and Set the Tenant id if logged in user.
        self::bootTenantId();

        // Add the global scope that will handle all operations except create()
        static::addGlobalScope(new TenantScope);

        // Add an observer that will handle create()-ing
        static::observe(new TenantObserver);
    }

    /**
     * Returns a new builder without the Tenant scope applied.
     *
     * $allUsers = User::removeTenant()->get();
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function removeTenant()
    {
        return (new static)->newQueryWithoutScope(new TenantScope);
    }

    /**
     * Delete from model without Tenant scope constraint.
     *
     * @return boolean
     */
    public function adminDelete()
    {
        $builder = $this->removeTenant()->where($this->getKeyName(), $this->getKey());

        return (bool) $builder->delete();
    }

    public static function bootTenantId()
    {
        if (Auth::check())
        {
            if (method_exists(Auth::user(), 'isAdmin') && Auth::user()->isAdmin())
            {
                return TenantScope::setOverride();
            }
            else
            {
		TenantScope::setOverride(false);
                return TenantScope::setTenantId(Auth::user()->getTenantId());
            }
        }
        else
        {
            return TenantScope::setOverride();
        }
    }

    /**
     * Get the name of the "tenant id" column.
     *
     * @return string
     */
    public function getTenantColumn()
    {
        return Config::get('laravel-multitenant::tenant_column');
    }

    /**
     * Get the fully qualified "tenant id" column.
     *
     * @return string
     */
    public function getQualifiedTenantColumn()
    {
        return $this->getTable().'.'.$this->getTenantColumn();
    }
}
