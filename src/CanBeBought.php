<?php

namespace Gloudemans\Shoppingcart;

use Gloudemans\Shoppingcart\CartItemOptions;
use Money\Money;
use Money\Currency;

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
        } else if (($title = $this->getAttribute('title'))) {
            return $title;
        } else if (($description = $this->getAttribute('description'))) {
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
}
