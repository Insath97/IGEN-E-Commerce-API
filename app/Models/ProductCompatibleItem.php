<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCompatibleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'compatible_product_id'
    ];

    /**
     * Relationships
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function compatibleProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'compatible_product_id');
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
    public function getCompatibleProductNameAttribute()
    {
        return $this->compatibleProduct->name ?? null;
    }
}
