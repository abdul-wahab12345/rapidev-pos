<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLevel extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id', 'branch_id', 'product_id', 'variant_id', 'quantity', 'reserved_qty',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'reserved_qty' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (StockLevel $stock) {
            if (empty($stock->id)) {
                $stock->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function getAvailableQuantityAttribute(): int
    {
        return $this->quantity - $this->reserved_qty;
    }
}
