<?php

namespace Gloudemans\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
 
class CartItemModel extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = Config::get('cart.database.tables.cart_item');
  
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
      'row_id',
      'cart_id',
      'price',
      'qty',
      'discount_rate',
      'discount_fixed',
      'taxRate',
      'options'
    ];
  
    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'options' => 'array', // Stored as JSON string, cast to array
    ];
  
    /**
     * Get the CartItems for the cart.
     */
    public function cart()
    {
        return $this->belongsTo(CartModel::class, 'cart_id');
    }
 
    /**
     * This will is the price of the CartItem as Money
     */
    public function price(): Attribute
    {
        return new Attribute(
            get: fn (int $value): Money => new Money($value),
            set: fn (Money $value): int => $value,
        );
    }
 
    /**
     * This will is the price of the CartItem as Money
     */
    public function discountRate(): Attribute
    {
        return new Attribute(
            get: fn (float $value): float => $value,
            set: fn (float $value): float => $value,
        );
    }
 
     /**
     * This will is the price of the CartItem as Money
     */
    public function discountFixed(): Attribute
    {
        return new Attribute(
            get: fn (int $value): Money => new Money($value),
            set: fn (Money $value): int => $value,
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
            get: function ($value, $attributes): Money {
                if (! $attributes['discount_fixed']) {
                    return $attribute['price_all']->subtract($attributes['discount_fixed'])
                } else {
                    return $attribute['price_all']->multiply(sprintf('%.14F', $attributes['discount_rate']), Config::get('cart.rounding', Money::ROUND_UP)),
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
            get: fn ($value, $attributes): Money => Money::max(new Money(0, $attribute['price']->getCurrency()), $attribute['price']->subtract($attribute['discount'])),
        );
    }

    /**
     * This is the tax, based on the subtotal (all previous calculations) and set tax rate.
     */
    public function tax(): Attribute
    {
        return new Attribute(
            get: fn ($value, $attributes): Money => $attribute['subtotal']->multiply(sprintf('%.14F', $attribute['tax_rate']), Config::get('cart.rounding', Money::ROUND_UP)),
        );
    }

    /**
     * This is the total price, consisting of the subtotal and tax applied.
     */
    public function total(): Attribute
    {
        return new Attribute(
            get: fn (): Money => $this->subtotal()->add($this->tax()),
        );
    }

    /**
     * This is the total price, consisting of the subtotal and tax applied.
     */
    public function totalWeight(): Attribute
    {
        return new Attribute(
            get: fn ($value, $attributes): int => $attributes['qty'] * $attributes['weight'],
        );
    }
}
