<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Quotation extends TenantAware
{
    protected $fillable = [
        'tenant_id', 'customer_id', 'user_id',
        'quotation_number', 'status', 'site_address', 'valid_until',
        'subtotal', 'discount', 'tax', 'delivery_fee', 'total', 'advance_paid',
        'notes', 'converted_sale_id',
    ];

    protected $casts = [
        'subtotal'      => 'decimal:2',
        'discount'      => 'decimal:2',
        'tax'           => 'decimal:2',
        'delivery_fee'  => 'decimal:2',
        'total'         => 'decimal:2',
        'advance_paid'  => 'decimal:2',
        'valid_until'  => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'converted_sale_id');
    }

    public function challans(): HasMany
    {
        return $this->hasMany(DeliveryChallan::class);
    }

    public static function generateNumber(string $tenantId): string
    {
        $date = now()->format('Ymd');
        $count = static::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereDate('created_at', today())
            ->count() + 1;

        return 'QUO-' . $date . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
    }

    public function getBalanceDueAttribute(): float
    {
        return max(0, (float) $this->total - (float) $this->advance_paid);
    }
}
