<?php

namespace Gloudemans\Shoppingcart;

use Closure;
use InvalidArgumentException;
use Carbon\Carbon;
use Money\Money;
use Money\Currency;
use Gloudemans\Shoppingcart\Contracts\Buyable;
use Gloudemans\Shoppingcart\Contracts\InstanceIdentifier;
use Gloudemans\Shoppingcart\Exceptions\CartAlreadyStoredException;
use Gloudemans\Shoppingcart\Exceptions\InvalidRowIDException;
use Gloudemans\Shoppingcart\Exceptions\UnknownModelException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Support\Facades\Config;

class Cart
{
    use Macroable;

    const DEFAULT_INSTANCE = 'default';

    /**
     * Instance of the session manager.
     */
    private \Illuminate\Session\SessionManager $session;

    /**
     * Instance of the event dispatcher.
     */
    private \Illuminate\Contracts\Events\Dispatcher $events;

    /**
     * Holds the current cart instance.
     */
    private string $instance;

    /**
     * Holds the creation date of the cart.
     */
    private ?Carbon $createdAt = null;

    /**
     * Holds the update date of the cart.
     */
    private ?Carbon $updatedAt = null;

    /**
     * Defines the discount percentage.
     */
    private float $discount = 0;

    /**
     * Defines the tax rate.
     *
     * @var float
     */
    private $taxRate = 0;

    /**
     * Cart constructor.
     *
     * @param \Illuminate\Session\SessionManager      $session
     * @param \Illuminate\Contracts\Events\Dispatcher $events
     */
    public function __construct(SessionManager $session, Dispatcher $events)
    {
        $this->session = $session;
        $this->events = $events;
        $this->taxRate = Config::get('cart.tax');

        $this->instance(self::DEFAULT_INSTANCE);
    }

    /**
     * Set the current cart instance.
     *
     * @param string|InstanceIdentifier|null $instance
     *
     * @return \Gloudemans\Shoppingcart\Cart
     */
    public function instance($instance = null): self
    {
        $instance = $instance ?: self::DEFAULT_INSTANCE;

        if ($instance instanceof InstanceIdentifier) {
            $this->discount = $instance->getInstanceGlobalDiscount();
            $instance = $instance->getInstanceIdentifier();
        }

        $this->instance = 'cart.'.$instance;

        return $this;
    }

    /**
     * Get the current cart instance.
     *
     * @return string
     */
    public function currentInstance(): string
    {
        return str_replace('cart.', '', $this->instance);
    }

    /**
     * Add an item to the cart.
     */
    public function add(int|string|Buyable|array $id, null|string|int $nameOrQty = null, null|int|CartItemOptions $qtyOrOptions = null, ?Money $price = null, ?int $weight = null, ?CartItemOptions $options = null): CartItem|array
    {
        /* Allow adding a CartItem by raw parameters */
        if (is_int($id) || is_string($id)) {
            if (! is_null($nameOrQty) && ! is_string($nameOrQty)) {
                throw new InvalidArgumentException('$nameOrQty must be of type string (name) or null when adding with raw parameters');
            }
            
            if (! is_null($qtyOrOptions) && ! is_int($qtyOrOptions)) {
                throw new InvalidArgumentException('$nameOrQty must be of type int (quantity) or null when adding with raw parameters');
            }
            
            return $this->addCartItem(CartItem::fromAttributes($id, $nameOrQty, $price, $qtyOrOptions ?: 1, $weight ?: 0, $options ?: new CartItemOptions([])));
        }
        /* Also allow passing a Buyable instance, get data from the instance rather than parameters */
        else if ($id instanceof Buyable) {
            if (! is_null($qtyOrOptions) && ! is_int($nameOrQty)) {
                throw new InvalidArgumentException('$nameOrQty must be of type int (quantity) when adding a Buyable instance');
            }
            
            if (! is_null($qtyOrOptions) && ! $qtyOrOptions instanceof CartItemOptions) {
                throw new InvalidArgumentException('$qtyOrOptions must be of type CartItemOptions (options) or null when adding a Buyable instance');
            }
            
            $cartItem = CartItem::fromBuyable($id, $nameOrQty ?: 1, $qtyOrOptions ?: new CartItemOptions([]));

            if ($id instanceof Model) {
                $cartItem->associate($id);
            }
            
            return $this->addCartItem($cartItem);
        }
        /* Also allow passing multiple definitions at the same time, simply call same method and collec return value */
        else if (is_array($id)) {
            /* Check if this iterable contains instances */
            if (is_array(head($id)) || head($id) instanceof Buyable) {
                return array_map(function (Buyable|iterable $item) {
                    return $this->add($item);
                }, $id);
            }
            /* Treat the array itself as an instance */
            else {
                $cartItem = CartItem::fromArray($id);
                
                return $this->addCartItem($cartItem);
            }
        }
        /* Due to PHP8 union types this should never happen */
        else {
            throw new InvalidArgumentException('$id must be of type int|string|Buyable|Iterable');
        }
    }

