<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderPayment extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'purchase_order_id',
        'amount',
        'payment_method',
        'notes',
        'is_voided',
        'created_by',
    ];

    protected $casts = [
        'amount'    => 'decimal:2',
        'is_voided' => 'boolean',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
