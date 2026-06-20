<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'sale_id', 'product_id', 'variant_id', 'product_name', 'variant_label',
        'quantity', 'unit_price', 'cost_price', 'discount', 'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'integer',
        'cost_price' => 'integer',
        'discount' => 'integer',
        'line_total' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (SaleItem $item) {
            if (empty($item->id)) {
                $item->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
