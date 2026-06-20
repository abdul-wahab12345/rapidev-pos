<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLevel extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id', 'branch_id', 'product_id', 'variant_id', 'lot_number',
        'quantity', 'reserved_qty',
        'boxes_count', 'loose_tiles_count', 'box_count_at',
    ];

    protected $casts = [
        'quantity'          => 'decimal:2',
        'reserved_qty'      => 'decimal:2',
        'boxes_count'       => 'integer',
        'loose_tiles_count' => 'integer',
        'box_count_at'      => 'datetime',
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

    public function getAvailableQuantityAttribute(): float
    {
        return (float) $this->quantity - (float) $this->reserved_qty;
    }
}
