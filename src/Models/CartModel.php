<?php

namespace Gloudemans\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
 
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
}
