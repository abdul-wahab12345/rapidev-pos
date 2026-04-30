<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id', 'branch_id', 'supplier_id', 'created_by',
        'po_number', 'order_date', 'expected_date', 'received_date',
        'status', 'subtotal', 'discount', 'tax', 'total', 'paid_amount',
        'payment_method', 'notes',
    ];

    protected $casts = [
        'order_date'    => 'date',
        'expected_date' => 'date',
        'received_date' => 'date',
        'subtotal'      => 'decimal:2',
        'discount'      => 'decimal:2',
        'tax'           => 'decimal:2',
        'total'         => 'decimal:2',
        'paid_amount'   => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (PurchaseOrder $po) {
            if (empty($po->id)) {
                $po->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public static function nextNumber(string $tenantId): string
    {
        $last = static::where('tenant_id', $tenantId)
            ->withTrashed()
            ->orderByDesc('po_number')
            ->value('po_number');

        $n = $last ? ((int) substr($last, 3)) + 1 : 1;
        return 'PO-' . str_pad($n, 5, '0', STR_PAD_LEFT);
    }

    public function amountDue(): float
    {
        return max(0, (float) $this->total - (float) $this->paid_amount);
    }

    public function supplier(): BelongsTo  { return $this->belongsTo(Supplier::class); }
    public function branch(): BelongsTo    { return $this->belongsTo(Branch::class); }
    public function creator(): BelongsTo   { return $this->belongsTo(User::class, 'created_by'); }
    public function items(): HasMany       { return $this->hasMany(PurchaseOrderItem::class); }
}
