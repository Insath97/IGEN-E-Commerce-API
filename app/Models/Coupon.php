<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'min_purchase_amount',
        'start_date',
        'expiry_date',
        'usage_limit',
        'usage_limit_per_user',
        'used_count',
        'is_active',
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
        'min_purchase_amount' => 'decimal:2',
        'start_date' => 'date',
        'expiry_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Relationships
     */
  /*   public function usages()
    {
        return $this->hasMany(CouponUsage::class);
    } */

    public function tiers()
    {
        return $this->hasMany(CouponTier::class)->orderBy('priority', 'asc');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        $today = Carbon::today()->toDateString();
        return $query->where('is_active', true)
            ->where('start_date', '<=', $today)
            ->where('expiry_date', '>=', $today);
    }

    public function scopeValid($query)
    {
        $today = Carbon::today()->toDateString();
        return $query->where(function ($q) use ($today) {
            $q->whereNull('usage_limit')
                ->orWhereColumn('used_count', '<', 'usage_limit');
        })
            ->where('start_date', '<=', $today)
            ->where('expiry_date', '>=', $today)
            ->where('is_active', true);
    }

    public function scopeExpired($query)
    {
        $today = Carbon::today()->toDateString();
        return $query->where('expiry_date', '<', $today);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where('code', 'LIKE', "%{$search}%")
            ->orWhere('name', 'LIKE', "%{$search}%")
            ->orWhere('description', 'LIKE', "%{$search}%");
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('is_active', 'desc')
            ->orderBy('created_at', 'desc');
    }

    public function scopeFixed($query)
    {
        return $query->where('type', 'fixed');
    }

    public function scopePercentage($query)
    {
        return $query->where('type', 'percentage');
    }

    /**
     * Attributes
     */
    public function getIsValidAttribute()
    {
        $today = Carbon::today();

        return $this->is_active
            && $today->between($this->start_date, $this->expiry_date)
            && ($this->usage_limit === null || $this->used_count < $this->usage_limit);
    }

    public function getDaysRemainingAttribute()
    {
        return Carbon::today()->diffInDays($this->expiry_date, false);
    }

    public function getUsagePercentageAttribute()
    {
        if ($this->usage_limit === null) return 0;
        return ($this->used_count / $this->usage_limit) * 100;
    }

    /**
     * Validation Methods
     */
    public function isValidForUser($userId)
    {
        // Check global usage limit
        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }

        // Check per-user usage limit
        $userUsageCount = $this->usages()->where('user_id', $userId)->count();
        if ($userUsageCount >= $this->usage_limit_per_user) {
            return false;
        }

        return $this->is_valid;
    }

    public function activate()
    {
        return $this->update(['is_active' => true]);
    }

    public function deactivate()
    {
        return $this->update(['is_active' => false]);
    }
}

