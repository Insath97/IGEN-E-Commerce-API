<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'variant_name',
        'sku',
        'barcode',
        'storage_size',
        'ram_size',
        'color',
        'price',
        'sales_price',
        'stock_quantity',
        'low_stock_threshold',
        'is_offer',
        'offer_price',
        'is_trending',
        'is_active',
        'is_featured',
        'created_by'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sales_price' => 'decimal:2',
        'offer_price' => 'decimal:2',
        'is_offer' => 'boolean',
        'is_trending' => 'boolean',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    /**
     * Relationships
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeTrending($query)
    {
        return $query->where('is_trending', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock_quantity', '>', 0);
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock_quantity', '<=', 'low_stock_threshold');
    }

    public function scopeOnOffer($query)
    {
        return $query->where('is_offer', true)
            ->whereNotNull('offer_price')
            ->where('offer_price', '>', 0);
    }

    /**
     * Helper Methods
     */
    public function getCurrentPriceAttribute()
    {
        return $this->offer_price ?? $this->sales_price ?? $this->price;
    }

    public function hasDiscount()
    {
        return $this->offer_price && $this->offer_price < $this->price;
    }

    public function discountPercentage()
    {
        if (!$this->hasDiscount()) {
            return 0;
        }

        return round((($this->price - $this->offer_price) / $this->price) * 100);
    }

    public function isOutOfStock()
    {
        return $this->stock_quantity <= 0;
    }

    public function isLowStock()
    {
        return $this->stock_quantity <= $this->low_stock_threshold;
    }

    public function activate()
    {
        $this->update(['is_active' => true]);
    }

    public function deactivate()
    {
        $this->update(['is_active' => false]);
    }

    public function toggleOffer()
    {
        $this->update(['is_offer' => !$this->is_offer]);
    }

    public function toggleTrending()
    {
        $this->update(['is_trending' => !$this->is_trending]);
    }

    public function toggleFeatured()
    {
        $this->update(['is_featured' => !$this->is_featured]);
    }
}
