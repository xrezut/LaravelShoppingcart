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
      'tax_rate'
    ];
 
     /**
     * The relationships that should always be loaded.
     *
     * @var array
     */
    protected $with = ['items'];
  
    /**
     * Get the CartItems for the cart.
     */
    public function items()
    {
        return $this->hasMany(CartItem::class);
    }
 
    /**
     * This will is the price of the CartItem as Money
     */
    public function price(): Attribute
    {
        return new Attribute(
            get: function (): Money {
                $sum = new Money(0, 'USD');
             
                foreach (this->items as $item) {
                    $sum->add($item->price_all);
                }
             
                return $sum;
            },
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
            get: function (): Money {
                $sum = new Money(0, 'USD');
             
                foreach (this->items as $item) {
                    $sum->add($item->discount);
                }
             
                return $sum;
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
            get: function (): Money {
                $sum = new Money(0, 'USD');
             
                foreach (this->items as $item) {
                    $sum->add($item->subtotal);
                }
             
                return $sum;
            },
        );
    }

    /**
     * This is the tax, based on the subtotal (all previous calculations) and set tax rate.
     */
    public function tax(): Attribute
    {
        return new Attribute(
            get: function (): Money {
                $sum = new Money(0, 'USD');
             
                foreach (this->items as $item) {
                    $sum->add($item->tax);
                }
             
                return $sum;
            },
        );
    }

    /**
     * This is the total price, consisting of the subtotal and tax applied.
     */
    public function total(): Attribute
    {
        return new Attribute(
            get: function (): Money {
                $sum = new Money(0, 'USD');
             
                foreach (this->items as $item) {
                    $sum->add($item->total);
                }
             
                return $sum;
            },
        );
    }

    /**
     * This is the total price, consisting of the subtotal and tax applied.
     */
    public function weight(): Attribute
    {
        return new Attribute(
            get: function (): int {
                $sum = 0;
             
                foreach (this->items as $item) {
                    $sum += $this->qty * $this->weight;
                }
             
                return $sum;
            },
        );
    }
}
