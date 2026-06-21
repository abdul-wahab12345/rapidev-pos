<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'purchase_order_id', 'product_id', 'variant_id',
        'product_name', 'variant_label',
        'quantity_ordered', 'quantity_received',
        'unit_cost', 'line_total',
    ];

    protected $casts = [
        'quantity_ordered'  => 'decimal:2',
        'quantity_received' => 'decimal:2',
        'unit_cost'   => 'decimal:2',
        'line_total'  => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (PurchaseOrderItem $i) {
            if (empty($i->id)) {
                $i->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function product(): BelongsTo       { return $this->belongsTo(Product::class); }
    public function variant(): BelongsTo       { return $this->belongsTo(ProductVariant::class, 'variant_id'); }
}
