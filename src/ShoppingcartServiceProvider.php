<?php

namespace Gloudemans\Shoppingcart;

use Illuminate\Auth\Events\Logout;
use Illuminate\Session\SessionManager;
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
        $this->app->bind('cart', 'Gloudemans\Shoppingcart\Cart');

        $config = __DIR__.'/Config/cart.php';
        $this->mergeConfigFrom($config, 'cart');

        $this->publishes([__DIR__.'/Config/cart.php' => config_path('cart.php')], 'config');

        $this->publishes([
            realpath(__DIR__.'/Database/migrations') => $this->app->databasePath().'/migrations',
        ], 'migrations');
    }
}
