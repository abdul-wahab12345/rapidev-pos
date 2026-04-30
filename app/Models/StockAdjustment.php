<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustment extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id', 'branch_id', 'product_id', 'variant_id', 'user_id',
        'quantity_before', 'quantity_change', 'quantity_after',
        'reason', 'notes',
    ];

    protected static function booted(): void
    {
        static::creating(function (StockAdjustment $adj) {
            if (empty($adj->id)) {
                $adj->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function product(): BelongsTo  { return $this->belongsTo(Product::class); }
    public function variant(): BelongsTo  { return $this->belongsTo(ProductVariant::class, 'variant_id'); }
    public function branch(): BelongsTo   { return $this->belongsTo(Branch::class); }
    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
}
