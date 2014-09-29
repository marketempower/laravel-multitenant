<?php namespace GlobeCode\LaravelMultiTenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ScopeInterface;

use GlobeCode\LaravelMultiTenant\Exceptions\TenantIdNotSetException;

class TenantScope implements ScopeInterface {

    /**
     * Manually set the tenant id
     *
     * @var mixed int|null
     */
    protected static $tenantId = null;

    /**
     * Override the scope
     *
     * @var boolean
     */
    protected static $override = false;

    /**
     * All of the extensions to be added to the builder.
     *
     * @var array
     */
    protected $extensions = ['RemoveTenant', 'ApplyTenant', 'AllTenants'];

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $builder
     *
     * @throws Exceptions\TenantIdNotSetException
     * @return void
     */
    public function apply(Builder $builder)
    {
        if (self::getOverride() === false)
        {
            $tenantId = (int) trim(self::getTenantId());

            if (is_null($tenantId) || empty($tenantId) || $tenantId < 1)
                throw new TenantIdNotSetException;

            $model = $builder->getModel();

            // Apply the scope to the Model's Builder
            $builder->where($model->getQualifiedTenantColumn(), $tenantId);
        }

        // Make extensions available, even if 'overriding'.
        $this->extend($builder);
    }

    /**
     * Remove the scope from the given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return void
     */
    public function remove(Builder $builder)
    {
        $column = $builder->getModel()->getQualifiedTenantColumn();

        // Illuminate/Database/Query/Builder.php
        $query = $builder->getQuery();

        // Array of all binding types, including 'where'
        $bindings = $query->getRawBindings();

        foreach ((array) $query->wheres as $key => $where)
        {
            // If the where clause is a tenant id constraint, we will remove it from
            // the query and reset the keys on the wheres.
            if ($this->isTenantConstraint($where, $column))
            {
                // Remove the binding for the tenant column value
                if(($bkey = array_search($where['value'], $bindings['where'])) !== false) {
                    unset($bindings['where'][$bkey]);
                }

                unset($query->wheres[$key]);
            }
        }

        // Repopulate the bindings 'where' type only.
        $query->setBindings(array_values($bindings['where']));

        // Repopulate the query 'wheres' only.
        $query->wheres = array_values($query->wheres);
    }

    /**
     * Extend the query builder with the needed functions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return void
     */
    public function extend(Builder $builder)
    {
        foreach ($this->extensions as $extension)
        {
            $this->{"add{$extension}"}($builder);
        }
    }

    /**
     * Add the no-tenant extension to the builder.
     * Remove the Tenant scope.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return void
     */
    protected function addRemoveTenant(Builder $builder)
    {
        $builder->macro('removeTenant', function(Builder $builder)
        {
            $this->remove($builder);

            return $builder;
        });
    }

    /**
     * Add the all-tenants extension to the builder.
     * Alias for removeTenant();
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return void
     */
    protected function addAllTenants(Builder $builder)
    {
        return $this->addRemoveTenant($builder);
    }

    /**
     * Add the where-tenant extension to the builder.
     * Include an array of specific tenant ids.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  int|array $arg  a single or array of ids
     * @return void
     */
    protected function addApplyTenant(Builder $builder)
    {
        $builder->macro('applyTenant', function(Builder $builder)
        {
            $this->remove($builder);

            // Position 1 in func_args is from the user input macro
            // parameter. Position 0 is the $builder instance
            $arg = func_get_args()[1];

            if (is_array($arg) && count($arg) <= 1) $arg = $arg[0];

            if (is_null($arg) || empty($arg))
                throw new TenantIdNotSetException;

            $where = (is_array($arg)) ? 'whereIn' : 'where';

            $builder->getQuery()->{$where}($builder->getModel()->getQualifiedTenantColumn(), $arg);

            return $builder;
        });
    }

    /**
     * Get the current Tenant Id.
     *
     * @return mixed int|null
     */
    public static function getTenantId()
    {
        return self::$tenantId;
    }

    /**
     * Get the override state.
     *
     * @return boolean
     */
    public static function getOverride()
    {
        return self::$override;
    }

    /**
     * Manually set a Tenant Id.
     *
     * TenantScope::setTenantId(1);
     *
     * @param  int $tenantId
     */
    public static function setTenantId($tenantId)
    {
        self::$tenantId = $tenantId;
    }

    /**
     * Set the override state.
     *
     * TenantScope::setOverride();
     */
    public static function setOverride()
    {
        self::$override = true;
    }

    /**
     * Determine if the given where clause is a tenant constraint.
     *
     * @param  array   $where
     * @param  string  $column
     *
     * @return bool
     */
    protected function isTenantConstraint(array $where, $column)
    {
        return $where['type'] == 'Basic' && $where['column'] == $column;
    }
}
