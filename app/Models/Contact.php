<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'subject',
        'message',
        'status',
        'is_replied',
        'replied_by',
        'replied_at',
        'reply_message',
    ];

    /**
     * Get the user who replied to the contact inquiry.
     */
    public function repliedBy()
    {
        return $this->belongsTo(User::class, 'replied_by');
    }
}
