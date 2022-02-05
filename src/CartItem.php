<?php

namespace Gloudemans\Shoppingcart;

use Gloudemans\Shoppingcart\Calculation\DefaultCalculator;
use Gloudemans\Shoppingcart\Contracts\Buyable;
use Gloudemans\Shoppingcart\Contracts\Calculator;
use Gloudemans\Shoppingcart\Exceptions\InvalidCalculatorException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Arr;
use Money\Money;
use ReflectionClass;

/**
 * @property-read mixed discount
 * @property-read float discountTotal
 * @property-read float priceTarget
 * @property-read float priceNet
 * @property-read float priceTotal
 * @property-read float subtotal
 * @property-read float taxTotal
 * @property-read float tax
 * @property-read float total
 * @property-read float priceTax
 */
class CartItem implements Arrayable, Jsonable
{
    /**
     * The rowID of the cart item.
     */
    public string $rowId;

    /**
     * The ID of the cart item.
     *
     * @var int|string
     */
    public $id;

    /**
     * The quantity for this cart item.
     */
    public int $qty;

    /**
     * The name of the cart item.
     *
     * @var string
     */
    public string $name;

    /**
     * The price without TAX of the cart item.
     */
    public Money $price;

    /**
     * The weight of the product.
     *
     * @var float
     */
    public $weight;

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
     *
     * @var string|null
     */
    private $associatedModel = null;

    /**
     * The discount rate for the cart item.
     */
    public float $discountRate = 0;

    /**
     * The cart instance of the cart item.
     */
    public ?string $instance = null;

    /**
     * CartItem constructor.
     *
     * @param int|string $id
     * @param string     $name
     * @param float      $price
     * @param float      $weight
     * @param array      $options
     */
    public function __construct($id, string $name, Money $price, int $qty = 1, int $weight = 0, ?CartItemOptions $options = null)
    {
        if (!is_string($id) && !is_int($id)) {
            throw new \InvalidArgumentException('Please supply a valid identifier.');
        }

        $this->id = $id;
        $this->name = $name;
        $this->price = $price;
        $this->qty = $qty;
        $this->weight = $weight;
        $this->options = $options ?: new CartItemOptions([]);
        $this->rowId = $this->generateRowId($id, $options);
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
    public function associate($model) : self
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
    public function setDiscount(float $discount) : self
    {
        $this->discountRate = $discountRate;

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
    
    /**
     * Get an attribute from the cart item or get the associated model.
     *
     * @return mixed
     */
    public function __get(string $attribute)
    {
        if (property_exists($this, $attribute)) {
            return $this->{$attribute};
        }
        $decimals = config('cart.format.decimals', 2);

        switch ($attribute) {
            case 'model':
                if (isset($this->associatedModel)) {
                    return with(new $this->associatedModel())->find($this->id);
                }
                // no break
            case 'modelFQCN':
                if (isset($this->associatedModel)) {
                    return $this->associatedModel;
                }
                // no break
            case 'weightTotal':
                return round($this->weight * $this->qty, $decimals);
        }

        $class = new ReflectionClass(config('cart.calculator', DefaultCalculator::class));
        if (!$class->implementsInterface(Calculator::class)) {
            throw new InvalidCalculatorException('The configured Calculator seems to be invalid. Calculators have to implement the Calculator Contract.');
        }

        return call_user_func($class->getName().'::getAttribute', $attribute, $this);
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
        $options = Arr::get($attributes, 'options', []);

        return new self($attributes['id'], $attributes['name'], $attributes['price'], $attributes['weight'], $options);
    }

    /**
     * Create a new instance from the given attributes.
     *
     * @param int|string $id
     */
    public static function fromAttributes($id, string $name, Money $price, int $weight, array $options = []) : self
    {
        return new self($id, $name, $price, $weight, $options);
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
            'qty'      => $this->qty,
            'price'    => $this->price,
            'weight'   => $this->weight,
            'options'  => $this->options->toArray(),
            'discount' => $this->discount,
            'tax'      => $this->tax,
            'subtotal' => $this->subtotal,
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
    protected function generateRowId(string $id, array $options) : string
    {
        ksort($options);

        return md5($id . serialize($options));
    }
}
