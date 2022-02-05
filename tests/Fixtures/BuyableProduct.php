<?php

namespace Gloudemans\Tests\Shoppingcart\Fixtures;

use Gloudemans\Shoppingcart\Contracts\Buyable;
use Gloudemans\Shoppingcart\CartItemOptions;
use Illuminate\Database\Eloquent\Model;
use Money\Money;
use Money\Currency;

class BuyableProduct extends Model implements Buyable
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'name',
        'title',
        'description',
        'price',
        'currency',
        'weight',
    ];

    protected $attributes = [
        'id'       => 1,
        'name'     => 'Item name',
        'price'    => '10.00',
        'currency' => 'USD',
        'weight'   => 0,
    ];

    /**
     * Get the identifier of the Buyable item.
     *
     * @return int|string
     */
    public function getBuyableIdentifier(CartItemOptions $options)
    {
        return $this->id;
    }

    /**
     * Get the description or title of the Buyable item.
     *
     * @return string
     */
    public function getBuyableDescription(CartItemOptions $options) : ?string
    {
        return $this->name;
    }

    /**
     * Get the price of the Buyable item.
     */
    public function getBuyablePrice(CartItemOptions $options): Money
    {
        return new Money($this->price, new Currency($this->currency));
    }

    /**
     * Get the price of the Buyable item.
     */
    public function getBuyableWeight(CartItemOptions $options): int
    {
        return $this->weight;
    }
}
