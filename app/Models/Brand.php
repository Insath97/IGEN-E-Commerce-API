<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'logo',
        'slug',
        'website',
        'description',
        'is_active',
        'is_featured',
        'created_by'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where('name', 'like', "%{$search}%")
            ->orWhere('slug', 'LIKE', "%{$search}%")
            ->orWhere('description', 'like', "%{$search}%");
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('is_featured', 'desc')
            ->orderBy('name', 'asc');
    }

    /**
     * Get the user who created the brand.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activate()
    {
        $this->update(['is_active' => true]);
    }

    public function deactivate()
    {
        $this->update(['is_active' => false]);
    }
}
