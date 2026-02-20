<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class CheckoutSession extends Model
{
    protected $fillable = [
        'user_id',
        'session_id',
        'type',
        'cart_id',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'shipping_fee',
        'total_amount',
        'coupon_id',
        'coupon_code',
        'delivery_address_id',
        'status',
        'expires_at',
        'completed_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function items()
    {
        return $this->hasMany(CheckoutItem::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function deliveryAddress()
    {
        return $this->belongsTo(DeliveryAddress::class);
    }

    public function order()
    {
        return $this->hasOne(Order::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'active')
            ->where('expires_at', '<=', now());
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForSession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    /**
     * Business Logic
     */
    public function recalculateTotals()
    {
        $this->subtotal = $this->items->sum('total_price');
        $this->total_amount = $this->subtotal - $this->discount_amount + $this->tax_amount + $this->shipping_fee;
        $this->save();
    }

    public function applyCoupon(Coupon $coupon)
    {
        $discountAmount = $coupon->calculateDiscount($this->subtotal);
        
        $this->update([
            'coupon_id' => $coupon->id,
            'coupon_code' => $coupon->code,
            'discount_amount' => $discountAmount,
            'total_amount' => $this->subtotal - $discountAmount + $this->tax_amount + $this->shipping_fee,
        ]);
    }

    public function removeCoupon()
    {
        $this->update([
            'coupon_id' => null,
            'coupon_code' => null,
            'discount_amount' => 0,
            'total_amount' => $this->subtotal + $this->tax_amount + $this->shipping_fee,
        ]);
    }

    public function markAsCompleted()
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markAsExpired()
    {
        $this->update(['status' => 'expired']);
    }

    public function markAsAbandoned()
    {
        $this->update(['status' => 'abandoned']);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && !$this->isExpired();
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($checkoutSession) {
            // Set expiration time (24 hours from now)
            if (!$checkoutSession->expires_at) {
                $checkoutSession->expires_at = Carbon::now()->addHours(24);
            }
        });
    }
}
