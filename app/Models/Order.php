<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    /**
     * Cancellation policy settings (in days)
     * This can be moved to a settings table later.
     */
    const CANCELLATION_LIMIT_DAYS = 3;

    protected $fillable = [
        'order_number',
        'user_id',
        'customer_id',
        'checkout_session_id',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'shipping_fee',
        'total_amount',
        'coupon_id',
        'coupon_code',
        'delivery_address_id',
        'order_status',
        'notes',
        'cancellation_reason',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function checkoutSession()
    {
        return $this->belongsTo(CheckoutSession::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function deliveryAddress()
    {
        return $this->belongsTo(DeliveryAddress::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment()
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('order_status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('order_status', 'processing');
    }

    public function scopeShipped($query)
    {
        return $query->where('order_status', 'shipped');
    }

    public function scopeDelivered($query)
    {
        return $query->where('order_status', 'delivered');
    }

    public function scopeCancelled($query)
    {
        return $query->where('order_status', 'cancelled');
    }

    public function scopePaid($query)
    {
        return $query->whereHas('payments', function ($q) {
            $q->where('payment_status', 'completed');
        });
    }

    public function scopeUnpaid($query)
    {
        return $query->whereDoesntHave('payments', function ($q) {
            $q->where('payment_status', 'completed');
        });
    }

    /**
     * Business Logic
     */
    public function markAsPaid($paymentReference = null, $amount = null)
    {
        return $this->payments()->create([
            'payment_method' => 'card', // Defaulting for this helper, should be specific in controller
            'payment_status' => 'completed',
            'payment_reference' => $paymentReference,
            'amount' => $amount ?? $this->total_amount,
            'paid_amount' => $amount ?? $this->total_amount,
            'paid_at' => now(),
        ]);
    }

    public function markAsProcessing()
    {
        $this->update(['order_status' => 'processing']);
    }

    public function markAsShipped()
    {
        $this->update(['order_status' => 'shipped']);
    }

    public function markAsDelivered()
    {
        $this->update(['order_status' => 'delivered']);
    }

    public function cancel($reason = null)
    {
        if (!$this->canBeCancelled()) {
            throw new \Exception('This order cannot be cancelled.');
        }

        $this->update([
            'order_status' => 'cancelled',
            'cancellation_reason' => $reason,
        ]);
    }

    /**
     * Check if the order can be cancelled based on status and time limit
     */
    public function canBeCancelled(): bool
    {
        // 1. Check status
        if (in_array($this->order_status, ['shipped', 'delivered', 'cancelled'])) {
            return false;
        }

        // 2. Check time limit
        // In the future, you can get this value from a settings table:
        // $limitDays = Setting::get('order_cancellation_limit_days', self::CANCELLATION_LIMIT_DAYS);
        $limitDays = self::CANCELLATION_LIMIT_DAYS;

        if ($this->created_at->addDays($limitDays)->isPast()) {
            return false;
        }

        return true;
    }


}