    /**
     * Add an item to the cart.
     *
     * @param \Gloudemans\Shoppingcart\CartItem $item          Item to add to the Cart
     * @param bool                              $keepDiscount  Keep the discount rate of the Item
     * @param bool                              $keepTax       Keep the Tax rate of the Item
     * @param bool                              $dispatchEvent
     *
     * @return \Gloudemans\Shoppingcart\CartItem The CartItem
     */
    public function addCartItem(CartItem $item, bool $keepDiscount = false, bool $keepTax = false, bool $dispatchEvent = true): CartItem
    {
        $item->setInstance($this->currentInstance());
        
        if (! $keepDiscount) {
            $item->setDiscount($this->discount);
        }

        if (!$keepTax) {
            $item->setTaxRate($this->taxRate);
        }

        $content = $this->getContent();

        if ($content->has($item->rowId)) {
            $item->qty += $content->get($item->rowId)->qty;
        }

        $content->put($item->rowId, $item);

        if ($dispatchEvent) {
            $this->events->dispatch('cart.adding', $item);
        }

        $this->session->put($this->instance, $content);

        if ($dispatchEvent) {
            $this->events->dispatch('cart.added', $item);
        }

        return $item;
    }

    /**
     * Update the cart item with the given rowId.
     *
     * @param string $rowId
     * @param mixed  $qty
     *
     * @return \Gloudemans\Shoppingcart\CartItem
     */
    public function update(string $rowId, $qty): ?CartItem
    {
        $cartItem = $this->get($rowId);

        if ($qty instanceof Buyable) {
            $cartItem->updateFromBuyable($qty);
        } elseif (is_array($qty)) {
            $cartItem->updateFromArray($qty);
        } else {
            $cartItem->qty = $qty;
        }

        $content = $this->getContent();

        if ($rowId !== $cartItem->rowId) {
            $itemOldIndex = $content->keys()->search($rowId);

            $content->pull($rowId);

            if ($content->has($cartItem->rowId)) {
                $existingCartItem = $this->get($cartItem->rowId);
                $cartItem->setQuantity($existingCartItem->qty + $cartItem->qty);
            }
        }

        if ($cartItem->qty <= 0) {
            $this->remove($cartItem->rowId);

            return null;
        } else {
            if (isset($itemOldIndex)) {
                $content = $content->slice(0, $itemOldIndex)
                    ->merge([$cartItem->rowId => $cartItem])
                    ->merge($content->slice($itemOldIndex));
            } else {
                $content->put($cartItem->rowId, $cartItem);
            }
        }

        $this->events->dispatch('cart.updating', $cartItem);

        $this->session->put($this->instance, $content);

        $this->events->dispatch('cart.updated', $cartItem);

        return $cartItem;
    }

    /**
     * Remove the cart item with the given rowId from the cart.
     *
     * @param string $rowId
     *
     * @return void
     */
    public function remove(string $rowId): void
    {
        $cartItem = $this->get($rowId);

        $content = $this->getContent();

        $content->pull($cartItem->rowId);

        $this->events->dispatch('cart.removing', $cartItem);

        $this->session->put($this->instance, $content);

        $this->events->dispatch('cart.removed', $cartItem);
    }

