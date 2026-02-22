<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckoutItem extends Model
{
    protected $fillable = [
        'checkout_session_id',
        'product_id',
        'variant_id',
        'cart_item_id',
        'quantity',
        'unit_price',
        'total_price',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    /**
     * Relationships
     */
    public function checkoutSession()
    {
        return $this->belongsTo(CheckoutSession::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function cartItem()
    {
        return $this->belongsTo(CartItem::class);
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            // Auto-calculate total_price
            $item->total_price = $item->quantity * $item->unit_price;
        });
    }
}
