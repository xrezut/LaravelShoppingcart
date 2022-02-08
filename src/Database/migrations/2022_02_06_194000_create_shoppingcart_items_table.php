<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class CreateShoppingcartItemsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create(Config::get('cart.database.tables.cart_item'), function (Blueprint $table) {
            /* RowID of the CartItem, based on id and options */
            $table->string('row_id', 32)->index();

            /* Relation CartItem to Cart */
            $table->unsignedBigInteger('cart_id');
            $table->foreign('cart_id')->references('id')->on(Config::get('cart.database.tables.cart'));

            /* Make id and rowid primary so there can not be duplicates */
            $table->primary(['cart_id', 'row_id']);

            /* The price of the CartItem */
            $table->unsignedBigInteger('price')->nullable();
            $table->integer('qty')->nullable();
            $table->float('discount_rate')->nullable();
            $table->unsignedBigInteger('discount_fixed')->nullable();
            $table->float('tax_rate')->nullable();

            /* Custom-Options of the CartItem */
            $table->json('options');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::drop(Config::get('cart.database.tables.cart_item'));
    }
}