    /**
     * Get a cart item from the cart by its rowId.
     *
     * @param string $rowId
     *
     * @return \Gloudemans\Shoppingcart\CartItem
     */
    public function get(string $rowId): CartItem
    {
        $content = $this->getContent();

        if (!$content->has($rowId)) {
            throw new InvalidRowIDException("The cart does not contain rowId {$rowId}.");
        }

        $cartItem = $content->get($rowId);

        if ($cartItem instanceof CartItem) {
            return $cartItem;
        }
    }

    /**
     * Destroy the current cart instance.
     *
     * @return void
     */
    public function destroy(): void
    {
        $this->session->remove($this->instance);
    }

    /**
     * Get the content of the cart.
     *
     * @return \Illuminate\Support\Collection
     */
    public function content(): Collection
    {
        if (is_null($this->session->get($this->instance))) {
            return new Collection([]);
        }

        return $this->session->get($this->instance);
    }

    /**
     * Get the total quantity of all CartItems in the cart.
     */
    public function count(): int
    {
        return $this->getContent()->sum('qty');
    }

    /**
     * Get the amount of CartItems in the Cart.
     * Keep in mind that this does NOT count quantity.
     */
    public function countItems(): int
    {
        return $this->getContent()->count();
    }

    /**
     * Get the discount of the items in the cart.
     *
     * @return Money
     */
    public function price(): Money
    {
        $calculated = $this->getContent()->reduce(function (Money $discount, CartItem $cartItem) {
            return $discount->add($cartItem->price());
        }, new Money(0, new Currency('USD')));

        if ($calculated instanceof Money) {
            return $calculated;
        } else {
            throw new \TypeError("Calculated price is not an instance of Money");
        }
    }

    /**
     * Get the discount of the items in the cart.
     *
     * @return float
     */
    public function discount(): Money
    {
        $calculated = $this->getContent()->reduce(function (Money $discount, CartItem $cartItem) {
            return $discount->add($cartItem->discount());
        }, new Money(0, new Currency('USD')));

        if ($calculated instanceof Money) {
            return $calculated;
        } else {
            throw new \TypeError("Calculated discount is not an instance of Money");
        }
    }

    /**
     * Get the subtotal (total - tax) of the items in the cart.
     */
    public function subtotal(): Money
    {
        $calculated = $this->getContent()->reduce(function (Money $subTotal, CartItem $cartItem) {
            return $subTotal->add($cartItem->subtotal());
        }, new Money(0, new Currency('USD')));

        if ($calculated instanceof Money) {
            return $calculated;
        } else {
            throw new \TypeError("Calculated subtotal is not an instance of Money");
        }
    }
    
    /**
     * Get the total tax of the items in the cart.
     */
    public function tax(): Money
    {
        $calculated = $this->getContent()->reduce(function (Money $tax, CartItem $cartItem) {
            return $tax->add($cartItem->tax());
        }, new Money(0, new Currency('USD')));

        if ($calculated instanceof Money) {
            return $calculated;
        }
    }

    /**
     * Get the total price of the items in the cart.
     */
    public function total(): Money
    {
        $calculated = $this->getContent()->reduce(function (Money $total, CartItem $cartItem) {
            return $total->add($cartItem->total());
        }, new Money(0, new Currency('USD')));

        if ($calculated instanceof Money) {
            return $calculated;
        } else {
            throw new \TypeError("Calculated total is not an instance of Money");
        }
    }

    /**
     * Get the total weight of the items in the cart.
     */
    public function weight(): int
    {
        $calculated = $this->getContent()->reduce(function (int $total, CartItem $cartItem) {
            return $total + $cartItem->weight();
        }, 0);

        if (is_int($calculated)) {
            return $calculated;
        } else {
            throw new \TypeError("Calculated weight was not an integer");
        }
    }

