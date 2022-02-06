<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class CreateShoppingcartTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create(Config::get('cart.database.tables.cart'), function (Blueprint $table) {
            /* Primary identifier of the cart, i.e. uinque user id */
            $table->string('identifier')->index();

            /* Instance of the cart to allow for multiple carts per identifier */
            $table->string('instance')->index();

            /* Make primary key a combination of booth */
            $table->primary(['identifier', 'instance']);

            /* Content of the cart */
            $table->longText('content');

            /* Allow empty timestamps */
            $table->nullableTimestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::drop(Config::get('cart.database.tables.cart'));
    }
}
