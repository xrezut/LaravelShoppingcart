<?php

namespace Gloudemans\Shoppingcart;

use Gloudemans\Shoppingcart\Contracts\Buyable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Money\Money;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Currencies\ISOCurrencies;

class CartItem implements Arrayable, Jsonable
{
    /**
     * The rowID of the cart item.
     */
    public string $rowId;

    /**
     * The ID of the cart item.
     */
    public int|string $id;

    /**
     * The quantity for this cart item.
     */
    public int $qty;

    /**
     * The name of the cart item.
     */
    public string $name;

    /**
     * The price without TAX of the cart item.
     */
    public Money $price;

    /**
     * The weight of the product.
     */
    public int $weight;

    /**
     * The options for this cart item.
     */
    public CartItemOptions $options;

    /**
     * The tax rate for the cart item.
     */
    public float $taxRate = 0;

    /**
     * The FQN of the associated model.
     */
    public ?string $associatedModel = null;

    /**
     * The discount rate for the cart item.
     */
    public float|Money $discount = 0;

    /**
     * The cart instance of the cart item.
     */
    public ?string $instance = null;

    public function __construct(int|string $id, string $name, Money $price, int $qty = 1, int $weight = 0, ?CartItemOptions $options = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->price = $price;
        $this->qty = $qty;
        $this->weight = $weight;
        $this->options = $options ?: new CartItemOptions([]);
        $this->rowId = $this->generateRowId($id, $options->toArray());
    }

    /**
     * Set the quantity for this cart item.
     */
    public function setQuantity(int $qty)
    {
        $this->qty = $qty;
    }

    /**
     * Update the cart item from a Buyable.
     */
    public function updateFromBuyable(Buyable $item): void
    {
        $this->id = $item->getBuyableIdentifier($this->options);
        $this->name = $item->getBuyableDescription($this->options);
        $this->price = $item->getBuyablePrice($this->options);
    }

    /**
     * Update the cart item from an array.
     */
    public function updateFromArray(array $attributes): void
    {
        $this->id = Arr::get($attributes, 'id', $this->id);
        $this->qty = Arr::get($attributes, 'qty', $this->qty);
        $this->name = Arr::get($attributes, 'name', $this->name);
        $this->price = Arr::get($attributes, 'price', $this->price);
        $this->weight = Arr::get($attributes, 'weight', $this->weight);
        $this->options = new CartItemOptions(Arr::get($attributes, 'options', $this->options));

        $this->rowId = $this->generateRowId($this->id, $this->options->all());
    }

    /**
     * Associate the cart item with the given model.
     *
     * @param mixed $model
     */
    public function associate(string|Model $model) : self
    {
        $this->associatedModel = is_string($model) ? $model : get_class($model);

        return $this;
    }

    /**
     * Set the tax rate.
     */
    public function setTaxRate(float $taxRate) : self
    {
        $this->taxRate = $taxRate;

        return $this;
    }

    /**
     * Set the discount rate.
     */
    public function setDiscount(float|Money $discount) : self
    {
        $this->discount = $discount;

        return $this;
    }

    /**
     * Set cart instance.
     */
    public function setInstance(?string $instance) : self
    {
        $this->instance = $instance;

        return $this;
    }

    public function model(): ?Model
    {
        if (isset($this->associatedModel)) {
            return (new $this->associatedModel())->find($this->id);
        }

        return null;
    }

    /**
     * This will is the price of the CartItem considering the set quantity. If you need the single 
     * price just set the parameter to true.
     */
    public function price(): Money
    {
        return $this->price->multiply($this->qty);
    }

    /**
     * This is the discount granted for this CartItem. It is based on the given price and, in case
     * discount is a float, multiplied or, in case it is an absolute Money, subtracted. It will return
     * a minimum value of 0.
     */
    public function discount(): Money
    {
        $price = $this->price();
        if ($this->discount instanceof Money) {
            return $this->price()->subtract($this->discount);
        } else {
            $result = $this->price()->multiply($this->discount, Config::get('cart.rounding', Money::ROUND_UP));
            return $this->price()->multiply($this->discount, Config::get('cart.rounding', Money::ROUND_UP));
        }
    }

    /**
     * This is the final price of the CartItem but without any tax applied. This does on the
     * other hand include any discounts.
     */
    public function subtotal(): Money
    {
        $subtotal = $this->price()->add($this->discount());
        return Money::max(new Money(0, $this->price->getCurrency()), $this->price()->subtract($this->discount()));
    }

    /**
     * This is the tax, based on the subtotal (all previous calculations) and set tax rate
     */
    public function tax(): Money
    {
        $tax = $this->subtotal()->multiply($this->taxRate, Config::get('cart.rounding', Money::ROUND_UP));
        return $this->subtotal()->multiply($this->taxRate, Config::get('cart.rounding', Money::ROUND_UP));
    }

    /**
     * This is the total price, consisting of the subtotal and tax applied.
     */
    public function total(): Money
    {
        return $this->subtotal()->add($this->tax());
    }

    /**
     * This is the total price, consisting of the subtotal and tax applied.
     */
    public function weight(): int
    {
        return $this->qty * $this->weight;
    }

    /**
     * Create a new instance from a Buyable.
     */
    public static function fromBuyable(Buyable $item, int $qty = 1, ?CartItemOptions $options = null) : self
    {
        $options = $options ?: new CartItemOptions([]);
        return new self($item->getBuyableIdentifier($options), $item->getBuyableDescription($options), $item->getBuyablePrice($options), $qty, $item->getBuyableWeight($options), $options);
    }

    /**
     * Create a new instance from the given array.
     */
    public static function fromArray(array $attributes) : self
    {
        $options = new CartItemOptions(Arr::get($attributes, 'options', []));
        return new self($attributes['id'], $attributes['name'], $attributes['price'], $attributes['qty'], $attributes['weight'], $options);
    }

    /**
     * Create a new instance from the given attributes.
     *
     * @param int|string $id
     */
    public static function fromAttributes(int|string $id, string $name, Money $price, int $qty = 1, int $weight = 0, ?CartItemOptions $options = null) : self
    {
        $options = $options ?: new CartItemOptions([]);
        return new self($id, $name, $price, $qty, $weight, $options);
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'rowId'    => $this->rowId,
            'id'       => $this->id,
            'name'     => $this->name,
            'price'    => self::formatMoney($this->price),
            'qty'      => $this->qty,
            'weight'   => $this->weight,
            'options'  => $this->options->toArray(),

            /* Calculated attributes */
            'discount' => self::formatMoney($this->discount()),
            'subtotal' => self::formatMoney($this->subtotal()),
            'tax'      => self::formatMoney($this->tax()),
            'total' => self::formatMoney($this->total()),
        ];
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param int $options
     *
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }
    
    /**
     * Generate a unique id for the cart item.
     */
    private static function formatMoney(Money $money) : string
    {
        return (new DecimalMoneyFormatter(new ISOCurrencies()))->format($money);
    }
    
    /**
     * Generate a unique id for the cart item.
     */
    protected function generateRowId(string $id, array $options) : string
    {
        ksort($options);

        return md5($id . serialize($options));
    }
}