    /**
     * Search the cart content for a cart item matching the given search closure.
     *
     * @param \Closure $search
     *
     * @return \Illuminate\Support\Collection
     */
    public function search(Closure $search): Collection
    {
        return $this->getContent()->filter($search);
    }

    /**
     * Associate the cart item with the given rowId with the given model.
     *
     * @param string $rowId
     * @param mixed  $model
     *
     * @return void
     */
    public function associate(string $rowId, $model): void
    {
        if (is_string($model) && !class_exists($model)) {
            throw new UnknownModelException("The supplied model {$model} does not exist.");
        }

        $cartItem = $this->get($rowId);

        $cartItem->associate($model);

        $content = $this->getContent();

        $content->put($cartItem->rowId, $cartItem);

        $this->session->put($this->instance, $content);
    }

    /**
     * Set the tax rate for the cart item with the given rowId.
     *
     * @param string    $rowId
     * @param int|float $taxRate
     *
     * @return void
     */
    public function setTax(string $rowId, $taxRate): void
    {
        $cartItem = $this->get($rowId);

        $cartItem->setTaxRate($taxRate);

        $content = $this->getContent();

        $content->put($cartItem->rowId, $cartItem);

        $this->session->put($this->instance, $content);
    }

    /**
     * Set the global tax rate for the cart.
     * This will set the tax rate for all items.
     *
     * @param float $discount
     */
    public function setGlobalTax($taxRate): void
    {
        $this->taxRate = $taxRate;

        $content = $this->getContent();
        if ($content && $content->count()) {
            $content->each(function ($item, $key) {
                $item->setTaxRate($this->taxRate);
            });
        }
    }

    /**
     * Set the discount rate for the cart item with the given rowId.
     *
     * @param string    $rowId
     * @param int|float $taxRate
     *
     * @return void
     */
    public function setDiscount(string $rowId, $discount): void
    {
        $cartItem = $this->get($rowId);

        $cartItem->setDiscount($discount);

        $content = $this->getContent();

        $content->put($cartItem->rowId, $cartItem);

        $this->session->put($this->instance, $content);
    }

    /**
     * Set the global discount percentage for the cart.
     * This will set the discount for all cart items.
     *
     * @param float $discount
     *
     * @return void
     */
    public function setGlobalDiscount(float $discount): void
    {
        $this->discount = $discount;

        $content = $this->getContent();
        if ($content && $content->count()) {
            $content->each(function ($item, $key) {
                $item->setDiscountRate($this->discount);
            });
        }
    }

