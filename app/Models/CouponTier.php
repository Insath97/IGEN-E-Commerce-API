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

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }
}
