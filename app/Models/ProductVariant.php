<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'product_id', 'size', 'color', 'sku', 'barcode',
        'cost_price', 'selling_price', 'is_active',
    ];

    protected $casts = [
        'cost_price' => 'integer',
        'selling_price' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (ProductVariant $variant) {
            if (empty($variant->id)) {
                $variant->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class, 'variant_id');
    }

    public function getLabelAttribute(): string
    {
        return implode(' / ', array_filter([$this->size, $this->color]));
    }
}
