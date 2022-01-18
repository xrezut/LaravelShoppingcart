<?php

namespace Gloudemans\Shoppingcart\Contracts;

use Money\Money;

interface InstanceIdentifier
{
    /**
     * Get the unique identifier to load the Cart from.
     *
     * @return int|string
     */
    public function getInstanceIdentifier();

    /**
     * Get the unique identifier to load the Cart from.
     *
     * @return int|string
     */
    public function getInstanceGlobalDiscount(): float;
}
