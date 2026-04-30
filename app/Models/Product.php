<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends TenantAware
{
    protected $fillable = [
        'tenant_id', 'category_id', 'name', 'name_ur', 'sku', 'barcode',
        'description', 'unit', 'cost_price', 'selling_price',
        'has_variants', 'reorder_level', 'image', 'is_active',
    ];

    protected $casts = [
        'cost_price'     => 'decimal:2',
        'selling_price'  => 'decimal:2',
        'has_variants'   => 'boolean',
        'is_active'      => 'boolean',
        'reorder_level'  => 'integer',
    ];

    // Profit margin percentage
    public function getMarginAttribute(): float
    {
        if ((float) $this->cost_price === 0.0) return 0;
        return round((($this->selling_price - $this->cost_price) / $this->cost_price) * 100, 1);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    // Total stock across all branches
    public function getTotalStockAttribute(): int
    {
        return $this->stockLevels()->sum('quantity');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name',    'ilike', "%{$term}%")
              ->orWhere('name_ur', 'ilike', "%{$term}%")
              ->orWhere('sku',     'ilike', "%{$term}%")
              ->orWhere('barcode', 'ilike', "%{$term}%");
        });
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereHas('stockLevels', function ($q) {
            $q->whereRaw('quantity <= products.reorder_level');
        });
    }
}
