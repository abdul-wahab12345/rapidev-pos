<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

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
        'order_date' => 'date',
        'expected_date' => 'date',
        'received_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (PurchaseOrder $po) {
            if (empty($po->id)) {
                $po->id = (string) Str::uuid();
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

        return 'PO-'.str_pad($n, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Sum of supplier return amounts against this PO (excludes soft-deleted returns via model scope).
     */
    public function returnedAmount(): float
    {
        $attrs = $this->getAttributes();
        if (array_key_exists('supplier_returns_sum_total_amount', $attrs)) {
            return (float) ($attrs['supplier_returns_sum_total_amount'] ?? 0);
        }

        if ($this->relationLoaded('supplierReturns')) {
            return (float) $this->supplierReturns->sum('total_amount');
        }

        return (float) $this->supplierReturns()->sum('total_amount');
    }

    /** Net obligation for goods kept after supplier returns / debit notes. */
    public function netPurchaseTotal(): float
    {
        return max(0.0, (float) $this->total - $this->returnedAmount());
    }

    public function amountDue(): float
    {
        return max(0.0, $this->netPurchaseTotal() - (float) $this->paid_amount);
    }

    /** Dashboard / list stats — open PO liabilities after returns across a tenant (same statuses as index stats). */
    public static function openAmountDueAggregate(string $tenantId): float
    {
        $returnsSub = SupplierReturn::query()
            ->selectRaw('purchase_order_id, sum(total_amount) as return_total')
            ->groupBy('purchase_order_id');

        return (float) static::query()
            ->where('purchase_orders.tenant_id', $tenantId)
            ->whereNull('purchase_orders.deleted_at')
            ->whereIn('purchase_orders.status', ['received', 'partial', 'ordered'])
            ->leftJoinSub($returnsSub, 'sr', 'purchase_orders.id', '=', 'sr.purchase_order_id')
            ->selectRaw('coalesce(sum(case when purchase_orders.total - coalesce(sr.return_total, 0) - purchase_orders.paid_amount > 0 then purchase_orders.total - coalesce(sr.return_total, 0) - purchase_orders.paid_amount else 0 end), 0) as agg')
            ->value('agg');
    }

    /**
     * Earliest PO order_date where amountDue() > 0, per supplier — for aging / dashboards.
     *
     * @param  iterable<string>  $supplierIds
     * @return array<string, CarbonInterface|null>
     */
    public static function oldestDueOrderDatesBySupplierIds(iterable $supplierIds): array
    {
        $ids = collect($supplierIds)->unique()->filter()->values();
        $map = [];

        foreach ($ids as $sid) {
            $map[(string) $sid] = null;
        }

        if ($ids->isEmpty()) {
            return $map;
        }

        $pos = static::query()
            ->whereIn('supplier_id', $ids->all())
            ->whereIn('status', ['ordered', 'partial', 'received'])
            ->withSum('supplierReturns', 'total_amount')
            ->orderBy('order_date')
            ->get(['supplier_id', 'order_date', 'total', 'paid_amount']);

        foreach ($pos->groupBy('supplier_id') as $supplierId => $group) {
            $hit = $group->sortBy('order_date')->first(fn ($po) => $po->amountDue() > 0);
            if ($hit !== null && $hit->order_date !== null) {
                $map[(string) $supplierId] = $hit->order_date;
            }
        }

        return $map;
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function supplierReturns(): HasMany
    {
        return $this->hasMany(SupplierReturn::class);
    }
}
