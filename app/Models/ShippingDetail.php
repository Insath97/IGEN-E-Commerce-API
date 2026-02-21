<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingDetail extends Model
{
    protected $fillable = [
        'order_id',
        'courier_name',
        'courier_phone',
        'tracking_number',
        'shipped_at',
        'estimated_delivery_at',
        'shipping_notes',
    ];

    protected $casts = [
        'shipped_at' => 'datetime',
        'estimated_delivery_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
