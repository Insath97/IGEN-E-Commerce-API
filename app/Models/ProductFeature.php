<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductFeature extends Model
{
    protected $table = 'product_features';

    protected $fillable = [
        'product_id',
        'feature_id',
    ];
}
