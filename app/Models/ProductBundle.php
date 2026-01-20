<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBundle extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'bundled_product_id'
    ];

    /**
     * Relationships
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function bundledProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'bundled_product_id');
    }

    /**
     * Scopes
     */
    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Helper Methods
     */
    public function getBundledProductNameAttribute()
    {
        return $this->bundledProduct->name ?? null;
    }
}
