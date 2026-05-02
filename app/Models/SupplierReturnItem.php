<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierReturnItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'supplier_return_id',
        'purchase_order_item_id',
        'product_name',
        'variant_label',
        'quantity_returned',
        'unit_cost',
        'line_total',
    ];

    protected $casts = [
        'unit_cost'  => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function supplierReturn(): BelongsTo
    {
        return $this->belongsTo(SupplierReturn::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }
}
