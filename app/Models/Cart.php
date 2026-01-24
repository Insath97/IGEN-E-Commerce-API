<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'total_amount',
        'item_count',
        'session_id',
        'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function recalculateTotals()
    {
        $this->total_amount = $this->items()->sum('total_price');
        $this->item_count = $this->items()->count();
        $this->save();
    }

    public function isEmpty(): bool
    {
        return $this->items()->count() === 0;
    }

    public function isGuestCart(): bool
    {
        return is_null($this->user_id) && !empty($this->session_id);
    }

    public function isUserCart(): bool
    {
        return !is_null($this->user_id);
    }

    public function assignToUser(int $userId): void
    {
        $this->user_id = $userId;
        $this->save();
    }

    public function mergeCart(Cart $guestCart): void
    {
        if ($guestCart->isEmpty()) {
            return;
        }

        foreach ($guestCart->items as $guestItem) {
            $existingItem = $this->items()
                ->where('product_id', $guestItem->product_id)
                ->where('variant_id', $guestItem->variant_id)
                ->first();

            if ($existingItem) {
                $existingItem->quantity += $guestItem->quantity;
                $existingItem->save();
            } else {
                $this->items()->create([
                    'product_id' => $guestItem->product_id,
                    'variant_id' => $guestItem->variant_id,
                    'quantity' => $guestItem->quantity,
                    'unit_price' => $guestItem->unit_price,
                    'total_price' => $guestItem->total_price,
                ]);
            }
        }

        $this->recalculateTotals();
        $guestCart->delete();
    }
}
