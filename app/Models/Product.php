<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'brand_id',
        'name',
        'code',
        'slug',
        'primary_image_path',
        'type',
        'status',
        'short_description',
        'full_description',
        'is_trending',
        'is_active',
        'is_featured',
        'created_by'
    ];

    protected $casts = [
        'is_trending' => 'boolean',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'product_features');
    }

    public function specifications(): HasMany
    {
        return $this->hasMany(ProductSpecification::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'product_tag');
    }

/*     public function reviews()
    {
        return $this->hasMany(Review::class)->approved();
    }
 */
    public function compatibleProducts(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_compatible_items',
            'product_id',
            'compatible_product_id'
        )->withTimestamps();
    }

    public function bundledProducts(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_bundles',
            'product_id',
            'bundled_product_id'
        )->withTimestamps();
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

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeSearch($query, $search)
    {
        return $query->where('name', 'like', "%{$search}%")
            ->orWhere('slug', 'like', "%{$search}%")
            ->orWhere('code', 'like', "%{$search}%")
            ->orWhere('short_description', 'like', "%{$search}%");
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('is_featured', 'desc')
            ->orderBy('is_trending', 'desc')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Helper Methods
     */
    public function activate()
    {
        $this->update(['is_active' => true]);
    }

    public function deactivate()
    {
        $this->update(['is_active' => false]);
    }

    public function publish()
    {
        $this->update(['status' => 'published']);
    }

    public function archive()
    {
        $this->update(['status' => 'archived']);
    }

    public function setAsDraft()
    {
        $this->update(['status' => 'draft']);
    }

    public function toggleTrending()
    {
        $this->update(['is_trending' => !$this->is_trending]);
    }

    public function toggleFeatured()
    {
        $this->update(['is_featured' => !$this->is_featured]);
    }

 /*    public function getAverageRatingAttribute()
    {
        return $this->reviews()->avg('rating') ?? 0;
    }

    public function getProductAverageRatingAttribute()
    {
        return $this->productReviews()->avg('rating') ?? 0;
    }

    public function getTotalReviewsAttribute()
    {
        return $this->reviews()->count();
    }

    public function getProductReviewsCountAttribute()
    {
        return $this->productReviews()->count();
    }

    public function getVariantReviewsCountAttribute()
    {
        return $this->variantReviews()->count();
    }

    public function getRatingDistributionAttribute()
    {
        $distribution = [];
        for ($i = 1; $i <= 5; $i++) {
            $distribution[$i] = $this->reviews()->where('rating', $i)->count();
        }
        return $distribution;
    } */
}
