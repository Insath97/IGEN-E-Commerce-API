<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'token',
        'refresh_token',
        'expires_at',
        'avatar',
        'provider_data'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'avatar' => 'array',
        'provider_data' => 'array'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
