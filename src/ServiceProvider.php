<?php

namespace Minhbang\Locale;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

/**
 * Class ServiceProvider
 *
 * @package Minhbang\Locale
 */
class ServiceProvider extends BaseServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes(
            [
                __DIR__ . '/../config/locale.php' => config_path('locale.php'),
            ]
        );
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/locale.php', 'locale');
        $this->app['locale-manager'] = $this->app->share(
            function () {
                return new Manager();
            }
        );
        // add Category alias
        $this->app->booting(
            function () {
                AliasLoader::getInstance()->alias('LocaleManager', Facade::class);
            }
        );
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['locale-manager'];
    }
}
