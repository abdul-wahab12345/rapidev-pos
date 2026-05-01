<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturn extends TenantAware
{
    protected $fillable = [
        'tenant_id', 'branch_id', 'sale_id', 'created_by',
        'return_number', 'return_date', 'reason', 'refund_method',
        'total_refund', 'notes', 'status',
    ];

    protected $casts = [
        'return_date'  => 'date',
        'total_refund' => 'integer',
    ];

    public static function nextNumber(string $tenantId): string
    {
        $last = static::where('tenant_id', $tenantId)
            ->withTrashed()
            ->orderByDesc('return_number')
            ->value('return_number');

        $n = $last ? ((int) substr($last, 4)) + 1 : 1;
        return 'RET-' . str_pad($n, 5, '0', STR_PAD_LEFT);
    }

    public function items(): HasMany    { return $this->hasMany(SaleReturnItem::class); }
    public function sale(): BelongsTo   { return $this->belongsTo(Sale::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
}
