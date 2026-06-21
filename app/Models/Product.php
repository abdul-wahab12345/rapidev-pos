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
        // Marble / Tiles attributes
        'material_type', 'finish', 'origin', 'thickness_mm',
        'tile_width_in', 'tile_height_in', 'tiles_per_box', 'sq_m_per_box',
    ];

    protected $casts = [
        'cost_price'     => 'decimal:2',
        'selling_price'  => 'decimal:2',
        'has_variants'   => 'boolean',
        'is_active'      => 'boolean',
        'reorder_level'  => 'integer',
        'thickness_mm'   => 'decimal:2',
        'tile_width_in'  => 'decimal:2',
        'tile_height_in' => 'decimal:2',
        'tiles_per_box'  => 'integer',
        'sq_m_per_box'   => 'decimal:4',
    ];

    /**
     * sq_m_per_box is the source of truth for all tile area math and is entered
     * directly (read off the supplier's box — e.g. 1.44 m²). Inch dimensions are
     * NOMINAL/marketing sizes (24×48" ≈ 60×120 cm) used for labels only, so they
     * are NOT reliable for area: 24" = 60.96 cm, not 60 cm.
     *
     * Fallback only: if sq_m_per_box was left blank but inch dims exist, estimate
     * it from inches so legacy/quick entries still get a value.
     */
    protected static function booted(): void
    {
        parent::booted(); // ← must call this first so TenantAware sets UUID + tenant_id

        static::saving(function (Product $product) {
            if (
                empty($product->sq_m_per_box)
                && $product->tile_width_in && $product->tile_height_in && $product->tiles_per_box
            ) {
                $product->sq_m_per_box = round(
                    ($product->tile_width_in * 0.0254) * ($product->tile_height_in * 0.0254) * $product->tiles_per_box,
                    4
                );
            }
        });
    }

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
            $q->where('name',    'like', "%{$term}%")
              ->orWhere('name_ur', 'like', "%{$term}%")
              ->orWhere('sku',     'like', "%{$term}%")
              ->orWhere('barcode', 'like', "%{$term}%");
        });
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereHas('stockLevels', function ($q) {
            $q->whereRaw('quantity <= products.reorder_level');
        });
    }
}
