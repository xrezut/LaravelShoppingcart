<?php

namespace Gloudemans\Shoppingcart\Contracts;

use Gloudemans\Shoppingcart\CartItemOptions;
use Money\Money;

interface Buyable
{
    /**
     * Get the identifier of the Buyable item.
     *
     * @return int|string
     */
    public function getBuyableIdentifier(CartItemOptions $options);

    /**
     * Get the description or title of the Buyable item.
     */
    public function getBuyableDescription(CartItemOptions $options): ?string;

    /**
     * Get the price of the Buyable item.
     */
    public function getBuyablePrice(CartItemOptions $options): Money;

    /**
     * Get the weight of the Buyable item.
     */
    public function getBuyableWeight(CartItemOptions $options): int;

    /**
     * Get the taxRate of the Buyable item.
     */
    public function getBuyableTaxRate(CartItemOptions $options): float;
}
