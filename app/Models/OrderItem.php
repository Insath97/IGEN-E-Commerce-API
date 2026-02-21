<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'product_name',
        'variant_name',
        'sku',
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
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function review()
    {
        return $this->hasOne(ProductReview::class, 'order_item_id');
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            // Snapshot product data
            if ($item->product) {
                $item->product_name = $item->product->name;
            }
            
            if ($item->variant) {
                $item->variant_name = $item->variant->variant_name;
                $item->sku = $item->variant->sku;
            }
            
            // Auto-calculate total_price
            $item->total_price = $item->quantity * $item->unit_price;
        });
    }
}
