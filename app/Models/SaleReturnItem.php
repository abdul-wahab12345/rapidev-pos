<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturnItem extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'sale_return_id', 'sale_item_id', 'product_id', 'variant_id',
        'product_name', 'variant_label',
        'quantity_returned', 'unit_price', 'line_total', 'restock',
    ];

    protected $casts = [
        'quantity_returned' => 'integer',
        'unit_price'        => 'integer',
        'line_total'        => 'integer',
        'restock'           => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (SaleReturnItem $item) {
            if (empty($item->id)) {
                $item->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function saleReturn(): BelongsTo { return $this->belongsTo(SaleReturn::class); }
    public function saleItem(): BelongsTo   { return $this->belongsTo(SaleItem::class); }
    public function product(): BelongsTo    { return $this->belongsTo(Product::class); }
}
