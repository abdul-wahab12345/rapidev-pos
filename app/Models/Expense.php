<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends TenantAware
{
    protected $fillable = [
        'tenant_id', 'branch_id', 'account_id', 'created_by',
        'expense_number', 'expense_date', 'amount',
        'payment_method', 'description', 'notes', 'reference',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount'       => 'decimal:2',
    ];

    public static function nextNumber(string $tenantId): string
    {
        $last = static::where('tenant_id', $tenantId)
            ->withTrashed()
            ->orderByDesc('expense_number')
            ->value('expense_number');

        $n = $last ? ((int) substr($last, 4)) + 1 : 1;
        return 'EXP-' . str_pad($n, 5, '0', STR_PAD_LEFT);
    }

    public function account(): BelongsTo  { return $this->belongsTo(Account::class); }
    public function branch(): BelongsTo   { return $this->belongsTo(Branch::class); }
    public function creator(): BelongsTo  { return $this->belongsTo(User::class, 'created_by'); }
}
