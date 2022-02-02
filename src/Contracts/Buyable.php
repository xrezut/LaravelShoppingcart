<?php

namespace Gloudemans\Shoppingcart\Contracts;

use Money\Money;

interface Buyable
{
    /**
     * Get the identifier of the Buyable item.
     *
     * @return int|string
     */
    public function getBuyableIdentifier();

    /**
     * Get the description or title of the Buyable item.
     */
    public function getBuyableDescription(): ?string;

    /**
     * Get the price of the Buyable item.
     */
    public function getBuyablePrice(): Money;

    /**
     * Get the weight of the Buyable item.
     */
    public function getBuyableWeight(): int;
}
