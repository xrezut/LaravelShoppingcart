<?php

namespace Gloudemans\Tests\Shoppingcart\Fixtures;

use Illuminate\Database\Eloquent\Model;

class ProductModel extends Model
{
    public $someValue = 'Some value';

    public function find($id): self
    {
        return $this;
    }
}
