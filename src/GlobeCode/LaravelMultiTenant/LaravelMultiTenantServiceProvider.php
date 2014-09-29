<?php namespace GlobeCode\LaravelMultiTenant;

use Illuminate\Support\ServiceProvider;

class LaravelMultiTenantServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->package('globecode/laravel-multitenant');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		// Register our config
		$this->app['config']->package('globecode/laravel-multitenant', __DIR__ . '/../../config');
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array();
	}

}