    /**
     * Store an the current instance of the cart.
     *
     * @param mixed $identifier
     *
     * @return void
     */
    public function store($identifier): void
    {
        $content = $this->getContent();

        if ($identifier instanceof InstanceIdentifier) {
            $identifier = $identifier->getInstanceIdentifier();
        }

        $instance = $this->currentInstance();

        if ($this->storedCartInstanceWithIdentifierExists($instance, $identifier)) {
            throw new CartAlreadyStoredException("A cart with identifier {$identifier} was already stored.");
        }

        $this->getConnection()->table(self::getTableName())->insert([
            'identifier' => $identifier,
            'instance'   => $instance,
            'content'    => serialize($content),
            'created_at' => $this->createdAt ?: Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $this->events->dispatch('cart.stored');
    }

    /**
     * Restore the cart with the given identifier.
     *
     * @param mixed $identifier
     *
     * @return void
     */
    public function restore($identifier): void
    {
        if ($identifier instanceof InstanceIdentifier) {
            $identifier = $identifier->getInstanceIdentifier();
        }

        $currentInstance = $this->currentInstance();

        if (!$this->storedCartInstanceWithIdentifierExists($currentInstance, $identifier)) {
            return;
        }

        $stored = $this->getConnection()->table(self::getTableName())
            ->where(['identifier'=> $identifier, 'instance' => $currentInstance])->first();

        $storedContent = unserialize(data_get($stored, 'content'));

        $this->instance(data_get($stored, 'instance'));

        $content = $this->getContent();

        foreach ($storedContent as $cartItem) {
            $content->put($cartItem->rowId, $cartItem);
        }

        $this->events->dispatch('cart.restored');

        $this->session->put($this->instance, $content);

        $this->instance($currentInstance);

        $this->createdAt = Carbon::parse(data_get($stored, 'created_at'));
        $this->updatedAt = Carbon::parse(data_get($stored, 'updated_at'));

        $this->getConnection()->table(self::getTableName())->where(['identifier' => $identifier, 'instance' => $currentInstance])->delete();
    }

    /**
     * Erase the cart with the given identifier.
     *
     * @param mixed $identifier
     *
     * @return void
     */
    public function erase($identifier): void
    {
        if ($identifier instanceof InstanceIdentifier) {
            $identifier = $identifier->getInstanceIdentifier();
        }

        $instance = $this->currentInstance();

        if (!$this->storedCartInstanceWithIdentifierExists($instance, $identifier)) {
            return;
        }

        $this->getConnection()->table(self::getTableName())->where(['identifier' => $identifier, 'instance' => $instance])->delete();

        $this->events->dispatch('cart.erased');
    }

    /**
     * Merges the contents of another cart into this cart.
     *
     * @param mixed $identifier   Identifier of the Cart to merge with.
     * @param bool  $keepDiscount Keep the discount of the CartItems.
     * @param bool  $keepTax      Keep the tax of the CartItems.
     * @param bool  $dispatchAdd  Flag to dispatch the add events.
     *
     * @return bool
     */
    public function merge($identifier, bool $keepDiscount = false, bool $keepTax = false, bool $dispatchAdd = true, $instance = self::DEFAULT_INSTANCE): bool
    {
        if (!$this->storedCartInstanceWithIdentifierExists($instance, $identifier)) {
            return false;
        }

        $stored = $this->getConnection()->table(self::getTableName())
            ->where(['identifier'=> $identifier, 'instance'=> $instance])->first();

        $storedContent = unserialize($stored->content);

        foreach ($storedContent as $cartItem) {
            $this->addCartItem($cartItem, $keepDiscount, $keepTax, $dispatchAdd);
        }

        $this->events->dispatch('cart.merged');

        return true;
    }

    /**
     * Magic method to make accessing the total, tax and subtotal properties possible.
     *
     * @param string $attribute
     */
    public function __get(string $attribute): ?Money
    {
        switch ($attribute) {
            case 'total':
                return $this->total();
            case 'tax':
                return $this->tax();
            case 'subtotal':
                return $this->subtotal();
            default:
                return null;
        }
    }

    /**
     * Get the carts content, if there is no cart content set yet, return a new empty Collection.
     */
    protected function getContent(): Collection
    {
        if ($this->session->has($this->instance)) {
            return $this->session->get($this->instance);
        }

        return new Collection();
    }

    /**
     * @param $identifier
     */
    private function storedCartInstanceWithIdentifierExists(string $instance, string $identifier): bool
    {
        return $this->getConnection()->table(self::getTableName())->where(['identifier' => $identifier, 'instance'=> $instance])->exists();
    }

    /**
     * Get the database connection.
     */
    private function getConnection(): \Illuminate\Database\Connection
    {
        return app(DatabaseManager::class)->connection($this->getConnectionName());
    }

    /**
     * Get the database table name.
     *
     * @return string
     */
    private static function getTableName(): string
    {
        return Config::get('cart.database.table', 'shoppingcart');
    }

    /**
     * Get the database connection name.
     */
    private function getConnectionName(): ?string
    {
        $connection = Config::get('cart.database.connection');

        return is_null($connection) ? Config::get('database.default') : $connection;
    }

    /**
     * Get the creation date of the cart (db context).
     *
     * @return \Carbon\Carbon|null
     */
    public function createdAt(): ?Carbon
    {
        return $this->createdAt;
    }

    /**
     * Get the lats update date of the cart (db context).
     *
     * @return \Carbon\Carbon|null
     */
    public function updatedAt(): ?Carbon
    {
        return $this->updatedAt;
    }
}
