<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CouponTier extends Model
{
    protected $fillable = [
        'coupon_id',
        'min_amount',
        'max_amount',
        'percentage',
        'priority',
    ];

    protected $casts = [
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'percentage' => 'decimal:2',
        'priority' => 'integer',
    ];

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }
}
