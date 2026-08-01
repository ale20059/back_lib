<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'sale_id', 'internal_product_id', 'quantity'];

    public function sale()
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function internalProduct()
    {
        return $this->belongsTo(InternalProduct::class, 'internal_product_id');
    }
}
