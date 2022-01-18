<?php

namespace Gloudemans\Shoppingcart\Calculation;

use Gloudemans\Shoppingcart\CartItem;
use Gloudemans\Shoppingcart\Contracts\Calculator;
use Money\Money;

class DefaultCalculator implements Calculator
{
    public static function getAttribute(string $attribute, CartItem $cartItem)
    {
        $decimals = config('cart.format.decimals', 2);

        switch ($attribute) {
            case 'discount':
                return $cartItem->price->multiply($cartItem->discountRate, config('cart.rounding', Money::ROUND_UP));
            case 'tax':
                return $cartItem->priceTarget->multiply($cartItem->taxRate + 1,  config('cart.rounding', Money::ROUND_UP));
            case 'priceTax':
                return $cartItem->priceTarget->add($cartItem->tax);
            case 'discountTotal':
                return $cartItem->discount->multiply($cartItem->qty, config('cart.rounding', Money::ROUND_UP));
            case 'priceTotal':
                return $cartItem->price->multiply($cartItem->qty, config('cart.rounding', Money::ROUND_UP));
            case 'subtotal':
                $subtotal = $cartItem->priceTotal->subtract($cartItem->discountTotal);
                return $subtotal->isPositive() ? $subtotal : new Money(0, $this->price->getCurrency());
            case 'priceTarget':
                return $cartItem->priceTotal->subtract($cartItem->discountTotal)->divide($cartItem->qty);
            case 'taxTotal':
                return $cartItem->subtotal->multiply($cartItem->taxRate + 1,  config('cart.rounding', Money::ROUND_UP));
            case 'total':
                return $cartItem->subtotal->add($cartItem->taxTotal);
            default:
                return;
        }
    }
}
