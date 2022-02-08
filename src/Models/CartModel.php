<?php

namespace Gloudemans\Models;
 
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Money\Money;
 
class CartModel extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = Config::get('cart.database.tables.cart');
  
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
      'identifier',
      'instance',
    ];
  
    /**
     * Get the CartItems for the cart.
     */
    public function items()
    {
        return $this->hasMany(CartItem::class);
    }
 
    /**
     * This will is the price of the CartItem considering the set quantity. If you need the single
     * price just set the parameter to true.
     */
    public function price(): Attribute
    {
        return new Attribute(
            get: fn (int $value) => new Money($value),
            set: fn (Money $value) => $value,
        );
    }
    
    /**
     * This will is the price of the CartItem considering the set quantity. If you need the single
     * price just set the parameter to true.
     */
    public function priceAll(): Attribute
    {
        return new Attribute(
            get: fn () => $this->price->multiply($this->qty),
        );
    }

    /**
     * This is the discount granted for this CartItem. It is based on the given price and, in case
     * discount is a float, multiplied or, in case it is an absolute Money, subtracted. It will return
     * a minimum value of 0.
     */
    public function discount(): Attribute
    {
        return new Attribute(
            get: fn () => {
                if ($this->discount instanceof Money) {
                    return $this->price_all->subtract($this->discount)
                } else {
                    return $this->price_all->multiply(sprintf('%.14F', $this->discount), Config::get('cart.rounding', Money::ROUND_UP)),
                }
            },
        );
    }

    /**
     * This is the final price of the CartItem but without any tax applied. This does on the
     * other hand include any discounts.
     */
    public function subtotal(): Attribute
    {
        return new Attribute(
            get: fn () => Money::max(new Money(0, $this->price->getCurrency()), $this->price()->subtract($this->discount())),
        );
    }

    /**
     * This is the tax, based on the subtotal (all previous calculations) and set tax rate.
     */
    public function tax(): Attribute
    {
        return new Attribute(
            get: fn () => $this->subtotal()->multiply(sprintf('%.14F', $this->taxRate), Config::get('cart.rounding', Money::ROUND_UP)),
        );
    }

    /**
     * This is the total price, consisting of the subtotal and tax applied.
     */
    public function total(): Attribute
    {
        return new Attribute(
            get: fn () => $this->subtotal()->add($this->tax()),
        );
    }

    /**
     * This is the total price, consisting of the subtotal and tax applied.
     */
    public function totalWeight(): Attribute
    {
        return new Attribute(
            get: fn () => $this->qty * $this->weight,
        );
    }
}
