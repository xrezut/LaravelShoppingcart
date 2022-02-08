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
      'discount_rate'
    ];
  
    /**
     * Get the CartItems for the cart.
     */
    public function items()
    {
        return $this->hasMany(CartItem::class);
    }
}
