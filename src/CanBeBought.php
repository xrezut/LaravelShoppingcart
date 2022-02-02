<?php

namespace Gloudemans\Shoppingcart;

use Money\Money;

trait CanBeBought
{
    /**
     * Get the identifier of the Buyable item.
     *
     * @return int|string
     */
    public function getBuyableIdentifier()
    {
        return method_exists($this, 'getKey') ? $this->getKey() : $this->id;
    }

    /**
     * Get the name, title or description of the Buyable item.
     */
    public function getBuyableDescription(): ?string
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
    public function getBuyablePrice(): Money
    {
        if (($price = $this->getAttribute('price'))) {
            return new Money($price);
        }
    }

    /**
     * Get the weight of the Buyable item.
     */
    public function getBuyableWeight(): int
    {
        if (($weight = $this->getAttribute('weight'))) {
            return $weight;
        }

        return 0;
    }
}
