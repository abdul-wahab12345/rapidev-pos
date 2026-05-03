<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RateListItem extends Model
{
    protected $fillable = [
        'rate_list_id',
        'product_id',
        'variant_id',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function rateList(): BelongsTo
    {
        return $this->belongsTo(RateList::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
