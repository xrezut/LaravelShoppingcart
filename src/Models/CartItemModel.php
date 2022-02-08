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
            get: fn (int $value) => new Money($value),
            set: fn (Money $value) => $value,
        );
    }
}
