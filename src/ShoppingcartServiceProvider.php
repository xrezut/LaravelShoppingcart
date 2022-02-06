<?php

namespace Gloudemans\Shoppingcart;

use Illuminate\Support\ServiceProvider;

class ShoppingcartServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        /* Bind Cart class to cart for Facade usage */
        $this->app->bind('cart', 'Gloudemans\Shoppingcart\Cart');

        /* Determine where the config file is located */
        $config = __DIR__.'/Config/cart.php';

        /* Use local config */
        $this->mergeConfigFrom($config, 'cart');

        /* Also allow publishing to overwrite local config */
        $this->publishes([$config => config_path('cart.php')], 'config');

        /* Publish included migrations */
        $this->publishes([
            realpath(__DIR__.'/Database/migrations') => $this->app->databasePath().'/migrations',
        ], 'migrations');
    }
}
