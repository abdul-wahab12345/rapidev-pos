<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends TenantAware
{
    protected $fillable = [
        'tenant_id', 'branch_id', 'user_id', 'customer_id', 'rate_list_id', 'invoice_number',
        'status', 'subtotal', 'discount', 'tax', 'total', 'paid', 'change_amount',
        'cash_amount', 'jazzcash_amount', 'easypaisa_amount', 'bank_amount', 'udhaar_amount',
        'payment_method', 'notes',
    ];

    protected $casts = [
        'subtotal'         => 'decimal:2',
        'discount'         => 'decimal:2',
        'tax'              => 'decimal:2',
        'total'            => 'decimal:2',
        'paid'             => 'decimal:2',
        'change_amount'    => 'decimal:2',
        'cash_amount'      => 'decimal:2',
        'jazzcash_amount'  => 'decimal:2',
        'easypaisa_amount' => 'decimal:2',
        'bank_amount'      => 'decimal:2',
        'udhaar_amount'    => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public static function generateInvoiceNumber(string $tenantId): string
    {
        $date = now()->format('Ymd');
        $count = static::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereDate('created_at', today())
            ->count() + 1;

        return 'INV-' . $date . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
