<?php

namespace Gloudemans\Tests\Shoppingcart;

use Gloudemans\Shoppingcart\CartItem;
use Gloudemans\Shoppingcart\CartItemOptions;
use Gloudemans\Shoppingcart\ShoppingcartServiceProvider;
use Orchestra\Testbench\TestCase;
use Money\Money;
use Money\Currency;

class CartItemTest extends TestCase
{
    /**
     * Set the package service provider.
     *
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [ShoppingcartServiceProvider::class];
    }

    /** @test */
    public function it_can_be_cast_to_an_array()
    {
        $cartItem = new CartItem(1, 'Some item', new Money(1000, new Currency('USD')), 2, 550, new CartItemOptions(['size' => 'XL', 'color' => 'red']));

        $this->assertEquals([
            'id'      => 1,
            'name'    => 'Some item',
            'price'   => '10.00',
            'rowId'   => '07d5da5550494c62daf9993cf954303f',
            'qty'     => 2,
            'options' => [
                'size'  => 'XL',
                'color' => 'red',
            ],
            'tax'      => '0.00',
            'subtotal' => '20.00',
            'total'    => '20.00',
            'discount' => '0.00',
            'weight'   => 550,
        ], $cartItem->toArray());
    }

    /** @test */
    public function it_can_be_cast_to_json()
    {
        $cartItem = new CartItem(1, 'Some item', new Money(1000, new Currency('USD')), 2, 550, new CartItemOptions(['size' => 'XL', 'color' => 'red']));

        $this->assertJson($cartItem->toJson());

        $json = '{"rowId":"07d5da5550494c62daf9993cf954303f","id":1,"name":"Some item","price":"10.00","qty":2,"weight":550,"options":{"size":"XL","color":"red"},"discount":"0.00","subtotal":"20.00","tax":"0.00","total":"20.00"}';

        $this->assertEquals($json, $cartItem->toJson());
    }
}
