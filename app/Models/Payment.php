<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'checkout_session_id',
        'payment_method',
        'payment_status',
        'amount',
        'paid_amount',
        'change_due',
        'currency',
        'transaction_id',
        'payment_reference',
        'gateway_reference',
        'bank_name',
        'account_number',
        'account_holder_name',
        'slip_path',
        'transfer_date',
        'delivered_to',
        'delivery_notes',
        'gateway_response',
        'ip_address',
        'notes',
        'paid_at',
        'failed_at',
        'verified_by',
        'verified_at',
        'verification_notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'change_due' => 'decimal:2',
        'gateway_response' => 'array',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'verified_at' => 'datetime',
        'transfer_date' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function checkoutSession()
    {
        return $this->belongsTo(CheckoutSession::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
