<?php

namespace Gloudemans\Shoppingcart;

use Money\Currency;
use Money\Money;

trait CanBeBought
{
    /**
     * Get the identifier of the Buyable item.
     *
     * @return int|string
     */
    public function getBuyableIdentifier(CartItemOptions $options)
    {
        return method_exists($this, 'getKey') ? $this->getKey() : $this->id;
    }

    /**
     * Get the name, title or description of the Buyable item.
     */
    public function getBuyableDescription(CartItemOptions $options): ?string
    {
        if (($name = $this->getAttribute('name'))) {
            return $name;
        } elseif (($title = $this->getAttribute('title'))) {
            return $title;
        } elseif (($description = $this->getAttribute('description'))) {
            return $description;
        } else {
            return null;
        }
    }

    /**
     * Get the price of the Buyable item.
     */
    public function getBuyablePrice(CartItemOptions $options): Money
    {
        if (($price = $this->getAttribute('price')) && ($currency = $this->getAttribute('currency'))) {
            return new Money($price, new Currency($currency));
        }
    }

    /**
     * Get the weight of the Buyable item.
     */
    public function getBuyableWeight(CartItemOptions $options): int
    {
        if (($weight = $this->getAttribute('weight'))) {
            return $weight;
        }

        return 0;
    }

    /**
     * Get the taxRate of the Buyable item.
     */
    public function getBuyableTaxRate(CartItemOptions $options): float
    {
        if (($taxRate = $this->getAttribute('taxRate'))) {
            return $taxRate;
        }

        return config('cart.tax', 0.21);
    }
}
